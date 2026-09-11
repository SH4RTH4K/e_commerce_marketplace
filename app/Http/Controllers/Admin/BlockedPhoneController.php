<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockedPhone;
use App\Support\BdPhoneValidator;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BlockedPhoneController extends Controller
{
    public function index()
    {
        $blockedPhones = BlockedPhone::latest()->paginate(20);
        return Inertia::render('Admin/BlockedPhones/Index', compact('blockedPhones'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'phone'  => ['required', 'string', 'max:20'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $phone = BdPhoneValidator::normalize($validated['phone']);

        if (! BdPhoneValidator::isValid($phone)) {
            return back()->withErrors(['phone' => 'Please enter a valid Bangladeshi phone number (01X-XXXXXXXX).']);
        }

        if (BlockedPhone::where('phone', $phone)->exists()) {
            return back()->withErrors(['phone' => 'This phone number is already blocked.']);
        }

        BlockedPhone::create([
            'phone'  => $phone,
            'reason' => $validated['reason'] ?? null,
        ]);

        return back()->with('status', 'Phone number blocked successfully.');
    }

    public function destroy(BlockedPhone $blockedPhone)
    {
        $blockedPhone->delete();
        return back()->with('status', 'Phone block removed.');
    }
}

