<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        @if(count($orders) === 1)
            Invoice_{{ $orders->first()->order_number }}
        @else
            Invoices_Batch_{{ count($orders) }}
        @endif
    </title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            line-height: 1.5;
            font-size: 13px;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Minimalist Floating Action Bar (Hidden on print) ── */
        .action-bar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }

        .action-bar .info {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #0f172a;
        }

        .action-bar .info span.muted {
            font-size: 12px;
            color: #64748b;
            font-weight: 400;
        }

        .btn-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
            border: 1px solid transparent;
        }

        .btn-primary {
            background: #0f172a;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #334155;
        }

        .btn-secondary {
            background: #ffffff;
            color: #334155;
            border-color: #cbd5e1;
        }

        .btn-secondary:hover {
            background: #f1f5f9;
        }

        /* ── Minimalist Sheet Container ── */
        .invoice-wrapper {
            max-width: 760px;
            margin: 24px auto;
        }

        .invoice-sheet {
            background: #ffffff;
            padding: 48px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 24px;
            position: relative;
        }

        /* Top Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 24px;
            border-bottom: 1px solid #e2e8f0;
        }

        .brand-title {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.02em;
        }

        .brand-sub {
            font-size: 11.5px;
            color: #64748b;
            margin-top: 3px;
        }

        .header-meta {
            text-align: right;
        }

        .invoice-heading {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.05em;
            color: #0f172a;
        }

        .invoice-code {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12.5px;
            font-weight: 600;
            color: #334155;
            margin-top: 2px;
        }

        .invoice-date {
            font-size: 11.5px;
            color: #64748b;
            margin-top: 2px;
        }

        /* Customer & Order Details Grid */
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
            padding: 24px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .section-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #94a3b8;
            margin-bottom: 6px;
        }

        .customer-name {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
        }

        .text-row {
            font-size: 12px;
            color: #475569;
            margin-top: 2px;
        }

        .badge {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 2px 7px;
            border-radius: 4px;
            background: #f1f5f9;
            color: #475569;
            margin-top: 4px;
        }

        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-pending { background: #fef3c7; color: #b45309; }

        /* Minimal Clean Items Table */
        .table-wrap {
            padding: 20px 0;
        }

        table.items-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.items-table th {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            padding: 10px 0;
            border-bottom: 1px solid #0f172a;
            text-align: left;
        }

        table.items-table th.text-right,
        table.items-table td.text-right {
            text-align: right;
        }

        table.items-table th.text-center,
        table.items-table td.text-center {
            text-align: center;
        }

        table.items-table td {
            padding: 14px 0;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
            font-size: 12.5px;
        }

        .item-title {
            font-weight: 600;
            color: #0f172a;
        }

        .item-variant {
            font-size: 11px;
            color: #64748b;
            margin-top: 1px;
        }

        .item-sku {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10.5px;
            color: #94a3b8;
        }

        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }

        /* Summary Section */
        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 240px;
            gap: 32px;
            padding-top: 16px;
        }

        .notes-content {
            font-size: 11.5px;
            color: #64748b;
            line-height: 1.6;
        }

        .notes-content strong {
            color: #334155;
        }

        table.summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        table.summary-table td {
            padding: 4px 0;
            color: #64748b;
        }

        table.summary-table td.amount {
            text-align: right;
            font-weight: 600;
            color: #0f172a;
        }

        table.summary-table tr.total-row td {
            padding-top: 10px;
            border-top: 1px solid #0f172a;
            font-size: 14.5px;
            font-weight: 800;
            color: #0f172a;
        }

        /* Minimal Footer */
        .footer {
            margin-top: 40px;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            color: #94a3b8;
        }

        /* ── Print Media ── */
        @media print {
            body {
                background: #ffffff !important;
                color: #000000 !important;
                font-size: 10.5pt;
            }

            .no-print {
                display: none !important;
            }

            .invoice-wrapper {
                max-width: 100% !important;
                margin: 0 !important;
            }

            .invoice-sheet {
                border: none !important;
                border-radius: 0 !important;
                padding: 12mm 12mm !important;
                page-break-after: always;
                page-break-inside: avoid;
            }

            .invoice-sheet:last-child {
                page-break-after: auto;
            }
        }
    </style>
