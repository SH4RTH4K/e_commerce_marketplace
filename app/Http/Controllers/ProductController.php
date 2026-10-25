<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use App\Models\Product;
use App\Models\ProductReview;
use App\Services\CategoryHierarchyService;
use App\Services\ProductMetaDescriptionService;
use App\Services\ProductOfferService;
use App\Services\StorefrontSeoService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function show(Request $request, Product $product)
    {
        abort_unless($product->is_published, 404);

        $product->load('images', 'variants', 'category', 'supplierLinks.supplierProduct');
        // Store SKUs belong to this store. Supplier codes only fill a missing
        // legacy SKU and remain available on the supplier mapping for sync.
        $product->sku = $product->sku
            ?: $product->supplierLinks->first()?->supplierProduct?->product_code
            ?: $product->supplierLinks->first()?->supplierProduct?->supplier_product_id;

        $canonical = route('product.show', $product);
        if ((string) $request->route()->originalParameter('product') !== $product->url_key) {
            $query = $request->server('QUERY_STRING');

            return redirect()->to($canonical.($query ? '?'.$query : ''), 301);
        }
        $product->unsetRelation('supplierLinks');

        $related = Product::published()
            ->orderByRaw('CASE WHEN stock_quantity > 0 OR sourced_stock_quantity > 0 THEN 0 WHEN allow_backorder = 1 THEN 1 ELSE 2 END')
            ->with('images', 'supplierLinks.supplierProduct')
            ->withExists('variants')
            ->withExists([
                'variants as variants_in_stock_exists' => fn ($variantQuery) => $variantQuery->where('stock', '>', 0),
            ])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->take(5)
            ->get()
            ->each(static function (Product $relatedProduct): void {
                $supplierProduct = $relatedProduct->supplierLinks->first()?->supplierProduct;
                $relatedProduct->sku = $relatedProduct->sku
                    ?: $supplierProduct?->product_code
                    ?: $supplierProduct?->supplier_product_id;
                $relatedProduct->unsetRelation('supplierLinks');
            });

        $sizes = $product->variants->where('type', 'Size')->values();
        $colors = $product->variants->where('type', 'Color')->values();
        $weights = $product->variants->where('type', 'Weight')->values();
        $variantGroups = $product->variants->groupBy('type')->map(fn ($items, $type) => [
            'name' => $type,
            'options' => $items->values(),
        ])->values();
        $features = Feature::where('is_active', true)->orderBy('position')->take(4)->get();

        $reviews = $product->approvedReviews()
            ->with('user')
            ->paginate(8, ['*'], 'reviews_page')
            ->withQueryString();

        $canReview = true;
        if ($user = auth()->user()) {
            $canReview = ! ProductReview::query()
                ->where('product_id', $product->id)
                ->where('user_id', $user->id)
                ->whereIn('status', [ProductReview::STATUS_PENDING, ProductReview::STATUS_APPROVED])
                ->exists();
        }

        $categoryPath = $product->category ? app(CategoryHierarchyService::class)->path($product->category) : [];
        try {
            $initialVariants = app(ProductOfferService::class)->selected($product, $request->query('variant'))['selection'];
        } catch (\InvalidArgumentException|\TypeError $exception) {
            abort(404);
        }

        return Inertia::render('Storefront/Product', array_merge(compact(
            'product',
            'related',
            'sizes',
            'colors',
            'weights',
            'variantGroups',
            'features',
            'reviews',
            'canReview'
        ), [
            'initialVariants' => $initialVariants,
            'categoryPath' => array_map(fn ($item) => collect($item)->only(['id', 'name', 'slug'])->all(), $categoryPath),
            'structuredData' => app(StorefrontSeoService::class)->product($product, $categoryPath),
            'seo' => [
                'title' => $product->meta_title ?: $product->name,
                'description' => app(ProductMetaDescriptionService::class)->generate($product),
                'description_is_generated' => true,
                'keywords' => $product->meta_keywords,
                'image' => $product->primaryImage() ? image_url($product->primaryImage()->path, $product->slug) : null,
                'type' => 'product',
                'canonical' => $canonical,
            ],
        ]));
    }
}
