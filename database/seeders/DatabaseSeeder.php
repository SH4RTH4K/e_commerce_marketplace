<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Category;
use App\Models\Feature;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUsers();
        $this->seedSettings();
        $categories = $this->seedCategories();
        $this->seedFeatures();
        $this->seedCoupons();
        $this->call(BannerSeeder::class);
        $this->call(ContactFormFieldSeeder::class);
        $this->seedProducts($categories);
        $this->seedReviews();
        $this->seedOrders();
        $this->call(LandingPageSeeder::class);
        $this->call(ContactMessageSeeder::class);
    }

    private function seedUsers(): void
    {
        $admin = User::firstOrNew(['email' => 'admin@sharthak.test']);
        $admin->fill([
            'name'              => 'Store Admin',
            'username'          => 'admin',
            'role'              => 'admin',
            'phone'             => '+880 1700-000000',
            'email_verified_at' => now(),
        ]);

        if (! $admin->exists) {
            // A one-way hash provisions the initial credential without storing plaintext.
            $admin->password = '$2y$12$.yflhn3vghuBscioneRlauuRDCD3EM.fu7MczRt4D2X2JEs0G5WUC';
        }

        $admin->save();

        User::updateOrCreate(
            ['email' => 'customer@sharthak.test'],
            [
                'name'              => 'Rahim Uddin',
                'password'          => Hash::make('password'),
                'role'              => 'customer',
                'phone'             => '01711-111111',
                'address'           => 'House 12, Road 5, Dhanmondi',
                'city'              => 'Dhaka',
                'postal_code'       => '1205',
                'email_verified_at' => now(),
            ]
        );
    }

    private function seedSettings(): void
    {
        $settings = [
            'site_name'                => 'SHARTHAK',
            'tagline'                  => 'Your everyday online marketplace',
            'logo'                     => '',
            'favicon'                  => '',
            'footer_text'              => 'Your everyday online marketplace - millions of products, flash deals and vouchers, delivered across the country.',
            'contact_phone'            => '+880 1700-000000',
            'whatsapp_number' => '01700000000',
            'messenger_page' => 'sharthak.page',
            'contact_email'            => 'support@sharthak.test',
            'contact_address'          => 'Dhaka 1207, Bangladesh',
            'contact_hours'            => 'Sat-Thu, 9am - 9pm',
            'contact_title'            => 'Get in touch',
            'contact_intro'            => 'We usually reply within a few hours.',
            'search_placeholder'       => 'Search in SHARTHAK...',
            'facebook_url'             => 'https://facebook.com/',
            'instagram_url'            => 'https://instagram.com/',
            'twitter_url'              => 'https://twitter.com/',
            'bkash_number'             => '01700000000',
            'nagad_number'             => '01800000000',
            'rocket_number'            => '01900000000',
            'pay_cod_enabled' => '1',
            'pay_bkash_enabled' => '1',
            'pay_nagad_enabled' => '1',
            'pay_rocket_enabled' => '1',
            'show_cards_in_footer'     => '0',
            'shipping_inside_dhaka'    => '60',
            'shipping_outside_dhaka'   => '120',
            'tax_percent'              => '5',
            'shipping_inside_label'    => 'Inside Dhaka',
            'shipping_outside_label'   => 'Outside Dhaka',
            'currency_symbol'          => "\u{09F3}",
            'currency_code'            => 'BDT',
            'default_meta_title'       => 'SHARTHAK - Online Shopping Marketplace',
            'default_meta_description' => 'SHARTHAK - flash deals, vouchers and millions of products. Fast delivery and Cash on Delivery across Bangladesh.',
            'default_meta_keywords'    => 'SHARTHAK, marketplace, online shopping, flash sale, vouchers, bangladesh',
            'tracking_gtm_id'          => '',
            'tracking_ga4_id'          => '',
            'tracking_meta_pixel_id'   => '',
            'otp_enabled'              => '1',
            'show_brands_marquee'      => '1',
            'brands_marquee'           => 'Volt, Pixel, Nimbus, Aero, Quanta, Core',
            'header_promo_text'        => "\u{1F4F1} Save more on the SHARTHAK app",
            'header_promo_link'        => '/shop',
            'shop_subtitle'            => 'Browse electronics, fashion, home & living and more - flash deals every day.',
            'flash_sale_ends_at'       => now()->addDays(2)->format('Y-m-d H:i:s'),
            'deal_ends_at'             => now()->addDays(5)->format('Y-m-d H:i:s'),
            'delivery_eta_text'        => 'Estimated delivery in 2-3 days',
            'homepage_tab_count'       => '8',
            'home_categories_title'    => 'Categories',
            'home_hot_deal_title'      => 'Flash Sale',
            'home_featured_title'      => 'Just For You',
            'home_deal_week_title'     => 'Latest Products',
            'home_tabs_title'          => 'Best Sellers',
            'home_brands_label'        => 'Official brand stores',
            'home_view_more_label'     => 'View all',
            'default_cta_text'         => 'ORDER NOW',
            'product_cta_action'       => 'checkout',
            'hero_fallback_badge'      => '11.11 SALE',
            'hero_fallback_title'      => 'Up to 70% off everything',
            'hero_fallback_subtitle'   => 'Millions of products. Free shipping vouchers. New deals every hour.',
            'terms_content'            => '',
            'privacy_content'          => '',
            'mail_mailer'              => 'log',
            'mail_host'                => '',
            'mail_port'                => '587',
            'mail_username'            => '',
            'mail_password'            => '',
            'mail_encryption'          => 'tls',
            'mail_from_address'        => 'no-reply@sharthak.test',
            'mail_from_name'           => 'SHARTHAK',
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Setting::forgetCache();
        // Wipe uploaded branding files so seed truly restores default logo/favicon.
        $brandingDir = public_path('uploads/branding');
        if (is_dir($brandingDir)) {
            foreach (scandir($brandingDir) ?: [] as $file) {
                if ($file === '.' || $file === '..' || $file === '.gitkeep' || $file === '.htaccess') {
                    continue;
                }
                $full = $brandingDir . DIRECTORY_SEPARATOR . $file;
                if (is_file($full)) {
                    @unlink($full);
                }
            }
        }
    }

    private function seedCategories(): array
    {
        $data = [
            ['Electronics', "\u{1F4F1}", 'Phones, laptops, gadgets and more.'],
            ['Fashion', "\u{1F457}", 'Clothing, shoes and accessories for everyone.'],
            ['Home & Living', '', 'Furniture, decor and home essentials.'],
            ['Health & Beauty', "\u{1F484}", 'Skincare, makeup and wellness products.'],
            ['Babies & Toys', '', 'Toys, baby care and kids essentials.'],
            ['Groceries', "\u{1F6D2}", 'Everyday pantry and household staples.'],
            ['Sports', "\u{26BD}", 'Sports gear, outdoor and fitness.'],
            ['Automotive', "\u{1F697}", 'Car accessories and auto care.'],
            ['Books & Office', "\u{1F4DA}", 'Books, stationery and office supplies.'],
            ['Phones', "\u{1F4F1}", 'Smartphones and mobile accessories.'],
            ['Laptops', "\u{1F4BB}", 'Notebooks and computing essentials.'],
            ['Watches', "\u{231A}", 'Smart watches and classic timepieces.'],
        ];

        $categories = [];
        foreach ($data as $i => [$name, $icon, $desc]) {
            $slug = Str::slug($name);
            $categories[$slug] = Category::updateOrCreate(
                ['slug' => $slug],
                [
                    'name'             => $name,
                    'icon'             => $icon,
                    'image'            => "https://loremflickr.com/400/400/marketplace,category,{$slug}",
                    'description'      => $desc,
                    'position'         => $i,
                    'is_active'        => true,
                    'meta_title'       => "{$name} - Buy Online in Bangladesh | SHARTHAK",
                    'meta_description' => "Shop {$name} at SHARTHAK marketplace. {$desc} Fast delivery and Cash on Delivery.",
                    'meta_keywords'    => Str::slug($name, ', ') . ", {$name}, online marketplace bangladesh, SHARTHAK",
                ]
            );
        }

        return $categories;
    }

    private function seedFeatures(): void
    {
        $features = [
            ['M3 7h11v8H3zM14 10h4l3 3v2h-7', 'Free Delivery', "Orders over \u{09F3}2,000"],
            ['M3 3h2l2 12h11l2-8H7', "\u{09F3}500 Vouchers", 'Collect & save'],
            ['M3 12a9 9 0 1 0 9-9 M3 5v4h4', 'Easy Returns', '7-day policy'],
            ['M12 3l8 4v5c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V7z', 'SHARTHAK Mall', '100% authentic brands'],
        ];
        foreach ($features as $i => [$icon, $title, $subtitle]) {
            Feature::updateOrCreate(
                ['title' => $title],
                ['icon' => $icon, 'subtitle' => $subtitle, 'position' => $i, 'is_active' => true]
            );
        }
    }

    private function seedCoupons(): void
    {
        $coupons = [
            [
                'code'             => 'SAVE10',
                'description'      => "10% off orders over \u{09F3}1,000",
                'type'             => 'percentage',
                'value'            => 10,
                'min_order_amount' => 1000,
                'max_discount'     => 500,
                'max_uses'         => 100,
                'expires_at'       => now()->addMonths(6),
            ],
            [
                'code'             => 'FLAT200',
                'description'      => "\u{09F3}200 off orders over \u{09F3}2,000",
                'type'             => 'fixed',
                'value'            => 200,
                'min_order_amount' => 2000,
                'max_uses'         => 50,
                'expires_at'       => now()->addMonths(3),
            ],
            [
                'code'             => 'WELCOME',
                'description'      => '15% welcome discount for new shoppers',
                'type'             => 'percentage',
                'value'            => 15,
                'min_order_amount' => 500,
                'max_discount'     => 800,
                'max_uses'         => null,
                'expires_at'       => null,
            ],
        ];

        foreach ($coupons as $data) {
            Coupon::updateOrCreate(
                ['code' => $data['code']],
                array_merge($data, ['is_active' => true, 'used_count' => 0])
            );
        }
    }

    private function seedProducts(array $categories): void
    {
        // [name, brand, regularBdt, saleBdt, defaultUnit, variantType, options[], featured, new, best]
        $catalog = [
            'electronics' => [
                ['Wireless Noise-Cancel Headphones', 'SoundHub', 16100, 12900, '1 unit', 'Color', ['Black', 'White'], true, true, true],
                ['Bluetooth Speaker Waterproof', 'Aero', 2500, 1990, '1 unit', 'Color', ['Black', 'Blue'], true, false, true],
                ['Smart Home Hub WiFi Controller', 'Nimbus', 9900, 8900, '1 unit', 'Color', ['White'], true, true, false],
                ['Action Camera 4K Bundle', 'Pixel', 12000, 7200, '1 unit', 'Color', ['Black'], false, true, true],
            ],
            'phones' => [
                ['Galaxy A55 5G 256GB', 'Samsung', 52000, 47900, '1 unit', 'Color', ['Awesome Navy', 'Lilac'], true, true, true],
                ['Redmi Note 13 Pro', 'Xiaomi', 32000, 28900, '1 unit', 'Color', ['Black', 'Green'], true, false, true],
                ['Wireless Earbuds Pro', 'SoundHub', 2990, 1499, '1 unit', 'Color', ['White', 'Black'], true, true, true],
                ['Phone Case MagSafe Clear', 'Core', 2200, 999, '1 unit', 'Color', ['Clear', 'Smoke'], false, false, true],
            ],
            'laptops' => [
                ['Tablet Pro 11 inch 128GB', 'Volt', 45700, 32000, '1 unit', 'Storage', ['128GB', '256GB'], true, true, true],
                ['Slim 15 Everyday Laptop', 'HP', 45000, 41000, '1 unit', 'Color', ['Silver', 'Blue'], true, false, true],
                ['Mechanical Keyboard Hot-swap RGB', 'Quanta', 5500, 4400, '1 unit', 'Color', ['Black'], true, true, false],
                ['Gaming Mouse RGB 16000 DPI', 'Pixel', 3500, 2900, '1 unit', 'Color', ['Black'], false, true, true],
            ],
            'fashion' => [
                ["Men's Casual Leather Sneakers", 'Aero', 6500, 5600, '1 pair', 'Size', ['40', '41', '42', '43'], true, true, true],
                ['Designer Travel Backpack Waterproof', 'Nimbus', 5500, 4800, '1 unit', 'Color', ['Black', 'Grey'], true, false, true],
                ['Polarized Aviator Sunglasses UV400', 'Volt', 2900, 2500, '1 unit', 'Color', ['Gold', 'Black'], false, true, false],
                ['Cotton Oversized Tee Pack', 'Core', 1800, 1290, '1 pack', 'Size', ['M', 'L', 'XL'], true, false, true],
            ],
            'home-living' => [
                ['Robot Vacuum Cleaner Smart', 'Quanta', 24900, 19900, '1 unit', 'Color', ['White'], true, true, true],
                ['Stainless Steel Water Bottle 1L', 'Aero', 2200, 1200, '1 unit', 'Color', ['Silver', 'Black'], true, false, true],
                ['LED Desk Lamp Touch Dimmer', 'Pixel', 3200, 2490, '1 unit', 'Color', ['White', 'Black'], false, true, false],
                ['Memory Foam Pillow Set', 'Nimbus', 4500, 3900, '1 set', 'Size', ['Standard'], true, false, true],
            ],
            'watches' => [
                ['Smart Band Fitness Tracker', 'Volt', 3900, 2500, '1 unit', 'Color', ['Black', 'Pink'], true, true, true],
                ['Classic Analog Leather Watch', 'Core', 8900, 7570, '1 unit', 'Color', ['Brown', 'Black'], false, true, false],
                ['Galaxy Watch Style Strap', 'Samsung', 2500, 1990, '1 unit', 'Color', ['Black', 'White'], true, false, true],
                ['Kids Digital Watch', 'Pixel', 1500, 990, '1 unit', 'Color', ['Blue', 'Pink'], false, true, false],
            ],
            'health-beauty' => [
                ['Vitamin C Serum 30ml', 'GlowLab', 1800, 1290, '1 bottle', 'Size', ['30ml'], true, true, true],
                ['Electric Toothbrush Kit', 'SmileCo', 4500, 3900, '1 kit', 'Color', ['White'], true, false, true],
                ['Hair Dryer Ionic Compact', 'BeautyAir', 3200, 2790, '1 unit', 'Color', ['Pink', 'Black'], false, true, false],
                ['Face Mask Sheet Pack 10', 'GlowLab', 900, 650, '1 pack', 'Type', ['Hydrating'], true, false, true],
            ],
            'sports' => [
                ['Yoga Mat Non-Slip 6mm', 'FitZone', 2200, 1690, '1 unit', 'Color', ['Purple', 'Blue'], true, true, false],
                ['Dumbbell Set 10kg Pair', 'FitZone', 4500, 3990, '1 pair', 'Weight', ['10kg'], true, false, true],
                ['Sports Water Bottle 750ml', 'Aero', 1200, 890, '1 unit', 'Color', ['Green', 'Black'], false, true, true],
                ['Resistance Band Set', 'Core', 1500, 1190, '1 set', 'Level', ['Light', 'Medium', 'Heavy'], true, true, false],
            ],
        ];

        $ratings = [4.4, 4.6, 4.7, 4.8, 4.9];

        foreach ($catalog as $catSlug => $items) {
            if (! isset($categories[$catSlug])) {
                continue;
            }
            $category = $categories[$catSlug];
            foreach ($items as $item) {
                [$name, $brand, $reg, $sale, $unit, $variantType, $options, $featured, $new, $best] = $item;
                $slug = Str::slug($name);
                $seed = 'bp-' . $slug;

                $product = Product::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'category_id'       => $category->id,
                        'name'              => $name,
                        'sku'               => 'SZ-' . strtoupper(Str::substr(md5($slug), 0, 6)),
                        'brand'             => $brand,
                        'short_description' => $this->shortDescription($name, $category->name),
                        'description'       => $this->longDescription($name, $brand, $category->name),
                        'regular_price'     => $reg,
                        'sale_price'        => $sale,
                        'stock_quantity'    => random_int(20, 200),
                        'unit'              => $unit,
                        'is_published'      => true,
                        'is_featured'       => $featured,
                        'is_new_arrival'    => $new,
                        'is_best_seller'    => $best,
                        'is_flash_sale'     => false,
                        'flash_sale_position'=> 0,
                        'flash_sale_progress'=> 50,
                        'rating'            => $ratings[array_rand($ratings)],
                        'reviews_count'     => random_int(24, 480),
                        'meta_title'        => "{$name} - {$brand} | Buy Online at SHARTHAK",
                        'meta_description'  => "Buy {$name} by {$brand} at SHARTHAK marketplace. " . $this->shortDescription($name, $category->name) . ' Fast delivery and Cash on Delivery.',
                        'meta_keywords'     => strtolower("{$name}, {$brand}, {$category->name}, buy {$name} online, SHARTHAK, bangladesh marketplace"),
                    ]
                );

                $product->images()->delete();
                for ($k = 1; $k <= 4; $k++) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'path'       => "https://loremflickr.com/800/800/marketplace,product?random={$product->id}{$k}",
                        'alt'        => "{$name} - {$brand} view {$k}",
                        'is_primary' => $k === 1,
                        'position'   => $k,
                    ]);
                }

                $product->variants()->delete();
                foreach ($options as $pos => $value) {
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'type'       => $variantType,
                        'value'      => $value,
                        'price_delta' => $this->variantPriceDelta((string) $variantType, (string) $value, (int) $pos),
                        'stock'      => random_int(5, 50),
                        'position'   => $pos,
                    ]);
                }
            }
        }

        // Curated flash-sale list only (not every discounted product)
        Product::query()
            ->where('is_flash_sale', true)
            ->update(['is_flash_sale' => false, 'flash_sale_position' => 0, 'flash_sale_progress' => 50]);

        $pos = 0;
        Product::query()
            ->whereNotNull('sale_price')
            ->whereColumn('sale_price', '<', 'regular_price')
            ->orderBy('id')
            ->take(5)
            ->get()
            ->each(function (Product $product) use (&$pos) {
                $product->update([
                    'is_flash_sale'       => true,
                    'flash_sale_position' => $pos++,
                    'flash_sale_progress' => random_int(35, 80),
                ]);
            });
    }

    private function seedReviews(): void
    {
        $products = Product::query()->take(12)->get();
        if ($products->isEmpty()) {
            return;
        }

        $samples = [
            [5, 'Excellent quality', 'Exactly as described. Fast delivery and well packed. Highly recommended.'],
            [4, 'Good value', 'Solid product for the price. Packaging was neat and delivery was on time.'],
            [5, 'Loved it', 'Great store experience. Will order again from SHARTHAK.'],
            [3, 'Okay overall', 'Product is fine but shipping took a bit longer than expected.'],
            [4, 'Happy with purchase', 'Works as expected. Customer support was helpful when I asked about size.'],
            [5, 'Perfect', 'Premium feel and authentic brand. COD was convenient.'],
        ];

        $authors = [
            ['Rahim Uddin', 'rahim@example.com'],
            ['Ayesha Akter', 'ayesha@example.com'],
            ['Karim Mia', 'karim@example.com'],
            ['Nadia Islam', 'nadia@example.com'],
            ['Sumon Hossain', 'sumon@example.com'],
            ['Tanvir Ahmed', 'tanvir@example.com'],
        ];

        foreach ($products as $i => $product) {
            ProductReview::where('product_id', $product->id)->delete();

            $count = random_int(2, 4);
            for ($n = 0; $n < $count; $n++) {
                [$rating, $title, $body] = $samples[($i + $n) % count($samples)];
                [$name, $email] = $authors[($i + $n) % count($authors)];

                ProductReview::create([
                    'product_id'           => $product->id,
                    'user_id'              => null,
                    'author_name'          => $name,
                    'author_email'         => $email,
                    'rating'               => $rating,
                    'title'                => $title,
                    'body'                 => $body,
                    'status'               => ProductReview::STATUS_APPROVED,
                    'is_verified_purchase' => (bool) random_int(0, 1),
                    'approved_at'          => now()->subDays(random_int(1, 20)),
                    'created_at'           => now()->subDays(random_int(1, 30)),
                ]);
            }

            // One pending sample on the first few products
            if ($i < 3) {
                ProductReview::create([
                    'product_id'           => $product->id,
                    'author_name'          => 'Guest Buyer',
                    'author_email'         => 'guest'.$i.'@example.com',
                    'rating'               => 4,
                    'title'                => 'Waiting for approval',
                    'body'                 => 'Looks good so far. Hoping this gets published soon after moderation.',
                    'status'               => ProductReview::STATUS_PENDING,
                    'is_verified_purchase' => false,
                ]);
            }

            $product->recalculateRatingFromReviews();
        }
    }

    private function shortDescription(string $name, string $category): string
    {
        return "Shop {$name} from our {$category} collection - authentic products at SHARTHAK marketplace.";
    }

    private function longDescription(string $name, string $brand, string $category): string
    {
        return implode("\n\n", [
            "The {$name} by {$brand} is a top pick in our {$category} category. Shop genuine products at SHARTHAK with secure checkout and nationwide delivery.",
            'Every listing is checked before it goes live. Choose your preferred option at checkout and enjoy secure packaging from warehouse to doorstep.',
            "Part of the SHARTHAK {$category} collection, this product is backed by our easy return policy. Order today and pay conveniently with Cash on Delivery or mobile banking anywhere in Bangladesh.",
            'Warranty and care instructions follow the brand guidelines included with your order.',
        ]);
    }

    private function seedOrders(): void
    {
        $products = Product::with('images')->get();
        if ($products->isEmpty()) {
            return;
        }

        $customers = [
            ['Rahim Uddin', '01711-111111', 'rahim@example.com', 'House 12, Road 5, Dhanmondi', 'Dhaka', 'inside_dhaka'],
            ['Karim Mia', '01822-222222', 'karim@example.com', 'Flat 4B, Kazir Dewri', 'Chattogram', 'outside_dhaka'],
            ['Ayesha Akter', '01933-333333', 'ayesha@example.com', 'House 30, Uttara Sector 7', 'Dhaka', 'inside_dhaka'],
            ['Sumon Hossain', '01644-444444', 'sumon@example.com', 'Zindabazar Main Road', 'Sylhet', 'outside_dhaka'],
            ['Nadia Islam', '01555-555555', 'nadia@example.com', 'House 9, Mehedibag', 'Dhaka', 'inside_dhaka'],
            ['Tanvir Ahmed', '01366-666666', 'tanvir@example.com', 'College Road', 'Rajshahi', 'outside_dhaka'],
        ];

        $scenarios = [
            ['pending',    'bkash',  'pending'],
            ['pending',    'nagad',  'pending'],
            ['confirmed',  'bkash',  'verified'],
            ['processing', 'rocket', 'verified'],
            ['shipped',    'cod',    'verified'],
            ['delivered',  'nagad',  'verified'],
            ['cancelled',  'bkash',  'rejected'],
        ];

        $insideFee  = (float) setting('shipping_inside_dhaka', 60);
        $outsideFee = (float) setting('shipping_outside_dhaka', 120);
        $taxPct     = (float) setting('tax_percent', 5);

        foreach ($scenarios as $n => [$status, $method, $payStatus]) {
            [$cname, $phone, $email, $addr, $city, $zone] = $customers[$n % count($customers)];

            $order = new Order([
                'order_number'          => 'ORD-' . now()->subDays($n)->format('ymd') . '-' . strtoupper(Str::random(4)),
                'customer_name'         => $cname,
                'customer_phone'        => $phone,
                'customer_email'        => $email,
                'shipping_address'      => $addr,
                'city'                  => $city,
                'postal_code'           => (string) random_int(1000, 9999),
                'shipping_zone'         => $zone,
                'payment_method'        => $method,
                'payment_status'        => $payStatus,
                'status'                => $status,
                'payment_sender_number' => $method === 'cod' ? null : $phone,
                'payment_txn_id'        => $method === 'cod' ? null : strtoupper(Str::random(10)),
                'shipping_charge'       => $zone === 'inside_dhaka' ? $insideFee : $outsideFee,
            ]);
            $order->created_at = now()->subDays($n)->subHours(random_int(1, 10));
            $order->updated_at = $order->created_at;
            $order->save();

            $subtotal = 0;
            foreach ($products->random(random_int(1, 3)) as $product) {
                $qty  = random_int(1, 3);
                $unit = (float) $product->price;
                $line = $unit * $qty;
                $subtotal += $line;

                $variantLabel = null;
                $firstVariant = $product->variants()->orderBy('position')->first();
                if ($firstVariant) {
                    $variantLabel = $firstVariant->type . ': ' . $firstVariant->value;
                } elseif ($product->unit) {
                    $variantLabel = $product->unit;
                }

                OrderItem::create([
                    'order_id'     => $order->id,
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'image'        => $product->primaryImage()?->path,
                    'variant'      => $variantLabel,
                    'unit_price'   => $unit,
                    'quantity'     => $qty,
                    'line_total'   => $line,
                ]);
            }

            $tax = round($subtotal * $taxPct / 100);
            $order->update([
                'subtotal' => $subtotal,
                'tax'      => $tax,
                'total'    => $subtotal + $order->shipping_charge + $tax,
            ]);
        }
    }

        /** Demo price adjustments so choosing a different variant changes the price. */
    private function variantPriceDelta(string $type, string $value, int $position = 0): float
    {
        $type = strtolower(trim($type));
        $value = trim($value);
        $upper = strtoupper($value);
        $lower = strtolower($value);

        if ($type === 'size') {
            $mapped = match ($upper) {
                'XXL', '2XL', '3XL' => 300.0,
                'XL' => 200.0,
                'L' => 100.0,
                'M' => 50.0,
                'S', 'XS' => 0.0,
                'PREMIUM', '8-PIECE', '10A' => 200.0,
                'BASIC', '5-PIECE', '6A', 'STANDARD', 'MEDIUM' => 0.0,
                default => null,
            };
            if ($mapped !== null) {
                return $mapped;
            }
            if (preg_match('/(\\d+(?:\\.\\d+)?)\\s*mm\\b/i', $value, $m)) {
                return ((float) $m[1]) * 5.0;
            }
            if (preg_match('/(\\d+(?:\\.\\d+)?)\\s*ml\\b/i', $value, $m)) {
                return ((float) $m[1]) * 0.5;
            }
            if (preg_match('/(\\d+(?:\\.\\d+)?)\\s*l\\b/i', $value, $m)) {
                return ((float) $m[1]) * 80.0;
            }
            if (preg_match('/^(\\d+(?:\\.\\d+)?)$/', $value, $m)) {
                return ((float) $m[1]) * 10.0;
            }
        }

        if ($type === 'color') {
            $premium = match ($lower) {
                'leather brown', 'navy', 'olive', 'grey', 'gray',
                'gold/green', 'cosmic red', 'nickel/fuchsia', 'copper',
                'forest green', 'ocean blue', 'neon', 'rgb' => 150.0,
                default => null,
            };
            if ($premium !== null) {
                return $premium;
            }
        }

        if (in_array($type, ['weight', 'pack'], true)) {
            // Normalize mass to grams so 250g / 500g / 1kg always differ.
            if (preg_match('/(\\d+(?:\\.\\d+)?)\\s*kg\\b/i', $value, $m)) {
                $grams = (float) $m[1] * 1000.0;

                return floor($grams / 250.0) * 50.0;
            }
            if (preg_match('/(\\d+(?:\\.\\d+)?)\\s*g\\b/i', $value, $m)) {
                $grams = (float) $m[1];

                return floor($grams / 250.0) * 50.0;
            }
            if (preg_match('/(\\d+(?:\\.\\d+)?)\\s*(ml|l|litre|liter)\\b/i', $value, $m)) {
                $n = (float) $m[1];
                $unit = strtolower($m[2]);
                if (str_starts_with($unit, 'l')) {
                    return $n >= 2 ? 200.0 : ($n >= 1 ? 100.0 : 50.0);
                }

                return $n >= 1000 ? 150.0 : ($n >= 500 ? 80.0 : ($n >= 250 ? 40.0 : 0.0));
            }
            if (preg_match('/(\\d+)\\s*(pcs|bags|ct|piece)/i', $value, $m)) {
                return ((float) $m[1]) * 10.0;
            }
        }

        if ($type === 'storage') {
            if (preg_match('/(\\d+(?:\\.\\d+)?)\\s*tb\\b/i', $value, $m)) {
                return ((float) $m[1]) * 2000.0;
            }
            if (preg_match('/(\\d+(?:\\.\\d+)?)\\s*gb\\b/i', $value, $m)) {
                return ((float) $m[1]) * 2.0;
            }
        }

        // Fallback: option index steps the price so variants always differ.
        return (float) ($position * 100);
    }
}
