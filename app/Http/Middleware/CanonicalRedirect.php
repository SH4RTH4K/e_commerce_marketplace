<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanonicalRedirect
{
    public function handle(Request $request, Closure $next): Response
    {
        $targetScheme = $this->targetScheme($request);
        $targetHost = $this->targetHost($request);

        if ($targetScheme || $targetHost) {
            $scheme = $targetScheme ?: ($this->isSecure($request) ? 'https' : 'http');
            $host = $targetHost ?: $request->getHost();
            $url = $scheme . '://' . $this->hostWithPort($host, $scheme, $request) . $request->getRequestUri();
            $status = in_array($request->getMethod(), ['GET', 'HEAD'], true) ? 301 : 308;

            return redirect()->away($url, $status);
        }

        return $next($request);
    }

    private function targetScheme(Request $request): ?string
    {
        $enabled = setting('seo_https_redirect_enabled', app()->environment('production') ? '1' : '0') === '1';

        return $enabled && ! $this->isSecure($request) ? 'https' : null;
    }

    private function targetHost(Request $request): ?string
    {
        $configuredPreference = trim((string) setting('seo_canonical_host', ''));
        $preference = $configuredPreference !== ''
            ? $configuredPreference
            : $this->defaultHostPreference($request);

        if (! in_array($preference, ['www', 'non_www'], true)) {
            return null;
        }

        $host = strtolower($request->getHost());
        if (! $this->canCanonicalizeHost($host)) {
            return null;
        }

        if ($preference === 'www' && ! str_starts_with($host, 'www.')) {
            return 'www.' . $host;
        }

        if ($preference === 'non_www' && str_starts_with($host, 'www.')) {
            return substr($host, 4);
        }

        return null;
    }

    private function defaultHostPreference(Request $request): string
    {
        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($this->canCanonicalizeHost($host)) {
            return str_starts_with($host, 'www.') ? 'www' : 'non_www';
        }

        return $this->canCanonicalizeHost(strtolower($request->getHost())) ? 'non_www' : 'none';
    }

    private function canCanonicalizeHost(string $host): bool
    {
        return $host !== ''
            && $host !== 'localhost'
            && str_contains($host, '.')
            && ! filter_var($host, FILTER_VALIDATE_IP);
    }

    private function isSecure(Request $request): bool
    {
        return $request->isSecure()
            || strtolower((string) $request->headers->get('X-Forwarded-Proto')) === 'https';
    }

    private function hostWithPort(string $host, string $scheme, Request $request): string
    {
        $port = $request->getPort();
        if (($scheme === 'https' && in_array($port, [80, 443], true)) || ($scheme === 'http' && in_array($port, [80, 443], true))) {
            return $host;
        }

        return $host . ':' . $port;
    }
}
