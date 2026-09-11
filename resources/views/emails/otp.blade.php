<!DOCTYPE html>
<html>
<body style="margin:0;background:#f3f4f6;font-family:'Mulish',Arial,Helvetica,sans-serif;color:#1f2430;">
  <div style="max-width:520px;margin:0 auto;padding:32px 16px;">
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
      <p style="margin:0 0 12px;font-size:15px;">Hi{{ $name ? ' ' . $name : '' }},</p>
      <p style="margin:0 0 20px;font-size:15px;color:#64748b;">Use the verification code below to continue. It expires in {{ $minutes }} minutes.</p>
      <div style="text-align:center;margin:24px 0;">
        <span style="display:inline-block;font-size:34px;font-weight:bold;letter-spacing:10px;background:#fff4ec;color:#f15a24;padding:14px 24px;border-radius:10px;">{{ $code }}</span>
      </div>
      <p style="margin:0;font-size:13px;color:#94a3b8;">If you didn't request this, you can safely ignore this email.</p>
    </div>
    <p style="text-align:center;font-size:12px;color:#94a3b8;margin-top:16px;">&copy; {{ date('Y') }} {{ $siteName }}</p>
  </div>
</body>
</html>
