<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DropshipSupplier;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Services\InventoryStockService;
use App\Services\ProductPricingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        [$query, $filters] = $this->filteredQuery($request, false);
        $totals = (clone $query)->selectRaw('COUNT(*) AS total, SUM(CASE WHEN stock_quantity > 5 THEN 1 ELSE 0 END) AS in_stock, SUM(CASE WHEN stock_quantity BETWEEN 1 AND 5 THEN 1 ELSE 0 END) AS low_stock, SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) AS out_stock')->first();
        $localUnits = (clone $query)->where('source_type', '!=', 'dropshipping')->whereDoesntHave('supplierLinks', fn ($link) => $link->where('product_created_by_integration', true))->sum('stock_quantity');
        $supplierUnits = (clone $query)->where(fn ($q) => $q->where('source_type', 'dropshipping')->orWhereHas('supplierLinks', fn ($link) => $link->where('product_created_by_integration', true)))->sum('stock_quantity');
        $this->applyStockFilter($query, $filters['stock_level']);
        $matchingUnits = (clone $query)->where('source_type', '!=', 'dropshipping')->whereDoesntHave('supplierLinks', fn ($link) => $link->where('product_created_by_integration', true))->sum('stock_quantity');
        $matchingSupplierUnits = (clone $query)->where(fn ($q) => $q->where('source_type', 'dropshipping')->orWhereHas('supplierLinks', fn ($link) => $link->where('product_created_by_integration', true)))->sum('stock_quantity');
        $this->applySort($query, $filters['order']);
        $query->with('category', 'images', 'supplier', 'supplierLinks.supplierProduct.supplier')->withCount('variants');
        return Inertia::render('Admin/Inventory/Index', [
            ...$filters,
            'products' => $query->paginate($filters['per_page'])->withQueryString()->through(fn ($product) => $this->row($product)),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'suppliers' => DropshipSupplier::orderBy('name')->get(['id', 'name', 'is_active']),
            'summary' => [...collect(['total', 'in_stock', 'low_stock', 'out_stock'])->mapWithKeys(fn ($key) => [$key => (int) $totals->$key])->all(), 'units' => (int) $localUnits, 'supplier_units' => (int) $supplierUnits],
            'matching_units' => (int) $matchingUnits,
            'matching_supplier_units' => (int) $matchingSupplierUnits,
            'can_adjust' => $request->user()->hasPermission('products.create'),
        ]);
    }

    public function adjust(Request $request, InventoryStockService $stock)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.expected_stock' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'operation' => ['required', 'in:add,remove,set'],
            'quantity' => ['required', 'integer', 'min:'.($request->input('operation') === 'set' ? 0 : 1), 'max:4294967295'],
            'reason' => ['required', 'string', 'max:500'],
            'protect_supplier_stock' => ['required', 'boolean'],
            'movement_type' => ['sometimes', 'in:opening,stock_in,stock_out,adjustment,return_in,damaged'],
            'reference' => ['nullable', 'string', 'max:120'],
            'supplier_id' => ['sometimes', 'nullable', 'integer', Rule::exists('dropship_suppliers', 'id')->where('is_active', true)],
        ]);
        $count = $stock->adjust($request->user(), $data['items'], $data['operation'], $data['quantity'], $data['reason'], (bool) $data['protect_supplier_stock'], array_intersect_key($data, array_flip(['movement_type', 'reference', 'supplier_id'])));
        return back()->with('status', "Stock adjusted for {$count} product(s).");
    }

    public function updateStock(Request $request, Product $product, InventoryStockService $stock)
    {
        $data = $request->validate([
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'expected_stock' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'reason' => ['required', 'string', 'max:500'], 'protect_supplier_stock' => ['required', 'boolean'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);
        $stock->adjust($request->user(), [['product_id' => $product->id, 'expected_stock' => $data['expected_stock']]], 'set', $data['stock_quantity'], $data['reason'], (bool) $data['protect_supplier_stock'], ['reference' => $data['reference'] ?? null]);
        return back()->with('status', 'Stock adjustment saved.');
    }

    public function history(Product $product)
    {
        return response()->json(InventoryAdjustment::where('product_id', $product->id)->with('user:id,name', 'supplier:id,name,supplier_code')->latest('id')->paginate(15)->through(fn ($adjustment) => [
            'id' => $adjustment->id, 'operation' => $adjustment->operation,
            'before' => $adjustment->stock_before, 'after' => $adjustment->stock_after, 'change' => $adjustment->change,
            'reason' => $adjustment->reason, 'actor' => $adjustment->actor_name ?? $adjustment->user?->name ?? 'Former staff member',
            'supplier_sync_paused' => $adjustment->supplier_sync_paused,
            'supplier' => $adjustment->supplier?->name,
            'supplier_id' => $adjustment->supplier_id, 'supplier_code' => $adjustment->supplier?->supplier_code,
            'movement_type' => $adjustment->movement_type, 'reference' => $adjustment->reference, 'stock_scope' => $adjustment->stock_scope,
            'created_at' => $adjustment->created_at->setTimezone('Asia/Dhaka')->format('d M Y, h:i A'),
        ]))->header('Cache-Control', 'private, no-store');
    }

    public function resume(Request $request, Product $product, InventoryStockService $stock)
    {
        $stock->resume($request->user(), $product);
        return back()->with('status', 'Supplier stock sync resumed. The next sync will refresh stock from the supplier.');
    }

    public function movements(Request $request)
    {
        [$query, $filters] = $this->movementQuery($request);
        return Inertia::render('Admin/Inventory/History', [
            ...$filters,
            'movements' => $query->latest('id')->paginate(25)->withQueryString()->through(fn ($item) => $this->movementRow($item)),
            'suppliers' => DropshipSupplier::orderBy('name')->get(['id', 'name', 'supplier_code']),
        ]);
    }

    public function exportHistory(Request $request)
    {
        [$query] = $this->movementQuery($request);
        return response()->streamDownload(function () use ($query) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Movement ID', 'Product', 'SKU', 'Supplier', 'Supplier code', 'Movement type', 'Stock ownership', 'Before', 'Change', 'After', 'Reference', 'Reason', 'Performed by', 'Date (Dhaka)'], ',', '"', '');
            foreach ($query->latest('id')->lazy(200) as $item) {
                $row = $this->movementRow($item);
                $values = [$row['id'], $row['product_name'], $row['sku'], $row['supplier'], $row['supplier_code'], $row['movement_type'] ?? $row['operation'], $row['stock_scope'], $row['before'], $row['change'], $row['after'], $row['reference'], $row['reason'], $row['actor'], $row['created_at']];
                fputcsv($output, array_map(fn ($value) => is_string($value) && preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value, $values), ',', '"', '');
            }
            fclose($output);
        }, 'stock-history-'.now('Asia/Dhaka')->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    private function movementQuery(Request $request): array
    {
        $filters = [
            'q' => mb_substr(trim((string) $request->input('q', '')), 0, 200),
            'supplier' => $request->integer('supplier') ?: null,
            'stock_scope' => in_array($request->input('stock_scope'), ['local', 'supplier'], true) ? $request->input('stock_scope') : '',
            'movement_type' => in_array($request->input('movement_type'), ['opening', 'stock_in', 'stock_out', 'adjustment', 'return_in', 'damaged', 'sync_resume'], true) ? $request->input('movement_type') : '',
        ];
        $query = InventoryAdjustment::query()->with('user:id,name', 'supplier:id,name,supplier_code');
        if ($filters['q'] !== '') $query->where(fn ($q) => $q->where('product_name', 'like', '%'.$filters['q'].'%')->orWhere('sku', 'like', '%'.$filters['q'].'%')->orWhere('reference', 'like', '%'.$filters['q'].'%'));
        if ($filters['supplier']) $query->where('supplier_id', $filters['supplier']);
        if ($filters['stock_scope']) $query->where('stock_scope', $filters['stock_scope']);
        if ($filters['movement_type']) {
            $type = $filters['movement_type'];
            $query->where(fn ($q) => $q->where('movement_type', $type)->orWhere(fn ($q) => $q->whereNull('movement_type')->where('operation', match ($type) { 'stock_in' => 'add', 'stock_out' => 'remove', 'adjustment' => 'set', default => $type })));
        }
        return [$query, $filters];
    }

    private function movementRow(InventoryAdjustment $item): array
    {
        return [
            'id' => $item->id, 'product_id' => $item->product_id, 'product_name' => $item->product_name, 'sku' => $item->sku,
            'supplier_id' => $item->supplier_id, 'supplier' => $item->supplier?->name, 'supplier_code' => $item->supplier?->supplier_code,
            'operation' => $item->operation, 'movement_type' => $item->movement_type,
            'stock_scope' => $item->stock_scope, 'before' => $item->stock_before, 'change' => $item->change, 'after' => $item->stock_after,
            'reference' => $item->reference, 'reason' => $item->reason, 'actor' => $item->actor_name ?? $item->user?->name ?? 'Former staff member',
            'created_at' => $item->created_at?->setTimezone('Asia/Dhaka')->format('d M Y, h:i A'),
        ];
    }

    public function export(Request $request)
    {
        [$query, $filters] = $this->filteredQuery($request);
        $this->applySort($query, $filters['order']);
        $query->with('category', 'images', 'supplier', 'supplierLinks.supplierProduct.supplier')->withCount('variants');
        return response()->streamDownload(function () use ($query) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['SKU', 'Product', 'Category', 'Type', 'Stock', 'Stock status', 'Visibility', 'Stock source', 'Supplier', 'Customer price', 'Regular price', 'Selling price', 'Minimum price', 'Maximum price', 'Product source', 'Stock ownership', 'Local warehouse units', 'Supplier availability'], ',', '"', '');
            foreach ($query->lazy(200) as $product) {
                $row = $this->row($product);
                $values = [$product->sku, $product->name, $product->category?->name, $row['has_variants'] ? 'Variable' : 'Simple', $product->stock_quantity, $product->stock_quantity === 0 ? 'Out of stock' : ($product->stock_quantity <= 5 ? 'Low stock' : 'Healthy stock'), $product->is_published ? 'Published' : 'Draft', $row['stock_source'], $row['supplier'], $product->sale_price ?? $product->regular_price, $product->regular_price, $row['selling_price'], $row['minimum_price'], $row['maximum_price'], $row['source_type'], $row['stock_type'] === 'supplier' ? 'Supplier-managed availability' : 'Local inventory', $row['local_stock_quantity'], $row['supplier_stock_quantity']];
                fputcsv($output, array_map(fn ($value) => is_string($value) && preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value, $values), ',', '"', '');
            }
            fclose($output);
        }, 'inventory-'.now('Asia/Dhaka')->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    private function filteredQuery(Request $request, bool $includeStock = true): array
    {
        $filters = [
            'q' => mb_substr(trim((string) $request->input('q', '')), 0, 200),
            'category' => $request->integer('category') ?: null, 'supplier' => $request->integer('supplier') ?: null,
            'stock_level' => $request->input('stock_level', $request->input('stock', '')),
            'visibility' => $request->input('visibility', ''), 'source' => $request->input('source', ''),
            'product_source' => $request->input('product_source', ''),
            'type' => $request->input('type', ''), 'order' => $request->input('order', 'stock_asc'),
            'per_page' => in_array($request->integer('per_page', 20), [20, 50, 100], true) ? $request->integer('per_page', 20) : 20,
        ];
        foreach (['stock_level' => ['out', 'low', 'in'], 'visibility' => ['published', 'draft'], 'source' => ['local', 'supplier', 'manual'], 'product_source' => ['manual', 'dropshipping', 'other'], 'type' => ['simple', 'variable']] as $key => $allowed) {
            if (! in_array($filters[$key], $allowed, true)) $filters[$key] = '';
        }
        if (! in_array($filters['order'], ['stock_asc', 'stock_desc', 'name_asc', 'name_desc', 'price_asc', 'price_desc', 'updated'], true)) $filters['order'] = 'stock_asc';
        $query = Product::query();
        if ($filters['q'] !== '') $query->where(fn ($q) => $q->where('name', 'like', '%'.$filters['q'].'%')->orWhere('sku', 'like', '%'.$filters['q'].'%'));
        if ($filters['category']) $query->where('category_id', $filters['category']);
        if ($filters['supplier']) $query->where(fn ($q) => $q->where('supplier_id', $filters['supplier'])->orWhere(fn ($q) => $q->whereNull('supplier_id')->whereHas('supplierLinks', fn ($link) => $link->where('product_created_by_integration', true)->whereHas('supplierProduct', fn ($q) => $q->where('supplier_id', $filters['supplier'])))));
        if ($filters['product_source']) $query->where('source_type', $filters['product_source']);
        if ($filters['visibility']) $query->where('is_published', $filters['visibility'] === 'published');
        if ($filters['type'] === 'variable') $query->has('variants');
        if ($filters['type'] === 'simple') $query->doesntHave('variants');
        $automatic = fn ($q) => $q->where('product_created_by_integration', true)->where('sync_status', 'active')->where('field_sync_rules->stock', true);
        if ($filters['source'] === 'local') $query->where('source_type', '!=', 'dropshipping')->whereDoesntHave('supplierLinks', fn ($link) => $link->where('product_created_by_integration', true));
        if ($filters['source'] === 'supplier') $query->where(fn ($q) => $q->where('source_type', 'dropshipping')->orWhereHas('supplierLinks', fn ($link) => $link->where('product_created_by_integration', true)));
        if ($filters['source'] === 'manual') $query->whereHas('supplierLinks', fn ($link) => $link->where('product_created_by_integration', true))->whereDoesntHave('supplierLinks', $automatic);
        if ($includeStock) $this->applyStockFilter($query, $filters['stock_level']);
        return [$query, $filters];
    }

    private function applyStockFilter($query, string $level): void
    {
        match ($level) {
            'out' => $query->where('stock_quantity', 0), 'low' => $query->whereBetween('stock_quantity', [1, 5]),
            'in' => $query->where('stock_quantity', '>', 5), default => null,
        };
    }

    private function applySort($query, string $order): void
    {
        match ($order) {
            'stock_desc' => $query->orderByDesc('stock_quantity')->orderBy('name'),
            'name_asc' => $query->orderBy('name'), 'name_desc' => $query->orderByDesc('name'),
            'price_asc' => $query->orderByRaw('COALESCE(sale_price, regular_price) ASC'),
            'price_desc' => $query->orderByRaw('COALESCE(sale_price, regular_price) DESC'),
            'updated' => $query->latest('updated_at'), default => $query->orderBy('stock_quantity')->orderBy('name'),
        };
        $query->orderBy('id');
    }

    private function row(Product $product): array
    {
        $ownedLinks = $product->supplierLinks->where('product_created_by_integration', true);
        $automatic = $ownedLinks->first(fn ($link) => $link->sync_status === 'active' && ($link->field_sync_rules['stock'] ?? false) === true);
        $link = $automatic ?? $ownedLinks->first();
        $supplierManaged = $product->source_type === 'dropshipping' || $product->supplierLinks->contains('product_created_by_integration', true);
        return [
            'id' => $product->id, 'name' => $product->name, 'slug' => $product->slug, 'url_key' => $product->url_key,
            'sku' => $product->sku, 'category' => $product->category ? ['name' => $product->category->name] : null,
            'image_url' => $product->imageUrl(), 'stock_quantity' => (int) $product->stock_quantity,
            'is_published' => $product->is_published, 'has_variants' => $product->variants_count > 0, 'variants_count' => $product->variants_count,
            'price' => $product->sale_price ?? $product->regular_price, 'regular_price' => $product->regular_price,
            'sale_price' => $product->sale_price,
            ...app(ProductPricingService::class)->inventoryDetails($product),
            'pricing_mode' => $product->pricing_mode,
            'stock_source' => $automatic ? 'supplier' : ($supplierManaged ? 'manual' : 'local'),
            'supplier' => $product->supplier?->name ?? $link?->supplierProduct?->supplier?->name,
            'supplier_id' => $product->supplier_id ?? $link?->supplierProduct?->supplier_id,
            'source_type' => $product->source_type, 'stock_type' => $supplierManaged ? 'supplier' : 'local',
            'local_stock_quantity' => $supplierManaged ? 0 : (int) $product->stock_quantity,
            'supplier_stock_quantity' => $supplierManaged ? (int) $product->stock_quantity : null,
            'has_supplier_link' => $ownedLinks->isNotEmpty(),
            'can_resume_sync' => ! $automatic && $ownedLinks->contains('sync_status', 'active'),
            'last_synced' => $link?->last_stock_synced_at?->setTimezone('Asia/Dhaka')->format('d M Y, h:i A'),
            'updated_at' => $product->updated_at?->setTimezone('Asia/Dhaka')->format('d M Y, h:i A'),
        ];
    }
}
