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
use Inertia\Inertia;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        [$query, $filters] = $this->filteredQuery($request, false);
        $totals = (clone $query)->selectRaw('COUNT(*) AS total, COALESCE(SUM(stock_quantity), 0) AS units, SUM(CASE WHEN stock_quantity > 5 THEN 1 ELSE 0 END) AS in_stock, SUM(CASE WHEN stock_quantity BETWEEN 1 AND 5 THEN 1 ELSE 0 END) AS low_stock, SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) AS out_stock')->first();
        $this->applyStockFilter($query, $filters['stock_level']);
        $matchingUnits = (clone $query)->sum('stock_quantity');
        $this->applySort($query, $filters['order']);
        $query->with('category', 'images', 'supplierLinks.supplierProduct.supplier')->withCount('variants');
        return Inertia::render('Admin/Inventory/Index', [
            ...$filters,
            'products' => $query->paginate($filters['per_page'])->withQueryString()->through(fn ($product) => $this->row($product)),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'suppliers' => DropshipSupplier::whereHas('products.productLink')->orderBy('name')->get(['id', 'name']),
            'summary' => collect(['total', 'units', 'in_stock', 'low_stock', 'out_stock'])->mapWithKeys(fn ($key) => [$key => (int) $totals->$key])->all(),
            'matching_units' => (int) $matchingUnits,
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
        ]);
        $count = $stock->adjust($request->user(), $data['items'], $data['operation'], $data['quantity'], $data['reason'], (bool) $data['protect_supplier_stock']);
        return back()->with('status', "Stock adjusted for {$count} product(s).");
    }

    public function updateStock(Request $request, Product $product, InventoryStockService $stock)
    {
        $data = $request->validate([
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'expected_stock' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'reason' => ['required', 'string', 'max:500'], 'protect_supplier_stock' => ['required', 'boolean'],
        ]);
        $stock->adjust($request->user(), [['product_id' => $product->id, 'expected_stock' => $data['expected_stock']]], 'set', $data['stock_quantity'], $data['reason'], (bool) $data['protect_supplier_stock']);
        return back()->with('status', 'Stock adjustment saved.');
    }

    public function history(Product $product)
    {
        return response()->json(InventoryAdjustment::where('product_id', $product->id)->with('user:id,name')->latest('id')->paginate(15)->through(fn ($adjustment) => [
            'id' => $adjustment->id, 'operation' => $adjustment->operation,
            'before' => $adjustment->stock_before, 'after' => $adjustment->stock_after, 'change' => $adjustment->change,
            'reason' => $adjustment->reason, 'actor' => $adjustment->user?->name ?? 'Former staff member',
            'supplier_sync_paused' => $adjustment->supplier_sync_paused,
            'supplier' => $adjustment->supplier?->name,
            'created_at' => $adjustment->created_at->setTimezone('Asia/Dhaka')->format('d M Y, h:i A'),
        ]))->header('Cache-Control', 'private, no-store');
    }

    public function resume(Request $request, Product $product, InventoryStockService $stock)
    {
        $stock->resume($request->user(), $product);
        return back()->with('status', 'Supplier stock sync resumed. The next sync will refresh stock from the supplier.');
    }

    public function export(Request $request)
    {
        [$query, $filters] = $this->filteredQuery($request);
        $this->applySort($query, $filters['order']);
        $query->with('category', 'images', 'supplierLinks.supplierProduct.supplier')->withCount('variants');
        return response()->streamDownload(function () use ($query) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['SKU', 'Product', 'Category', 'Type', 'Stock', 'Stock status', 'Visibility', 'Stock source', 'Supplier', 'Customer price', 'Regular price', 'Selling price', 'Minimum price', 'Maximum price'], ',', '"', '');
            foreach ($query->lazy(200) as $product) {
                $row = $this->row($product);
                $values = [$product->sku, $product->name, $product->category?->name, $row['has_variants'] ? 'Variable' : 'Simple', $product->stock_quantity, $product->stock_quantity === 0 ? 'Out of stock' : ($product->stock_quantity <= 5 ? 'Low stock' : 'Healthy stock'), $product->is_published ? 'Published' : 'Draft', $row['stock_source'], $row['supplier'], $product->sale_price ?? $product->regular_price, $product->regular_price, $row['selling_price'], $row['minimum_price'], $row['maximum_price']];
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
            'type' => $request->input('type', ''), 'order' => $request->input('order', 'stock_asc'),
            'per_page' => in_array($request->integer('per_page', 20), [20, 50, 100], true) ? $request->integer('per_page', 20) : 20,
        ];
        foreach (['stock_level' => ['out', 'low', 'in'], 'visibility' => ['published', 'draft'], 'source' => ['local', 'supplier', 'manual'], 'type' => ['simple', 'variable']] as $key => $allowed) {
            if (! in_array($filters[$key], $allowed, true)) $filters[$key] = '';
        }
        if (! in_array($filters['order'], ['stock_asc', 'stock_desc', 'name_asc', 'name_desc', 'price_asc', 'price_desc', 'updated'], true)) $filters['order'] = 'stock_asc';
        $query = Product::query();
        if ($filters['q'] !== '') $query->where(fn ($q) => $q->where('name', 'like', '%'.$filters['q'].'%')->orWhere('sku', 'like', '%'.$filters['q'].'%'));
        if ($filters['category']) $query->where('category_id', $filters['category']);
        if ($filters['supplier']) $query->whereHas('supplierLinks.supplierProduct', fn ($q) => $q->where('supplier_id', $filters['supplier']));
        if ($filters['visibility']) $query->where('is_published', $filters['visibility'] === 'published');
        if ($filters['type'] === 'variable') $query->has('variants');
        if ($filters['type'] === 'simple') $query->doesntHave('variants');
        $automatic = fn ($q) => $q->where('sync_status', 'active')->where('field_sync_rules->stock', true);
        if ($filters['source'] === 'local') $query->doesntHave('supplierLinks');
        if ($filters['source'] === 'supplier') $query->whereHas('supplierLinks', $automatic);
        if ($filters['source'] === 'manual') $query->has('supplierLinks')->whereDoesntHave('supplierLinks', $automatic);
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
        $automatic = $product->supplierLinks->first(fn ($link) => $link->sync_status === 'active' && ($link->field_sync_rules['stock'] ?? false) === true);
        $link = $automatic ?? $product->supplierLinks->first();
        return [
            'id' => $product->id, 'name' => $product->name, 'slug' => $product->slug, 'url_key' => $product->url_key,
            'sku' => $product->sku, 'category' => $product->category ? ['name' => $product->category->name] : null,
            'image_url' => $product->imageUrl(), 'stock_quantity' => (int) $product->stock_quantity,
            'is_published' => $product->is_published, 'has_variants' => $product->variants_count > 0, 'variants_count' => $product->variants_count,
            'price' => $product->sale_price ?? $product->regular_price, 'regular_price' => $product->regular_price,
            'sale_price' => $product->sale_price,
            ...app(ProductPricingService::class)->inventoryDetails($product),
            'pricing_mode' => $product->pricing_mode,
            'stock_source' => $automatic ? 'supplier' : ($link ? 'manual' : 'local'),
            'supplier' => $link?->supplierProduct?->supplier?->name,
            'can_resume_sync' => ! $automatic && $product->supplierLinks->contains('sync_status', 'active'),
            'last_synced' => $link?->last_stock_synced_at?->setTimezone('Asia/Dhaka')->format('d M Y, h:i A'),
            'updated_at' => $product->updated_at?->setTimezone('Asia/Dhaka')->format('d M Y, h:i A'),
        ];
    }
}
