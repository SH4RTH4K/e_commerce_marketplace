<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Feature;
use App\Models\Product;
use App\Models\ProductReview;

class ProductController extends Controller
{
    public function show(Product $product)
    {
        abort_unless($product->is_published, 404);

        $product->load('images', 'variants', 'category');

        $related = Product::published()
            ->with('images')
            ->withExists('variants')
            ->withExists([
                'variants as variants_in_stock_exists' => fn ($variantQuery) => $variantQuery->where('stock', '>', 0),
            ])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->take(5)
            ->get();

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

        return Inertia::render('Storefront/Product', compact(
            'product',
            'related',
            'sizes',
            'colors',
            'weights',
            'variantGroups',
            'features',
            'reviews',
            'canReview'
        ));
    }
}