</head>
<body>

    <!-- ── Minimal Top Action Bar (Hidden on print) ── -->
    <div class="action-bar no-print">
        <div class="info">
            <span>
                @if($isBulk)
                    Batch Print: {{ count($orders) }} Invoices
                @else
                    Invoice #{{ $orders->first()->order_number }}
                @endif
            </span>
            <span class="muted">· A4 / PDF Ready</span>
        </div>

        <div class="btn-group">
            @if(isset($isCustomer) && $isCustomer)
                <a href="/account" class="btn btn-secondary">
                    ← My Account
                </a>
            @else
                <a href="/admin/orders" class="btn btn-secondary">
                    ← Orders
                </a>
            @endif

            <button onclick="window.print()" class="btn btn-primary">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.2">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Print / Save PDF
            </button>
        </div>
    </div>

    <!-- ── Minimal Clean Invoice Sheet ── -->
    <div class="invoice-wrapper">
        @foreach($orders as $order)
            <div class="invoice-sheet">
                
                <!-- 1. Header -->
                <div class="header">
                    <div>
                        @if(!empty($store['logo']))
                            <img src="{{ $store['logo'] }}" alt="{{ $store['name'] }}" style="max-height: 44px; max-width: 200px; object-fit: contain; margin-bottom: 4px; display: block;" />
                        @else
                            <div class="brand-title">{{ $store['name'] }}</div>
                        @endif
                        <div class="brand-sub">{{ $store['address'] }} · {{ $store['phone'] }} · {{ $store['email'] }}</div>
                    </div>
                    <div class="header-meta">
                        <div class="invoice-heading">INVOICE</div>
                        <div class="invoice-code">#{{ $order->order_number }}</div>
                        <div class="invoice-date">{{ $order->created_at->format('d M Y, h:i A') }}</div>
                    </div>
                </div>

                <!-- 2. Customer & Shipping Info -->
                <div class="details-grid">
                    <div>
                        <div class="section-label">Billed &amp; Shipped To</div>
                        <div class="customer-name">{{ $order->customer_name }}</div>
                        <div class="text-row">Phone: <strong>{{ $order->customer_phone }}</strong></div>
                        <div class="text-row">{{ $order->shipping_address }}@if($order->city), {{ $order->city }}@endif</div>
                        @if($order->customer_email)
                            <div class="text-row">Email: {{ $order->customer_email }}</div>
                        @endif
                        @if($order->delivery_note)
                            <div class="text-row" style="margin-top: 4px; padding: 4px 6px; background: #fef3c7; border: 1px solid #fde68a; border-radius: 4px; font-size: 11px; color: #78350f;">
                                <strong>Note:</strong> {{ $order->delivery_note }}
                            </div>
                        @endif
                    </div>

                    <div style="text-align: right;">
                        <div class="section-label">Payment &amp; Delivery</div>
                        <div class="text-row">Payment: <strong>{{ $order->paymentMethodLabel() }}</strong></div>
                        @if($order->payment_sender_number)
                            <div class="text-row">Sender: <strong>{{ $order->payment_sender_number }}</strong></div>
                        @endif
                        @if($order->payment_txn_id)
                            <div class="text-row">TrxID: <code class="font-mono" style="font-weight: bold; background: #f1f5f9; padding: 1px 4px; border-radius: 3px;">{{ $order->payment_txn_id }}</code></div>
                        @endif
                        @if($order->courier_provider)
                            <div class="text-row">Courier: {{ ucfirst($order->courier_provider) }} @if($order->courier_tracking_code)({{ $order->courier_tracking_code }})@endif</div>
                        @endif
                        <div style="margin-top: 4px;">
                            <span class="badge {{ $order->payment_status === 'verified' ? 'badge-success' : 'badge-pending' }}">
                                {{ strtoupper($order->payment_status) }}
                            </span>
                            <span class="badge">
                                {{ strtoupper($order->status) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 3. Items Table -->
                <div class="table-wrap">
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th style="width: 25px;">#</th>
                                <th>Item &amp; Description</th>
                                <th class="text-right" style="width: 90px;">Price</th>
                                <th class="text-center" style="width: 40px;">Qty</th>
                                <th class="text-right" style="width: 90px;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($order->items as $idx => $item)
                                <tr>
                                    <td class="text-center font-mono">{{ $idx + 1 }}</td>
                                    <td>
                                        <div class="item-title">{{ $item->product_name ?: ($item->product?->name ?? 'Product') }}</div>
                                        @if($item->variant)
                                            <div class="item-variant">{{ $item->variant }}</div>
                                        @endif
                                        @if($item->product && $item->product->sku)
                                            <div class="item-sku">SKU: {{ $item->product->sku }}</div>
                                        @endif
                                    </td>
                                    <td class="text-right font-mono">{{ $store['currency'] }}{{ number_format($item->unit_price, 2) }}</td>
                                    <td class="text-center font-mono font-bold">{{ $item->quantity ?? $item->qty ?? 1 }}</td>
                                    <td class="text-right font-mono font-bold">{{ $store['currency'] }}{{ number_format($item->line_total, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #94a3b8; padding: 16px;">No items in order.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- 4. Summary & Instructions -->
                <div class="summary-grid">
                    <div class="notes-content">
                        @if($order->internal_note)
                            <p><strong>Note:</strong> {{ $order->internal_note }}</p>
                        @else
                            <p>Thank you for your order. Please check the parcel before accepting delivery.</p>
                        @endif
                        <p style="margin-top: 6px;">Cash on Delivery Amount: <strong>{{ $store['currency'] }}{{ number_format($order->total, 2) }}</strong></p>
                    </div>

                    <div>
                        <table class="summary-table">
                            <tr>
                                <td>Subtotal</td>
                                <td class="amount font-mono">{{ $store['currency'] }}{{ number_format($order->subtotal ?: ($order->total - ($order->shipping_charge ?? 0)), 2) }}</td>
                            </tr>
                            <tr>
                                <td>Delivery</td>
                                <td class="amount font-mono">{{ $store['currency'] }}{{ number_format($order->shipping_charge ?? 0, 2) }}</td>
                            </tr>
                            @php
                                $discountAmt = (float) ($order->discount_amount ?: ($order->discount ?? 0));
                            @endphp
                            @if($discountAmt > 0)
                                <tr>
                                    <td>Discount @if($order->coupon_code)({{ $order->coupon_code }})@endif</td>
                                    <td class="amount font-mono" style="color: #15803d;">-{{ $store['currency'] }}{{ number_format($discountAmt, 2) }}</td>
                                </tr>
                            @endif
                            @if(($order->tax ?? 0) > 0)
                                <tr>
                                    <td>Tax</td>
                                    <td class="amount font-mono">{{ $store['currency'] }}{{ number_format($order->tax, 2) }}</td>
                                </tr>
                            @endif
                            <tr class="total-row">
                                <td>Total Due</td>
                                <td class="amount font-mono">{{ $store['currency'] }}{{ number_format($order->total, 2) }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- 5. Minimal Footer -->
                <div class="footer">
                    <div>{{ $store['name'] }} · {{ url('/') }}</div>
                    <div>Computer-generated invoice.</div>
                </div>

            </div>
        @endforeach
    </div>

</body>
</html>
