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
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #123044;
            --ink-soft: #355467;
            --muted: #718895;
            --line: #dbe6e8;
            --line-soft: #edf3f2;
            --paper: #ffffff;
            --canvas: #eef5f3;
            --accent: #087861;
            --accent-dark: #075a4a;
            --accent-soft: #e3f3ed;
            --gold: #d29a35;
            --gold-soft: #fff5dd;
            --danger-soft: #fff0ec;
            --danger: #a64e36;
            --shadow: 0 22px 55px rgba(18, 48, 68, 0.09);
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            background: var(--canvas);
            color: var(--ink);
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 13px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }

        .action-bar {
            position: sticky;
            top: 0;
            z-index: 10;
            padding: 13px 28px;
            background: rgba(255, 255, 255, 0.94);
            border-bottom: 1px solid rgba(219, 230, 232, 0.9);
            box-shadow: 0 5px 18px rgba(18, 48, 68, 0.06);
            backdrop-filter: blur(14px);
        }

        .toolbar-shell {
            max-width: 1120px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .toolbar-context {
            display: flex;
            align-items: center;
            gap: 11px;
            min-width: 0;
        }

        .toolbar-mark {
            width: 30px;
            height: 30px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border-radius: 9px;
            background: var(--accent-soft);
            color: var(--accent);
        }

        .toolbar-title {
            color: var(--ink);
            font-family: 'Space Grotesk', sans-serif;
            font-size: 13px;
            font-weight: 700;
        }

        .toolbar-subtitle {
            color: var(--muted);
            font-size: 11px;
        }

        .toolbar-divider {
            width: 1px;
            height: 24px;
            background: var(--line);
        }

        .btn-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 0 0 auto;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 36px;
            padding: 8px 14px;
            border: 1px solid transparent;
            border-radius: 9px;
            cursor: pointer;
            font: 600 12px/1 'DM Sans', sans-serif;
            text-decoration: none;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-secondary {
            color: var(--ink-soft);
            background: #fff;
            border-color: var(--line);
        }

        .btn-secondary:hover {
            background: #f8fbfa;
            box-shadow: 0 4px 12px rgba(18, 48, 68, 0.08);
        }

        .btn-mono {
            color: #1c2b31;
            background: #fff;
            border-color: #afbec1;
        }

        .btn-mono:hover {
            background: #f2f5f5;
            box-shadow: 0 4px 12px rgba(18, 48, 68, 0.1);
        }

        .btn-color {
            color: #fff;
            background: var(--accent);
            box-shadow: 0 5px 12px rgba(8, 120, 97, 0.2);
        }

        .btn-color:hover {
            background: var(--accent-dark);
            box-shadow: 0 7px 16px rgba(8, 120, 97, 0.28);
        }

        .btn-whatsapp {
            color: #16834b;
            background: #ecfdf3;
            border-color: #bcebd0;
        }

        .btn-whatsapp:hover {
            background: #d8f8e5;
            box-shadow: 0 4px 12px rgba(22, 131, 75, 0.14);
        }

        .btn-whatsapp-mono {
            color: #35644b;
            background: #f4fcf7;
            border-color: #cfe9d8;
        }

        .btn-whatsapp-mono:hover {
            background: #e4f7eb;
            box-shadow: 0 4px 12px rgba(22, 131, 75, 0.1);
        }

        .invoice-wrapper {
            width: min(100% - 32px, 920px);
            margin: 34px auto 54px;
        }

        .invoice-sheet {
            position: relative;
            overflow: hidden;
            margin-bottom: 28px;
            padding: 48px 54px 34px;
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: 18px;
            box-shadow: var(--shadow);
        }

        .invoice-sheet::before {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 5px;
            background: linear-gradient(90deg, var(--accent) 0%, var(--accent) 62%, var(--gold) 62%, var(--gold) 100%);
        }

        .header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 30px;
            padding-bottom: 29px;
            border-bottom: 1px solid var(--line);
        }

        .brand-lockup {
            min-width: 0;
        }

        .brand-row {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-logo {
            display: block;
            max-width: 190px;
            max-height: 58px;
            object-fit: contain;
            object-position: left center;
        }

        .brand-copy .brand-title {
            font-size: 21px;
        }

        .brand-title {
            color: var(--ink);
            font-family: 'Space Grotesk', sans-serif;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.04em;
        }

        .brand-kicker {
            margin-top: 3px;
            color: var(--accent);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .brand-sub {
            max-width: 470px;
            margin-top: 11px;
            color: var(--muted);
            font-size: 11px;
        }

        .header-meta {
            min-width: 190px;
            padding: 14px 16px;
            text-align: right;
            background: var(--accent-soft);
            border-radius: 12px;
        }

        .invoice-eyebrow {
            color: var(--accent);
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .invoice-heading {
            margin-top: 1px;
            color: var(--ink);
            font-family: 'Space Grotesk', sans-serif;
            font-size: 25px;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .invoice-code {
            margin-top: 6px;
            color: var(--ink-soft);
            font-family: 'Space Grotesk', monospace;
            font-size: 12px;
            font-weight: 600;
        }

        .invoice-date {
            margin-top: 3px;
            color: var(--muted);
            font-size: 10.5px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) minmax(260px, 0.75fr);
            gap: 18px;
            padding: 24px 0;
            border-bottom: 1px solid var(--line);
        }

        .detail-card {
            min-height: 122px;
            padding: 16px 18px;
            background: #fbfdfc;
            border: 1px solid var(--line-soft);
            border-radius: 12px;
        }

        .detail-card.payment-card {
            text-align: right;
            background: #f8fbfa;
        }

        .section-label {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 8px;
            color: var(--muted);
            font-size: 9.5px;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .payment-card .section-label {
            justify-content: flex-end;
        }

        .section-label::before {
            content: "";
            width: 17px;
            height: 2px;
            background: var(--gold);
            border-radius: 99px;
        }

        .customer-name {
            color: var(--ink);
            font-family: 'Space Grotesk', sans-serif;
            font-size: 16px;
            font-weight: 700;
        }

        .text-row {
            margin-top: 3px;
            color: var(--ink-soft);
            font-size: 11.5px;
        }

        .text-row strong {
            color: var(--ink);
            font-weight: 700;
        }

        .badge-row {
            margin-top: 8px;
        }

        .badge {
            display: inline-block;
            margin-top: 3px;
            padding: 4px 8px;
            color: var(--ink-soft);
            background: #eaf0f0;
            border: 1px solid #dfe9e8;
            border-radius: 5px;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .badge-success {
            color: var(--accent-dark);
            background: var(--accent-soft);
            border-color: #c7e7dc;
        }

        .badge-pending {
            color: #96661a;
            background: var(--gold-soft);
            border-color: #f2dfad;
        }

        .delivery-note {
            margin-top: 7px;
            padding: 6px 8px;
            color: #805d1d;
            background: var(--gold-soft);
            border: 1px solid #f2dfad;
            border-radius: 6px;
            font-size: 10.5px;
        }

        .delivery-note strong {
            color: #6e4d12;
        }

        .table-wrap {
            padding: 25px 0 19px;
        }

        table.items-table,
        table.summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.items-table th {
            padding: 10px 10px;
            color: var(--accent-dark);
            background: var(--accent-soft);
            font-size: 9.5px;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-align: left;
            text-transform: uppercase;
        }

        table.items-table th:first-child {
            padding-left: 12px;
            border-radius: 7px 0 0 7px;
        }

        table.items-table th:last-child {
            padding-right: 12px;
            border-radius: 0 7px 7px 0;
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
            padding: 16px 10px;
            border-bottom: 1px solid var(--line-soft);
            color: var(--ink-soft);
            font-size: 12px;
            vertical-align: top;
        }

        table.items-table td:first-child {
            padding-left: 12px;
        }

        table.items-table td:last-child {
            padding-right: 12px;
        }

        .item-title {
            color: var(--ink);
            font-family: 'Space Grotesk', sans-serif;
            font-size: 12.5px;
            font-weight: 600;
        }

        .item-title a {
            color: inherit;
            text-decoration: none;
        }

        .item-title a:hover {
            color: var(--accent);
            text-decoration: underline;
        }

        .item-variant {
            margin-top: 2px;
            color: var(--muted);
            font-size: 10.5px;
        }

        .item-sku,
        .font-mono {
            font-family: 'Space Grotesk', monospace;
        }

        .item-sku {
            margin-top: 4px;
            color: var(--muted);
            font-size: 9.5px;
        }

        .font-bold {
            font-weight: 700;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 270px;
            align-items: start;
            gap: 34px;
            padding-top: 11px;
        }

        .notes-content {
            padding: 13px 0;
            color: var(--muted);
            font-size: 11.5px;
            line-height: 1.65;
        }

        .notes-content p + p {
            margin-top: 9px;
        }

        .notes-content strong {
            color: var(--ink-soft);
        }

        .summary-table {
            padding: 7px 0;
        }

        table.summary-table td {
            padding: 5px 0;
            color: var(--muted);
            font-size: 11.5px;
        }

        table.summary-table td.amount {
            color: var(--ink);
            font-weight: 700;
            text-align: right;
        }

        table.summary-table .discount-amount {
            color: var(--accent) !important;
        }

        table.summary-table tr.total-row td {
            padding: 14px 15px;
            color: #fff;
            background: var(--accent);
            font-size: 14px;
            font-weight: 800;
        }

        table.summary-table tr.total-row td:first-child {
            border-radius: 9px 0 0 9px;
        }

        table.summary-table tr.total-row td:last-child {
            border-radius: 0 9px 9px 0;
        }

        .footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-top: 31px;
            padding-top: 16px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 10px;
        }

        .footer strong {
            color: var(--accent);
            font-weight: 700;
        }

        @media (max-width: 700px) {
            .action-bar {
                padding: 12px 16px;
            }

            .toolbar-shell,
            .header,
            .footer {
                align-items: stretch;
                flex-direction: column;
            }

            .toolbar-shell {
                gap: 12px;
            }

            .toolbar-divider {
                display: none;
            }

            .btn-group {
                display: grid;
                grid-template-columns: 1fr 1fr 1fr;
            }

            .btn {
                padding-inline: 9px;
                font-size: 11px;
            }

            .invoice-wrapper {
                width: min(100% - 20px, 920px);
                margin-top: 20px;
            }

            .invoice-sheet {
                padding: 35px 20px 25px;
                border-radius: 13px;
            }

            .brand-row {
                align-items: flex-start;
            }

            .brand-logo {
                max-width: 90px;
                max-height: 55px;
            }

            .header-meta,
            .detail-card.payment-card {
                text-align: left;
            }

            .payment-card .section-label {
                justify-content: flex-start;
            }

            .details-grid,
            .summary-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .summary-grid {
                gap: 6px;
            }

            .footer {
                gap: 5px;
            }

            table.items-table {
                min-width: 570px;
            }

            .table-wrap {
                overflow-x: auto;
            }
        }

        @media print {
            @page {
                size: A4;
                margin: 0;
            }

            body {
                min-height: auto;
                background: #fff !important;
            }

            .no-print {
                display: none !important;
            }

            .invoice-wrapper {
                width: 100% !important;
                margin: 0 !important;
            }

            .invoice-sheet {
                min-height: 277mm;
                margin: 0 !important;
                padding: 12mm 13mm 9mm !important;
                border: none !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                page-break-after: always;
                page-break-inside: avoid;
            }

            .invoice-sheet:last-child {
                page-break-after: auto;
            }

            .table-wrap {
                overflow: visible;
            }
        }

        /* Explicit black-and-white print mode. */
        body.print-mono {
            --ink: #111111;
            --ink-soft: #333333;
            --muted: #555555;
            --line: #b8b8b8;
            --line-soft: #dddddd;
            --accent: #111111;
            --accent-dark: #111111;
            --accent-soft: #eeeeee;
            --gold: #555555;
            --gold-soft: #f4f4f4;
            --danger-soft: #f4f4f4;
            --danger: #222222;
        }

        body.print-mono .invoice-sheet::before {
            background: #111 !important;
        }

        body.print-mono .invoice-sheet,
        body.print-mono .detail-card,
        body.print-mono .detail-card.payment-card,
        body.print-mono .header-meta {
            background: #fff !important;
        }

        body.print-mono table.items-table th {
            color: #111 !important;
            background: #eeeeee !important;
        }

        body.print-mono table.summary-table tr.total-row td {
            color: #111 !important;
            background: #fff !important;
            border-top: 2px solid #111 !important;
            border-bottom: 2px solid #111 !important;
        }

        body.print-mono .badge,
        body.print-mono .badge-success,
        body.print-mono .badge-pending,
        body.print-mono .delivery-note {
            color: #111 !important;
            background: #f4f4f4 !important;
            border-color: #aaa !important;
        }

        body.pdf-mode {
            min-height: auto;
            background: #fff !important;
        }

        body.pdf-mode .invoice-sheet {
            box-shadow: none !important;
            border-radius: 0 !important;
        }

        .wa-choice-modal[hidden] {
            display: none !important;
        }

        .wa-choice-modal {
            position: fixed;
            inset: 0;
            z-index: 50;
            display: grid;
            place-items: center;
            padding: 20px;
            background: rgba(18, 48, 68, 0.48);
            backdrop-filter: blur(5px);
        }

        .wa-choice-card {
            width: min(100%, 390px);
            padding: 24px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: 0 24px 70px rgba(18, 48, 68, 0.22);
        }

        .wa-choice-title {
            color: var(--ink);
            font-family: 'Space Grotesk', sans-serif;
            font-size: 17px;
            font-weight: 700;
        }

        .wa-choice-copy {
            margin-top: 5px;
            color: var(--muted);
            font-size: 11.5px;
            line-height: 1.55;
        }

        .wa-choice-actions {
            display: grid;
            gap: 9px;
            margin-top: 19px;
        }

        .wa-choice-action {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 11px 12px;
            color: var(--ink);
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 10px;
            cursor: pointer;
            font: 700 12px/1.3 'DM Sans', sans-serif;
            text-align: left;
        }

        .wa-choice-action:hover {
            background: #f7fbf9;
            border-color: #bcebd0;
        }

        .wa-choice-action.primary {
            color: #126a40;
            background: #ecfdf3;
            border-color: #bcebd0;
        }

        .wa-choice-action span {
            display: block;
            color: var(--muted);
            font-size: 10.5px;
            font-weight: 400;
        }

        .wa-choice-cancel {
            width: 100%;
            margin-top: 13px;
            padding: 8px;
            color: var(--muted);
            background: transparent;
            border: 0;
            cursor: pointer;
            font: 600 11px 'DM Sans', sans-serif;
        }
    </style>
</head>
<body class="{{ ($isPdf ?? false) ? 'pdf-mode ' . (($printMode ?? 'color') === 'mono' ? 'print-mono' : '') : '' }}">
    @php
        $singleOrder = count($orders) === 1 ? $orders->first() : null;
        $singleCustomerInvoiceUrl = $singleOrder
            ? url('/order/' . $singleOrder->order_number . '/invoice?token=' . urlencode((string) $singleOrder->confirmation_token))
            : null;
    @endphp
    @if(!($isPdf ?? false))
    <div class="action-bar no-print">
        <div class="toolbar-shell">
            <div class="toolbar-context">
                <div class="toolbar-mark" aria-hidden="true">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h8l4 4v14H7a2 2 0 01-2-2V5a2 2 0 012-2z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 3v5h5M9 13h6M9 17h6" />
                    </svg>
                </div>
                <div>
                    <div class="toolbar-title">
                        @if($isBulk)
                            {{ count($orders) }} invoices ready to print
                        @else
                            Invoice #{{ $orders->first()->order_number }}
                        @endif
                    </div>
                    <div class="toolbar-subtitle">A4 layout · choose your print finish</div>
                </div>
            </div>

            <div class="btn-group">
                @if(isset($isCustomer) && $isCustomer)
                    <a href="/account" class="btn btn-secondary">← Account</a>
                @else
                    <a href="/admin/orders" class="btn btn-secondary">← Orders</a>
                @endif
                <div class="toolbar-divider" aria-hidden="true"></div>
                @if($singleOrder && $singleOrder->customer_phone && $singleCustomerInvoiceUrl)
                    <button type="button" data-wa-invoice="color" data-wa-phone="{{ $singleOrder->customer_phone }}" data-wa-name="{{ $singleOrder->customer_name }}" data-wa-order="{{ $singleOrder->order_number }}" data-wa-url="{{ $singleCustomerInvoiceUrl }}" data-wa-pdf-url="{{ '/admin/orders/' . $singleOrder->id . '/invoice/pdf' }}" class="btn btn-whatsapp" title="Open WhatsApp with a ready-to-send color invoice message">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 11.5a8 8 0 01-11.7 7.1L4 20l1.4-4.1A8 8 0 1112 20a8.3 8.3 0 008-8.5z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.8 8.7c.2-.4.4-.4.7-.4h.4c.2 0 .4.1.5.4l.6 1.4c.1.2.1.4-.1.6l-.5.6c.5 1 1.2 1.7 2.2 2.2l.6-.5c.2-.2.4-.2.6-.1l1.4.6c.3.1.4.3.4.5v.4c0 .3 0 .5-.4.7-.3.2-.9.3-1.4.1-2.7-.7-4.7-2.7-5.4-5.4-.2-.5-.1-1.1.1-1.4z" />
                        </svg>
                        WhatsApp · Color
                    </button>
                    <button type="button" data-wa-invoice="mono" data-wa-phone="{{ $singleOrder->customer_phone }}" data-wa-name="{{ $singleOrder->customer_name }}" data-wa-order="{{ $singleOrder->order_number }}" data-wa-url="{{ $singleCustomerInvoiceUrl }}" data-wa-pdf-url="{{ '/admin/orders/' . $singleOrder->id . '/invoice/pdf' }}" class="btn btn-whatsapp-mono" title="Open WhatsApp with a ready-to-send black and white invoice message">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 11.5a8 8 0 01-11.7 7.1L4 20l1.4-4.1A8 8 0 1112 20a8.3 8.3 0 008-8.5z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.8 8.7c.2-.4.4-.4.7-.4h.4c.2 0 .4.1.5.4l.6 1.4c.1.2.1.4-.1.6l-.5.6c.5 1 1.2 1.7 2.2 2.2l.6-.5c.2-.2.4-.2.6-.1l1.4.6c.3.1.4.3.4.5v.4c0 .3 0 .5-.4.7-.3.2-.9.3-1.4.1-2.7-.7-4.7-2.7-5.4-5.4-.2-.5-.1-1.1.1-1.4z" />
                        </svg>
                        WhatsApp · B&amp;W
                    </button>
                @endif
                <button type="button" onclick="printInvoice('mono')" class="btn btn-mono">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V4h12v5M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v6H6v-6z" />
                    </svg>
                    B&amp;W Print
                </button>
                <button type="button" onclick="printInvoice('color')" class="btn btn-color">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V4h12v5M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v6H6v-6z" />
                    </svg>
                    Color Print
                </button>
            </div>
        </div>
    </div>
    @endif

    <main class="invoice-wrapper">
        @foreach($orders as $order)
            <article class="invoice-sheet">
                <header class="header">
                    <div class="brand-lockup">
                        @if(!empty($store['logo']))
                            <div class="brand-row">
                                <img class="brand-logo" src="{{ $store['logo'] }}" alt="{{ $store['name'] }}">
                                <div class="brand-copy">
                                    <div class="brand-title">{{ $store['name'] }}</div>
                                    <div class="brand-kicker">{{ $store['tagline'] }}</div>
                                </div>
                            </div>
                        @else
                            <div class="brand-title">{{ $store['name'] }}</div>
                            <div class="brand-kicker">{{ $store['tagline'] }}</div>
                        @endif
                        <div class="brand-sub">{{ $store['address'] }} · {{ $store['phone'] }} · {{ $store['email'] }}</div>
                    </div>
                    <div class="header-meta">
                        <div class="invoice-eyebrow">Official document</div>
                        <div class="invoice-heading">INVOICE</div>
                        <div class="invoice-code">#{{ $order->order_number }}</div>
                        <div class="invoice-date">{{ $order->created_at->format('d M Y, h:i A') }}</div>
                    </div>
                </header>

                <section class="details-grid">
                    <div class="detail-card">
                        <div class="section-label">Billed &amp; shipped to</div>
                        <div class="customer-name">{{ $order->customer_name }}</div>
                        <div class="text-row">Phone: <strong>{{ $order->customer_phone }}</strong></div>
                        <div class="text-row">{{ $order->shipping_address }}@if($order->city), {{ $order->city }}@endif</div>
                        @if($order->customer_email)
                            <div class="text-row">Email: {{ $order->customer_email }}</div>
                        @endif
                        @if($order->delivery_note)
                            <div class="delivery-note"><strong>Note:</strong> {{ $order->delivery_note }}</div>
                        @endif
                    </div>

                    <div class="detail-card payment-card">
                        <div class="section-label">Payment &amp; delivery</div>
                        <div class="text-row">Payment: <strong>{{ $order->paymentMethodLabel() }}</strong></div>
                        @if($order->payment_sender_number)
                            <div class="text-row">Sender: <strong>{{ $order->payment_sender_number }}</strong></div>
                        @endif
                        @if($order->payment_txn_id)
                            <div class="text-row">TrxID: <strong class="font-mono">{{ $order->payment_txn_id }}</strong></div>
                        @endif
                        @if($order->courier_provider)
                            <div class="text-row">Courier: {{ ucfirst($order->courier_provider) }} @if($order->courier_tracking_code)({{ $order->courier_tracking_code }})@endif</div>
                        @endif
                        <div class="badge-row">
                            <span class="badge {{ $order->payment_status === 'verified' ? 'badge-success' : 'badge-pending' }}">{{ strtoupper($order->payment_status) }}</span>
                            <span class="badge">{{ strtoupper($order->status) }}</span>
                        </div>
                    </div>
                </section>

                <section class="table-wrap">
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th style="width: 36px;">#</th>
                                <th>Item &amp; description</th>
                                <th class="text-right" style="width: 92px;">Price</th>
                                <th class="text-center" style="width: 42px;">Qty</th>
                                <th class="text-right" style="width: 98px;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($order->items as $idx => $item)
                                <tr>
                                    <td class="text-center font-mono">{{ $idx + 1 }}</td>
                                    <td>
                                        @php
                                            $itemQuantity = $item->quantity ?? $item->qty ?? 1;
                                            $itemLineTotal = (float) $item->line_total > 0
                                                ? (float) $item->line_total
                                                : (float) $item->unit_price * (int) $itemQuantity;
                                            $productName = $item->product_name ?: ($item->product?->name ?? 'Product');
                                            $productUrl = $item->product ? route('product.show', $item->product) : null;
                                        @endphp
                                        <div class="item-title">
                                            @if($productUrl)
                                                <a href="{{ $productUrl }}" target="_blank" rel="noopener">{{ $productName }}</a>
                                            @else
                                                {{ $productName }}
                                            @endif
                                        </div>
                                        @if($item->variant)
                                            <div class="item-variant">{{ $item->variant }}</div>
                                        @endif
                                        @if($item->product && $item->product->sku)
                                            <div class="item-sku">SKU: {{ $item->product->sku }}</div>
                                        @endif
                                    </td>
                                    <td class="text-right font-mono">{{ $store['currency'] }}{{ number_format($item->unit_price, 2) }}</td>
                                    <td class="text-center font-mono font-bold">{{ $itemQuantity }}</td>
                                    <td class="text-right font-mono font-bold">{{ $store['currency'] }}{{ number_format($itemLineTotal, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--muted); padding: 18px;">No items in order.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>

                <section class="summary-grid">
                    <div class="notes-content">
                        @if($order->internal_note)
                            <p><strong>Note:</strong> {{ $order->internal_note }}</p>
                        @else
                            <p>Thank you for your order. Please check the parcel before accepting delivery.</p>
                        @endif
                        <p>Cash on Delivery Amount: <strong>{{ $store['currency'] }}{{ number_format($order->total, 2) }}</strong></p>
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
                                    <td class="amount font-mono discount-amount">-{{ $store['currency'] }}{{ number_format($discountAmt, 2) }}</td>
                                </tr>
                            @endif
                            @if(($order->tax ?? 0) > 0)
                                <tr>
                                    <td>Tax</td>
                                    <td class="amount font-mono">{{ $store['currency'] }}{{ number_format($order->tax, 2) }}</td>
                                </tr>
                            @endif
                            <tr class="total-row">
                                <td>Total due</td>
                                <td class="amount font-mono">{{ $store['currency'] }}{{ number_format($order->total, 2) }}</td>
                            </tr>
                        </table>
                    </div>
                </section>

                <footer class="footer">
                    <div><strong>{{ $store['name'] }}</strong> · {{ url('/') }}</div>
                    <div>{{ $store['footer_text'] ?: 'Computer-generated invoice.' }}</div>
                </footer>
            </article>
        @endforeach
    </main>

    @if(!($isPdf ?? false))
    <div id="wa-choice-modal" class="wa-choice-modal no-print" hidden role="dialog" aria-modal="true" aria-labelledby="wa-choice-title">
        <div class="wa-choice-card">
            <div class="wa-choice-title" id="wa-choice-title">Send invoice on WhatsApp</div>
            <div class="wa-choice-copy">Choose whether to share the actual PDF or send a secure invoice link. PDF sharing works directly on supported mobile devices.</div>
            <div class="wa-choice-actions">
                <button type="button" class="wa-choice-action primary" data-wa-choice="pdf">
                    <span aria-hidden="true">📎</span>
                    <span><strong>Send PDF to WhatsApp</strong><span>Attach the selected invoice PDF</span></span>
                </button>
                <button type="button" class="wa-choice-action" data-wa-choice="link">
                    <span aria-hidden="true">🔗</span>
                    <span><strong>Send invoice link</strong><span>Open the secure invoice link in WhatsApp</span></span>
                </button>
            </div>
            <button type="button" class="wa-choice-cancel" data-wa-choice="cancel">Cancel</button>
        </div>
    </div>

    <script>
        (function () {
            const requestedMode = new URLSearchParams(window.location.search).get('print');
            if (requestedMode === 'mono') document.body.classList.add('print-mono');
        })();

        function printInvoice(mode) {
            document.body.classList.toggle('print-mono', mode === 'mono');
            document.body.dataset.printMode = mode;
            window.setTimeout(function () {
                window.print();
            }, 60);
        }

        window.addEventListener('afterprint', function () {
            document.body.classList.remove('print-mono');
            delete document.body.dataset.printMode;
        });

        let activeWhatsAppButton = null;
        const waChoiceModal = document.getElementById('wa-choice-modal');

        function closeWhatsAppChoice() {
            activeWhatsAppButton = null;
            if (waChoiceModal) waChoiceModal.hidden = true;
        }

        function normalizeWhatsAppNumber(phone) {
            let digits = String(phone || '').replace(/\D+/g, '');
            if (digits.startsWith('0') && digits.length === 11) digits = '88' + digits;
            return digits;
        }

        function invoiceMessage(button, mode, includeLink = true) {
            const modeLabel = mode === 'mono' ? 'black & white' : 'color';
            const separator = button.dataset.waUrl.includes('?') ? '&' : '?';
            const invoiceUrl = button.dataset.waUrl + separator + 'print=' + mode;
            const linkLine = includeLink ? '\n\nOpen invoice: ' + invoiceUrl : '';
            return 'Hello ' + (button.dataset.waName || 'there') + ',\n\nYour ' + modeLabel + ' invoice for order #' + button.dataset.waOrder + ' is ready.' + linkLine + '\n\nThank you for shopping with us!';
        }

        function openWhatsAppLink(button) {
            const phone = normalizeWhatsAppNumber(button.dataset.waPhone);
            const mode = button.dataset.waInvoice === 'mono' ? 'mono' : 'color';
            const message = invoiceMessage(button, mode) + '\n\nCustomer WhatsApp: +' + phone;
            window.open('https://wa.me/?text=' + encodeURIComponent(message), '_blank', 'noopener');
            closeWhatsAppChoice();
        }

        async function sendInvoicePdf(button) {
            const mode = button.dataset.waInvoice === 'mono' ? 'mono' : 'color';
            const separator = button.dataset.waPdfUrl.includes('?') ? '&' : '?';
            const pdfUrl = button.dataset.waPdfUrl + separator + 'mode=' + mode;
            const fileName = 'Invoice_' + button.dataset.waOrder + '_' + mode + '.pdf';

            try {
                const response = await fetch(pdfUrl, {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/pdf',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!response.ok) throw new Error('PDF request failed');

                const blob = await response.blob();
                const file = new File([blob], fileName, { type: 'application/pdf' });
                const canShareFile = typeof navigator.share === 'function'
                    && (!navigator.canShare || navigator.canShare({ files: [file] }));

                if (canShareFile) {
                    await navigator.share({
                        title: 'Invoice #' + button.dataset.waOrder,
                        text: invoiceMessage(button, mode, false),
                        files: [file],
                    });
                    closeWhatsAppChoice();
                    return;
                }

                const download = document.createElement('a');
                download.href = URL.createObjectURL(blob);
                download.download = fileName;
                document.body.appendChild(download);
                download.click();
                download.remove();
                URL.revokeObjectURL(download.href);

                const fallbackMessage = invoiceMessage(button, mode, false) + '\n\nThe PDF has been downloaded. Please attach it to this chat before sending.';
                window.open('https://wa.me/?text=' + encodeURIComponent(fallbackMessage), '_blank', 'noopener');
                closeWhatsAppChoice();
            } catch (error) {
                if (error?.name !== 'AbortError') {
                    window.alert('Could not prepare the invoice PDF. Please try again or send the invoice link.');
                }
            }
        }

        document.querySelectorAll('[data-wa-invoice]').forEach(function (button) {
            button.addEventListener('click', function () {
                const phone = normalizeWhatsAppNumber(button.dataset.waPhone);
                if (!phone) {
                    window.alert('This order does not have a valid WhatsApp number.');
                    return;
                }
                activeWhatsAppButton = button;
                waChoiceModal.hidden = false;
            });
        });

        waChoiceModal?.addEventListener('click', function (event) {
            if (event.target === waChoiceModal) closeWhatsAppChoice();
        });

        document.querySelectorAll('[data-wa-choice]').forEach(function (choice) {
            choice.addEventListener('click', function () {
                if (choice.dataset.waChoice === 'cancel') {
                    closeWhatsAppChoice();
                } else if (activeWhatsAppButton && choice.dataset.waChoice === 'link') {
                    openWhatsAppLink(activeWhatsAppButton);
                } else if (activeWhatsAppButton && choice.dataset.waChoice === 'pdf') {
                    sendInvoicePdf(activeWhatsAppButton);
                }
            });
        });
    </script>
    @endif
</body>
</html>
