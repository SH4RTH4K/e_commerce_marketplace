<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
