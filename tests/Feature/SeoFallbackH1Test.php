<?php

namespace Tests\Feature;

use App\Models\Setting;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoFallbackH1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_public_initial_html_contains_configured_fallback_h1_for_crawlers(): void
    {
        Setting::put('seo_fallback_h1_enabled', '1');
        Setting::put('seo_h1_heading', 'Premium Online Shopping in Bangladesh');

        $xpath = $this->htmlFor('/');

        $this->assertSame(
            'Premium Online Shopping in Bangladesh',
            $xpath->evaluate('string(//h1[@data-seo-fallback-h1])')
        );
    }

    public function test_fallback_h1_can_be_disabled_from_seo_settings(): void
    {
        Setting::put('seo_fallback_h1_enabled', '0');
        Setting::put('seo_h1_heading', 'Premium Online Shopping in Bangladesh');

        $xpath = $this->htmlFor('/');

        $this->assertSame(0, $xpath->query('//h1[@data-seo-fallback-h1]')->length);
    }

    private function htmlFor(string $uri): DOMXPath
    {
        $response = $this->get($uri)->assertOk();
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());

        return new DOMXPath($document);
    }
}
