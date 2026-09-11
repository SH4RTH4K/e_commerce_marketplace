<?php

namespace Tests\Unit;

use App\Services\BdCourierService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BdCourierServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_a_successful_response_verifies_the_connection_and_normalizes_summary_data(): void
    {
        Http::fake([
            'api.bdcourier.com/courier-check*' => Http::response([
                'status' => 'success',
                'success_ratio' => 75,
                'risk_level' => 'medium',
                'data' => [
                    'pathao' => [
                        'name' => 'Pathao',
                        'total_parcel' => 4,
                        'success_parcel' => 3,
                        'cancelled_parcel' => 1,
                        'success_ratio' => 75,
                    ],
                ],
            ]),
        ]);

        $check = (new BdCourierService('valid-test-key'))->checkWithStatus('+88 01711-223344');

        $this->assertTrue($check['connected']);
        $this->assertSame('connected', $check['status']);
        $this->assertSame('01711223344', $check['result']['_phone']);
        $this->assertSame('medium', $check['result']['_risk_level']);
        $this->assertSame(4, $check['result']['data']['summary']['total_parcel']);
        $this->assertSame(75.0, $check['result']['_success_ratio']);
        $this->assertSame('connected', BdCourierService::connectionStatus('valid-test-key')['status']);

        Http::assertSent(fn ($request) =>
            $request->hasHeader('Authorization', 'Bearer valid-test-key')
            && $request->method() === 'GET'
            && $request['phone'] === '01711223344'
        );
    }

    public function test_an_invalid_key_is_reported_separately_from_a_network_failure(): void
    {
        Http::fake([
            'api.bdcourier.com/courier-check*' => Http::response(['message' => 'Unauthenticated.'], 401),
        ]);

        $check = (new BdCourierService('invalid-test-key'))->checkWithStatus('01711223344');

        $this->assertFalse($check['connected']);
        $this->assertSame('unauthorized', $check['status']);
        $this->assertStringContainsString('rejected the API key', $check['message']);
        $this->assertNull($check['result']);
    }

    public function test_connection_status_is_not_reused_for_a_different_key(): void
    {
        Http::fake([
            'api.bdcourier.com/courier-check*' => Http::response([
                'status' => 'success',
                'data' => ['summary' => ['success_ratio' => 100]],
            ]),
        ]);

        (new BdCourierService('first-key'))->checkWithStatus('01711223344');

        $status = BdCourierService::connectionStatus('second-key');

        $this->assertFalse($status['connected']);
        $this->assertSame('not_tested', $status['status']);
    }

    public function test_it_accepts_the_plugins_legacy_courier_data_response(): void
    {
        Http::fake([
            'api.bdcourier.com/courier-check*' => Http::response([
                'courierData' => [
                    'steadfast' => [
                        'total_parcel' => 10,
                        'success_parcel' => 8,
                        'cancelled_parcel' => 2,
                    ],
                ],
            ]),
        ]);

        $result = (new BdCourierService('valid-test-key'))->check('01711223344');

        $this->assertNotNull($result);
        $this->assertSame(10, $result['data']['summary']['total_parcel']);
        $this->assertSame(80.0, $result['_success_ratio']);
        $this->assertSame('safe', $result['_risk_level']);
    }

    public function test_local_risk_threshold_boundaries(): void
    {
        $this->assertSame('unknown', BdCourierService::riskLevel(100, 0));
        $this->assertSame('danger', BdCourierService::riskLevel(39.99, 1));
        $this->assertSame('high', BdCourierService::riskLevel(40, 1));
        $this->assertSame('high', BdCourierService::riskLevel(59.99, 1));
        $this->assertSame('medium', BdCourierService::riskLevel(60, 1));
        $this->assertSame('medium', BdCourierService::riskLevel(79.99, 1));
        $this->assertSame('safe', BdCourierService::riskLevel(80, 1));
    }

    public function test_connection_test_uses_the_dedicated_endpoint(): void
    {
        Http::fake([
            'api.bdcourier.com/check-connection' => Http::response([
                'status' => 'success',
                'message' => 'Connected',
            ]),
        ]);

        $check = (new BdCourierService('valid-test-key'))->testConnection();

        $this->assertTrue($check['connected']);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && $request->url() === 'https://api.bdcourier.com/check-connection');
    }

    public function test_it_normalizes_nested_courier_rows_and_string_values(): void
    {
        Http::fake([
            'api.bdcourier.com/courier-check*' => Http::response([
                'status' => 'success',
                'data' => [
                    'data' => [
                        'paper_fly' => [
                            'total_parcel' => '5',
                            'success_parcel' => '4',
                        ],
                        'steadfast' => [
                            'name' => 'SteadFast',
                            'total_parcel' => '0',
                            'success_parcel' => '0',
                        ],
                    ],
                    'reports' => [[
                        'id' => 7,
                        'details' => 'Test report',
                    ]],
                ],
            ]),
        ]);

        $result = (new BdCourierService('valid-test-key'))->check('01711223344');

        $this->assertSame('Paper Fly', $result['data']['paper_fly']['name']);
        $this->assertSame(5, $result['data']['paper_fly']['total_parcel']);
        $this->assertSame(1, $result['data']['paper_fly']['cancelled_parcel']);
        $this->assertSame(80.0, $result['data']['paper_fly']['success_ratio']);
        $this->assertSame(0, $result['data']['steadfast']['total_parcel']);
        $this->assertSame('Test report', $result['reports'][0]['details']);
        $this->assertSame(5, $result['data']['summary']['total_parcel']);
    }

    public function test_connection_test_accepts_data_connected_and_preserves_metadata(): void
    {
        Http::fake([
            'api.bdcourier.com/check-connection' => Http::response([
                'message' => 'Connected',
                'data' => [
                    'connected' => true,
                    'user_id' => 42,
                    'server_time' => '2026-09-07T12:00:00Z',
                ],
            ]),
        ]);

        $check = (new BdCourierService('valid-test-key'))->testConnection();

        $this->assertTrue($check['connected']);
        $this->assertSame(42, $check['user_id']);
        $this->assertSame('2026-09-07T12:00:00Z', $check['server_time']);
    }

    public function test_it_fetches_and_normalizes_plan_and_usage_information(): void
    {
        Http::fake([
            'api.bdcourier.com/my-plan' => Http::response([
                'status' => 'success',
                'message' => 'Plan loaded',
                'data' => [
                    'has_subscription' => false,
                    'is_free' => true,
                    'plan_name' => 'Free B',
                    'plan_type' => 'free',
                    'status' => 'active',
                    'api_calls' => '5',
                    'call_limit' => '80',
                    'paid_calls' => 0,
                    'paid_limit' => 0,
                    'remaining_free_calls' => '75',
                    'remaining_paid_calls' => 0,
                ],
            ]),
        ]);

        $plan = (new BdCourierService('valid-test-key'))->getPlanInfo();

        $this->assertTrue($plan['success']);
        $this->assertSame('Free B', $plan['data']['plan_name']);
        $this->assertSame(5, $plan['data']['api_calls']);
        $this->assertSame(80, $plan['data']['call_limit']);
        $this->assertSame(75, $plan['data']['remaining_free_calls']);
        $this->assertSame(0, $plan['data']['paid_limit']);

        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && $request->url() === 'https://api.bdcourier.com/my-plan'
            && $request->hasHeader('Authorization', 'Bearer valid-test-key'));
    }

    public function test_plan_request_reports_quota_and_authorization_errors(): void
    {
        Http::fake([
            'api.bdcourier.com/my-plan' => Http::response(['message' => 'Too many requests'], 429),
        ]);

        $plan = (new BdCourierService('valid-test-key'))->getPlanInfo();

        $this->assertFalse($plan['success']);
        $this->assertSame('rate_limited', $plan['status']);
        $this->assertNull($plan['data']);
    }
}
