<?php

namespace App\Services\Courier;

use App\Contracts\CourierInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RedxService implements CourierInterface
{
    protected string $baseUrl = 'https://openapi.redx.com.bd/v1.0.0-beta';

    protected function headers(): array
    {
        return [
            'API-ACCESS-TOKEN' => 'Bearer ' . (setting('redx_api_token') ?: config('services.redx.api_token', '')),
            'Content-Type'     => 'application/json',
        ];
    }

    public function createOrder(array $orderData): array
    {
        $payload = [
            'name'               => $orderData['recipient_name'],
            'phone'              => $orderData['recipient_phone'],
            'address'            => $orderData['recipient_address'],
            'merchant_invoice_id'=> $orderData['invoice'],
            'cash_collection_amount' => (float) $orderData['cod_amount'],
            'parcel_weight'      => (float) (($orderData['item_weight'] ?? 0.5) * 1000), // kg → grams (RedX expects grams)
            'instruction'        => $orderData['note'] ?? null,
            'value'              => (float) $orderData['cod_amount'],
        ];

        $response = Http::withHeaders($this->headers())
            ->timeout(15)
            ->connectTimeout(5)
            ->post("{$this->baseUrl}/parcel", $payload);

        $body = $response->json();

        Log::info('[RedX] createOrder response', ['body' => $body]);

        if (! $response->successful()) {
            throw new \RuntimeException('RedX order creation failed: ' . ($body['message'] ?? json_encode($body)));
        }

        return [
            'tracking_code'  => $body['tracking_id'] ?? ($body['parcel']['tracking_id'] ?? ''),
            'consignment_id' => (string) ($body['id'] ?? ($body['parcel']['id'] ?? '')),
            'courier_status' => 'created',
            'raw_response'   => $body,
        ];
    }

    public function checkStatus(string $trackingCode): array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(15)
            ->connectTimeout(5)
            ->get("{$this->baseUrl}/parcel/track/{$trackingCode}");

        $body = $response->json();

        return [
            'courier_status' => $body['status'] ?? 'unknown',
            'raw_response'   => $body,
        ];
    }

    public function getLocations(): array
    {
        // RedX uses free-text address; no location IDs needed
        return [];
    }
}
