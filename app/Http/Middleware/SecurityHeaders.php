<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);
        app(Vite::class)->useCspNonce($nonce);

        /** @var Response $response */
        $response = $next($request);

        if (app(\App\Services\StorefrontSeoService::class)->isPrivate($request)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        // Clickjacking protection
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        // MIME-type sniffing protection
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // Referrer leak prevention
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Camera / mic / location off
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        // No Flash / PDF cross-domain policy
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // Content Security Policy
        // In production, enforce strict CSP. In local development, relax to allow Vite HMR dev server.
        if (config('app.env') === 'production') {
            $csp = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}' https://www.googletagmanager.com https://www.google-analytics.com https://connect.facebook.net https://www.facebook.com/signals/iwl.js https://static.hotjar.com",
                // Style nonce migration is deferred: legacy storefront/admin markup still emits style attributes and preview HTML.
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "font-src 'self' https://fonts.gstatic.com data:",
                "img-src 'self' data: blob: https:",
                "connect-src 'self' https://www.google-analytics.com https://analytics.google.com https://www.facebook.com https://stats.g.doubleclick.net",
                "frame-src 'self' https://www.youtube.com https://www.facebook.com",
                "frame-ancestors 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
            ]);
            $response->headers->set(
                config('security.csp_report_only', false)
                    ? 'Content-Security-Policy-Report-Only'
                    : 'Content-Security-Policy',
                $csp,
            );
        }

        // HSTS — force HTTPS for 1 year (only in production)
        // preload is included so browsers can cache the HSTS policy before the first visit.
        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }
        // Hide PHP version
        header_remove('X-Powered-By');

        return $response;
    }
}
