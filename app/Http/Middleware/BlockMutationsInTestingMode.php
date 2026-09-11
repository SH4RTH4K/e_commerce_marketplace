<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockMutationsInTestingMode
{
    private const MESSAGE = 'Aita testing mode ase, tumi purchase korar por aigula delete edit shob kisu korte parbe.';

    public function handle(Request $request, Closure $next): Response
    {
        if (! testing_mode()) {
            return $next($request);
        }

        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        // Logout must remain available while browsing the demo admin.
        if ($request->routeIs('admin.logout')) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => self::MESSAGE], 403);
        }

        return back()->withErrors(['testing_mode' => self::MESSAGE]);
    }
}
