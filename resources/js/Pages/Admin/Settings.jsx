import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import { fileToBase64 } from '@/lib/utils';

const inputClass = "w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300";
const checkboxClass = "h-4 w-4 accent-orange-500 rounded";

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>'"]/g, char => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
  }[char]));
}

function shippingPreviewContent(data) {
  const currency = escapeHtml(data.currency_symbol || '৳');
  const insideLabel = escapeHtml(data.shipping_inside_label || 'Inside Dhaka');
  const outsideLabel = escapeHtml(data.shipping_outside_label || 'Outside Dhaka');
  const insideCharge = escapeHtml(data.shipping_inside_dhaka || '0');
  const outsideCharge = escapeHtml(data.shipping_outside_dhaka || '0');
  const additionalContent = String(data.shipping_content || '').trim();
  const defaultInformation = '<h2>Delivery Information</h2><p>Please provide a complete delivery address and a reachable phone number when placing your order. Our team may contact you to confirm the order before dispatch.</p><p>Shipping charges and delivery availability are applied according to the current store configuration at checkout.</p>';

  return `<h2>Shipping Charges</h2><p>Your delivery charge is calculated at checkout from the delivery area you choose.</p><ul><li><strong>${insideLabel}:</strong> ${currency}${insideCharge}</li><li><strong>${outsideLabel}:</strong> ${currency}${outsideCharge}</li></ul>${additionalContent || defaultInformation}`;
}

const legalPageEditors = [
  { id: 'terms_content', tab: 'Terms', title: 'Terms & Conditions', path: '/terms' },
  { id: 'privacy_content', tab: 'Privacy', title: 'Privacy Policy', path: '/privacy' },
  { id: 'refund_content', tab: 'Refund', title: 'Refund Policy', path: '/refund-policy' },
  { id: 'shipping_content', tab: 'Shipping', title: 'Additional Shipping Information', path: '/shipping', isShipping: true },
];

function policyPreviewDocument(title, content) {
  return `<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>${title}</title>
    <style>
      * { box-sizing: border-box; }
      body { margin: 0; padding: 32px 20px; background: #f8fafc; color: #263238; font-family: Arial, Helvetica, sans-serif; }
      body > p { max-width: 720px; margin: 48px auto; text-align: center; color: #64748b; }
    </style>
  </head>
  <body>${content || '<p>Add policy content, then select Preview.</p>'}</body>
</html>`;
}

function Field({ label, error, children }) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1.5">{label}</label>
      {children}
      {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
    </div>
  );
}

const typographyDefaults = {
  'template-1': {
    body: { font: 'CozaPoppins, Arial, sans-serif', color: '#666666', size: '14px', weight: '400', style: 'normal', transform: 'none' },
    header: { font: 'CozaPoppins, Arial, sans-serif', color: '#333333', size: '14px', weight: '700', style: 'normal', transform: 'none' },
    hero: { font: 'CozaPoppins, Arial, sans-serif', color: '#333333', size: '16px', weight: '400', style: 'normal', transform: 'none' },
    section: { font: 'CozaPoppins, Arial, sans-serif', color: '#222222', size: '30px', weight: '700', style: 'normal', transform: 'none' },
    product: { font: 'CozaPoppins, Arial, sans-serif', color: '#333333', size: '14px', weight: '400', style: 'normal', transform: 'none' },
    button: { font: 'CozaPoppins, Arial, sans-serif', color: '#ffffff', size: '14px', weight: '700', style: 'normal', transform: 'uppercase' },
    footer: { font: 'CozaPoppins, Arial, sans-serif', color: '#b2b2b2', size: '13px', weight: '400', style: 'normal', transform: 'none' },
  },
  'template-2': {
    body: { font: 'Mulish, ui-sans-serif, system-ui, sans-serif', color: '#111827', size: '14px', weight: '400', style: 'normal', transform: 'none' },
    header: { font: 'Mulish, ui-sans-serif, system-ui, sans-serif', color: '#0b1c21', size: '14px', weight: '700', style: 'normal', transform: 'none' },
    hero: { font: 'Mulish, ui-sans-serif, system-ui, sans-serif', color: '#111827', size: '16px', weight: '500', style: 'normal', transform: 'none' },
    section: { font: 'Outfit, Mulish, ui-sans-serif, system-ui, sans-serif', color: '#111827', size: '24px', weight: '800', style: 'normal', transform: 'none' },
    product: { font: 'Mulish, ui-sans-serif, system-ui, sans-serif', color: '#111827', size: '14px', weight: '700', style: 'normal', transform: 'none' },
    button: { font: 'Mulish, ui-sans-serif, system-ui, sans-serif', color: '#ffffff', size: '14px', weight: '700', style: 'normal', transform: 'none' },
    footer: { font: 'Mulish, ui-sans-serif, system-ui, sans-serif', color: '#6b7280', size: '14px', weight: '400', style: 'normal', transform: 'none' },
  },
};

function readTypography(raw, template) {
  let saved = raw;
  if (typeof raw === 'string') {
    try { saved = JSON.parse(raw); } catch (e) { saved = {}; }
  }
  return Object.keys(typographyDefaults[template]).reduce((result, area) => ({
    ...result,
    [area]: { ...typographyDefaults[template][area], ...(saved?.[area] || {}) },
  }), {});
}

const typographyAreaLabels = {
  body: 'Body & general text',
  header: 'Header & menu',
  hero: 'Hero & banners',
  section: 'Section headings',
  product: 'Product cards',
  button: 'Buttons & actions',
  footer: 'Footer',
};

function TypographyEditor({ template, value, onChange, onReset }) {
  const update = (area, key, nextValue) => onChange({
    ...value,
    [area]: { ...value[area], [key]: nextValue },
  });

  return (
    <div className="space-y-3">
      {Object.entries(typographyAreaLabels).map(([area, label]) => {
        const current = value[area] || typographyDefaults[template][area];
        return (
          <div key={area} className="rounded-xl border border-gray-100 bg-white p-3">
            <div className="grid grid-cols-1 lg:grid-cols-[1.2fr_1.5fr_88px_100px_100px_120px] gap-3 items-end">
              <p className="text-sm font-semibold text-gray-800">{label}</p>
              <Field label="Font family">
                <select value={current.font} onChange={e => update(area, 'font', e.target.value)} className={inputClass}>
                  <option value="CozaPoppins, Arial, sans-serif">Poppins</option>
                  <option value="CozaPlayfair, Georgia, serif">Playfair Display</option>
                  <option value="Mulish, ui-sans-serif, system-ui, sans-serif">Mulish</option>
                  <option value="Outfit, Mulish, ui-sans-serif, system-ui, sans-serif">Outfit</option>
                  <option value="Arial, sans-serif">Arial</option>
                  <option value="Georgia, serif">Georgia</option>
                  <option value="system-ui, sans-serif">System</option>
                </select>
              </Field>
              <Field label="Color">
                <input type="color" value={current.color} onChange={e => update(area, 'color', e.target.value)} className="h-[42px] w-full cursor-pointer rounded-xl border border-gray-200 bg-white p-1" />
              </Field>
              <Field label="Size">
                <select value={current.size} onChange={e => update(area, 'size', e.target.value)} className={inputClass}>
                  {['12px', '13px', '14px', '15px', '16px', '18px', '20px', '22px', '24px', '28px', '30px', '32px', '36px', '40px', '48px'].map(size => <option key={size} value={size}>{size}</option>)}
                </select>
              </Field>
              <Field label="Weight">
                <select value={current.weight} onChange={e => update(area, 'weight', e.target.value)} className={inputClass}>
                  {['300', '400', '500', '600', '700', '800', '900'].map(weight => <option key={weight} value={weight}>{weight}</option>)}
                </select>
              </Field>
              <Field label="Style / case">
                <div className="grid grid-cols-2 gap-1">
                  <select value={current.style} onChange={e => update(area, 'style', e.target.value)} className={inputClass} aria-label={`${label} font style`}>
                    <option value="normal">Normal</option>
                    <option value="italic">Italic</option>
                  </select>
                  <select value={current.transform} onChange={e => update(area, 'transform', e.target.value)} className={inputClass} aria-label={`${label} text case`}>
                    <option value="none">Aa</option>
                    <option value="capitalize">Aa Bb</option>
                    <option value="uppercase">AB</option>
                    <option value="lowercase">ab</option>
                  </select>
                </div>
              </Field>
            </div>
          </div>
        );
      })}
      <button type="button" onClick={onReset} className="text-sm font-semibold text-orange-600 hover:text-orange-700">Reset this template typography</button>
    </div>
  );
}

