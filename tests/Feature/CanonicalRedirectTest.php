<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_http_www_url_redirects_to_https_non_www_canonical_url(): void
    {
        Setting::put('seo_https_redirect_enabled', '1');
        Setting::put('seo_canonical_host', 'non_www');

        $this->get('http://www.example.com/shop?sort=newest')
            ->assertStatus(301)
            ->assertRedirect('https://example.com/shop?sort=newest');
    }

    public function test_non_www_url_redirects_to_www_when_configured(): void
    {
        Setting::put('seo_https_redirect_enabled', '0');
        Setting::put('seo_canonical_host', 'www');

        $this->get('http://example.com/shop')
            ->assertStatus(301)
            ->assertRedirect('http://www.example.com/shop');
    }

    public function test_blank_canonical_host_defaults_to_non_www_for_public_hosts(): void
    {
        Setting::put('seo_https_redirect_enabled', '0');
        Setting::put('seo_canonical_host', '');

        $this->withServerVariables([
            'HTTPS' => 'on',
            'HTTP_HOST' => 'www.example.com',
            'SERVER_NAME' => 'www.example.com',
        ])->get('/shop')
            ->assertStatus(301)
            ->assertRedirect('https://example.com/shop');
    }

    public function test_redirects_can_be_disabled(): void
    {
        Setting::put('seo_https_redirect_enabled', '0');
        Setting::put('seo_canonical_host', 'none');

        $this->get('http://www.example.com/')
            ->assertOk();
    }
}
