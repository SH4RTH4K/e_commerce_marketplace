<?php

namespace Tests\Feature;

use App\Models\AbandonedCheckout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbandonedCheckoutPhoneValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_phone_is_not_saved_as_an_abandoned_checkout(): void
    {
        $this->postJson('/checkout/ping', [
            'customer_name' => 'Kazi Akter',
            'customer_phone' => '6502530000',
            'cart_items' => [['name' => 'Test product', 'qty' => 1]],
            'cart_total' => 831,
        ])->assertUnprocessable()->assertJsonValidationErrors('customer_phone');

        $this->assertDatabaseCount('abandoned_checkouts', 0);
    }

    public function test_letters_cannot_be_hidden_inside_an_otherwise_valid_phone(): void
    {
        $this->postJson('/checkout/ping', [
            'customer_phone' => 'abc01712345678',
            'cart_total' => 831,
        ])->assertUnprocessable()->assertJsonValidationErrors('customer_phone');

        $this->assertDatabaseCount('abandoned_checkouts', 0);
    }

    public function test_valid_international_phone_is_normalized_before_tracking(): void
    {
        $this->postJson('/checkout/ping', [
            'customer_name' => 'Kazi Akter',
            'customer_phone' => '+880 1712-345678',
            'cart_items' => [['name' => 'Test product', 'qty' => 1]],
            'cart_total' => 831,
        ])->assertOk()->assertJsonPath('status', 'tracked');

        $this->assertDatabaseHas('abandoned_checkouts', [
            'customer_phone' => '01712345678',
        ]);
    }

    public function test_email_only_abandoned_checkout_can_still_be_tracked(): void
    {
        $this->postJson('/checkout/ping', [
            'customer_name' => 'Email Customer',
            'customer_email' => 'customer@example.com',
            'cart_total' => 500,
        ])->assertOk()->assertJsonPath('status', 'tracked');

        $this->assertDatabaseHas('abandoned_checkouts', [
            'customer_email' => 'customer@example.com',
            'customer_phone' => null,
        ]);
    }

    public function test_admin_cannot_create_an_order_from_a_legacy_invalid_phone(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $checkout = AbandonedCheckout::create([
            'session_id' => 'legacy-invalid-phone',
            'customer_name' => 'Kazi Akter',
            'customer_phone' => '6502530000',
            'shipping_address' => 'Dhaka',
            'cart_total' => 831,
            'cart_items' => [['name' => 'Test product', 'qty' => 1, 'price' => 831]],
        ]);

        $this->post('/admin/abandoned-checkouts/'.$checkout->id.'/create-order')
            ->assertSessionHasErrors('customer_phone');

        $this->assertDatabaseCount('orders', 0);
        $this->assertFalse($checkout->fresh()->is_recovered);
    }
}
