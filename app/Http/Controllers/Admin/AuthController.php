<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check() && Auth::user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return Inertia::render('Admin/Auth/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:180'],
            'password' => ['required'],
        ]);

        $login = trim($credentials['username']);

        // Admin authentication is intentionally username-only. The email address is
        // used internally by Laravel's provider after the matching username is found;
        // it is never accepted as admin login input.
        $user = User::query()
            // Keep legacy cPanel accounts usable when role values were
            // entered with different casing or accidental whitespace.
            ->whereRaw('LOWER(TRIM(role)) IN ('.implode(',', array_fill(0, count(User::STAFF_ROLES), '?')).')', User::STAFF_ROLES)
            ->where('username', $login)
            ->first();

        $attempted = $user && Auth::attempt([
            'email'    => $user->email,
            'password' => $credentials['password'],
        ], $request->boolean('remember'));

        if (! $attempted) {
            return back()
                ->withErrors(['username' => 'These credentials do not match our records.'])
                ->onlyInput('username');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
