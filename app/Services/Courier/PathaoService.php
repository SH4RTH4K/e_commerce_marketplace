<?php

namespace App\Services\Courier;

use App\Contracts\CourierInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PathaoService implements CourierInterface
{
    protected string $baseUrl = 'https://api-hermes.pathao.com';

    protected function credentials(): array
    {
        return [
            'client_id'     => setting('pathao_client_id')     ?: config('services.pathao.client_id', ''),
            'client_secret' => setting('pathao_client_secret') ?: config('services.pathao.client_secret', ''),
            'username'      => setting('pathao_username')      ?: config('services.pathao.username', ''),
            'password'      => setting('pathao_password')      ?: config('services.pathao.password', ''),
        ];
    }

    /**
     * Get (or refresh) the OAuth access token.
     * Cached for 50 minutes since tokens expire after 60 minutes.
     */
    protected function accessToken(): string
    {
        return Cache::remember('pathao_access_token', 50 * 60, function () {
            $creds = $this->credentials();

            $response = Http::timeout(15)
                ->connectTimeout(5)
                ->post("{$this->baseUrl}/aladdin/api/v1/issue-token", [
                'client_id'     => $creds['client_id'],
                'client_secret' => $creds['client_secret'],
                'username'      => $creds['username'],
                'password'      => $creds['password'],
                'grant_type'    => 'password',
            ]);

            $body = $response->json();

            if (! $response->successful() || empty($body['access_token'])) {
                throw new \RuntimeException('Pathao token issue failed: ' . ($body['message'] ?? 'Unknown error'));
            }

            return $body['access_token'];
        });
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->accessToken(),
            'Content-Type'  => 'application/json',
        ];
    }

    public function createOrder(array $orderData): array
    {
        $payload = [
            'store_id'            => setting('pathao_store_id') ?: config('services.pathao.store_id'),
            'merchant_order_id'   => $orderData['invoice'],
            'recipient_name'      => $orderData['recipient_name'],
            'recipient_phone'     => $orderData['recipient_phone'],
            'recipient_address'   => $orderData['recipient_address'],
            'delivery_type'       => 48,  // 48h standard delivery
            'item_type'           => 2,   // parcel
            'item_quantity'       => $orderData['item_quantity'] ?? 1,
            'item_weight'         => $orderData['item_weight'] ?? 0.5,
            'item_description'    => $orderData['item_description'] ?? null,
            'amount_to_collect'   => $orderData['cod_amount'],
            'special_instruction' => $orderData['note'] ?? null,
        ];

        $response = Http::withHeaders($this->headers())
            ->timeout(15)
            ->connectTimeout(5)
            ->post("{$this->baseUrl}/aladdin/api/v1/orders", $payload);

        $body = $response->json();

        Log::info('[Pathao] createOrder response', ['body' => $body]);

        if (! $response->successful()) {
            // Token may have expired — clear cache and throw
            Cache::forget('pathao_access_token');
            throw new \RuntimeException('Pathao order creation failed: ' . ($body['message'] ?? 'Unknown error'));
        }

        $data = $body['data'] ?? $body;

        return [
            'tracking_code'  => $data['consignment_id'] ?? '',
            'consignment_id' => (string) ($data['consignment_id'] ?? ''),
            'courier_status' => $data['order_status'] ?? 'Pending',
            'raw_response'   => $body,
        ];
    }

    public function checkStatus(string $trackingCode): array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(15)
            ->connectTimeout(5)
            ->get("{$this->baseUrl}/aladdin/api/v1/orders/{$trackingCode}");

        $body = $response->json();

        return [
            'courier_status' => $body['data']['order_status'] ?? 'unknown',
            'raw_response'   => $body,
        ];
    }

    public function getLocations(): array
    {
        // Pathao now handles address routing automatically
        return [];
    }
}
