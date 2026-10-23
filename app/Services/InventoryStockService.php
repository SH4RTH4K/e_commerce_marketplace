<?php

namespace App\Services;

use App\Models\DropshipProductLink;
use App\Models\DropshipSupplier;
use App\Models\DropshipSupplierProduct;
use App\Models\InventoryAdjustment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryStockService
{
    public function adjust(User $actor, array $items, string $operation, int $quantity, string $reason, bool $protect, array $context = []): int
    {
        $movement = $context['movement_type'] ?? match ($operation) {
            'add' => 'stock_in', 'remove' => 'stock_out', default => 'adjustment'
        };
        $expectedOperation = match ($movement) {
            'opening', 'stock_in', 'return_in' => 'add', 'stock_out', 'damaged' => 'remove', 'adjustment' => 'set', default => null
        };
        if ($expectedOperation !== $operation || $quantity < ($operation === 'set' ? 0 : 1)) {
            throw ValidationException::withMessages(['movement_type' => 'Choose a movement type that matches the quantity operation.']);
        }
        if (isset($context['supplier_id']) && ! DropshipSupplier::whereKey($context['supplier_id'])->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['supplier_id' => 'Choose an active supplier for this stock movement.']);
        }

        return DB::transaction(function () use ($actor, $items, $operation, $quantity, $reason, $protect, $context, $movement) {
            $links = $this->lockLinks(array_column($items, 'product_id'));
            $products = Product::whereIn('id', array_column($items, 'product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $count = 0;
            foreach ($items as $item) {
                $product = $products->get($item['product_id']);
                if (! $product || (int) $product->stock_quantity !== (int) $item['expected_stock']) {
                    throw ValidationException::withMessages(['items' => 'Stock changed while you were editing. Refresh inventory and review the current quantities before saving.']);
                }
                $before = (int) $product->stock_quantity;
                $after = match ($operation) {
                    'add' => $before + $quantity, 'remove' => $before - $quantity, default => $quantity,
                };
                if ($after < 0 || $after > 4_294_967_295) {
                    throw ValidationException::withMessages(['quantity' => "The adjustment gives an invalid stock quantity for {$product->name}."]);
                }
                $productLinks = $links->where('product_id', $product->id)->where('product_created_by_integration', true);
                if ($movement === 'opening' && ($product->source_type === 'dropshipping' || $productLinks->contains('product_created_by_integration', true))) {
                    throw ValidationException::withMessages(['movement_type' => 'Opening warehouse stock is only available for locally held products.']);
                }
                if ($movement === 'opening' && ($before !== 0 || InventoryAdjustment::where('product_id', $product->id)->exists())) {
                    throw ValidationException::withMessages(['movement_type' => 'Opening stock is only available before any stock movements and with a zero balance. Use Stock in instead.']);
                }
                $automatic = $productLinks->contains(fn ($link) => $link->sync_status === 'active' && ($link->field_sync_rules['stock'] ?? false) === true);
                if ($after === $before && ! ($protect && $automatic)) {
                    continue;
                }
                if ($protect) {
                    foreach ($productLinks as $link) {
                        $link->update(['field_sync_rules' => array_merge($link->field_sync_rules ?? [], ['stock' => false])]);
                    }
                }
                $product->update(['stock_quantity' => $after]);
                $this->record($actor, $product, $operation, $before, $after, $reason, $protect && $productLinks->isNotEmpty(), [
                    ...$context, 'movement_type' => $movement,
                    'stock_scope' => $product->source_type === 'dropshipping' || $productLinks->contains('product_created_by_integration', true) ? 'supplier' : 'local',
                ]);
                $count++;
            }

            return $count;
        });
    }

    /** Reserve availability and retain an item-specific supplier snapshot atomically. */
    public function reserveOrderStock(?User $actor, Product $product, Order $order, OrderItem $item, bool $allowPartial = false): void
    {
        DB::transaction(function () use ($actor, $product, $order, $item, $allowPartial) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $item = OrderItem::whereKey($item->id)->where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            $reference = $this->orderReference($order, $item);
            if ($item->reserved_stock_quantity !== null || InventoryAdjustment::where('product_id', $product->id)->where('reference', $reference)->where('operation', 'remove')->lockForUpdate()->first()) {
                return;
            }

            $before = (int) $product->stock_quantity;
            $localBefore = (int) $product->sourced_stock_quantity;
            $available = $product->availableStockQuantity();
            $quantity = $allowPartial || $product->allow_backorder ? min((int) $item->quantity, $available) : (int) $item->quantity;
            if ($quantity < 0 || $quantity > $available) {
                throw new \RuntimeException("{$product->name} only has {$available} in stock.");
            }
            $localReserved = min($quantity, $localBefore);
            $standardReserved = $quantity - $localReserved;
            if ($standardReserved > 0) {
                $product->decrement('stock_quantity', $standardReserved);
            }
            if ($localReserved > 0) {
                $product->decrement('sourced_stock_quantity', $localReserved);
            }
            $item->forceFill([
                'reserved_stock_quantity' => $quantity,
                'reserved_sourced_quantity' => $localReserved,
                'sourcing_required' => $product->allow_backorder && $quantity < (int) $item->quantity,
            ])->save();
            $this->record($actor, $product, 'remove', $before, $before - $standardReserved, $quantity > 0 ? 'Order stock reserved' : 'Order placed without reserved stock', false, [
                'movement_type' => 'stock_out', 'reference' => $reference,
                'stock_scope' => $this->stockScope($product), 'actor_name' => $actor?->name ?? 'Guest checkout',
            ]);
            if ($localReserved > 0) {
                $this->record($actor, $product, 'remove', $localBefore, $localBefore - $localReserved, 'Locally received stock allocated to order', false, [
                    'movement_type' => 'stock_out', 'reference' => $reference.' / sourced', 'stock_scope' => 'local',
                ]);
            }
        });
    }

    /** Receive physical goods and allocate them to one item under the same locks. */
    public function receiveForOrder(User $actor, Order $order, OrderItem $item, int $quantity, int $expectedOutstanding, ?string $reference = null): void
    {
        DB::transaction(function () use ($actor, $order, $item, $quantity, $expectedOutstanding, $reference): void {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $item = OrderItem::whereKey($item->id)->where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            if ($order->status !== 'pending' || ! $item->sourcing_required) {
                throw ValidationException::withMessages(['quantity' => 'Only pending orders that require sourcing can receive stock.']);
            }
            $outstanding = $item->sourcing_outstanding;
            if ($outstanding !== $expectedOutstanding || $quantity < 1 || $quantity > $outstanding) {
                throw ValidationException::withMessages(['quantity' => 'The outstanding quantity changed. Refresh the order before recording a receipt.']);
            }
            $product = Product::whereKey($item->product_id)->lockForUpdate()->first();
            if (! $product) {
                throw ValidationException::withMessages(['quantity' => 'The ordered product no longer exists.']);
            }
            $before = (int) $product->sourced_stock_quantity;
            if ($before + $quantity > 4_294_967_295) {
                throw ValidationException::withMessages(['quantity' => 'The receipt exceeds the supported warehouse balance.']);
            }
            $movementReference = mb_substr($this->orderReference($order, $item).' / receipt '.((int) $item->reserved_stock_quantity + $quantity), 0, 120);
            $reason = 'Physical stock received for order'.($reference ? ': '.$reference : '');
            $product->increment('sourced_stock_quantity', $quantity);
            $this->record($actor, $product, 'add', $before, $before + $quantity, $reason, false, [
                'movement_type' => 'stock_in', 'reference' => $movementReference, 'stock_scope' => 'local',
            ]);
            $product->decrement('sourced_stock_quantity', $quantity);
            $this->record($actor, $product, 'remove', $before + $quantity, $before, 'Received stock allocated to order', false, [
                'movement_type' => 'stock_out', 'reference' => $movementReference, 'stock_scope' => 'local',
            ]);
            $item->forceFill([
                'reserved_stock_quantity' => (int) $item->reserved_stock_quantity + $quantity,
                'reserved_sourced_quantity' => (int) $item->reserved_sourced_quantity + $quantity,
            ])->save();
        }, 3);
    }

    /** The order lock and item references prevent duplicate cancellation releases. */
    public function restoreOrderStock(?User $actor, Order $order): void
    {
        DB::transaction(function () use ($actor, $order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            foreach ($order->items()->orderBy('product_id')->orderBy('id')->get() as $item) {
                if (! $item->product_id) {
                    continue;
                }
                $product = Product::whereKey($item->product_id)->lockForUpdate()->first();
                if (! $product) {
                    continue;
                }
                $reference = $this->orderReference($order, $item);
                if (InventoryAdjustment::where('product_id', $product->id)->where('reference', $reference)->where('movement_type', 'return_in')->lockForUpdate()->first()) {
                    continue;
                }
                $reservation = InventoryAdjustment::where('product_id', $product->id)->where('reference', $reference)->where('operation', 'remove')->lockForUpdate()->first();
                $localQuantity = $item->reserved_stock_quantity !== null ? (int) $item->reserved_sourced_quantity : 0;
                $quantity = $item->reserved_stock_quantity !== null
                    ? max(0, (int) $item->reserved_stock_quantity - $localQuantity)
                    : ($reservation ? max(0, -$reservation->change) : (int) $item->quantity);
                $before = (int) $product->stock_quantity;
                if ($quantity > 0) {
                    $product->increment('stock_quantity', $quantity);
                }
                $context = ['movement_type' => 'return_in', 'reference' => $reference, 'stock_scope' => $reservation?->stock_scope ?? $this->stockScope($product), 'actor_name' => $actor?->name ?? 'System'];
                if ($reservation) {
                    $context['supplier_id'] = $reservation->supplier_id;
                }
                $this->record($actor, $product, 'add', $before, $before + $quantity, 'Order reservation released', false, $context);
                if ($localQuantity > 0) {
                    $localBefore = (int) $product->sourced_stock_quantity;
                    $product->increment('sourced_stock_quantity', $localQuantity);
                    $this->record($actor, $product, 'add', $localBefore, $localBefore + $localQuantity, 'Locally received allocation released', false, [
                        'movement_type' => 'return_in', 'reference' => $reference.' / sourced', 'stock_scope' => 'local',
                    ]);
                }
            }
        });
    }

    private function orderReference(Order $order, OrderItem $item): string
    {
        return mb_substr('Order '.$order->order_number.' / item '.$item->id, 0, 120);
    }

    private function stockScope(Product $product): string
    {
        return $product->source_type === 'dropshipping' || $product->supplierLinks()->where('product_created_by_integration', true)->exists() ? 'supplier' : 'local';
    }

    public function recordOpening(User $actor, Product $product, ?string $reference = null): void
    {
        DB::transaction(function () use ($actor, $product, $reference) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            if ($product->source_type === 'dropshipping' || $product->supplierLinks()->where('product_created_by_integration', true)->exists() || (int) $product->stock_quantity <= 0
                || InventoryAdjustment::where('product_id', $product->id)->exists()) {
                return;
            }

            $this->record($actor, $product, 'add', 0, (int) $product->stock_quantity, 'Opening stock', false, [
                'movement_type' => 'opening', 'reference' => $reference, 'stock_scope' => 'local',
            ]);
        });
    }

    /** Called inside the product editor's existing transaction and row lock. */
    public function recordProductEdit(User $actor, Product $product, int $before, ?string $reference = null): void
    {
        $after = (int) $product->stock_quantity;
        if ($before === $after) {
            return;
        }

        $this->record($actor, $product, 'set', $before, $after, 'Quantity updated in product editor', false, [
            'movement_type' => 'adjustment', 'reference' => $reference,
            'stock_scope' => $product->source_type === 'dropshipping' || $product->supplierLinks()->where('product_created_by_integration', true)->exists() ? 'supplier' : 'local',
        ]);
    }

    public function resume(User $actor, Product $product): void
    {
        DB::transaction(function () use ($actor, $product) {
            $links = $this->lockLinks([$product->id])->where('product_created_by_integration', true)->where('sync_status', 'active');
            if ($links->isEmpty()) {
                throw ValidationException::withMessages(['stock_sync' => 'This product has no active supplier connection.']);
            }
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            foreach ($links as $link) {
                $link->update(['field_sync_rules' => array_merge($link->field_sync_rules ?? [], ['stock' => true])]);
            }
            $this->record($actor, $product, 'sync_resume', $product->stock_quantity, $product->stock_quantity, 'Supplier stock sync resumed', false, ['stock_scope' => 'supplier']);
        });
    }

    private function lockLinks(array $ids)
    {
        $links = DropshipProductLink::whereIn('product_id', $ids)->orderBy('id')->get(['id', 'supplier_product_row_id']);
        DropshipSupplierProduct::whereIn('id', $links->pluck('supplier_product_row_id'))->orderBy('id')->lockForUpdate()->get();

        return DropshipProductLink::whereIn('id', $links->pluck('id'))->orderBy('id')->lockForUpdate()->get();
    }

    private function record(?User $actor, Product $product, string $operation, int $before, int $after, string $reason, bool $paused, array $context = []): void
    {
        InventoryAdjustment::create([
            'product_id' => $product->id, 'user_id' => $actor?->id,
            'product_name' => $product->name, 'sku' => $product->sku,
            'operation' => $operation, 'stock_before' => $before, 'stock_after' => $after,
            'change' => $after - $before, 'reason' => $reason, 'supplier_sync_paused' => $paused,
            'supplier_id' => array_key_exists('supplier_id', $context) ? $context['supplier_id'] : ($product->supplier_id ?? $product->supplierLinks()->where('product_created_by_integration', true)->with('supplierProduct')->first()?->supplierProduct?->supplier_id),
            'movement_type' => $context['movement_type'] ?? null, 'reference' => $context['reference'] ?? null,
            'stock_scope' => $context['stock_scope'] ?? 'local',
            'actor_name' => $context['actor_name'] ?? $actor?->name,
        ]);
    }
}
