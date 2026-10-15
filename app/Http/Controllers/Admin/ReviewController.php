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
        $data = $request->validate(['q' => ['nullable', 'string', 'max:200'], 'status' => ['nullable', 'string'],
            'rating' => ['nullable', 'integer', 'between:1,5'], 'purchase' => ['nullable', 'string'],
            'sort' => ['nullable', 'string'], 'per_page' => ['nullable', 'integer']]);
        $filters = [
            'q' => trim($data['q'] ?? ''),
            'status' => in_array($data['status'] ?? '', ['pending', 'approved', 'rejected', 'all'], true) ? $data['status'] : 'pending',
            'rating' => $data['rating'] ?? '',
            'purchase' => in_array($data['purchase'] ?? '', ['verified', 'unverified'], true) ? $data['purchase'] : '',
            'sort' => in_array($data['sort'] ?? '', ['newest', 'oldest', 'lowest', 'highest'], true) ? $data['sort'] : 'newest',
            'per_page' => in_array((int) ($data['per_page'] ?? 20), [20, 50, 100], true) ? (int) ($data['per_page'] ?? 20) : 20,
        ];
        $query = ProductReview::query();
        if ($filters['q'] !== '') {
            $term = $filters['q'];
            $query->where(fn ($q) => $q->where('author_name', 'like', '%'.$term.'%')->orWhere('author_email', 'like', '%'.$term.'%')
                ->orWhere('title', 'like', '%'.$term.'%')->orWhere('body', 'like', '%'.$term.'%')
                ->orWhereHas('product', fn ($p) => $p->where('name', 'like', '%'.$term.'%')));
        }
        if ($filters['rating'] !== '') $query->where('rating', $filters['rating']);
        if ($filters['purchase'] !== '') $query->where('is_verified_purchase', $filters['purchase'] === 'verified');
        $counts = array_fill_keys(['pending', 'approved', 'rejected', 'all'], 0);
        foreach ((clone $query)->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')->get() as $group) {
            $counts[$group->status] = (int) $group->aggregate;
            $counts['all'] += (int) $group->aggregate;
        }
        if ($filters['status'] !== 'all') $query->where('status', $filters['status']);
        match ($filters['sort']) {
            'oldest' => $query->oldest('created_at')->orderBy('id'),
            'lowest' => $query->orderBy('rating')->latest('id'),
            'highest' => $query->orderByDesc('rating')->latest('id'),
            default => $query->latest('created_at')->latest('id'),
        };
        return Inertia::render('Admin/Reviews/Index', [
            'reviews' => $query->with(['product.images', 'user'])->paginate($filters['per_page'])->withQueryString()->through(fn ($review) => [
                'id' => $review->id, 'author_name' => $review->author_name ?: $review->user?->name,
                'author_email' => $review->author_email ?: $review->user?->email,
                'rating' => $review->rating, 'title' => $review->title, 'body' => $review->body,
                'status' => $review->status, 'is_verified_purchase' => $review->is_verified_purchase,
                'created_at_formatted' => $review->created_at?->setTimezone('Asia/Dhaka')->format('d M Y, h:i A'),
                'product' => $review->product ? ['name' => $review->product->name, 'image_url' => $review->product->imageUrl()] : null,
            ]),
            'filters' => $filters, 'status' => $filters['status'], 'q' => $filters['q'], 'counts' => $counts,
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
            'ids'         => ['required', 'array', 'min:1', 'max:100'],
            'ids.*'       => ['integer', 'distinct', 'exists:product_reviews,id'],
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
