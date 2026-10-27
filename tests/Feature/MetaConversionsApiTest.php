<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\MetaConversionsApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MetaConversionsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_purchase_is_hashed_consent_gated_and_deduplicated(): void
    {
        $this->enableCapi();
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);

        $order = $this->orderWithItem();
        $request = Request::create('/', 'GET', [], ['taqi_marketing_consent' => 'granted']);
        $service = app(MetaConversionsApiService::class);

        $this->assertTrue($service->sendPurchase($order, $request));
        $this->assertTrue($service->sendPurchase($order->fresh(), $request));
        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($order) {
            $payload = $request->data();
            $event = $payload['data'][0];

            return str_contains($request->url(), '/v26.0/3577038609126077/events')
                && ! str_contains($request->url(), 'access_token')
                && $payload['access_token'] === 'test-token-secret'
                && $event['event_name'] === 'Purchase'
                && $event['event_id'] === MetaConversionsApiService::purchaseEventId($order)
                && $event['action_source'] === 'website'
                && $event['user_data']['em'] === [hash('sha256', 'customer@example.com')]
                && $event['user_data']['ph'] === [hash('sha256', '8801712345678')]
                && ! array_key_exists('client_ip_address', $event['user_data'])
                && ! array_key_exists('client_user_agent', $event['user_data'])
                && $event['custom_data']['value'] === 1060.0;
        });

        $this->assertNotNull($order->fresh()->meta_purchase_capi_sent_at);
    }

    public function test_server_purchase_does_not_send_without_marketing_consent(): void
    {
        $this->enableCapi();
        Http::fake();

        $order = $this->orderWithItem();
        $request = Request::create('/');

        $this->assertFalse(app(MetaConversionsApiService::class)->sendPurchase($order, $request));
        Http::assertNothingSent();
        $this->assertNull($order->fresh()->meta_purchase_capi_sent_at);
    }

    public function test_failed_server_purchase_is_not_marked_as_sent(): void
    {
        $this->enableCapi();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'rejected']], 400)]);

        $order = $this->orderWithItem();
        $request = Request::create('/', 'GET', [], ['taqi_marketing_consent' => 'granted']);

        $this->assertFalse(app(MetaConversionsApiService::class)->sendPurchase($order, $request));
        $this->assertNull($order->fresh()->meta_purchase_capi_sent_at);
    }

    public function test_admin_token_is_encrypted_and_never_returned_in_settings_props(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['role' => 'admin']);
        $token = 'live-token-do-not-expose';

        $this->actingAs($admin)->put('/admin/settings/tracking', [
            'tracking_meta_pixel_id' => '3577038609126077',
            'tracking_meta_pixel_consent_required' => '1',
            'tracking_meta_capi_enabled' => '1',
            'tracking_meta_capi_access_token' => $token,
            'tracking_meta_capi_clear_token' => '0',
            'tracking_gtm_id' => '',
            'tracking_ga4_id' => '',
            'tracking_ga4_mode' => 'direct',
            'tracking_hotjar_id' => '',
        ])->assertRedirect();

        $stored = Setting::get('tracking_meta_capi_access_token');
        $this->assertNotSame($token, $stored);
        $this->assertSame($token, Crypt::decryptString($stored));

        $response = $this->actingAs($admin)->get('/admin/settings')->assertOk();
        $this->assertStringNotContainsString($token, $response->getContent());
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Settings')
            ->where('settings.tracking_meta_capi_token_saved', true)
            ->missing('settings.tracking_meta_capi_access_token'));
    }

    private function enableCapi(): void
    {
        Setting::put('tracking_meta_pixel_id', '3577038609126077');
        Setting::put('tracking_meta_pixel_consent_required', '1');
        Setting::put('tracking_meta_capi_enabled', '1');
        Setting::put('tracking_meta_capi_access_token', Crypt::encryptString('test-token-secret'));
    }

    private function orderWithItem(): Order
    {
        $order = Order::factory()->create([
            'customer_email' => ' Customer@Example.com ',
            'customer_phone' => '+880 1712-345678',
            'subtotal' => 1000,
            'total' => 1060,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => null,
            'product_name' => 'Example product',
            'unit_price' => 1000,
            'quantity' => 1,
            'line_total' => 1000,
        ]);

        return $order->load('items');
    }
}
