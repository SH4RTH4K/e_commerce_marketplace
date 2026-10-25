<?php

namespace Tests\Unit;

use App\Services\SeoNameTranslationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SeoNameTranslationServiceTest extends TestCase
{
    public function test_approved_catalog_phrase_is_translated_without_provider_transliteration(): void
    {
        Http::fake();

        $translation = app(SeoNameTranslationService::class)
            ->translate('রাসূল আমার ভালোবাসা Islamic Hoodie (Royal Blue)');

        $this->assertSame('The Messenger Is My Love Islamic Hoodie (Royal Blue)', $translation);
        Http::assertNothingSent();
    }
}
