<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentSecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withMiddleware();
        $this->withHeader('X-Forwarded-Proto', 'https');
    }

    public function test_enforced_production_csp_uses_a_request_nonce_without_unsafe_inline_scripts(): void
    {
        $this->useProductionCsp(false);
        $this->assertSame('production', config('app.env'));

        $response = $this->get('/');
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertSame('camera=(), microphone=(), geolocation=()', $response->headers->get('Permissions-Policy'));
        $this->assertSame('max-age=31536000; includeSubDomains; preload', $response->headers->get('Strict-Transport-Security'));
        $this->assertFalse($response->headers->has('X-XSS-Protection'));
        $csp = (string) $response->headers->get('Content-Security-Policy');
        $scriptDirective = collect(explode(';', $csp))
            ->first(fn (string $directive): bool => str_starts_with(trim($directive), 'script-src'));

        $this->assertNotEmpty($csp);
        $this->assertNotEmpty($scriptDirective);
        $this->assertStringNotContainsString("'unsafe-inline'", $scriptDirective);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertMatchesRegularExpression("/'nonce-[A-Za-z0-9+\\/=]+'/", $scriptDirective);

        preg_match("/'nonce-([^']+)'/", $scriptDirective, $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $this->assertStringContainsString('nonce="'.$matches[1].'"', $response->getContent());
        $this->assertStringContainsString('data-page="app" nonce="'.$matches[1].'"', $response->getContent());
    }

    public function test_report_only_mode_uses_the_same_nonce_policy_without_enforcement(): void
    {
        $this->useProductionCsp(true);

        $response = $this->get('/');

        $this->assertTrue($response->headers->has('Content-Security-Policy-Report-Only'));
        $this->assertFalse($response->headers->has('Content-Security-Policy'));
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", (string) $response->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_nonce_policy_is_present_across_storefront_page_types(): void
    {
        $this->useProductionCsp(false);
        $category = Category::create(['name' => 'Speakers', 'slug' => 'speakers', 'is_active' => true, 'show_in_menu' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Portable Speaker',
            'slug' => 'portable-speaker',
            'sku' => 'CSP123',
            'regular_price' => 1000,
            'stock_quantity' => 5,
            'is_published' => true,
        ]);

        $pages = [
            '/',
            '/shop',
            '/category/speakers',
            route('product.show', $product),
            '/cart',
            '/checkout',
            '/blog',
            '/login',
        ];

        foreach ($pages as $url) {
            $response = $this->get($url);
            $csp = (string) $response->headers->get('Content-Security-Policy');
            $scriptDirective = collect(explode(';', $csp))
                ->first(fn (string $directive): bool => str_starts_with(trim($directive), 'script-src'));

            $this->assertNotEmpty($scriptDirective, $url);
            $this->assertStringNotContainsString("'unsafe-inline'", $scriptDirective, $url);
            $this->assertStringNotContainsString("'unsafe-eval'", $csp, $url);
            $this->assertAllScriptTagsHaveNonces($response->getContent(), $url);
        }
    }

    private function assertAllScriptTagsHaveNonces(string $html, string $url): void
    {
        preg_match_all('/<script\\b[^>]*>/i', $html, $tags);

        foreach ($tags[0] as $tag) {
            $this->assertMatchesRegularExpression('/\\bnonce="[^"]+"/i', $tag, $url.' contains an un-nonced script tag');
        }
    }

    private function useProductionCsp(bool $reportOnly): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.env' => 'production', 'security.csp_report_only' => $reportOnly]);
    }
}
