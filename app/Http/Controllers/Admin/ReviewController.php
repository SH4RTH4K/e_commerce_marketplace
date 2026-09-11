<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');
        if (! in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
            $status = 'pending';
        }

        $query = ProductReview::with(['product.images', 'user'])->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($term = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($term) {
                $q->where('author_name', 'like', "%{$term}%")
                    ->orWhere('author_email', 'like', "%{$term}%")
                    ->orWhere('title', 'like', "%{$term}%")
                    ->orWhere('body', 'like', "%{$term}%")
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$term}%"));
            });
        }

        $counts = [
            'pending'  => ProductReview::pending()->count(),
            'approved' => ProductReview::approved()->count(),
            'rejected' => ProductReview::where('status', ProductReview::STATUS_REJECTED)->count(),
            'all'      => ProductReview::count(),
        ];

        return Inertia::render('Admin/Reviews/Index', [
            'reviews' => $query->paginate(20)->withQueryString(),
            'status'  => $status,
            'q'       => $term,
            'counts'  => $counts,
        ]);
    }

    public function approve(ProductReview $review)
    {
        $review->approve();

        return back()->with('status', 'Review approved.');
    }

    public function reject(ProductReview $review)
    {
        $review->reject();

        return back()->with('status', 'Review rejected.');
    }

    public function destroy(ProductReview $review)
    {
        $product = $review->product;
        $review->delete();
        $product?->recalculateRatingFromReviews();

        return back()->with('status', 'Review deleted.');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'ids'         => ['required', 'array', 'min:1'],
            'ids.*'       => ['integer', 'exists:product_reviews,id'],
            'bulk_action' => ['required', 'in:approve,reject,delete'],
        ]);

        $reviews = ProductReview::whereIn('id', $data['ids'])->get();
        $count = 0;

        foreach ($reviews as $review) {
            if ($data['bulk_action'] === 'approve') {
                $review->approve();
                $count++;
            } elseif ($data['bulk_action'] === 'reject') {
                $review->reject();
                $count++;
            } else {
                $product = $review->product;
                $review->delete();
                $product?->recalculateRatingFromReviews();
                $count++;
            }
        }

        $label = match ($data['bulk_action']) {
            'approve' => 'approved',
            'reject'  => 'rejected',
            default   => 'deleted',
        };

        return back()->with('status', "{$count} review(s) {$label}.");
    }
}
