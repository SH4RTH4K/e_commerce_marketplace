<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\PublicUploader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
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
            'body' => ['font' => 'Mulish, ui-sans-serif, system-ui, sans-serif', 'color' => '#111827', 'size' => '14px', 'weight' => '400', 'style' => 'normal', 'transform' => 'none'],
            'header' => ['font' => 'Mulish, ui-sans-serif, system-ui, sans-serif', 'color' => '#0b1c21', 'size' => '14px', 'weight' => '700', 'style' => 'normal', 'transform' => 'none'],
            'hero' => ['font' => 'Mulish, ui-sans-serif, system-ui, sans-serif', 'color' => '#111827', 'size' => '16px', 'weight' => '500', 'style' => 'normal', 'transform' => 'none'],
            'section' => ['font' => 'Outfit, Mulish, ui-sans-serif, system-ui, sans-serif', 'color' => '#111827', 'size' => '24px', 'weight' => '800', 'style' => 'normal', 'transform' => 'none'],
            'product' => ['font' => 'Mulish, ui-sans-serif, system-ui, sans-serif', 'color' => '#111827', 'size' => '14px', 'weight' => '700', 'style' => 'normal', 'transform' => 'none'],
            'button' => ['font' => 'Mulish, ui-sans-serif, system-ui, sans-serif', 'color' => '#ffffff', 'size' => '14px', 'weight' => '700', 'style' => 'normal', 'transform' => 'none'],
            'footer' => ['font' => 'Mulish, ui-sans-serif, system-ui, sans-serif', 'color' => '#6b7280', 'size' => '14px', 'weight' => '400', 'style' => 'normal', 'transform' => 'none'],
        ],
    ];

    private const SECTIONS = [
        'brand',
        'chat',
        'homepage',
        'payments',
        'shipping',
        'mail',
        'seo',
        'tracking',
        'legal',
        'courier',
        'fake_order_guard',
        'storefront_ui',
        'theme',
    ];

    public function edit()
    {
        return Inertia::render('Admin/Settings', [
            'settings' => Setting::pluck('value', 'key'),
            'templateStatus' => [
                'template_1_assets' => is_file(public_path('templates/template-1/images/slide-01.jpg'))
                    && is_file(public_path('templates/template-1/images/banner-01.jpg')),
                'template_1_css' => is_file(public_path('theme/css/template-1-storefront.css')),
                'template_1_fonts' => is_file(public_path('templates/template-1/fonts/Poppins/Poppins-Regular.ttf')),
            ],
        ]);
    }

    public function updateSection(Request $request, string $section)
    {
        if (! in_array($section, self::SECTIONS, true)) {
            abort(404);
        }

        $data = $request->validate($this->rulesForSection($section));

        if ($section === 'fake_order_guard'
            && $request->boolean('fog_bdcourier_enabled')
            && ! Schema::hasTable('bd_courier_checks')) {
            throw ValidationException::withMessages([
                'fog_bdcourier_enabled' => 'Run php artisan migrate to install the BD Courier history table before enabling this module.',
            ]);
        }

        $this->persistSection($section, $data, $request);

        $payload = [
            'message' => $this->sectionLabel($section) . ' saved.',
            'section' => $section,
        ];

        if ($section === 'brand') {
            $payload['logo_url'] = logo_url();
            $payload['favicon_url'] = favicon_url();
            $payload['has_logo'] = has_custom_logo();
            $payload['has_favicon'] = (bool) ($request->boolean('remove_favicon') ? false : setting('favicon'));
        }

        if ($section === 'tracking') {
            $payload['tracking_active'] = tracking_any_enabled();
            $payload['tracking_labels'] = array_values(array_filter([
                tracking_gtm_id() ? 'GTM' : null,
                tracking_ga4_id() ? 'GA4' : null,
                tracking_meta_pixel_id() ? 'Meta Pixel' : null,
            ]));
        }

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return back()->with('status', $payload['message']);
    }

    /** Send a test email to the admin using the current mail settings. */
    public function testMail(Request $request)
    {
        $to = $request->validate(['test_email' => ['required', 'email']])['test_email'];

        try {
            configure_mail_from_settings();
            \Illuminate\Support\Facades\Mail::raw(
                'This is a test email from ' . site_name() . '. Your mail settings are working.',
                fn ($m) => $m->to($to)->subject('Test email — ' . site_name())
            );
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Send failed: ' . $e->getMessage()], 422);
            }

            return back()->withErrors(['test_email' => 'Send failed: ' . $e->getMessage()]);
        }

        $where = (setting('mail_mailer', 'log') === 'smtp') ? "sent to {$to}" : 'written to storage/logs/laravel.log (log mailer)';
        $message = "Test email {$where}.";

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('status', $message);
    }

    private function rulesForSection(string $section): array
    {
        return match ($section) {
            'brand' => [
                'site_name'       => ['required', 'string', 'max:120'],
                'tagline'         => ['nullable', 'string', 'max:200'],
                'footer_text'     => ['nullable', 'string', 'max:400'],
                'footer_copyright_enabled' => ['nullable', 'boolean'],
                'footer_copyright_text'    => ['nullable', 'string', 'max:200'],
                'footer_copyright_url'     => ['nullable', 'url', 'max:255'],
                'footer_developer_enabled' => ['nullable', 'boolean'],
                'footer_developer_label'   => ['nullable', 'string', 'max:60'],
                'footer_developer_name'    => ['nullable', 'string', 'max:100'],
                'footer_developer_url'     => ['nullable', 'url', 'max:255'],
                'contact_phone'   => ['nullable', 'string', 'max:60'],
                'contact_email'   => ['nullable', 'email', 'max:120'],
                'contact_address' => ['nullable', 'string', 'max:255'],
                'contact_hours'   => ['nullable', 'string', 'max:120'],
                'contact_title'   => ['nullable', 'string', 'max:120'],
                'contact_intro'   => ['nullable', 'string', 'max:255'],
                'facebook_url'    => ['nullable', 'url', 'max:255'],
                'instagram_url'   => ['nullable', 'url', 'max:255'],
                'twitter_url'     => ['nullable', 'url', 'max:255'],
                'search_placeholder' => ['nullable', 'string', 'max:120'],
                // Use mimes for reliable MIME-type validation of uploaded branding assets.
                'logo_file'       => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:4096'],
                'favicon_file'    => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg,webp,ico', 'max:2048'],
                'remove_logo'     => ['nullable', 'boolean'],
                'remove_favicon'  => ['nullable', 'boolean'],
            ],
            'chat' => [
                'chat_enabled'    => ['nullable', 'boolean'],
                'whatsapp_number' => ['nullable', 'string', 'max:40'],
                'call_number'     => ['nullable', 'string', 'max:40'],
                'messenger_page'  => ['nullable', 'url', 'max:255'],
            ],
            'homepage' => [
                'show_brands_marquee' => ['nullable', 'boolean'],
                'brands_marquee'      => ['nullable', 'string', 'max:500'],
                'header_promo_text'   => ['nullable', 'string', 'max:200'],
                'header_promo_link'   => ['nullable', 'url', 'max:255'],
                'shop_subtitle'       => ['nullable', 'string', 'max:255'],
                'deal_ends_at'        => ['nullable', 'date'],
                'delivery_eta_text'   => ['nullable', 'string', 'max:120'],
                'homepage_tab_count'  => ['nullable', 'integer', 'min:1', 'max:8'],
                'home_categories_title' => ['nullable', 'string', 'max:80'],
                'home_hot_deal_title'   => ['nullable', 'string', 'max:80'],
                'home_featured_title'   => ['nullable', 'string', 'max:80'],
                'home_deal_week_title'  => ['nullable', 'string', 'max:80'],
                'home_tabs_title'       => ['nullable', 'string', 'max:80'],
                'home_brands_label'     => ['nullable', 'string', 'max:120'],
                'home_view_more_label'  => ['nullable', 'string', 'max:40'],
                'default_cta_text'      => ['nullable', 'string', 'max:40'],
                'product_cta_action'    => ['nullable', 'in:checkout,cart'],
                'hero_fallback_badge'   => ['nullable', 'string', 'max:40'],
                'hero_fallback_title'   => ['nullable', 'string', 'max:120'],
                'hero_fallback_subtitle'=> ['nullable', 'string', 'max:255'],
                // Popup Notification
                'popup_enabled'        => ['nullable', 'boolean'],
                'popup_text'           => ['nullable', 'string', 'max:400'],
                'popup_link'           => ['nullable', 'url', 'max:255'],
                'popup_btn_label'      => ['nullable', 'string', 'max:40'],
                'popup_delay_seconds'  => ['nullable', 'integer', 'min:0', 'max:60'],
                'popup_image_file'     => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,gif', 'max:2048'],
                'popup_remove_image'   => ['nullable', 'boolean'],
                'footer_text'          => ['nullable', 'string', 'max:500'],
            ],
            'storefront_ui' => [
                // Use storefront_footer_text to avoid clobbering the homepage footer_text setting
                'storefront_footer_text'     => ['nullable', 'string', 'max:500'],
                // Color fields must be valid hex color codes to prevent CSS/XSS injection
                'product_card_bg_color'      => ['nullable', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{3,8}$/'],
                'product_card_text_color'    => ['nullable', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{3,8}$/'],
                'product_card_btn_bg_color'  => ['nullable', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{3,8}$/'],
                'product_card_btn_text_color'=> ['nullable', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{3,8}$/'],
                'product_card_buy_text'      => ['nullable', 'string', 'max:60'],
                'product_card_options_text'  => ['nullable', 'string', 'max:60'],
                'product_page_add_cart_text' => ['nullable', 'string', 'max:60'],
                'product_page_add_cart_bg_color'   => ['nullable', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{3,8}$/'],
                'product_page_add_cart_text_color' => ['nullable', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{3,8}$/'],
                'product_page_buy_text'      => ['nullable', 'string', 'max:60'],
                'product_page_buy_bg_color'  => ['nullable', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{3,8}$/'],
                'product_page_buy_text_color'=> ['nullable', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{3,8}$/'],
                'product_cta_action'         => ['nullable', 'in:checkout,cart'],
                'default_cta_text'           => ['nullable', 'string', 'max:60'],

                // Cash on delivery button
                'product_page_cod_enabled'   => ['nullable', 'boolean'],
                'product_page_cod_text'      => ['nullable', 'string', 'max:80'],
                'product_page_cod_bg_color'  => ['nullable', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{3,8}$/'],
                'product_page_cod_text_color'=> ['nullable', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{3,8}$/'],

                // WhatsApp order button
                'product_page_whatsapp_enabled'    => ['nullable', 'boolean'],
                'product_page_whatsapp_text'       => ['nullable', 'string', 'max:80'],
                'product_page_whatsapp_number'     => ['nullable', 'string', 'max:40'],
                'product_page_whatsapp_bg_color'   => ['nullable', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{3,8}$/'],
                'product_page_whatsapp_text_color' => ['nullable', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{3,8}$/'],

                // Call for order button
                'product_page_call_enabled'        => ['nullable', 'boolean'],
                'product_page_call_text'           => ['nullable', 'string', 'max:80'],
                'product_page_call_number'         => ['nullable', 'string', 'max:40'],
                'product_page_call_bg_color'       => ['nullable', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{3,8}$/'],
                'product_page_call_text_color'     => ['nullable', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{3,8}$/'],
            ],
            'theme' => [
                'storefront_template' => ['required', 'in:template-1,template-2'],
                'template_1_navbar_menu' => ['nullable', 'in:coza,categories'],
                'template_1_show_search' => ['nullable', 'boolean'],
                'template_1_category_per_row' => ['nullable', 'integer', 'min:2', 'max:5'],
                'template_1_product_per_row' => ['nullable', 'integer', 'min:2', 'max:6'],
                'template_2_product_per_row' => ['nullable', 'integer', 'min:2', 'max:6'],
                'template_1_products_per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
                'template_2_products_per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
                'template_1_hero_overlay_color' => ['nullable', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'template_1_hero_overlay_opacity' => ['nullable', 'numeric', 'min:0', 'max:80'],
                'template_1_hero_text_background_color' => ['nullable', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'template_1_hero_text_background_opacity' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'template_1_hero_text_position' => ['nullable', 'in:left,center,right'],
                'template_1_category_title_color' => ['nullable', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'template_1_category_secondary_color' => ['nullable', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'template_1_category_overlay_color' => ['nullable', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'template_1_category_overlay_opacity' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'template_1_category_hover_overlay_opacity' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'template_1_category_title_size' => ['nullable', 'in:16px,18px,20px,22px,24px,28px,30px,32px,36px,40px,48px'],
                'template_1_category_title_weight' => ['nullable', 'in:400,500,600,700,800,900'],
                'template_1_category_title_style' => ['nullable', 'in:normal,italic'],
                'template_1_category_title_transform' => ['nullable', 'in:none,capitalize,uppercase,lowercase'],
                'template_1_category_text_align' => ['nullable', 'in:left,center,right'],
                'template_1_category_text_shadow' => ['nullable', 'boolean'],
                'template_1_overview_all_count' => ['nullable', 'integer', 'min:1', 'max:48'],
                'template_1_overview_featured_count' => ['nullable', 'integer', 'min:1', 'max:48'],
                'template_1_overview_new_count' => ['nullable', 'integer', 'min:1', 'max:48'],
                'template_1_overview_best_count' => ['nullable', 'integer', 'min:1', 'max:48'],
                'template_2_overview_featured_count' => ['nullable', 'integer', 'min:1', 'max:48'],
                'template_2_overview_new_count' => ['nullable', 'integer', 'min:1', 'max:48'],
                'template_2_overview_best_count' => ['nullable', 'integer', 'min:1', 'max:48'],
                'theme_typography_template_1' => ['required', 'json', 'max:20000'],
                'theme_typography_template_2' => ['required', 'json', 'max:20000'],
            ],
            'payments' => [
                'bkash_number'  => ['nullable', 'string', 'max:40'],
                'nagad_number'  => ['nullable', 'string', 'max:40'],
                'rocket_number' => ['nullable', 'string', 'max:40'],
                'pay_cod_enabled' => ['nullable', 'boolean'],
                'pay_bkash_enabled' => ['nullable', 'boolean'],
                'pay_nagad_enabled' => ['nullable', 'boolean'],
                'pay_rocket_enabled' => ['nullable', 'boolean'],
                'show_cards_in_footer' => ['nullable', 'boolean'],
                // COD: whether to show "Pay now (delivery)" breakdown on Thank You page
                'cod_delivery_upfront' => ['nullable', 'boolean'],
            ],
            'shipping' => [
                'shipping_inside_dhaka'  => ['required', 'numeric', 'min:0'],
                'shipping_outside_dhaka' => ['required', 'numeric', 'min:0'],
                'tax_percent'            => ['required', 'numeric', 'min:0', 'max:100'],
                'shipping_inside_label'  => ['nullable', 'string', 'max:80'],
                'shipping_outside_label' => ['nullable', 'string', 'max:120'],
                'shipping_charge_intro'  => ['nullable', 'string', 'max:2000'],
                'currency_symbol'        => ['nullable', 'string', 'max:20'],
                'currency_code'          => ['nullable', 'string', 'max:10'],
                'fraud_order_time_limit_minutes' => ['nullable', 'numeric', 'min:0', 'max:1440'],
                'checkout_delivery_note_enabled' => ['nullable', 'boolean'],
                'checkout_delivery_note_label'   => ['nullable', 'string', 'max:120'],
            ],
            'mail' => [
                'otp_enabled'       => ['nullable', 'boolean'],
                'mail_mailer'       => ['required', 'in:log,smtp'],
                'mail_host'         => ['nullable', 'string', 'max:120'],
                'mail_port'         => ['nullable', 'numeric'],
                'mail_username'     => ['nullable', 'string', 'max:180'],
                'mail_password'     => ['nullable', 'string', 'max:180'],
                'mail_encryption'   => ['nullable', 'in:tls,ssl,none'],
                'mail_from_address' => ['nullable', 'email', 'max:120'],
                'mail_from_name'    => ['nullable', 'string', 'max:120'],
            ],
            'seo' => [
                'default_meta_title'       => ['nullable', 'string', 'max:180'],
                'default_meta_description' => ['nullable', 'string', 'max:400'],
                'default_meta_keywords'    => ['nullable', 'string', 'max:400'],
            ],
            'tracking' => [
                'tracking_gtm_id'        => ['nullable', 'string', 'max:20', 'regex:/^(|GTM-[A-Z0-9]+)$/i'],
                'tracking_ga4_id'        => ['nullable', 'string', 'max:20', 'regex:/^(|G-[A-Z0-9]+)$/i'],
                'tracking_meta_pixel_id' => ['nullable', 'string', 'max:20', 'regex:/^(|\d+)$/'],
            ],
            'legal' => [
                'terms_content'   => ['nullable', 'string', 'max:20000'],
                'privacy_content' => ['nullable', 'string', 'max:20000'],
                'refund_content'  => ['nullable', 'string', 'max:20000'],
                'shipping_content' => ['nullable', 'string', 'max:20000'],
                'shipping_page_enabled' => ['nullable', 'boolean'],
            ],
            'courier' => [
                'courier_default'          => ['nullable', 'in:steadfast,pathao,redx'],
                'steadfast_api_key'        => ['nullable', 'string', 'max:120'],
                'steadfast_secret_key'     => ['nullable', 'string', 'max:120'],
                'pathao_client_id'         => ['nullable', 'string', 'max:120'],
                'pathao_client_secret'     => ['nullable', 'string', 'max:120'],
                'pathao_username'          => ['nullable', 'string', 'max:120'],
                'pathao_password'          => ['nullable', 'string', 'max:120'],
                'pathao_store_id'          => ['nullable', 'string', 'max:120'],
                'redx_api_token'           => ['nullable', 'string', 'max:240'],
            ],
            'fake_order_guard' => [
                'fog_enabled'                      => ['nullable', 'boolean'],
                'fog_ip_block_enabled'             => ['nullable', 'boolean'],
                'fog_device_block_enabled'         => ['nullable', 'boolean'],
                'fog_phone_block_enabled'          => ['nullable', 'boolean'],
                'fog_fake_number_block_enabled'    => ['nullable', 'boolean'],
                'fog_ip_cooldown_minutes'          => ['nullable', 'integer', 'min:0', 'max:1440'],
                'fog_phone_cooldown_minutes'       => ['nullable', 'integer', 'min:0', 'max:1440'],
                'fog_max_orders_per_phone'         => ['nullable', 'integer', 'min:0', 'max:100'],
                'fog_auto_block_ip_on_cancel'      => ['nullable', 'boolean'],
                // BD Courier
                'fog_bdcourier_enabled'            => ['nullable', 'boolean'],
                'fog_bdcourier_api_key'            => ['nullable', 'string', 'max:200'],
                'fog_bdcourier_auto_check_checkout'=> ['nullable', 'boolean'],
                'fog_bdcourier_min_success_rate'   => ['nullable', 'integer', 'min:0', 'max:100'],
                'fog_bdcourier_block_risk_levels'  => ['nullable', 'string', 'max:100'],
            ],
            default => throw ValidationException::withMessages(['section' => 'Unknown settings section.']),
        };
    }

    private function persistSection(string $section, array $data, Request $request): void
    {
        if ($section === 'theme') {
            foreach (['template-1', 'template-2'] as $template) {
                $key = 'theme_typography_' . str_replace('-', '_', $template);
                if (array_key_exists($key, $data)) {
                    $data[$key] = $this->normalizeTypography($data[$key], $template);
                }
            }
        }

        $keys = match ($section) {
            'brand' => [
                'site_name', 'tagline', 'footer_text', 'footer_copyright_text', 'footer_copyright_url',
                'footer_developer_label', 'footer_developer_name', 'footer_developer_url',
                'contact_phone', 'contact_email', 'contact_address',
                'contact_hours', 'contact_title', 'contact_intro',
                'facebook_url', 'instagram_url', 'twitter_url',
                'search_placeholder',
            ],
            'chat' => [
                'chat_enabled', 'whatsapp_number', 'call_number', 'messenger_page',
            ],

            'homepage' => [
                'brands_marquee', 'header_promo_text', 'header_promo_link',
                'shop_subtitle', 'delivery_eta_text', 'homepage_tab_count',
                'home_categories_title', 'home_hot_deal_title', 'home_featured_title',
                'home_deal_week_title', 'home_tabs_title', 'home_brands_label',
                'home_view_more_label', 'default_cta_text', 'product_cta_action',
                'hero_fallback_badge', 'hero_fallback_title', 'hero_fallback_subtitle',
                'popup_text', 'popup_link', 'popup_btn_label', 'popup_delay_seconds',
                'footer_text',
            ],
            'storefront_ui' => [
                // NOTE: storefront_ui uses 'storefront_footer_text' to avoid overwriting
                // the 'footer_text' key that belongs to the homepage section.
                'storefront_footer_text',
                'product_card_bg_color', 'product_card_text_color',
                'product_card_btn_bg_color', 'product_card_btn_text_color',
                'product_card_buy_text', 'product_card_options_text',
                'product_page_add_cart_text', 'product_page_add_cart_bg_color', 'product_page_add_cart_text_color',
                'product_page_buy_text', 'product_page_buy_bg_color', 'product_page_buy_text_color',
                'product_page_cod_text', 'product_page_cod_bg_color', 'product_page_cod_text_color',
                'product_page_whatsapp_text', 'product_page_whatsapp_number', 'product_page_whatsapp_bg_color', 'product_page_whatsapp_text_color',
                'product_page_call_text', 'product_page_call_number', 'product_page_call_bg_color', 'product_page_call_text_color',
                'product_cta_action', 'default_cta_text',
            ],
            'payments' => ['bkash_number', 'nagad_number', 'rocket_number'],
            'shipping' => [
                'shipping_inside_dhaka', 'shipping_outside_dhaka', 'tax_percent',
                'shipping_inside_label', 'shipping_outside_label',
                'shipping_charge_intro',
                'currency_symbol', 'currency_code', 'fraud_order_time_limit_minutes',
                'checkout_delivery_note_label',
            ],
            'mail' => [
                'mail_mailer', 'mail_host', 'mail_port', 'mail_username',
                'mail_encryption', 'mail_from_address', 'mail_from_name',
            ],
            'seo' => ['default_meta_title', 'default_meta_description', 'default_meta_keywords'],
            'tracking' => ['tracking_gtm_id', 'tracking_ga4_id', 'tracking_meta_pixel_id'],
            'legal' => ['terms_content', 'privacy_content', 'refund_content', 'shipping_content', 'shipping_page_enabled'],
            'courier' => [
                'courier_default', 'steadfast_api_key', 'steadfast_secret_key',
                'pathao_client_id', 'pathao_client_secret', 'pathao_username', 'pathao_password', 'pathao_store_id',
                'redx_api_token',
            ],
            'fake_order_guard' => [
                'fog_ip_cooldown_minutes', 'fog_phone_cooldown_minutes', 'fog_max_orders_per_phone',
                'fog_bdcourier_api_key', 'fog_bdcourier_min_success_rate', 'fog_bdcourier_block_risk_levels',
            ],
            'theme' => [
                'storefront_template', 'template_1_navbar_menu', 'template_1_category_per_row',
                'template_1_product_per_row', 'template_2_product_per_row',
                'template_1_products_per_page', 'template_2_products_per_page',
                'template_1_hero_overlay_color', 'template_1_hero_overlay_opacity',
                'template_1_hero_text_background_color',
                'template_1_hero_text_background_opacity', 'template_1_hero_text_position',
                'template_1_category_title_color', 'template_1_category_secondary_color',
                'template_1_category_overlay_color', 'template_1_category_overlay_opacity',
                'template_1_category_hover_overlay_opacity', 'template_1_category_title_size',
                'template_1_category_title_weight', 'template_1_category_title_style',
                'template_1_category_title_transform', 'template_1_category_text_align',
                'template_1_overview_all_count', 'template_1_overview_featured_count',
                'template_1_overview_new_count', 'template_1_overview_best_count',
                'template_2_overview_featured_count', 'template_2_overview_new_count',
                'template_2_overview_best_count',
                'theme_typography_template_1', 'theme_typography_template_2',
            ],
            default => [],
        };

        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $value = (string) ($data[$key] ?? '');
            if (in_array($key, ['tracking_gtm_id', 'tracking_ga4_id'], true) && $value !== '') {
                $value = strtoupper($value);
            }
            Setting::put($key, $value);
        }

        if ($section === 'shipping') {
            Setting::put('checkout_delivery_note_enabled', $request->boolean('checkout_delivery_note_enabled') ? '1' : '0');
        }

        if ($section === 'legal') {
            Setting::put('shipping_page_enabled', $request->boolean('shipping_page_enabled') ? '1' : '0');
        }

        if ($section === 'theme') {
            Setting::put('template_1_show_search', $request->boolean('template_1_show_search') ? '1' : '0');
            Setting::put('template_1_category_text_shadow', $request->boolean('template_1_category_text_shadow') ? '1' : '0');
        }

        if ($section === 'storefront_ui') {
            Setting::put('product_page_cod_enabled', $request->boolean('product_page_cod_enabled') ? '1' : '0');
            Setting::put('product_page_whatsapp_enabled', $request->boolean('product_page_whatsapp_enabled') ? '1' : '0');
            Setting::put('product_page_call_enabled', $request->boolean('product_page_call_enabled') ? '1' : '0');
            if ($request->filled('product_card_buy_text')) {
                Setting::put('default_cta_text', (string) $request->input('product_card_buy_text'));
            }
        }

        if ($section === 'mail') {
            Setting::put('otp_enabled', $request->boolean('otp_enabled') ? '1' : '0');
            // mail_password is purposefully excluded from $keys above so leaving it blank in the form does not overwrite an existing password
            if ($request->filled('mail_password')) {
                Setting::put('mail_password', (string) $request->input('mail_password'));
            }
        }

        if ($section === 'homepage') {
            Setting::put('show_brands_marquee', $request->boolean('show_brands_marquee') ? '1' : '0');
            Setting::put('popup_enabled', $request->boolean('popup_enabled') ? '1' : '0');
            foreach (['deal_ends_at'] as $dtKey) {
                if ($request->filled($dtKey)) {
                    $raw = str_replace('T', ' ', (string) $request->input($dtKey));
                    if (strlen($raw) === 16) {
                        $raw .= ':00';
                    }
                    Setting::put($dtKey, $raw);
                } elseif (array_key_exists($dtKey, $data)) {
                    Setting::put($dtKey, '');
                }
            }
            // Popup image upload/remove
            if ($request->boolean('popup_remove_image')) {
                $this->deleteStored(Setting::get('popup_image'));
                Setting::put('popup_image', '');
            } elseif ($request->hasFile('popup_image_file')) {
                $path = PublicUploader::storeFromRequest($request, 'popup_image_file', 'popups', 'webp');
                if ($path) {
                    $this->deleteStored(Setting::get('popup_image'));
                    Setting::put('popup_image', $path);
                }
            }
        }

        if ($section === 'brand') {
            Setting::put('footer_copyright_enabled', $request->boolean('footer_copyright_enabled') ? '1' : '0');
            Setting::put('footer_developer_enabled', $request->boolean('footer_developer_enabled') ? '1' : '0');
        }

        if ($section === 'payments') {
                        Setting::put('pay_cod_enabled', $request->boolean('pay_cod_enabled') ? '1' : '0');
            Setting::put('pay_bkash_enabled', $request->boolean('pay_bkash_enabled') ? '1' : '0');
            Setting::put('pay_nagad_enabled', $request->boolean('pay_nagad_enabled') ? '1' : '0');
            Setting::put('pay_rocket_enabled', $request->boolean('pay_rocket_enabled') ? '1' : '0');
Setting::put('show_cards_in_footer', $request->boolean('show_cards_in_footer') ? '1' : '0');
        }

        if ($section === 'fake_order_guard') {
            Setting::put('fog_enabled',                    $request->boolean('fog_enabled') ? '1' : '0');
            Setting::put('fog_ip_block_enabled',           $request->boolean('fog_ip_block_enabled') ? '1' : '0');
            Setting::put('fog_device_block_enabled',       $request->boolean('fog_device_block_enabled') ? '1' : '0');
            Setting::put('fog_phone_block_enabled',        $request->boolean('fog_phone_block_enabled') ? '1' : '0');
            Setting::put('fog_fake_number_block_enabled',  $request->boolean('fog_fake_number_block_enabled') ? '1' : '0');
            Setting::put('fog_auto_block_ip_on_cancel',    $request->boolean('fog_auto_block_ip_on_cancel') ? '1' : '0');
            // BD Courier
            Setting::put('fog_bdcourier_enabled',             $request->boolean('fog_bdcourier_enabled') ? '1' : '0');
            Setting::put('fog_bdcourier_auto_check_checkout', $request->boolean('fog_bdcourier_auto_check_checkout') ? '1' : '0');
            if ($request->filled('fog_bdcourier_api_key')) {
                Setting::put('fog_bdcourier_api_key', (string) $request->input('fog_bdcourier_api_key'));
            }
        }

        if ($section === 'brand') {
            $this->handleImage($request, 'logo_file', 'logo', 'remove_logo');
            $this->handleImage($request, 'favicon_file', 'favicon', 'remove_favicon');
        }
    }

    private function normalizeTypography($value, string $template): string
    {
        $decoded = is_array($value) ? $value : json_decode((string) $value, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages(['theme_typography_' . str_replace('-', '_', $template) => 'Typography settings must be valid JSON.']);
        }

        $fonts = [
            'CozaPoppins, Arial, sans-serif',
            'CozaPlayfair, Georgia, serif',
            'Mulish, ui-sans-serif, system-ui, sans-serif',
            'Outfit, Mulish, ui-sans-serif, system-ui, sans-serif',
            'Arial, sans-serif',
            'Georgia, serif',
            'system-ui, sans-serif',
        ];
        $sizes = ['12px', '13px', '14px', '15px', '16px', '18px', '20px', '22px', '24px', '28px', '30px', '32px', '36px', '40px', '48px'];
        $weights = ['300', '400', '500', '600', '700', '800', '900'];
        $styles = ['normal', 'italic'];
        $transforms = ['none', 'uppercase', 'lowercase', 'capitalize'];
        $normalized = [];

        foreach (self::TYPOGRAPHY_DEFAULTS[$template] as $area => $defaults) {
            $input = is_array($decoded[$area] ?? null) ? $decoded[$area] : [];
            $color = (string) ($input['color'] ?? $defaults['color']);
            if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                $color = $defaults['color'];
            }

            $normalized[$area] = [
                'font' => in_array($input['font'] ?? null, $fonts, true) ? $input['font'] : $defaults['font'],
                'color' => $color,
                'size' => in_array($input['size'] ?? null, $sizes, true) ? $input['size'] : $defaults['size'],
                'weight' => in_array((string) ($input['weight'] ?? ''), $weights, true) ? (string) $input['weight'] : $defaults['weight'],
                'style' => in_array($input['style'] ?? null, $styles, true) ? $input['style'] : $defaults['style'],
                'transform' => in_array($input['transform'] ?? null, $transforms, true) ? $input['transform'] : $defaults['transform'],
            ];
        }

        return json_encode($normalized, JSON_UNESCAPED_SLASHES);
    }

    private function sectionLabel(string $section): string
    {
        return match ($section) {
            'brand' => 'Brand & identity',
            'chat' => 'Floating chat',
            'homepage' => 'Homepage',
            'payments' => 'Payments',
            'shipping' => 'Shipping & tax',
            'mail' => 'Email & OTP',
            'seo' => 'SEO defaults',
            'tracking' => 'Marketing & analytics',
            'legal' => 'Legal pages',
            'courier'           => 'Courier APIs',
            'fake_order_guard'  => 'Fake Order Guard',
            'theme'             => 'Template selection',
            default             => 'Settings',
        };
    }

    private function handleImage(Request $request, string $fileField, string $settingKey, string $removeField): void
    {
        if ($request->boolean($removeField)) {
            $this->deleteStored(Setting::get($settingKey));
            Setting::put($settingKey, '');

            return;
        }

        $path = PublicUploader::storeFromRequest($request, $fileField, 'branding', 'png');
        if ($path) {
            $this->deleteStored(Setting::get($settingKey));
            Setting::put($settingKey, $path);
        }
    }

    private function deleteStored(?string $path): void
    {
        if (! $path || str_starts_with($path, 'http')) {
            return;
        }

        $relative = ltrim($path, '/');
        if (str_starts_with($relative, 'uploads/')) {
            PublicUploader::delete($relative);

            return;
        }

        Storage::disk('public')->delete($relative);
    }
}
