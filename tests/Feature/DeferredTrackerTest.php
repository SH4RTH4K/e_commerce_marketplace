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

    public function test_hotjar_is_not_in_initial_html_while_ga_and_meta_remain_immediate(): void
    {
        Setting::put('tracking_hotjar_id', '12345678');
        Setting::put('tracking_ga4_id', 'G-STORE');
        Setting::put('tracking_meta_pixel_id', '123456789012345');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/\bhotjar:\s*"12345678"/', $html);
        $this->assertStringNotContainsString('static.hotjar.com', $html);
        $this->assertStringContainsString('googletagmanager.com/gtag/js?id=G-STORE', $html);
        $this->assertStringContainsString('connect.facebook.net/en_US/fbevents.js', $html);
    }
}
