<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SeoNameTranslationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use RuntimeException;

class TranslationSettingsController extends Controller
{
    public function index(SeoNameTranslationService $translator)
    {
        $provider = (string) Setting::get('seo_translation_provider', 'mymemory');
        if (! in_array($provider, ['mymemory', 'google_cloud'], true)) {
            $provider = 'mymemory';
        }

        return Inertia::render('Admin/TranslationSettings', [
            'settings' => [
                'provider' => $provider,
                'hasApiKey' => $translator->hasApiKey(),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'provider' => ['required', 'in:mymemory,google_cloud'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'remove_api_key' => ['nullable', 'boolean'],
        ]);

        $apiKey = trim((string) ($data['api_key'] ?? ''));
        $removeApiKey = $request->boolean('remove_api_key');
        $hasSavedKey = trim((string) Setting::get('seo_translation_api_key', '')) !== '';
        if ($data['provider'] === 'google_cloud' && $apiKey === '' && (! $hasSavedKey || $removeApiKey)) {
            throw ValidationException::withMessages([
                'api_key' => 'A Google Cloud API key is required. Enter a key or select MyMemory.',
            ]);
        }

        DB::transaction(function () use ($data, $apiKey, $removeApiKey) {
            Setting::query()->updateOrCreate(
                ['key' => 'seo_translation_provider'],
                ['value' => $data['provider']]
            );

            if ($apiKey !== '') {
                Setting::query()->updateOrCreate(
                    ['key' => 'seo_translation_api_key'],
                    ['value' => Crypt::encryptString($apiKey)]
                );
            } elseif ($removeApiKey) {
                Setting::query()->where('key', 'seo_translation_api_key')->delete();
            }
        });
        Setting::forgetCache();

        return back()->with('status', 'Translation provider settings saved.');
    }

    public function test(SeoNameTranslationService $translator)
    {
        try {
            $sample = $translator->translate('বাংলা পরীক্ষা');
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'Connection successful.', 'sample' => $sample]);
    }
}
