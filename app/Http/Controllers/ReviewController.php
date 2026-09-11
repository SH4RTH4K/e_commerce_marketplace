<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Product $product)
    {
        abort_unless($product->is_published, 404);

        $user = $request->user();
        if (! $user) {
            return redirect()
                ->guest(route('login', ['redirect' => route('product.show', $product) . '#reviews']))
                ->with('status', 'Please sign in to write a review.');
        }

        $data = $request->validate([
            'author_name'  => ['nullable', 'string', 'max:120'],
            'author_email' => ['nullable', 'email', 'max:120'],
            'rating'       => ['required', 'integer', 'min:1', 'max:5'],
            'title'        => ['nullable', 'string', 'max:180'],
            'body'         => ['required', 'string', 'max:2000'],
        ]);

        if ($user) {
            $already = ProductReview::query()
                ->where('product_id', $product->id)
                ->where('user_id', $user->id)
                ->whereIn('status', [ProductReview::STATUS_PENDING, ProductReview::STATUS_APPROVED])
                ->exists();

            if ($already) {
                return back()
                    ->withInput()
                    ->withErrors(['body' => 'You have already reviewed this product.'])
                    ->withFragment('reviews');
            }
        }

        $verified = false;
        if ($user) {
            $verified = OrderItem::query()
                ->where('product_id', $product->id)
                ->whereHas('order', function ($q) use ($user) {
                    $q->where(function ($sub) use ($user) {
                        $sub->where('user_id', $user->id);
                        if (! empty($user->phone)) {
                            $sub->orWhere('customer_phone', $user->phone);
                        }
                        if (! empty($user->email)) {
                            $sub->orWhere('customer_email', $user->email);
                        }
                    })->whereNotIn('status', ['cancelled']);
                })
                ->exists();
        }

        $authorName = trim((string) ($data['author_name'] ?? ''));
        if ($authorName === '') {
            $authorName = $user?->name ?: ($user?->phone ?: ($user?->email ?: 'Customer'));
        }

        $authorEmail = ! empty($data['author_email']) ? trim((string) $data['author_email']) : ($user?->email ?: null);

        ProductReview::create([
            'product_id'           => $product->id,
            'user_id'              => $user?->id,
            'author_name'          => $authorName,
            'author_email'         => $authorEmail,
            'rating'               => (int) $data['rating'],
            'title'                => ! empty($data['title']) ? trim((string) $data['title']) : null,
            'body'                 => trim((string) $data['body']),
            'status'               => ProductReview::STATUS_PENDING,
            'is_verified_purchase' => $verified,
        ]);

        return redirect()
            ->route('product.show', $product)
            ->with('status', 'Thanks! Your review was submitted and is awaiting approval.')
            ->withFragment('reviews');
    }
}
