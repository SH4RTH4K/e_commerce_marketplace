<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request, ?Category $category = null)
    {
        $query = Product::published()
            ->with('images', 'category', 'supplierLinks.supplierProduct')
            ->withExists('variants')
            ->withExists([
                'variants as variants_in_stock_exists' => fn ($variantQuery) => $variantQuery->where('stock', '>', 0),
            ]);

        if ($category) {
            $query->where('category_id', $category->id);
        }

        if ($term = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('brand', 'like', "%{$term}%")
                    ->orWhere('short_description', 'like', "%{$term}%");
            });
        }

        if ($request->filled('min')) {
            $query->whereRaw('COALESCE(sale_price, regular_price) >= ?', [(float) $request->input('min')]);
        }
        if ($request->filled('max')) {
            $query->whereRaw('COALESCE(sale_price, regular_price) <= ?', [(float) $request->input('max')]);
        }

        // Flash Sale page: only flash products, ordered by admin flash-sale order
        $isFlashPage = $request->boolean('flash');
        if ($isFlashPage) {
            $query->where('is_flash_sale', true);
        } elseif ($request->boolean('on_sale')) {
            $query->where(function ($q) {
                $q->where('is_flash_sale', true)
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('sale_price')
                            ->whereColumn('sale_price', '<', 'regular_price');
                    });
            });
        }
        $inStock = $request->boolean('in_stock');
        $outOfStock = $request->boolean('out_of_stock');
        if ($inStock && ! $outOfStock) {
            $query->where(function ($q) {
                $q->where('stock_quantity', '>', 0)
                    ->orWhereHas('variants', fn ($variantQuery) => $variantQuery->where('stock', '>', 0));
            });
        } elseif ($outOfStock && ! $inStock) {
            $query->where('stock_quantity', '<=', 0)
                ->whereDoesntHave('variants', fn ($variantQuery) => $variantQuery->where('stock', '>', 0));
        }
        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }
        if ($request->boolean('new')) {
            $query->where('is_new_arrival', true);
        }
        if ($request->boolean('best')) {
            $query->where('is_best_seller', true);
        }

        if ($brand = trim((string) $request->input('brand'))) {
            $query->where('brand', $brand);
        }

        if ($request->filled('min_rating')) {
            // Round average so 4.5–5.0 counts as 5★ (& Up), 3.5–4.4 as 4★, etc.
            $minStars = max(1, min(5, (int) $request->input('min_rating')));
            $query->whereRaw('ROUND(rating) >= ?', [$minStars]);
        }

        $selectedVariants = collect((array) $request->input('variants', []))
            ->map(fn ($values) => collect((array) $values)
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->unique()
                ->values()
                ->all())
            ->filter(fn ($values, $type) => trim((string) $type) !== '' && count($values) > 0)
            ->all();

        foreach ($selectedVariants as $type => $values) {
            $query->whereHas('variants', function ($variantQuery) use ($type, $values) {
                $variantQuery->where('type', (string) $type)
                    ->whereIn('value', $values);
            });
        }

        $sort = $request->input('sort');
        if ($isFlashPage && blank($sort)) {
            $query->orderBy('flash_sale_position')->orderBy('id');
        } else {
            match ($sort) {
                'price_low'  => $query->orderByRaw('COALESCE(sale_price, regular_price) asc'),
                'price_high' => $query->orderByRaw('COALESCE(sale_price, regular_price) desc'),
                'rating'     => $query->orderByDesc('rating'),
                'name'       => $query->orderBy('name'),
                'newest'     => $query->latest(),
                default      => $query->latest(),
            };
        }

        $template = setting('storefront_template', 'template-2');
        $productsPerPageKey = $template === 'template-1'
            ? 'template_1_products_per_page'
            : 'template_2_products_per_page';
        $productsPerPage = max(1, min(48, (int) setting($productsPerPageKey, '12')));

        $products = $query->paginate($productsPerPage)->withQueryString();
        $products->getCollection()->each(static function (Product $product): void {
            $supplierProduct = $product->supplierLinks->first()?->supplierProduct;
            $product->sku = $product->sku
                ?: $supplierProduct?->product_code
                ?: $supplierProduct?->supplier_product_id;
            $product->unsetRelation('supplierLinks');
        });

        $priceCeiling = (int) Product::published()
            ->selectRaw('MAX(COALESCE(sale_price, regular_price)) as max_price')
            ->value('max_price');
        $priceCeiling = max(5000, (int) (ceil(max($priceCeiling, 5000) / 50) * 50));

        $brands = Product::published()
            ->when($category, fn ($q) => $q->where('category_id', $category->id))
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->orderBy('brand')
            ->distinct()
            ->pluck('brand');

        $variantFilters = ProductVariant::query()
            ->selectRaw('type, value, COUNT(DISTINCT product_id) as products_count')
            ->whereHas('product', function ($productQuery) use ($category) {
                $productQuery->published()
                    ->when($category, fn ($q) => $q->where('category_id', $category->id));
            })
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->groupBy('type', 'value')
            ->orderBy('type')
            ->orderBy('value')
            ->get()
            ->groupBy('type')
            ->map(fn ($values, $type) => [
                'type' => $type,
                'options' => $values->map(fn ($row) => [
                    'value' => $row->value,
                    'products_count' => (int) $row->products_count,
                ])->values(),
            ])
            ->values();

        $categories = Category::where('is_active', true)
            ->where('show_in_menu', true)
            ->withCount(['products' => fn ($q) => $q->published()])
            ->orderBy('menu_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Storefront/Shop', [
            'products'         => $products,
            'activeCategory'   => $category,
            'categories'       => $categories,
            'allProductsCount' => Product::published()->count(),
            'brands'           => $brands,
            'variantFilters'   => $variantFilters,
            'sort'             => $request->input('sort'),
            'minRating'        => $request->input('min_rating'),
            'q'                => $term,
            'priceCeiling'     => $priceCeiling,
            'seo'              => [
                'title' => $category?->meta_title ?: ($category?->name ?: ($term ? "Search: {$term}" : 'Shop All Products')),
                'description' => $category?->meta_description,
                'keywords' => $category?->meta_keywords,
                'robots' => $term || $request->query->count() > 0 ? 'noindex,follow' : 'index,follow',
            ],
        ]);
    }
}
