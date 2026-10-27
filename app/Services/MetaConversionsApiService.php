<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetaConversionsApiService
{
    private const GRAPH_API_VERSION = 'v26.0';

    public static function purchaseEventId(Order $order): string
    {
        // Stable across browser/server requests and application key rotations.
        return hash('sha256', 'taqilife:purchase:'.$order->getKey());
    }

    public function sendPurchase(Order $order, Request $request): bool
    {
        if ($order->meta_purchase_capi_sent_at) {
            return true;
        }

        if ((string) setting('tracking_meta_capi_enabled', '0') !== '1'
            || ! tracking_meta_pixel_id()
            || (tracking_meta_pixel_consent_required() && $request->cookie('taqi_marketing_consent') !== 'granted')) {
            return false;
        }

        $encryptedToken = trim((string) setting('tracking_meta_capi_access_token', ''));
        if ($encryptedToken === '') {
            return false;
        }

        try {
            $accessToken = Crypt::decryptString($encryptedToken);
        } catch (Throwable) {
            Log::warning('Meta CAPI Purchase skipped: saved access token could not be decrypted.');

            return false;
        }

        $order->loadMissing('items');
        $itemsWithProductIds = $order->items->filter(fn ($item) => $item->product_id !== null);
        $contents = $itemsWithProductIds->map(fn ($item) => [
            'id' => (string) $item->product_id,
            'quantity' => (int) $item->quantity,
            'item_price' => (float) $item->unit_price,
        ])->values()->all();

        $userData = array_filter([
            'em' => $this->hashAsArray($this->normalizeEmail($order->customer_email)),
            'ph' => $this->hashAsArray($this->normalizePhone($order->customer_phone)),
            'external_id' => $order->user_id ? $this->hashAsArray((string) $order->user_id) : null,
            'country' => $this->hashAsArray('bd'),
        ]);

        $event = [
            'event_name' => 'Purchase',
            'event_time' => ($order->created_at ?? now())->timestamp,
            'event_id' => self::purchaseEventId($order),
            'action_source' => 'website',
            'event_source_url' => route('order.confirmation', ['order' => $order->order_number]),
            'user_data' => $userData,
            'custom_data' => [
                'currency' => strtoupper((string) setting('currency_code', 'BDT')),
                'value' => (float) $order->total,
                'content_type' => 'product',
                'content_ids' => $itemsWithProductIds->pluck('product_id')->map(fn ($id) => (string) $id)->values()->all(),
                'contents' => $contents,
                'num_items' => (int) $order->items->sum('quantity'),
            ],
        ];

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(1)
                ->timeout(3)
                ->post('https://graph.facebook.com/'.self::GRAPH_API_VERSION.'/'.tracking_meta_pixel_id().'/events', [
                    'access_token' => $accessToken,
                    'data' => [$event],
                ]);
        } catch (Throwable $exception) {
            // Do not log request data or exception messages: the access token is secret.
            Log::warning('Meta CAPI Purchase delivery failed.', [
                'order_id' => $order->getKey(),
                'exception' => get_class($exception),
            ]);

            return false;
        }

        if (! $response->successful() || (int) $response->json('events_received', 0) !== 1) {
            Log::warning('Meta CAPI Purchase was not accepted.', [
                'order_id' => $order->getKey(),
                'http_status' => $response->status(),
            ]);

            return false;
        }

        $order->forceFill(['meta_purchase_capi_sent_at' => now()])->save();

        return true;
    }

    private function normalizeEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email !== '' ? $email : null;
    }

    private function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '0')) {
            $digits = '88'.$digits;
        } elseif (str_starts_with($digits, '1') && strlen($digits) === 10) {
            $digits = '880'.$digits;
        }

        return $digits;
    }

    private function hashValue(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return hash('sha256', trim($value));
    }

    private function hashAsArray(?string $value): ?array
    {
        $hash = $this->hashValue($value);

        return $hash !== null ? [$hash] : null;
    }
}
