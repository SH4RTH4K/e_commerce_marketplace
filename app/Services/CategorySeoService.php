<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeoService
{
    /**
     * Search snippets for the main collections, written around the products
     * customers can actually browse in each collection.
     *
     * @var array<string, array{title: string, description: string}>
     */
    private const COLLECTIONS = [
        'airpods' => [
            'title' => 'AirPods & Earbuds Online in Bangladesh | TAQI LIFE',
            'description' => 'Shop AirPods and wireless earbuds at TAQI LIFE in Bangladesh. Compare key features, product details and current prices before choosing.',
        ],
        'customize-and-gift' => [
            'title' => 'Custom Gifts Online in Bangladesh | TAQI LIFE',
            'description' => 'Find customizable gifts at TAQI LIFE in Bangladesh. Browse available designs, product details and prices before placing an order.',
        ],
        'gadgets-electronics' => [
            'title' => 'Gadgets & Electronics Online in Bangladesh | TAQI LIFE',
            'description' => 'Shop gadgets and electronics in Bangladesh at TAQI LIFE. Compare product features, photos, prices and current availability before ordering.',
        ],
        'home-lifestyle' => [
            'title' => 'Home & Lifestyle Products Online in Bangladesh | TAQI LIFE',
            'description' => 'Browse home and lifestyle products at TAQI LIFE in Bangladesh. Compare practical everyday items by product details, price and availability.',
        ],
        'kids-zone' => [
            'title' => "Kids' Products Online in Bangladesh | TAQI LIFE",
            'description' => "Shop children's products online in Bangladesh at TAQI LIFE. Review product details, prices and available options before placing an order.",
        ],
        'mens-fashion' => [
            'title' => "Men's Fashion Online in Bangladesh | TAQI LIFE",
            'description' => "Shop men's clothing and fashion online in Bangladesh at TAQI LIFE. Browse current styles, compare product details and check available sizes.",
        ],
        'watches' => [
            'title' => 'Watches Online in Bangladesh | TAQI LIFE',
            'description' => 'Browse watches at TAQI LIFE in Bangladesh. Compare product styles, features and prices, then check current availability before ordering.',
        ],
        'womens-fashion' => [
            'title' => "Women's Fashion Online in Bangladesh | TAQI LIFE",
            'description' => "Browse women's fashion at TAQI LIFE in Bangladesh. Compare clothing and accessories by style, price, size options and current availability.",
        ],
    ];

    /** @return array{title: string, description: string, keywords: ?string} */
    public function metadata(Category $category): array
    {
        $name = $this->plainText($category->name);
        $defaults = self::COLLECTIONS[$category->slug] ?? null;
        $description = trim((string) $category->meta_description);

        if ($description === '') {
            $description = $this->plainText($category->description);
        }

        if ($description === '') {
            $description = $defaults['description'] ?? "Browse {$name} online in Bangladesh at ".site_name().". Compare product details and prices, and check current availability before ordering.";
        }

        return [
            'title' => trim((string) $category->meta_title) ?: ($defaults['title'] ?? $name.' Online in Bangladesh | '.site_name()),
            'description' => $description,
            'keywords' => trim((string) $category->meta_keywords) ?: null,
        ];
    }

    private function plainText(?string $value): string
    {
        return Str::squish(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
