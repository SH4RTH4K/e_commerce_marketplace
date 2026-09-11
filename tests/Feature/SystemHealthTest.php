<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_open_system_health(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/admin/system-health')
            ->assertOk();
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
}