export default function Settings({ settings, templateStatus = {} }) {
  const [activeTab, setActiveTab] = useState('brand');
  const [testEmail, setTestEmail] = useState('');
  const [processing, setProcessing] = useState(false);
  const [legalPreview, setLegalPreview] = useState(null);
  const [activeLegalEditor, setActiveLegalEditor] = useState('terms_content');

  const { data, setData, errors, setError, clearErrors } = useForm({
    // Brand
    site_name: settings.site_name || '', tagline: settings.tagline || '', footer_text: settings.footer_text || '',
    footer_copyright_enabled: settings.footer_copyright_enabled !== '0' && settings.footer_copyright_enabled !== false,
    footer_copyright_text: settings.footer_copyright_text || '',
    footer_copyright_url: settings.footer_copyright_url || '',
    footer_developer_enabled: settings.footer_developer_enabled === '1' || settings.footer_developer_enabled === true,
    footer_developer_label: settings.footer_developer_label || 'Developed by', footer_developer_name: settings.footer_developer_name || '', footer_developer_url: settings.footer_developer_url || '',
    contact_phone: settings.contact_phone || '', contact_email: settings.contact_email || '', contact_address: settings.contact_address || '',
    contact_hours: settings.contact_hours || '', contact_title: settings.contact_title || '', contact_intro: settings.contact_intro || '',
    facebook_url: settings.facebook_url || '', instagram_url: settings.instagram_url || '', twitter_url: settings.twitter_url || '', search_placeholder: settings.search_placeholder || '',
    logo_file: null, favicon_file: null, remove_logo: false, remove_favicon: false,
    
    // Chat
    chat_enabled: settings.chat_enabled === '1' || settings.chat_enabled === true,
    whatsapp_number: settings.whatsapp_number || '', messenger_page: settings.messenger_page || '',
    call_number: settings.call_number || '',
    
    // Homepage
    show_brands_marquee: settings.show_brands_marquee === '1', brands_marquee: settings.brands_marquee || '',
    header_promo_text: settings.header_promo_text || '', header_promo_link: settings.header_promo_link || '', shop_subtitle: settings.shop_subtitle || '',
    deal_ends_at: settings.deal_ends_at ? settings.deal_ends_at.slice(0, 16) : '', delivery_eta_text: settings.delivery_eta_text || '',
    homepage_tab_count: settings.homepage_tab_count || '4', home_categories_title: settings.home_categories_title || '', home_hot_deal_title: settings.home_hot_deal_title || '',
    home_featured_title: settings.home_featured_title || '', home_deal_week_title: settings.home_deal_week_title || '', home_tabs_title: settings.home_tabs_title || '',
    home_brands_label: settings.home_brands_label || '', home_view_more_label: settings.home_view_more_label || '', default_cta_text: settings.default_cta_text || '',
    product_cta_action: settings.product_cta_action || 'checkout', hero_fallback_badge: settings.hero_fallback_badge || '', hero_fallback_title: settings.hero_fallback_title || '',
    hero_fallback_subtitle: settings.hero_fallback_subtitle || '',

    // Storefront UI
    product_card_bg_color: settings.product_card_bg_color || '#FFFFFF',
    product_card_text_color: settings.product_card_text_color || '#111827',
    product_card_btn_bg_color: settings.product_card_btn_bg_color || '#f15a24',
    product_card_btn_text_color: settings.product_card_btn_text_color || '#ffffff',
    product_card_buy_text: settings.product_card_buy_text || 'Order Now',
    product_card_options_text: settings.product_card_options_text || 'Order Now',
    product_page_add_cart_text: settings.product_page_add_cart_text || 'ADD TO CART',
    product_page_add_cart_bg_color: settings.product_page_add_cart_bg_color || '#f15a24',
    product_page_add_cart_text_color: settings.product_page_add_cart_text_color || '#ffffff',
    product_page_buy_text: settings.product_page_buy_text || 'ORDER NOW',
    product_page_buy_bg_color: settings.product_page_buy_bg_color || '#0b1c21',
    product_page_buy_text_color: settings.product_page_buy_text_color || '#ffffff',

    // COD Quick Order Button
    product_page_cod_enabled: settings.product_page_cod_enabled !== '0',
    product_page_cod_text: settings.product_page_cod_text || 'ক্যাশ অন ডেলিভারিতে অর্ডার করুন',
    product_page_cod_bg_color: settings.product_page_cod_bg_color || '#16a34a',
    product_page_cod_text_color: settings.product_page_cod_text_color || '#ffffff',

    // WhatsApp Order Button
    product_page_whatsapp_enabled: settings.product_page_whatsapp_enabled !== '0',
    product_page_whatsapp_text: settings.product_page_whatsapp_text || 'WhatsApp Order',
    product_page_whatsapp_number: settings.whatsapp_number || settings.product_page_whatsapp_number || '',
    product_page_whatsapp_bg_color: settings.product_page_whatsapp_bg_color || '#25D366',
    product_page_whatsapp_text_color: settings.product_page_whatsapp_text_color || '#ffffff',

    // Call For Order Button
    product_page_call_enabled: settings.product_page_call_enabled !== '0',
    product_page_call_text: settings.product_page_call_text || 'Call For Order',
    product_page_call_number: settings.product_page_call_number || settings.call_number || settings.contact_phone || '',
    product_page_call_bg_color: settings.product_page_call_bg_color || '#294294',
    product_page_call_text_color: settings.product_page_call_text_color || '#ffffff',

    // Payments
    bkash_number: settings.bkash_number || '', nagad_number: settings.nagad_number || '', rocket_number: settings.rocket_number || '',
    pay_cod_enabled: settings.pay_cod_enabled === '1', pay_bkash_enabled: settings.pay_bkash_enabled === '1', pay_nagad_enabled: settings.pay_nagad_enabled === '1', pay_rocket_enabled: settings.pay_rocket_enabled === '1',
    show_cards_in_footer: settings.show_cards_in_footer === '1',
    cod_delivery_upfront: settings.cod_delivery_upfront !== '0',
    
    // Shipping
    shipping_inside_dhaka: settings.shipping_inside_dhaka || '0', shipping_outside_dhaka: settings.shipping_outside_dhaka || '0', tax_percent: settings.tax_percent || '0',
    fraud_order_time_limit_minutes: settings.fraud_order_time_limit_minutes || '10',
    shipping_inside_label: settings.shipping_inside_label || '', shipping_outside_label: settings.shipping_outside_label || '', currency_symbol: settings.currency_symbol || '৳', currency_code: settings.currency_code || 'BDT',
    checkout_delivery_note_enabled: settings.checkout_delivery_note_enabled !== '0',
    checkout_delivery_note_label: settings.checkout_delivery_note_label || 'ডেলিভারি সংক্রান্ত বিশেষ নোট (ঐচ্ছিক)',
    
    // Mail
    otp_enabled: settings.otp_enabled === '1', mail_mailer: settings.mail_mailer || 'log', mail_host: settings.mail_host || '', mail_port: settings.mail_port || '',
    mail_username: settings.mail_username || '', mail_password: '', mail_encryption: settings.mail_encryption || 'none', mail_from_address: settings.mail_from_address || '', mail_from_name: settings.mail_from_name || '',
    
    // SEO
    default_meta_title: settings.default_meta_title || '', default_meta_description: settings.default_meta_description || '', default_meta_keywords: settings.default_meta_keywords || '',
    
    // Tracking
    tracking_gtm_id: settings.tracking_gtm_id || '', tracking_ga4_id: settings.tracking_ga4_id || '', tracking_meta_pixel_id: settings.tracking_meta_pixel_id || '',
    
    // Legal
    terms_content: settings.terms_content || '', privacy_content: settings.privacy_content || '', refund_content: settings.refund_content || '', shipping_content: settings.shipping_content || '', shipping_page_enabled: settings.shipping_page_enabled !== '0',

    // Courier APIs
    courier_default:      settings.courier_default      || 'steadfast',
    steadfast_api_key:    settings.steadfast_api_key    || '',
    steadfast_secret_key: settings.steadfast_secret_key || '',
    pathao_client_id:     settings.pathao_client_id     || '',
    pathao_client_secret: settings.pathao_client_secret || '',
    pathao_username:      settings.pathao_username      || '',
    pathao_password:      settings.pathao_password      || '',
    pathao_store_id:      settings.pathao_store_id      || '',
    redx_api_token:       settings.redx_api_token       || '',

    // Fake Order Guard
    fog_enabled:                      settings.fog_enabled                      === '1',
    fog_ip_block_enabled:             settings.fog_ip_block_enabled             === '1',
    fog_device_block_enabled:         settings.fog_device_block_enabled         === '1',
    fog_phone_block_enabled:          settings.fog_phone_block_enabled          === '1',
    fog_fake_number_block_enabled:    settings.fog_fake_number_block_enabled    === '1',
    fog_auto_block_ip_on_cancel:      settings.fog_auto_block_ip_on_cancel      === '1',
    fog_bdcourier_enabled:            settings.fog_bdcourier_enabled            === '1',
    fog_bdcourier_auto_check_checkout:settings.fog_bdcourier_auto_check_checkout=== '1',
    fog_bdcourier_api_key:            settings.fog_bdcourier_api_key            || '',
    fog_bdcourier_min_success_rate:   settings.fog_bdcourier_min_success_rate   || '0',
    fog_bdcourier_block_risk_levels:  settings.fog_bdcourier_block_risk_levels  || 'danger,high',
    fog_ip_cooldown_minutes:          settings.fog_ip_cooldown_minutes          || '0',
    fog_phone_cooldown_minutes:       settings.fog_phone_cooldown_minutes       || '0',
    fog_max_orders_per_phone:         settings.fog_max_orders_per_phone         || '0',

    // Popup Notification
    popup_enabled:        settings.popup_enabled        === '1',
    popup_text:           settings.popup_text           || '',
    popup_link:           settings.popup_link           || '',
    popup_btn_label:      settings.popup_btn_label      || 'Shop Now',
    popup_delay_seconds:  settings.popup_delay_seconds  || '3',
    popup_image_file:     null,
    popup_remove_image:   false,

    // Storefront Template
    storefront_template: settings.storefront_template || 'template-2',
    template_1_navbar_menu: settings.template_1_navbar_menu || 'coza',
    template_1_show_search: settings.template_1_show_search === '1',
    template_1_category_per_row: settings.template_1_category_per_row || '3',
    template_1_product_per_row: settings.template_1_product_per_row || '5',
    template_2_product_per_row: settings.template_2_product_per_row || '5',
    template_1_products_per_page: settings.template_1_products_per_page || '12',
    template_2_products_per_page: settings.template_2_products_per_page || '12',
    template_1_hero_overlay_color: settings.template_1_hero_overlay_color || '#ffffff',
    template_1_hero_overlay_opacity: settings.template_1_hero_overlay_opacity || '28',
    template_1_hero_text_background_color: settings.template_1_hero_text_background_color || '#1f2430',
    template_1_hero_text_background_opacity: settings.template_1_hero_text_background_opacity || '88',
    template_1_hero_text_position: settings.template_1_hero_text_position || 'left',
    template_1_category_title_color: settings.template_1_category_title_color || '#ffffff',
    template_1_category_secondary_color: settings.template_1_category_secondary_color || '#f5f7ff',
    template_1_category_overlay_color: settings.template_1_category_overlay_color || '#1f2430',
    template_1_category_overlay_opacity: settings.template_1_category_overlay_opacity || '58',
    template_1_category_hover_overlay_opacity: settings.template_1_category_hover_overlay_opacity || '82',
    template_1_category_title_size: settings.template_1_category_title_size || '28px',
    template_1_category_title_weight: settings.template_1_category_title_weight || '700',
    template_1_category_title_style: settings.template_1_category_title_style || 'normal',
    template_1_category_title_transform: settings.template_1_category_title_transform || 'none',
    template_1_category_text_align: settings.template_1_category_text_align || 'left',
    template_1_category_text_shadow: settings.template_1_category_text_shadow !== '0',
    template_1_overview_all_count: settings.template_1_overview_all_count || '16',
    template_1_overview_featured_count: settings.template_1_overview_featured_count || '12',
    template_1_overview_new_count: settings.template_1_overview_new_count || '12',
    template_1_overview_best_count: settings.template_1_overview_best_count || '12',
    template_2_overview_featured_count: settings.template_2_overview_featured_count || '12',
    template_2_overview_new_count: settings.template_2_overview_new_count || '12',
    template_2_overview_best_count: settings.template_2_overview_best_count || '12',
    theme_typography_template_1: readTypography(settings.theme_typography_template_1, 'template-1'),
    theme_typography_template_2: readTypography(settings.theme_typography_template_2, 'template-2'),
  });

  const selectedLegalPage = legalPageEditors.find(page => page.id === activeLegalEditor) || legalPageEditors[0];

  const submitSection = async (e, section) => {
    e.preventDefault();
    clearErrors();
    setProcessing(true);
    try {
      const formData = new FormData();
      for (const [k, v] of Object.entries(data)) {
        if (v === null || v === undefined) continue;
        if (v instanceof File) {
          const b64 = await fileToBase64(v);
          formData.append(k + '_b64', b64);
          formData.append(k + '_name', v.name || `${k}.jpg`);
        } else if (typeof v === 'boolean') {
          formData.append(k, v ? '1' : '0');
        } else if (k === 'theme_typography_template_1' || k === 'theme_typography_template_2') {
          formData.append(k, JSON.stringify(v));
        } else {
          formData.append(k, v);
        }
      }
      formData.append('_method', 'PUT');
      router.post(`/admin/settings/${section}`, formData, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
          if (section === 'mail') setData('mail_password', '');
          if (section === 'brand') { setData('logo_file', null); setData('favicon_file', null); setData('remove_logo', false); setData('remove_favicon', false); }
        },
        onError: (errs) => setError(errs),
        onFinish: () => setProcessing(false),
      });
    } catch (err) {
      setProcessing(false);
      console.error(err);
    }
  };

  const handleTestMail = () => {
    if (!testEmail) return alert('Enter email to test.');
    router.post('/admin/settings/test-mail', { test_email: testEmail }, { preserveScroll: true, onSuccess: () => setTestEmail('') });
  };

  const tabs = [
    {
      id: 'brand', label: 'Brand & General',
      icon: <svg viewBox="0 0 24 24" className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><circle cx="7" cy="7" r="1.5" fill="currentColor" stroke="none"/></svg>
    },
    {
      id: 'chat', label: 'Live Chat',
      icon: <svg viewBox="0 0 24 24" className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
    },
    {
      id: 'homepage', label: 'Homepage Config',
      icon: <svg viewBox="0 0 24 24" className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
    },
    {
      id: 'theme', label: 'Template',
      icon: <svg viewBox="0 0 24 24" className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
    },
    {
      id: 'payments', label: 'Payments',
      icon: <svg viewBox="0 0 24 24" className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
    },
    {
      id: 'shipping', label: 'Shipping & Currency',
      icon: <svg viewBox="0 0 24 24" className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 5v3h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
    },
    {
      id: 'mail', label: 'Email & OTP',
      icon: <svg viewBox="0 0 24 24" className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
    },
    {
      id: 'seo', label: 'SEO',
      icon: <svg viewBox="0 0 24 24" className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    },
    {
      id: 'tracking', label: 'Tracking',
      icon: <svg viewBox="0 0 24 24" className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
    },
    {
      id: 'legal', label: 'Legal Pages',
      icon: <svg viewBox="0 0 24 24" className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
    },
    {
      id: 'courier', label: 'Courier APIs',
      icon: <svg viewBox="0 0 24 24" className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h11a2 2 0 012 2v3"/><rect x="9" y="11" width="14" height="10" rx="2"/><circle cx="12" cy="16" r="1"/></svg>
    },
    {
      id: 'fog', label: 'Fake Order Guard',
      icon: <svg viewBox="0 0 24 24" className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
    },
  ];

  return (
    <>
      <Head title="Settings" />
      <AdminLayout title="Settings">
        <div className="flex flex-col md:flex-row gap-6">
          {/* Sidebar Tabs */}
          <div className="w-full md:w-64 shrink-0">
            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
              <nav className="flex flex-col p-2 space-y-1">
                {tabs.map(t => (
                  <button key={t.id} onClick={() => setActiveTab(t.id)}
                    className={`flex items-center gap-2.5 px-4 py-2.5 text-sm font-medium rounded-xl text-left transition-colors ${activeTab === t.id ? 'bg-orange-50 text-orange-600' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-700'}`}>
                    <span className={`flex-shrink-0 ${activeTab === t.id ? 'text-orange-500' : 'text-gray-400'}`}>{t.icon}</span>
                    {t.label}
                  </button>
                ))}
              </nav>
            </div>
          </div>

          {/* Form Area */}
          <div className="flex-1 max-w-4xl">
            {activeTab === 'brand' && (
              <form onSubmit={e => submitSection(e, 'brand')} className="space-y-5">
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
                  <h3 className="font-bold text-gray-900 pb-3 border-b border-gray-50">Brand & Identity</h3>
                  <div className="grid grid-cols-2 gap-4">
                    <Field label="Site Name" error={errors.site_name}><input value={data.site_name} onChange={e => setData('site_name', e.target.value)} className={inputClass} required /></Field>
                    <Field label="Search Placeholder" error={errors.search_placeholder}><input value={data.search_placeholder} onChange={e => setData('search_placeholder', e.target.value)} className={inputClass} /></Field>
                  </div>
                  <Field label="Tagline" error={errors.tagline}><input value={data.tagline} onChange={e => setData('tagline', e.target.value)} className={inputClass} /></Field>
                  <Field label="Footer Marketplace Description (About Us Text)" error={errors.footer_text}>
                    <textarea 
                      value={data.footer_text} 
                      onChange={e => setData('footer_text', e.target.value)} 
                      rows={3} 
                      className={inputClass} 
                      placeholder="e.g. Your everyday online marketplace - millions of products, flash deals and vouchers, delivered across the country." 
                    />
                    <p className="text-xs text-gray-400 mt-1">This text appears directly under the store logo in the website footer across all pages.</p>
                  </Field>
                  <div className="rounded-xl border border-gray-100 bg-gray-50 p-4 space-y-4">
                    <label className="flex items-center gap-3 cursor-pointer">
                      <input type="checkbox" checked={data.footer_copyright_enabled} onChange={e => setData('footer_copyright_enabled', e.target.checked)} className={checkboxClass} />
                      <span className="text-sm font-semibold text-gray-800">Show copyright line in footer</span>
                    </label>
                    <Field label="Custom copyright text (optional)" error={errors.footer_copyright_text}><input value={data.footer_copyright_text} onChange={e => setData('footer_copyright_text', e.target.value)} className={inputClass} placeholder="© 2026 TAQI LIFE. All rights reserved." /></Field>
                    <Field label="Copyright link (optional)" error={errors.footer_copyright_url}><input type="url" value={data.footer_copyright_url} onChange={e => setData('footer_copyright_url', e.target.value)} className={inputClass} placeholder="https://example.com" /></Field>
                    <p className="text-xs text-gray-400">Leave this empty to automatically show the current year and site name.</p>
                    <div className="border-t border-gray-200 pt-4" />
                    <label className="flex items-center gap-3 cursor-pointer">
                      <input type="checkbox" checked={data.footer_developer_enabled} onChange={e => setData('footer_developer_enabled', e.target.checked)} className={checkboxClass} />
                      <span className="text-sm font-semibold text-gray-800">Show developer credit in footer</span>
                    </label>
                    <p className="text-xs text-gray-400">Shown next to the copyright line only when enabled and a developer name is provided.</p>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <Field label="Credit label" error={errors.footer_developer_label}><input value={data.footer_developer_label} onChange={e => setData('footer_developer_label', e.target.value)} className={inputClass} placeholder="Developed by" /></Field>
                      <Field label="Developer name" error={errors.footer_developer_name}><input value={data.footer_developer_name} onChange={e => setData('footer_developer_name', e.target.value)} className={inputClass} placeholder="Your agency or developer" /></Field>
                    </div>
                    <Field label="Developer website URL (optional)" error={errors.footer_developer_url}><input type="url" value={data.footer_developer_url} onChange={e => setData('footer_developer_url', e.target.value)} className={inputClass} placeholder="https://example.com" /></Field>
                  </div>
                  
                  <div className="grid grid-cols-2 gap-6 pt-4 border-t border-gray-50">
                    <div>
                      <Field label="Logo" error={errors.logo_file}><input type="file" accept="image/*" onChange={e => setData('logo_file', e.target.files[0])} className={inputClass} /></Field>
                      {settings.logo && !data.remove_logo && (
                        <div className="mt-2 flex items-center gap-3">
                          <img src={`/${settings.logo}`} alt="Logo" className="h-10 object-contain border border-gray-200 rounded p-1" />
                          <label className="flex items-center gap-1.5 text-xs text-red-500 cursor-pointer"><input type="checkbox" onChange={e => setData('remove_logo', e.target.checked)} className={checkboxClass} /> Remove</label>
                        </div>
                      )}
                    </div>
                    <div>
                      <Field label="Favicon" error={errors.favicon_file}><input type="file" accept="image/*" onChange={e => setData('favicon_file', e.target.files[0])} className={inputClass} /></Field>
                      {settings.favicon && !data.remove_favicon && (
                        <div className="mt-2 flex items-center gap-3">
                          <img src={`/${settings.favicon}`} alt="Favicon" className="h-10 w-10 object-contain border border-gray-200 rounded p-1" />
                          <label className="flex items-center gap-1.5 text-xs text-red-500 cursor-pointer"><input type="checkbox" onChange={e => setData('remove_favicon', e.target.checked)} className={checkboxClass} /> Remove</label>
                        </div>
                      )}
                    </div>
                  </div>
                </div>

                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
                  <h3 className="font-bold text-gray-900 pb-3 border-b border-gray-50">Contact Information</h3>
                  <div className="grid grid-cols-2 gap-4">
                    <Field label="Phone" error={errors.contact_phone}><input value={data.contact_phone} onChange={e => setData('contact_phone', e.target.value)} className={inputClass} /></Field>
                    <Field label="Email" error={errors.contact_email}><input value={data.contact_email} onChange={e => setData('contact_email', e.target.value)} className={inputClass} /></Field>
                    <Field label="Address" error={errors.contact_address}><input value={data.contact_address} onChange={e => setData('contact_address', e.target.value)} className={inputClass} /></Field>
                    <Field label="Business Hours" error={errors.contact_hours}><input value={data.contact_hours} onChange={e => setData('contact_hours', e.target.value)} className={inputClass} /></Field>
                    <Field label="Contact Page Title" error={errors.contact_title}><input value={data.contact_title} onChange={e => setData('contact_title', e.target.value)} className={inputClass} /></Field>
                    <Field label="Contact Page Intro" error={errors.contact_intro}><input value={data.contact_intro} onChange={e => setData('contact_intro', e.target.value)} className={inputClass} /></Field>
                  </div>
                </div>

                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
                  <h3 className="font-bold text-gray-900 pb-3 border-b border-gray-50">Social Links</h3>
                  <div className="grid grid-cols-2 gap-4">
                    <Field label="Facebook URL" error={errors.facebook_url}><input value={data.facebook_url} onChange={e => setData('facebook_url', e.target.value)} className={inputClass} placeholder="https://facebook.com/yourpage" /></Field>
                    <Field label="Instagram URL" error={errors.instagram_url}><input value={data.instagram_url} onChange={e => setData('instagram_url', e.target.value)} className={inputClass} placeholder="https://instagram.com/yourpage" /></Field>
                    <Field label="Twitter / X URL" error={errors.twitter_url}><input value={data.twitter_url} onChange={e => setData('twitter_url', e.target.value)} className={inputClass} placeholder="https://x.com/yourpage" /></Field>
                  </div>
                </div>

                {/* Quick Links */}
                <div className="bg-blue-50 border border-blue-100 rounded-2xl p-4">
                  <p className="text-xs font-semibold text-blue-700 mb-3 uppercase tracking-wide flex items-center gap-1.5">
                    <svg className="w-3.5 h-3.5 text-blue-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                    <span>Quick Links</span>
                  </p>
                  <div className="flex flex-wrap gap-2">
                    {[
                      { label: 'Visit Homepage', href: '/', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
                      { label: 'Contact Page', href: '/contact', icon: 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z' },
                      { label: 'Shop', href: '/shop', icon: 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z' },
                      { label: 'Terms', href: '/terms', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
                      { label: 'Privacy', href: '/privacy', icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z' },
                    ].map(l => (
                      <a key={l.href} href={l.href} target="_blank" rel="noreferrer"
                        className="px-3 py-1.5 bg-white border border-blue-200 text-blue-600 text-xs font-semibold rounded-lg hover:bg-blue-100 transition-colors inline-flex items-center gap-1.5">
                        <svg className="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d={l.icon}/></svg>
                        <span>{l.label}</span>
                        <span className="text-[10px] opacity-60">↗</span>
                      </a>
                    ))}
                  </div>
                </div>

                <button type="submit" disabled={processing} className="px-6 py-3 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white font-semibold rounded-xl flex items-center gap-2">
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7"/></svg>
                  Save Brand Settings
                </button>
              </form>
            )}

            {activeTab === 'chat' && (
              <form onSubmit={e => submitSection(e, 'chat')} className="space-y-5">
                {/* Header preview */}
                <div className="bg-gradient-to-r from-green-500 to-emerald-600 rounded-2xl p-5 text-white">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      {/* Live Chat SVG Icon */}
                      <div className="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                        <svg viewBox="0 0 24 24" className="w-6 h-6" fill="white" xmlns="http://www.w3.org/2000/svg">
                          <path d="M20 2H4C2.9 2 2 2.9 2 4v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM9 11H7V9h2v2zm4 0h-2V9h2v2zm4 0h-2V9h2v2z"/>
                        </svg>
                      </div>
                      <div>
                        <h3 className="font-bold text-lg">Live Chat Widget</h3>
                        <p className="text-green-100 text-sm">Floating chat button on all storefront pages</p>
                      </div>
                    </div>
                    <div className="flex items-center gap-3 bg-white/20 rounded-xl px-4 py-2">
                      <span className="text-sm font-semibold">{data.chat_enabled ? 'Enabled' : 'Disabled'}</span>
                      <label className="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" checked={data.chat_enabled} onChange={e => setData('chat_enabled', e.target.checked)} className="sr-only peer" />
                        <div className="w-11 h-6 bg-white/30 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-white/60"></div>
                      </label>
                    </div>
                  </div>
                  {/* Live preview */}
                  <div className="mt-4 flex items-center gap-3">
                    <span className="text-green-100 text-xs font-semibold uppercase tracking-wide">Widget Preview:</span>
                    <div className="flex gap-2">
                      {data.whatsapp_number && (
                        <div className="w-9 h-9 rounded-full flex items-center justify-center shadow-lg" style={{background:'linear-gradient(135deg,#25D366,#128C7E)'}}>
                          <svg viewBox="0 0 32 32" className="w-5 h-5" fill="white"><path d="M16 2C8.28 2 2 8.28 2 16c0 2.44.65 4.73 1.78 6.72L2 30l7.52-1.74A13.93 13.93 0 0016 30c7.72 0 14-6.28 14-14S23.72 2 16 2zm0 25.5a11.43 11.43 0 01-5.86-1.62l-.42-.25-4.46 1.03 1.06-4.35-.28-.45A11.47 11.47 0 014.5 16C4.5 9.6 9.6 4.5 16 4.5S27.5 9.6 27.5 16 22.4 27.5 16 27.5zm6.29-8.56c-.34-.17-2.03-1-2.35-1.11-.32-.12-.55-.17-.78.17-.23.34-.9 1.11-1.1 1.34-.2.23-.4.26-.74.09-.34-.17-1.44-.53-2.74-1.69-1.01-.9-1.7-2.02-1.9-2.36-.2-.34-.02-.52.15-.69.15-.15.34-.4.51-.6.17-.2.23-.34.34-.57.12-.23.06-.43-.03-.6-.09-.17-.78-1.88-1.07-2.57-.28-.68-.57-.58-.78-.59h-.66c-.23 0-.6.09-.91.43-.31.34-1.2 1.17-1.2 2.86s1.23 3.32 1.4 3.55c.17.23 2.42 3.7 5.87 5.19.82.35 1.46.56 1.96.72.82.26 1.57.22 2.16.13.66-.1 2.03-.83 2.32-1.63.29-.8.29-1.49.2-1.63-.09-.14-.32-.23-.66-.4z"/></svg>
                        </div>
                      )}
                      {data.call_number && (
                        <div className="w-9 h-9 rounded-full flex items-center justify-center shadow-lg" style={{background:'linear-gradient(135deg,#34d399,#059669)'}}>
                          <svg viewBox="0 0 24 24" className="w-5 h-5" fill="white"><path d="M6.62 10.79a15.05 15.05 0 006.59 6.59l2.2-2.2a1 1 0 011.01-.24c1.12.37 2.33.57 3.58.57a1 1 0 011 1V20a1 1 0 01-1 1C9.61 21 3 14.39 3 6.5a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.58a1 1 0 01-.25 1.01l-2.2 2.2z"/></svg>
                        </div>
                      )}
                      {data.messenger_page && (
                        <div className="w-9 h-9 rounded-full flex items-center justify-center shadow-lg" style={{background:'linear-gradient(135deg,#0084ff,#a033ff)'}}>
                          <svg viewBox="0 0 32 32" className="w-5 h-5" fill="white"><path d="M16 2C8.27 2 2 7.8 2 14.93c0 3.9 1.85 7.38 4.76 9.76V30l4.59-2.52A14.86 14.86 0 0016 27.86c7.73 0 14-5.8 14-12.93C30 7.8 23.73 2 16 2zm1.41 17.41l-3.57-3.8-6.97 3.8 7.66-8.13 3.66 3.8 6.88-3.8-7.66 8.13z"/></svg>
                        </div>
                      )}
                    </div>
                  </div>
                </div>

                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
                  {/* WhatsApp */}
                  <div className="flex items-start gap-4 p-4 rounded-xl border-2 border-green-100 bg-green-50">
                    <div className="w-12 h-12 rounded-2xl flex items-center justify-center shadow-md flex-shrink-0" style={{background:'linear-gradient(135deg,#25D366,#128C7E)'}}>
                      <svg viewBox="0 0 32 32" className="w-7 h-7" fill="white"><path d="M16 2C8.28 2 2 8.28 2 16c0 2.44.65 4.73 1.78 6.72L2 30l7.52-1.74A13.93 13.93 0 0016 30c7.72 0 14-6.28 14-14S23.72 2 16 2zm0 25.5a11.43 11.43 0 01-5.86-1.62l-.42-.25-4.46 1.03 1.06-4.35-.28-.45A11.47 11.47 0 014.5 16C4.5 9.6 9.6 4.5 16 4.5S27.5 9.6 27.5 16 22.4 27.5 16 27.5zm6.29-8.56c-.34-.17-2.03-1-2.35-1.11-.32-.12-.55-.17-.78.17-.23.34-.9 1.11-1.1 1.34-.2.23-.4.26-.74.09-.34-.17-1.44-.53-2.74-1.69-1.01-.9-1.7-2.02-1.9-2.36-.2-.34-.02-.52.15-.69.15-.15.34-.4.51-.6.17-.2.23-.34.34-.57.12-.23.06-.43-.03-.6-.09-.17-.78-1.88-1.07-2.57-.28-.68-.57-.58-.78-.59h-.66c-.23 0-.6.09-.91.43-.31.34-1.2 1.17-1.2 2.86s1.23 3.32 1.4 3.55c.17.23 2.42 3.7 5.87 5.19.82.35 1.46.56 1.96.72.82.26 1.57.22 2.16.13.66-.1 2.03-.83 2.32-1.63.29-.8.29-1.49.2-1.63-.09-.14-.32-.23-.66-.4z"/></svg>
                    </div>
                    <div className="flex-1">
                      <label className="block text-sm font-bold text-gray-800 mb-1">WhatsApp</label>
                      <p className="text-xs text-gray-500 mb-2">Customer taps this icon → opens WhatsApp chat with your number</p>
                      <Field label="WhatsApp Number (with country code)" error={errors.whatsapp_number}>
                        <input value={data.whatsapp_number} onChange={e => setData('whatsapp_number', e.target.value)} className={inputClass} placeholder="e.g. 8801712345678" />
                      </Field>
                      <p className="text-xs text-gray-500 mt-2">Use your WhatsApp number, including country code, for example 8801712345678. The product WhatsApp button will stay hidden until this is configured.</p>
                    </div>
                  </div>

                  {/* Call */}
                  <div className="flex items-start gap-4 p-4 rounded-xl border-2 border-emerald-100 bg-emerald-50">
                    <div className="w-12 h-12 rounded-2xl flex items-center justify-center shadow-md flex-shrink-0" style={{background:'linear-gradient(135deg,#34d399,#059669)'}}>
                      <svg viewBox="0 0 24 24" className="w-7 h-7" fill="white"><path d="M6.62 10.79a15.05 15.05 0 006.59 6.59l2.2-2.2a1 1 0 011.01-.24c1.12.37 2.33.57 3.58.57a1 1 0 011 1V20a1 1 0 01-1 1C9.61 21 3 14.39 3 6.5a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.58a1 1 0 01-.25 1.01l-2.2 2.2z"/></svg>
                    </div>
                    <div className="flex-1">
                      <label className="block text-sm font-bold text-gray-800 mb-1">Phone Call</label>
                      <p className="text-xs text-gray-500 mb-2">Customer taps this icon → initiates a phone call directly</p>
                      <Field label="Call Number" error={errors.call_number}>
                        <input value={data.call_number} onChange={e => setData('call_number', e.target.value)} className={inputClass} placeholder="e.g. +8801712345678" />
                      </Field>
                    </div>
                  </div>

                  {/* Messenger/Facebook */}
                  <div className="flex items-start gap-4 p-4 rounded-xl border-2 border-blue-100 bg-blue-50">
                    <div className="w-12 h-12 rounded-2xl flex items-center justify-center shadow-md flex-shrink-0" style={{background:'linear-gradient(135deg,#0084ff,#a033ff)'}}>
                      <svg viewBox="0 0 32 32" className="w-7 h-7" fill="white"><path d="M16 2C8.27 2 2 7.8 2 14.93c0 3.9 1.85 7.38 4.76 9.76V30l4.59-2.52A14.86 14.86 0 0016 27.86c7.73 0 14-5.8 14-12.93C30 7.8 23.73 2 16 2zm1.41 17.41l-3.57-3.8-6.97 3.8 7.66-8.13 3.66 3.8 6.88-3.8-7.66 8.13z"/></svg>
                    </div>
                    <div className="flex-1">
                      <label className="block text-sm font-bold text-gray-800 mb-1">Facebook Messenger</label>
                      <p className="text-xs text-gray-500 mb-2">Customer taps this icon → opens Facebook Messenger chat</p>
                      <Field label="Messenger Page URL or Username" error={errors.messenger_page}>
                        <input value={data.messenger_page} onChange={e => setData('messenger_page', e.target.value)} className={inputClass} placeholder="e.g. https://m.me/yourpage or yourpagename" />
                      </Field>
                    </div>
                  </div>

                  {/* Info box */}
                  <div className="bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs text-amber-700">
                    <strong>Tip:</strong> Leave any field blank to hide that icon. At least one must be filled for the widget to appear on the storefront.
                  </div>
                </div>

                <button type="submit" disabled={processing} className="px-6 py-3 bg-green-500 hover:bg-green-600 disabled:opacity-60 text-white font-semibold rounded-xl">Save Chat Settings</button>
              </form>
            )}

            {activeTab === 'homepage' && (
              <form onSubmit={e => submitSection(e, 'homepage')} className="space-y-5">

                {/* Quick Links */}
                <div className="bg-orange-50 border border-orange-100 rounded-2xl p-4">
                  <p className="text-xs font-semibold text-orange-700 mb-3 uppercase tracking-wide flex items-center gap-1.5">
                    <svg className="w-3.5 h-3.5 text-orange-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                    <span>Quick Preview Links</span>
                  </p>
                  <div className="flex flex-wrap gap-2">
                    {[
                      { label: 'Homepage', href: '/', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
                      { label: 'Shop', href: '/shop', icon: 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z' },
                      { label: 'Categories', href: '/shop', icon: 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z' },
                      { label: 'Banners (Admin)', href: '/admin/banners', icon: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z' },
                      { label: 'Features (Admin)', href: '/admin/features', icon: 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z' },
                      { label: 'Flash Sale', href: '/admin/flash-sale', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
                    ].map(l => (
                      <a key={l.href} href={l.href} target="_blank" rel="noreferrer"
                        className="px-3 py-1.5 bg-white border border-orange-200 text-orange-600 text-xs font-semibold rounded-lg hover:bg-orange-100 transition-colors inline-flex items-center gap-1.5">
                        <svg className="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d={l.icon}/></svg>
                        <span>{l.label}</span>
                        <span className="text-[10px] opacity-70">↗</span>
                      </a>
                    ))}
                  </div>
                </div>

                {/* Announcement Bar */}
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
                  <div className="flex items-center justify-between pb-3 border-b border-gray-50">
                    <h3 className="font-bold text-gray-900"><svg className="w-4 h-4 text-orange-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg> Announcement Bar</h3>
                    <a href="/" target="_blank" rel="noreferrer" className="text-xs text-orange-500 hover:underline">Preview ↗</a>
                  </div>
                  <p className="text-xs text-gray-400">The promo bar shown at the very top of every page.</p>
                  <div className="grid grid-cols-2 gap-4">
                    <Field label="Promo Text" error={errors.header_promo_text}>
                      <input value={data.header_promo_text} onChange={e => setData('header_promo_text', e.target.value)} className={inputClass} placeholder="e.g. Free shipping on orders over ৳500!" />
                    </Field>
                    <Field label="Promo Link (URL)" error={errors.header_promo_link}>
                      <input value={data.header_promo_link} onChange={e => setData('header_promo_link', e.target.value)} className={inputClass} placeholder="/shop or https://..." />
                    </Field>
                  </div>
                </div>

                {/* Hero / Banners */}
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
                  <div className="flex items-center justify-between pb-3 border-b border-gray-50">
                    <h3 className="font-bold text-gray-900"><svg className="w-4 h-4 text-orange-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg> Hero Banners (Fallback)</h3>
                    <div className="flex items-center gap-3">
                      <a href="/admin/banners" target="_blank" rel="noreferrer" className="text-xs text-orange-500 hover:underline">Manage Banners ↗</a>
                      <a href="/" target="_blank" rel="noreferrer" className="text-xs text-gray-400 hover:underline">Preview ↗</a>
                    </div>
                  </div>
                  <p className="text-xs text-gray-400">Shown when no banner images are uploaded. Upload real banners via <a href="/admin/banners" target="_blank" className="text-orange-500 hover:underline">Admin → Banners</a>.</p>
                  <div className="grid grid-cols-2 gap-4">
                    <Field label="Fallback Badge" error={errors.hero_fallback_badge}>
                      <input value={data.hero_fallback_badge} onChange={e => setData('hero_fallback_badge', e.target.value)} className={inputClass} placeholder="e.g. New Arrival" />
                    </Field>
                    <Field label="Fallback Title" error={errors.hero_fallback_title}>
                      <input value={data.hero_fallback_title} onChange={e => setData('hero_fallback_title', e.target.value)} className={inputClass} placeholder="e.g. Shop the Best Deals" />
                    </Field>
                    <Field label="Fallback Subtitle" error={errors.hero_fallback_subtitle}>
                      <input value={data.hero_fallback_subtitle} onChange={e => setData('hero_fallback_subtitle', e.target.value)} className={inputClass} placeholder="e.g. Discover amazing products" />
                    </Field>
                  </div>
                </div>

                {/* Brands Marquee */}
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
                  <div className="flex items-center justify-between pb-3 border-b border-gray-50">
                    <h3 className="font-bold text-gray-900"><svg className="w-4 h-4 text-orange-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg> Brands Marquee</h3>
                    <a href="/" target="_blank" rel="noreferrer" className="text-xs text-orange-500 hover:underline">Preview ↗</a>
                  </div>
                  <label className="flex items-center gap-3">
                    <input type="checkbox" checked={data.show_brands_marquee} onChange={e => setData('show_brands_marquee', e.target.checked)} className={checkboxClass} />
                    <span className="text-sm font-medium">Show scrolling brands marquee on homepage</span>
                  </label>
                  <Field label="Brand Names (comma-separated)" error={errors.brands_marquee}>
                    <textarea value={data.brands_marquee} onChange={e => setData('brands_marquee', e.target.value)} className={inputClass} rows={2} placeholder="Nike, Adidas, Samsung, Apple, Sony" />
                  </Field>
                </div>

                {/* Section Labels */}
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
                  <div className="flex items-center justify-between pb-3 border-b border-gray-50">
                    <h3 className="font-bold text-gray-900"><svg className="w-4 h-4 text-orange-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg> Section Labels & Titles</h3>
                    <a href="/" target="_blank" rel="noreferrer" className="text-xs text-orange-500 hover:underline">Preview Homepage ↗</a>
                  </div>
                  <p className="text-xs text-gray-400">Customize the heading text for each homepage section.</p>
                  <div className="grid grid-cols-2 gap-4">
                    <Field label="View More Button Label" error={errors.home_view_more_label}>
                      <input value={data.home_view_more_label} onChange={e => setData('home_view_more_label', e.target.value)} className={inputClass} placeholder="View all" />
                    </Field>
                    <Field label="Default CTA Button Text" error={errors.default_cta_text}>
                      <input value={data.default_cta_text} onChange={e => setData('default_cta_text', e.target.value)} className={inputClass} placeholder="Shop now" />
                    </Field>
                    <Field label="Categories Section Title" error={errors.home_categories_title}>
                      <input value={data.home_categories_title} onChange={e => setData('home_categories_title', e.target.value)} className={inputClass} placeholder="Featured Categories" />
                    </Field>
                    <Field label="Hot Deals Title" error={errors.home_hot_deal_title}>
                      <input value={data.home_hot_deal_title} onChange={e => setData('home_hot_deal_title', e.target.value)} className={inputClass} placeholder="Hot Deals" />
                    </Field>
                    <Field label="Featured Products Title" error={errors.home_featured_title}>
                      <input value={data.home_featured_title} onChange={e => setData('home_featured_title', e.target.value)} className={inputClass} placeholder="Featured" />
                    </Field>
                    <Field label="Deal of the Week Title" error={errors.home_deal_week_title}>
                      <input value={data.home_deal_week_title} onChange={e => setData('home_deal_week_title', e.target.value)} className={inputClass} placeholder="Deal of the Week" />
                    </Field>
                    <Field label="Shop Subtitle (Shop page)" error={errors.shop_subtitle}>
                      <input value={data.shop_subtitle} onChange={e => setData('shop_subtitle', e.target.value)} className={inputClass} placeholder="Browse all products" />
                    </Field>
                    <Field label="Delivery ETA Text" error={errors.delivery_eta_text}>
                      <input value={data.delivery_eta_text} onChange={e => setData('delivery_eta_text', e.target.value)} className={inputClass} placeholder="Delivered within 2-3 days" />
                    </Field>
                  </div>
                </div>

                {/* Flash Sale Countdown */}
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
                  <div className="flex items-center justify-between pb-3 border-b border-gray-50">
                    <h3 className="font-bold text-gray-900"><svg className="w-4 h-4 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg> Flash Sale Countdown</h3>
                    <div className="flex items-center gap-3">
                      <a href="/admin/flash-sale" target="_blank" rel="noreferrer" className="text-xs text-orange-500 hover:underline">Manage Flash Sale ↗</a>
                      <a href="/" target="_blank" rel="noreferrer" className="text-xs text-gray-400 hover:underline">Preview ↗</a>
                    </div>
                  </div>
                  <Field label="Deal Ends At (countdown timer)" error={errors.deal_ends_at}>
                    <input type="datetime-local" value={data.deal_ends_at} onChange={e => setData('deal_ends_at', e.target.value)} className={inputClass} />
                  </Field>
                  <p className="text-xs text-gray-400">⚠ This sets a global countdown shown on flash sale sections. Leave blank to hide the timer.</p>
                </div>

                {/* Footer Marketplace Description */}
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
                  <div className="flex items-center justify-between pb-3 border-b border-gray-50">
                    <h3 className="font-bold text-gray-900"><svg className="w-4 h-4 text-orange-500 shrink-0 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>Footer Marketplace Description</h3>
                    <a href="/" target="_blank" rel="noreferrer" className="text-xs text-orange-500 hover:underline">Preview Footer ↗</a>
                  </div>
                  <p className="text-xs text-gray-400">The description text displayed under the brand logo in the website footer.</p>
                  <Field label="Footer Text" error={errors.footer_text}>
                    <textarea 
                      value={data.footer_text} 
                      onChange={e => setData('footer_text', e.target.value)} 
                      rows={3} 
                      className={inputClass} 
                      placeholder="Your everyday online marketplace - millions of products, flash deals and vouchers, delivered across the country." 
                    />
                  </Field>
                </div>

                <button type="submit" disabled={processing} className="px-6 py-3 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white font-semibold rounded-xl flex items-center gap-2">
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7"/></svg>
                  Save Homepage Settings
                </button>
              </form>
            )}

            {activeTab === 'theme' && (
              <form onSubmit={e => submitSection(e, 'theme')} className="space-y-5">
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
                  <div className="flex items-center justify-between gap-4 pb-3 border-b border-gray-50">
                    <div>
                      <h3 className="font-bold text-gray-900">Storefront Template</h3>
                      <p className="text-sm text-gray-500 mt-1">Choose the active customer-facing shop design.</p>
                    </div>
                    <span className="text-xs font-semibold rounded-full bg-orange-50 text-orange-600 px-3 py-1">
                      Current: {data.storefront_template === 'template-1' ? 'Template 1' : 'Template 2'}
                    </span>
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {[
                      {
                        id: 'template-1',
                        name: 'Template 1',
                        note: 'Coza-style clean fashion/catalog layout',
                        palette: ['#717fe0', '#222222', '#ffffff'],
                      },
                      {
                        id: 'template-2',
                        name: 'Template 2',
                        note: 'Current active Shopzy marketplace layout',
                        palette: ['#f15a24', '#0A2A22', '#f8f9fa'],
                      },
                    ].map(template => {
                      const selected = data.storefront_template === template.id;
                      return (
                        <label
                          key={template.id}
                          className={`cursor-pointer rounded-2xl border p-4 transition-all ${selected ? 'border-orange-400 bg-orange-50 ring-2 ring-orange-100' : 'border-gray-100 bg-white hover:border-gray-200'}`}
                        >
                          <div className="flex items-start justify-between gap-3">
                            <div>
                              <p className="font-bold text-gray-900">{template.name}</p>
                              <p className="text-sm text-gray-500 mt-1">{template.note}</p>
                            </div>
                            <input
                              type="radio"
                              name="storefront_template"
                              value={template.id}
                              checked={selected}
                              onChange={e => setData('storefront_template', e.target.value)}
                              className="mt-1 h-4 w-4 accent-orange-500"
                            />
                          </div>
                          <div className="mt-4 rounded-xl border border-gray-100 bg-white p-3">
                            <div className="h-20 rounded-lg border border-gray-100 overflow-hidden">
                              <div className="h-4" style={{ backgroundColor: template.palette[1] }} />
                              <div className="grid grid-cols-3 gap-2 p-3" style={{ backgroundColor: template.palette[2] }}>
                                <div className="h-10 rounded" style={{ backgroundColor: template.palette[0] }} />
                                <div className="h-10 rounded bg-gray-100" />
                                <div className="h-10 rounded bg-gray-100" />
                              </div>
                            </div>
                          </div>
                        </label>
                      );
                    })}
                  </div>

                  {data.storefront_template === 'template-1' && (
                    <div className="rounded-2xl border border-gray-100 bg-gray-50 p-4 space-y-4">
                      <div>
                        <h4 className="text-sm font-bold text-gray-900">Template 1 Dependencies</h4>
                        <div className="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3">
                          {[
                            ['Assets', templateStatus.template_1_assets],
                            ['CSS bridge', templateStatus.template_1_css],
                            ['Fonts', templateStatus.template_1_fonts],
                          ].map(([label, ok]) => (
                            <div key={label} className={`rounded-xl border px-3 py-2 text-sm font-semibold ${ok ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700'}`}>
                              {ok ? 'Ready' : 'Missing'}: {label}
                            </div>
                          ))}
                        </div>
                      </div>

                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <Field label="Template 1 Navbar Menu" error={errors.template_1_navbar_menu}>
                          <select value={data.template_1_navbar_menu} onChange={e => setData('template_1_navbar_menu', e.target.value)} className={inputClass}>
                            <option value="coza">Coza Menu (Home, Shop, Features, Track, Contact)</option>
                            <option value="categories">Category Menu (from store categories)</option>
                          </select>
                        </Field>
                        <div className="flex items-end pb-2">
                          <label className="flex items-center gap-3">
                            <input type="checkbox" checked={data.template_1_show_search} onChange={e => setData('template_1_show_search', e.target.checked)} className={checkboxClass} />
                            <span className="text-sm font-medium text-gray-700">Show search in navbar</span>
                          </label>
                        </div>
                      </div>

                      <div className="rounded-xl border border-gray-200 bg-white p-4 space-y-4">
                        <div>
                          <h4 className="text-sm font-bold text-gray-900">Template-1 Layout Counts</h4>
                          <p className="text-xs text-gray-500 mt-1">Control category banner rows and catalog pagination for Template-1.</p>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                          <Field label="Category Banners Per Row" error={errors.template_1_category_per_row}>
                            <input type="number" min="2" max="5" value={data.template_1_category_per_row} onChange={e => setData('template_1_category_per_row', e.target.value)} className={inputClass} />
                          </Field>
                          <Field label="Products Per Row" error={errors.template_1_product_per_row}>
                            <input type="number" min="2" max="6" value={data.template_1_product_per_row} onChange={e => setData('template_1_product_per_row', e.target.value)} className={inputClass} />
                          </Field>
                          <Field label="Products Per Page" error={errors.template_1_products_per_page}>
                            <input type="number" min="1" max="48" value={data.template_1_products_per_page} onChange={e => setData('template_1_products_per_page', e.target.value)} className={inputClass} />
                          </Field>
                        </div>
                      </div>

                      <div className="rounded-xl border border-gray-200 bg-white p-4 space-y-4">
                        <div>
                          <h4 className="text-sm font-bold text-gray-900">Hero Slider Style</h4>
                          <p className="text-xs text-gray-500 mt-1">Hero images and text are managed from Admin &gt; Banners. Use these controls for the Template-1 presentation only.</p>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                          <Field label="Text position">
                            <select value={data.template_1_hero_text_position} onChange={e => setData('template_1_hero_text_position', e.target.value)} className={inputClass}>
                              <option value="left">Left</option>
                              <option value="center">Center</option>
                              <option value="right">Right</option>
                            </select>
                          </Field>
                          <Field label="Overlay color">
                            <input type="color" value={data.template_1_hero_overlay_color} onChange={e => setData('template_1_hero_overlay_color', e.target.value)} className="h-[42px] w-full cursor-pointer rounded-xl border border-gray-200 bg-white p-1" />
                          </Field>
                          <Field label={`Overlay opacity (${data.template_1_hero_overlay_opacity}%)`}>
                            <input type="range" min="0" max="80" value={data.template_1_hero_overlay_opacity} onChange={e => setData('template_1_hero_overlay_opacity', e.target.value)} className="mt-3 w-full accent-orange-500" />
                          </Field>
                          <Field label="Text panel background">
                            <input type="color" value={data.template_1_hero_text_background_color} onChange={e => setData('template_1_hero_text_background_color', e.target.value)} className="h-[42px] w-full cursor-pointer rounded-xl border border-gray-200 bg-white p-1" />
                          </Field>
                          <Field label={`Text background opacity (${data.template_1_hero_text_background_opacity}%)`}>
                            <input type="range" min="0" max="100" value={data.template_1_hero_text_background_opacity} onChange={e => setData('template_1_hero_text_background_opacity', e.target.value)} className="mt-3 w-full accent-orange-500" />
                          </Field>
                        </div>
                      </div>

                      <div className="rounded-xl border border-gray-200 bg-white p-4 space-y-4">
                        <div>
                          <h4 className="text-sm font-bold text-gray-900">Category Banner Text Style</h4>
                          <p className="text-xs text-gray-500 mt-1">Control the readability and appearance of text over the category images.</p>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                          <Field label="Title color">
                            <input type="color" value={data.template_1_category_title_color} onChange={e => setData('template_1_category_title_color', e.target.value)} className="h-[42px] w-full cursor-pointer rounded-xl border border-gray-200 bg-white p-1" />
                          </Field>
                          <Field label="Subtitle / CTA color">
                            <input type="color" value={data.template_1_category_secondary_color} onChange={e => setData('template_1_category_secondary_color', e.target.value)} className="h-[42px] w-full cursor-pointer rounded-xl border border-gray-200 bg-white p-1" />
                          </Field>
                          <Field label="Overlay color">
                            <input type="color" value={data.template_1_category_overlay_color} onChange={e => setData('template_1_category_overlay_color', e.target.value)} className="h-[42px] w-full cursor-pointer rounded-xl border border-gray-200 bg-white p-1" />
                          </Field>
                          <Field label={`Overlay opacity (${data.template_1_category_overlay_opacity}%)`}>
                            <input type="range" min="0" max="100" value={data.template_1_category_overlay_opacity} onChange={e => setData('template_1_category_overlay_opacity', e.target.value)} className="mt-3 w-full accent-orange-500" />
                          </Field>
                          <Field label={`Hover opacity (${data.template_1_category_hover_overlay_opacity}%)`}>
                            <input type="range" min="0" max="100" value={data.template_1_category_hover_overlay_opacity} onChange={e => setData('template_1_category_hover_overlay_opacity', e.target.value)} className="mt-3 w-full accent-orange-500" />
                          </Field>
                          <Field label="Title size">
                            <select value={data.template_1_category_title_size} onChange={e => setData('template_1_category_title_size', e.target.value)} className={inputClass}>
                              {['16px', '18px', '20px', '22px', '24px', '28px', '30px', '32px', '36px', '40px', '48px'].map(size => <option key={size} value={size}>{size}</option>)}
                            </select>
                          </Field>
                          <Field label="Title weight">
                            <select value={data.template_1_category_title_weight} onChange={e => setData('template_1_category_title_weight', e.target.value)} className={inputClass}>
                              {['400', '500', '600', '700', '800', '900'].map(weight => <option key={weight} value={weight}>{weight}</option>)}
                            </select>
                          </Field>
                          <Field label="Text alignment">
                            <select value={data.template_1_category_text_align} onChange={e => setData('template_1_category_text_align', e.target.value)} className={inputClass}>
                              <option value="left">Left</option>
                              <option value="center">Center</option>
                              <option value="right">Right</option>
                            </select>
                          </Field>
                          <Field label="Title style">
                            <select value={data.template_1_category_title_style} onChange={e => setData('template_1_category_title_style', e.target.value)} className={inputClass}>
                              <option value="normal">Normal</option>
                              <option value="italic">Italic</option>
                            </select>
                          </Field>
                          <Field label="Title text case">
                            <select value={data.template_1_category_title_transform} onChange={e => setData('template_1_category_title_transform', e.target.value)} className={inputClass}>
                              <option value="none">Normal case</option>
                              <option value="capitalize">Capitalize</option>
                              <option value="uppercase">Uppercase</option>
                              <option value="lowercase">Lowercase</option>
                            </select>
                          </Field>
                          <div className="flex items-end pb-2">
                            <label className="flex items-center gap-3">
                              <input type="checkbox" checked={data.template_1_category_text_shadow} onChange={e => setData('template_1_category_text_shadow', e.target.checked)} className={checkboxClass} />
                              <span className="text-sm font-medium text-gray-700">Text shadow for contrast</span>
                            </label>
                          </div>
                        </div>
                      </div>

                      <div className="rounded-xl border border-gray-200 bg-white p-4 space-y-4">
                        <div>
                          <h4 className="text-sm font-bold text-gray-900">Product Overview Counts</h4>
                          <p className="text-xs text-gray-500 mt-1">Choose how many products appear in each Template-1 overview tab.</p>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                          <Field label="All Products">
                            <input type="number" min="1" max="48" value={data.template_1_overview_all_count} onChange={e => setData('template_1_overview_all_count', e.target.value)} className={inputClass} />
                          </Field>
                          <Field label="Featured">
                            <input type="number" min="1" max="48" value={data.template_1_overview_featured_count} onChange={e => setData('template_1_overview_featured_count', e.target.value)} className={inputClass} />
                          </Field>
                          <Field label="New Arrivals">
                            <input type="number" min="1" max="48" value={data.template_1_overview_new_count} onChange={e => setData('template_1_overview_new_count', e.target.value)} className={inputClass} />
                          </Field>
                          <Field label="Best Sellers">
                            <input type="number" min="1" max="48" value={data.template_1_overview_best_count} onChange={e => setData('template_1_overview_best_count', e.target.value)} className={inputClass} />
                          </Field>
                        </div>
                      </div>
                    </div>
                  )}

                  {data.storefront_template === 'template-2' && (
                    <div className="rounded-2xl border border-gray-100 bg-gray-50 p-4 space-y-4">
                      <div className="rounded-xl border border-gray-200 bg-white p-4 space-y-4">
                        <div>
                          <h4 className="text-sm font-bold text-gray-900">Template-2 Product Section Counts</h4>
                          <p className="text-xs text-gray-500 mt-1">Choose how many products appear in the Featured, New Arrivals, and Best Sellers sections.</p>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                          <Field label="Featured / Just For You">
                            <input type="number" min="1" max="48" value={data.template_2_overview_featured_count} onChange={e => setData('template_2_overview_featured_count', e.target.value)} className={inputClass} />
                          </Field>
                          <Field label="New Arrivals">
                            <input type="number" min="1" max="48" value={data.template_2_overview_new_count} onChange={e => setData('template_2_overview_new_count', e.target.value)} className={inputClass} />
                          </Field>
                          <Field label="Best Sellers">
                            <input type="number" min="1" max="48" value={data.template_2_overview_best_count} onChange={e => setData('template_2_overview_best_count', e.target.value)} className={inputClass} />
                          </Field>
                        </div>
                      </div>
                      <div className="rounded-xl border border-gray-200 bg-white p-4 space-y-4">
                        <div>
                          <h4 className="text-sm font-bold text-gray-900">Template-2 Layout Counts</h4>
                          <p className="text-xs text-gray-500 mt-1">Control catalog grid columns and pagination for Template-2.</p>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                          <Field label="Products Per Row" error={errors.template_2_product_per_row}>
                            <input type="number" min="2" max="6" value={data.template_2_product_per_row} onChange={e => setData('template_2_product_per_row', e.target.value)} className={inputClass} />
                          </Field>
                          <Field label="Products Per Page" error={errors.template_2_products_per_page}>
                            <input type="number" min="1" max="48" value={data.template_2_products_per_page} onChange={e => setData('template_2_products_per_page', e.target.value)} className={inputClass} />
                          </Field>
                        </div>
                      </div>
                    </div>
                  )}

                  <div className="rounded-2xl border border-gray-100 bg-gray-50 p-4 space-y-4">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <h3 className="font-bold text-gray-900">Typography controls</h3>
                        <p className="text-sm text-gray-500 mt-1">Choose text appearance independently for each storefront template and section.</p>
                      </div>
                      <span className="rounded-full bg-white border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-500">Saved with Template settings</span>
                    </div>

                    {(() => {
                      const template = data.storefront_template;
                      const key = `theme_typography_${template.replace('-', '_')}`;
                      return (
                        <div className="rounded-xl border border-gray-200 bg-gray-100/70 p-3 sm:p-4">
                          <div className="flex items-center justify-between gap-3 mb-3">
                            <h4 className="text-sm font-bold text-gray-900">{template === 'template-1' ? 'Template 1' : 'Template 2'} typography</h4>
                            <span className="text-xs text-green-600 font-semibold">Active template</span>
                          </div>
                          <TypographyEditor
                            template={template}
                            value={data[key]}
                            onChange={next => setData(key, next)}
                            onReset={() => setData(key, readTypography({}, template))}
                          />
                          {errors[key] && <p className="text-red-500 text-xs mt-2">{errors[key]}</p>}
                        </div>
                      );
                    })()}
                  </div>
                </div>
                <button type="submit" disabled={processing} className="px-6 py-3 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white font-semibold rounded-xl">
                  Save Template & Typography
                </button>
              </form>
            )}

            {activeTab === 'payments' && (
              <form onSubmit={e => submitSection(e, 'payments')} className="space-y-5">
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
                  <h3 className="font-bold text-gray-900 pb-3 border-b border-gray-50">Payment Gateways</h3>
                  <div className="grid grid-cols-2 gap-6">
                    <div>
                      <label className="flex items-center gap-3 mb-2"><input type="checkbox" checked={data.pay_bkash_enabled} onChange={e => setData('pay_bkash_enabled', e.target.checked)} className={checkboxClass} /> <span className="font-medium text-pink-600">bKash</span></label>
                      <Field error={errors.bkash_number}><input value={data.bkash_number} onChange={e => setData('bkash_number', e.target.value)} className={inputClass} placeholder="bKash Number" /></Field>
                    </div>
                    <div>
                      <label className="flex items-center gap-3 mb-2"><input type="checkbox" checked={data.pay_nagad_enabled} onChange={e => setData('pay_nagad_enabled', e.target.checked)} className={checkboxClass} /> <span className="font-medium text-orange-500">Nagad</span></label>
                      <Field error={errors.nagad_number}><input value={data.nagad_number} onChange={e => setData('nagad_number', e.target.value)} className={inputClass} placeholder="Nagad Number" /></Field>
                    </div>
                    <div>
                      <label className="flex items-center gap-3 mb-2"><input type="checkbox" checked={data.pay_rocket_enabled} onChange={e => setData('pay_rocket_enabled', e.target.checked)} className={checkboxClass} /> <span className="font-medium text-purple-600">Rocket</span></label>
                      <Field error={errors.rocket_number}><input value={data.rocket_number} onChange={e => setData('rocket_number', e.target.value)} className={inputClass} placeholder="Rocket Number" /></Field>
                    </div>
                    <div>
                      <label className="flex items-center gap-3 mb-2"><input type="checkbox" checked={data.pay_cod_enabled} onChange={e => setData('pay_cod_enabled', e.target.checked)} className={checkboxClass} /> <span className="font-medium text-gray-800">Cash on Delivery</span></label>
                    </div>
                  </div>
                  <div className="pt-4 border-t border-gray-50">
                    <label className="flex items-center gap-3">
                      <input type="checkbox" checked={data.show_cards_in_footer} onChange={e => setData('show_cards_in_footer', e.target.checked)} className={checkboxClass} />
                      <span className="text-sm font-medium">Show Payment Icons in Footer</span>
                    </label>
                  </div>

                  {/* COD Delivery Upfront Toggle */}
                  <div className="pt-4 border-t border-gray-50 space-y-2">
                    <h4 className="font-semibold text-gray-800 text-sm">Cash on Delivery — Thank You Page</h4>
                    <label className="flex items-start gap-3 cursor-pointer">
                      <input
                        type="checkbox"
                        checked={data.cod_delivery_upfront}
                        onChange={e => setData('cod_delivery_upfront', e.target.checked)}
                        className={checkboxClass}
                      />
                      <div>
                        <span className="font-medium text-gray-800 text-sm block">"Pay now (delivery)" ব্রেকডাউন দেখাও</span>
                        <span className="text-xs text-gray-500 leading-snug">
                          চালু থাকলে: Thank You পেজে ডেলিভারি চার্জ আলাদাভাবে <b>Pay now</b> এবং বাকি টাকা <b>Pay after delivery</b> হিসেবে দেখাবে।<br />
                          বন্ধ থাকলে: শুধু Order Total দেখাবে, আলাদা ব্রেকডাউন থাকবে না।
                        </span>
                      </div>
                    </label>
                  </div>
                </div>
                <button type="submit" disabled={processing} className="px-6 py-3 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white font-semibold rounded-xl">Save Payment Settings</button>
              </form>
            )}

            {activeTab === 'shipping' && (
              <form onSubmit={e => submitSection(e, 'shipping')} className="space-y-5">
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
                  <h3 className="font-bold text-gray-900 pb-3 border-b border-gray-50">Shipping & Currency</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <Field label="Shipping Fee (Inside Dhaka)" error={errors.shipping_inside_dhaka}><input type="number" min="0" step="0.01" value={data.shipping_inside_dhaka} onChange={e => setData('shipping_inside_dhaka', e.target.value)} className={inputClass} required /></Field>
                    <Field label="Inside Label" error={errors.shipping_inside_label}><input value={data.shipping_inside_label} onChange={e => setData('shipping_inside_label', e.target.value)} className={inputClass} /></Field>
                    <Field label="Shipping Fee (Outside Dhaka)" error={errors.shipping_outside_dhaka}><input type="number" min="0" step="0.01" value={data.shipping_outside_dhaka} onChange={e => setData('shipping_outside_dhaka', e.target.value)} className={inputClass} required /></Field>
                    <Field label="Outside Label" error={errors.shipping_outside_label}><input value={data.shipping_outside_label} onChange={e => setData('shipping_outside_label', e.target.value)} className={inputClass} /></Field>
                    <Field label="Tax Percent (%)" error={errors.tax_percent}><input type="number" min="0" max="100" step="0.01" value={data.tax_percent} onChange={e => setData('tax_percent', e.target.value)} className={inputClass} required /></Field>
                  </div>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <Field label="Currency Symbol" error={errors.currency_symbol}><input type="text" value={data.currency_symbol} onChange={e => setData('currency_symbol', e.target.value)} className={inputClass} placeholder="e.g. ৳" /></Field>
                    <Field label="Currency Code" error={errors.currency_code}><input type="text" value={data.currency_code} onChange={e => setData('currency_code', e.target.value)} className={inputClass} placeholder="e.g. BDT" /></Field>
                  </div>
                  <div className="grid grid-cols-1 gap-4 mt-6 pt-6 border-t border-gray-100">
                    <div>
                      <h3 className="font-bold text-gray-900">Checkout &amp; Popup Delivery Note (ডেলিভারি নোট অপশন)</h3>
                      <p className="text-xs text-gray-400 mt-0.5">Allow customers to provide special delivery notes or instructions during checkout and COD quick order popup.</p>
                    </div>
                    <label className="flex items-center gap-3 cursor-pointer">
                      <input 
                        type="checkbox" 
                        checked={data.checkout_delivery_note_enabled} 
                        onChange={e => setData('checkout_delivery_note_enabled', e.target.checked)} 
                        className={checkboxClass} 
                      />
                      <span className="text-sm font-semibold text-gray-800">
                        Enable Delivery Note field on Checkout &amp; Quick Order Popup (চেকআউট পেজ এবং পপআপে ডেলিভারি নোট চালু রাখুন)
                      </span>
                    </label>
                    {data.checkout_delivery_note_enabled && (
                      <Field label="Delivery Note Field Label (ফিল্ড লেবেল)" error={errors.checkout_delivery_note_label}>
                        <input 
                          value={data.checkout_delivery_note_label} 
                          onChange={e => setData('checkout_delivery_note_label', e.target.value)} 
                          className={inputClass} 
                          placeholder="ডেলিভারি সংক্রান্ত বিশেষ নোট (ঐচ্ছিক)" 
                        />
                      </Field>
                    )}
                  </div>

                  <div className="grid grid-cols-1 gap-6 mt-6 pt-6 border-t border-gray-100">
                    <h3 className="font-bold text-gray-900">Security & Fraud Prevention</h3>
                    <Field label="Order Cooldown (Minutes)" error={errors.fraud_order_time_limit_minutes} help="Time before the same IP can place another order. (Set to 0 to disable)">
                        <input type="number" min="0" max="1440" value={data.fraud_order_time_limit_minutes} onChange={e => setData('fraud_order_time_limit_minutes', e.target.value)} className={inputClass} />
                    </Field>
                  </div>
                </div>
                <button type="submit" disabled={processing} className="px-6 py-3 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white font-semibold rounded-xl">Save Shipping Settings</button>
              </form>
            )}

            {activeTab === 'mail' && (
              <form onSubmit={e => submitSection(e, 'mail')} className="space-y-5">
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
                  <h3 className="font-bold text-gray-900 pb-3 border-b border-gray-50">Mail Configuration</h3>
                  <label className="flex items-center gap-3"><input type="checkbox" checked={data.otp_enabled} onChange={e => setData('otp_enabled', e.target.checked)} className={checkboxClass} /> <span className="text-sm font-medium">Enable OTP Login/Verification</span></label>
                  
                  <div className="grid grid-cols-2 gap-4">
                    <Field label="Mailer" error={errors.mail_mailer}>
                      <select value={data.mail_mailer} onChange={e => setData('mail_mailer', e.target.value)} className={inputClass}>
                        <option value="log">Log (Testing)</option>
                        <option value="smtp">SMTP (Production)</option>
                      </select>
                    </Field>
                    <Field label="Encryption" error={errors.mail_encryption}>
                      <select value={data.mail_encryption} onChange={e => setData('mail_encryption', e.target.value)} className={inputClass}>
                        <option value="none">None</option>
                        <option value="tls">TLS</option>
                        <option value="ssl">SSL</option>
                      </select>
                    </Field>
                    <Field label="Host" error={errors.mail_host}><input value={data.mail_host} onChange={e => setData('mail_host', e.target.value)} className={inputClass} /></Field>
                    <Field label="Port" error={errors.mail_port}><input value={data.mail_port} onChange={e => setData('mail_port', e.target.value)} className={inputClass} /></Field>
                    <Field label="Username" error={errors.mail_username}><input value={data.mail_username} onChange={e => setData('mail_username', e.target.value)} className={inputClass} /></Field>
                    <Field label="Password" error={errors.mail_password}><input type="password" value={data.mail_password} onChange={e => setData('mail_password', e.target.value)} className={inputClass} placeholder="Leave blank to keep unchanged" /></Field>
                    <Field label="From Address" error={errors.mail_from_address}><input value={data.mail_from_address} onChange={e => setData('mail_from_address', e.target.value)} className={inputClass} /></Field>
                    <Field label="From Name" error={errors.mail_from_name}><input value={data.mail_from_name} onChange={e => setData('mail_from_name', e.target.value)} className={inputClass} /></Field>
                  </div>

                  <div className="pt-4 border-t border-gray-50 flex gap-2">
                    <input type="email" value={testEmail} onChange={e => setTestEmail(e.target.value)} placeholder="Email to test..." className={`${inputClass} max-w-xs`} />
                    <button type="button" onClick={handleTestMail} className="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-xl">Send Test Mail</button>
                  </div>
                </div>
                <button type="submit" disabled={processing} className="px-6 py-3 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white font-semibold rounded-xl">Save Mail Settings</button>
              </form>
            )}

            {activeTab === 'seo' && (
              <form onSubmit={e => submitSection(e, 'seo')} className="space-y-5">
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
                  <h3 className="font-bold text-gray-900 pb-3 border-b border-gray-50">Default SEO Setup</h3>
                  <div className="rounded-xl border border-emerald-100 bg-emerald-50 p-4 text-sm text-emerald-950">
                    <h4 className="font-bold">SEO setup helper</h4>
                    <ul className="mt-2 list-disc space-y-1 pl-5 text-xs leading-5 text-emerald-900">
                      <li>These values are the fallback for storefront pages. Product and category SEO fields override them when filled.</li>
                      <li>Use a unique, clear title (about 50–60 characters) and a helpful description (about 120–160 characters).</li>
                      <li>Keyword tags are optional for modern search engines, but can be kept for internal consistency.</li>
                      <li>Your crawl files are generated automatically: <a href="/sitemap.xml" target="_blank" rel="noreferrer" className="font-semibold underline">View sitemap.xml</a> and <a href="/robots.txt" target="_blank" rel="noreferrer" className="font-semibold underline">View robots.txt</a>. Submit the sitemap URL to Google Search Console after the site is public.</li>
                    </ul>
                  </div>
                  <Field label="Default Meta Title" error={errors.default_meta_title}>
                    <input value={data.default_meta_title} onChange={e => setData('default_meta_title', e.target.value)} className={inputClass} />
                    <p className="mt-1.5 text-xs text-gray-500">{data.default_meta_title.length}/60 characters recommended.</p>
                  </Field>
                  <Field label="Default Meta Description" error={errors.default_meta_description}>
                    <textarea value={data.default_meta_description} onChange={e => setData('default_meta_description', e.target.value)} rows={3} className={inputClass} />
                    <p className="mt-1.5 text-xs text-gray-500">{data.default_meta_description.length}/160 characters recommended.</p>
                  </Field>
                  <Field label="Default Meta Keywords" error={errors.default_meta_keywords}>
                    <textarea value={data.default_meta_keywords} onChange={e => setData('default_meta_keywords', e.target.value)} rows={2} className={inputClass} placeholder="store, shop, etc..." />
                    <p className="mt-1.5 text-xs text-gray-500">Optional. Use comma-separated terms; do not repeat the same phrase.</p>
                  </Field>
                </div>
                <button type="submit" disabled={processing} className="px-6 py-3 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white font-semibold rounded-xl">Save SEO Settings</button>
              </form>
            )}

            {activeTab === 'tracking' && (
              <form onSubmit={e => submitSection(e, 'tracking')} className="space-y-5">
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
                  <h3 className="font-bold text-gray-900 pb-3 border-b border-gray-50">Marketing & Analytics</h3>
                  <div className="rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-950">
                    <h4 className="font-bold">Setup helper</h4>
                    <p className="mt-1 text-xs leading-5 text-blue-800">Paste only the ID, not the complete script. Save, open your storefront in a new tab, then verify that the tag fires with the provider’s browser helper.</p>
                    <div className="mt-4 grid gap-3 lg:grid-cols-3">
                      <div className="rounded-lg bg-white/80 p-3">
                        <p className="font-semibold">1. Google Tag Manager</p>
                        <p className="mt-1 text-xs leading-5 text-blue-800">In Tag Manager, open your Web container and copy its container ID, for example <code>GTM-ABC1234</code>. Publish the container after configuring tags.</p>
                      </div>
                      <div className="rounded-lg bg-white/80 p-3">
                        <p className="font-semibold">2. Google Analytics 4</p>
                        <p className="mt-1 text-xs leading-5 text-blue-800">In GA4, copy the Web data stream Measurement ID, for example <code>G-ABC123456</code>. Leave this empty when your GA4 tag is already configured in GTM to prevent duplicate page views.</p>
                      </div>
                      <div className="rounded-lg bg-white/80 p-3">
                        <p className="font-semibold">3. Meta Pixel</p>
                        <p className="mt-1 text-xs leading-5 text-blue-800">In Meta Events Manager, copy the numeric Pixel ID only. Use Meta Pixel Helper after saving to confirm the <code>PageView</code> event.</p>
                      </div>
                    </div>
                  </div>
                  <div className="space-y-4">
                    <Field label="Google Tag Manager (GTM) ID" error={errors.tracking_gtm_id}>
                      <input value={data.tracking_gtm_id} onChange={e => setData('tracking_gtm_id', e.target.value)} className={inputClass} placeholder="GTM-XXXXXXX" />
                      <p className="mt-1.5 text-xs text-gray-500">Optional. This application automatically adds the GTM base tag to storefront pages.</p>
                    </Field>
                    <Field label="Google Analytics (GA4) ID" error={errors.tracking_ga4_id}>
                      <input value={data.tracking_ga4_id} onChange={e => setData('tracking_ga4_id', e.target.value)} className={inputClass} placeholder="G-XXXXXXX" />
                      <p className="mt-1.5 text-xs text-gray-500">Use direct GA4 only when GA4 is not managed through GTM.</p>
                    </Field>
                    <Field label="Meta Pixel ID" error={errors.tracking_meta_pixel_id}>
                      <input value={data.tracking_meta_pixel_id} onChange={e => setData('tracking_meta_pixel_id', e.target.value)} className={inputClass} placeholder="Numeric ID" />
                      <p className="mt-1.5 text-xs text-gray-500">Use numbers only; do not paste Meta’s full Pixel code.</p>
                    </Field>
                  </div>
                </div>
                <button type="submit" disabled={processing} className="px-6 py-3 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white font-semibold rounded-xl">Save Tracking Settings</button>
              </form>
            )}

            {activeTab === 'legal' && (
              <form onSubmit={e => submitSection(e, 'legal')} className="space-y-5">
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
                  <div className="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-gray-50">
                    <div>
                      <h3 className="font-bold text-gray-900">Legal Pages</h3>
                      <p className="mt-1 text-xs text-gray-400">Preview HTML and CSS before saving. The preview uses your current, unsaved editor content.</p>
                    </div>
                  </div>
                  <div className="flex flex-wrap gap-2" role="tablist" aria-label="Legal page editors">
                    {legalPageEditors.map(page => (
                      <button key={page.id} type="button" role="tab" aria-selected={selectedLegalPage.id === page.id} onClick={() => setActiveLegalEditor(page.id)} className={`rounded-lg px-4 py-2 text-sm font-semibold transition-colors ${selectedLegalPage.id === page.id ? 'bg-orange-500 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}>
                        {page.tab}
                      </button>
                    ))}
                  </div>
                  <section className="rounded-xl border border-gray-200 bg-gray-50/70 p-4 sm:p-5">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                      <div>
                        <h4 className="font-bold text-gray-900">{selectedLegalPage.title}</h4>
                        <p className="mt-1 text-xs text-gray-500">HTML and CSS editor. Other legal pages remain unchanged while you edit this page.</p>
                      </div>
                      <a href={selectedLegalPage.path} target="_blank" rel="noreferrer" className="text-xs font-semibold text-orange-600 hover:underline">View saved page ↗</a>
                    </div>
                    {selectedLegalPage.isShipping && (
                      <label className="mb-4 flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3">
                        <input type="checkbox" checked={data.shipping_page_enabled} onChange={e => setData('shipping_page_enabled', e.target.checked)} className={checkboxClass} />
                        <span className="text-sm font-semibold text-gray-800">Enable Shipping page and footer link</span>
                      </label>
                    )}
                    <Field label={`${selectedLegalPage.title} content`} error={errors[selectedLegalPage.id]}>
                      <textarea value={data[selectedLegalPage.id]} onChange={e => setData(selectedLegalPage.id, e.target.value)} rows={22} spellCheck="false" className={`${inputClass} min-h-[420px] resize-y font-mono text-xs leading-6`} placeholder={selectedLegalPage.isShipping ? 'Add delivery areas, delivery times, and shipping terms...' : 'Write or paste page content here...'} />
                    </Field>
                    <p className="mt-3 text-xs leading-5 text-gray-500">
                      {selectedLegalPage.isShipping
                        ? <>Shipping charges from Shipping &amp; Currency always appear above this content. Add HTML/CSS only for information below them; use a <code>&lt;style&gt;...&lt;/style&gt;</code> block for CSS.</>
                        : <>Add HTML directly. Use a <code>&lt;style&gt;...&lt;/style&gt;</code> block for CSS. JavaScript is not supported.</>}
                    </p>
                    <button type="button" onClick={() => setLegalPreview({ title: `${selectedLegalPage.title} preview`, content: selectedLegalPage.isShipping ? shippingPreviewContent(data) : data[selectedLegalPage.id] })} className="mt-4 inline-flex items-center gap-1 rounded-lg bg-orange-50 px-3 py-2 text-xs font-bold text-orange-600 transition-colors hover:bg-orange-100">
                      {selectedLegalPage.isShipping ? 'Preview charges and unsaved changes' : 'Preview unsaved changes'} <span aria-hidden="true">↗</span>
                    </button>
                  </section>
                </div>
                <button type="submit" disabled={processing} className="px-6 py-3 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white font-semibold rounded-xl">Save Legal Pages</button>
              </form>
            )}

            {legalPreview && (
              <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-label={legalPreview.title}>
                <div className="flex h-[min(820px,calc(100vh-32px))] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                  <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <div>
                      <h2 className="font-bold text-gray-900">{legalPreview.title}</h2>
                      <p className="mt-0.5 text-xs text-gray-400">Unsaved changes are shown safely in an isolated preview.</p>
                    </div>
                    <button type="button" onClick={() => setLegalPreview(null)} className="rounded-lg p-2 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900" aria-label="Close preview">
                      <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M6 6l12 12M18 6 6 18" /></svg>
                    </button>
                  </div>
                  <iframe title={legalPreview.title} srcDoc={policyPreviewDocument(legalPreview.title, legalPreview.content)} sandbox="" className="min-h-0 w-full flex-1 border-0 bg-slate-50" />
                </div>
              </div>
            )}

            {activeTab === 'courier' && (
              <form onSubmit={e => submitSection(e, 'courier')} className="space-y-5">

                {/* Default Courier */}
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
                  <h3 className="font-bold text-gray-900 pb-3 border-b border-gray-50 flex items-center gap-2">
                    <svg className="w-5 h-5 text-orange-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path strokeLinecap="round" strokeLinejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg> Courier Integration
                  </h3>
                  <Field label="Default Courier" error={errors.courier_default}>
                    <select value={data.courier_default} onChange={e => setData('courier_default', e.target.value)} className={inputClass}>
                      <option value="steadfast">Steadfast Courier</option>
                      <option value="pathao">Pathao Courier</option>
                      <option value="redx">RedX Courier</option>
                    </select>
                  </Field>
                  <p className="text-xs text-gray-400 bg-gray-50 rounded-xl p-3">
                    ✅ Keys entered here are saved in the database and override any <code>.env</code> values. Fields left blank will fall back to <code>.env</code> values.
                  </p>
                </div>

                {/* Steadfast */}
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
                  <div className="flex items-center gap-2 pb-3 border-b border-gray-50">
                    <span className="inline-block w-3 h-3 rounded-full bg-green-500"></span>
                    <h3 className="font-bold text-gray-900">Steadfast Courier</h3>
                    <a href="https://steadfast.com.bd/dashboard" target="_blank" rel="noreferrer" className="ml-auto text-xs text-orange-500 hover:underline">Open Dashboard ↗</a>
                  </div>
                  <div className="grid grid-cols-2 gap-4">
                    <Field label="API Key" error={errors.steadfast_api_key}>
                      <input value={data.steadfast_api_key} onChange={e => setData('steadfast_api_key', e.target.value)} className={inputClass} placeholder="Get from Steadfast merchant portal" />
                    </Field>
                    <Field label="Secret Key" error={errors.steadfast_secret_key}>
                      <input type="password" value={data.steadfast_secret_key} onChange={e => setData('steadfast_secret_key', e.target.value)} className={inputClass} placeholder="••••••••••••" />
                    </Field>
                  </div>
                </div>

                {/* Pathao */}
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
                  <div className="flex items-center gap-2 pb-3 border-b border-gray-50">
                    <span className="inline-block w-3 h-3 rounded-full bg-blue-500"></span>
                    <h3 className="font-bold text-gray-900">Pathao Courier</h3>
                    <a href="https://pathao.com/courier/" target="_blank" rel="noreferrer" className="ml-auto text-xs text-orange-500 hover:underline">Open Dashboard ↗</a>
                  </div>
                  <div className="grid grid-cols-2 gap-4">
                    <Field label="Client ID" error={errors.pathao_client_id}>
                      <input value={data.pathao_client_id} onChange={e => setData('pathao_client_id', e.target.value)} className={inputClass} />
                    </Field>
                    <Field label="Client Secret" error={errors.pathao_client_secret}>
                      <input type="password" value={data.pathao_client_secret} onChange={e => setData('pathao_client_secret', e.target.value)} className={inputClass} placeholder="••••••••••••" />
                    </Field>
                    <Field label="Username (Email)" error={errors.pathao_username}>
                      <input value={data.pathao_username} onChange={e => setData('pathao_username', e.target.value)} className={inputClass} />
                    </Field>
                    <Field label="Password" error={errors.pathao_password}>
                      <input type="password" value={data.pathao_password} onChange={e => setData('pathao_password', e.target.value)} className={inputClass} placeholder="••••••••••••" />
                    </Field>
                    <Field label="Store ID" error={errors.pathao_store_id}>
                      <input value={data.pathao_store_id} onChange={e => setData('pathao_store_id', e.target.value)} className={inputClass} placeholder="From Pathao merchant stores" />
                    </Field>
                  </div>
                </div>

                {/* RedX */}
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
                  <div className="flex items-center gap-2 pb-3 border-b border-gray-50">
                    <span className="inline-block w-3 h-3 rounded-full bg-red-500"></span>
                    <h3 className="font-bold text-gray-900">RedX Courier</h3>
                    <a href="https://redx.com.bd/" target="_blank" rel="noreferrer" className="ml-auto text-xs text-orange-500 hover:underline">Open Dashboard ↗</a>
                  </div>
                  <Field label="API Access Token" error={errors.redx_api_token}>
                    <input type="password" value={data.redx_api_token} onChange={e => setData('redx_api_token', e.target.value)} className={inputClass} placeholder="Bearer token from RedX merchant portal" />
                  </Field>
                </div>

                <button type="submit" disabled={processing} className="px-6 py-3 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white font-semibold rounded-xl flex items-center gap-2">
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7"/></svg>
                  Save Courier Settings
                </button>
              </form>
            )}

            {activeTab === 'fog' && (
              <form onSubmit={e => submitSection(e, 'fake_order_guard')} className="space-y-5">
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
                  <div className="flex items-center justify-between pb-3 border-b border-gray-50">
                    <h3 className="font-bold text-gray-900"><svg className="w-4 h-4 text-orange-500 shrink-0 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>Fake Order Guard</h3>
                    <a href="/admin/fake-order-guard" target="_blank" rel="noreferrer" className="text-xs text-orange-500 hover:underline">Open FOG Dashboard ↗</a>
                  </div>
                  <p className="text-xs text-gray-400 bg-amber-50 border border-amber-200 rounded-xl p-3">
                    ⚠ Fake Order Guard blocks suspicious checkouts based on IP, device fingerprint, phone number, and BD Courier delivery history.
                  </p>

                  {/* Master Switches */}
                  <div className="space-y-3">
                    <p className="text-sm font-semibold text-gray-700">Master Switches</p>
                    <label className="flex items-center gap-3"><input type="checkbox" checked={data.fog_enabled ?? (settings.fog_enabled === '1')} onChange={e => setData('fog_enabled', e.target.checked)} className={checkboxClass} /> <span className="text-sm font-medium">Enable Fake Order Guard</span></label>
                    <label className="flex items-center gap-3"><input type="checkbox" checked={data.fog_ip_block_enabled ?? (settings.fog_ip_block_enabled === '1')} onChange={e => setData('fog_ip_block_enabled', e.target.checked)} className={checkboxClass} /> <span className="text-sm">Block by IP Address</span></label>
                    <label className="flex items-center gap-3"><input type="checkbox" checked={data.fog_device_block_enabled ?? (settings.fog_device_block_enabled === '1')} onChange={e => setData('fog_device_block_enabled', e.target.checked)} className={checkboxClass} /> <span className="text-sm">Block by Device Fingerprint</span></label>
                    <label className="flex items-center gap-3"><input type="checkbox" checked={data.fog_phone_block_enabled ?? (settings.fog_phone_block_enabled === '1')} onChange={e => setData('fog_phone_block_enabled', e.target.checked)} className={checkboxClass} /> <span className="text-sm">Block by Phone Number</span></label>
                    <label className="flex items-center gap-3"><input type="checkbox" checked={data.fog_fake_number_block_enabled ?? (settings.fog_fake_number_block_enabled === '1')} onChange={e => setData('fog_fake_number_block_enabled', e.target.checked)} className={checkboxClass} /> <span className="text-sm">Block Fake/Sequential Phone Numbers</span></label>
                    <label className="flex items-center gap-3"><input type="checkbox" checked={data.fog_auto_block_ip_on_cancel ?? (settings.fog_auto_block_ip_on_cancel === '1')} onChange={e => setData('fog_auto_block_ip_on_cancel', e.target.checked)} className={checkboxClass} /> <span className="text-sm">Auto-Block IP When Order Cancelled</span></label>
                  </div>

                  {/* Cooldown settings */}
                  <div className="grid grid-cols-2 gap-4 pt-4 border-t border-gray-50">
                    <Field label="IP Cooldown (minutes)" error={errors.fog_ip_cooldown_minutes}>
                      <input type="number" min="0" max="1440" value={data.fog_ip_cooldown_minutes ?? (settings.fog_ip_cooldown_minutes || '0')} onChange={e => setData('fog_ip_cooldown_minutes', e.target.value)} className={inputClass} placeholder="0 = disabled" />
                    </Field>
                    <Field label="Phone Cooldown (minutes)" error={errors.fog_phone_cooldown_minutes}>
                      <input type="number" min="0" max="1440" value={data.fog_phone_cooldown_minutes ?? (settings.fog_phone_cooldown_minutes || '0')} onChange={e => setData('fog_phone_cooldown_minutes', e.target.value)} className={inputClass} placeholder="0 = disabled" />
                    </Field>
                    <Field label="Max Orders per Phone" error={errors.fog_max_orders_per_phone}>
                      <input type="number" min="0" max="100" value={data.fog_max_orders_per_phone ?? (settings.fog_max_orders_per_phone || '0')} onChange={e => setData('fog_max_orders_per_phone', e.target.value)} className={inputClass} placeholder="0 = unlimited" />
                    </Field>
                  </div>
                </div>

                {/* BD Courier */}
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
                  <div className="flex items-center gap-2 pb-3 border-b border-gray-50">
                    <span className="inline-block w-3 h-3 rounded-full bg-indigo-500"></span>
                    <h3 className="font-bold text-gray-900">BD Courier Fraud Check</h3>
                    <a href="https://bdcourier.com" target="_blank" rel="noreferrer" className="ml-auto text-xs text-orange-500 hover:underline">BD Courier ↗</a>
                  </div>
                  <label className="flex items-center gap-3"><input type="checkbox" checked={data.fog_bdcourier_enabled ?? (settings.fog_bdcourier_enabled === '1')} onChange={e => setData('fog_bdcourier_enabled', e.target.checked)} className={checkboxClass} /> <span className="text-sm font-medium">Enable BD Courier phone check at checkout</span></label>
                  <label className="flex items-center gap-3"><input type="checkbox" checked={data.fog_bdcourier_auto_check_checkout ?? (settings.fog_bdcourier_auto_check_checkout === '1')} onChange={e => setData('fog_bdcourier_auto_check_checkout', e.target.checked)} className={checkboxClass} /> <span className="text-sm">Auto-check phone at checkout (block if risky)</span></label>
                  <div className="grid grid-cols-2 gap-4">
                    <Field label="BD Courier API Key" error={errors.fog_bdcourier_api_key}>
                      <input type="password" value={data.fog_bdcourier_api_key ?? (settings.fog_bdcourier_api_key || '')} onChange={e => setData('fog_bdcourier_api_key', e.target.value)} className={inputClass} placeholder="Your BD Courier API key" />
                    </Field>
                    <Field label="Minimum Success Rate to Allow (%)" error={errors.fog_bdcourier_min_success_rate}>
                      <input type="number" min="0" max="100" value={data.fog_bdcourier_min_success_rate ?? (settings.fog_bdcourier_min_success_rate || '0')} onChange={e => setData('fog_bdcourier_min_success_rate', e.target.value)} className={inputClass} placeholder="0 = no minimum" />
                    </Field>
                    <Field label="Block Risk Levels (comma-separated)" error={errors.fog_bdcourier_block_risk_levels}>
                      <input value={data.fog_bdcourier_block_risk_levels ?? (settings.fog_bdcourier_block_risk_levels || 'danger,high')} onChange={e => setData('fog_bdcourier_block_risk_levels', e.target.value)} className={inputClass} placeholder="danger,high" />
                    </Field>
                  </div>
                  <p className="text-xs text-gray-400">Risk levels: <code>safe</code>, <code>low</code>, <code>medium</code>, <code>high</code>, <code>danger</code></p>
                </div>

                <div className="flex gap-3 flex-wrap">
                  <button type="submit" disabled={processing} className="px-6 py-3 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white font-semibold rounded-xl flex items-center gap-2">
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Save FOG Settings
                  </button>
                  <a href="/admin/fake-order-guard" className="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl text-sm flex items-center gap-2">🛡 Open FOG Dashboard ↗</a>
                  <a href="/admin/blocked-ips" className="px-6 py-3 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 font-semibold rounded-xl text-sm">🚫 Blocked IPs</a>
                  <a href="/admin/blocked-phones" className="px-6 py-3 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 font-semibold rounded-xl text-sm">📵 Blocked Phones</a>
                </div>
              </form>
            )}
          </div>

        </div>
      </AdminLayout>
    </>
  );
}
