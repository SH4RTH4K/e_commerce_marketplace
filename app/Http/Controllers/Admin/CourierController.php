<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CourierManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CourierController extends Controller
{
    public function __construct(protected CourierManagerService $courierManager) {}

    /**
     * POST /admin/orders/{order}/send-to-courier
     * Push a single order to the selected courier.
     */
    public function sendToCourier(Request $request, Order $order): JsonResponse
    {
        // BUG-5: Block re-shipping already-shipped or delivered orders
        if (in_array($order->status, ['shipped', 'delivered'], true)) {
            return response()->json([
                'success' => false,
                'message' => "Order {$order->order_number} is already {$order->status} and cannot be re-shipped.",
            ], 422);
        }

        $validated = $request->validate([
            'courier_provider' => ['required', Rule::in(CourierManagerService::providers())],
            'cod_amount'       => ['required', 'numeric', 'min:0'],
            'item_weight'      => ['nullable', 'numeric', 'min:0'],
            'note'             => ['nullable', 'string', 'max:300'],
        ]);

        $order->loadMissing('items');

        $courier = $this->courierManager->resolve($validated['courier_provider']);

        $orderData = [
            'invoice'           => $order->order_number,
            'recipient_name'    => $order->customer_name,
            'recipient_phone'   => $order->customer_phone,
            'recipient_address' => trim("{$order->shipping_address}, {$order->city}"),
            'cod_amount'        => $validated['cod_amount'],
            'item_weight'       => $validated['item_weight'] ?? 0.5,
            'item_quantity'     => $order->items->sum('quantity'),
            'item_description'  => $order->items->map(fn($i) => "{$i->product_name} x{$i->quantity}")->join(', '),
            'note'              => $validated['note'] ?? $order->note ?? null,
        ];

        // BUG-1: Catch courier API errors and return structured JSON instead of HTML 500
        try {
            $result = $courier->createOrder($orderData);
        } catch (\RuntimeException $e) {
            Log::warning('[Courier] createOrder failed', [
                'provider' => $validated['courier_provider'],
                'order'    => $order->order_number,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('[Courier] Unexpected error in createOrder', [
                'provider' => $validated['courier_provider'],
                'order'    => $order->order_number,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Courier service is currently unavailable. Please try again shortly.',
            ], 503);
        }

        // Persist courier info on the order
        $order->update([
            'courier_provider'       => $validated['courier_provider'],
            'courier_tracking_code'  => $result['tracking_code'],
            'courier_consignment_id' => $result['consignment_id'],
            'courier_status'         => $result['courier_status'],
            'status'                 => 'shipped',  // auto-advance order status
        ]);

        return response()->json([
            'success'        => true,
            'message'        => 'Order shipped successfully via ' . ucfirst($validated['courier_provider']) . '!',
            'tracking_code'  => $result['tracking_code'],
            'courier_status' => $result['courier_status'],
        ]);
    }

    /**
     * POST /webhooks/courier-status
     * Receive delivery status updates from couriers (CSRF-exempt).
     */
    public function webhook(Request $request): JsonResponse
    {
        // Verify the shared webhook secret to prevent status spoofing. A missing
        // secret is tolerated only outside production so local/test webhooks remain usable.
        $secret = trim((string) config('services.courier_webhook_secret'));
        $providedToken = (string) $request->header('X-Webhook-Token');
        $secretMissingInProduction = config('app.env') === 'production' && $secret === '';
        $tokenInvalid = $secret !== '' && ! hash_equals($secret, $providedToken);

        if ($secretMissingInProduction || $tokenInvalid) {
            Log::warning('[Courier Webhook] Unauthorized request', [
                'ip'    => $request->ip(),
                'token' => $request->header('X-Webhook-Token') ? '(present but wrong)' : '(missing)',
            ]);

            return response()->json(['status' => 'unauthorized'], 401);
        }

        $data = $request->all();

        // Each courier uses different field names for the tracking identifier
        $trackingCode = $data['tracking_code']
            ?? $data['consignment_id']
            ?? $data['tracking_id']
            ?? null;

        $newStatus = $data['status']
            ?? $data['delivery_status']
            ?? null;

        if (! $trackingCode || ! $newStatus) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $order = Order::where('courier_tracking_code', $trackingCode)
            ->orWhere('courier_consignment_id', $trackingCode)
            ->first();

        if (! $order) {
            return response()->json(['status' => 'not_found'], 200);
        }

        // Persist the raw courier status string as-is
        $order->update(['courier_status' => $newStatus]);

        // BUG-7: Normalize to lowercase so we match regardless of courier casing conventions
        // (Steadfast: "Delivered", "Cancelled"; Pathao: "Delivered"; RedX: "delivered")
        $normalized = strtolower(trim((string) $newStatus));

        $statusMap = [
            'delivered'             => 'delivered',
            'cancelled'             => 'cancelled',
            'cancel'                => 'cancelled',
            'return'                => 'cancelled',
            'returned'              => 'cancelled',
            'return to courier hub' => 'cancelled',
            'return to merchant'    => 'cancelled',
        ];

        if (isset($statusMap[$normalized])) {
            $newOrderStatus = $statusMap[$normalized];
            if ($newOrderStatus === 'cancelled' && $order->status !== 'cancelled') {
                $order->restoreStock();
                $order->releaseCoupon();
            }
            $order->update(['status' => $newOrderStatus]);
        }

        Log::info('[Courier Webhook] Status updated', [
            'order'          => $order->order_number,
            'courier_status' => $newStatus,
            'order_status'   => $statusMap[$normalized] ?? '(no change)',
        ]);

        return response()->json(['status' => 'updated']);
    }
}
