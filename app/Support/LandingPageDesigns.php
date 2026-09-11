<?php

namespace App\Support;

use App\Models\Setting;

class LandingPageDesigns
{
    public const DESIGNS = [
        'campaign' => [
            'label'       => 'Campaign Pro (SHARTHAK)',
            'description' => 'High-converting sales campaign with top timer, video/image hero, package selector & 1-page COD checkout.',
            'preview'     => 'landing/campaign/preview.png',
        ],
        'chilora' => [
            'label'       => 'SHARTHAK X1 (Kids & Products)',
            'description' => 'Kids learning-book sales page — hero, countdown, features, reviews, order.',
            'preview'     => 'landing/chilora/preview.png',
        ],
        'nuraya' => [
            'label'       => 'SHARTHAK X2 (Fashion & Single Item)',
            'description' => 'Single-product modest wear sales page — hero, offer, benefits, reviews, order.',
            'preview'     => 'landing/nuraya/preview.png',
        ],
    ];

    public static function isVisible(string $design): bool
    {
        if (! in_array($design, self::keys(), true)) {
            return false;
        }

        $raw = Setting::get("landing_design_{$design}_visible", '1');

        return $raw === '1' || $raw === 1 || $raw === true;
    }

    public static function setVisible(string $design, bool $visible): void
    {
        Setting::put("landing_design_{$design}_visible", $visible ? '1' : '0');
    }

    public static function previewUrl(string $design): string
    {
        $path = self::DESIGNS[$design]['preview'] ?? null;

        return $path ? asset($path) : '';
    }

    public static function keys(): array
    {
        return array_keys(self::DESIGNS);
    }

    /** @return array<string, array{label: string, toggle?: bool, fields: array<int, array<string, mixed>>}> */
    public static function sections(string $design): array
    {
        return match ($design) {
            'campaign' => self::campaignSections(),
            'nuraya'   => self::nurayaSections(),
            default    => self::chiloraSections(),
        };
    }

    public static function defaultSectionVisibility(string $design): array
    {
        $vis = [];
        foreach (self::sections($design) as $key => $meta) {
            if (! empty($meta['toggle'])) {
                $vis[$key] = true;
            }
        }

        return $vis;
    }

    public static function defaults(string $design): array
    {
        $base = match ($design) {
            'campaign' => self::campaignDefaults(),
            'nuraya'   => self::nurayaDefaults(),
            default    => self::chiloraDefaults(),
        };
        $base['_sections'] = self::defaultSectionVisibility($design);

        return $base;
    }

