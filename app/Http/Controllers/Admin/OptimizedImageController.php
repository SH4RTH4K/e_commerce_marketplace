<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OptimizedImageCleanupService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OptimizedImageController extends Controller
{
    public function index(OptimizedImageCleanupService $cleanup)
    {
        return Inertia::render('Admin/OptimizedImages/Index', $cleanup->scan());
    }

    public function delete(Request $request, OptimizedImageCleanupService $cleanup)
    {
        $data = $request->validate([
            'filenames' => ['required', 'array', 'min:1', 'max:1000'],
            'filenames.*' => ['required', 'string', 'regex:/^[a-f0-9]{64}\.webp$/i'],
        ]);
        $result = $cleanup->delete($data['filenames']);

        return back()->with('status', count($result['deleted']).' orphaned optimized image(s) deleted.'.(count($result['skipped']) ? ' Some files were skipped because they are no longer orphaned.' : ''));
    }
}
