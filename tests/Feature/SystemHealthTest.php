<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_open_system_health(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/admin/system-health')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('health.disk.ok', true)
                ->where('health.disk.message', fn ($message) => str_contains($message, 'Project files use'))
                ->has('health.disk.breakdown')
                ->where('databaseCache.available', true)
                ->where('databaseCache.table', 'cache')
                ->has('databaseCache.total_rows'));
    }

    public function test_customers_cannot_open_system_health(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get('/admin/system-health')
            ->assertRedirect(route('admin.login'));
    }

    public function test_database_backup_is_recorded_and_stored_on_the_private_disk(): void
    {
        $backupId = app(DatabaseBackupService::class)->create('test');
        $backup = DB::table('system_backups')->find($backupId);

        $this->assertNotNull($backup);
        $this->assertSame('completed', $backup->status);
        $this->assertGreaterThan(0, $backup->size_bytes);
        Storage::disk('local')->assertExists('backups/'.$backup->filename);

        Storage::disk('local')->delete('backups/'.$backup->filename);
        DB::table('system_backups')->where('id', $backupId)->delete();
    }

    public function test_an_admin_can_prune_only_expired_database_cache_rows(): void
    {
        DB::table('cache')->insert([
            ['key' => 'expired-cache-row', 'value' => 'expired', 'expiration' => now()->subMinute()->timestamp],
            ['key' => 'active-cache-row', 'value' => 'active', 'expiration' => now()->addHour()->timestamp],
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post('/admin/system-health/cache/prune-expired')
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('cache', ['key' => 'expired-cache-row']);
        $this->assertDatabaseHas('cache', ['key' => 'active-cache-row']);
    }

    public function test_an_admin_can_empty_the_database_cache_without_dropping_its_table(): void
    {
        DB::table('cache')->insert([
            'key' => 'temporary-cache-row',
            'value' => 'temporary',
            'expiration' => now()->addHour()->timestamp,
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post('/admin/system-health/cache/clear')
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(0, DB::table('cache')->count());
        $this->assertTrue(Schema::hasTable('cache'));
    }
}
