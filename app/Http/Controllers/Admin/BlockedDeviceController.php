<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockedDevice;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BlockedDeviceController extends Controller
{
    public function index()
    {
        $blockedDevices = BlockedDevice::latest()->paginate(20);
        return Inertia::render('Admin/BlockedDevices/Index', compact('blockedDevices'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_agent' => ['required', 'string', 'max:1000'],
            'reason'     => ['nullable', 'string', 'max:255'],
        ]);

        $hash = hash('sha256', trim($validated['user_agent']));

        if (BlockedDevice::where('device_hash', $hash)->exists()) {
            return back()->withErrors(['user_agent' => 'This device is already blocked.']);
        }

        BlockedDevice::create([
            'device_hash' => $hash,
            'user_agent'  => $validated['user_agent'],
            'reason'      => $validated['reason'] ?? null,
        ]);

        return back()->with('status', 'Device blocked successfully.');
    }

    public function destroy(BlockedDevice $blockedDevice)
    {
        $blockedDevice->delete();
        return back()->with('status', 'Device block removed.');
    }
}
