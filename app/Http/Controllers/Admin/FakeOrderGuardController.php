<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockedDevice;
use App\Models\BlockedIp;
use App\Models\BlockedPhone;
use App\Models\BdCourierCheck;
use App\Models\Order;
use App\Models\Setting;
use App\Services\BdCourierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class FakeOrderGuardController extends Controller
{
    public function index(string $section = 'overview')
    {
        $settings = Setting::pluck('value', 'key');
        $apiConnection = BdCourierService::connectionStatus((string) ($settings['fog_bdcourier_api_key'] ?? ''));
        $historyReady = Schema::hasTable('bd_courier_checks');
        $databaseStatus = [
            'ready' => $historyReady,
            'table' => 'bd_courier_checks',
            'message' => $historyReady
                ? 'Courier history storage is installed and ready.'
                : 'Courier history migration is pending. Run php artisan migrate before enabling BD Courier.',
        ];
        $recentCourierChecks = $historyReady
            ? BdCourierCheck::query()->latest('last_checked_at')->limit(10)->get()->map(fn (BdCourierCheck $check) => $this->historyPayload($check))->values()
            : collect();

        $stats = [
            'blocked_ips'     => BlockedIp::count(),
            'blocked_devices' => BlockedDevice::count(),
            'blocked_phones'  => BlockedPhone::count(),
            'total_orders'    => Order::count(),
            'pending_orders'  => Order::where('status', 'pending')->count(),
        ];

        $recentBlockedOrders = Order::whereIn('ip_address', BlockedIp::pluck('ip_address'))
            ->orWhereIn('device_hash', BlockedDevice::pluck('device_hash'))
            ->orWhereIn('customer_phone', BlockedPhone::pluck('phone'))
            ->latest()
            ->limit(5)
            ->get(['id', 'order_number', 'customer_name', 'customer_phone', 'ip_address', 'status', 'created_at']);

        return Inertia::render('Admin/FakeOrderGuard/Index', compact(
            'section',
            'settings',
            'stats',
            'recentBlockedOrders',
            'apiConnection',
            'databaseStatus',
            'recentCourierChecks'
        ));
    }

    /**
     * Manual BD Courier phone check — called from the admin UI.
     */
    public function checkPhone(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'customer_name' => ['required', 'string', 'max:120'],
        ]);

        $phone = BdCourierCheck::normalizePhone($request->input('phone'));
        if (! preg_match('/^01[3-9]\d{8}$/', $phone)) {
            return response()->json(['error' => 'Enter a valid Bangladesh phone number (01XXXXXXXXX).'], 422);
        }

        if ((string) setting('fog_enabled', '1') !== '1' || (string) setting('fog_bdcourier_enabled', '0') !== '1') {
            return response()->json(['error' => 'Enable and save Fake Order Guard and BD Courier before checking a number.'], 422);
        }

        if (! Schema::hasTable('bd_courier_checks')) {
            return response()->json(['error' => 'Courier history migration is pending. Run php artisan migrate first.'], 503);
        }

        $history = BdCourierCheck::query()->where('phone', $phone)->first();
        if (! $history || ! is_array($history->response)) {
            return response()->json([
                'error' => 'No saved result exists for this phone. BD Courier checks run only when a customer places an order.',
            ], 404);
        }

        $result = $history->response;
        $result['_history'] = $this->historyPayload($history);

        return response()->json($result);
    }

    /** Verify the saved key using BD Courier's connection endpoint. */
    public function testConnection()
    {
        $apiKey = (string) setting('fog_bdcourier_api_key', '');
        if (trim($apiKey) === '') {
            return response()->json([
                'connected' => false,
                'status' => 'not_configured',
                'message' => 'Save a BD Courier API key before testing.',
                'checked_at' => null,
            ], 422);
        }

        $check = (new BdCourierService($apiKey))->testConnection();

        return response()->json(
            $this->publicConnectionStatus($check),
            $check['connected'] ? 200 : ($check['status'] === 'network_error' ? 503 : 422)
        );
    }

    /** Return live BD Courier subscription and API-usage information. */
    public function planInfo()
    {
        $result = (new BdCourierService((string) setting('fog_bdcourier_api_key', '')))->getPlanInfo();

        $httpStatus = $result['success'] ? 200 : match ($result['status']) {
            'unauthorized' => 401,
            'rate_limited' => 429,
            'network_error', 'provider_unavailable' => 503,
            default => 422,
        };

        return response()->json($result, $httpStatus);
    }

    private function historyPayload(BdCourierCheck $check): array
    {
        return [
            'id' => $check->id,
            'customer_name' => $check->customer_name,
            'phone' => $check->phone,
            'source' => $check->source,
            'total_parcels' => $check->total_parcels,
            'successful_parcels' => $check->successful_parcels,
            'cancelled_parcels' => $check->cancelled_parcels,
            'success_ratio' => (float) $check->success_ratio,
            // Re-map from the stored figures so legacy/provider labels cannot
            // disagree with the success rate shown in the same history row.
            'risk_level' => BdCourierService::riskLevel(
                (float) $check->success_ratio,
                (int) $check->total_parcels
            ),
            'check_count' => $check->check_count,
            'last_checked_at' => $check->last_checked_at?->toIso8601String(),
            'couriers' => $this->courierBreakdown($check->response),
        ];
    }

    private function courierBreakdown(?array $response): array
    {
        $data = $response['data'] ?? [];
        if (! is_array($data)) {
            return [];
        }

        return collect($data)
            ->reject(fn ($courier, $key) => $key === 'summary' || ! is_array($courier) || ! array_key_exists('total_parcel', $courier))
            ->map(fn (array $courier, $key) => [
                'key' => (string) $key,
                'name' => trim((string) ($courier['name'] ?? '')) ?: ucwords(str_replace(['-', '_'], ' ', (string) $key)),
                'logo' => is_string($courier['logo'] ?? null) ? $courier['logo'] : null,
                'total_parcel' => (int) ($courier['total_parcel'] ?? 0),
                'success_parcel' => (int) ($courier['success_parcel'] ?? 0),
                'cancelled_parcel' => array_key_exists('cancelled_parcel', $courier)
                    ? (int) $courier['cancelled_parcel']
                    : max(0, (int) ($courier['total_parcel'] ?? 0) - (int) ($courier['success_parcel'] ?? 0)),
                'success_ratio' => isset($courier['success_ratio']) && is_numeric($courier['success_ratio'])
                    ? round((float) $courier['success_ratio'], 2)
                    : ((int) ($courier['total_parcel'] ?? 0) > 0
                        ? round(((int) ($courier['success_parcel'] ?? 0) / (int) $courier['total_parcel']) * 100, 2)
                        : 0),
            ])
            ->values()
            ->all();
    }

    private function publicConnectionStatus(array $check): array
    {
        return [
            'connected' => (bool) $check['connected'],
            'status' => (string) $check['status'],
            'message' => (string) $check['message'],
            'checked_at' => $check['checked_at'] ?? null,
            'user_id' => $check['user_id'] ?? null,
            'server_time' => $check['server_time'] ?? null,
        ];
    }
}
