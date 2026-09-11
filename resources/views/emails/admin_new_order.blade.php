<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;background:#f3f4f6;font-family:'Mulish',Arial,Helvetica,sans-serif;color:#1f2430;">
  <div style="max-width:560px;margin:0 auto;padding:32px 16px;">

    <div style="background:#1e293b;color:#fff;padding:20px 24px;border-radius:12px 12px 0 0;">
      <table role="presentation" cellpadding="0" cellspacing="0">
        <tr>
          <td style="vertical-align:middle;padding-right:12px;">
            <img src="{{ logo_url() }}" alt="" width="32" height="32" style="display:block;border-radius:8px;" />
          </td>
          <td style="vertical-align:middle;">
            <h1 style="margin:0;font-size:18px;font-weight:800;">{{ $siteName }} &mdash; New Order Alert</h1>
          </td>
        </tr>
      </table>
    </div>

    <div style="background:#fff;padding:28px 24px;border-radius:0 0 12px 12px;border:1px solid #e5e7eb;border-top:0;">
      <div style="background:#fff4ec;border:1px solid #fed7aa;border-radius:10px;padding:14px 20px;margin-bottom:20px;">
        <p style="margin:0;font-size:13px;color:#92400e;text-transform:uppercase;letter-spacing:0.05em;">New Order Received</p>
        <p style="margin:4px 0 0;font-size:22px;font-weight:800;color:#f15a24;">{{ $order->order_number }}</p>
      </div>

      <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin-bottom:20px;">
        <tr>
          <td style="padding:5px 0;font-size:13px;color:#6b7280;">Customer</td>
          <td style="padding:5px 0;text-align:right;font-size:13px;font-weight:600;">{{ $order->customer_name }}</td>
        </tr>
        <tr>
          <td style="padding:5px 0;font-size:13px;color:#6b7280;">Phone</td>
          <td style="padding:5px 0;text-align:right;font-size:13px;">{{ $order->customer_phone }}</td>
        </tr>
        @if ($order->customer_email)
        <tr>
          <td style="padding:5px 0;font-size:13px;color:#6b7280;">Email</td>
          <td style="padding:5px 0;text-align:right;font-size:13px;">{{ $order->customer_email }}</td>
        </tr>
        @endif
        <tr>
          <td style="padding:5px 0;font-size:13px;color:#6b7280;">Address</td>
          <td style="padding:5px 0;text-align:right;font-size:13px;">{{ $order->shipping_address }}, {{ $order->city }}</td>
        </tr>
        <tr>
          <td style="padding:5px 0;font-size:13px;color:#6b7280;">Payment</td>
          <td style="padding:5px 0;text-align:right;font-size:13px;">{{ $order->paymentMethodLabel() }}</td>
        </tr>
        @if ($order->payment_txn_id)
        <tr>
          <td style="padding:5px 0;font-size:13px;color:#6b7280;">TXN ID</td>
          <td style="padding:5px 0;text-align:right;font-size:13px;font-family:monospace;">{{ $order->payment_txn_id }}</td>
        </tr>
        @endif
        <tr style="border-top:2px solid #f15a24;">
          <td style="padding:8px 0 4px;font-size:15px;font-weight:700;">Order Total</td>
          <td style="padding:8px 0 4px;text-align:right;font-size:15px;font-weight:700;color:#f15a24;">{{ money($order->total) }}</td>
        </tr>
      </table>

      <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin-bottom:20px;">
        <thead>
          <tr style="background:#f9fafb;">
            <th style="padding:8px 10px;text-align:left;font-size:12px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;">Product</th>
            <th style="padding:8px 10px;text-align:center;font-size:12px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;">Qty</th>
            <th style="padding:8px 10px;text-align:right;font-size:12px;color:#6b7280;font-weight:600;border-bottom:1px solid #e5e7eb;">Total</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($order->items as $item)
          <tr>
            <td style="padding:10px;font-size:13px;border-bottom:1px solid #f3f4f6;">
              {{ $item->product_name }}
              @if ($item->variant)
                <span style="color:#9ca3af;font-size:11px;"> &mdash; {{ $item->variant }}</span>
              @endif
            </td>
            <td style="padding:10px;text-align:center;font-size:13px;border-bottom:1px solid #f3f4f6;">{{ $item->quantity }}</td>
            <td style="padding:10px;text-align:right;font-size:13px;border-bottom:1px solid #f3f4f6;">{{ money($item->line_total) }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>

      <p style="margin:0;font-size:13px;color:#94a3b8;">
        Log in to the admin panel to review and process this order.
      </p>
    </div>

    <p style="text-align:center;font-size:12px;color:#94a3b8;margin-top:16px;">&copy; {{ date('Y') }} {{ $siteName }}</p>
  </div>
</body>
</html>
