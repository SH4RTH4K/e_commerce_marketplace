<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SeoNameTranslationService
{
    public function translate(string $name): string
    {
        $name = trim($name);
        $cacheKey = 'seo-name-translation:'.hash('sha256', $name);

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($name) {
            try {
                $response = Http::acceptJson()->timeout(12)->get('https://api.mymemory.translated.net/get', [
                    'q' => $name,
                    'langpair' => 'bn|en',
                ]);
            } catch (\Throwable $exception) {
                throw new RuntimeException('The translation provider could not be reached.', previous: $exception);
            }

            $translation = $response->json('responseData.translatedText');
            if (! $response->successful() || (int) $response->json('responseStatus') !== 200 || ! is_string($translation)) {
                throw new RuntimeException('The translation provider returned an unsuccessful response.');
            }

            $translation = trim(html_entity_decode(strip_tags($translation), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($translation === '' || preg_match('/\p{Bengali}/u', $translation)) {
                throw new RuntimeException('The translation provider did not return a complete English translation.');
            }

            return mb_substr($translation, 0, 300);
        });
    }
}
