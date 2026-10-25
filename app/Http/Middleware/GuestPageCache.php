<?php

namespace App\Http\Middleware;

use App\Services\StorefrontPageCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GuestPageCache
{
    public function __construct(private readonly StorefrontPageCache $pages) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldInspect($request)) {
            $response = $next($request);

            if ($this->isPublicHtmlRequest($request) && ! $this->hasShareableGuestState($request)) {
                $this->markPrivate($response);
            }

            return $response;
        }

        $key = $this->pages->key($request);
        try {
            $cached = $this->pages->store()->get($key);
        } catch (\Throwable) {
            return $next($request);
        }

        if (is_array($cached) && isset($cached['content'], $cached['status'], $cached['headers'])) {
            $response = new Response(
                $this->hydrateCsrfToken((string) $cached['content'], $request),
                (int) $cached['status'],
                $cached['headers'],
            );
            $this->markCached($response, 'HIT');

            return $response;
        }

        $response = $next($request);

        if (! $this->isCacheable($request, $response)) {
            if ($this->pages->isPublicRoute($request)) {
                $this->markPrivate($response);
            }

            return $response;
        }

        $headers = $response->headers->all();
        unset($headers['set-cookie']);
        unset($headers['content-length']);

        $content = (string) $response->getContent();
        $cachedContent = $this->dehydrateCsrfToken($content);

        // Never persist a session-bound token if the response format changes
        // and the placeholder could not be produced.
        if (str_contains($content, 'csrf-token') && ! str_contains($cachedContent, StorefrontPageCache::CSRF_PLACEHOLDER)) {
            $this->markPrivate($response);

            return $response;
        }

        try {
            $this->pages->store()->put($key, [
                'content' => $cachedContent,
                'status' => $response->getStatusCode(),
                'headers' => $headers,
            ], $this->pages->ttl());
        } catch (\Throwable) {
            $this->markPrivate($response);

            return $response;
        }

        $this->markCached($response, 'MISS');

        return $response;
    }

    private function shouldInspect(Request $request): bool
    {
        if (! $this->isPublicHtmlRequest($request)) {
            return false;
        }

        return $this->hasShareableGuestState($request);
    }

    private function isPublicHtmlRequest(Request $request): bool
    {
        return config('storefront.page_cache.enabled', true)
            && $request->isMethod('GET')
            && $this->pages->isPublicRoute($request)
            && ! $request->headers->has('X-Inertia')
            && ! $request->expectsJson()
            && ! $request->ajax()
            && ! $request->isPrecognitive()
            && str_contains(strtolower((string) $request->header('Accept')), 'text/html');
    }

    private function isCacheable(Request $request, Response $response): bool
    {
        if ($request->user() || auth()->check()
            || ! $response->isSuccessful()
            || ! str_contains(strtolower((string) $response->headers->get('Content-Type')), 'text/html')
            || str_contains(strtolower((string) $response->headers->get('Cache-Control')), 'no-store')) {
            return false;
        }

        // _token and _previous are framework bookkeeping. Every other
        // session key can affect the rendered body (cart, flash, OTP, etc.).
        return $this->hasShareableGuestState($request);
    }

    private function hasShareableGuestState(Request $request): bool
    {
        if ($request->user() || auth()->check()) {
            return false;
        }

        $session = $request->session()->all();
        unset($session['_token'], $session['_previous']);

        if (isset($session['_flash'])) {
            if (($session['_flash']['old'] ?? []) !== [] || ($session['_flash']['new'] ?? []) !== []) {
                return false;
            }

            unset($session['_flash']);
        }

        return $session === [];
    }

    private function dehydrateCsrfToken(string $content): string
    {
        return preg_replace(
            '/(<meta\s+name=["\']csrf-token["\']\s+content=["\'])[^"\']*(["\'])/i',
            '$1'.StorefrontPageCache::CSRF_PLACEHOLDER.'$2',
            $content,
        ) ?? $content;
    }

    private function hydrateCsrfToken(string $content, Request $request): string
    {
        return str_replace(StorefrontPageCache::CSRF_PLACEHOLDER, $request->session()->token(), $content);
    }

    private function markCached(Response $response, string $status): void
    {
        $ttl = $this->pages->ttl();
        $response->headers->set('Cache-Control', "public, max-age={$ttl}, s-maxage={$ttl}, stale-while-revalidate=60");
        $response->headers->set('X-LiteSpeed-Cache-Control', "public,max-age={$ttl}");
        $response->headers->set('Vary', 'Cookie, Accept-Encoding');
        $response->headers->set('X-Page-Cache', $status);
    }

    private function markPrivate(Response $response): void
    {
        $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('X-Page-Cache', 'BYPASS');
    }
}
