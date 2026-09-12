<?php

namespace App\Services;

use Composer\CaBundle\CaBundle;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class BdCourierService
{
    private const BASE_URL = 'https://api.bdcourier.com';
    private const TIMEOUT  = 12; // seconds — fail-open on timeout

    private const CONNECTION_CACHE_KEY = 'bdcourier.connection_status';

    private string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? (string) setting('fog_bdcourier_api_key', '');
    }

    /**
     * Check a phone number against BD Courier fraud database.
     * Returns null on failure (API unreachable, key missing, etc.)
     */
    public function check(string $phone): ?array
    {
        return $this->checkWithStatus($phone)['result'];
    }

    /**
     * Check a phone number and retain enough detail for the admin connection UI.
     * Checkout callers continue to use check(), which deliberately fails open.
     *
     * @return array{connected: bool, status: string, message: string, checked_at: string, result: ?array}
     */
    public function checkWithStatus(string $phone): array
    {
        if (trim($this->apiKey) === '') {
            return $this->failure('not_configured', 'BD Courier API key is not configured.');
        }

        $phone = preg_replace('/\D+/', '', trim($phone));
        if (strlen($phone) === 13 && str_starts_with($phone, '88')) {
            $phone = substr($phone, 2);
        }

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->connectTimeout(5)
                ->withOptions(['verify' => CaBundle::getSystemCaRootBundlePath()])
                ->withToken($this->apiKey)
                ->get(self::BASE_URL . '/courier-check', ['phone' => $phone]);

            if ($response->failed()) {
                Log::warning('BD Courier API error', [
                    'status' => $response->status(),
                    'phone' => $phone,
                ]);

                return $this->httpFailure($response);
            }

            $json = $response->json();
            $courierData = $this->extractCourierData($json);
            if ($courierData === null) {
                return $this->failure(
                    'provider_error',
                    is_array($json)
                        ? $this->providerMessage($json)
                        : 'BD Courier returned an invalid response.'
                );
            }

            $sourceSummary = $courierData['summary'] ?? null;
            $courierData = $this->normalizeCourierData($courierData);
            $normalized = is_array($json) ? $json : [];
            $normalized['status'] = 'success';
            $normalized['data'] = $courierData;
            $normalized['data']['summary'] = $this->normalizeSummary(
                $sourceSummary,
                $normalized['data'],
                $normalized
            );

            $reports = $this->extractReports($json);
            if ($reports !== []) {
                $normalized['reports'] = $reports;
            }

            $normalized['_phone'] = $phone;
            $normalized['_success_ratio'] = (float) ($normalized['data']['summary']['success_ratio'] ?? $normalized['success_ratio'] ?? 0);
            $providerRiskLevel = $normalized['risk_verdict']['level'] ?? $normalized['risk_level'] ?? null;
            $normalized['_risk_level'] = self::canonicalRiskLevel($providerRiskLevel)
                ?? self::riskLevel($normalized['_success_ratio'], (int) ($normalized['data']['summary']['total_parcel'] ?? 0));
            // Keep the label free of a duplicated "Risk" suffix; the UI adds it.
            $normalized['_risk_label'] = ucfirst((string) $normalized['_risk_level']);
            $normalized['_risk_color'] = $this->riskColor($normalized['_risk_level']);

            return $this->rememberConnection([
                'connected' => true,
                'status' => 'connected',
                'message' => 'API key verified and BD Courier responded successfully.',
                'checked_at' => now()->toIso8601String(),
                'result' => $normalized,
            ]);
        } catch (ConnectionException $e) {
            Log::warning('BD Courier check exception: ' . $e->getMessage());

            return $this->failure('network_error', 'Could not securely connect to BD Courier. Check the server network and TLS certificates.');
        } catch (\Throwable $e) {
            Log::warning('BD Courier check exception: ' . $e->getMessage());

            return $this->failure('client_error', 'The BD Courier request could not be completed.');
        }
    }

    /** Check the API token without consuming a customer phone lookup. */
    public function testConnection(): array
    {
        if (trim($this->apiKey) === '') {
            return $this->failure('not_configured', 'BD Courier API key is not configured.');
        }

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->connectTimeout(5)
                ->withOptions(['verify' => CaBundle::getSystemCaRootBundlePath()])
                ->withToken($this->apiKey)
                ->get(self::BASE_URL . '/check-connection');

            if ($response->failed()) {
                return $this->httpFailure($response);
            }

            $json = $response->json();
            $connected = is_array($json) && (
                ($json['status'] ?? '') === 'success'
                || ($json['connected'] ?? false) === true
                || data_get($json, 'data.connected') === true
            );
            if (! $connected) {
                return $this->failure('provider_error', is_array($json) ? $this->providerMessage($json) : 'BD Courier returned an invalid response.');
            }

            $connectionData = is_array($json['data'] ?? null) ? $json['data'] : $json;

            return $this->rememberConnection([
                'connected' => true,
                'status' => 'connected',
                'message' => (string) ($json['message'] ?? 'API key verified successfully.'),
                'checked_at' => now()->toIso8601String(),
                'user_id' => isset($connectionData['user_id']) ? (int) $connectionData['user_id'] : null,
                'server_time' => isset($connectionData['server_time']) ? (string) $connectionData['server_time'] : null,
                'result' => null,
            ]);
        } catch (ConnectionException $e) {
            Log::warning('BD Courier connection test exception: ' . $e->getMessage());

            return $this->failure('network_error', 'Could not securely connect to BD Courier. Check the server network and TLS certificates.');
        } catch (\Throwable $e) {
            Log::warning('BD Courier connection test exception: ' . $e->getMessage());

            return $this->failure('client_error', 'The BD Courier connection test could not be completed.');
        }
    }

    /** Fetch the live subscription, billing and API-quota information. */
    public function getPlanInfo(): array
    {
        if (trim($this->apiKey) === '') {
            return $this->planFailure('not_configured', 'BD Courier API key is not configured.');
        }

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->connectTimeout(5)
                ->withOptions(['verify' => CaBundle::getSystemCaRootBundlePath()])
                ->acceptJson()
                ->withToken($this->apiKey)
                ->get(self::BASE_URL . '/my-plan');

            if ($response->failed()) {
                [$status, $message] = $this->httpFailureDetails($response);

                return $this->planFailure($status, $message);
            }

            $json = $response->json();
            if (! is_array($json)) {
                return $this->planFailure('provider_error', 'BD Courier returned an invalid plan response.');
            }

            if (isset($json['status']) && $json['status'] !== 'success') {
                return $this->planFailure('provider_error', $this->providerMessage($json));
            }

            $data = is_array($json['data'] ?? null) ? $json['data'] : $json;
            if (! $this->hasPlanStructure($data)) {
                return $this->planFailure('provider_error', $this->providerMessage($json));
            }

            return [
                'success' => true,
                'status' => 'success',
                'message' => (string) ($json['message'] ?? 'Plan information loaded successfully.'),
                'fetched_at' => now()->toIso8601String(),
                'data' => $this->normalizePlanData($data),
            ];
        } catch (ConnectionException $e) {
            Log::warning('BD Courier plan request exception: ' . $e->getMessage());

            return $this->planFailure('network_error', 'Could not securely connect to BD Courier. Check the server network and TLS certificates.');
        } catch (\Throwable $e) {
            Log::warning('BD Courier plan request exception: ' . $e->getMessage());

            return $this->planFailure('client_error', 'The BD Courier plan request could not be completed.');
        }
    }

    /** Return the latest verified connection state for the currently saved key. */
    public static function connectionStatus(string $apiKey): array
    {
        if (trim($apiKey) === '') {
            return [
                'connected' => false,
                'status' => 'not_configured',
                'message' => 'Add and save an API key, then test the connection.',
                'checked_at' => null,
            ];
        }

        $cached = Cache::get(self::CONNECTION_CACHE_KEY);
        if (! is_array($cached) || ! hash_equals($cached['key_fingerprint'] ?? '', self::keyFingerprint($apiKey))) {
            return [
                'connected' => false,
                'status' => 'not_tested',
                'message' => 'API key is configured but has not been verified yet.',
                'checked_at' => null,
            ];
        }

        unset($cached['key_fingerprint'], $cached['result']);

        return $cached;
    }

    private function httpFailure(Response $response): array
    {
        [$status, $message] = $this->httpFailureDetails($response);

        return $this->failure($status, $message);
    }

    /** @return array{0: string, 1: string} */
    private function httpFailureDetails(Response $response): array
    {
        $status = match (true) {
            in_array($response->status(), [401, 403], true) => 'unauthorized',
            $response->status() === 429 => 'rate_limited',
            $response->serverError() => 'provider_unavailable',
            default => 'provider_error',
        };

        $message = match ($status) {
            'unauthorized' => 'BD Courier rejected the API key. Generate or copy a valid key and save it.',
            'rate_limited' => 'BD Courier rate limit or account quota has been reached.',
            'provider_unavailable' => 'BD Courier is temporarily unavailable.',
            default => (string) ($response->json('message') ?? $response->json('error') ?? 'BD Courier rejected the request.'),
        };

        return [$status, $message];
    }

    private function planFailure(string $status, string $message): array
    {
        return [
            'success' => false,
            'status' => $status,
            'message' => $message,
            'fetched_at' => now()->toIso8601String(),
            'data' => null,
        ];
    }

    private function failure(string $status, string $message): array
    {
        return $this->rememberConnection([
            'connected' => false,
            'status' => $status,
            'message' => $message,
            'checked_at' => now()->toIso8601String(),
            'result' => null,
        ]);
    }

    private function rememberConnection(array $status): array
    {
        if (trim($this->apiKey) !== '') {
            Cache::put(self::CONNECTION_CACHE_KEY, [
                ...$status,
                'key_fingerprint' => self::keyFingerprint($this->apiKey),
            ], now()->addDay());
        }

        return $status;
    }

    private static function keyFingerprint(string $apiKey): string
    {
        return hash('sha256', $apiKey);
    }

    private function buildSummary(array $data, array $response): array
    {
        $couriers = array_filter(
            $data,
            fn ($item, $key) => $key !== 'summary' && is_array($item),
            ARRAY_FILTER_USE_BOTH
        );
        $total = array_sum(array_map(fn ($item) => (int) ($item['total_parcel'] ?? 0), $couriers));
        $successful = array_sum(array_map(fn ($item) => (int) ($item['success_parcel'] ?? 0), $couriers));
        $cancelled = array_sum(array_map(fn ($item) => (int) ($item['cancelled_parcel'] ?? 0), $couriers));

        return [
            'total_parcel' => $total,
            'success_parcel' => $successful,
            'cancelled_parcel' => $cancelled,
            'success_ratio' => (float) ($response['success_ratio'] ?? ($total > 0 ? ($successful / $total) * 100 : 0)),
        ];
    }

    private function extractCourierData(mixed $json): ?array
    {
        if (! is_array($json)) {
            return null;
        }

        $candidates = [
            data_get($json, 'courierData'),
            data_get($json, 'data.courierData'),
            data_get($json, 'data.couriers'),
            data_get($json, 'data.data'),
            data_get($json, 'couriers'),
            data_get($json, 'data'),
            $json,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate) && $this->hasCourierStructure($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function hasCourierStructure(array $data): bool
    {
        if (isset($data['summary']) && is_array($data['summary']) && (
            array_key_exists('total_parcel', $data['summary'])
            || array_key_exists('success_parcel', $data['summary'])
        )) {
            return true;
        }

        return collect($data)->contains(
            fn ($value, $key) => $key !== 'summary' && is_array($value) && array_key_exists('total_parcel', $value)
        );
    }

    private function normalizeCourierData(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $courier) {
            if ($key === 'summary' || ! is_array($courier)) {
                continue;
            }

            if (! array_key_exists('total_parcel', $courier)
                && ! array_key_exists('success_parcel', $courier)
                && ! array_key_exists('cancelled_parcel', $courier)) {
                continue;
            }

            $total = (int) ($courier['total_parcel'] ?? 0);
            $successful = (int) ($courier['success_parcel'] ?? 0);
            $cancelled = array_key_exists('cancelled_parcel', $courier)
                ? (int) $courier['cancelled_parcel']
                : max(0, $total - $successful);
            $ratio = isset($courier['success_ratio']) && is_numeric($courier['success_ratio'])
                ? (float) $courier['success_ratio']
                : ($total > 0 ? ($successful / $total) * 100 : 0.0);
            $fallbackName = ucwords(str_replace(['-', '_'], ' ', (string) $key));

            $normalized[(string) $key] = [
                ...$courier,
                'name' => trim((string) ($courier['name'] ?? '')) ?: $fallbackName,
                'logo' => is_string($courier['logo'] ?? null) ? $courier['logo'] : null,
                'total_parcel' => $total,
                'success_parcel' => $successful,
                'cancelled_parcel' => $cancelled,
                'success_ratio' => round($ratio, 2),
            ];
        }

        return $normalized;
    }

    private function normalizeSummary(mixed $summary, array $data, array $response): array
    {
        $built = $this->buildSummary($data, $response);
        if (! is_array($summary)) {
            return $built;
        }

        $total = (int) ($summary['total_parcel'] ?? $built['total_parcel']);
        $successful = (int) ($summary['success_parcel'] ?? $built['success_parcel']);
        $cancelled = array_key_exists('cancelled_parcel', $summary)
            ? (int) $summary['cancelled_parcel']
            : max(0, $total - $successful);
        $ratio = isset($summary['success_ratio']) && is_numeric($summary['success_ratio'])
            ? (float) $summary['success_ratio']
            : ($total > 0 ? ($successful / $total) * 100 : 0.0);

        return [
            ...$summary,
            'total_parcel' => $total,
            'success_parcel' => $successful,
            'cancelled_parcel' => $cancelled,
            'success_ratio' => round($ratio, 2),
        ];
    }

    private function extractReports(array $json): array
    {
        $candidates = [
            data_get($json, 'reports'),
            data_get($json, 'data.reports'),
            data_get($json, 'data.data.reports'),
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                return array_values(array_filter($candidate, 'is_array'));
            }
        }

        return [];
    }

    private function hasPlanStructure(array $data): bool
    {
        return collect([
            'plan_name', 'plan_type', 'status', 'has_subscription', 'is_free',
            'call_limit', 'paid_limit', 'api_calls', 'paid_calls',
            'remaining_free_calls', 'remaining_paid_calls',
        ])->contains(fn (string $key) => array_key_exists($key, $data));
    }

    private function normalizePlanData(array $data): array
    {
        $planType = strtolower(trim((string) ($data['plan_type'] ?? 'free'))) ?: 'free';
        $callLimit = max(0, (int) ($data['call_limit'] ?? 0));
        $paidLimit = max(0, (int) ($data['paid_limit'] ?? 0));
        $apiCalls = max(0, (int) ($data['api_calls'] ?? 0));
        $paidCalls = max(0, (int) ($data['paid_calls'] ?? 0));
        $nextDueDate = $this->nullableString($data['next_due_date'] ?? null);
        $expiresAt = $this->nullableString($data['expires_at'] ?? null);
        $daysRemaining = isset($data['days_remaining']) && is_numeric($data['days_remaining'])
            ? max(0, (int) $data['days_remaining'])
            : $this->daysUntil($nextDueDate ?? $expiresAt);

        return [
            'has_subscription' => (bool) ($data['has_subscription'] ?? ($planType === 'paid')),
            'is_free' => (bool) ($data['is_free'] ?? ($planType !== 'paid')),
            'plan_name' => trim((string) ($data['plan_name'] ?? 'Free')) ?: 'Free',
            'plan_type' => $planType,
            'price' => isset($data['price']) && $data['price'] !== '' ? $data['price'] : null,
            'frequency' => $this->nullableString($data['frequency'] ?? null),
            'status' => trim((string) ($data['status'] ?? 'inactive')) ?: 'inactive',
            'next_due_date' => $nextDueDate,
            'days_remaining' => $daysRemaining,
            'expires_at' => $expiresAt,
            'api_calls' => $apiCalls,
            'paid_calls' => $paidCalls,
            'call_limit' => $callLimit,
            'paid_limit' => $paidLimit,
            'remaining_free_calls' => max(0, (int) ($data['remaining_free_calls'] ?? ($callLimit - $apiCalls))),
            'remaining_paid_calls' => max(0, (int) ($data['remaining_paid_calls'] ?? ($paidLimit - $paidCalls))),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function daysUntil(?string $date): ?int
    {
        if ($date === null) {
            return null;
        }

        try {
            return max(0, (int) floor(Carbon::today()->diffInDays(Carbon::parse($date)->startOfDay(), false)));
        } catch (\Throwable) {
            return null;
        }
    }

    private function providerMessage(array $json): string
    {
        $error = $json['error'] ?? null;
        if (is_array($error)) {
            $error = $error['message'] ?? $error['error'] ?? null;
        }

        $message = $json['message'] ?? $error ?? (is_string($json['data'] ?? null) ? $json['data'] : null);

        return is_string($message) && trim($message) !== ''
            ? trim($message)
            : 'BD Courier rejected the request.';
    }

    /**
     * Map delivery success to the application's canonical risk level.
     */
    public static function canonicalRiskLevel($level): ?string
    {
        $normalized = strtolower(trim((string) $level));
        $normalized = preg_replace('/[\s-]+/', '_', $normalized) ?? '';

        if (str_contains($normalized, 'danger')) return 'danger';
        if (str_contains($normalized, 'high')) return 'high';
        if (str_contains($normalized, 'medium') || str_contains($normalized, 'moderate')) return 'medium';
        if (str_contains($normalized, 'low')) return 'low';
        if (str_contains($normalized, 'safe') || str_contains($normalized, 'good')) return 'safe';

        return null;
    }

    public static function riskLevel(float $ratio, int $totalParcels): string
    {
        if ($totalParcels <= 0) {
            return 'unknown';
        }

        if ($ratio >= 80) {
            return 'safe';
        }

        if ($ratio >= 60) {
            return 'medium';
        }

        if ($ratio >= 40) {
            return 'high';
        }

        return 'danger';
    }

    /**
     * Map risk level to a Tailwind color name for the UI.
     */
    public static function riskColor(string $level): string
    {
        return match ($level) {
            'safe'   => 'green',
            'low'    => 'blue',
            'medium' => 'amber',
            'high'   => 'red',
            'danger' => 'rose',
            default  => 'gray',
        };
    }

    /**
     * Determine if this risk level should be blocked based on admin settings.
     */
    public function shouldBlock(?array $result): bool
    {
        if (! $result) return false;

        $level = $result['_risk_level'] ?? 'unknown';

        // Check risk level blocklist
        $blockLevels = array_filter(
            array_map('trim', explode(',', (string) setting('fog_bdcourier_block_risk_levels', 'danger,high')))
        );

        if (in_array($level, $blockLevels, true)) {
            return true;
        }

        // Check minimum success rate
        $minRate = (int) setting('fog_bdcourier_min_success_rate', 0);
        if ($minRate > 0) {
            $ratio = $result['_success_ratio'] ?? 100;
            // If the customer has orders and ratio is below threshold
            $totalParcels = (int) ($result['data']['summary']['total_parcel'] ?? 0);
            if ($totalParcels > 0 && $ratio < $minRate) {
                return true;
            }
        }

        return false;
    }

    /**
     * Human-readable block reason for the customer.
     */
    public function blockMessage(?array $result): string
    {
        if (! $result) return 'অর্ডার প্রক্রিয়াকরণে সমস্যা হয়েছে।';

        $level = $result['_risk_level'] ?? '';
        $ratio = number_format($result['_success_ratio'] ?? 0, 1);

        return match ($level) {
            'danger' => "আপনার ফোন নম্বরে অস্বাভাবিক ডেলিভারি রেকর্ড পাওয়া গেছে ({$ratio}% সফলতার হার)। অর্ডার প্রক্রিয়া করা সম্ভব নয়।",
            'high'   => "আপনার ফোন নম্বরে উচ্চ ঝুঁকি চিহ্নিত হয়েছে ({$ratio}% সফলতার হার)। অর্ডার নিশ্চিত করা যাচ্ছে না।",
            default  => "আপনার ফোন নম্বরটি যাচাই করা সম্ভব হয়নি। অনুগ্রহ করে আবার চেষ্টা করুন।",
        };
    }
}
