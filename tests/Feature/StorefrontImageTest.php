<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Category;
use App\Services\Dropshipping\Security\SupplierImageUrlValidator;
use App\Services\StorefrontImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StorefrontImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_image_is_resized_cached_and_keeps_aspect_ratio(): void
    {
        $name = 'uploads/seo-test-'.bin2hex(random_bytes(6)).'.png';
        if (! is_dir(public_path('uploads'))) mkdir(public_path('uploads'), 0775, true);
        $bitmap = imagecreatetruecolor(1200, 800);
        imagepng($bitmap, public_path($name)); imagedestroy($bitmap);
        $category = Category::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Image test', 'regular_price' => 10, 'stock_quantity' => 1, 'is_published' => true]);
        $image = $product->images()->create(['path' => $name]);
        $optimized = null;
        try {
            $first = $this->get('/media/product/'.$image->id.'/640')->assertRedirect();
            $optimized = public_path(ltrim(parse_url($first->headers->get('Location'), PHP_URL_PATH), '/'));
            $this->assertStringContainsString('/uploads/optimized/', str_replace('\\', '/', $optimized));
            $dimensions = getimagesize($optimized);
            $this->assertSame([640, 427, IMAGETYPE_WEBP], array_slice($dimensions, 0, 3));
            $this->get('/media/product/'.$image->id.'/640')->assertRedirect($first->headers->get('Location'));
            $this->get('/media/product/'.$image->id.'/99999')->assertNotFound();
            $product->update(['is_published' => false]);
            $this->get('/media/product/'.$image->id.'/640')->assertNotFound();
        } finally {
            unlink(public_path($name));
            if ($optimized && is_file($optimized) && str_contains(str_replace('\\', '/', $optimized), '/uploads/optimized/')) unlink($optimized);
        }
    }

    public function test_private_supplier_address_is_never_fetched_by_the_server(): void
    {
        Http::fake();
        $this->app->instance(SupplierImageUrlValidator::class, new SupplierImageUrlValidator(fn ($host) => ['127.0.0.1']));
        $category = Category::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Private URL test', 'regular_price' => 10, 'stock_quantity' => 1, 'is_published' => true]);
        $image = $product->images()->create(['path' => 'https://supplier.example.com/private.png']);
        $this->get('/media/product/'.$image->id.'/320')->assertRedirect('https://supplier.example.com/private.png');
        Http::assertNothingSent();
    }

    public function test_generated_variants_for_a_replaced_source_can_be_removed(): void
    {
        $name = 'uploads/categories/cleanup-test-'.bin2hex(random_bytes(6)).'.png';
        if (! is_dir(public_path('uploads/categories'))) mkdir(public_path('uploads/categories'), 0775, true);
        $bitmap = imagecreatetruecolor(320, 240);
        imagepng($bitmap, public_path($name));
        imagedestroy($bitmap);

        $images = app(StorefrontImageService::class);
        $paths = [];
        try {
            foreach ([160, 320] as $width) {
                $response = $images->optimized($name, $width);
                $paths[] = public_path(ltrim($response, '/'));
                $this->assertFileExists(end($paths));
            }

            $this->assertSame(2, $images->forgetOptimizedVariants($name));
            foreach ($paths as $path) {
                $this->assertFileDoesNotExist($path);
            }
        } finally {
            if (is_file(public_path($name))) unlink(public_path($name));
            foreach ($paths as $path) {
                if (is_file($path)) unlink($path);
            }
        }
    }
}
