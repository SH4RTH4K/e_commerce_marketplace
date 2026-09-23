<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Feature;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function show(Request $request, Product $product)
    {
        abort_unless($product->is_published, 404);

        $canonical = route('product.show', $product);
        if ((string) $request->route()->originalParameter('product') !== $product->slug) {
            $query = $request->server('QUERY_STRING');

            return redirect()->to($canonical.($query ? '?'.$query : ''), 301);
        }

        $product->load('images', 'variants', 'category', 'supplierLinks.supplierProduct');
        // Supplier product codes are the SKU for imported products. Set the
        // display value on the existing SKU attribute so it is always included
        // in the serialized storefront product payload.
        $product->sku = $product->sku
            ?: $product->supplierLinks->first()?->supplierProduct?->product_code
            ?: $product->supplierLinks->first()?->supplierProduct?->supplier_product_id;
        $product->unsetRelation('supplierLinks');

        $related = Product::published()
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

        $sizes   = $product->variants->where('type', 'Size')->values();
        $colors  = $product->variants->where('type', 'Color')->values();
        $weights = $product->variants->where('type', 'Weight')->values();
        $variantGroups = $product->variants->groupBy('type')->map(fn($items, $type) => [
            'name'    => $type,
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
            'seo' => [
                'title' => $product->meta_title ?: $product->name,
                'description' => Str::limit(trim(strip_tags((string) ($product->meta_description ?: $product->short_description ?: $product->description))), 300),
                'keywords' => $product->meta_keywords,
                'image' => $product->primaryImage() ? image_url($product->primaryImage()->path, $product->slug) : null,
                'type' => 'product',
                'canonical' => $canonical,
            ],
        ]));
    }
}
