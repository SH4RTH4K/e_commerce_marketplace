<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileMetadataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withHeader('X-Forwarded-Proto', 'https');
    }

    public function test_manifest_is_valid_and_its_branding_icons_are_reachable(): void
    {
        $response = $this->get('/manifest.webmanifest')->assertOk();
        $this->assertStringContainsString('application/manifest+json', (string) $response->headers->get('Content-Type'));

        $manifest = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('TAQI LIFE', $manifest['name']);
        $this->assertSame('TAQI', $manifest['short_name']);
        $this->assertSame('browser', $manifest['display']);
        $this->assertSame('#f15a24', $manifest['theme_color']);
        $this->assertSame(['192x192', '512x512'], array_column($manifest['icons'], 'sizes'));

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim(parse_url($icon['src'], PHP_URL_PATH), '/')));
            $this->assertSame('image/png', $icon['type']);
        }
    }

    public function test_theme_color_and_manifest_are_present_across_public_page_types(): void
    {
        foreach (['/', '/shop', '/cart', '/checkout', '/blog', '/login', '/register', '/admin/login'] as $url) {
            $response = $this->followingRedirects()->get($url);
            $this->assertSame(200, $response->status(), $url);
            $html = $response->getContent();

            $this->assertStringContainsString('<meta name="theme-color" content="#f15a24" />', $html, $url);
            $this->assertStringContainsString('<link rel="manifest" href="'.asset('manifest.webmanifest').'" />', $html, $url);
        }
    }
}