    private static function campaignSections(): array
    {
        return [
            'setup' => [
                'label'  => 'Page setup',
                'toggle' => false,
                'fields' => [
                    ['name' => 'title', 'type' => 'page_title', 'label' => 'Title'],
                    ['name' => 'slug', 'type' => 'page_slug', 'label' => 'URL slug'],
                    ['name' => 'product_id', 'type' => 'page_product', 'label' => 'Linked product'],
                    ['name' => 'is_active', 'type' => 'page_active', 'label' => 'Page visible'],
                    ['name' => 'meta_title', 'type' => 'text', 'label' => 'Meta title'],
                    ['name' => 'meta_description', 'type' => 'textarea', 'label' => 'Meta description'],
                ],
            ],
            'top_timer' => [
                'label'  => '1. Top urgent bar & countdown',
                'toggle' => true,
                'fields' => [
                    ['name' => 'top_timer_headline', 'type' => 'text', 'label' => 'Top headline'],
                    ['name' => 'top_timer_highlight', 'type' => 'text', 'label' => 'Highlighted urgent text'],
                    ['name' => 'countdown_hours', 'type' => 'number', 'label' => 'Countdown timer hours (e.g. 14)'],
                    ['name' => 'top_bg_gradient', 'type' => 'text', 'label' => 'Top background CSS (e.g. radial-gradient(...))'],
                    ['name' => 'phone', 'type' => 'text', 'label' => 'Helpline phone'],
                    ['name' => 'whatsapp', 'type' => 'text', 'label' => 'WhatsApp number (intl e.g. 8801...)'],
                ],
            ],
            'hero' => [
                'label'  => '2. Hero & video / price showcase',
                'toggle' => true,
                'fields' => [
                    ['name' => 'hero_headline', 'type' => 'textarea', 'label' => 'Main headline'],
                    ['name' => 'hero_subtitle', 'type' => 'textarea', 'label' => 'Subtitle / description'],
                    ['name' => 'hero_video_url', 'type' => 'text', 'label' => 'YouTube / Vimeo / Video URL (embeds responsive video)'],
                    ['name' => 'hero_image', 'type' => 'image', 'label' => 'Hero banner image (used if no video or as thumbnail)'],
                    ['name' => 'regular_price', 'type' => 'number', 'label' => 'Regular price (৳)'],
                    ['name' => 'offer_price', 'type' => 'number', 'label' => 'Special offer price (৳)'],
                    ['name' => 'offer_badge', 'type' => 'text', 'label' => 'Discount / Offer badge label'],
                    ['name' => 'cta_text', 'type' => 'text', 'label' => 'Hero CTA button text'],
                ],
            ],
            'notice' => [
                'label'  => '3. Notice / warning box',
                'toggle' => true,
                'fields' => [
                    ['name' => 'notice_heading', 'type' => 'text', 'label' => 'Notice box heading'],
                    ['name' => 'notice_text', 'type' => 'textarea', 'label' => 'Notice body (supports line breaks)'],
                ],
            ],
            'features' => [
                'label'  => '4. Items / books breakdown',
                'toggle' => true,
                'fields' => [
                    ['name' => 'features_heading', 'type' => 'text', 'label' => 'Section heading'],
                    ['name' => 'features_intro', 'type' => 'textarea', 'label' => 'Section intro'],
                    ['name' => 'features', 'type' => 'features_list', 'label' => 'Product / Book items'],
                ],
            ],
            'why' => [
                'label'  => '5. Benefits / why choose',
                'toggle' => true,
                'fields' => [
                    ['name' => 'why_heading', 'type' => 'text', 'label' => 'Section heading'],
                    ['name' => 'benefits', 'type' => 'benefits_icon_list', 'label' => 'Benefits list'],
                ],
            ],
            'reviews' => [
                'label'  => '6. Customer reviews & proof',
                'toggle' => true,
                'fields' => [
                    ['name' => 'reviews_heading', 'type' => 'text', 'label' => 'Section heading'],
                    ['name' => 'testimonials', 'type' => 'testimonials_list', 'label' => 'Customer reviews & screenshots'],
                ],
            ],
            'order' => [
                'label'  => '7. 1-Page COD checkout block',
                'toggle' => true,
                'fields' => [
                    ['name' => 'order_heading', 'type' => 'text', 'label' => 'Form heading'],
                    ['name' => 'order_subtext', 'type' => 'textarea', 'label' => 'Form subtext / instructions'],
                    ['name' => 'package_options', 'type' => 'packages_list', 'label' => 'Package choices (Package Name | Price | Qty | Badge)'],
                    ['name' => 'shipping_inside', 'type' => 'number', 'label' => 'Shipping fee inside Dhaka (৳)'],
                    ['name' => 'shipping_outside', 'type' => 'number', 'label' => 'Shipping fee outside Dhaka (৳)'],
                    ['name' => 'order_btn_text', 'type' => 'text', 'label' => 'Submit order button text'],
                    ['name' => 'guarantee_text', 'type' => 'text', 'label' => 'Trust / Guarantee label'],
                    ['name' => 'footer_text', 'type' => 'textarea', 'label' => 'Footer copyright / blurb'],
                ],
            ],
        ];
    }

