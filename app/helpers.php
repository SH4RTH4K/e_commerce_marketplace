<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    /** Read a value from the settings table. */
    function setting(string $key, $default = null)
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('testing_mode')) {
    /**
     * Whether admin write actions are locked (TESTING_MODE in Site 3/shop/.env).
     * Reads live env / .env file so toggling works without config:cache surprises.
     */
    function testing_mode(): bool
    {
        return once(function () {
            foreach ([$_ENV, $_SERVER] as $bag) {
                if (array_key_exists('TESTING_MODE', $bag) && $bag['TESTING_MODE'] !== '' && $bag['TESTING_MODE'] !== null) {
                    return filter_var($bag['TESTING_MODE'], FILTER_VALIDATE_BOOLEAN);
                }
            }

            $path = base_path('.env');
            if (is_readable($path)) {
                $lines = file($path, FILE_IGNORE_NEW_LINES);
                if ($lines !== false) {
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if ($line === '' || str_starts_with($line, '#')) {
                            continue;
                        }
                        if (! preg_match('/^TESTING_MODE\\s*=\\s*(.*)$/i', $line, $m)) {
                            continue;
                        }
                        $raw = trim($m[1], " \t\"'");

                        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
                    }
                }
            }

            return filter_var(config('app.testing_mode', false), FILTER_VALIDATE_BOOLEAN);
        });
    }
}

if (! function_exists('money')) {
    /** Format an amount using the store currency symbol from Settings. */
        function money($amount): string
    {
        $symbol = trim((string) setting('currency_symbol', "\u{09F3}"));
        if ($symbol === '') {
            $symbol = "\u{09F3}";
        }

        return $symbol . number_format((float) $amount, 0);
    }
}

if (! function_exists('currency_symbol')) {
    function currency_symbol(): string
    {
        $symbol = trim((string) setting('currency_symbol', "\u{09F3}"));

        return $symbol !== '' ? $symbol : "\u{09F3}";
    }
}

if (! function_exists('shipping_zone_label')) {
    function shipping_zone_label(string $zone): string
    {
        return match ($zone) {
            'inside_dhaka' => (string) setting('shipping_inside_label', 'Inside Dhaka'),
            'outside_dhaka' => (string) setting('shipping_outside_label', 'Outside Dhaka'),
            default => $zone,
        };
    }
}

if (! function_exists('configure_mail_from_settings')) {
    /**
     * Apply the admin-managed mail configuration at runtime and return the active mailer.
     * DB settings take priority; falls back to .env / config values when a DB field is empty.
     * Falls back to the "log" mailer when SMTP host is completely missing, so the OTP flow
     * keeps working even before real credentials are entered.
     */
    function configure_mail_from_settings(): string
    {
        $mailer = setting('mail_mailer', 'log') ?: 'log';

        if ($mailer === 'smtp') {
            $encryption = setting('mail_encryption', 'tls') ?: 'tls';

            // DB value wins; fall back to .env / config when DB field is blank.
            $host     = setting('mail_host')     ?: config('mail.mailers.smtp.host', '');
            $port     = (int)(setting('mail_port')     ?: config('mail.mailers.smtp.port', 587));
            $username = setting('mail_username') ?: config('mail.mailers.smtp.username') ?: null;
            $password = setting('mail_password') ?: config('mail.mailers.smtp.password') ?: null;

            // If we still have no host, fall back to log so mail doesn't crash silently.
            if (empty($host)) {
                config(['mail.default' => 'log']);
                return 'log';
            }

            config([
                'mail.default'                 => 'smtp',
                'mail.mailers.smtp.host'       => $host,
                'mail.mailers.smtp.port'       => $port,
                'mail.mailers.smtp.username'   => $username,
                'mail.mailers.smtp.password'   => $password,
                'mail.mailers.smtp.encryption' => ($encryption === 'none') ? null : $encryption,
            ]);
        } else {
            config(['mail.default' => 'log']);
            $mailer = 'log';
        }

        config([
            'mail.from.address' => setting('mail_from_address') ?: config('mail.from.address'),
            'mail.from.name'    => setting('mail_from_name') ?: site_name(),
        ]);

        return $mailer;
    }
}

