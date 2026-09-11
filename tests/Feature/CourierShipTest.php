<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CourierShipTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ────────────────────────────────────────────────────────────

    /** Create an admin user and act as them. */
    private function actingAsAdmin(): static
    {
        $admin = User::factory()->create(['role' => 'admin']);
        return $this->actingAs($admin);
    }

    /** Create a confirmed order ready to be shipped. */
    private function makeOrder(array $overrides = []): Order
    {
        return Order::factory()->readyToShip()->create(array_merge([
            'total' => 1500.00,
        ], $overrides));
    }

    // ─── sendToCourier — Happy Paths ────────────────────────────────────────

    #[Test]
    public function steadfast_ships_order_successfully(): void
    {
        Http::fake([
            'portal.packzy.com/*' => Http::response([
                'status'      => 200,
                'consignment' => [
                    'tracking_code'    => 'SF-TEST-001',
                    'consignment_id'   => 99001,
                    'status'           => 'in_review',
                ],
            ], 200),
        ]);

        $order = $this->makeOrder();

        $response = $this->actingAsAdmin()
            ->postJson("/admin/orders/{$order->id}/send-to-courier", [
                'courier_provider' => 'steadfast',
                'cod_amount'       => 1500,
                'item_weight'      => 0.5,
            ]);

        $response->assertOk()
            ->assertJson(['success' => true, 'tracking_code' => '99001']);

        $this->assertDatabaseHas('orders', [
            'id'                    => $order->id,
            'status'                => 'shipped',
            'courier_provider'      => 'steadfast',
            'courier_tracking_code' => '99001',
        ]);
    }

    #[Test]
    public function pathao_ships_order_successfully(): void
    {
        Http::fake([
            'api-hermes.pathao.com/aladdin/api/v1/issue-token' => Http::response([
                'access_token' => 'fake-pathao-token',
                'token_type'   => 'Bearer',
            ], 200),
            'api-hermes.pathao.com/aladdin/api/v1/orders' => Http::response([
                'data' => [
                    'consignment_id' => 'PT-20001',
                    'order_status'   => 'Pending',
                ],
            ], 200),
        ]);

        $order = $this->makeOrder();

        $response = $this->actingAsAdmin()
            ->postJson("/admin/orders/{$order->id}/send-to-courier", [
                'courier_provider' => 'pathao',
                'cod_amount'       => 1500,
            ]);

        $response->assertOk()
            ->assertJson(['success' => true, 'tracking_code' => 'PT-20001']);

        $this->assertDatabaseHas('orders', [
            'id'               => $order->id,
            'status'           => 'shipped',
            'courier_provider' => 'pathao',
        ]);
    }

    #[Test]
    public function redx_ships_order_and_converts_weight_to_grams(): void
    {
        Http::fake([
            'openapi.redx.com.bd/*' => Http::response([
                'tracking_id' => 'RX-55001',
                'id'          => 55001,
            ], 200),
        ]);

        $order = $this->makeOrder();

        $response = $this->actingAsAdmin()
            ->postJson("/admin/orders/{$order->id}/send-to-courier", [
                'courier_provider' => 'redx',
                'cod_amount'       => 1500,
                'item_weight'      => 0.5,  // kg — should become 500g in the request
            ]);

        $response->assertOk()
            ->assertJson(['success' => true, 'tracking_code' => 'RX-55001']);

        // Verify weight was converted to grams in the outgoing request
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'redx.com.bd')
                && $request->data()['parcel_weight'] === 500.0;
        });
    }

    // ─── sendToCourier — Guard: No Re-shipping ───────────────────────────────

    #[Test]
    public function cannot_ship_an_already_shipped_order(): void
    {
        Http::fake();

        $order = Order::factory()->shipped()->create();

        $response = $this->actingAsAdmin()
            ->postJson("/admin/orders/{$order->id}/send-to-courier", [
                'courier_provider' => 'steadfast',
                'cod_amount'       => 1500,
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonPath('message', fn ($msg) => str_contains($msg, 'already shipped'));

        Http::assertNothingSent();
    }

    #[Test]
    public function cannot_ship_a_delivered_order(): void
    {
        Http::fake();

        $order = Order::factory()->create(['status' => 'delivered']);

        $response = $this->actingAsAdmin()
            ->postJson("/admin/orders/{$order->id}/send-to-courier", [
                'courier_provider' => 'redx',
                'cod_amount'       => 800,
            ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
        Http::assertNothingSent();
    }

    // ─── sendToCourier — Error Handling ─────────────────────────────────────

    #[Test]
    public function courier_api_error_returns_422_json_not_html(): void
    {
        Http::fake([
            'portal.packzy.com/*' => Http::response([
                'status'  => 401,
                'message' => 'Invalid API credentials',
            ], 401),
        ]);

        $order = $this->makeOrder();

        $response = $this->actingAsAdmin()
            ->postJson("/admin/orders/{$order->id}/send-to-courier", [
                'courier_provider' => 'steadfast',
                'cod_amount'       => 1500,
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertHeader('Content-Type', 'application/json');

        // Order should NOT be marked as shipped
        $this->assertDatabaseMissing('orders', ['id' => $order->id, 'status' => 'shipped']);
    }

    #[Test]
    public function network_timeout_returns_503_json_not_html(): void
    {
        Http::fake([
            'portal.packzy.com/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out');
            },
        ]);

        $order = $this->makeOrder();

        $response = $this->actingAsAdmin()
            ->postJson("/admin/orders/{$order->id}/send-to-courier", [
                'courier_provider' => 'steadfast',
                'cod_amount'       => 1500,
            ]);

        $response->assertStatus(503)
            ->assertJson(['success' => false])
            ->assertHeader('Content-Type', 'application/json');
    }

    // ─── Webhook ─────────────────────────────────────────────────────────────

    #[Test]
    public function webhook_updates_order_status_when_token_matches(): void
    {
        config(['services.courier_webhook_secret' => 'test-secret-abc']);

        $order = Order::factory()->shipped()->create([
            'courier_tracking_code' => 'SF-TRACK-999',
        ]);

        $response = $this->postJson('/webhooks/courier-status', [
            'tracking_code' => 'SF-TRACK-999',
            'status'        => 'Delivered',
        ], ['X-Webhook-Token' => 'test-secret-abc']);

        $response->assertOk()->assertJson(['status' => 'updated']);

        $this->assertDatabaseHas('orders', [
            'id'             => $order->id,
            'status'         => 'delivered',
            'courier_status' => 'Delivered',
        ]);
    }

    #[Test]
    public function webhook_handles_cancel_status_case_insensitively(): void
    {
        config(['services.courier_webhook_secret' => null]);

        $order = Order::factory()->shipped()->create([
            'courier_tracking_code' => 'RX-CANCEL-001',
        ]);

        $this->postJson('/webhooks/courier-status', [
            'tracking_code' => 'RX-CANCEL-001',
            'status'        => 'Return to Courier Hub',
        ])->assertOk();

        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => 'cancelled',
        ]);
    }

    #[Test]
    public function webhook_rejects_request_with_wrong_token(): void
    {
        config(['services.courier_webhook_secret' => 'correct-secret']);

        $response = $this->postJson('/webhooks/courier-status', [
            'tracking_code' => 'SF-TRACK-000',
            'status'        => 'Delivered',
        ], ['X-Webhook-Token' => 'wrong-token']);

        $response->assertStatus(401)->assertJson(['status' => 'unauthorized']);
    }

    #[Test]
    public function webhook_rejects_request_with_missing_token(): void
    {
        config(['services.courier_webhook_secret' => 'correct-secret']);

        $response = $this->postJson('/webhooks/courier-status', [
            'tracking_code' => 'SF-TRACK-000',
            'status'        => 'Delivered',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function webhook_skips_auth_when_secret_is_not_configured(): void
    {
        config(['services.courier_webhook_secret' => null]);

        $order = Order::factory()->shipped()->create([
            'courier_tracking_code' => 'SF-NO-AUTH',
        ]);

        $response = $this->postJson('/webhooks/courier-status', [
            'tracking_code' => 'SF-NO-AUTH',
            'status'        => 'delivered',
        ]);

        $response->assertOk()->assertJson(['status' => 'updated']);
    }

    // ─── Route Auth Guard ────────────────────────────────────────────────────

    #[Test]
    public function guest_cannot_ship_an_order(): void
    {
        $order = $this->makeOrder();

        $this->postJson("/admin/orders/{$order->id}/send-to-courier", [
            'courier_provider' => 'steadfast',
            'cod_amount'       => 1500,
        ])->assertRedirect();
    }

    #[Test]
    public function unknown_courier_provider_is_rejected(): void
    {
        $order = $this->makeOrder();

        $this->actingAsAdmin()
            ->postJson("/admin/orders/{$order->id}/send-to-courier", [
                'courier_provider' => 'fake-courier',
                'cod_amount'       => 1500,
            ])->assertStatus(422);
    }
}
