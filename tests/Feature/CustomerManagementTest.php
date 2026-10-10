<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_a_customer_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($admin)
            ->delete(route('admin.customers.destroy', $customer))
            ->assertRedirect(route('admin.customers.index'));

        $this->assertDatabaseMissing('users', ['id' => $customer->id]);
    }

    public function test_admin_can_edit_a_customer_account_by_email_address(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($admin)
            ->get(route('admin.customers.edit', ['customer' => $customer->email]))
            ->assertOk();
    }

    public function test_admin_can_deactivate_a_customer_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.customers.toggle', $customer))
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $customer->id, 'is_active' => false]);
    }

    public function test_customer_list_receives_admin_approval_setting(): void
    {
        Setting::put('customer_approval_required', '1');

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.customers.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Customers/Index')
                ->where('approvalRequired', true));
    }

    public function test_customer_registration_can_require_admin_approval(): void
    {
        Mail::fake();
        Setting::put('customer_approval_required', '1');

        $this->post(route('register.store'), [
            'name' => 'New Customer',
            'email' => 'new.customer@example.com',
            'phone' => '01712345678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('verify'));

        $this->assertDatabaseHas('users', [
            'email' => 'new.customer@example.com',
            'role' => 'customer',
            'is_active' => false,
        ]);
    }

    public function test_customer_verification_blocks_unapproved_accounts(): void
    {
        Mail::fake();
        Setting::put('customer_approval_required', '1');

        $user = User::factory()->create([
            'role' => 'customer',
            'email' => 'pending.customer@example.com',
            'email_verified_at' => null,
            'is_active' => false,
        ]);

        $code = app(OtpService::class)->send($user->email, 'register', $user->name);

        $this->withSession([
            'otp_email' => $user->email,
            'otp_purpose' => 'register',
        ])->post(route('verify.store'), ['code' => $code])
            ->assertRedirect(route('login'));
    }
}
