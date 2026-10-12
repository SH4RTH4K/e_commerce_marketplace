<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Setting;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Throwable;

class AdminInvoiceController extends Controller
{
    public function show(Order $order)
    {
        $order->load(['items.product.images']);
        $store = $this->getStoreDetails();

        return view('admin.orders.invoice', [
            'orders' => collect([$order]),
            'store'  => $store,
            'isBulk' => false,
        ]);
    }

    /**
     * Render a real PDF for download or native file sharing on supported devices.
     */
    public function pdf(Order $order, Request $request)
    {
        $order->load(['items.product.images']);
        $store = $this->getStoreDetails();
        $printMode = $request->query('mode') === 'mono' ? 'mono' : 'color';

        $options = new Options();
        $options->setIsRemoteEnabled(true);
        $options->setDefaultFont('DejaVu Sans');

        try {
            $html = view('admin.orders.invoice', [
                'orders'    => collect([$order]),
                'store'     => $store,
                'isBulk'    => false,
                'isPdf'     => true,
                'printMode' => $printMode,
            ])->render();

            $output = $this->renderPdf($html, $options);
        } catch (Throwable $exception) {
            report($exception);

            $output = $this->renderPdf($this->fallbackInvoicePdfHtml($order, $store), $options);
        }

        return response($output, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Invoice_' . $order->order_number . '_' . $printMode . '.pdf"',
        ]);
    }

    public function bulk(Request $request)
    {
        $ids = $request->query('ids', '');
        if (is_string($ids)) {
            $idArray = array_filter(array_map('intval', explode(',', $ids)));
        } else {
            $idArray = (array) $ids;
        }

        if (empty($idArray)) {
            return redirect()->route('admin.orders.index')->with('error', 'No orders selected for printing.');
        }

        // Cap at 200 IDs to prevent admin-triggered DB/memory exhaustion
        if (count($idArray) > 200) {
            return redirect()->route('admin.orders.index')->with('error', 'You can print a maximum of 200 invoices at once. Please select fewer orders.');
        }

        $orders = Order::with(['items.product.images'])
            ->whereIn('id', $idArray)
            ->latest()
            ->get();

        if ($orders->isEmpty()) {
            return redirect()->route('admin.orders.index')->with('error', 'Selected orders not found.');
        }

        $store = $this->getStoreDetails();

        return view('admin.orders.invoice', [
            'orders' => $orders,
            'store'  => $store,
            'isBulk' => true,
        ]);
    }

    public function customerInvoice(string $orderNumber, Request $request)
    {
        $order = Order::with(['items.product.images'])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $token = (string) $request->query('token', '');
        $user = $request->user();

        $authorized = false;

        // 1. Staff / Admin is authorized
        if ($user && $user->isAdmin()) {
            $authorized = true;
        }

        // 2. Logged-in customer who placed the order is authorized
        if ($user && $order->user_id && (int) $order->user_id === (int) $user->id) {
            $authorized = true;
        }

        // 3. Valid confirmation token matches the order
        if ($token !== '' && hash_equals((string) $order->confirmation_token, $token)) {
            $authorized = true;
        }

        if (! $authorized) {
            abort(403, 'A valid order token or account login is required to view this invoice.');
        }

        $store = $this->getStoreDetails();

        return view('admin.orders.invoice', [
            'orders' => collect([$order]),
            'store'  => $store,
            'isBulk' => false,
            'isCustomer' => true,
        ]);
    }

    private function getStoreDetails(): array
    {
        return [
            'name'       => setting('site_name', config('app.name', 'Shopzy')),
            'tagline'    => setting('tagline', 'Quality Online Shopping'),
            'logo'       => logo_url(),
            'phone'      => setting('contact_phone', '+880 1700-000000'),
            'email'      => setting('contact_email', 'support@shopzy.com'),
            'address'    => setting('contact_address', 'Dhaka, Bangladesh'),
            'currency'   => setting('currency_symbol', '৳'),
            'footer_text'=> setting('footer_text', 'Thank you for shopping with us!'),
        ];
    }

    private function renderPdf(string $html, Options $options): string
    {
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function fallbackInvoicePdfHtml(Order $order, array $store): string
    {
        $currency = e($store['currency']);
        $rows = '';

        foreach ($order->items as $index => $item) {
            $quantity = $item->quantity ?? $item->qty ?? 1;
            $lineTotal = (float) $item->line_total > 0
                ? (float) $item->line_total
                : (float) $item->unit_price * (int) $quantity;
            $productName = e($item->product_name ?: ($item->product?->name ?? 'Product'));
            $variant = $item->variant ? '<br><small>' . e($item->variant) . '</small>' : '';
            $sku = ($item->product && $item->product->sku) ? '<br><small>SKU: ' . e($item->product->sku) . '</small>' : '';

            $rows .= '<tr>'
                . '<td class="center">' . ($index + 1) . '</td>'
                . '<td class="center"><span class="image-box">Image</span></td>'
                . '<td><strong>' . $productName . '</strong>' . $variant . $sku . '</td>'
                . '<td class="right">' . $currency . number_format((float) $item->unit_price, 2) . '</td>'
                . '<td class="center">' . e((string) $quantity) . '</td>'
                . '<td class="right">' . $currency . number_format($lineTotal, 2) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6" class="center">No items in order.</td></tr>';
        }

        $subtotal = (float) ($order->subtotal ?: ($order->total - ($order->shipping_charge ?? 0)));
        $shipping = (float) ($order->shipping_charge ?? 0);
        $discount = (float) ($order->discount_amount ?: ($order->discount ?? 0));
        $tax = (float) ($order->tax ?? 0);
        $createdAt = $order->created_at ? $order->created_at->format('d M Y, h:i A') : '';

        $discountRow = $discount > 0
            ? '<tr><td>Discount</td><td class="right">-' . $currency . number_format($discount, 2) . '</td></tr>'
            : '';
        $taxRow = $tax > 0
            ? '<tr><td>Tax</td><td class="right">' . $currency . number_format($tax, 2) . '</td></tr>'
            : '';

        return '<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; line-height: 1.45; }
        h1, h2, p { margin: 0; }
        .header { border-bottom: 2px solid #111827; padding-bottom: 14px; margin-bottom: 18px; }
        .store { font-size: 18px; font-weight: 700; }
        .muted { color: #6b7280; }
        .grid { width: 100%; margin-bottom: 18px; }
        .grid td { vertical-align: top; width: 50%; padding: 10px; border: 1px solid #e5e7eb; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f3f4f6; text-align: left; font-size: 10px; text-transform: uppercase; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 8px; }
        .right { text-align: right; }
        .center { text-align: center; }
        .image-box { display: inline-block; width: 42px; height: 42px; border: 1px solid #d1d5db; color: #6b7280; font-size: 8px; line-height: 42px; }
        .summary { width: 42%; margin-left: auto; margin-top: 14px; }
        .total td { font-weight: 700; border-top: 2px solid #111827; }
        .footer { margin-top: 22px; color: #6b7280; font-size: 10px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="store">' . e($store['name']) . '</div>
        <div class="muted">' . e($store['tagline']) . '</div>
        <div class="muted">' . e($store['address']) . ' | ' . e($store['phone']) . ' | ' . e($store['email']) . '</div>
        <h1 style="margin-top:12px;">Invoice #' . e($order->order_number) . '</h1>
        <p class="muted">' . e($createdAt) . '</p>
    </div>

    <table class="grid">
        <tr>
            <td>
                <h2>Billed &amp; shipped to</h2>
                <p><strong>' . e($order->customer_name) . '</strong></p>
                <p>Phone: ' . e($order->customer_phone) . '</p>
                <p>' . e($order->shipping_address) . ($order->city ? ', ' . e($order->city) : '') . '</p>
            </td>
            <td>
                <h2>Payment &amp; delivery</h2>
                <p>Payment: ' . e($order->paymentMethodLabel()) . '</p>
                <p>Status: ' . e(strtoupper($order->payment_status)) . ' / ' . e(strtoupper($order->status)) . '</p>
                <p>Cash on Delivery: <strong>' . $currency . number_format((float) $order->total, 2) . '</strong></p>
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width:36px;">SL</th>
                <th style="width:58px;">Image</th>
                <th>Item &amp; description</th>
                <th class="right" style="width:82px;">Price</th>
                <th class="center" style="width:42px;">Qty</th>
                <th class="right" style="width:92px;">Total</th>
            </tr>
        </thead>
        <tbody>' . $rows . '</tbody>
    </table>

    <table class="summary">
        <tr><td>Subtotal</td><td class="right">' . $currency . number_format($subtotal, 2) . '</td></tr>
        <tr><td>Delivery</td><td class="right">' . $currency . number_format($shipping, 2) . '</td></tr>
        ' . $discountRow . '
        ' . $taxRow . '
        <tr class="total"><td>Total due</td><td class="right">' . $currency . number_format((float) $order->total, 2) . '</td></tr>
    </table>

    <div class="footer">' . e($store['footer_text'] ?: 'Computer-generated invoice.') . '</div>
</body>
</html>';
    }
}
