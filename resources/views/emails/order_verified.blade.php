<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;background:#f3f4f6;font-family:'Mulish',Arial,Helvetica,sans-serif;color:#1f2430;">
  <div style="max-width:560px;margin:0 auto;padding:32px 16px;">

    <div style="background:#f15a24;color:#fff;padding:20px 24px;border-radius:12px 12px 0 0;">
      <table role="presentation" cellpadding="0" cellspacing="0">
        <tr>
          <td style="vertical-align:middle;padding-right:12px;">
            <img src="{{ logo_url() }}" alt="" width="32" height="32" style="display:block;border-radius:8px;" />
          </td>
          <td style="vertical-align:middle;">
            <h1 style="margin:0;font-size:22px;font-weight:800;letter-spacing:-0.02em;">{{ $siteName }}</h1>
          </td>
        </tr>
      </table>
    </div>

    <div style="background:#fff;padding:28px 24px;border-radius:0 0 12px 12px;border:1px solid #e5e7eb;border-top:0;">
      <p style="margin:0 0 8px;font-size:15px;">Hi <strong>{{ $order->customer_name }}</strong>,</p>
      <p style="margin:0 0 20px;font-size:15px;color:#64748b;">
        Great news! Your payment has been verified and your order is now <strong style="color:#16a34a;">confirmed</strong>.
      </p>

      <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px 20px;margin-bottom:20px;">
        <p style="margin:0;font-size:13px;color:#166534;text-transform:uppercase;letter-spacing:0.05em;">Order Confirmed</p>
        <p style="margin:4px 0 0;font-size:22px;font-weight:800;color:#16a34a;letter-spacing:0.04em;">{{ $order->order_number }}</p>
      </div>

      <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin-bottom:24px;">
        <tr>
          <td style="padding:6px 0;font-size:13px;color:#6b7280;">Payment Method</td>
          <td style="padding:6px 0;text-align:right;font-size:13px;font-weight:600;">{{ $order->paymentMethodLabel() }}</td>
        </tr>
        <tr>
          <td style="padding:6px 0;font-size:13px;color:#6b7280;">Order Total</td>
          <td style="padding:6px 0;text-align:right;font-size:13px;font-weight:700;color:#f15a24;">{{ money($order->total) }}</td>
        </tr>
        <tr>
          <td style="padding:6px 0;font-size:13px;color:#6b7280;">Delivery Address</td>
          <td style="padding:6px 0;text-align:right;font-size:13px;">{{ $order->shipping_address }}, {{ $order->city }}</td>
        </tr>
      </table>

      <div style="background:#f9fafb;border-radius:8px;padding:14px 16px;margin-bottom:20px;">
        <p style="margin:0;font-size:14px;color:#374151;">
          We are now preparing your order. You will receive another update once it has been shipped.
          Thank you for shopping with <strong>{{ $siteName }}</strong>!
        </p>
      </div>

      <p style="margin:0;font-size:13px;color:#94a3b8;">
        If you have any questions about your order, please contact our support team.
      </p>
    </div>

    <p style="text-align:center;font-size:12px;color:#94a3b8;margin-top:16px;">&copy; {{ date('Y') }} {{ $siteName }}</p>
  </div>
</body>
</html>
