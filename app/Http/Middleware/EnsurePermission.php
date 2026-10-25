<?php

namespace App\Http\Middleware;

use App\Support\AdminPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        // Existing route declarations use broad legacy groups. EnsureAdmin now
        // authorizes their specific operation, so they must not block a narrower grant.
        if ($request->route() && in_array('admin', $request->route()->gatherMiddleware(), true)
            && AdminPermissions::forRoute($request->route(), $request) !== null) {
            return $next($request);
        }

        abort_unless($user && $user->isAdmin() && $user->hasPermission($permission), 403);

        return $next($request);
    }
}
