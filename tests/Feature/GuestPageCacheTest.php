<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestPageCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['storefront.page_cache.enabled' => true]);
        Setting::put('seo_keyword_consistency_enabled', '0');
    }

    public function test_guest_html_is_publicly_cacheable_and_hits_on_the_second_request(): void
    {
        $first = $this->get('/about')->assertOk();
        $second = $this->get('/about')->assertOk();

        $first->assertHeader('Cache-Control', 'max-age=300, public, s-maxage=300, stale-while-revalidate=60')
            ->assertHeader('X-Page-Cache', 'MISS');
        $second->assertHeader('Cache-Control', 'max-age=300, public, s-maxage=300, stale-while-revalidate=60')
            ->assertHeader('X-Page-Cache', 'HIT');

        $this->assertStringNotContainsString('__STOREFRONT_CSRF_TOKEN__', $second->getContent());
        $this->assertMatchesRegularExpression('/<meta name="csrf-token" content="[^"]+"/i', $second->getContent());
    }

    public function test_session_state_and_authenticated_visitors_never_use_the_guest_cache(): void
    {
        $this->get('/about')->assertHeader('X-Page-Cache', 'MISS');

        $this->withSession(['cart' => ['42' => 1]])
            ->get('/about')
            ->assertHeader('X-Page-Cache', 'BYPASS')
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');

        $user = User::factory()->create(['role' => 'customer', 'is_active' => true]);

        $this->actingAs($user)
            ->get('/about')
            ->assertHeader('X-Page-Cache', 'BYPASS')
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');
    }

    public function test_setting_changes_invalidate_cached_public_html(): void
    {
        $this->get('/about')->assertHeader('X-Page-Cache', 'MISS');
        $this->get('/about')->assertHeader('X-Page-Cache', 'HIT');

        Setting::put('about_content', '<p>Updated content</p>');

        $this->get('/about')
            ->assertHeader('X-Page-Cache', 'MISS')
            ->assertSee('Updated content');
    }
}
