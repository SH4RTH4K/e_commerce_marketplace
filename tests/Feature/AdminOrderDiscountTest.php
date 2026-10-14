<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminOrderDiscountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Setting::put('tax_percent', '10');
        Setting::put('currency_symbol', '৳');
    }

    public function test_admin_can_apply_change_and_remove_a_discount_on_a_customer_order(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'discount_amount' => 0,
            'tax' => 100,
            'total' => 1160,
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        foreach ([[125.50, '87.45', '1021.95'], [200, '80.00', '940.00'], [0, '100.00', '1160.00']] as [$discount, $tax, $total]) {
            $this->from("/admin/orders/{$order->id}")
                ->patch("/admin/orders/{$order->id}", ['discount_amount' => $discount])
                ->assertRedirect("/admin/orders/{$order->id}")
                ->assertSessionHasNoErrors();

            $order->refresh();
            $this->assertEquals($discount, $order->discount_amount);
            $this->assertSame($tax, $order->tax);
            $this->assertSame($total, $order->total);
            $this->assertSame('1000.00', $order->subtotal);
            $this->assertSame('60.00', $order->shipping_charge);
            $this->assertSame('pending', $order->status);
            $this->assertSame($customer->id, $order->user_id);
        }
    }

    public function test_saved_discount_and_total_appear_on_the_order_page_and_invoice(): void
    {
        $order = Order::factory()->create(['tax' => 100, 'total' => 1160]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch("/admin/orders/{$order->id}", ['discount_amount' => 200])
            ->assertSessionHasNoErrors();

        $this->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Orders/Show')
                ->where('order.discount_amount', '200.00')
                ->where('order.total', '940.00')
                ->where('taxPercent', 10));

        $this->get("/admin/orders/{$order->id}/invoice")
            ->assertOk()
            ->assertSee('-৳200.00', false)
            ->assertSee('৳940.00', false);
    }

    public function test_full_product_discount_keeps_delivery_charge_and_zero_tax(): void
    {
        $order = Order::factory()->create(['tax' => 100, 'total' => 1160]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch("/admin/orders/{$order->id}", ['discount_amount' => 1000])
            ->assertSessionHasNoErrors();

        $this->assertSame('0.00', $order->fresh()->tax);
        $this->assertSame('60.00', $order->fresh()->total);
    }

    #[DataProvider('invalidDiscounts')]
    public function test_invalid_discounts_do_not_change_order_totals(mixed $discount): void
    {
        $order = Order::factory()->create(['discount_amount' => 100, 'tax' => 90, 'total' => 1050]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patchJson("/admin/orders/{$order->id}", ['discount_amount' => $discount])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('discount_amount');

        $this->assertSame('100.00', $order->fresh()->discount_amount);
        $this->assertSame('90.00', $order->fresh()->tax);
        $this->assertSame('1050.00', $order->fresh()->total);
    }

    public static function invalidDiscounts(): array
    {
        return [
            'negative' => [-1],
            'above subtotal' => [1000.01],
            'not a number' => ['invalid'],
            'empty' => [''],
            'null' => [null],
            'fraction of a cent' => [10.001],
        ];
    }

    public function test_other_order_updates_and_unchanged_discounts_preserve_saved_totals(): void
    {
        $order = Order::factory()->create(['discount_amount' => 100, 'tax' => 45, 'total' => 1005]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        foreach ([['internal_note' => 'Call before delivery'], ['discount_amount' => 100]] as $data) {
            $this->patch("/admin/orders/{$order->id}", $data)->assertSessionHasNoErrors();
            $this->assertSame('100.00', $order->fresh()->discount_amount);
            $this->assertSame('45.00', $order->fresh()->tax);
            $this->assertSame('1005.00', $order->fresh()->total);
        }
    }

    public function test_customer_cannot_apply_admin_discount(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = Order::factory()->create(['user_id' => $customer->id]);
        $this->actingAs($customer)
            ->patch("/admin/orders/{$order->id}", ['discount_amount' => 500])
            ->assertRedirect(route('admin.login'));

        $this->assertSame('0.00', $order->fresh()->discount_amount);
        $this->assertSame('1060.00', $order->fresh()->total);
    }
}