    public static function campaignDefaults(): array
    {
        return [
            'meta_title'          => 'সফলতার রোডম্যাপ ৫টি মাস্টারপিস বই পাচ্ছেন মাত্র ৬৯৯ টাকায়',
            'meta_description'    => 'সফলতার রোডম্যাপ ৫টি মাস্টারপিস বই পাচ্ছেন মাত্র ৬৯৯ টাকায়। ক্যাশ অন ডেলিভারি সারা দেশে।',
            'top_timer_headline'  => 'সফলতার রোডম্যাপ ৫টি মাস্টারপিস বই পাচ্ছেন মাত্র ৬৯৯ টাকায়।',
            'top_timer_highlight' => 'সময় শেষ হলে অফার আর আসবে না — অর্ডার করুন এখনই!',
            'countdown_hours'     => 14,
            'top_bg_gradient'     => 'radial-gradient(at center center, #1877F2 28%, #0a4898 79%)',
            'phone'               => '01923443872',
            'whatsapp'            => '8801923443872',
            'hero_headline'       => 'সফলতার রোডম্যাপ ৫টি মাস্টারপিস বই পাচ্ছেন মাত্র ৬৯৯ টাকায়।',
            'hero_subtitle'       => 'ক্যারিয়ার ও বিজনেস সফলতার জন্য বিশ্বসেরা ৫টি বইয়ের সম্পূর্ণ প্যাকেজ। আজই অর্ডার করুন এবং নিজের দক্ষতাকে নিয়ে যান অন্য উচ্চতায়!',
            'hero_video_url'      => '',
            'hero_image'       => 'https://loremflickr.com/1000/440/marketplace,book,learning?random=1',
            'cta_text'         => 'অর্ডার করতে চাই — ক্যাশ অন ডেলিভারি',
            'notice_heading'      => 'সতর্কতা / বিশেষ দ্রষ্টব্য',
            'notice_text'         => "১. পণ্য হাতে পেয়ে চেক করে সম্পূর্ণ মূল্য পরিশোধ করবেন।\n২. অগ্রিম ১ টাকাও দেওয়া লাগবে না।\n৩. ডেলিভারি ম্যানের সামনে প্যাকেট খুলে চেক করার সুযোগ রয়েছে।",
            'features_heading'    => '#বুক লিস্ট — যে ৫টি মাস্টারপিস বই পাচ্ছেন',
            'features_intro'      => 'বিজনেস, মার্কেটিং ও ক্যারিয়ার গ্রোথের জন্য অত্যন্ত ফলপ্রসূ ৫টি কালজয়ী বই',
            'features'            => [
                ['title' => '১) $100 মিলিয়ন মানি মডেলস', 'body' => 'টাকা উপার্জনের কার্যকরী ফ্রেমওয়ার্ক এবং বিজনেস স্কেলিং সিক্রেটস।', 'image' => 'https://loremflickr.com/600/600/marketplace,book?random=2'],
                ['title' => '২) $100 মিলিয়ন লিডস', 'body' => 'কাস্টমার আকর্ষণ ও আনলিমিটেড কোয়ালিটি লিড জেনারেশন মেথড।', 'image' => 'https://loremflickr.com/600/600/marketplace,book?random=3'],
                ['title' => '৩) $100 মিলিয়ন অফারস', 'body' => 'এমন আকর্ষণীয় অফার তৈরি করুন যা কোনো কাস্টমার না করতে পারবে middle।', 'image' => 'https://loremflickr.com/600/600/marketplace,book?random=4'],
                ['title' => '৪) ১০০ গ্রেট মার্কেটিং আইডিয়া', 'body' => 'সহজ ও প্র্যাকটিক্যাল ১০০টি আধুনিক মার্কেটিং কৌশল।', 'image' => 'https://loremflickr.com/600/600/marketplace,book?random=5'],
                ['title' => '৫) দ্য ওয়ান পেইজ মার্কেটিং প্ল্যান', 'body' => '১ পৃষ্ঠায় আপনার সম্পূর্ণ বিজনেসের মার্কেটিং ব্লু-প্রিন্ট।', 'image' => 'https://loremflickr.com/600/600/marketplace,book?random=6'],
            ],
            'why_heading'         => 'কেন এই বইগুলো আপনার পড়া উচিত?',
            'benefits'            => [
                ['icon' => 'trending-up', 'title' => 'রিয়েল-লাইফ বিজনেস কৌশল', 'body' => 'কোনো থিওরি নয়, আন্তর্জাতিক সফল উদ্যোক্তাদের বাস্তব অভিজ্ঞতা ভিত্তিক কৌশল।'],
                ['icon' => 'zap', 'title' => 'দ্রুত আয় বৃদ্ধির ফর্মুলা', 'body' => 'মার্কেটিং এবং সেলস বাড়ানোর পরীক্ষিত গাইডলাইন।'],
                ['icon' => 'shield-check', 'title' => '১০০% কোয়ালিটি প্রিন্ট', 'body' => 'উন্নত কোয়ালিটির ৮০ জিএসএম অফসেট পেপারে চকচকে ঝকঝকে প্রিন্ট।'],
                ['icon' => 'truck', 'title' => 'ক্যাশ অন ডেলিভারি', 'body' => 'সারা বাংলাদেশে হোম ডেলিভারি — হাতে পেয়ে চেক করে টাকা দিন।'],
            ],
            'reviews_heading'     => 'আমাদের সম্মানিত পাঠকদের প্রতিক্রিয়া',
            'testimonials'        => [
                ['text' => '“বইগুলোর বাইন্ডিং ও কাগজের মান অসাধারণ। প্যাকেজিং চমৎকার ছিল।”', 'image' => 'https://loremflickr.com/400/400/marketplace,person?random=7', 'name' => 'তানভীর আহমেদ'],
                ['text' => '“এই ৫টি বই যে কোনো তরুণ উদ্যোক্তার জন্য রত্নভাণ্ডার। আমি অত্যন্ত সন্তুষ্ট।”', 'image' => 'https://loremflickr.com/400/400/marketplace,person?random=8', 'name' => 'রাকিবুল হাসান'],
                ['text' => '“ডেলিভারি অনেক দ্রুত পেয়েছি। ধন্যবাদ এতো সুন্দর কম্বো অফার দেওয়ার জন্য।”', 'image' => 'https://loremflickr.com/400/400/marketplace,person?random=9', 'name' => 'মোহাম্মদ ইমরান'],
            ],
            'order_heading'       => 'অর্ডার করতে নিচের ফর্মটি সঠিকভাবে পূরণ করুন',
            'order_subtext'       => 'ক্যাশ অন ডেলিভারি — পণ্য হাতে পেয়ে চেক করে মূল্য পরিশোধ করুন।',
            'package_options'     => [
                ['name' => '১ সেট (৫টি মাস্টারপিস বই)', 'price' => 699, 'qty' => 1, 'badge' => 'জনপ্রিয় অফার'],
                ['name' => '২ সেট (১০টি বই - স্পেশাল গিফট সহ)', 'price' => 1299, 'qty' => 2, 'badge' => 'সেরা সেভিংস'],
            ],
            'shipping_inside'     => 70,
            'shipping_outside'    => 130,
            'order_btn_text'      => 'অর্ডার কনফার্ম করুন (ক্যাশ অন ডেলিভারি)',
            'guarantee_text'      => '🔒 ১০০% ক্যাশ অন ডেলিভারি ও মানিব্যাক গ্যারান্টি',
            'footer_text'         => 'সকল স্বত্ব সংরক্ষিত © ২০২৬ | আপনার বিশ্বস্ত অনলাইন বুক মার্কেটপ্লেস',
        ];
    }

