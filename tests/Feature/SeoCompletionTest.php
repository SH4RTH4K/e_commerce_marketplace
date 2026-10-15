<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\SeoCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SeoCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function category(array $attributes = []): Category
    {
        return Category::create(array_merge(['name' => 'Home & Lifestyle', 'slug' => 'home-lifestyle'], $attributes));
    }

    private function product(Category $category, array $attributes = []): Product
    {
        return Product::create(array_merge(['name' => 'Automatic Soap Dispenser 380ml – Touchless Smart Foaming Hand Washer with Infrared Motion Sensor, USB Rechargeable & Liquid Level Indicator Window for Kitchen & Bathroom',
            'sku' => '3329', 'category_id' => $category->id, 'regular_price' => 1890, 'sale_price' => 1701, 'stock_quantity' => 7, 'is_published' => true], $attributes));
    }

    public function test_preview_is_read_only_and_reports_missing_fields_with_fact_based_unique_item_text(): void
    {
        $category = $this->category();
        $product = $this->product($category);
        $this->get('/admin/settings/seo-completion')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SeoCompletion')->where('summary.categories.incomplete', 1)->where('summary.products.meta_title', 1)
            ->has('samples', 2)->where('samples.1.proposed.meta_title', fn ($text) => str_contains($text, '380ml') && mb_strlen($text) <= 65)
            ->where('samples.1.proposed.meta_description', fn ($text) => str_contains($text, 'Touchless') && mb_strlen($text) <= 160));
        $this->assertNull($product->fresh()->meta_title);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_completion_fills_all_three_fields_and_preserves_every_other_product_and_category_attribute(): void
    {
        $category = $this->category();
        $product = $this->product($category);
        $beforeProduct = $product->fresh()->toArray();
        $beforeCategory = $category->fresh()->toArray();
        $this->post('/admin/settings/seo-completion', ['fill_missing' => true])->assertRedirect('/admin/settings/seo-completion')->assertSessionHasNoErrors();
        foreach (SeoCompletionService::FIELDS as $field) {
            $this->assertNotEmpty($product->fresh()->{$field});
            $this->assertNotEmpty($category->fresh()->{$field});
            unset($beforeProduct[$field], $beforeCategory[$field]);
        }
        $afterProduct = $product->fresh()->toArray();
        $afterCategory = $category->fresh()->toArray();
        foreach ([...SeoCompletionService::FIELDS, 'updated_at'] as $field) {
            unset($beforeProduct[$field], $beforeCategory[$field], $afterProduct[$field], $afterCategory[$field]);
        }
        $this->assertSame($beforeProduct, $afterProduct);
        $this->assertSame($beforeCategory, $afterCategory);
        $result = session('seo_completion_result');
        $this->assertSame(6, $result['fields']);
        $snapshot = json_decode(Storage::disk('local')->get('seo-completion/'.$result['run_id'].'.json'), true);
        $this->assertSame('completed', $snapshot['status']);
        $this->assertNull($snapshot['changes'][1]['before']['meta_title']);
        $this->assertSame($product->fresh()->meta_title, $snapshot['changes'][1]['after']['meta_title']);
    }

    public function test_partial_existing_text_is_preserved_verbatim_and_repeat_runs_are_idempotent(): void
    {
        $category = $this->category(['meta_title' => 'Keep category title', 'meta_description' => 'Keep category description', 'meta_keywords' => 'keep, category']);
        $product = $this->product($category, ['meta_title' => '  Custom Product Title  ', 'meta_description' => 'Editorial text & details', 'meta_keywords' => '']);
        $service = app(SeoCompletionService::class);
        $result = $service->complete((int) auth()->id());
        $this->assertSame(1, $result['fields']);
        $this->assertSame(0, $result['categories']);
        $this->assertSame('  Custom Product Title  ', $product->fresh()->meta_title);
        $this->assertSame('Editorial text & details', $product->fresh()->meta_description);
        $this->assertSame('keep, category', $category->fresh()->meta_keywords);
        $this->assertSame(0, $service->complete((int) auth()->id())['fields']);
        $this->assertSame(0, $service->summary()['products']['incomplete']);
    }

    public function test_drafts_hidden_categories_and_all_category_levels_are_included(): void
    {
        $root = $this->category();
        $child = $this->category(['name' => 'Bathroom', 'slug' => 'bathroom', 'parent_id' => $root->id]);
        $leaf = $this->category(['name' => 'Soap Dispensers', 'slug' => 'soap-dispensers', 'parent_id' => $child->id, 'is_active' => false]);
        $product = $this->product($leaf, ['is_published' => false]);
        $result = app(SeoCompletionService::class)->complete((int) auth()->id());
        $this->assertSame(3, $result['categories']);
        $this->assertSame(1, $result['products']);
        $this->assertFalse($product->fresh()->is_published);
        $this->assertFalse($leaf->fresh()->is_active);
    }

    public function test_html_entities_and_unicode_names_are_cleaned_without_cutting_characters(): void
    {
        $category = $this->category();
        $product = $this->product($category, ['name' => '<b>Soft &amp; Warm Scarf</b> — Blue <script>alert(1)</script>']);
        $patch = app(SeoCompletionService::class)->proposed($product);
        $this->assertStringNotContainsString('<b>', $patch['meta_title']);
        $this->assertStringContainsString('Soft & Warm Scarf', $patch['meta_description']);
        $this->assertStringNotContainsString('alert', $patch['meta_description']);
        $product->name = str_repeat('বাংলা ', 30).'শাড়ি';
        $patch = app(SeoCompletionService::class)->proposed($product);
        foreach ($patch as $value) {
            $this->assertTrue(mb_check_encoding($value, 'UTF-8'));
        }
    }

    public function test_catalog_is_processed_beyond_a_single_chunk_and_sitemap_cache_is_invalidated(): void
    {
        $category = $this->category();
        for ($i = 0; $i < 215; $i++) {
            $this->product($category, ['name' => 'Model '.$i.' Desk Lamp', 'sku' => 'LAMP-'.$i]);
        }
        Cache::put('sitemap_urls_v2', ['old']);
        $result = app(SeoCompletionService::class)->complete((int) auth()->id());
        $this->assertSame(215, $result['products']);
        $this->assertSame(648, $result['fields']);
        $this->assertSame(215, Product::whereNotNull('meta_title')->count());
        $this->assertNull(Cache::get('sitemap_urls_v2'));
    }

    public function test_an_existing_run_lock_prevents_overlapping_updates(): void
    {
        $this->category();
        $lock = Cache::lock('catalog-seo-completion', 300);
        $lock->get();
        try {
            app(SeoCompletionService::class)->complete((int) auth()->id());
            $this->fail('An overlapping operation was allowed.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('already running', $error->getMessage());
            $this->assertNull(Category::first()->meta_title);
        } finally {
            $lock->release();
        }
    }

    public function test_concurrent_editor_changes_are_kept_and_a_repeat_run_can_fill_remaining_fields(): void
    {
        $category = $this->category();
        $product = $this->product($category);
        $disk = Storage::disk('local');
        $edited = false;
        $mock = \Mockery::mock();
        $mock->shouldReceive('put')->andReturnUsing(function ($path, $contents) use ($disk, $product, &$edited) {
            $saved = $disk->put($path, $contents);
            if (! $edited) {
                Product::whereKey($product->id)->update(['meta_title' => 'Concurrent editor title']);
                $edited = true;
            }

            return $saved;
        });
        Storage::shouldReceive('disk')->with('local')->andReturn($mock);
        $result = app(SeoCompletionService::class)->complete((int) auth()->id());
        $this->assertSame(1, $result['skipped']);
        $this->assertSame('Concurrent editor title', $product->fresh()->meta_title);
        $this->assertNull($product->fresh()->meta_description);
        $result = app(SeoCompletionService::class)->complete((int) auth()->id());
        $this->assertSame(2, $result['fields']);
        $this->assertSame('Concurrent editor title', $product->fresh()->meta_title);
    }

    public function test_snapshot_failure_prevents_updates_and_releases_the_run_lock(): void
    {
        $category = $this->category();
        $mock = \Mockery::mock();
        $mock->shouldReceive('put')->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($mock);
        try {
            app(SeoCompletionService::class)->complete((int) auth()->id());
            $this->fail('Metadata changed without a snapshot.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('No metadata was changed', $error->getMessage());
        }
        $this->assertNull($category->fresh()->meta_title);
        $lock = Cache::lock('catalog-seo-completion', 300);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_staff_without_settings_permission_cannot_preview_or_change_metadata(): void
    {
        $this->category();
        $this->actingAs(User::factory()->create(['role' => 'manager', 'permissions' => []]));
        $this->get('/admin/settings/seo-completion')->assertForbidden();
        $this->post('/admin/settings/seo-completion', ['fill_missing' => true])->assertForbidden();
        $this->assertNull(Category::first()->meta_title);
    }

    public function test_the_update_requires_explicit_fill_missing_intent(): void
    {
        $this->category();
        $this->postJson('/admin/settings/seo-completion', [])->assertUnprocessable()->assertJsonValidationErrors('fill_missing');
        $this->assertNull(Category::first()->meta_title);
    }

    public function test_bengali_names_require_english_labels_and_repeated_names_share_the_translation(): void
    {
        $category = $this->category();
        $first = $this->product($category, ['name' => 'প্রিমিয়াম টু-পিস এমব্রয়ডারি কটি সেট', 'sku' => '1001']);
        $second = $this->product($category, ['name' => $first->name, 'sku' => '1002']);
        $this->post('/admin/settings/seo-completion', ['fill_missing' => true])->assertSessionHasErrors('completion');
        $this->assertNull($category->fresh()->meta_title);
        $this->post('/admin/settings/seo-completion', ['fill_missing' => true, 'english_names' => [$first->id => 'Premium Two-Piece Embroidered Waistcoat Set']])->assertSessionHasNoErrors();
        foreach ([$first, $second] as $product) {
            $this->assertSame($first->name, $product->fresh()->name);
            foreach (SeoCompletionService::FIELDS as $field) {
                $this->assertSame(0, preg_match('/\p{Bengali}/u', $product->fresh()->{$field}));
            }
        }
        $this->assertNotSame($first->fresh()->meta_title, $second->fresh()->meta_title);
        $this->assertSame([], app(SeoCompletionService::class)->englishNames());
    }
}
