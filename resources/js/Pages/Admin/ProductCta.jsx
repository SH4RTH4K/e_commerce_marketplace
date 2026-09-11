import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm } from '@inertiajs/react';

const inputClass = "w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300 transition-all";

function ColorField({ label, value, onChange }) {
  return (
    <div>
      <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">{label}</label>
      <div className="flex items-center gap-2.5">
        <input
          type="color"
          value={value || '#000000'}
          onChange={e => onChange(e.target.value)}
          className="h-9 w-9 rounded-lg border border-gray-200 cursor-pointer p-0.5 bg-white shadow-sm shrink-0"
        />
        <input
          type="text"
          value={value}
          onChange={e => onChange(e.target.value)}
          placeholder="#000000"
          className="w-full uppercase font-mono text-xs border border-gray-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300"
        />
      </div>
    </div>
  );
}

function Toggle({ enabled, onChange, label, description }) {
  return (
    <div className="flex items-center justify-between py-2">
      <div>
        <p className="text-sm font-semibold text-gray-800">{label}</p>
        {description && <p className="text-xs text-gray-400 mt-0.5">{description}</p>}
      </div>
      <button
        type="button"
        onClick={() => onChange(!enabled)}
        className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none ${
          enabled ? 'bg-orange-500' : 'bg-gray-200'
        }`}
      >
        <span
          className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
            enabled ? 'translate-x-5' : 'translate-x-0'
          }`}
        />
      </button>
    </div>
  );
}