    private static function chiloraSections(): array
    {
        // Order matches the public Chilora page top → bottom (after admin-only Page setup).
        return [
            'setup' => [
                'label'  => 'Page setup',
                'toggle' => false,
                'fields' => [
                    ['name' => 'title', 'type' => 'page_title', 'label' => 'Title'],
                    ['name' => 'slug', 'type' => 'page_slug', 'label' => 'URL slug'],
                    ['name' => 'product_id', 'type' => 'page_product', 'label' => 'Linked product'],
                    ['name' => 'is_active', 'type' => 'page_active', 'label' => 'Page visible'],
                    ['name' => 'meta_title', 'type' => 'text', 'label' => 'Meta title'],
                    ['name' => 'meta_description', 'type' => 'textarea', 'label' => 'Meta description'],
                ],
            ],
            'brand' => [
                'label'  => '1. Promo bar & brand',
                'toggle' => true,
                'fields' => [
                    ['name' => 'brand', 'type' => 'text', 'label' => 'Brand name'],
                    ['name' => 'phone', 'type' => 'text', 'label' => 'Phone'],
                    ['name' => 'whatsapp', 'type' => 'text', 'label' => 'WhatsApp (intl)'],
                    ['name' => 'warranty', 'type' => 'text', 'label' => 'Warranty label'],
                    ['name' => 'promo_left', 'type' => 'text', 'label' => 'Promo bar text'],
                    ['name' => 'footer_blurb', 'type' => 'textarea', 'label' => 'Footer blurb'],
                ],
            ],
            'hero' => [
                'label'  => '2. Hero',
                'toggle' => true,
                'fields' => [
                    ['name' => 'hero_badge', 'type' => 'text', 'label' => 'Badge'],
                    ['name' => 'hero_title', 'type' => 'textarea', 'label' => 'Headline'],
                    ['name' => 'hero_subtitle', 'type' => 'textarea', 'label' => 'Supporting text'],
                    ['name' => 'hero_image', 'type' => 'image', 'label' => 'Hero image'],
                    ['name' => 'cta_text', 'type' => 'text', 'label' => 'CTA button text'],
                ],
            ],
            'offer' => [
                'label'  => '3. Offer / countdown',
                'toggle' => true,
                'fields' => [
                    ['name' => 'offer_badge', 'type' => 'text', 'label' => 'Badge'],
                    ['name' => 'offer_title', 'type' => 'text', 'label' => 'Title'],
                    ['name' => 'offer_price', 'type' => 'number', 'label' => 'Offer price (৳)'],
                    ['name' => 'regular_price', 'type' => 'number', 'label' => 'Regular price (৳)'],
                    ['name' => 'offer_end_hours', 'type' => 'number', 'label' => 'Countdown hours'],
                ],
            ],
            'features' => [
                'label'  => '4. Features',
                'toggle' => true,
                'fields' => [
                    ['name' => 'features_heading', 'type' => 'text', 'label' => 'Heading'],
                    ['name' => 'features_intro', 'type' => 'textarea', 'label' => 'Intro'],
                    ['name' => 'features', 'type' => 'features_list', 'label' => 'Feature cards'],
                ],
            ],
            'why' => [
                'label'  => '5. Why / benefits',
                'toggle' => true,
                'fields' => [
                    ['name' => 'why_heading', 'type' => 'text', 'label' => 'Heading'],
                    ['name' => 'benefits', 'type' => 'benefits_icon_list', 'label' => 'Benefits'],
                ],
            ],
            'reviews' => [
                'label'  => '6. Reviews',
                'toggle' => true,
                'fields' => [
                    ['name' => 'reviews_heading', 'type' => 'text', 'label' => 'Heading'],
                    ['name' => 'testimonials', 'type' => 'testimonials_list', 'label' => 'Testimonials'],
                ],
            ],
            'phone_cta' => [
                'label'  => '7. Phone CTA',
                'toggle' => true,
                'fields' => [
                    ['name' => 'phone_cta_title', 'type' => 'text', 'label' => 'Title'],
                ],
            ],
            'order' => [
                'label'  => '8. Order / checkout block',
                'toggle' => true,
                'fields' => [
                    ['name' => 'order_heading', 'type' => 'text', 'label' => 'Heading'],
                    ['name' => 'order_subtext', 'type' => 'textarea', 'label' => 'Subtext'],
                    ['name' => 'product_label', 'type' => 'text', 'label' => 'Product label'],
                    ['name' => 'product_thumb', 'type' => 'image', 'label' => 'Product thumb'],
                ],
            ],
        ];
    }

