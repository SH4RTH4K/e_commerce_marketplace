<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class OtpController extends Controller
{
    public function show(Request $request)
    {
        if (! $request->session()->has('otp_email')) {
            return redirect()->route('login');
        }

        return Inertia::render('Storefront/Auth/Verify', [
            'email'   => $request->session()->get('otp_email'),
            'purpose' => $request->session()->get('otp_purpose', 'register'),
        ]);
    }

    public function verify(Request $request, OtpService $otp)
    {
        $email   = $request->session()->get('otp_email');
        $purpose = $request->session()->get('otp_purpose', 'register');

        if (! $email) {
            return redirect()->route('login');
        }

        $request->validate(['code' => ['required', 'digits:6']]);

        if (! $otp->verify($email, $request->input('code'), $purpose)) {
            return back()->withErrors(['code' => 'That code is invalid or has expired.']);
        }

        $user = User::where('email', $email)->firstOrFail();
        $request->session()->forget(['otp_email', 'otp_purpose']);

        if ($purpose === 'reset') {
            $request->session()->put('reset_verified', $email);

            return redirect()->route('password.reset');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }
        Auth::login($user, true);

        return redirect()->route('account')->with('status', 'Your email has been verified.');
    }

    public function resend(Request $request, OtpService $otp)
    {
        $email   = $request->session()->get('otp_email');
        $purpose = $request->session()->get('otp_purpose', 'register');

        if (! $email) {
            return redirect()->route('login');
        }

        $code = $otp->send($email, $purpose);

        return back()
            ->with('status', 'A new code has been sent.')
            ->with('dev_otp', app()->environment('local') ? $code : null);
    }
}
