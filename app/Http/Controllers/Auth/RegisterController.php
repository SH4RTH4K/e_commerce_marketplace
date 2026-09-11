<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class RegisterController extends Controller
{
    public function show()
    {
        return Inertia::render('Storefront/Auth/Register');
    }

    public function store(Request $request, OtpService $otp)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['required', 'email', 'max:120', 'unique:users,email'],
            'phone'    => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $data['phone'] ?? null,
            'password' => $data['password'],   // hashed by the model cast
            'role'     => 'customer',
        ]);

        if (otp_enabled()) {
            $code = $otp->send($user->email, 'register', $user->name);
            $request->session()->put('otp_email', $user->email);
            $request->session()->put('otp_purpose', 'register');

            return redirect()->route('verify')
                ->with('status', 'We sent a verification code to your email.')
                ->with('dev_otp', (app()->environment('local') && ! app()->isProduction()) ? $code : null);
        }

        $user->forceFill(['email_verified_at' => now()])->save();
        Auth::login($user, true);

        return redirect()->route('account')->with('status', 'Welcome to your account!');
    }
}
