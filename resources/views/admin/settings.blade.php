@extends('layouts.admin')
@section('title', 'Settings')

@section('content')
<div class="settings-page w-full max-w-3xl mx-auto space-y-5 sm:space-y-6">
  <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-1 sm:mb-2">
    <div class="min-w-0">
      <h2 class="font-display text-xl font-bold text-ink">Site Settings</h2>
      <p class="text-sm text-gray-500 mt-1">Save all sections at once, or save each section individually.</p>
    </div>
    <button type="button" id="saveAllSettings" class="btn-primary shrink-0 w-full sm:w-auto">Save all</button>
  </div>
  <div id="saveAllFeedback" class="hidden text-sm rounded-lg px-3 py-2"></div>

  <!-- Brand -->
  <form method="POST" action="{{ route('admin.settings.update-section', 'brand') }}" enctype="multipart/form-data" class="settings-section-form" data-section="brand">
    @csrf @method('PUT')
    <div class="card p-4 sm:p-5 space-y-4">
      <div class="settings-section-head">
        <h3 class="font-semibold text-ink">Brand & identity</h3>
        <button type="submit" class="section-save-btn btn-primary">Save section</button>
      </div>
      <div class="section-feedback hidden text-sm rounded-lg px-3 py-2"></div>

      <div><label class="lbl">Site name</label><input name="site_name" class="inp" value="{{ $settings['site_name'] ?? '' }}" required /></div>
      <div><label class="lbl">Tagline</label><input name="tagline" class="inp" value="{{ $settings['tagline'] ?? '' }}" /></div>
      <div><label class="lbl">Footer text</label><textarea name="footer_text" class="inp" rows="2">{{ $settings['footer_text'] ?? '' }}</textarea></div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
        <div>
          <label class="lbl">Logo</label>
          <div class="flex items-center gap-3">
            <div class="h-14 w-14 rounded-xl bg-brand-50 flex items-center justify-center overflow-hidden shrink-0 ring-1 ring-brand-100" data-logo-preview>
              <img src="{{ logo_url() }}" class="h-full w-full object-contain p-1.5" alt="Logo">
            </div>
            <div class="min-w-0 flex-1">
              <input name="logo_file" type="file" accept="image/png,image/jpeg,image/svg+xml,image/webp" class="text-sm w-full max-w-full" />
              <p class="text-xs text-gray-400 mt-1">PNG, JPG, SVG or WEBP · max 2 MB. Default is the SHARTHAK mark.</p>
              <label class="flex items-center gap-1.5 text-xs text-red-500 mt-1 {{ has_custom_logo() ? '' : 'hidden' }}" data-remove-logo-wrap>
                <input type="checkbox" name="remove_logo" value="1" class="accent-red-500"> Reset to default logo
              </label>
            </div>
          </div>
        </div>

        <div>
          <label class="lbl">Favicon / site icon</label>
          <div class="flex items-center gap-3">
            <div class="h-14 w-14 rounded-xl bg-brand-50 flex items-center justify-center overflow-hidden shrink-0 ring-1 ring-brand-100">
              <img src="{{ favicon_url() }}" class="h-8 w-8 object-contain" alt="Favicon" data-favicon-preview>
            </div>
            <div class="min-w-0 flex-1">
              <input name="favicon_file" type="file" accept="image/png,image/x-icon,image/svg+xml,image/webp,image/jpeg" class="text-sm w-full max-w-full" />
              <p class="text-xs text-gray-400 mt-1">ICO, PNG, SVG or WEBP · max 1 MB.</p>
              <label class="flex items-center gap-1.5 text-xs text-red-500 mt-1 {{ ($settings['favicon'] ?? false) ? '' : 'hidden' }}" data-remove-favicon-wrap>
                <input type="checkbox" name="remove_favicon" value="1" class="accent-red-500"> Reset to default icon
              </label>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div><label class="lbl">Contact phone</label><input name="contact_phone" class="inp" value="{{ $settings['contact_phone'] ?? '' }}" /></div>
        <div><label class="lbl">Contact email</label><input name="contact_email" type="email" class="inp" value="{{ $settings['contact_email'] ?? '' }}" /></div>
      </div>
      <div><label class="lbl">Address</label><input name="contact_address" class="inp" value="{{ $settings['contact_address'] ?? '' }}" /></div>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div><label class="lbl">Contact page title</label><input name="contact_title" class="inp" value="{{ $settings['contact_title'] ?? '' }}" placeholder="Get in touch" /></div>
        <div><label class="lbl">Contact hours</label><input name="contact_hours" class="inp" value="{{ $settings['contact_hours'] ?? '' }}" placeholder="Sat–Thu, 9am – 9pm" /></div>
        <div><label class="lbl">Search placeholder</label><input name="search_placeholder" class="inp" value="{{ $settings['search_placeholder'] ?? '' }}" placeholder="Search Product..." /></div>
      </div>
      <div><label class="lbl">Contact page intro</label><input name="contact_intro" class="inp" value="{{ $settings['contact_intro'] ?? '' }}" placeholder="We usually reply within a few hours." /></div>

      <div class="border-t border-gray-100 pt-4">
        <h4 class="text-sm font-semibold text-ink mb-3">Social links</h4>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div><label class="lbl">Facebook URL</label><input name="facebook_url" class="inp" value="{{ $settings['facebook_url'] ?? '' }}" /></div>
          <div><label class="lbl">Instagram URL</label><input name="instagram_url" class="inp" value="{{ $settings['instagram_url'] ?? '' }}" /></div>
          <div><label class="lbl">Twitter URL</label><input name="twitter_url" class="inp" value="{{ $settings['twitter_url'] ?? '' }}" /></div>
        </div>
      </div>
    </div>
  </form>


  <!-- Floating chat -->
  <form method="POST" action="{{ route('admin.settings.update-section', 'chat') }}" class="settings-section-form" data-section="chat">
    @csrf @method('PUT')
    <div class="card p-4 sm:p-5 space-y-4">
      <div class="settings-section-head">
        <h3 class="font-semibold text-ink">Floating chat</h3>
        <button type="submit" class="section-save-btn btn-primary">Save section</button>
      </div>
      <div class="section-feedback hidden text-sm rounded-lg px-3 py-2"></div>
      <p class="text-sm text-gray-500">Controls the storefront floating chat button (WhatsApp / Messenger).</p>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="lbl">WhatsApp number</label>
          <input name="whatsapp_number" class="inp" value="{{ $settings['whatsapp_number'] ?? '' }}" placeholder="01XXXXXXXXX or 8801XXXXXXXXX" />
          <p class="text-xs text-gray-400 mt-1">Used by the floating chat button. Falls back to contact phone if empty.</p>
        </div>
        <div>
          <label class="lbl">Messenger page</label>
          <input name="messenger_page" class="inp" value="{{ $settings['messenger_page'] ?? '' }}" placeholder="your.page.username" />
          <p class="text-xs text-gray-400 mt-1">Facebook page username for m.me links.</p>
        </div>
      </div>
    </div>
  </form>

  <!-- Homepage -->
  <form method="POST" action="{{ route('admin.settings.update-section', 'homepage') }}" class="settings-section-form" data-section="homepage">
    @csrf @method('PUT')
    <div class="card p-4 sm:p-5 space-y-4">
      <div class="settings-section-head">
        <h3 class="font-semibold text-ink">Homepage</h3>
        <button type="submit" class="section-save-btn btn-primary">Save section</button>
      </div>
      <div class="section-feedback hidden text-sm rounded-lg px-3 py-2"></div>
      <p class="text-sm text-gray-500">Promo bar, homepage blocks, brands marquee, and shop copy. Flash Sale products and countdown are managed under <a href="{{ route('admin.flash-sale.index') }}" class="text-brand-700 font-semibold hover:underline">Flash Sale</a>.</p>

      <div><label class="lbl">Header promo text</label><input name="header_promo_text" class="inp" value="{{ $settings['header_promo_text'] ?? '' }}" placeholder="Buy one get one free on your first order" /></div>
      <div><label class="lbl">Header promo link</label><input name="header_promo_link" class="inp" value="{{ $settings['header_promo_link'] ?? '' }}" placeholder="/shop or full URL" /></div>
      <div><label class="lbl">Shop page subtitle</label><input name="shop_subtitle" class="inp" value="{{ $settings['shop_subtitle'] ?? '' }}" /></div>
      <div><label class="lbl">Delivery ETA text</label><input name="delivery_eta_text" class="inp" value="{{ $settings['delivery_eta_text'] ?? '' }}" placeholder="Estimated delivery in 2–3 days" /></div>
      <div><label class="lbl">Deal of the week ends at</label><input name="deal_ends_at" type="datetime-local" class="inp" value="{{ !empty($settings['deal_ends_at']) ? \Illuminate\Support\Str::of($settings['deal_ends_at'])->replace(' ', 'T')->substr(0, 16) : '' }}" /></div>
      <div><label class="lbl">Homepage category tabs (count)</label><input name="homepage_tab_count" type="number" min="1" max="8" class="inp" value="{{ $settings['homepage_tab_count'] ?? '3' }}" /></div>

      <div class="border-t border-gray-100 pt-4">
        <h4 class="text-sm font-semibold text-ink mb-3">Section titles &amp; labels</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div><label class="lbl">Categories title</label><input name="home_categories_title" class="inp" value="{{ $settings['home_categories_title'] ?? '' }}" placeholder="Categories" /></div>
          <div><label class="lbl">Hot deal title</label><input name="home_hot_deal_title" class="inp" value="{{ $settings['home_hot_deal_title'] ?? '' }}" placeholder="Hot Deal" /></div>
          <div><label class="lbl">Featured products title</label><input name="home_featured_title" class="inp" value="{{ $settings['home_featured_title'] ?? '' }}" placeholder="Just For You" /></div>
          <div><label class="lbl">Latest products title</label><input name="home_deal_week_title" class="inp" value="{{ $settings['home_deal_week_title'] ?? '' }}" placeholder="Latest Products" /></div>
          <div><label class="lbl">Best sellers title</label><input name="home_tabs_title" class="inp" value="{{ $settings['home_tabs_title'] ?? '' }}" placeholder="Best Sellers" /></div>
          <div><label class="lbl">Brands label ({site} = site name)</label><input name="home_brands_label" class="inp" value="{{ $settings['home_brands_label'] ?? '' }}" placeholder="Trusted by top brands on {site}" /></div>
          <div><label class="lbl">"View more" button label</label><input name="home_view_more_label" class="inp" value="{{ $settings['home_view_more_label'] ?? '' }}" placeholder="View More" /></div>
          <div><label class="lbl">Default CTA button text</label><input name="default_cta_text" class="inp" value="{{ $settings['default_cta_text'] ?? '' }}" placeholder="ORDER NOW" /></div>
          <div>
            <label class="lbl">CTA button action</label>
            <select name="product_cta_action" class="inp">
              <option value="checkout" @selected(($settings['product_cta_action'] ?? 'checkout') === 'checkout')>Go to checkout (Order Now)</option>
              <option value="cart" @selected(($settings['product_cta_action'] ?? 'checkout') === 'cart')>Add to cart</option>
            </select>
            <p class="text-xs text-gray-400 mt-1">Checkout replaces cart with that product and goes directly to checkout. Add to cart opens the cart drawer.</p>
          </div>
        </div>
      </div>

      <div class="border-t border-gray-100 pt-4">
        <h4 class="text-sm font-semibold text-ink mb-3">Hero fallback (shown when no hero banners are set)</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div><label class="lbl">Fallback badge</label><input name="hero_fallback_badge" class="inp" value="{{ $settings['hero_fallback_badge'] ?? '' }}" placeholder="SPECIAL OFFER" /></div>
          <div><label class="lbl">Fallback title</label><input name="hero_fallback_title" class="inp" value="{{ $settings['hero_fallback_title'] ?? '' }}" placeholder="NEW ARRIVALS" /></div>
        </div>
        <div class="mt-4"><label class="lbl">Fallback subtitle</label><input name="hero_fallback_subtitle" class="inp" value="{{ $settings['hero_fallback_subtitle'] ?? '' }}" placeholder="Fresh styles and top picks, every week." /></div>
      </div>

      @php $brandsOn = ($settings['show_brands_marquee'] ?? '1') === '1'; @endphp
      <label class="flex items-center justify-between gap-4 rounded-xl border border-brand-100 bg-brand-50/40 px-4 py-3 cursor-pointer">
        <span class="min-w-0">
          <span class="block text-sm font-semibold text-ink">Trusted brands marquee</span>
          <span class="block text-xs text-gray-500 mt-0.5">Scrolling brand names near the bottom of the homepage</span>
        </span>
        <span class="relative inline-flex items-center shrink-0">
          <input type="checkbox" name="show_brands_marquee" value="1" class="peer sr-only" @checked($brandsOn)>
          <span class="h-7 w-12 rounded-full bg-gray-300 transition peer-checked:bg-brand-600"></span>
          <span class="pointer-events-none absolute left-0.5 top-0.5 h-6 w-6 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
        </span>
      </label>
      <div><label class="lbl">Brand names (comma-separated)</label><input name="brands_marquee" class="inp" value="{{ $settings['brands_marquee'] ?? '' }}" placeholder="Sony, Samsung, Apple, Xiaomi" /></div>
    </div>
  </form>

  <!-- Storefront UI -->
  <form method="POST" action="{{ route('admin.settings.update-section', 'storefront_ui') }}" class="settings-section-form" data-section="storefront_ui">
    @csrf @method('PUT')
    <div class="card p-4 sm:p-5 space-y-4 border-l-4 border-blue-500">
      <div class="settings-section-head">
        <h3 class="font-semibold text-ink">Storefront UI</h3>
        <button type="submit" class="section-save-btn btn-primary">Save section</button>
      </div>
      <div class="section-feedback hidden text-sm rounded-lg px-3 py-2"></div>
      <p class="text-sm text-gray-500">Customize the colors and text of product cards and the product details page.</p>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="lbl">Product Card Background</label>
          <div class="flex items-center gap-3">
            <input name="product_card_bg_color" type="color" class="h-10 w-14 rounded cursor-pointer" value="{{ $settings['product_card_bg_color'] ?? '#FFFFFF' }}" />
            <input name="product_card_bg_color_hex" type="text" class="inp flex-1" value="{{ $settings['product_card_bg_color'] ?? '#FFFFFF' }}" oninput="this.previousElementSibling.value = this.value" />
          </div>
        </div>
        <div>
          <label class="lbl">Product Card Text Color</label>
          <div class="flex items-center gap-3">
            <input name="product_card_text_color" type="color" class="h-10 w-14 rounded cursor-pointer" value="{{ $settings['product_card_text_color'] ?? '#111827' }}" />
            <input name="product_card_text_color_hex" type="text" class="inp flex-1" value="{{ $settings['product_card_text_color'] ?? '#111827' }}" oninput="this.previousElementSibling.value = this.value" />
          </div>
        </div>
      </div>

      <div class="border-t border-gray-100 pt-4">
        <h4 class="text-sm font-semibold text-ink mb-3">Product Card Order Button</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
          <div><label class="lbl">Card Order Button Text (All Products)</label><input name="product_card_buy_text" class="inp" value="{{ $settings['product_card_buy_text'] ?? 'অর্ডার করুন' }}" placeholder="অর্ডার করুন" /></div>
          <div><label class="lbl">Direct Click Action</label>
            <select name="product_cta_action" class="inp">
              <option value="checkout" {{ ($settings['product_cta_action'] ?? 'checkout') === 'checkout' ? 'selected' : '' }}>Direct Checkout</option>
              <option value="cart" {{ ($settings['product_cta_action'] ?? '') === 'cart' ? 'selected' : '' }}>Add to Cart</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="lbl">Button Background Color</label>
            <div class="flex items-center gap-3">
              <input name="product_card_btn_bg_color" type="color" class="h-10 w-14 rounded cursor-pointer" value="{{ $settings['product_card_btn_bg_color'] ?? '#f15a24' }}" />
              <input name="product_card_btn_bg_color_hex" type="text" class="inp flex-1" value="{{ $settings['product_card_btn_bg_color'] ?? '#f15a24' }}" oninput="this.previousElementSibling.value = this.value" />
            </div>
          </div>
          <div>
            <label class="lbl">Button Text Color</label>
            <div class="flex items-center gap-3">
              <input name="product_card_btn_text_color" type="color" class="h-10 w-14 rounded cursor-pointer" value="{{ $settings['product_card_btn_text_color'] ?? '#ffffff' }}" />
              <input name="product_card_btn_text_color_hex" type="text" class="inp flex-1" value="{{ $settings['product_card_btn_text_color'] ?? '#ffffff' }}" oninput="this.previousElementSibling.value = this.value" />
            </div>
          </div>
        </div>
      </div>

      <div class="border-t border-gray-100 pt-4">
        <h4 class="text-sm font-semibold text-ink mb-3">Product Details Page Buttons</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div><label class="lbl">"Add to Cart" Text</label><input name="product_page_add_cart_text" class="inp" value="{{ $settings['product_page_add_cart_text'] ?? 'ADD TO CART' }}" placeholder="ADD TO CART" /></div>
          <div><label class="lbl">"Order Now" Text</label><input name="product_page_buy_text" class="inp" value="{{ $settings['product_page_buy_text'] ?? 'ORDER NOW' }}" placeholder="ORDER NOW" /></div>
        </div>
      </div>
    </div>
  </form>

  <!-- Payments -->
  <form method="POST" action="{{ route('admin.settings.update-section', 'payments') }}" class="settings-section-form" data-section="payments">
    @csrf @method('PUT')
    <div class="card p-4 sm:p-5 space-y-4">
      <div class="settings-section-head">
        <h3 class="font-semibold text-ink">Payments (manual mobile banking)</h3>
        <button type="submit" class="section-save-btn btn-primary">Save section</button>
      </div>
      <div class="section-feedback hidden text-sm rounded-lg px-3 py-2"></div>
      <p class="text-sm text-gray-500">Turn methods on/off for checkout. Mobile numbers are shown when that method is enabled.</p>
            <p class="text-sm text-gray-500">Turn methods on/off for checkout. Mobile numbers are shown when that method is enabled.</p>
      <div class="space-y-3">
        @php
          $payCodOn = ($settings['pay_cod_enabled'] ?? '1') === '1';
          $payBkashOn = ($settings['pay_bkash_enabled'] ?? '1') === '1';
          $payNagadOn = ($settings['pay_nagad_enabled'] ?? '1') === '1';
          $payRocketOn = ($settings['pay_rocket_enabled'] ?? '1') === '1';
        @endphp
        <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 px-4 py-3 cursor-pointer">
          <span class="text-sm font-semibold text-ink">Cash on Delivery</span>
          <span class="relative inline-flex items-center shrink-0">
            <input type="checkbox" name="pay_cod_enabled" value="1" class="peer sr-only" @checked($payCodOn)>
            <span class="h-7 w-12 rounded-full bg-gray-300 transition peer-checked:bg-brand-600"></span>
            <span class="pointer-events-none absolute left-0.5 top-0.5 h-6 w-6 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
          </span>
        </label>
        <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 px-4 py-3 cursor-pointer">
          <span class="text-sm font-semibold text-ink">bKash</span>
          <span class="relative inline-flex items-center shrink-0">
            <input type="checkbox" name="pay_bkash_enabled" value="1" class="peer sr-only" @checked($payBkashOn)>
            <span class="h-7 w-12 rounded-full bg-gray-300 transition peer-checked:bg-brand-600"></span>
            <span class="pointer-events-none absolute left-0.5 top-0.5 h-6 w-6 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
          </span>
        </label>
        <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 px-4 py-3 cursor-pointer">
          <span class="text-sm font-semibold text-ink">Nagad</span>
          <span class="relative inline-flex items-center shrink-0">
            <input type="checkbox" name="pay_nagad_enabled" value="1" class="peer sr-only" @checked($payNagadOn)>
            <span class="h-7 w-12 rounded-full bg-gray-300 transition peer-checked:bg-brand-600"></span>
            <span class="pointer-events-none absolute left-0.5 top-0.5 h-6 w-6 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
          </span>
        </label>
        <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 px-4 py-3 cursor-pointer">
          <span class="text-sm font-semibold text-ink">Rocket</span>
          <span class="relative inline-flex items-center shrink-0">
            <input type="checkbox" name="pay_rocket_enabled" value="1" class="peer sr-only" @checked($payRocketOn)>
            <span class="h-7 w-12 rounded-full bg-gray-300 transition peer-checked:bg-brand-600"></span>
            <span class="pointer-events-none absolute left-0.5 top-0.5 h-6 w-6 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
          </span>
        </label>
      </div>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div><label class="lbl">bKash number</label><input name="bkash_number" class="inp" value="{{ $settings['bkash_number'] ?? '' }}" /></div>
        <div><label class="lbl">Nagad number</label><input name="nagad_number" class="inp" value="{{ $settings['nagad_number'] ?? '' }}" /></div>
        <div><label class="lbl">Rocket number</label><input name="rocket_number" class="inp" value="{{ $settings['rocket_number'] ?? '' }}" /></div>
      </div>
      @php $cardsOn = ($settings['show_cards_in_footer'] ?? '0') === '1'; @endphp
      <label class="flex items-center justify-between gap-4 rounded-xl border border-brand-100 bg-brand-50/40 px-4 py-3 cursor-pointer">
        <span class="min-w-0">
          <span class="block text-sm font-semibold text-ink">Show "Cards" in footer payment list</span>
          <span class="block text-xs text-gray-500 mt-0.5">Only enable if you actually accept card payments</span>
        </span>
        <span class="relative inline-flex items-center shrink-0">
          <input type="checkbox" name="show_cards_in_footer" value="1" class="peer sr-only" @checked($cardsOn)>
          <span class="h-7 w-12 rounded-full bg-gray-300 transition peer-checked:bg-brand-600"></span>
          <span class="pointer-events-none absolute left-0.5 top-0.5 h-6 w-6 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
        </span>
      </label>
    </div>
  </form>

  <!-- Shipping + tax -->
  <form method="POST" action="{{ route('admin.settings.update-section', 'shipping') }}" class="settings-section-form" data-section="shipping">
    @csrf @method('PUT')
    <div class="card p-4 sm:p-5 space-y-4">
      <div class="settings-section-head">
        <h3 class="font-semibold text-ink">Shipping & tax</h3>
        <button type="submit" class="section-save-btn btn-primary">Save section</button>
      </div>
      <div class="section-feedback hidden text-sm rounded-lg px-3 py-2"></div>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div><label class="lbl">Inside zone fee (amount)</label><input name="shipping_inside_dhaka" type="number" step="0.01" class="inp" value="{{ $settings['shipping_inside_dhaka'] ?? '' }}" required /></div>
        <div><label class="lbl">Outside zone fee (amount)</label><input name="shipping_outside_dhaka" type="number" step="0.01" class="inp" value="{{ $settings['shipping_outside_dhaka'] ?? '' }}" required /></div>
        <div><label class="lbl">Tax (%)</label><input name="tax_percent" type="number" step="0.01" class="inp" value="{{ $settings['tax_percent'] ?? '' }}" required /></div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div><label class="lbl">Inside zone label</label><input name="shipping_inside_label" class="inp" value="{{ $settings['shipping_inside_label'] ?? '' }}" placeholder="Inside Dhaka" /></div>
        <div><label class="lbl">Outside zone label</label><input name="shipping_outside_label" class="inp" value="{{ $settings['shipping_outside_label'] ?? '' }}" placeholder="Outside Dhaka" /></div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div><label class="lbl">Currency symbol</label><input name="currency_symbol" class="inp" value="{{ $settings['currency_symbol'] ?? '' }}" placeholder="৳" /></div>
        <div><label class="lbl">Currency code</label><input name="currency_code" class="inp" value="{{ $settings['currency_code'] ?? '' }}" placeholder="BDT" /></div>
      </div>
    </div>
  </form>

  <!-- Mail & OTP -->
  <form method="POST" action="{{ route('admin.settings.update-section', 'mail') }}" class="settings-section-form" data-section="mail">
    @csrf @method('PUT')
    <div class="card p-4 sm:p-5 space-y-4">
      <div class="settings-section-head">
        <h3 class="font-semibold text-ink">Email &amp; OTP verification</h3>
        <button type="submit" class="section-save-btn btn-primary">Save section</button>
      </div>
      <div class="section-feedback hidden text-sm rounded-lg px-3 py-2"></div>

      <label class="flex items-start gap-2 text-sm">
        <input type="checkbox" name="otp_enabled" value="1" class="accent-brand-600 mt-0.5 shrink-0" @checked(($settings['otp_enabled'] ?? '1') === '1')>
        <span>Require email OTP verification when customers register / sign in</span>
      </label>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label class="lbl">Mailer</label>
          <select name="mail_mailer" class="inp">
            <option value="log" @selected(($settings['mail_mailer'] ?? 'log') === 'log')>Log (writes to log file)</option>
            <option value="smtp" @selected(($settings['mail_mailer'] ?? '') === 'smtp')>SMTP</option>
          </select>
        </div>
        <div><label class="lbl">From name</label><input name="mail_from_name" class="inp" value="{{ $settings['mail_from_name'] ?? '' }}" /></div>
        <div><label class="lbl">From address</label><input name="mail_from_address" type="email" class="inp" value="{{ $settings['mail_from_address'] ?? '' }}" /></div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="sm:col-span-2"><label class="lbl">SMTP host</label><input name="mail_host" class="inp" value="{{ $settings['mail_host'] ?? '' }}" placeholder="smtp.gmail.com" /></div>
        <div><label class="lbl">Port</label><input name="mail_port" class="inp" value="{{ $settings['mail_port'] ?? '' }}" placeholder="587" /></div>
        <div>
          <label class="lbl">Encryption</label>
          <select name="mail_encryption" class="inp">
            @foreach(['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'] as $val => $lbl)
              <option value="{{ $val }}" @selected(($settings['mail_encryption'] ?? 'tls') === $val)>{{ $lbl }}</option>
            @endforeach
          </select>
        </div>
        <div class="sm:col-span-2"><label class="lbl">SMTP username</label><input name="mail_username" class="inp" value="{{ $settings['mail_username'] ?? '' }}" /></div>
        <div class="sm:col-span-2"><label class="lbl">SMTP password</label><input name="mail_password" type="password" class="inp" placeholder="{{ ($settings['mail_password'] ?? '') ? '•••••• (leave blank to keep)' : '' }}" autocomplete="new-password" /></div>
      </div>
      <p class="text-xs text-gray-400">Keep <b>Log</b> if SMTP isn't set up yet — OTP codes are written to <code>storage/logs/laravel.log</code>.</p>
    </div>
  </form>

  <!-- SEO defaults -->
  <form method="POST" action="{{ route('admin.settings.update-section', 'seo') }}" class="settings-section-form" data-section="seo">
    @csrf @method('PUT')
    <div class="card p-4 sm:p-5 space-y-4">
      <div class="settings-section-head">
        <h3 class="font-semibold text-ink">SEO defaults</h3>
        <button type="submit" class="section-save-btn btn-primary">Save section</button>
      </div>
      <div class="section-feedback hidden text-sm rounded-lg px-3 py-2"></div>
      <div><label class="lbl">Default meta title</label><input name="default_meta_title" class="inp" value="{{ $settings['default_meta_title'] ?? '' }}" /></div>
      <div><label class="lbl">Default meta description</label><textarea name="default_meta_description" class="inp" rows="2">{{ $settings['default_meta_description'] ?? '' }}</textarea></div>
      <div><label class="lbl">Default meta keywords</label><textarea name="default_meta_keywords" class="inp" rows="2">{{ $settings['default_meta_keywords'] ?? '' }}</textarea></div>
    </div>
  </form>

  <!-- Marketing & analytics -->
  <form method="POST" action="{{ route('admin.settings.update-section', 'tracking') }}" class="settings-section-form" data-section="tracking">
    @csrf @method('PUT')
    <div class="card p-4 sm:p-5 space-y-4">
      <div class="settings-section-head">
        <h3 class="font-semibold text-ink">Marketing &amp; analytics</h3>
        <button type="submit" class="section-save-btn btn-primary">Save section</button>
      </div>
      <div class="section-feedback hidden text-sm rounded-lg px-3 py-2"></div>
      <p class="text-sm text-gray-500">Optional tracking tags for the public storefront only. Leave blank to disable each tag.</p>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label class="lbl">Google Tag Manager</label>
          <input name="tracking_gtm_id" class="inp font-mono text-sm" value="{{ $settings['tracking_gtm_id'] ?? '' }}" placeholder="GTM-XXXXXXX" />
          <p class="text-xs text-gray-400 mt-1">Container ID from tagmanager.google.com</p>
        </div>
        <div>
          <label class="lbl">Google Analytics 4</label>
          <input name="tracking_ga4_id" class="inp font-mono text-sm" value="{{ $settings['tracking_ga4_id'] ?? '' }}" placeholder="G-XXXXXXXXXX" />
          <p class="text-xs text-gray-400 mt-1">Leave empty if GA4 runs inside GTM.</p>
        </div>
        <div>
          <label class="lbl">Meta (Facebook) Pixel</label>
          <input name="tracking_meta_pixel_id" class="inp font-mono text-sm" value="{{ $settings['tracking_meta_pixel_id'] ?? '' }}" placeholder="123456789012345" />
          <p class="text-xs text-gray-400 mt-1">Numeric Pixel ID from Meta Events Manager</p>
        </div>
      </div>
      <p class="text-xs font-medium {{ tracking_any_enabled() ? 'text-brand-700' : 'text-gray-400 hidden' }}" data-tracking-status>
        @if(tracking_any_enabled())
          Active on storefront: @if(tracking_gtm_id()) GTM @endif @if(tracking_ga4_id()) GA4 @endif @if(tracking_meta_pixel_id()) Meta Pixel @endif
        @endif
      </p>
    </div>
  </form>

  <!-- Legal -->
  <form method="POST" action="{{ route('admin.settings.update-section', 'legal') }}" class="settings-section-form" data-section="legal">
    @csrf @method('PUT')
    <div class="card p-4 sm:p-5 space-y-4">
      <div class="settings-section-head">
        <h3 class="font-semibold text-ink">Legal pages</h3>
        <button type="submit" class="section-save-btn btn-primary">Save section</button>
      </div>
      <div class="section-feedback hidden text-sm rounded-lg px-3 py-2"></div>
      <p class="text-sm text-gray-500">Shown on /terms and /privacy. Leave blank to use the built-in defaults.</p>
      <div><label class="lbl">Terms of Service</label><textarea name="terms_content" class="inp" rows="6">{{ $settings['terms_content'] ?? '' }}</textarea></div>
      <div><label class="lbl">Privacy Policy</label><textarea name="privacy_content" class="inp" rows="6">{{ $settings['privacy_content'] ?? '' }}</textarea></div>
    </div>
  </form>

  <!-- Test email -->
  <form method="POST" action="{{ route('admin.settings.test-mail') }}" class="settings-section-form" data-section="test-mail">
    @csrf
    <div class="card p-4 sm:p-5 space-y-4">
      <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-end gap-3">
        <div class="flex-1 min-w-0 sm:min-w-[220px]">
          <label class="lbl">Send a test email to</label>
          <input name="test_email" type="email" class="inp" value="{{ auth()->user()->email }}" />
        </div>
        <button type="submit" class="section-save-btn w-full sm:w-auto px-4 py-2.5 text-sm rounded-xl border border-brand-200 bg-white font-medium hover:bg-brand-50 text-ink">Send test email</button>
      </div>
      <div class="section-feedback hidden text-sm rounded-lg px-3 py-2"></div>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  function showFeedback(el, type, html) {
    if (!el) return;
    el.classList.remove('hidden', 'bg-green-50', 'border-green-200', 'text-green-700', 'bg-brand-50', 'border-brand-200', 'text-brand-800', 'bg-red-50', 'border-red-200', 'text-red-700', 'border');
    el.classList.add('border', type === 'ok' ? 'bg-brand-50' : 'bg-red-50', type === 'ok' ? 'border-brand-200' : 'border-red-200', type === 'ok' ? 'text-brand-800' : 'text-red-700');
    el.innerHTML = html;
  }

  function formatErrors(payload) {
    if (payload.errors) {
      var items = Object.values(payload.errors).flat().map(function (m) { return '<li>' + m + '</li>'; }).join('');
      return '<ul class="list-disc list-inside">' + items + '</ul>';
    }
    return payload.message || 'Something went wrong.';
  }

  function updateBrandPreviews(form, data) {
    var logoBox = form.querySelector('[data-logo-preview]');
    var faviconImg = form.querySelector('[data-favicon-preview]');
    var removeLogoWrap = form.querySelector('[data-remove-logo-wrap]');
    var removeFaviconWrap = form.querySelector('[data-remove-favicon-wrap]');

    if (logoBox && data.logo_url) {
      logoBox.innerHTML = '<img src="' + data.logo_url + '?t=' + Date.now() + '" class="h-full w-full object-contain p-1.5" alt="Logo">';
      if (removeLogoWrap) removeLogoWrap.classList.toggle('hidden', !data.has_logo);
      var removeLogo = form.querySelector('[name="remove_logo"]');
      if (removeLogo && !data.has_logo) removeLogo.checked = false;
    }

    if (faviconImg && data.favicon_url) {
      faviconImg.src = data.favicon_url + '?t=' + Date.now();
      if (removeFaviconWrap) removeFaviconWrap.classList.toggle('hidden', !data.has_favicon);
      var removeFavicon = form.querySelector('[name="remove_favicon"]');
      if (removeFavicon) removeFavicon.checked = false;
    }
  }

  function updateTrackingStatus(form, data) {
    var el = form.querySelector('[data-tracking-status]');
    if (!el) return;
    if (data.tracking_active && data.tracking_labels && data.tracking_labels.length) {
      el.textContent = 'Active on storefront: ' + data.tracking_labels.join(' ');
      el.classList.remove('hidden', 'text-gray-400');
      el.classList.add('text-brand-700');
    } else {
      el.classList.add('hidden');
      el.textContent = '';
    }
  }

  function saveForm(form) {
    var btn = form.querySelector('.section-save-btn');
    var feedback = form.querySelector('.section-feedback');
    var defaultLabel = btn ? btn.textContent : '';
    var isTestMail = form.dataset.section === 'test-mail';

    if (btn) {
      btn.disabled = true;
      btn.textContent = isTestMail ? 'Sending…' : 'Saving…';
    }

    return fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf,
      },
      credentials: 'same-origin',
    })
      .then(function (res) {
        return res.text().then(function (text) {
          var data = {};
          if (text) {
            try {
              data = JSON.parse(text);
            } catch (e) {
              throw {
                message: res.ok
                  ? 'Server returned a non-JSON response. If you uploaded an image, check public/uploads permissions on cPanel.'
                  : ('Save failed (HTTP ' + res.status + '). The server may have blocked the upload (size limit or ModSecurity).'),
              };
            }
          }
          if (!res.ok) throw data;
          return data;
        });
      })
      .then(function (data) {
        showFeedback(feedback, 'ok', data.message || 'Saved.');
        if (form.dataset.section === 'brand') updateBrandPreviews(form, data);
        if (form.dataset.section === 'tracking') updateTrackingStatus(form, data);
        if (form.dataset.section === 'mail') {
          var pw = form.querySelector('[name="mail_password"]');
          if (pw) pw.value = '';
        }
        form.querySelectorAll('input[type="file"]').forEach(function (input) { input.value = ''; });
        return data;
      })
      .catch(function (err) {
        showFeedback(feedback, 'err', formatErrors(err && typeof err === 'object' ? err : { message: String(err) }));
        throw err;
      })
      .finally(function () {
        if (btn) {
          btn.disabled = false;
          btn.textContent = defaultLabel;
        }
      });
  }

  function formHasFiles(form) {
    return Array.from(form.querySelectorAll('input[type="file"]')).some(function (input) {
      return input.files && input.files.length > 0;
    });
  }

  document.querySelectorAll('.settings-section-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      // Native multipart submit is more reliable for images on LiteSpeed/cPanel than fetch.
      if (formHasFiles(form)) {
        return;
      }
      e.preventDefault();
      saveForm(form).catch(function () {});
    });
  });

  var saveAllBtn = document.getElementById('saveAllSettings');
  var saveAllFeedback = document.getElementById('saveAllFeedback');
  if (saveAllBtn) {
    saveAllBtn.addEventListener('click', function () {
      var forms = Array.from(document.querySelectorAll('.settings-section-form')).filter(function (f) {
        return f.dataset.section !== 'test-mail';
      });
      var withFiles = forms.find(formHasFiles);
      if (withFiles) {
        showFeedback(saveAllFeedback, 'err', 'Save the Brand section first (it has an image selected), then use Save all for the rest.');
        withFiles.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }
      var defaultLabel = saveAllBtn.textContent;
      saveAllBtn.disabled = true;
      saveAllBtn.textContent = 'Saving all…';
      showFeedback(saveAllFeedback, 'ok', 'Saving all sections…');

      forms.reduce(function (chain, form) {
        return chain.then(function () { return saveForm(form); });
      }, Promise.resolve())
        .then(function () {
          showFeedback(saveAllFeedback, 'ok', 'All settings saved.');
        })
        .catch(function () {
          showFeedback(saveAllFeedback, 'err', 'Some sections failed to save. Check the section errors below.');
        })
        .finally(function () {
          saveAllBtn.disabled = false;
          saveAllBtn.textContent = defaultLabel;
        });
    });
  }
})();
</script>
@endpush
