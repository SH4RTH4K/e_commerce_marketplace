<?php

namespace Tests\Feature;

use App\Models\ApplicationUpdateSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApplicationUpdateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_admin_can_open_the_git_repository_screen(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/admin/system/git-repository');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/GitRepository')
            ->where('settings.repository_url', 'https://github.com/SH4RTH4K/e_commerce_marketplace.git')
            ->where('settings.enabled', false));
    }

    #[Test]
    public function repository_settings_are_saved_with_safe_defaults(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post('/admin/system/git-repository', [
                'enabled' => '1',
                'repository_type' => 'public',
                'repository_url' => 'https://github.com/SH4RTH4K/e_commerce_marketplace.git',
                'branch' => 'main',
                'remote_name' => 'origin',
                'authentication' => 'none',
                'run_migrations' => '1',
                'clear_cache' => '1',
                'health_check' => '1',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('application_update_settings', [
            'repository_url' => 'https://github.com/SH4RTH4K/e_commerce_marketplace.git',
            'branch' => 'main',
            'remote_name' => 'origin',
            'enabled' => 1,
            'authentication' => 'none',
        ]);
    }

    #[Test]
    public function enabling_repository_settings_keeps_an_existing_checkout_managed(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post('/admin/system/git-repository', [
                'enabled' => '1',
                'repository_type' => 'public',
                'repository_url' => 'https://github.com/SH4RTH4K/e_commerce_marketplace.git',
                'branch' => 'main',
                'remote_name' => 'origin',
                'authentication' => 'none',
                'run_migrations' => '1',
                'clear_cache' => '1',
                'health_check' => '1',
            ]);

        $response->assertRedirect();
        $this->assertDirectoryExists(base_path('.git'));
        $this->assertDatabaseHas('application_update_settings', ['enabled' => 1]);
    }

    #[Test]
    public function private_repository_requires_a_credential(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->from('/admin/system/git-repository')
            ->post('/admin/system/git-repository', [
                'enabled' => '1',
                'repository_type' => 'private',
                'repository_url' => 'https://github.com/company/private-repository.git',
                'branch' => 'main',
                'remote_name' => 'origin',
                'authentication' => 'pat',
                'run_migrations' => '1',
                'clear_cache' => '1',
                'health_check' => '1',
            ]);

        $response->assertRedirect('/admin/system/git-repository');
        $response->assertSessionHasErrors('secret');
        $this->assertDatabaseMissing('application_update_settings', ['repository_url' => 'https://github.com/company/private-repository.git']);
    }
}
