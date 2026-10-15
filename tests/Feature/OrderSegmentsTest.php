<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderSegmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_all_orders_includes_every_stage_and_segment_counts(): void
    {
        foreach (Order::STATUSES as $status) Order::factory()->create(['status' => $status]);
        $this->get('/admin/orders')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')->where('status', 'all')->has('orders.data', 6)
            ->where('counts.all', 6)->where('counts.pending', 1)->where('counts.processing', 1)
            ->where('counts.pending_verification', 0)->where('listedTotal', 6360));
    }

    #[DataProvider('statuses')]
    public function test_each_fulfilment_segment_contains_only_its_own_orders(string $status): void
    {
        foreach (Order::STATUSES as $stage) Order::factory()->create(['status' => $stage]);
        $this->get('/admin/orders?status='.$status)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('status', $status)->has('orders.data', 1)->where('orders.data.0.status', $status)
            ->where('counts.all', 6)->where('counts.'.$status, 1));
    }

    public static function statuses(): array
    {
        return array_map(fn ($status) => [$status], Order::STATUSES);
    }

    public function test_payment_verification_excludes_cod_reviewed_and_closed_orders(): void
    {
        foreach (Order::MOBILE_BANKING_METHODS as $method) Order::factory()->create(['payment_method' => $method]);
        Order::factory()->create(['payment_method' => 'cod']);
        Order::factory()->create(['payment_method' => 'bkash', 'payment_status' => 'verified']);
        Order::factory()->create(['payment_method' => 'bkash', 'payment_status' => 'rejected']);
        Order::factory()->create(['payment_method' => 'bkash', 'status' => 'cancelled']);
        Order::factory()->create(['payment_method' => 'bkash', 'status' => 'delivered']);
        $this->get('/admin/orders?status=pending_verification')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 3)->where('counts.pending_verification', 3)->where('counts.all', 8)
            ->where('admin_badges.orders_all', 8)->where('admin_badges.orders_pending_verification', 3)
            ->where('admin_badges.pending_orders', 6));
    }

    public function test_segment_counts_follow_filters_while_sidebar_counts_remain_global(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 10:00:00', 'Asia/Dhaka'));
        foreach (['pending', 'processing'] as $status) {
            Order::factory()->create(['customer_name' => 'Matched Customer', 'status' => $status, 'payment_method' => 'bkash']);
        }
        Order::factory()->create(['customer_name' => 'Different Customer', 'payment_method' => 'bkash']);
        Order::factory()->create(['customer_name' => 'Matched Customer', 'payment_method' => 'cod']);
        Order::factory()->create(['customer_name' => 'Matched Customer', 'payment_method' => 'bkash', 'created_at' => now()->subDays(2)]);
        $this->get('/admin/orders?status=processing&q=Matched&method=bkash&date_filter=today')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('counts.all', 2)
                ->where('counts.pending', 1)->where('counts.processing', 1)->where('counts.pending_verification', 2)
                ->where('listedTotal', 1060)->where('admin_badges.orders_all', 5));
    }

    public function test_dhaka_today_and_yesterday_use_utc_database_boundaries(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 10:00:00', 'Asia/Dhaka'));
        $before = Order::factory()->create(['created_at' => '2026-10-14 17:59:59']);
        $start = Order::factory()->create(['created_at' => '2026-10-14 18:00:00']);
        $end = Order::factory()->create(['created_at' => '2026-10-15 17:59:59']);
        Order::factory()->create(['created_at' => '2026-10-15 18:00:00']);
        $this->get('/admin/orders?date_filter=today&sort=oldest')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 2)->where('orders.data.0.id', $start->id)->where('orders.data.1.id', $end->id));
        $this->get('/admin/orders?date_filter=yesterday')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)->where('orders.data.0.id', $before->id));
    }

    public function test_custom_range_and_last_month_include_whole_local_days(): void
    {
        $this->travelTo(Carbon::parse('2026-10-31 10:00:00', 'Asia/Dhaka'));
        $order = Order::factory()->create(['created_at' => '2026-09-30 17:59:59']);
        Order::factory()->create(['created_at' => '2026-09-30 18:00:00']);
        $this->get('/admin/orders?date_filter=last_month')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)->where('orders.data.0.id', $order->id));
        $this->get('/admin/orders?from_date=2026-09-30&to_date=2026-09-30')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dateFilter', 'custom')->has('orders.data', 1)->where('orders.data.0.id', $order->id));
    }

    public function test_transaction_id_and_email_search_work(): void
    {
        $order = Order::factory()->create(['payment_txn_id' => 'TXN-FIND-1', 'customer_email' => 'find-order@example.test']);
        Order::factory()->create();
        foreach (['TXN-FIND-1', 'find-order@example.test'] as $query) {
            $this->get('/admin/orders?q='.urlencode($query))->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('orders.data', 1)->where('orders.data.0.id', $order->id));
        }
    }

    public function test_status_updates_move_orders_between_segments_and_refresh_counts(): void
    {
        $order = Order::factory()->create(['status' => 'confirmed']);
        $this->patch('/admin/orders/'.$order->id, ['status' => 'processing'])->assertSessionHasNoErrors();
        $this->get('/admin/orders?status=confirmed')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 0)->where('counts.confirmed', 0)->where('counts.processing', 1));
        $this->get('/admin/orders?status=processing')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)->where('orders.data.0.id', $order->id));
    }

    public function test_sorting_page_size_and_pagination_preserve_the_segment(): void
    {
        Order::factory()->count(27)->create(['status' => 'processing', 'total' => 100]);
        $largest = Order::factory()->create(['status' => 'processing', 'total' => 5000]);
        Order::factory()->create(['status' => 'pending', 'total' => 9999]);
        $this->get('/admin/orders?status=processing&per_page=25&sort=total_desc')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 25)->where('orders.data.0.id', $largest->id)->where('orders.total', 28)
            ->where('per_page', 25)->where('orders.next_page_url', fn ($url) => str_contains($url, 'status=processing') && str_contains($url, 'sort=total_desc')));
        $this->get('/admin/orders?status=processing&per_page=25&sort=total_desc&page=2')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 3)->where('orders.current_page', 2));
    }

    public function test_invalid_filters_are_normalized_and_invalid_dates_are_rejected(): void
    {
        Order::factory()->create();
        $this->get('/admin/orders?status=unknown&method=other&sort=bad&per_page=1000&date_filter=invalid')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('status', 'all')->where('method', '')
                ->where('sort', 'newest')->where('per_page', 15)->where('dateFilter', 'all')->has('orders.data', 1));
        $this->getJson('/admin/orders?date_filter=custom&from_date=not-a-date')->assertUnprocessable()->assertJsonValidationErrors('from_date');
        $this->getJson('/admin/orders?date_filter=custom&from_date=2026-10-15&to_date=2026-10-14')->assertUnprocessable()->assertJsonValidationErrors('to_date');
    }

    public function test_order_view_permission_is_required_for_segments(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff', 'permissions' => ['orders.view']]))
            ->get('/admin/orders?status=pending')->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'staff', 'permissions' => ['products.view']]))
            ->get('/admin/orders?status=pending')->assertForbidden();
    }
}
