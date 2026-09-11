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

        // Usernames are the primary admin credential. The email fallback keeps existing
        // staff accounts usable until each account has been assigned a username.
        $user = User::query()
            ->whereIn('role', User::STAFF_ROLES)
            ->where(function ($query) use ($login) {
                $query->where('username', $login)
                    ->orWhere('email', $login);
            })
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
