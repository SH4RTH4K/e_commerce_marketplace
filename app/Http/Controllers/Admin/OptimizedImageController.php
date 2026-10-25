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
        $perPage = (int) request()->input('per_page', 50);
        $perPage = in_array($perPage, [25, 50, 100, 250, 500], true) ? $perPage : 50;
        $page = max(1, (int) request()->input('page', 1));
        $scan = $cleanup->scan();
        $total = count($scan['files']);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        return Inertia::render('Admin/OptimizedImages/Index', [
            ...$scan,
            'files' => array_values(array_slice($scan['files'], $offset, $perPage)),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $total),
            ],
        ]);
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
