<?php

namespace Tests\Feature;

use App\Models\BlockedIp;
use App\Models\Setting;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FakeOrderGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $category = \App\Models\Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category',
        ]);
        
        $this->product = Product::create([
            'category_id'    => $category->id,
            'name'           => 'Test Product',
            'slug'           => 'test-product',
            'regular_price'  => 1000,
            'stock_quantity' => 10,
            'is_published'   => true,
        ]);
    }

    private function fillCart()
    {
        $this->post('/cart/add', [
            'product_id' => $this->product->id,
            'qty'        => 1,
        ]);
    }

    public function test_rejects_invalid_bangladesh_phone_number()
    {
        $this->fillCart();

        $response = $this->post('/checkout', [
            'customer_name'    => 'John Doe',
            'customer_phone'   => '01234567890', // Invalid prefix (012 instead of 013-019)
            'shipping_address' => '123 Test St',
            'city'             => 'Dhaka',
            'shipping_zone'    => 'inside_dhaka',
            'payment_method'   => 'cod',
        ]);

        $response->dump();

        $response->assertSessionHasErrors(['customer_phone']);
    }

    public function test_accepts_valid_bangladesh_phone_number()
    {
        $this->fillCart();

        $response = $this->post('/checkout', [
            'customer_name'    => 'John Doe',
            'customer_phone'   => '01711223344', // Valid GP number
            'shipping_address' => '123 Test St',
            'city'             => 'Dhaka',
            'shipping_zone'    => 'inside_dhaka',
            'payment_method'   => 'cod',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        
        $this->assertDatabaseHas('orders', [
            'customer_phone' => '01711223344',
            'ip_address'     => '127.0.0.1',
        ]);
    }

    public function test_blocks_checkout_if_ip_is_blocked()
    {
        BlockedIp::create([
            'ip_address' => '127.0.0.1',
            'reason'     => 'Spammer',
        ]);

        $this->fillCart();

        $response = $this->post('/checkout', [
            'customer_name'    => 'John Doe',
            'customer_phone'   => '01711223344',
            'shipping_address' => '123 Test St',
            'city'             => 'Dhaka',
            'shipping_zone'    => 'inside_dhaka',
            'payment_method'   => 'cod',
        ]);

        $response->assertSessionHasErrors('cart');
        $this->assertNotEmpty(session('errors')->first('cart'));
    }

    public function test_rate_limits_multiple_orders_from_same_ip()
    {
        // Set setting to 10 minutes limit
        \App\Models\Setting::put('fraud_order_time_limit_minutes', '10');

        // First order
        $this->fillCart();
        $this->post('/checkout', [
            'customer_name'    => 'John Doe 1',
            'customer_phone'   => '01711223344',
            'shipping_address' => '123 Test St',
            'city'             => 'Dhaka',
            'shipping_zone'    => 'inside_dhaka',
            'payment_method'   => 'cod',
        ]);

        $this->assertDatabaseCount('orders', 1);

        // Attempt second order immediately
        $this->fillCart();
        $response = $this->post('/checkout', [
            'customer_name'    => 'John Doe 2',
            'customer_phone'   => '01811223344',
            'shipping_address' => '456 Test St',
            'city'             => 'Dhaka',
            'shipping_zone'    => 'inside_dhaka',
            'payment_method'   => 'cod',
        ]);

        $response->assertSessionHasErrors('cart');
        $this->assertNotEmpty(session('errors')->first('cart'));
        $this->assertDatabaseCount('orders', 1); // Second order was not saved
    }

    public function test_admin_can_load_live_plan_information(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Setting::put('fog_bdcourier_api_key', 'valid-test-key');
        Http::fake([
            'api.bdcourier.com/my-plan' => Http::response([
                'status' => 'success',
                'data' => [
                    'plan_name' => 'Free B',
                    'plan_type' => 'free',
                    'status' => 'active',
                    'api_calls' => 5,
                    'call_limit' => 80,
                    'paid_calls' => 0,
                    'paid_limit' => 0,
                    'remaining_free_calls' => 75,
                    'remaining_paid_calls' => 0,
                ],
            ]),
        ]);

        $this->actingAs($admin)
            ->getJson('/admin/fake-order-guard/plan-info')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.plan_name', 'Free B')
            ->assertJsonPath('data.api_calls', 5)
            ->assertJsonPath('data.remaining_free_calls', 75);
    }

    public function test_manual_check_history_response_contains_courier_breakdown(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Setting::put('fog_enabled', '1');
        Setting::put('fog_bdcourier_enabled', '1');
        Setting::put('fog_bdcourier_api_key', 'valid-test-key');
        Http::fake([
            'api.bdcourier.com/courier-check*' => Http::response([
                'status' => 'success',
                'data' => [
                    'pathao' => [
                        'name' => 'Pathao',
                        'total_parcel' => 4,
                        'success_parcel' => 3,
                    ],
                ],
            ]),
        ]);

        $this->actingAs($admin)
            ->postJson('/admin/fake-order-guard/check-phone', [
                'phone' => '01711223344',
                'customer_name' => 'Manual Customer',
            ])
            ->assertOk()
            ->assertJsonPath('_history.customer_name', 'Manual Customer')
            ->assertJsonPath('_history.couriers.0.name', 'Pathao')
            ->assertJsonPath('_history.couriers.0.total_parcel', 4)
            ->assertJsonPath('_history.couriers.0.cancelled_parcel', 1);
    }
}
