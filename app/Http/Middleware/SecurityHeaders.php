<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Clickjacking protection
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        // MIME-type sniffing protection
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // Referrer leak prevention
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Camera / mic / location off
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        // XSS protection (legacy browsers)
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        // No Flash / PDF cross-domain policy
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // Content Security Policy
        // In production, enforce strict CSP. In local development, relax to allow Vite HMR dev server.
        if (app()->environment('production')) {
            $csp = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' https://www.googletagmanager.com https://www.google-analytics.com https://connect.facebook.net https://static.hotjar.com",
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
            $response->headers->set('Content-Security-Policy', $csp);
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
