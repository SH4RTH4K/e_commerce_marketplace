<?php

namespace App\Http\Middleware;

use App\Models\ContactMessage;
use App\Models\Banner;
use App\Models\Feature;
use App\Models\Order;
use App\Models\ProductReview;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    private const TYPOGRAPHY_DEFAULTS = [
        'template-1' => [
            'body' => ['font' => 'CozaPoppins, Arial, sans-serif', 'color' => '#666666', 'size' => '14px', 'weight' => '400', 'style' => 'normal', 'transform' => 'none'],
            'header' => ['font' => 'CozaPoppins, Arial, sans-serif', 'color' => '#333333', 'size' => '14px', 'weight' => '700', 'style' => 'normal', 'transform' => 'none'],
            'hero' => ['font' => 'CozaPoppins, Arial, sans-serif', 'color' => '#333333', 'size' => '16px', 'weight' => '400', 'style' => 'normal', 'transform' => 'none'],
            'section' => ['font' => 'CozaPoppins, Arial, sans-serif', 'color' => '#222222', 'size' => '30px', 'weight' => '700', 'style' => 'normal', 'transform' => 'none'],
            'product' => ['font' => 'CozaPoppins, Arial, sans-serif', 'color' => '#333333', 'size' => '14px', 'weight' => '400', 'style' => 'normal', 'transform' => 'none'],
            'button' => ['font' => 'CozaPoppins, Arial, sans-serif', 'color' => '#ffffff', 'size' => '14px', 'weight' => '700', 'style' => 'normal', 'transform' => 'uppercase'],
            'footer' => ['font' => 'CozaPoppins, Arial, sans-serif', 'color' => '#b2b2b2', 'size' => '13px', 'weight' => '400', 'style' => 'normal', 'transform' => 'none'],
        ],
        'template-2' => [
            'body' => ['font' => 'CozaPoppins, Arial, sans-serif', 'color' => '#666666', 'size' => '14px', 'weight' => '400', 'style' => 'normal', 'transform' => 'none'],
            'header' => ['font' => 'CozaPoppins, Arial, sans-serif', 'color' => '#333333', 'size' => '14px', 'weight' => '700', 'style' => 'normal', 'transform' => 'none'],
            'hero' => ['font' => 'CozaPoppins, Arial, sans-serif', 'color' => '#333333', 'size' => '16px', 'weight' => '400', 'style' => 'normal', 'transform' => 'none'],
            'section' => ['font' => 'CozaPoppins, Arial, sans-serif', 'color' => '#222222', 'size' => '30px', 'weight' => '700', 'style' => 'normal', 'transform' => 'none'],
            'product' => ['font' => 'CozaPoppins, Arial, sans-serif', 'color' => '#333333', 'size' => '14px', 'weight' => '400', 'style' => 'normal', 'transform' => 'none'],
            'button' => ['font' => 'CozaPoppins, Arial, sans-serif', 'color' => '#ffffff', 'size' => '14px', 'weight' => '700', 'style' => 'normal', 'transform' => 'uppercase'],
            'footer' => ['font' => 'CozaPoppins, Arial, sans-serif', 'color' => '#b2b2b2', 'size' => '13px', 'weight' => '400', 'style' => 'normal', 'transform' => 'none'],
        ],
    ];

    /**
     * The root template that is loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     */
    public function share(Request $request): array
    {
        $isAdmin = $request->is('admin*');
        $cartService = app(\App\Services\CartService::class);
        $productCardBuyText = setting('product_card_buy_text', setting('default_cta_text', 'Order Now'));
        $productCardOptionsText = setting('product_card_options_text', $productCardBuyText);
        if ($productCardBuyText === 'অর্ডার করুন') {
            $productCardBuyText = 'Order Now';
        }
        if ($productCardOptionsText === 'অর্ডার করুন') {
            $productCardOptionsText = 'Order Now';
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? [
                    'id'             => $request->user()->id,
                    'name'           => $request->user()->name,
                    'email'          => $request->user()->email,
                    'role'           => $request->user()->role ?? 'customer',
                    'permissions'    => $request->user()->permissions ?? [],
                    'is_super_admin' => $request->user()->isSuperAdmin(),
                ] : null,
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error'  => fn () => $request->session()->get('error'),
                'cart_open' => fn () => $request->session()->get('cart_open'),
            ],
            'errors' => fn () => $request->session()->get('errors')
                ? $request->session()->get('errors')->getBag('default')->getMessages()
                : (object) [],
            'testing_mode' => testing_mode(),
            'dropshipping_enabled' => (bool) config('dropshipping.enabled'),
            'app' => [
                'name'            => site_name(),
                'tagline'         => setting('tagline', ''),
                'logo_url'        => logo_url(),
                'favicon_url'     => favicon_url(),
                'currency_symbol' => setting('currency_symbol', '৳'),
                'footer_text'     => setting('footer_text', 'Your one-stop marketplace for quality products at great prices. We deliver the best items directly to your doorstep with care.'),
                'settings'        => [
                    'footer_text'      => setting('footer_text', 'Your one-stop marketplace for quality products at great prices. We deliver the best items directly to your doorstep with care.'),
                    'header_logo_height' => (int) setting('header_logo_height', '48'),
                    'header_title_size' => (int) setting('header_title_size', '18'),
                    'header_tagline_size' => (int) setting('header_tagline_size', '11'),
                    'footer_copyright_enabled' => setting('footer_copyright_enabled', '1') === '1',
                    'footer_copyright_text' => setting('footer_copyright_text', ''),
                    'footer_copyright_url' => setting('footer_copyright_url', ''),
                    'footer_developer_enabled' => setting('footer_developer_enabled', '0') === '1',
                    'footer_developer_label' => setting('footer_developer_label', 'Developed by'),
                    'footer_developer_name' => setting('footer_developer_name', ''),
                    'footer_developer_url' => setting('footer_developer_url', ''),
                    'shipping_page_enabled' => setting('shipping_page_enabled', '1') === '1',
                    'default_meta_title' => setting('default_meta_title', site_name()),
                    'default_meta_description' => setting('default_meta_description', ''),
                    'default_meta_keywords' => setting('default_meta_keywords', ''),
                    'storefront_template' => setting('storefront_template', 'template-2'),
                    'template_1_navbar_menu' => setting('template_1_navbar_menu', 'coza'),
                    'template_1_site_name_style' => setting('template_1_site_name_style', 'default'),
                    'template_1_show_search' => setting('template_1_show_search', '0') === '1',
                    'template_1_category_per_row' => setting('template_1_category_per_row', '3'),
                    'template_1_product_per_row' => setting('template_1_product_per_row', '5'),
                    'template_2_product_per_row' => setting('template_2_product_per_row', '5'),
                    'template_1_products_per_page' => setting('template_1_products_per_page', '12'),
                    'template_1_inner_page_banner' => Banner::active()
                        ->placement('hero')
                        ->whereNotNull('image')
                        ->where('image', '!=', '')
                        ->orderBy('position')
                        ->orderBy('id')
                        ->value('image'),
                    'template_2_products_per_page' => setting('template_2_products_per_page', '12'),
                    'template_1_hero_overlay_color' => setting('template_1_hero_overlay_color', '#ffffff'),
                    // Keep product photography true to its original colour by default. Administrators can
                    // still add an overlay from Theme settings when a particular hero needs extra text contrast.
                    'template_1_hero_overlay_opacity' => (int) setting('template_1_hero_overlay_opacity', '0'),
                    'template_1_hero_text_background_color' => setting('template_1_hero_text_background_color', '#1f2430'),
                    'template_1_hero_text_background_opacity' => (int) setting('template_1_hero_text_background_opacity', '88'),
                    'template_1_hero_text_position' => setting('template_1_hero_text_position', 'left'),
                    'template_2_hero_overlay_color' => setting('template_2_hero_overlay_color', '#ffffff'),
                    'template_2_hero_overlay_opacity' => (int) setting('template_2_hero_overlay_opacity', '0'),
                    'template_2_hero_text_background_color' => setting('template_2_hero_text_background_color', '#1f2430'),
                    'template_2_hero_text_background_opacity' => (int) setting('template_2_hero_text_background_opacity', '35'),
                    'template_2_hero_text_position' => setting('template_2_hero_text_position', 'left'),
                    'template_2_category_title_color' => setting('template_2_category_title_color', '#1f2937'),
                    'template_2_category_secondary_color' => setting('template_2_category_secondary_color', '#f2541c'),
                    'template_2_category_overlay_color' => setting('template_2_category_overlay_color', '#1f2430'),
                    'template_2_category_overlay_opacity' => (int) setting('template_2_category_overlay_opacity', '0'),
                    'template_2_category_hover_overlay_opacity' => (int) setting('template_2_category_hover_overlay_opacity', '12'),
                    'template_2_category_title_size' => setting('template_2_category_title_size', '14px'),
                    'template_2_category_title_weight' => setting('template_2_category_title_weight', '700'),
                    'template_2_category_title_style' => setting('template_2_category_title_style', 'normal'),
                    'template_2_category_title_transform' => setting('template_2_category_title_transform', 'none'),
                    'template_2_category_text_align' => setting('template_2_category_text_align', 'center'),
                    'template_2_category_text_shadow' => setting('template_2_category_text_shadow', '0') === '1',
                    'template_1_category_title_color' => setting('template_1_category_title_color', '#ffffff'),
                    'template_1_category_secondary_color' => setting('template_1_category_secondary_color', '#f5f7ff'),
                    'template_1_category_overlay_color' => setting('template_1_category_overlay_color', '#1f2430'),
                    'template_1_category_overlay_opacity' => (int) setting('template_1_category_overlay_opacity', '58'),
                    'template_1_category_hover_overlay_opacity' => (int) setting('template_1_category_hover_overlay_opacity', '82'),
                    'template_1_category_title_size' => setting('template_1_category_title_size', '28px'),
                    'template_1_category_title_weight' => setting('template_1_category_title_weight', '700'),
                    'template_1_category_title_style' => setting('template_1_category_title_style', 'normal'),
                    'template_1_category_title_transform' => setting('template_1_category_title_transform', 'none'),
                    'template_1_category_text_align' => setting('template_1_category_text_align', 'left'),
                    'template_1_category_text_shadow' => setting('template_1_category_text_shadow', '1') === '1',
                    'template_1_overview_all_count' => (int) setting('template_1_overview_all_count', '16'),
                    'template_1_overview_featured_count' => (int) setting('template_1_overview_featured_count', '12'),
                    'template_1_overview_new_count' => (int) setting('template_1_overview_new_count', '12'),
                    'template_1_overview_best_count' => (int) setting('template_1_overview_best_count', '12'),
                    'theme_typography_template_1' => $this->typographySettings('template-1'),
                    'theme_typography_template_2' => $this->typographySettings('template-2'),
                    'template_2_footer_config' => setting('template_2_footer_config', ''),
                    'chat_enabled'     => setting('chat_enabled', '1') === '1',
                    'whatsapp_number'  => setting('whatsapp_number', ''),
                    'call_number'      => setting('call_number', ''),
                    'messenger_page'   => setting('messenger_page', ''),
                    'contact_phone'    => setting('contact_phone', ''),
                    'facebook_url'     => setting('facebook_url', ''),
                    'instagram_url'    => setting('instagram_url', ''),
                    'twitter_url'      => setting('twitter_url', ''),
                    'youtube_url'      => setting('youtube_url', ''),
                    'product_card_bg_color'        => setting('product_card_bg_color', '#FFFFFF'),
                    'product_card_text_color'      => setting('product_card_text_color', '#111827'),
                    'product_card_btn_bg_color'    => setting('product_card_btn_bg_color', '#f15a24'),
                    'product_card_btn_text_color'  => setting('product_card_btn_text_color', '#ffffff'),
                    'product_card_buy_text'        => $productCardBuyText,
                    'product_card_options_text'    => $productCardOptionsText,
                    'product_cta_action'           => setting('product_cta_action', 'checkout'),
                    
                    // Product Page Action Buttons
                    'product_page_add_cart_text'       => setting('product_page_add_cart_text', 'ADD TO CART'),
                    'product_page_add_cart_bg_color'   => setting('product_page_add_cart_bg_color', '#f15a24'),
                    'product_page_add_cart_text_color' => setting('product_page_add_cart_text_color', '#ffffff'),
                    
                    'product_page_buy_text'            => setting('product_page_buy_text', 'ORDER NOW'),
                    'product_page_buy_bg_color'        => setting('product_page_buy_bg_color', '#0b1c21'),
                    'product_page_buy_text_color'      => setting('product_page_buy_text_color', '#ffffff'),

                    'product_page_cod_enabled'         => setting('product_page_cod_enabled', '1') === '1',
                    'product_page_cod_text'            => setting('product_page_cod_text', 'ক্যাশ অন ডেলিভারিতে অর্ডার করুন'),
                    'product_page_cod_bg_color'        => setting('product_page_cod_bg_color', '#16a34a'),
                    'product_page_cod_text_color'      => setting('product_page_cod_text_color', '#ffffff'),

                    'product_page_whatsapp_enabled'    => setting('product_page_whatsapp_enabled', '1') === '1',
                    'product_page_whatsapp_text'       => setting('product_page_whatsapp_text', 'WhatsApp Order'),
                    'product_page_whatsapp_number'     => setting('whatsapp_number', setting('product_page_whatsapp_number', '')),
                    'product_page_whatsapp_bg_color'   => setting('product_page_whatsapp_bg_color', '#25D366'),
                    'product_page_whatsapp_text_color' => setting('product_page_whatsapp_text_color', '#ffffff'),

                    'product_page_call_enabled'        => setting('product_page_call_enabled', '1') === '1',
                    'product_page_call_text'           => setting('product_page_call_text', 'Call For Order'),
                    'product_page_call_number'         => setting('product_page_call_number', setting('call_number', setting('contact_phone', ''))),
                    'product_page_call_bg_color'       => setting('product_page_call_bg_color', '#294294'),
                    'product_page_call_text_color'     => setting('product_page_call_text_color', '#ffffff'),

                    'ship_inside'  => (float) setting('shipping_inside_dhaka', 60),
                    'ship_outside' => (float) setting('shipping_outside_dhaka', 120),
                    'ship_inside_label'  => setting('shipping_inside_label', 'ঢাকার ভেতরে'),
                    'ship_outside_label' => setting('shipping_outside_label', 'ঢাকার বাইরে'),
                    'pay_cod_enabled'    => setting('pay_cod_enabled', '1') === '1',
                    'pay_bkash_enabled'  => setting('pay_bkash_enabled', '0') === '1',
                    'pay_nagad_enabled'  => setting('pay_nagad_enabled', '0') === '1',
                    'pay_rocket_enabled' => setting('pay_rocket_enabled', '0') === '1',
                    'show_cards_in_footer' => setting('show_cards_in_footer', '0') === '1',
                    'currency_symbol'    => setting('currency_symbol', '৳'),
                    // Whether to show "Pay now (delivery)" split on Thank You page
                    'cod_delivery_upfront' => setting('cod_delivery_upfront', '1') === '1',
                    // Delivery note field in checkout & popup
                    'checkout_delivery_note_enabled' => setting('checkout_delivery_note_enabled', '1') === '1',
                    'checkout_delivery_note_label'   => setting('checkout_delivery_note_label', 'ডেলিভারি সংক্রান্ত বিশেষ নোট (ঐচ্ছিক)'),
                ],
            ],
            'admin_badges' => fn () => $isAdmin && $request->user() ? [
                'pending_orders'  => Order::where('payment_status', 'pending')->count(),
                'pending_reviews' => ProductReview::pending()->count(),
                'new_messages'    => ContactMessage::new()->count(),
            ] : null,
            'cartCount' => fn () => $isAdmin ? 0 : $cartService->count(),
            'cartItems' => fn () => $isAdmin ? collect() : $cartService->items(),
            'cartSubtotal' => fn () => $isAdmin ? 0.0 : $cartService->subtotal(),
            'storefrontFeatures' => fn () => $isAdmin ? [] : Feature::query()
                ->where('is_active', true)
                ->whereNotNull('title')
                ->where('title', '!=', '')
                ->orderBy('position')
                ->orderBy('id')
                ->limit(5)
                ->get(['id', 'title', 'subtitle', 'icon'])
                ->values()
                ->toArray(),
            'categories' => fn () => $isAdmin ? [] : \App\Models\Category::whereNull('parent_id')
                ->with(['children' => fn($q) => $q->where('is_active', true)->where('show_in_menu', true)->select('id', 'name', 'slug', 'parent_id', 'icon', 'image')->orderBy('menu_order')->orderBy('name')])
                ->where('is_active', true)
                ->where('show_in_menu', true)
                ->orderBy('menu_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'parent_id', 'icon', 'image'])
                ->values()
                ->toArray(),
            'hasFlashSale' => fn () => $isAdmin ? false : \App\Models\Product::published()->where('is_flash_sale', true)->exists(),
            'promoText' => fn () => $isAdmin ? '' : setting('header_promo_text', ''),
            'promoLink' => fn () => $isAdmin ? '' : setting('header_promo_link', ''),
            'popup' => fn () => $isAdmin ? ['enabled' => false] : [
                'enabled'       => setting('popup_enabled', '0') === '1',
                'title'         => setting('popup_title', ''),
                'text'          => setting('popup_text', ''),
                'image'         => setting('popup_image') ? (str_starts_with(setting('popup_image'), 'http') ? setting('popup_image') : asset(setting('popup_image'))) : '',
                'link'          => setting('popup_link', ''),
                'delay_seconds' => (int) setting('popup_delay_seconds', '3'),
                'btn_label'     => setting('popup_btn_label', 'Shop Now'),
                'frequency'     => setting('popup_frequency', 'once_per_session'),
            ],
        ]);
    }

    private function typographySettings(string $template): array
    {
        $raw = setting('theme_typography_' . str_replace('-', '_', $template), '');
        $saved = json_decode((string) $raw, true);

        return array_replace_recursive(self::TYPOGRAPHY_DEFAULTS[$template], is_array($saved) ? $saved : []);
    }
}
