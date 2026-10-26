<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeferredTrackerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_meta_pixel_waits_for_visitor_consent_by_default(): void
    {
        Setting::put('tracking_hotjar_id', '12345678');
        Setting::put('tracking_ga4_id', 'G-STORE');
        Setting::put('tracking_meta_pixel_id', '123456789012345');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/\bhotjar:\s*"12345678"/', $html);
        $this->assertStringNotContainsString('static.hotjar.com', $html);
        $this->assertStringContainsString('googletagmanager.com/gtag/js?id=G-STORE', $html);
        $this->assertStringContainsString('pixelConsentRequired: true', $html);
        $this->assertStringContainsString('pixelConsent: false', $html);
        $this->assertStringContainsString('data-consent-accept', $html);
        $this->assertStringContainsString('data-consent-reject', $html);
        $this->assertDoesNotMatchRegularExpression('/<script\b[^>]*\bsrc=["\']https:\/\/connect\.facebook\.net/i', $html);
        $this->assertStringNotContainsString('facebook.com/tr?id=123456789012345', $html);
    }

    public function test_meta_pixel_can_be_configured_to_load_immediately(): void
    {
        Setting::put('tracking_meta_pixel_id', '123456789012345');
        Setting::put('tracking_meta_pixel_consent_required', '0');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('pixelConsentRequired: false', $html);
        $this->assertStringContainsString('pixelConsent: true', $html);
        $this->assertStringContainsString('window.__loadMetaPixel();', $html);
        $this->assertStringNotContainsString('taqi-pixel-consent', $html);
    }
}
