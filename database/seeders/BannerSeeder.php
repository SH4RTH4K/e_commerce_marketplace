<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    public function run(): void
    {
        // Homepage uses placement "hero" only (slider). Remove unused legacy placements.
        Banner::whereIn('placement', ['hero_side', 'promo', 'deals'])->delete();

        $banners = [
            [
                'title'       => 'Up to 70% off everything',
                'subtitle'    => 'Millions of products. Free shipping vouchers. New deals every hour.',
                'badge'       => '11.11 SALE',
                'image'       => 'https://loremflickr.com/1000/440/marketplace,banner,shopping?random=1',
                'link_url'    => '/shop',
                'button_text' => 'Shop now',
                'placement'   => 'hero',
                'style'       => 'brand',
                'position'    => 0,
                'is_active'   => true,
            ],
            [
                'title'       => 'Latest gadgets, lowest prices',
                'subtitle'    => 'Genuine brands with warranty. Pay on delivery.',
                'badge'       => 'NEW TECH',
                'image'       => 'https://loremflickr.com/1000/440/marketplace,banner,gadget?random=2',
                'link_url'    => '/shop?flash=1',
                'button_text' => 'Explore',
                'placement'   => 'hero',
                'style'       => 'brand',
                'position'    => 1,
                'is_active'   => true,
            ],
            [
                'title'       => "Fashion picks under \u{09F3}999",
                'subtitle'    => "Everyday wear, sneakers & accessories \u{2014} new drops daily.",
                'badge'       => 'TRENDING',
                'image'       => 'https://loremflickr.com/1000/440/marketplace,banner,fashion?random=3',
                'link_url'    => '/shop',
                'button_text' => 'Shop fashion',
                'placement'   => 'hero',
                'style'       => 'brand',
                'position'    => 2,
                'is_active'   => true,
            ],
            [
                'title'       => 'Home essentials, big savings',
                'subtitle'    => "Kitchen, decor & storage \u{2014} free shipping on select orders.",
                'badge'       => 'HOME DEAL',
                'image'       => 'https://loremflickr.com/1000/440/marketplace,banner,home?random=4',
                'link_url'    => '/shop',
                'button_text' => 'Shop home',
                'placement'   => 'hero',
                'style'       => 'accent',
                'position'    => 3,
                'is_active'   => true,
            ],
            [
                'title'       => 'Beauty & personal care',
                'subtitle'    => 'Skincare, fragrance & grooming from trusted brands.',
                'badge'       => 'GLOW UP',
                'image'       => 'https://loremflickr.com/1000/440/marketplace,banner,beauty?random=5',
                'link_url'    => '/shop',
                'button_text' => 'Shop beauty',
                'placement'   => 'hero',
                'style'       => 'rose',
                'position'    => 4,
                'is_active'   => true,
            ],
        ];

        foreach ($banners as $banner) {
            Banner::updateOrCreate(
                [
                    'placement' => $banner['placement'],
                    'position'  => $banner['position'],
                    'title'     => $banner['title'],
                ],
                $banner
            );
        }
    }
}
