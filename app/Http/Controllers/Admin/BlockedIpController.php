<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockedIp;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BlockedIpController extends Controller
{
    public function index()
    {
        $blockedIps = BlockedIp::latest()->paginate(20);
        return Inertia::render('Admin/BlockedIps/Index', compact('blockedIps'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ip_address' => ['required', 'string', 'max:45', 'unique:blocked_ips,ip_address'],
            'reason'     => ['nullable', 'string', 'max:255'],
        ]);

        BlockedIp::create($validated);
        return back()->with('status', 'IP blocked successfully.');
    }

    public function destroy(BlockedIp $blockedIp)
    {
        $blockedIp->delete();
        return back()->with('status', 'IP block removed.');
    }
}
