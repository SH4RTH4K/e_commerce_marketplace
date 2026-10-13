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

        try {
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
        } catch (Throwable $exception) {
            report($exception);

            $output = $this->nativeInvoicePdf($order, $store, $printMode);
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

    private function nativeInvoicePdf(Order $order, array $store, string $printMode = 'color'): string
    {
        $isMono = $printMode === 'mono';
        $green = $isMono ? '#111827' : '#0f8b72';
        $gold = $isMono ? '#9ca3af' : '#d7a22a';
        $softGreen = $isMono ? '#f3f4f6' : '#e7f6f1';
        $tableHead = $isMono ? '#f3f4f6' : '#e3f5ef';
        $content = '';
        $images = [];

        $content .= $this->pdfRect(0, 0, 595, 842, '#f4fbf8');
        $content .= $this->pdfRect(28, 24, 539, 794, '#ffffff');
        $content .= $this->pdfRect(28, 814, 388, 4, $green);
        $content .= $this->pdfRect(416, 814, 151, 4, $gold);

        $brandTextX = 62;
        if (! empty($store['logo'])) {
            $logoName = $this->addPdfImage($images, $store['logo'], 180);
            if ($logoName) {
                $content .= $this->pdfImage($logoName, 62, 735, 38, 38);
                $brandTextX = 112;
            }
        }

        $content .= $this->pdfText($store['name'], $brandTextX, 752, 18, true, '#152238');
        $content .= $this->pdfText($store['tagline'], $brandTextX, 735, 8, true, $green);
        $content .= $this->pdfWrappedText(
            $store['address'] . ' | ' . $store['phone'] . ' | ' . $store['email'],
            62,
            714,
            9,
            false,
            '#64748b',
            70,
            12
        );

        $content .= $this->pdfRect(425, 682, 110, 105, $softGreen);
        $content .= $this->pdfText('OFFICIAL DOCUMENT', 449, 758, 7, true, $green);
        $content .= $this->pdfText('INVOICE', 453, 734, 20, true, '#0f172a');
        $content .= $this->pdfText('#' . $order->order_number, 452, 709, 9, true, '#334155');
        $content .= $this->pdfText($order->created_at ? $order->created_at->format('d M Y, h:i A') : '', 461, 692, 8, false, '#64748b');

        $content .= $this->pdfLine(62, 658, 535, 658, '#e5e7eb');
        $content .= $this->pdfRect(62, 548, 285, 88, '#ffffff', '#e5e7eb');
        $content .= $this->pdfRect(360, 548, 175, 88, '#ffffff', '#e5e7eb');
        $content .= $this->pdfText('BILLED & SHIPPED TO', 76, 612, 7, true, $gold);
        $content .= $this->pdfText($order->customer_name, 76, 590, 12, true, '#1f2937');
        $content .= $this->pdfText('Phone: ' . $order->customer_phone, 76, 574, 9, false, '#111827');
        $content .= $this->pdfWrappedText(
            trim($order->shipping_address . ($order->city ? ', ' . $order->city : '')),
            76,
            559,
            8,
            false,
            '#475569',
            58,
            10
        );
        if ($order->delivery_note) {
            $content .= $this->pdfRect(76, 518, 250, 18, '#fff7df', '#f1dfad');
            $content .= $this->pdfText('Note: ' . $order->delivery_note, 82, 524, 7, false, '#6b4f00');
        }

        $content .= $this->pdfText('PAYMENT & DELIVERY', 425, 612, 7, true, $gold);
        $content .= $this->pdfText('Payment: ' . $order->paymentMethodLabel(), 390, 588, 9, false, '#111827');
        $content .= $this->pdfRect(411, 568, 46, 16, '#fff7df', '#f1dfad');
        $content .= $this->pdfText(strtoupper((string) $order->payment_status), 417, 573, 6, true, '#6b4f00');
        $content .= $this->pdfRect(463, 568, 42, 16, '#edf2f7', '#d1d5db');
        $content .= $this->pdfText(strtoupper((string) $order->status), 470, 573, 6, true, '#334155');

        $content .= $this->pdfLine(62, 496, 535, 496, '#e5e7eb');
        $content .= $this->pdfRect(62, 462, 473, 24, $tableHead);
        $content .= $this->pdfText('SL', 70, 471, 7, true, '#0f172a');
        $content .= $this->pdfText('IMAGE', 94, 471, 7, true, '#0f172a');
        $content .= $this->pdfText('ITEM & DESCRIPTION', 140, 471, 7, true, '#0f172a');
        $content .= $this->pdfText('PRICE', 404, 471, 7, true, '#0f172a');
        $content .= $this->pdfText('QTY', 459, 471, 7, true, '#0f172a');
        $content .= $this->pdfText('TOTAL', 500, 471, 7, true, '#0f172a');

        $y = 430;

        foreach ($order->items as $index => $item) {
            $quantity = $item->quantity ?? $item->qty ?? 1;
            $lineTotal = (float) $item->line_total > 0
                ? (float) $item->line_total
                : (float) $item->unit_price * (int) $quantity;
            $productName = $item->product_name ?: ($item->product?->name ?? 'Product');

            if ($y < 176) {
                break;
            }

            $content .= $this->pdfText((string) ($index + 1), 72, $y, 9, false, '#111827');
            $productImage = $item->image ?: $item->product?->primaryImage()?->path;
            $productImageSource = $productImage ? image_url($productImage, $productName) : null;
            $productImageName = $this->addPdfImage($images, $productImageSource, 220);
            if ($productImageName) {
                $content .= $this->pdfRect(94, $y - 18, 28, 28, '#ffffff', '#cbd5e1');
                $content .= $this->pdfImage($productImageName, 95, $y - 17, 26, 26);
            } else {
                $content .= $this->pdfRect(94, $y - 18, 28, 28, '#f8fafc', '#cbd5e1');
                $content .= $this->pdfText('Image', 98, $y - 3, 6, true, '#64748b');
            }
            $productLines = array_slice($this->pdfWrapLines($productName, 38), 0, 4);
            foreach ($productLines as $lineIndex => $line) {
                $content .= $this->pdfText($line, 140, $y + 2 - ($lineIndex * 11), 9, true, '#111827');
            }
            $metaY = $y + 2 - (count($productLines) * 11) - 4;
            if ($item->variant) {
                $content .= $this->pdfText((string) $item->variant, 140, $metaY, 7, false, '#64748b');
                $metaY -= 10;
            }
            if ($item->product && $item->product->sku) {
                $content .= $this->pdfText('SKU: ' . $item->product->sku, 140, $metaY, 7, false, '#64748b');
            }
            $content .= $this->pdfText('BDT ' . number_format((float) $item->unit_price, 2), 392, $y, 8, false, '#111827');
            $content .= $this->pdfText((string) $quantity, 464, $y, 8, true, '#111827');
            $content .= $this->pdfText('BDT ' . number_format($lineTotal, 2), 486, $y, 8, true, '#111827');
            $content .= $this->pdfLine(62, $y - 48, 535, $y - 48, '#e5e7eb');

            $y -= 64;
        }

        $subtotal = (float) ($order->subtotal ?: ($order->total - ($order->shipping_charge ?? 0)));
        $discount = (float) ($order->discount_amount ?: ($order->discount ?? 0));
        $tax = (float) ($order->tax ?? 0);
        $summaryY = max(120, $y - 18);

        $content .= $this->pdfWrappedText(
            $order->internal_note ? 'Note: ' . $order->internal_note : 'Thank you for your order. Please check the parcel before accepting delivery.',
            62,
            $summaryY,
            8,
            false,
            '#334155',
            54,
            11
        );
        $content .= $this->pdfText('Cash on Delivery Amount: BDT ' . number_format((float) $order->total, 2), 62, $summaryY - 30, 8, true, '#334155');

        $summaryX = 390;
        $content .= $this->pdfText('Subtotal', $summaryX, $summaryY, 8, false, '#475569');
        $content .= $this->pdfText('BDT ' . number_format($subtotal, 2), 475, $summaryY, 8, true, '#111827');
        $content .= $this->pdfText('Delivery', $summaryX, $summaryY - 18, 8, false, '#475569');
        $content .= $this->pdfText('BDT ' . number_format((float) ($order->shipping_charge ?? 0), 2), 475, $summaryY - 18, 8, true, '#111827');
        $summaryTotalY = $summaryY - 72;
        if ($discount > 0) {
            $content .= $this->pdfText('Discount', $summaryX, $summaryY - 36, 8, false, '#475569');
            $content .= $this->pdfText('-BDT ' . number_format($discount, 2), 475, $summaryY - 36, 8, true, '#b91c1c');
            $summaryTotalY -= 18;
        }
        if ($tax > 0) {
            $content .= $this->pdfText('Tax', $summaryX, $summaryTotalY + 18, 8, false, '#475569');
            $content .= $this->pdfText('BDT ' . number_format($tax, 2), 475, $summaryTotalY + 18, 8, true, '#111827');
            $summaryTotalY -= 18;
        }

        $content .= $this->pdfRect(388, $summaryTotalY - 8, 148, 34, $green);
        $content .= $this->pdfText('Total due', 400, $summaryTotalY + 5, 10, true, '#ffffff');
        $content .= $this->pdfText('BDT ' . number_format((float) $order->total, 2), 472, $summaryTotalY + 5, 10, true, '#ffffff');

        $content .= $this->pdfLine(62, 84, 535, 84, '#e5e7eb');
        $content .= $this->pdfText($store['name'] . ' | ' . url('/'), 62, 62, 7, true, '#64748b');
        $content .= $this->pdfWrappedText($store['footer_text'] ?: 'Computer-generated invoice.', 62, 48, 7, false, '#64748b', 96, 9);

        return $this->buildPdfPage($content, $images);
    }

    private function buildPdfPage(string $stream, array $images = []): string
    {
        $imageObjects = [];
        $xObjects = '';
        $nextObjectId = 5;
        foreach ($images as $name => $image) {
            $objectId = $nextObjectId++;
            $xObjects .= '/' . $name . ' ' . $objectId . ' 0 R ';
            $imageObjects[$objectId] = '<< /Type /XObject /Subtype /Image /Width ' . $image['width']
                . ' /Height ' . $image['height']
                . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($image['data'])
                . " >>\nstream\n" . $image['data'] . "\nendstream";
        }

        $resourceXObjects = $xObjects !== '' ? ' /XObject << ' . $xObjects . '>>' : '';
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> /F2 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >> >>' . $resourceXObjects . ' >> /Contents 4 0 R >>',
            4 => '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream",
        ] + $imageObjects;

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        $objectCount = count($objects);

        for ($i = 1; $i <= $objectCount; $i++) {
            $offsets[$i] = strlen($pdf);
            $pdf .= $i . " 0 obj\n" . $objects[$i] . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . ($objectCount + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $objectCount; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size " . ($objectCount + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    private function pdfRect(float $x, float $y, float $width, float $height, string $fill, ?string $stroke = null): string
    {
        $command = 'q ' . $this->pdfColor($fill, true);
        if ($stroke) {
            $command .= ' ' . $this->pdfColor($stroke, false);
        }

        $command .= ' ' . $this->pdfNumber($x) . ' ' . $this->pdfNumber($y) . ' ' . $this->pdfNumber($width) . ' ' . $this->pdfNumber($height) . ' re ' . ($stroke ? 'B' : 'f') . " Q\n";

        return $command;
    }

    private function pdfLine(float $x1, float $y1, float $x2, float $y2, string $stroke): string
    {
        return 'q ' . $this->pdfColor($stroke, false) . ' 0.7 w ' . $this->pdfNumber($x1) . ' ' . $this->pdfNumber($y1) . ' m ' . $this->pdfNumber($x2) . ' ' . $this->pdfNumber($y2) . " l S Q\n";
    }

    private function pdfImage(string $name, float $x, float $y, float $width, float $height): string
    {
        return 'q ' . $this->pdfNumber($width) . ' 0 0 ' . $this->pdfNumber($height) . ' ' . $this->pdfNumber($x) . ' ' . $this->pdfNumber($y) . ' cm /' . $name . " Do Q\n";
    }

    private function addPdfImage(array &$images, ?string $source, int $maxPixels): ?string
    {
        $image = $this->pdfJpegImage($source, $maxPixels);
        if (! $image) {
            return null;
        }

        $name = 'Im' . (count($images) + 1);
        $images[$name] = $image;

        return $name;
    }

    private function pdfJpegImage(?string $source, int $maxPixels): ?array
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $bytes = $this->readPdfImageBytes($source);
        if (! $bytes) {
            return null;
        }

        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 1 || $height < 1) {
            imagedestroy($image);
            return null;
        }

        $scale = min(1, $maxPixels / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 84);
        $jpeg = ob_get_clean();

        imagedestroy($image);
        imagedestroy($canvas);

        if (! $jpeg) {
            return null;
        }

        return [
            'width' => $targetWidth,
            'height' => $targetHeight,
            'data' => $jpeg,
        ];
    }

    private function readPdfImageBytes(?string $source): ?string
    {
        $source = trim((string) $source);
        if ($source === '' || str_starts_with($source, 'data:image/svg')) {
            return null;
        }

        $path = $this->localPathFromImageSource($source);
        if ($path && is_file($path) && is_readable($path)) {
            return file_get_contents($path) ?: null;
        }

        if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 4,
                    'user_agent' => 'TAQI LIFE invoice PDF',
                ],
            ]);

            return @file_get_contents($source, false, $context) ?: null;
        }

        return null;
    }

    private function localPathFromImageSource(string $source): ?string
    {
        $parts = parse_url($source);
        $path = $parts['path'] ?? $source;
        if (! is_string($path) || $path === '') {
            return null;
        }

        $path = ltrim($path, '/');
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        $candidates = [
            public_path($path),
            public_path('storage/' . preg_replace('#^(storage/|public/storage/)#', '', $path)),
            storage_path('app/public/' . preg_replace('#^(storage/|public/storage/)#', '', $path)),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function pdfText(mixed $text, float $x, float $y, int $size = 10, bool $bold = false, string $color = '#111111'): string
    {
        return 'BT ' . $this->pdfColor($color, true) . ' /' . ($bold ? 'F2' : 'F1') . ' ' . $size . ' Tf ' . $this->pdfNumber($x) . ' ' . $this->pdfNumber($y) . ' Td (' . $this->escapePdfText($this->pdfAscii($text)) . ") Tj ET\n";
    }

    private function pdfWrappedText(mixed $text, float $x, float $y, int $size, bool $bold, string $color, int $maxChars, int $lineHeight): string
    {
        $output = '';
        $lines = $this->pdfWrapLines($text, $maxChars);

        foreach ($lines as $index => $line) {
            $output .= $this->pdfText($line, $x, $y - ($index * $lineHeight), $size, $bold, $color);
        }

        return $output;
    }

    private function pdfWrapLines(mixed $text, int $maxChars): array
    {
        return explode("\n", wordwrap($this->pdfAscii($text), $maxChars, "\n", true));
    }

    private function pdfColor(string $hex, bool $fill): string
    {
        $hex = ltrim($hex, '#');
        $red = hexdec(substr($hex, 0, 2)) / 255;
        $green = hexdec(substr($hex, 2, 2)) / 255;
        $blue = hexdec(substr($hex, 4, 2)) / 255;

        return $this->pdfNumber($red) . ' ' . $this->pdfNumber($green) . ' ' . $this->pdfNumber($blue) . ' ' . ($fill ? 'rg' : 'RG');
    }

    private function pdfNumber(float $value): string
    {
        return rtrim(rtrim(sprintf('%.3F', $value), '0'), '.');
    }

    private function pdfAscii(mixed $value): string
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $value));
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        if ($converted !== false) {
            $text = $converted;
        }

        return preg_replace('/[^\x20-\x7E]/', '', $text) ?: '';
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
