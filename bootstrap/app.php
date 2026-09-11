<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('store:backup')->dailyAt('02:00')->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Reverse-proxy trust: read from TRUSTED_PROXIES env var.
        // Use '*' only for shared hosting / Cloudflare.
        // For a direct VPS set TRUSTED_PROXIES=127.0.0.1
        // For Cloudflare set TRUSTED_PROXIES=CLOUDFLARE (or their IP ranges).
        $trustedProxies = env('TRUSTED_PROXIES', '127.0.0.1');
        $middleware->trustProxies(at: $trustedProxies);

        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // Exempt courier webhooks from CSRF verification
        $middleware->validateCsrfTokens(except: [
            '/webhooks/courier-status',
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
            'dropshipping.enabled' => \App\Http\Middleware\EnsureDropshippingEnabled::class,
            'testing.readonly' => \App\Http\Middleware\BlockMutationsInTestingMode::class,
        ]);

        // Guests hitting an auth-protected storefront page go to the customer login.
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Admin settings/fetch calls send Accept: application/json but are not under /api.
        // Without this, validation/upload errors return HTML and the UI shows "Unexpected token '<'".
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => ! $request->header('X-Inertia') && (
                $request->expectsJson()
                || ($request->ajax() && ! $request->header('X-Inertia'))
                || $request->is('api/*')
            ),
        );
    })->create();