    private static function nurayaSections(): array
    {
        // Order matches the public Nuraya page top → bottom (single product, like Chilora).
        return [
            'setup' => [
                'label'  => 'Page setup',
                'toggle' => false,
                'fields' => [
                    ['name' => 'title', 'type' => 'page_title', 'label' => 'Title'],
                    ['name' => 'slug', 'type' => 'page_slug', 'label' => 'URL slug'],
                    ['name' => 'product_id', 'type' => 'page_product', 'label' => 'Linked product'],
                    ['name' => 'is_active', 'type' => 'page_active', 'label' => 'Page visible'],
                    ['name' => 'meta_title', 'type' => 'text', 'label' => 'Meta title'],
                    ['name' => 'meta_description', 'type' => 'textarea', 'label' => 'Meta description'],
                ],
            ],
            'brand' => [
                'label'  => '1. Promo bar & brand',
                'toggle' => true,
                'fields' => [
                    ['name' => 'brand', 'type' => 'text', 'label' => 'Brand name'],
                    ['name' => 'phone', 'type' => 'text', 'label' => 'Phone'],
                    ['name' => 'whatsapp', 'type' => 'text', 'label' => 'WhatsApp (intl)'],
                    ['name' => 'promo_text', 'type' => 'text', 'label' => 'Promo bar text'],
                    ['name' => 'footer_blurb', 'type' => 'textarea', 'label' => 'Footer blurb'],
                ],
            ],
            'hero' => [
                'label'  => '2. Hero',
                'toggle' => true,
                'fields' => [
                    ['name' => 'hero_chip', 'type' => 'text', 'label' => 'Chip'],
                    ['name' => 'hero_title', 'type' => 'textarea', 'label' => 'Headline'],
                    ['name' => 'hero_kicker', 'type' => 'text', 'label' => 'Kicker'],
                    ['name' => 'hero_subtitle', 'type' => 'textarea', 'label' => 'Supporting text'],
                    ['name' => 'hero_image', 'type' => 'image', 'label' => 'Hero / product image'],
                    ['name' => 'cta_text', 'type' => 'text', 'label' => 'CTA button text'],
                ],
            ],
            'offer' => [
                'label'  => '3. Offer / countdown',
                'toggle' => true,
                'fields' => [
                    ['name' => 'offer_badge', 'type' => 'text', 'label' => 'Badge'],
                    ['name' => 'offer_title', 'type' => 'text', 'label' => 'Title'],
                    ['name' => 'offer_price', 'type' => 'number', 'label' => 'Offer price (৳)'],
                    ['name' => 'regular_price', 'type' => 'number', 'label' => 'Regular price (৳)'],
                    ['name' => 'offer_end_hours', 'type' => 'number', 'label' => 'Countdown hours'],
                ],
            ],
            'benefits' => [
                'label'  => '4. Benefits',
                'toggle' => true,
                'fields' => [
                    ['name' => 'benefits_heading', 'type' => 'text', 'label' => 'Heading'],
                    ['name' => 'benefits', 'type' => 'benefits_items_list', 'label' => 'Benefit columns'],
                ],
            ],
            'reviews' => [
                'label'  => '5. Reviews',
                'toggle' => true,
                'fields' => [
                    ['name' => 'reviews_heading', 'type' => 'text', 'label' => 'Heading'],
                    ['name' => 'testimonials', 'type' => 'testimonials_list', 'label' => 'Testimonials'],
                ],
            ],
            'order' => [
                'label'  => '6. Order / checkout block',
                'toggle' => true,
                'fields' => [
                    ['name' => 'order_heading', 'type' => 'text', 'label' => 'Heading'],
                    ['name' => 'order_subtext', 'type' => 'textarea', 'label' => 'Subtext'],
                    ['name' => 'product_label', 'type' => 'text', 'label' => 'Product label'],
                    ['name' => 'product_thumb', 'type' => 'image', 'label' => 'Product thumb'],
                ],
            ],
        ];
    }

