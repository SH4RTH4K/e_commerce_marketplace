<?php

namespace App\Services\Courier;

use App\Contracts\CourierInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SteadfastService implements CourierInterface
{
    protected string $baseUrl = 'https://portal.packzy.com/api/v1';
    protected string $apiKey;
    protected string $secretKey;

    public function __construct()
    {
        $this->apiKey    = setting('steadfast_api_key') ?: config('services.steadfast.api_key', '');
        $this->secretKey = setting('steadfast_secret_key') ?: config('services.steadfast.secret_key', '');
    }

    protected function headers(): array
    {
        return [
            'Api-Key'      => $this->apiKey,
            'Secret-Key'   => $this->secretKey,
            'Content-Type' => 'application/json',
        ];
    }

    public function createOrder(array $orderData): array
    {
        $payload = [
            'invoice'          => $orderData['invoice'],          // unique order number
            'recipient_name'   => $orderData['recipient_name'],
            'recipient_phone'  => $orderData['recipient_phone'],
            'recipient_address'=> $orderData['recipient_address'],
            'cod_amount'       => $orderData['cod_amount'],
            'note'             => $orderData['note'] ?? null,
            'item_description' => $orderData['item_description'] ?? null,
        ];

        $response = Http::withHeaders($this->headers())
            ->timeout(15)
            ->connectTimeout(5)
            ->post("{$this->baseUrl}/create_order", $payload);

        $body = $response->json();

        Log::info('[Steadfast] createOrder response', ['body' => $body]);

        if (! $response->successful() || ($body['status'] ?? 0) !== 200) {
            throw new \RuntimeException('Steadfast order creation failed: ' . ($body['message'] ?? 'Unknown error'));
        }

        $consignment = $body['consignment'];
        $consignmentId = (string) ($consignment['consignment_id'] ?? '');
        $trackingCode  = $consignmentId !== '' ? $consignmentId : (string) ($consignment['tracking_code'] ?? '');

        return [
            'tracking_code'    => $trackingCode,
            'consignment_id'   => $consignmentId,
            'courier_status'   => $consignment['status'] ?? 'in_review',
            'raw_response'     => $body,
        ];
    }

    public function checkStatus(string $trackingCode): array
    {
        $endpoint = is_numeric($trackingCode)
            ? "{$this->baseUrl}/status_by_cid/{$trackingCode}"
            : "{$this->baseUrl}/status_by_trackingcode/{$trackingCode}";

        $response = Http::withHeaders($this->headers())
            ->timeout(15)
            ->connectTimeout(5)
            ->get($endpoint);

        $body = $response->json();

        return [
            'courier_status' => $body['delivery_status'] ?? 'unknown',
            'raw_response'   => $body,
        ];
    }

    public function getLocations(): array
    {
        // Steadfast does not require city/zone/area selection
        return [];
    }
}
