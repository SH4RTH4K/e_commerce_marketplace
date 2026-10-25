<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\SitemapService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_index_and_children_are_valid_and_cover_the_public_catalogue(): void
    {
        $category = Category::create(['name' => 'Speakers', 'slug' => 'speakers', 'is_active' => true, 'show_in_menu' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Portable Speaker',
            'slug' => 'portable-speaker',
            'sku' => 'ABC123',
            'regular_price' => 1000,
            'stock_quantity' => 5,
            'is_published' => true,
        ]);
        Category::create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Draft Speaker',
            'slug' => 'draft-speaker',
            'regular_price' => 1000,
            'is_published' => false,
        ]);
        app(SitemapService::class)->invalidate();

        $index = $this->get('/sitemap.xml')->assertOk();
        $indexXml = $this->xml($index->getContent());
        $this->assertSame('sitemapindex', $indexXml->evaluate('local-name(/*)'));
        $childUrls = collect(iterator_to_array($indexXml->query('//sm:sitemap/sm:loc')))
            ->map(fn ($node) => trim($node->textContent))
            ->all();
        $this->assertSame([
            route('sitemap.products'),
            route('sitemap.categories'),
            route('sitemap.pages'),
            route('sitemap.blogs'),
        ], $childUrls);

        $counts = [];
        foreach (['products', 'categories', 'pages', 'blogs'] as $type) {
            $response = $this->get(route('sitemap.'.$type))->assertOk();
            $xml = $this->xml($response->getContent());
            $this->assertSame('urlset', $xml->evaluate('local-name(/*)'));
            $locs = $xml->query('//sm:url/sm:loc');
            $counts[$type] = $locs->length;
            $this->assertLessThan(50000, $locs->length);
            $this->assertStringNotContainsString('/admin', $response->getContent());
            $this->assertStringNotContainsString('/checkout', $response->getContent());
            $this->assertStringNotContainsString('/account', $response->getContent());
            $this->assertStringNotContainsString('/api/', $response->getContent());
        }

        $this->assertContains(route('product.show', $product), $this->locs($this->get(route('sitemap.products'))->getContent()));
        $this->assertContains(route('shop.category', $category), $this->locs($this->get(route('sitemap.categories'))->getContent()));
        $this->assertNotContains(route('shop.category', 'hidden'), $this->locs($this->get(route('sitemap.categories'))->getContent()));
        $this->assertContains(route('contact'), $this->locs($this->get(route('sitemap.pages'))->getContent()));
        $this->assertContains(route('blog.detail', 'clothing-size-guide'), $this->locs($this->get(route('sitemap.blogs'))->getContent()));
        $this->assertSame($product->updated_at->toAtomString(), $this->xml($this->get(route('sitemap.products'))->getContent())->evaluate('string(//sm:url[sm:loc="'.route('product.show', $product).'"]/sm:lastmod)'));

        $expected = 8 + count(app(SitemapService::class)->visibleCategories()) + Product::published()->count() + 1 + 5;
        $this->assertSame($expected, array_sum($counts));
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: '.route('sitemap'), false);
    }

    private function xml(string $content): DOMXPath
    {
        $document = new DOMDocument;
        $this->assertTrue(@$document->loadXML($content));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        return $xpath;
    }

    private function locs(string $content): array
    {
        return collect(iterator_to_array($this->xml($content)->query('//sm:url/sm:loc')))
            ->map(fn ($node) => trim($node->textContent))
            ->all();
    }
}