if (! function_exists('otp_enabled')) {
    /** Whether email OTP verification is switched on in the admin panel. */
    function otp_enabled(): bool
    {
        return (string) setting('otp_enabled', '1') === '1';
    }
}

if (! function_exists('send_order_email')) {
    /**
     * Send a transactional order email to the customer.
     * Silently logs and swallows any mail transport errors so the order flow is never interrupted.
     *
     * @param  \App\Models\Order  $order   The order (must have items loaded)
     * @param  string             $view    Blade view, e.g. 'emails.order_placed'
     * @param  string             $subject Email subject line
     */
    function send_order_email(\App\Models\Order $order, string $view, string $subject): void
    {
        $email = trim((string) ($order->customer_email ?? ''));
        if ($email === '') {
            return; // Customer did not provide an email — nothing to send
        }

        $siteName = site_name();

        try {
            configure_mail_from_settings();
            \Illuminate\Support\Facades\Mail::send(
                $view,
                ['order' => $order, 'siteName' => $siteName],
                function ($message) use ($email, $subject) {
                    $message->to($email)->subject($subject);
                }
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Order email ({$view}) failed: " . $e->getMessage());
        }
    }
}

if (! function_exists('send_admin_order_alert')) {
    /**
     * Send a new-order notification to the store admin (contact_email from Brand settings).
     * Silently logs and swallows any mail transport errors.
     *
     * @param  \App\Models\Order  $order  The order (must have items loaded)
     */
    function send_admin_order_alert(\App\Models\Order $order): void
    {
        $adminEmail = trim((string) setting('contact_email', ''));
        if ($adminEmail === '') {
            return; // No admin email configured — skip
        }

        $siteName = site_name();

        try {
            configure_mail_from_settings();
            \Illuminate\Support\Facades\Mail::send(
                'emails.admin_new_order',
                ['order' => $order, 'siteName' => $siteName],
                function ($message) use ($adminEmail, $siteName, $order) {
                    $message->to($adminEmail)
                            ->subject("[New Order] {$order->order_number} — {$siteName}");
                }
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Admin order alert failed: " . $e->getMessage());
        }
    }
}

if (! function_exists('site_name')) {
    /** Canonical storefront / admin site name (Settings → site_name). */
    function site_name(): string
    {
        $name = trim((string) setting('site_name', config('app.name', 'SHARTHAK')));

        return $name !== '' ? $name : 'SHARTHAK';
    }
}

if (! function_exists('branding_asset_url')) {
    /** Resolve a branding path (uploads/… or legacy storage) to a public URL. */
    function branding_asset_url(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $relative = ltrim($path, '/');
        if (str_starts_with($relative, 'uploads/')) {
            return asset($relative);
        }

        return asset('storage/' . $relative);
    }
}
if (! function_exists('branding_asset_is_usable')) {
    /** True when a stored branding path can actually be served. */
    function branding_asset_is_usable(string $path): bool
    {
        $path = trim($path);
        if ($path === '') {
            return false;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return true;
        }

        $relative = ltrim($path, '/');
        if (str_starts_with($relative, 'uploads/')) {
            return is_file(public_path($relative));
        }

        return is_file(public_path('storage/' . $relative))
            || is_file(storage_path('app/public/' . $relative));
    }
}

if (! function_exists('branding_cache_buster')) {
    /** Query string so logo/favicon updates are visible after seed/reset. */
    function branding_cache_buster(?string $path = null): string
    {
        if ($path) {
            $path = trim($path);
            if ($path !== '' && ! str_starts_with($path, 'http://') && ! str_starts_with($path, 'https://')) {
                $relative = ltrim($path, '/');
                $full = str_starts_with($relative, 'uploads/')
                    ? public_path($relative)
                    : public_path('storage/' . $relative);
                if (is_file($full)) {
                    return '?v=' . filemtime($full);
                }
            }
        }

        return '?v=' . time();
    }
}
if (! function_exists('favicon_url')) {
    /** The site favicon — custom upload if set and present, otherwise the bundled theme icon. */
    function favicon_url(): string
    {
        $f = trim((string) setting('favicon', ''));
        if ($f !== '' && branding_asset_is_usable($f)) {
            return branding_asset_url($f) . branding_cache_buster($f);
        }

        $default = public_path('theme/favicon.svg');
        $url = asset('theme/favicon.svg');

        return $url . (is_file($default) ? ('?v=' . filemtime($default)) : '?v=1');
    }
}

if (! function_exists('logo_url')) {
    /**
     * The site logo URL.
     * Custom admin upload wins when the file exists; otherwise the bundled theme mark.
     */
    function logo_url(string $variant = 'default'): string
    {
        $l = trim((string) setting('logo', ''));
        if ($l !== '' && branding_asset_is_usable($l)) {
            return branding_asset_url($l) . branding_cache_buster($l);
        }

        $default = public_path('theme/logo.svg');
        if (is_file($default)) {
            return asset('theme/logo.svg') . '?v=' . filemtime($default);
        }

        $fav = public_path('theme/favicon.svg');

        return asset('theme/favicon.svg') . (is_file($fav) ? ('?v=' . filemtime($fav)) : '?v=1');
    }
}

if (! function_exists('has_custom_logo')) {
    /** Whether an admin-uploaded logo is set and the file still exists. */
    function has_custom_logo(): bool
    {
        $l = trim((string) setting('logo', ''));

        return $l !== '' && branding_asset_is_usable($l);
    }
}

if (! function_exists('tracking_gtm_id')) {
    /** Valid Google Tag Manager container ID (GTM-XXXX), or null when unset/invalid. */
    function tracking_gtm_id(): ?string
    {
        $id = strtoupper(trim((string) setting('tracking_gtm_id', '')));

        return preg_match('/^GTM-[A-Z0-9]+$/', $id) ? $id : null;
    }
}

if (! function_exists('tracking_ga4_id')) {
    /** Valid GA4 measurement ID (G-XXXX), or null when unset/invalid. */
    function tracking_ga4_id(): ?string
    {
        $id = strtoupper(trim((string) setting('tracking_ga4_id', '')));

        return preg_match('/^G-[A-Z0-9]+$/', $id) ? $id : null;
    }
}

if (! function_exists('tracking_meta_pixel_id')) {
    /** Valid Meta (Facebook) Pixel ID (numeric), or null when unset/invalid. */
    function tracking_meta_pixel_id(): ?string
    {
        $id = trim((string) setting('tracking_meta_pixel_id', ''));

        return preg_match('/^\\d+$/', $id) ? $id : null;
    }
}

if (! function_exists('tracking_any_enabled')) {
    /** Whether any storefront tracking tag is configured. */
    function tracking_any_enabled(): bool
    {
        return tracking_gtm_id() || tracking_ga4_id() || tracking_meta_pixel_id();
    }
}

if (! function_exists('image_url')) {
    /**
     * Resolve an image reference to a usable URL.
     * - Full URLs (http…) are returned as-is.
     * - Stored paths resolve against the public storage disk.
     * - Missing values fall back to a neutral local SVG placeholder (not stock photos).
     */
    function image_url(?string $path, string $seed = 'SHARTHAK'): string
    {
        if ($path) {
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return 'https://wsrv.nl/?url=' . rawurlencode($path) . '&w=1200&q=75&output=webp';
            }
            // Direct public uploads (e.g. uploads/products/abc.jpg)
            if (str_starts_with($path, '/uploads/') || str_starts_with($path, 'uploads/')) {
                return asset(ltrim($path, '/'));
            }
            return asset('storage/' . ltrim($path, '/'));
        }

        $label = e(mb_substr(trim($seed) !== '' ? $seed : 'No image', 0, 28));
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="600" height="720" viewBox="0 0 600 720">
  <rect width="600" height="720" fill="#f5f5f4"/>
  <rect x="40" y="40" width="520" height="640" rx="24" fill="#e7e5e4"/>
  <text x="300" y="370" text-anchor="middle" fill="#a8a29e" font-family="system-ui,sans-serif" font-size="26">{$label}</text>
</svg>
SVG;

        return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
    }
}
