<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SeoCompletionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SeoCompletionController extends Controller
{
    public function index(SeoCompletionService $seo)
    {
        return Inertia::render('Admin/SeoCompletion', ['summary' => $seo->summary(), 'samples' => $seo->preview(), 'englishNames' => $seo->englishNames(), 'result' => session('seo_completion_result')]);
    }

    public function store(Request $request, SeoCompletionService $seo)
    {
        $data = $request->validate(['fill_missing' => ['required', 'accepted'], 'english_names' => ['nullable', 'array', 'max:3500'],
            'english_names.*' => ['nullable', 'string', 'max:300', 'regex:/[A-Za-z]/', 'not_regex:/\p{Bengali}/u']]);
        try {
            $result = $seo->complete((int) $request->user()->id, array_filter($data['english_names'] ?? [], fn ($value) => trim((string) $value) !== ''));
        } catch (\RuntimeException $exception) {
            report($exception);

            return back()->withErrors(['completion' => $exception->getMessage()]);
        }

        return redirect()->route('admin.seo-completion.index')->with('seo_completion_result', $result)
            ->with('status', "SEO updated for {$result['categories']} categories and {$result['products']} products. Existing SEO text was preserved.");
    }
}
