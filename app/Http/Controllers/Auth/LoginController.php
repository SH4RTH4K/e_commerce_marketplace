<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LoginController extends Controller
{
    public function show(Request $request)
    {
        $redirect = $request->query('redirect');
        if (is_string($redirect) && $redirect !== '') {
            // Only allow same-host redirects. Use parse_url() to compare the
            // host — str_starts_with(url('/')) is bypassable via //evil.com
            // or https://yourdomain.com.evil.com.
            $appHost    = parse_url(config('app.url'), PHP_URL_HOST);
            $targetHost = parse_url($redirect, PHP_URL_HOST);

            $isSafeRelative  = $targetHost === null && str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//');
            $isSameHost      = $targetHost !== null && strtolower($targetHost) === strtolower((string) $appHost);

            if ($isSafeRelative || $isSameHost) {
                $request->session()->put('url.intended', $redirect);
            }
        }

        return Inertia::render('Storefront/Auth/Login');
    }

    public function login(Request $request, OtpService $otp)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'These credentials do not match our records.'])->onlyInput('email');
        }

        $user = Auth::user();

        if (! $user->isActive()) {
            Auth::logout();

            return back()->withErrors(['email' => 'This customer account has been deactivated.'])->onlyInput('email');
        }

        // Admins who use the storefront login go straight to the panel.
        if ($user->isAdmin()) {
            $request->session()->regenerate();

            return redirect()->route('admin.dashboard');
        }

        // Unverified customers must confirm their email via OTP first.
        if (otp_enabled() && ! $user->hasVerifiedEmail()) {
            Auth::logout();
            $code = $otp->send($user->email, 'register', $user->name);
            $request->session()->put('otp_email', $user->email);
            $request->session()->put('otp_purpose', 'register');

            return redirect()->route('verify')
                ->with('status', 'Please verify your email to continue.')
                ->with('dev_otp', (app()->environment('local') && ! app()->isProduction()) ? $code : null);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('account'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'You have been signed out.');
    }
}
