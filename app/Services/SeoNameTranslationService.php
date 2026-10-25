<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SeoNameTranslationService
{
    /** Canonical English renderings for approved Bengali catalog phrases. */
    private const PREFERRED_PHRASES = [
        'রাসূল আমার ভালোবাসা' => 'The Messenger Is My Love',
        'রাসুল আমার ভালোবাসা' => 'The Messenger Is My Love',
    ];

    public function translate(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 300) {
            throw new RuntimeException('Enter a product name of up to 300 characters.');
        }

        $name = strtr($name, self::PREFERRED_PHRASES);
        if (! preg_match('/\p{Bengali}/u', $name)) {
            return $name;
        }

        $provider = (string) Setting::get('seo_translation_provider', 'mymemory');
        if (! in_array($provider, ['mymemory', 'google_cloud'], true)) {
            throw new RuntimeException('Choose a supported translation provider in Translation API settings.');
        }

        $apiKey = $this->apiKey();
        if ($provider === 'google_cloud' && $apiKey === '') {
            throw new RuntimeException('Add a Google Cloud API key in Admin → System → Translation API.');
        }

        $cacheKey = 'seo-name-translation:'.hash('sha256', implode('|', [
            $provider,
            hash('sha256', $apiKey),
            $name,
        ]));

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($provider, $apiKey, $name) {
            return $provider === 'google_cloud'
                ? $this->translateWithGoogle($name, $apiKey)
                : $this->translateWithMyMemory($name);
        });
    }

    public function hasApiKey(): bool
    {
        return trim((string) Setting::get('seo_translation_api_key', '')) !== '';
    }

    private function apiKey(): string
    {
        $encrypted = trim((string) Setting::get('seo_translation_api_key', ''));
        if ($encrypted === '') {
            return '';
        }

        try {
            return trim(Crypt::decryptString($encrypted));
        } catch (DecryptException) {
            throw new RuntimeException('The saved translation API key could not be read. Enter it again in Translation API settings.');
        }
    }

    private function translateWithMyMemory(string $name): string
    {
        try {
            $response = Http::acceptJson()->timeout(12)->get('https://api.mymemory.translated.net/get', [
                'q' => $name,
                'langpair' => 'bn|en',
            ]);
        } catch (\Throwable $exception) {
            throw new RuntimeException('MyMemory could not be reached. Try again later or switch providers.', previous: $exception);
        }

        if ($response->status() === 429) {
            throw new RuntimeException('MyMemory has reached its free usage limit. Try later or switch providers.');
        }

        $translation = $response->json('responseData.translatedText');
        if (! $response->successful() || (int) $response->json('responseStatus') !== 200 || ! is_string($translation)) {
            throw new RuntimeException('MyMemory could not translate this name. Check the provider settings and try again.');
        }

        return $this->cleanTranslation($translation);
    }

    private function translateWithGoogle(string $name, string $apiKey): string
    {
        try {
            $response = Http::acceptJson()
                ->withHeaders(['X-Goog-Api-Key' => $apiKey])
                ->timeout(12)
                ->post('https://translation.googleapis.com/language/translate/v2', [
                    'q' => $name,
                    'source' => 'bn',
                    'target' => 'en',
                    'format' => 'text',
                ]);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Google Cloud Translation could not be reached. Try again later.', previous: $exception);
        }

        if ($response->status() === 429) {
            throw new RuntimeException('Google Cloud Translation quota was reached. Check the project quota and try later.');
        }

        $translation = $response->json('data.translations.0.translatedText');
        if (! $response->successful()) {
            throw new RuntimeException('Google Cloud rejected the request. Check its API key, enabled API, and billing status.');
        }
        if (! is_string($translation)) {
            throw new RuntimeException('Google Cloud did not return a translation. Check the provider settings and try again.');
        }

        return $this->cleanTranslation($translation);
    }

    private function cleanTranslation(string $translation): string
    {
        $translation = trim(html_entity_decode(strip_tags($translation), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($translation === '' || preg_match('/\p{Bengali}/u', $translation)) {
            throw new RuntimeException('The provider did not return a complete English translation.');
        }

        return mb_substr($translation, 0, 300);
    }
}