    public static function chiloraDefaults(): array
    {
        return [
            'brand'            => 'Chilora',
            'phone'            => '01600-000000',
            'whatsapp'         => '8801600000000',
            'warranty'         => '৬ মাসের ওয়ারেন্টি',
            'promo_left'       => 'ক্যাশ অন ডেলিভারি সারা দেশে',
            'meta_title'       => 'Chilora — টকিং লার্নিং বুক + প্র্যাকটিস কিট',
            'meta_description' => 'রিচার্জেবল টকিং লার্নিং বুক — ক্যাশ অন ডেলিভারি।',
            'hero_badge'       => 'আদর্শ মা-বাবার সেরা উপহার',
            'hero_title'       => "বাচ্চাদের মোবাইল আসক্তি কমাতে দিন\nমেধা বিকাশের সেরা উপহার",
            'hero_subtitle'    => 'রিচার্জেবল ইন্টেলিজেন্ট বুক – আপডেটেড ভার্সন! বারবার ব্যাটারি কেনার ঝামেলা নেই। সাথে ৬ মাসের ওয়ারেন্টি।',
            'hero_image'       => 'https://loremflickr.com/720/560/marketplace,book,kids?random=10',
            'cta_text'         => 'অর্ডার করতে চাই',
            'offer_badge'      => 'সীমিত সময়ের বিশেষ অফার',
            'offer_title'      => 'অফার শেষ হওয়ার আগে অর্ডার করুন',
            'offer_price'      => 1200,
            'regular_price'    => 1650,
            'offer_end_hours'  => 18,
            'features_heading' => 'একটি বইয়েই সব কিছু',
            'features_intro'   => 'বাংলা–ইংরেজি–আরবি শিক্ষা, ইসলামিক কন্টেন্ট, রেকর্ডিং ও লেখার প্র্যাকটিস — সব একসাথে।',
            'features'         => [
                ['title' => 'Rechargeable & Hassle-Free', 'body' => 'USB Type-C চার্জিং — ব্যাটারি কেনার ঝামেলা নেই।'],
                ['title' => 'রেকর্ডিং বাটন', 'body' => 'শিশু যা বলবে, বইটি রিপিট করবে — মজার ছলে শেখা।'],
                ['title' => 'ইন্টারেক্টিভ প্রশ্নোত্তর', 'body' => 'স্মৃতিশক্তি ও মনোযোগ বাড়ায় প্রশ্ন বাটনের মাধ্যমে।'],
                ['title' => '৪০ পৃষ্ঠায় ৫২+ কন্টেন্ট', 'body' => 'সমৃদ্ধ পাঠ্য — একটি বইয়েই সম্পূর্ণ প্যাকেজ।'],
                ['title' => 'Male & Female Dual Voice', 'body' => 'দ্বৈত কণ্ঠস্বর — শোনার অভিজ্ঞতা আরও সমৃদ্ধ।'],
                ['title' => 'Book Lock System', 'body' => 'শক্ত হ্যান্ডেল ও পরিষ্কার সাউন্ড কোয়ালিটি।'],
            ],
            'why_heading' => 'শিশুর জন্য সেরা মেধা বিকাশের সঙ্গী',
            'benefits'    => [
                ['icon' => 'globe', 'title' => 'তিন ভাষায় শিক্ষা', 'body' => 'বাংলা, ইংরেজি ও আরবি বর্ণমালা ও উচ্চারণ একই বইতে।'],
                ['icon' => 'mic', 'title' => 'কথা বলা ও উচ্চারণ চর্চা', 'body' => 'স্পষ্ট উচ্চারণ গাইড — শিশু নিজে নিজে শিখতে পারে।'],
                ['icon' => 'palette', 'title' => 'আনন্দময় বিষয়বস্তু', 'body' => 'ভিজ্যুয়াল উপস্থাপনা যা কৌতূহল ও শেখার আগ্রহ বাড়ায়।'],
                ['icon' => 'edit', 'title' => 'লেখা ও পড়ার অনুশীলন', 'body' => 'মার্কার পেন দিয়ে হাতে-কলমে শেখার বাস্তব মজা।'],
                ['icon' => 'shield', 'title' => 'টেকসই ও নিরাপদ বডি', 'body' => 'প্রিমিয়াম ABS প্লাস্টিক বডি — শিশুদের জন্য দীর্ঘস্থায়ী।'],
                ['icon' => 'battery', 'title' => 'রিচার্জেবল ইন্টেলিজেন্ট বুক', 'body' => 'আপডেটেড ভার্সন — বারবার ব্যাটারি কেনার দরকার নেই।'],
            ],
            'reviews_heading' => 'আমাদের সম্মানিত কাস্টমারের মতামত',
            'testimonials'    => [
                ['image' => 'https://loremflickr.com/640/480/marketplace,person?random=11', 'text' => '“বাচ্চা এখন বই নিয়ে মেতে থাকে — মোবাইল দেখার অভ্যাস অনেকটাই কমে গেছে।”'],
                ['image' => 'https://loremflickr.com/640/480/marketplace,person?random=12', 'text' => '“সাউন্ড অনেক স্পষ্ট এবং চার্জ অনেকক্ষণ থাকে। প্যাকেজিং দারুণ ছিল।”'],
                ['image' => 'https://loremflickr.com/640/480/marketplace,person?random=13', 'text' => '“ওয়াটার বুক ফ্রি পেয়ে বাচ্চারা ভীষণ খুশি — দুই ভাইবোন একসাথে খেলছে।”'],
            ],
            'phone_cta_title' => 'ফোনে অর্ডার করতে চান? সরাসরি কল করুন',
            'order_heading'   => 'অর্ডার করতে নিচের বাটনে ক্লিক করুন',
            'order_subtext'   => 'চেকআউট পেজে গিয়ে ঠিকানা ও পেমেন্ট সম্পন্ন করুন। ক্যাশ অন ডেলিভারি উপলব্ধ।',
            'product_label'   => 'Chilora টকিং বুক + ওয়াটার প্র্যাকটিস বুক ফ্রি',
            'product_thumb'   => 'https://loremflickr.com/120/120/marketplace,book,kids?random=14',
            'footer_blurb'    => 'শিশুদের জন্য আনন্দময় শিক্ষার সঙ্গী — টকিং লার্নিং বুক ও প্র্যাকটিস কিট।',
        ];
    }

