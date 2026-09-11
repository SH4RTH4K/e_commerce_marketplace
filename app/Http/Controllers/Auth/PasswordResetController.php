<?php

namespace App\Http\Controllers\Auth;

use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class PasswordResetController extends Controller
{
    public function showRequest()
    {
        return Inertia::render('Storefront/Auth/ForgotPassword');
    }

    public function sendCode(Request $request, OtpService $otp)
    {
        $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $request->input('email'))->first();
        if (! $user) {
            return back()->withErrors(['email' => 'No account found with that email address.'])->onlyInput('email');
        }

        $code = $otp->send($user->email, 'reset', $user->name);
        $request->session()->put('otp_email', $user->email);
        $request->session()->put('otp_purpose', 'reset');

        return redirect()->route('verify')
            ->with('status', 'We sent a password reset code to your email.')
            ->with('dev_otp', app()->environment('local') ? $code : null);
    }

    public function showReset(Request $request)
    {
        if (! $request->session()->has('reset_verified')) {
            return redirect()->route('password.request');
        }

        return Inertia::render('Storefront/Auth/ResetPassword', [
            'email' => $request->session()->get('reset_verified'),
        ]);
    }

    public function reset(Request $request)
    {
        $email = $request->session()->get('reset_verified');
        if (! $email) {
            return redirect()->route('password.request');
        }

        $request->validate(['password' => ['required', 'confirmed', Password::min(8)]]);

        $user = User::where('email', $email)->firstOrFail();
        $user->update(['password' => $request->input('password')]); // hashed by cast
        $request->session()->forget('reset_verified');

        Auth::login($user);

        return redirect()->route('account')->with('status', 'Your password has been updated.');
    }
}
