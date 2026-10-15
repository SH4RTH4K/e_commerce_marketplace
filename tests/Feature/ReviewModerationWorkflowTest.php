<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReviewModerationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function product(): Product
    {
        $category = Category::firstOrCreate(['slug' => 'reviews'], ['name' => 'Reviews']);
        return Product::create(['category_id' => $category->id, 'name' => 'Reviewed Product', 'regular_price' => 100]);
    }

    private function review(Product $product, array $attributes = []): ProductReview
    {
        return ProductReview::create(array_merge(['product_id' => $product->id, 'author_name' => 'Review Customer', 'author_email' => 'review@example.test', 'rating' => 4, 'body' => str_repeat('Useful full customer feedback. ', 20), 'status' => 'pending', 'is_verified_purchase' => true], $attributes));
    }

    public function test_filters_scope_counts_and_include_full_review_content(): void
    {
        $product = $this->product();
        $pending = $this->review($product);
        $this->review($product, ['status' => 'approved']);
        $this->review($product, ['rating' => 1]);
        $this->review($product, ['is_verified_purchase' => false]);
        $this->get('/admin/reviews?rating=4&purchase=verified&q=Reviewed')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('reviews.data', 1)->where('reviews.data.0.body', $pending->body)
            ->where('reviews.data.0.is_verified_purchase', true)->where('counts.pending', 1)
            ->where('counts.approved', 1)->where('counts.all', 2));
    }

    public function test_approve_reject_and_delete_keep_product_rating_correct(): void
    {
        $product = $this->product(); $first = $this->review($product, ['rating' => 5]); $second = $this->review($product, ['rating' => 1]);
        $this->post('/admin/reviews/'.$first->id.'/approve')->assertSessionHasNoErrors();
        $this->assertSame('5.00', $product->fresh()->rating);
        $this->post('/admin/reviews/bulk', ['ids' => [$second->id], 'bulk_action' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('3.00', $product->fresh()->rating);
        $this->post('/admin/reviews/'.$second->id.'/reject')->assertSessionHasNoErrors();
        $this->assertSame('5.00', $product->fresh()->rating);
        $this->delete('/admin/reviews/'.$first->id)->assertSessionHasNoErrors();
        $this->assertSame(0, $product->fresh()->reviews_count);
    }

    public function test_bulk_reject_delete_and_duplicate_selection_validation(): void
    {
        $product = $this->product(); $first = $this->review($product); $second = $this->review($product);
        $this->postJson('/admin/reviews/bulk', ['ids' => [$first->id, $first->id], 'bulk_action' => 'approve'])->assertUnprocessable();
        $this->assertSame('pending', $first->fresh()->status);
        $this->post('/admin/reviews/bulk', ['ids' => [$first->id, $second->id], 'bulk_action' => 'reject'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $second->fresh()->status);
        $this->post('/admin/reviews/bulk', ['ids' => [$first->id, $second->id], 'bulk_action' => 'delete'])->assertSessionHasNoErrors();
        $this->assertSame(0, ProductReview::count());
    }

    public function test_review_permission_is_enforced_for_reads_and_mutations(): void
    {
        $review = $this->review($this->product());
        $this->actingAs(User::factory()->create(['role' => 'support', 'permissions' => ['reviews.manage']]))->get('/admin/reviews')->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'support', 'permissions' => ['customers.manage']]))->get('/admin/reviews')->assertForbidden();
        $this->post('/admin/reviews/'.$review->id.'/approve')->assertForbidden();
        $this->delete('/admin/reviews/'.$review->id)->assertForbidden();
    }

    public function test_sorting_pagination_and_invalid_ratings(): void
    {
        $product = $this->product(); $lowest = $this->review($product, ['rating' => 1]);
        for ($i = 0; $i < 21; $i++) $this->review($product);
        $this->get('/admin/reviews?sort=lowest&per_page=20')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('reviews.data', 20)->where('reviews.data.0.id', $lowest->id)->where('reviews.total', 22));
        $this->getJson('/admin/reviews?rating=6')->assertUnprocessable()->assertJsonValidationErrors('rating');
    }
}
