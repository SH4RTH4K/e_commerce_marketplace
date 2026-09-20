<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category', 'images');

        $term = trim((string) $request->input('q'));
        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->input('category'));
        }

        // Stock level filter: low (1-5), out (0), in (>5).
        // `stock` is retained for old bookmarked URLs.
        $stockLevel = $request->input('stock_level', $request->input('stock'));
        if (in_array($stockLevel, ['out', 'low', 'in'], true)) {
            match ($stockLevel) {
                'out'  => $query->where('stock_quantity', 0),
                'low'  => $query->where('stock_quantity', '>', 0)->where('stock_quantity', '<=', 5),
                'in'   => $query->where('stock_quantity', '>', 5),
            };
        }

        $visibility = $request->input('visibility');
        if (in_array($visibility, ['published', 'draft'], true)) {
            $query->where('is_published', $visibility === 'published');
        }

        $order = $request->input('order', 'stock_asc');
        match ($order) {
            'stock_desc' => $query->orderByDesc('stock_quantity')->orderBy('name'),
            'name_asc'   => $query->orderBy('name'),
            'name_desc'  => $query->orderByDesc('name'),
            'price_asc'  => $query->orderBy('regular_price')->orderBy('name'),
            'price_desc' => $query->orderByDesc('regular_price')->orderBy('name'),
            'updated'    => $query->latest('updated_at'),
            default      => $query->orderBy('stock_quantity')->orderBy('name'),
        };

        if (! in_array($order, ['stock_asc', 'stock_desc', 'name_asc', 'name_desc', 'price_asc', 'price_desc', 'updated'], true)) {
            $order = 'stock_asc';
        }

        return Inertia::render('Admin/Inventory/Index', [
            'products'   => $query->paginate(20)->withQueryString()->through(fn($p) => [
                'id'             => $p->id,
                'name'           => $p->name,
                'slug'           => $p->slug,
                'sku'            => $p->sku,
                'regular_price'  => $p->regular_price,
                'stock_quantity' => $p->stock_quantity,
                'is_published'   => $p->is_published,
                'category'       => $p->category ? ['name' => $p->category->name] : null,
                'primary_image'  => $p->images->where('is_primary', true)->first()?->path,
            ]),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'q'          => $term,
            'category'   => $request->input('category'),
            'stock_level' => $stockLevel,
            'visibility'  => $visibility,
            'order'       => $order,
            'summary'    => [
                'total'     => Product::count(),
                'in_stock'  => Product::where('stock_quantity', '>', 5)->count(),
                'low_stock' => Product::where('stock_quantity', '>', 0)->where('stock_quantity', '<=', 5)->count(),
                'out_stock' => Product::where('stock_quantity', 0)->count(),
            ],
        ]);
    }

    public function updateStock(Request $request, Product $product)
    {
        $request->validate(['stock_quantity' => 'required|integer|min:0']);
        $product->update(['stock_quantity' => $request->input('stock_quantity')]);
        return back()->with('status', "Stock updated to {$request->input('stock_quantity')} for \"{$product->name}\"");
    }
}
