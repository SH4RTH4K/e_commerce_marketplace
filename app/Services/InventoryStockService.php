<?php

namespace App\Services;

use App\Models\DropshipProductLink;
use App\Models\DropshipSupplierProduct;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryStockService
{
    public function adjust(User $actor, array $items, string $operation, int $quantity, string $reason, bool $protect): int
    {
        return DB::transaction(function () use ($actor, $items, $operation, $quantity, $reason, $protect) {
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
                $productLinks = $links->where('product_id', $product->id);
                $automatic = $productLinks->contains(fn ($link) => $link->sync_status === 'active' && ($link->field_sync_rules['stock'] ?? false) === true);
                if ($after === $before && ! ($protect && $automatic)) continue;
                if ($protect) {
                    foreach ($productLinks as $link) {
                        $link->update(['field_sync_rules' => array_merge($link->field_sync_rules ?? [], ['stock' => false])]);
                    }
                }
                $product->update(['stock_quantity' => $after]);
                $this->record($actor, $product, $operation, $before, $after, $reason, $protect && $productLinks->isNotEmpty());
                $count++;
            }
            return $count;
        });
    }

    public function resume(User $actor, Product $product): void
    {
        DB::transaction(function () use ($actor, $product) {
            $links = $this->lockLinks([$product->id])->where('sync_status', 'active');
            if ($links->isEmpty()) throw ValidationException::withMessages(['stock_sync' => 'This product has no active supplier connection.']);
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            foreach ($links as $link) {
                $link->update(['field_sync_rules' => array_merge($link->field_sync_rules ?? [], ['stock' => true])]);
            }
            $this->record($actor, $product, 'sync_resume', $product->stock_quantity, $product->stock_quantity, 'Supplier stock sync resumed', false);
        });
    }

    private function lockLinks(array $ids)
    {
        $links = DropshipProductLink::whereIn('product_id', $ids)->orderBy('id')->get(['id', 'supplier_product_row_id']);
        DropshipSupplierProduct::whereIn('id', $links->pluck('supplier_product_row_id'))->orderBy('id')->lockForUpdate()->get();
        return DropshipProductLink::whereIn('id', $links->pluck('id'))->orderBy('id')->lockForUpdate()->get();
    }

    private function record(User $actor, Product $product, string $operation, int $before, int $after, string $reason, bool $paused): void
    {
        InventoryAdjustment::create([
            'product_id' => $product->id, 'user_id' => $actor->id,
            'product_name' => $product->name, 'sku' => $product->sku,
            'operation' => $operation, 'stock_before' => $before, 'stock_after' => $after,
            'change' => $after - $before, 'reason' => $reason, 'supplier_sync_paused' => $paused,
        ]);
    }
}
