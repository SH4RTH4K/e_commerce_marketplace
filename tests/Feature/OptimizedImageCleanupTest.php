<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Services\StorefrontImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OptimizedImageCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_scan_and_delete_an_orphaned_optimized_image(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $filename = str_repeat('a', 64).'.webp';
        $directory = public_path('uploads/optimized');
        if (! is_dir($directory)) mkdir($directory, 0775, true);
        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        file_put_contents($path, 'orphan');
        touch($path, now()->subDays(10)->timestamp);

        try {
            $this->get('/admin/system/optimized-images')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('directory', 'public/uploads/optimized')
                    ->where('files', fn ($files) => collect($files)->contains(fn ($file) => ($file['filename'] ?? null) === $filename && ($file['status'] ?? null) === 'orphaned')));

            $this->post('/admin/system/optimized-images/delete', ['filenames' => [$filename]])
                ->assertSessionHasNoErrors();
            $this->assertFileDoesNotExist($path);
        } finally {
            if (is_file($path)) unlink($path);
        }
    }

    public function test_manual_delete_cannot_remove_a_current_referenced_variant(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $source = 'uploads/categories/protected-'.bin2hex(random_bytes(6)).'.png';
        $sourcePath = public_path($source);
        if (! is_dir(dirname($sourcePath))) mkdir(dirname($sourcePath), 0775, true);
        $bitmap = imagecreatetruecolor(160, 160);
        imagepng($bitmap, $sourcePath);
        imagedestroy($bitmap);
        Category::create([
            'name' => 'Protected test category',
            'slug' => 'protected-test-category-'.bin2hex(random_bytes(4)),
            'image' => $source,
            'is_active' => true,
        ]);
        $variant = public_path(ltrim(app(StorefrontImageService::class)->optimized($source, 160), '/'));
        $filename = basename($variant);

        try {
            $this->post('/admin/system/optimized-images/delete', ['filenames' => [$filename]])
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status', fn (string $status) => str_contains($status, 'skipped'));
            $this->assertFileExists($variant);
        } finally {
            if (is_file($sourcePath)) unlink($sourcePath);
            if (is_file($variant)) unlink($variant);
        }
    }
}