export default function ProductCta({ settings = {} }) {
  const { data, setData, put, processing, errors } = useForm({
    // COD Button
    product_page_cod_enabled: settings.product_page_cod_enabled ?? true,
    product_page_cod_text: settings.product_page_cod_text || 'ক্যাশ অন ডেলিভারিতে অর্ডার করুন',
    product_page_cod_bg_color: settings.product_page_cod_bg_color || '#16a34a',
    product_page_cod_text_color: settings.product_page_cod_text_color || '#ffffff',

    // WhatsApp Button
    product_page_whatsapp_enabled: settings.product_page_whatsapp_enabled ?? true,
    product_page_whatsapp_text: settings.product_page_whatsapp_text || 'WhatsApp Order',
    product_page_whatsapp_number: settings.product_page_whatsapp_number || '+8801923443872',
    product_page_whatsapp_bg_color: settings.product_page_whatsapp_bg_color || '#25D366',
    product_page_whatsapp_text_color: settings.product_page_whatsapp_text_color || '#ffffff',

    // Call For Order Button
    product_page_call_enabled: settings.product_page_call_enabled ?? true,
    product_page_call_text: settings.product_page_call_text || 'Call For Order',
    product_page_call_number: settings.product_page_call_number || '01923443872',
    product_page_call_bg_color: settings.product_page_call_bg_color || '#294294',
    product_page_call_text_color: settings.product_page_call_text_color || '#ffffff',

    // Add to Cart & Buy Now Buttons
    product_page_add_cart_text: settings.product_page_add_cart_text || 'ADD TO CART',
    product_page_add_cart_bg_color: settings.product_page_add_cart_bg_color || '#f15a24',
    product_page_add_cart_text_color: settings.product_page_add_cart_text_color || '#ffffff',
    product_page_buy_text: settings.product_page_buy_text || 'ORDER NOW',
    product_page_buy_bg_color: settings.product_page_buy_bg_color || '#0b1c21',
    product_page_buy_text_color: settings.product_page_buy_text_color || '#ffffff',

    // Product Card Settings
    product_card_bg_color: settings.product_card_bg_color || '#FFFFFF',
    product_card_text_color: settings.product_card_text_color || '#111827',
    product_card_btn_bg_color: settings.product_card_btn_bg_color || '#f15a24',
    product_card_btn_text_color: settings.product_card_btn_text_color || '#ffffff',
    product_card_buy_text: settings.product_card_buy_text || 'Order Now',
    product_card_options_text: settings.product_card_options_text || 'Order Now',
    product_cta_action: settings.product_cta_action || 'checkout',
  });

  const submit = (e) => {
    e.preventDefault();
    put('/admin/product-button', {
      preserveScroll: true,
    });
  };

  return (
    <>
      <Head title="Order &amp; CTA Buttons Customizer" />
      <AdminLayout title="Order &amp; CTA Buttons Customizer">
        <div className="max-w-6xl space-y-6">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <h1 className="text-xl font-bold text-gray-900">Order &amp; CTA Buttons Customizer</h1>
              <p className="text-sm text-gray-500 mt-0.5">
                Customize 1-click COD quick order, WhatsApp, Call buttons, Add to Cart, and Product Card designs.
              </p>
            </div>
            <button
              onClick={submit}
              disabled={processing}
              className="px-6 py-2.5 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white text-sm font-bold rounded-xl transition-colors shadow-sm shadow-orange-200 flex items-center justify-center gap-2"
            >
              {processing ? 'Saving...' : 'Save All Changes'}
            </button>
          </div>

          <form onSubmit={submit} className="grid grid-cols-1 lg:grid-cols-12 gap-6">

            {/* ── Left Column: Form Settings ── */}
            <div className="lg:col-span-7 space-y-6">

              {/* 1. COD Quick Order Button */}
              <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
                <div className="flex items-center gap-2 pb-3 border-b border-gray-100">
                  <div className="p-2 rounded-xl bg-green-50 text-green-600">
                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                  </div>
                  <div>
                    <h3 className="font-bold text-gray-900 text-sm">Cash on Delivery (COD) Quick Order Button</h3>
                    <p className="text-xs text-gray-400">Prominent 1-click modal checkout button on product details page</p>
                  </div>
                </div>

                <Toggle
                  enabled={data.product_page_cod_enabled}
                  onChange={v => setData('product_page_cod_enabled', v)}
                  label={data.product_page_cod_enabled ? "Enabled" : "Disabled"}
                  description="Display full-width high-conversion COD button on product page"
                />

                {data.product_page_cod_enabled && (
                  <div className="space-y-4 pt-2">
                    <div>
                      <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">COD Button Text</label>
                      <input
                        type="text"
                        value={data.product_page_cod_text}
                        onChange={e => setData('product_page_cod_text', e.target.value)}
                        className={inputClass}
                        required
                      />
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <ColorField
                        label="Button Background Color"
                        value={data.product_page_cod_bg_color}
                        onChange={v => setData('product_page_cod_bg_color', v)}
                      />
                      <ColorField
                        label="Button Text Color"
                        value={data.product_page_cod_text_color}
                        onChange={v => setData('product_page_cod_text_color', v)}
                      />
                    </div>
                  </div>
                )}
              </div>

              {/* 2. WhatsApp Order Button */}
              <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
                <div className="flex items-center gap-2 pb-3 border-b border-gray-100">
                  <div className="p-2 rounded-xl bg-emerald-50 text-emerald-600">
                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                  </div>
                  <div>
                    <h3 className="font-bold text-gray-900 text-sm">WhatsApp Order Button</h3>
                    <p className="text-xs text-gray-400">Opens WhatsApp chat with product name &amp; link</p>
                  </div>
                </div>

                <Toggle
                  enabled={data.product_page_whatsapp_enabled}
                  onChange={v => setData('product_page_whatsapp_enabled', v)}
                  label={data.product_page_whatsapp_enabled ? "Enabled" : "Disabled"}
                  description="Enable direct 1-click WhatsApp order button"
                />

                {data.product_page_whatsapp_enabled && (
                  <div className="space-y-4 pt-2">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Button Text</label>
                        <input
                          type="text"
                          value={data.product_page_whatsapp_text}
                          onChange={e => setData('product_page_whatsapp_text', e.target.value)}
                          className={inputClass}
                          required
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Dedicated WhatsApp Number</label>
                        <input
                          type="text"
                          value={data.product_page_whatsapp_number}
                          onChange={e => setData('product_page_whatsapp_number', e.target.value)}
                          placeholder="+8801XXXXXXXXX"
                          className={inputClass}
                        />
                      </div>
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <ColorField
                        label="Button Background Color"
                        value={data.product_page_whatsapp_bg_color}
                        onChange={v => setData('product_page_whatsapp_bg_color', v)}
                      />
                      <ColorField
                        label="Button Text Color"
                        value={data.product_page_whatsapp_text_color}
                        onChange={v => setData('product_page_whatsapp_text_color', v)}
                      />
                    </div>
                  </div>
                )}
              </div>

              {/* 3. Call For Order Button */}
              <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
                <div className="flex items-center gap-2 pb-3 border-b border-gray-100">
                  <div className="p-2 rounded-xl bg-blue-50 text-blue-600">
                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                  </div>
                  <div>
                    <h3 className="font-bold text-gray-900 text-sm">Call For Order Button</h3>
                    <p className="text-xs text-gray-400">Initiates direct telephone call from customer device</p>
                  </div>
                </div>

                <Toggle
                  enabled={data.product_page_call_enabled}
                  onChange={v => setData('product_page_call_enabled', v)}
                  label={data.product_page_call_enabled ? "Enabled" : "Disabled"}
                  description="Enable direct call for order button"
                />

                {data.product_page_call_enabled && (
                  <div className="space-y-4 pt-2">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Button Text</label>
                        <input
                          type="text"
                          value={data.product_page_call_text}
                          onChange={e => setData('product_page_call_text', e.target.value)}
                          className={inputClass}
                          required
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Dedicated Call Number</label>
                        <input
                          type="text"
                          value={data.product_page_call_number}
                          onChange={e => setData('product_page_call_number', e.target.value)}
                          placeholder="01XXXXXXXXX"
                          className={inputClass}
                        />
                      </div>
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <ColorField
                        label="Button Background Color"
                        value={data.product_page_call_bg_color}
                        onChange={v => setData('product_page_call_bg_color', v)}
                      />
                      <ColorField
                        label="Button Text Color"
                        value={data.product_page_call_text_color}
                        onChange={v => setData('product_page_call_text_color', v)}
                      />
                    </div>
                  </div>
                )}
              </div>

              {/* 4. Add to Cart & Order Now Buttons */}
              <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
                <div className="flex items-center gap-2 pb-3 border-b border-gray-100">
                  <div className="p-2 rounded-xl bg-orange-50 text-orange-600">
                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                  </div>
                  <div>
                    <h3 className="font-bold text-gray-900 text-sm">Add to Cart &amp; Order Now Buttons</h3>
                    <p className="text-xs text-gray-400">Styling for main action buttons on product page</p>
                  </div>
                </div>

                {/* Add to cart */}
                <div className="p-4 bg-gray-50/70 rounded-xl border border-gray-100 space-y-3">
                  <h4 className="text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-1.5">
                    <svg className="w-4 h-4 text-orange-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    <span>Add to Cart Button</span>
                  </h4>
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                      <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Button Text</label>
                      <input
                        type="text"
                        value={data.product_page_add_cart_text}
                        onChange={e => setData('product_page_add_cart_text', e.target.value)}
                        className={inputClass}
                        required
                      />
                    </div>
                    <ColorField
                      label="BG Color"
                      value={data.product_page_add_cart_bg_color}
                      onChange={v => setData('product_page_add_cart_bg_color', v)}
                    />
                    <ColorField
                      label="Text Color"
                      value={data.product_page_add_cart_text_color}
                      onChange={v => setData('product_page_add_cart_text_color', v)}
                    />
                  </div>
                </div>

                {/* Order now */}
                <div className="p-4 bg-gray-50/70 rounded-xl border border-gray-100 space-y-3">
                  <h4 className="text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-1.5">
                    <svg className="w-4 h-4 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span>Order Now Button</span>
                  </h4>
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                      <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Button Text</label>
                      <input
                        type="text"
                        value={data.product_page_buy_text}
                        onChange={e => setData('product_page_buy_text', e.target.value)}
                        className={inputClass}
                        required
                      />
                    </div>
                    <ColorField
                      label="BG Color"
                      value={data.product_page_buy_bg_color}
                      onChange={v => setData('product_page_buy_bg_color', v)}
                    />
                    <ColorField
                      label="Text Color"
                      value={data.product_page_buy_text_color}
                      onChange={v => setData('product_page_buy_text_color', v)}
                    />
                  </div>
                </div>
              </div>

              {/* 5. Product Card Customization (Homepage & Shop) */}
              <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
                <div className="flex items-center gap-2 pb-3 border-b border-gray-100">
                  <div className="p-2 rounded-xl bg-purple-50 text-purple-600">
                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                  </div>
                  <div>
                    <h3 className="font-bold text-gray-900 text-sm">Product Card Customization (Homepage &amp; Shop)</h3>
                    <p className="text-xs text-gray-400">Card background, text color &amp; unified order button design</p>
                  </div>
                </div>

                <div className="p-4 bg-gray-50/70 rounded-xl border border-gray-100 space-y-4">
                  <h4 className="text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-1.5">
                    🛍 Product Card Order Button
                  </h4>
                  
                  <div>
                    <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Button Text (All Products)</label>
                    <input
                      type="text"
                      value={data.product_card_buy_text}
                      onChange={e => {
                        setData('product_card_buy_text', e.target.value);
                        setData('product_card_options_text', e.target.value);
                      }}
                      placeholder="অর্ডার করুন / ORDER NOW / Buy Now"
                      className={inputClass}
                      required
                    />
                    <p className="text-[11px] text-gray-400 mt-1">Applies equally to both simple and variable products for a consistent look.</p>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <ColorField
                      label="Button Background Color"
                      value={data.product_card_btn_bg_color}
                      onChange={v => setData('product_card_btn_bg_color', v)}
                    />
                    <ColorField
                      label="Button Text Color"
                      value={data.product_card_btn_text_color}
                      onChange={v => setData('product_card_btn_text_color', v)}
                    />
                  </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                  <ColorField
                    label="Product Card Background"
                    value={data.product_card_bg_color}
                    onChange={v => setData('product_card_bg_color', v)}
                  />
                  <ColorField
                    label="Product Card Text Color"
                    value={data.product_card_text_color}
                    onChange={v => setData('product_card_text_color', v)}
                  />
                </div>

                <div className="pt-1">
                  <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Direct Button Click Behavior (Simple Products)</label>
                  <select
                    value={data.product_cta_action}
                    onChange={e => setData('product_cta_action', e.target.value)}
                    className={inputClass}
                  >
                    <option value="checkout">Direct Checkout (Instantly go to Checkout)</option>
                    <option value="cart">Add to Cart (Add item to cart &amp; stay on page)</option>
                  </select>
                </div>
              </div>

            </div>

            {/* ── Right Column: Live Preview ── */}
            <div className="lg:col-span-5 space-y-6">
              <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sticky top-20 space-y-5">
                <div className="flex items-center justify-between border-b border-gray-100 pb-3">
                  <h3 className="font-bold text-gray-900 text-sm">Live Preview</h3>
                  <span className="text-[10px] font-bold uppercase tracking-wider text-green-600 bg-green-50 px-2 py-0.5 rounded-full">
                    Interactive
                  </span>
                </div>

                {/* Product Detail Actions Preview */}
                <div className="space-y-3">
                  <p className="text-xs font-bold text-gray-400 uppercase tracking-wider">Product Details Actions</p>

                  <div className="space-y-2.5 p-4 rounded-xl bg-slate-50 border border-gray-100">
                    {/* COD Button */}
                    {data.product_page_cod_enabled && (
                      <button
                        type="button"
                        style={{
                          backgroundColor: data.product_page_cod_bg_color || '#16a34a',
                          color: data.product_page_cod_text_color || '#ffffff',
                        }}
                        className="w-full py-3 px-4 rounded-xl text-sm font-bold transition-opacity hover:opacity-95 shadow-sm flex items-center justify-center gap-2"
                      >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                          <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        {data.product_page_cod_text}
                      </button>
                    )}

                    {/* Add to Cart & Buy Now grid */}
                    <div className="grid grid-cols-2 gap-2">
                      <button
                        type="button"
                        style={{
                          backgroundColor: data.product_page_add_cart_bg_color || '#f15a24',
                          color: data.product_page_add_cart_text_color || '#ffffff',
                        }}
                        className="py-2.5 px-3 rounded-xl text-xs font-bold transition-opacity hover:opacity-95 text-center truncate"
                      >
                        {data.product_page_add_cart_text}
                      </button>

                      <button
                        type="button"
                        style={{
                          backgroundColor: data.product_page_buy_bg_color || '#0b1c21',
                          color: data.product_page_buy_text_color || '#ffffff',
                        }}
                        className="py-2.5 px-3 rounded-xl text-xs font-bold transition-opacity hover:opacity-95 text-center truncate"
                      >
                        {data.product_page_buy_text}
                      </button>
                    </div>

                    {/* WhatsApp & Call row */}
                    <div className="grid grid-cols-2 gap-2">
                      {data.product_page_whatsapp_enabled && (
                        <button
                          type="button"
                          style={{
                            backgroundColor: data.product_page_whatsapp_bg_color || '#25D366',
                            color: data.product_page_whatsapp_text_color || '#ffffff',
                          }}
                          className="py-2 px-2.5 rounded-xl text-xs font-semibold transition-opacity hover:opacity-95 flex items-center justify-center gap-1.5 truncate"
                        >
                          <svg className="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                          </svg>
                          <span className="truncate">{data.product_page_whatsapp_text}</span>
                        </button>
                      )}

                      {data.product_page_call_enabled && (
                        <button
                          type="button"
                          style={{
                            backgroundColor: data.product_page_call_bg_color || '#294294',
                            color: data.product_page_call_text_color || '#ffffff',
                          }}
                          className="py-2 px-2.5 rounded-xl text-xs font-semibold transition-opacity hover:opacity-95 flex items-center justify-center gap-1.5 truncate"
                        >
                          <svg className="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                          </svg>
                          <span className="truncate">{data.product_page_call_text}</span>
                        </button>
                      )}
                    </div>
                  </div>
                </div>

                {/* Product Card Preview */}
                <div className="space-y-3 pt-2">
                  <p className="text-xs font-bold text-gray-400 uppercase tracking-wider">Product Card Preview</p>

                  <div className="max-w-[240px] mx-auto">
                    <div
                      style={{
                        backgroundColor: data.product_card_bg_color || '#FFFFFF',
                        color: data.product_card_text_color || '#111827',
                      }}
                      className="rounded-2xl border border-gray-100 shadow-md overflow-hidden p-3 space-y-2.5 transition-colors"
                    >
                      <div className="h-32 bg-gray-100 rounded-xl flex items-center justify-center text-gray-400 text-xs font-medium">
                        Product Image
                      </div>
                      <div>
                        <p className="font-bold text-xs truncate">Sample Product</p>
                        <p className="font-extrabold text-sm text-orange-600 mt-0.5">৳1,450</p>
                      </div>
                      <button
                        type="button"
                        style={{
                          backgroundColor: data.product_card_btn_bg_color || '#f15a24',
                          color: data.product_card_btn_text_color || '#ffffff',
                        }}
                        className="w-full py-2.5 px-3 rounded-xl text-xs font-bold shadow-sm transition-all hover:brightness-95 flex items-center justify-center gap-1.5"
                      >
                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <span>{data.product_card_buy_text || 'Order Now'}</span>
                      </button>
                    </div>
                  </div>
                </div>

                <div className="pt-2">
                  <button
                    type="button"
                    onClick={submit}
                    disabled={processing}
                    className="w-full py-3 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white text-sm font-bold rounded-xl transition-all shadow-sm shadow-orange-200"
                  >
                    {processing ? 'Saving...' : 'Save All Changes'}
                  </button>
                </div>
              </div>
            </div>

          </form>
        </div>
      </AdminLayout>
    </>
  );
}
