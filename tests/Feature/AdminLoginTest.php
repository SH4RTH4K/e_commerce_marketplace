<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_log_in_with_username(): void
    {
        $admin = User::factory()->create([
            'username' => 'admin',
            'password' => 'test-password',
            'role' => 'admin',
        ]);

        $response = $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'test-password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_legacy_cpanel_admin_role_can_log_in_and_access_system_health(): void
    {
        $admin = User::factory()->create([
            'username' => 'admin',
            'password' => 'test-password',
            'role' => ' Admin ',
        ]);

        $response = $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'test-password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);

        $this->get('/admin/system-health')->assertOk();
    }

    public function test_admin_email_is_not_accepted_as_a_login_username(): void
    {
        User::factory()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'test-password',
            'role' => 'admin',
        ]);

        $response = $this->from('/admin/login')->post('/admin/login', [
            'username' => 'admin@example.com',
            'password' => 'test-password',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_customer_cannot_use_the_admin_login(): void
    {
        User::factory()->create([
            'username' => 'customer',
            'password' => 'test-password',
            'role' => 'customer',
        ]);

        $response = $this->from('/admin/login')->post('/admin/login', [
            'username' => 'customer',
            'password' => 'test-password',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_invalid_password_is_rejected(): void
    {
        User::factory()->create([
            'username' => 'admin',
            'password' => 'correct-password',
            'role' => 'admin',
        ]);

        $response = $this->from('/admin/login')->post('/admin/login', [
            'username' => 'admin',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }
}
