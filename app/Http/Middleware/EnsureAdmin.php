<?php

namespace App\Http\Middleware;

use App\Support\AdminPermissions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check() || ! Auth::user()->isAdmin()) {
            return redirect()->route('admin.login')->withErrors([
                'email' => 'Please sign in with an administrator account.',
            ]);
        }

        $route = $request->route();
        $permissions = $route ? AdminPermissions::forRoute($route, $request) : null;
        // Every protected admin action must have an explicit catalog entry.
        abort_if($permissions === null, 403, 'This admin action has no permission mapping.');

        if (! $request->user()->isSuperAdmin()) {
            abort_if(class_basename($route->getControllerClass()) === 'RoleController', 403, 'Only Super Admin can manage roles and permissions.');
            abort_unless($permissions === [] || collect($permissions)->contains(fn ($permission) => $request->user()->hasPermission($permission)), 403);
            if ($route->getControllerClass() === \App\Http\Controllers\Admin\OrderSourcingController::class) {
                abort_unless($request->user()->hasPermission('inventory.edit'), 403);
            }
            if ($route->getControllerClass() === \App\Http\Controllers\Admin\AbandonedCheckoutController::class && $route->getActionMethod() === 'createOrder') {
                abort_unless($request->user()->hasPermission('orders.add'), 403);
            }
        }

        return $next($request);
    }
}
