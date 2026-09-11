<?php

namespace Database\Seeders;

use App\Models\LandingPage;
use App\Models\Product;
use App\Support\LandingPageDesigns;
use Illuminate\Database\Seeder;

class LandingPageSeeder extends Seeder
{
    public function run(): void
    {
        $product = Product::query()->orderBy('id')->first();
        if (! $product) {
            $this->command?->warn('LandingPageSeeder skipped: no products found. Seed products first.');

            return;
        }

        foreach (LandingPageDesigns::keys() as $design) {
            LandingPageDesigns::setVisible($design, true);
        }

        $pages = [
            [
                'slug'       => 'sfltar-rodmzap-5ti-mastarpis-bi-pacchen-matr-699-takay',
                'title'      => 'সফলতার রোডম্যাপ ৫টি মাস্টারপিস বই পাচ্ছেন মাত্র ৬৯৯ টাকায়',
                'design'     => 'campaign',
                'hero_title' => 'সফলতার রোডম্যাপ ৫টি মাস্টারপিস বই পাচ্ছেন মাত্র ৬৯৯ টাকায়।',
            ],
            [
                'slug'       => 'chilora-landing',
                'title'      => 'SHARTHAK X1 (টকিং লার্নিং বুক)',
                'design'     => 'chilora',
                'hero_title' => 'বাচ্চাদের মেধা বিকাশের সেরা উপহার',
            ],
            [
                'slug'       => 'nuraya-landing',
                'title'      => 'SHARTHAK X2 (সালাত হিজাব)',
                'design'     => 'nuraya',
                'hero_title' => '১০০% সুতি অরবিন্দ ভয়েলের সালাত হিজাব',
            ],
        ];

        foreach ($pages as $meta) {
            $design = $meta['design'];
            $content = LandingPageDesigns::defaults($design);
            $content['hero_title'] = $meta['hero_title'];

            LandingPage::updateOrCreate(
                ['slug' => $meta['slug']],
                [
                    'title'      => $meta['title'],
                    'design'     => $design,
                    'product_id' => $product->id,
                    'is_active'  => true,
                    'content'    => $content,
                ]
            );
        }

        // Remove legacy / corrupted drafts
        LandingPage::whereIn('slug', ['nuraya-landingasd', '2chilora-landing'])->delete();
        LandingPage::where('slug', 'like', '%chilora-landing%')
            ->where('slug', '!=', 'chilora-landing')
            ->delete();
        LandingPage::where('slug', 'like', '%nuraya-landing%')
            ->where('slug', '!=', 'nuraya-landing')
            ->delete();
    }
}