    public static function nurayaDefaults(): array
    {
        return [
            'brand'            => 'Nuraya',
            'phone'            => '01700-000000',
            'whatsapp'         => '8801700000000',
            'promo_text'       => '🌸 ১০০% সুতি অরবিন্দ ভয়েলের সালাত হিজাব 🌸',
            'meta_title'       => 'Nuraya — সুতি সালাত হিজাব',
            'meta_description' => '১০০% সুতি অরবিন্দ ভয়েল সালাত হিজাব। ক্যাশ অন ডেলিভারি।',
            'hero_chip'        => '✨ সেরা পছন্দ',
            'hero_title'       => "১০০% সুতি অরবিন্দ\nভয়েলের সালাত হিজাব",
            'hero_kicker'      => 'আপনার নামাজে আনবে আরও প্রশান্তি ও স্বাচ্ছন্দ্য',
            'hero_subtitle'    => 'হালকা, ঢিলেঢালা ও আরামদায়ক—ঘাম কম, স্বস্তি বেশি। সারা দেশে ক্যাশ অন ডেলিভারি।',
            'hero_image'       => 'landing/nuraya/img/hero.jpg',
            'cta_text'         => 'এখনই অর্ডার করুন',
            'offer_badge'      => '🔥 সীমিত সময়ের অফার!',
            'offer_title'      => 'অফার শেষ হওয়ার আগেই অর্ডার করুন',
            'offer_price'      => 550,
            'regular_price'    => 630,
            'offer_end_hours'  => 23,
            'benefits_heading' => 'এই সালাত হিজাব আপনাকে দেবে পূর্ণ স্বস্তি ও মনোযোগ',
            'benefits'         => [
                ['title' => 'ফেব্রিক কোয়ালিটি', 'items' => "১০০% সুতি অরবিন্দ ভয়েল\nহালকা ও নরম টেক্সচার\nবাতাস চলাচল উপযোগী"],
                ['title' => 'ব্যবহার উপযোগিতা', 'items' => "সালাত আদায়ের জন্য পারফেক্ট\nঘরে দৈনন্দিন ব্যবহার\nপরিপূর্ণ কাভারেজ"],
                ['title' => 'সাইজ ডিটেইলস', 'items' => "ফ্রন্ট: ৪৪”\nব্যাক: ৪৮”\nলং সালাত হিজাব"],
            ],
            'reviews_heading' => 'হাজারো সন্তুষ্ট ক্রেতার রিভিউ',
            'testimonials'    => [
                ['text' => '“কাপড় খুব নরম, নামাজে গরম লাগে না।”', 'image' => 'landing/nuraya/img/review-1.jpg'],
                ['text' => '“রঙ হুবহু ছবির মতো। মাকে উপহার দিয়েছি।”', 'image' => 'landing/nuraya/img/review-2.jpg'],
                ['text' => '“কাভারেজ ভালো, ডেলিভারিও সময়মতো।”', 'image' => 'landing/nuraya/img/review-3.jpg'],
            ],
            'order_heading' => 'অর্ডার সম্পন্ন করুন',
            'order_subtext' => 'চেকআউট পেজে গিয়ে ঠিকানা ও পেমেন্ট সম্পন্ন করুন। ক্যাশ অন ডেলিভারি উপলব্ধ।',
            'product_label' => 'Nuraya Soft Veil সালাত হিজাব',
            'product_thumb' => 'landing/nuraya/img/thumb.jpg',
            'footer_blurb'  => 'পবিত্রতা, আরাম ও কোমল কাপড়ের সমন্বয়ে — একটি পারফেক্ট সালাত হিজাব।',
        ];
    }
}
