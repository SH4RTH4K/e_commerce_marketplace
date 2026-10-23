<?php

namespace Tests\Unit;

use App\Services\Dropshipping\SupplierProductImageResolver;
use PHPUnit\Framework\TestCase;

class SupplierProductImageResolverTest extends TestCase
{
    public function test_it_resolves_supplier_images_from_supported_nested_payload_shapes(): void
    {
        $payload = [
            'thumbnail_img' => ' https://supplier.example/primary.jpg ',
            'data' => [
                'product_images' => [
                    ['product_image' => 'https://supplier.example/detail.webp'],
                    ['media' => ['src' => 'https://supplier.example/nested.png']],
                ],
            ],
            'gallery' => json_encode([
                ['url' => 'https://supplier.example/gallery.jpg'],
                ['url' => 'https://supplier.example/detail.webp'],
            ], JSON_THROW_ON_ERROR),
        ];

        $this->assertSame([
            'https://supplier.example/primary.jpg',
            'https://supplier.example/detail.webp',
            'https://supplier.example/nested.png',
            'https://supplier.example/gallery.jpg',
        ], (new SupplierProductImageResolver)->resolve($payload));
    }

    public function test_it_ignores_non_image_urls_outside_image_containers(): void
    {
        $payload = [
            'url' => 'https://supplier.example/product/1',
            'links' => ['src' => 'https://supplier.example/api/product/1'],
            'images' => [['url' => 'https://supplier.example/product.jpg']],
        ];

        $this->assertSame(
            ['https://supplier.example/product.jpg'],
            (new SupplierProductImageResolver)->resolve($payload),
        );
    }
}
