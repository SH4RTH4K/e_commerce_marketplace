<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Services\OptimizedImageCleanupService;
use App\Services\StorefrontImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
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
            $scan = app(OptimizedImageCleanupService::class)->scan();
            $this->assertTrue(collect($scan['files'])->contains(fn ($file) => ($file['filename'] ?? null) === $filename));

            $this->get('/admin/system/optimized-images')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('directory', 'public/uploads/optimized')
                    ->where('pagination.per_page', 50)
                    ->where('pagination.total', $scan['orphaned_count']));

            $this->post('/admin/system/optimized-images/delete', ['filenames' => [$filename]])
                ->assertSessionHasNoErrors();
            $this->assertFileDoesNotExist($path);
            $this->assertNotEmpty(glob(storage_path('app/private/optimized-image-trash/*/'.$filename)) ?: []);
        } finally {
            if (is_file($path)) unlink($path);
            foreach (glob(storage_path('app/private/optimized-image-trash/*/'.$filename)) ?: [] as $trashPath) {
                if (is_file($trashPath)) unlink($trashPath);
                $directory = dirname($trashPath);
                if (is_dir($directory)) File::deleteDirectory($directory);
            }
        }
    }

    public function test_recovery_trash_is_purged_only_after_retention_period(): void
    {
        $trashRoot = storage_path('app/private/optimized-image-trash');
        $oldDirectory = $trashRoot.'/old-batch';
        $recentDirectory = $trashRoot.'/recent-batch';
        File::ensureDirectoryExists($oldDirectory, 0700);
        File::ensureDirectoryExists($recentDirectory, 0700);
        file_put_contents($oldDirectory.'/old.webp', 'old');
        file_put_contents($recentDirectory.'/recent.webp', 'recent');
        touch($oldDirectory, now()->subDays(31)->timestamp);
        touch($recentDirectory, now()->subDays(2)->timestamp);

        try {
            $result = app(OptimizedImageCleanupService::class)->purgeTrash(30);

            $this->assertSame(1, $result['purged']);
            $this->assertDirectoryDoesNotExist($oldDirectory);
            $this->assertFileExists($recentDirectory.'/recent.webp');
        } finally {
            if (is_dir($trashRoot)) File::deleteDirectory($trashRoot);
        }
    }

    public function test_admin_can_view_preview_and_restore_a_quarantined_image(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $filename = str_repeat('b', 64).'.webp';
        $directory = public_path('uploads/optimized');
        if (! is_dir($directory)) mkdir($directory, 0775, true);
        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        file_put_contents($path, 'orphan');

        try {
            $this->post('/admin/system/optimized-images/delete', ['filenames' => [$filename]])
                ->assertSessionHasNoErrors();
            $trashPath = (glob(storage_path('app/private/optimized-image-trash/*/'.$filename)) ?: [])[0] ?? null;
            $this->assertNotNull($trashPath);
            $batch = basename(dirname($trashPath));

            $this->get('/admin/system/recovery-trash')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('batches.0.files.0.filename', $filename));
            $this->get('/admin/system/recovery-trash/'.$batch.'/'.$filename.'/preview')
                ->assertOk()
                ->assertHeader('content-type', 'image/webp');
            $this->post('/admin/system/recovery-trash/restore', ['batch' => $batch, 'filename' => $filename])
                ->assertSessionHas('status', 'Optimized image restored successfully.');
            $this->assertFileExists($path);
            $this->assertFileDoesNotExist($trashPath);
        } finally {
            if (is_file($path)) unlink($path);
            foreach (glob(storage_path('app/private/optimized-image-trash/*/'.$filename)) ?: [] as $trashPath) {
                if (is_file($trashPath)) unlink($trashPath);
                $batchDirectory = dirname($trashPath);
                if (is_dir($batchDirectory)) File::deleteDirectory($batchDirectory);
            }
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
