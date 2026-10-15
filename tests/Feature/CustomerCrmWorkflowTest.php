<?php

namespace Tests\Feature;

use App\Models\CustomerNote;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerCrmWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_guest_orders_with_different_names_and_phone_formats_form_one_profile(): void
    {
        Order::factory()->create(['customer_name' => 'First Name', 'customer_phone' => '01712345678', 'status' => 'delivered', 'total' => 500]);
        Order::factory()->create(['customer_name' => 'Latest Name', 'customer_phone' => '+88 017-1234-5678', 'status' => 'delivered', 'total' => 700]);
        Order::factory()->create(['customer_phone' => '008801712345678', 'status' => 'cancelled', 'total' => 900]);
        $this->get('/admin/customers')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 1)->where('customers.data.0.key', 'phone-01712345678')
            ->where('customers.data.0.orders_count', 3)->where('customers.data.0.total_spent', 1200)
            ->where('summary.repeat', 1));
        $this->get('/admin/customers/profiles/phone-01712345678')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 3)->where('customer.cancelled_count', 1)->where('customer.total_spent', 1200));
    }

    public function test_registered_account_merges_unique_phone_guest_checkouts_and_supports_changed_order_contacts(): void
    {
        $account = User::factory()->create(['phone' => '01712345678']);
        Order::factory()->create(['user_id' => $account->id, 'customer_phone' => '01812345678']);
        Order::factory()->create(['customer_phone' => '+8801712345678']);
        $this->get('/admin/customers')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 1)->where('customers.data.0.account_id', $account->id)->where('customers.data.0.orders_count', 2));
        $this->get('/admin/customers/profiles/account-'.$account->id)->assertOk()->assertInertia(fn (Assert $page) => $page->has('orders.data', 2));
        $this->get('/admin/customers/01712345678')->assertRedirect('/admin/customers/profiles/account-'.$account->id);
    }

    public function test_guest_profile_keeps_last_available_email_and_edit_returns_only_to_crm(): void
    {
        Order::factory()->create(['customer_phone' => '01712345678', 'customer_email' => 'known@example.test']);
        Order::factory()->create(['customer_phone' => '01712345678', 'customer_email' => null]);
        $this->get('/admin/customers/profiles/phone-01712345678')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('customer.customer_email', 'known@example.test'));
        $account = User::factory()->create();
        $payload = ['name' => $account->name, 'email' => $account->email, 'is_active' => true, 'return_to' => 'https://example.com'];
        $this->put('/admin/customers/'.$account->id, $payload)->assertRedirect('/admin/customers');
        $payload['return_to'] = '/admin/customers?segment=registered';
        $this->put('/admin/customers/'.$account->id, $payload)->assertRedirect('/admin/customers?segment=registered');
    }

    public function test_staff_created_orders_are_customer_guests_and_never_staff_accounts(): void
    {
        Order::factory()->create(['user_id' => auth()->id(), 'customer_phone' => '01712345678']);
        $this->get('/admin/customers')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 1)->where('customers.data.0.account_id', null)->where('customers.data.0.key', 'phone-01712345678'));
        $this->get('/admin/customers/profiles/account-'.auth()->id())->assertNotFound();
    }

    public function test_shared_phone_accounts_remain_separate_from_unattributed_guest_orders(): void
    {
        $first = User::factory()->create(['phone' => '01712345678']);
        $second = User::factory()->create(['phone' => '+8801712345678']);
        Order::factory()->create(['user_id' => $first->id, 'customer_phone' => '01712345678']);
        Order::factory()->create(['customer_phone' => '01712345678']);
        $this->get('/admin/customers')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 3)->where('summary.registered', 2)->where('summary.guest', 1));
        $this->get('/admin/customers/profiles/account-'.$second->id)->assertOk()->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));
        $this->get('/admin/customers/profiles/phone-01712345678')->assertOk()->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));
    }

    public function test_missing_or_invalid_phone_orders_do_not_merge_unrelated_buyers(): void
    {
        $first = Order::factory()->create(['customer_phone' => '']);
        Order::factory()->create(['customer_phone' => 'Unknown']);
        $this->get('/admin/customers')->assertOk()->assertInertia(fn (Assert $page) => $page->has('customers.data', 2));
        $this->get('/admin/customers/profiles/order-'.$first->id)->assertOk()->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));
    }

    public function test_accounts_without_orders_have_real_registration_dates_and_no_last_order(): void
    {
        $account = User::factory()->create(['is_active' => false]);
        $this->get('/admin/customers?segment=no_orders')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 1)->where('customers.data.0.last_order_at', null)->where('summary.inactive', 1));
        $this->get('/admin/customers/profiles/account-'.$account->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 0)->where('customer.is_active', false));
    }

    public function test_search_segments_and_activity_scope_summary_and_delivered_value(): void
    {
        $account = User::factory()->create(['name' => 'Search Person', 'email' => 'find@example.test', 'phone' => '01712345678', 'is_active' => false]);
        Order::factory()->create(['user_id' => $account->id, 'customer_phone' => '01712345678', 'status' => 'delivered', 'total' => 500]);
        Order::factory()->create(['customer_phone' => '01812345678', 'created_at' => now()->subDays(100)]);
        $this->get('/admin/customers?q=find%40example.test&segment=inactive&activity=recent')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 1)->where('summary.all', 1)->where('deliveredValue', 500));
        $this->get('/admin/customers?q=%2B8801712345678')->assertOk()->assertInertia(fn (Assert $page) => $page->has('customers.data', 1));
        $this->get('/admin/customers?activity=dormant')->assertOk()->assertInertia(fn (Assert $page) => $page->has('customers.data', 1)->where('summary.guest', 1));
    }

    public function test_profile_orders_are_paginated_and_status_filter_preserves_customer_totals(): void
    {
        Order::factory()->count(17)->create(['customer_phone' => '01712345678', 'status' => 'processing']);
        Order::factory()->create(['customer_phone' => '01712345678', 'status' => 'cancelled']);
        $this->get('/admin/customers/profiles/phone-01712345678?order_status=processing')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 15)->where('orders.total', 17)->where('customer.orders_count', 18));
        $this->get('/admin/customers/profiles/phone-01712345678?order_status=processing&page=2')->assertOk()->assertInertia(fn (Assert $page) => $page->has('orders.data', 2));
    }

    public function test_private_notes_record_actor_and_follow_unique_guest_to_account_link(): void
    {
        Order::factory()->create(['customer_phone' => '01712345678']);
        $this->post('/admin/customers/profiles/phone-01712345678/notes', ['body' => 'Customer requested afternoon delivery.', 'user_id' => 999])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customer_notes', ['customer_key' => 'phone-01712345678', 'user_id' => auth()->id(), 'author_name' => auth()->user()->name]);
        $account = User::factory()->create(['phone' => '01712345678']);
        $this->get('/admin/customers/profiles/account-'.$account->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('notes.data', 1)->where('notes.data.0.body', 'Customer requested afternoon delivery.'));
        $this->postJson('/admin/customers/profiles/account-'.$account->id.'/notes', ['body' => '  '])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->postJson('/admin/customers/profiles/account-'.$account->id.'/notes', ['body' => str_repeat('a', 2001)])->assertUnprocessable();
    }

    public function test_explicit_account_activation_is_idempotent_and_deletion_keeps_orders(): void
    {
        $account = User::factory()->create(['is_active' => false]);
        $order = Order::factory()->create(['user_id' => $account->id]);
        for ($i = 0; $i < 2; $i++) $this->patch('/admin/customers/'.$account->id.'/toggle', ['is_active' => true])->assertSessionHasNoErrors();
        $this->assertTrue($account->fresh()->isActive());
        CustomerNote::create(['customer_key' => 'account-'.$account->id, 'author_name' => 'Admin', 'body' => 'Account-only note']);
        $this->delete('/admin/customers/'.$account->id)->assertRedirect('/admin/customers');
        $this->assertNotNull($order->fresh());
        $this->assertNull($order->fresh()->user_id);
        $this->assertSame(0, CustomerNote::count());
    }

    public function test_export_uses_filtered_customers_and_neutralizes_formulas(): void
    {
        User::factory()->create(['name' => '=1+1', 'email' => 'export@example.test']);
        Order::factory()->create(['customer_phone' => '01712345678']);
        $csv = $this->get('/admin/customers/export?segment=no_orders')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringNotContainsString('01712345678', $csv);
    }

    public function test_customer_management_requires_permission_and_blocks_staff_account_edits(): void
    {
        $staff = User::factory()->create(['role' => 'support', 'permissions' => ['customers.manage']]);
        $this->actingAs($staff)->get('/admin/customers')->assertOk();
        $this->get('/admin/customers/'.$staff->id.'/edit')->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'inventory', 'permissions' => ['products.view']]))->get('/admin/customers')->assertForbidden();
        $this->get('/admin/customers/export')->assertForbidden();
        $this->post('/admin/customers/profiles/phone-01712345678/notes', ['body' => 'No access'])->assertForbidden();
    }
}
