import { useState, useEffect, useRef } from 'react';
import { usePage, router } from '@inertiajs/react';
import { trackInitiateCheckout } from '@/lib/tracking';

function getCsrf() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function postJson(url, data) {
  const res = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': getCsrf(),
    },
    body: JSON.stringify(data),
  });
  const json = await res.json();
  if (!res.ok) throw json;
  return json;
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
function money(v, sym = '৳') { return `${sym}${Number(v || 0).toLocaleString('en-BD')}`; }
function cls(...a) { return a.filter(Boolean).join(' '); }

// ─── Icons ─────────────────────────────────────────────────────────────────────
const I = ({ d, className = 'w-5 h-5' }) => (
  <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
    <path strokeLinecap="round" strokeLinejoin="round" d={d} />
  </svg>
);
const ICON = {
  user:    'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
  phone:   'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z',
  map:     'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z',
  tag:     'M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-5 5a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 10V5a2 2 0 012-2z',
  check:   'M5 13l4 4L19 7',
  close:   'M6 18L18 6M6 6l12 12',
  shield:  'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
  truck:   'M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0',
  box:     'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
  cod:     'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
  note:    'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
};

// ─── Input Field Component ─────────────────────────────────────────────────────
function Field({ icon, label, error, children }) {
  return (
    <div>
      <label className="block text-xs font-bold text-gray-500 mb-1.5 uppercase tracking-wider">{label}</label>
      <div className="relative">
        <span className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">
          <I d={icon} className="w-4 h-4" />
        </span>
        {children}
      </div>
      {error && (
        <p className="mt-1 text-xs text-red-500 flex items-center gap-1">
          <I d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" className="w-3 h-3 shrink-0" />
          {error}
        </p>
      )}
    </div>
  );
}

// ─── Main Modal ────────────────────────────────────────────────────────────────
export default function CodOrderModal({ open, onClose, product, qty = 1, variant = null, unitPrice = null, imageUrl: imgFn }) {
  const { app } = usePage().props;
  const sym     = app.settings?.currency_symbol || '৳';
  const s       = app.settings || {};

  const [quantity, setQuantity]       = useState(qty || 1);
  const [zone, setZone]               = useState('inside_dhaka');
  const [couponInput, setCouponInput] = useState('');
  const [couponApplied, setCouponApplied] = useState(null); // { code, discount }
  const [couponLoading, setCouponLoading] = useState(false);
  const [couponError, setCouponError]   = useState('');
  const [submitting, setSubmitting]     = useState(false);
  const [success, setSuccess]           = useState(null); // { order_number, total }
  const [globalError, setGlobalError]   = useState('');
  const [fields, setFields] = useState({ customer_name: '', customer_phone: '', shipping_address: '', delivery_note: '' });
  const [errors, setErrors] = useState({});
  const firstInputRef = useRef(null);

  const isDeliveryNoteEnabled = s.checkout_delivery_note_enabled !== false && s.checkout_delivery_note_enabled !== '0';
  const deliveryNoteLabel     = s.checkout_delivery_note_label || 'ডেলিভারি সংক্রান্ত বিশেষ নোট (ঐচ্ছিক)';

  const basePrice      = parseFloat(product?.sale_price || product?.regular_price || 0);
  const price          = (unitPrice !== null && unitPrice !== undefined) ? parseFloat(unitPrice) : basePrice;
  const subtotal       = price * quantity;
  const isFreeShipping = Boolean(product?.is_free_shipping);
  const shipping       = isFreeShipping ? 0 : (zone === 'inside_dhaka' ? (s.ship_inside || 60) : (s.ship_outside || 120));
  const discount       = couponApplied?.discount || 0;
  const total          = Math.max(0, subtotal - discount) + shipping;

  // Sync quantity with prop whenever modal opens or qty prop changes
  useEffect(() => {
    if (open) {
      setQuantity(qty || 1);
    }
  }, [open, qty]);

  // Prefill from auth user & fire InitiateCheckout event
  const { auth } = usePage().props;
  useEffect(() => {
    if (open) {
      if (auth?.user) {
        setFields(f => ({
          ...f,
          customer_name: auth.user.name || f.customer_name,
        }));
      }

      if (product) {
        trackInitiateCheckout([
          {
            id: product.id,
            name: product.name,
            price: price,
            quantity: quantity,
            variant: variant || undefined,
          },
        ], price * quantity);
      }
    }
  }, [open]);

  // Lock body & html scroll when modal is open
  useEffect(() => {
    if (open) {
      document.body.style.overflow = 'hidden';
      document.documentElement.style.overflow = 'hidden';
      document.body.style.touchAction = 'none';
    } else {
      document.body.style.overflow = '';
      document.documentElement.style.overflow = '';
      document.body.style.touchAction = '';
    }
    return () => {
      document.body.style.overflow = '';
      document.documentElement.style.overflow = '';
      document.body.style.touchAction = '';
    };
  }, [open]);

  // Close on Escape
  useEffect(() => {
    const handler = (e) => { if (e.key === 'Escape') handleClose(); };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, []);

  const handleClose = () => {
    if (submitting) return;
    onClose();
    setTimeout(() => {
      setSuccess(null);
      setErrors({});
      setGlobalError('');
      setCouponApplied(null);
      setCouponInput('');
      setCouponError('');
      setFields({ customer_name: '', customer_phone: '', shipping_address: '', delivery_note: '' });
    }, 300);
  };

  const applyCoupon = async () => {
    if (!couponInput.trim()) return;
    setCouponLoading(true);
    setCouponError('');
    try {
      setCouponApplied({ code: couponInput.trim(), discount: 0 });
      setCouponError('কুপন কোড সংরক্ষিত হয়েছে। অর্ডার সাবমিটের সময় যাচাই হবে।');
    } finally {
      setCouponLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSubmitting(true);
    setErrors({});
    setGlobalError('');

    try {
      const data = await postJson('/quick-order', {
        product_id:       product.id,
        qty:              quantity,
        variant:          variant || null,
        customer_name:    fields.customer_name,
        customer_phone:   fields.customer_phone,
        shipping_address: fields.shipping_address,
        shipping_zone:    zone,
        delivery_note:    fields.delivery_note || '',
        coupon_code:      couponApplied?.code || '',
      });

      if (data.success && data.redirect) {
        onClose();
        router.visit(data.redirect);
        return;
      }

      if (data.success) {
        setSuccess({ order_number: data.order_number, total: data.total, redirect: data.redirect });
      }
    } catch (err) {
      if (err?.errors) {
        setErrors(err.errors);
        if (err.errors.form) setGlobalError(err.errors.form);
        if (err.errors.coupon_code) {
          setCouponError(err.errors.coupon_code);
          setCouponApplied(null);
        }
      } else {
        setGlobalError('কিছু একটা সমস্যা হয়েছে। আবার চেষ্টা করুন।');
      }
    } finally {
      setSubmitting(false);
    }
  };

  const img = (() => {
    const p = product?.images?.find(i => i.is_primary)?.path || product?.images?.[0]?.path;
    return imgFn ? imgFn(p, product?.name) : (p ? `/storage/${p}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(product?.name || 'P')}&background=f15a24&color=fff`);
  })();

  if (!open && !success) return null;

  return (
    <>
      {/* Backdrop */}
      <div
        className={cls('fixed inset-0 z-[100] bg-black/60 backdrop-blur-sm transition-opacity duration-300', open ? 'opacity-100' : 'opacity-0 pointer-events-none')}
        onClick={handleClose}
      />

      {/* Modal / Drawer */}
      <div
        role="dialog"
        aria-modal="true"
        aria-label="Quick COD Order"
        className={cls(
          'fixed z-[101] bg-white shadow-2xl transition-all duration-300 ease-out flex flex-col overscroll-contain touch-pan-y',
          // Mobile: bottom sheet with safe-area support
          'bottom-0 left-0 right-0 rounded-t-3xl max-h-[92vh] max-h-[92dvh] pb-[env(safe-area-inset-bottom,0px)]',
          // Desktop: centered modal
          'sm:bottom-auto sm:left-1/2 sm:top-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:w-full sm:max-w-lg sm:rounded-3xl sm:max-h-[90vh] sm:max-h-[90dvh]',
          open ? 'translate-y-0 sm:scale-100 sm:opacity-100' : 'translate-y-full sm:translate-y-0 sm:scale-95 sm:opacity-0 pointer-events-none',
        )}
      >
        {/* ── Success Screen ── */}
        {success ? (
          <div className="flex flex-col items-center justify-center text-center p-8 gap-4 flex-1">
            <div className="w-20 h-20 rounded-full bg-green-100 flex items-center justify-center mb-2 animate-bounce">
              <I d={ICON.check} className="w-10 h-10 text-green-600" />
            </div>
            <h2 className="text-2xl font-extrabold text-gray-900">অর্ডার সফল!</h2>
            <p className="text-gray-500 text-sm">আপনার অর্ডার নম্বর:</p>
            <div className="px-6 py-3 bg-green-50 border border-green-200 rounded-2xl">
              <p className="text-2xl font-extrabold text-green-700 tracking-wider">{success.order_number}</p>
            </div>
            <p className="text-gray-500 text-sm">মোট পরিমাণ: <strong className="text-gray-900">{money(success.total, sym)}</strong></p>
            <p className="text-xs text-gray-400">আমাদের টিম শীঘ্রই আপনার সাথে যোগাযোগ করবে।</p>
            <div className="flex gap-3 mt-2 w-full">
              <button onClick={handleClose} className="flex-1 h-12 rounded-2xl border-2 border-gray-200 font-bold text-gray-600 hover:border-gray-300 transition-colors">
                বন্ধ করুন
              </button>
              <a href={success.redirect} className="flex-1 h-12 rounded-2xl bg-green-600 text-white font-bold flex items-center justify-center hover:bg-green-700 transition-colors">
                অর্ডার দেখুন
              </a>
            </div>
          </div>
        ) : (
          <>
            {/* ── Header ── */}
            <div className="flex items-center gap-3 p-4 sm:p-5 border-b border-gray-100 shrink-0">
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2">
                  <div className="w-7 h-7 rounded-lg bg-green-600 flex items-center justify-center shrink-0">
                    <I d={ICON.cod} className="w-4 h-4 text-white" />
                  </div>
                  <h2 className="text-base font-extrabold text-gray-900 leading-tight">ক্যাশ অন ডেলিভারিতে অর্ডার করুন</h2>
                </div>
              </div>
              <button onClick={handleClose} className="w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center shrink-0 transition-colors">
                <I d={ICON.close} className="w-4 h-4 text-gray-600" />
              </button>
            </div>

            {/* ── Product summary strip with interactive Quantity Stepper ── */}
            <div className="flex items-center justify-between gap-3 px-4 sm:px-5 py-3 bg-gray-50/90 border-b border-gray-100 shrink-0">
              <div className="flex items-center gap-3 min-w-0 flex-1">
                <div className="w-12 h-12 rounded-xl overflow-hidden border border-gray-200 shrink-0 bg-white shadow-2xs">
                  <img src={img} alt={product?.name} className="w-full h-full object-cover" />
                </div>
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-bold text-gray-900 line-clamp-1">{product?.name}</p>
                  <div className="flex items-center gap-2 mt-0.5">
                    <span className="text-xs font-extrabold text-green-700">{money(price, sym)}</span>
                    {variant && <span className="text-[11px] text-gray-500 bg-gray-200/70 px-1.5 py-0.5 rounded-md truncate max-w-[120px]">{variant}</span>}
                  </div>
                </div>
              </div>

              {/* Quantity Controls (+ / -) */}
              <div className="flex items-center gap-1 bg-white border border-gray-200 rounded-xl p-1 shadow-2xs shrink-0">
                <button
                  type="button"
                  onClick={() => setQuantity(q => Math.max(1, q - 1))}
                  disabled={quantity <= 1}
                  className="w-7 h-7 rounded-lg bg-gray-50 hover:bg-gray-100 disabled:opacity-40 disabled:hover:bg-gray-50 flex items-center justify-center text-gray-700 font-bold text-base transition-colors"
                  aria-label="Decrease quantity"
                  title="পরিমাণ কমান"
                >
                  −
                </button>
                <input
                  type="number"
                  min="1"
                  max={product?.stock_quantity > 0 ? product.stock_quantity : 99}
                  value={quantity}
                  onChange={(e) => {
                    const val = parseInt(e.target.value, 10);
                    if (!isNaN(val) && val >= 1) {
                      setQuantity(Math.min(val, product?.stock_quantity > 0 ? product.stock_quantity : 99));
                    } else if (e.target.value === '') {
                      setQuantity(1);
                    }
                  }}
                  className="w-8 h-7 text-center font-bold text-sm text-gray-900 border-none outline-none p-0 bg-transparent"
                />
                <button
                  type="button"
                  onClick={() => setQuantity(q => (product?.stock_quantity > 0 ? Math.min(product.stock_quantity, q + 1) : q + 1))}
                  className="w-7 h-7 rounded-lg bg-gray-50 hover:bg-gray-100 flex items-center justify-center text-gray-700 font-bold text-base transition-colors"
                  aria-label="Increase quantity"
                  title="পরিমাণ বাড়ান"
                >
                  +
                </button>
              </div>
            </div>

            {/* ── Form Body (scrollable) ── */}
            <form id="cod-form" onSubmit={handleSubmit} className="flex-1 overflow-y-auto overscroll-contain">
              <div className="p-4 sm:p-5 space-y-4">

                {/* Global error */}
                {globalError && (
                  <div className="flex items-start gap-2 p-3 bg-red-50 border border-red-200 rounded-2xl text-red-600 text-sm">
                    <I d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" className="w-4 h-4 mt-0.5 shrink-0" />
                    {globalError}
                  </div>
                )}

                {/* Name */}
                <Field icon={ICON.user} label="আপনার নাম *" error={errors.customer_name}>
                  <input
                    ref={firstInputRef}
                    type="text"
                    value={fields.customer_name}
                    onChange={e => setFields(f => ({ ...f, customer_name: e.target.value }))}
                    placeholder="আপনার সম্পূর্ণ নাম লিখুন"
                    required
                    className="w-full pl-10 pr-4 h-12 rounded-2xl border border-gray-200 text-[16px] sm:text-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 outline-none transition-all"
                  />
                </Field>

                {/* Phone */}
                <Field icon={ICON.phone} label="মোবাইল নম্বর *" error={errors.customer_phone}>
                  <input
                    type="tel"
                    value={fields.customer_phone}
                    onChange={e => setFields(f => ({ ...f, customer_phone: e.target.value }))}
                    placeholder="01XXXXXXXXX"
                    required
                    inputMode="numeric"
                    className="w-full pl-10 pr-4 h-12 rounded-2xl border border-gray-200 text-[16px] sm:text-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 outline-none transition-all"
                  />
                </Field>

                {/* Shipping Zone */}
                <div>
                  <div className="flex items-center justify-between mb-2">
                    <label className="block text-xs font-bold text-gray-500 uppercase tracking-wider">
                      ডেলিভারি এলাকা *
                    </label>
                    {isFreeShipping && (
                      <span className="text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                        ফ্রি হোম ডেলিভারি
                      </span>
                    )}
                  </div>
                  <div className="grid grid-cols-2 gap-2">
                    {[
                      { id: 'inside_dhaka', label: s.ship_inside_label || 'ঢাকার ভেতরে', charge: s.ship_inside || 60 },
                      { id: 'outside_dhaka', label: s.ship_outside_label || 'ঢাকার বাইরে', charge: s.ship_outside || 120 },
                    ].map(z => (
                      <button
                        key={z.id}
                        type="button"
                        onClick={() => setZone(z.id)}
                        className={cls(
                          'flex flex-col items-center p-3 rounded-2xl border-2 transition-all text-center',
                          zone === z.id
                            ? 'border-green-500 bg-green-50'
                            : 'border-gray-200 hover:border-gray-300'
                        )}
                      >
                        <I d={ICON.truck} className={cls('w-5 h-5 mb-1', zone === z.id ? 'text-green-600' : 'text-gray-400')} />
                        <span className={cls('text-sm font-bold', zone === z.id ? 'text-green-700' : 'text-gray-700')}>{z.label}</span>
                        <span className={cls('text-xs mt-0.5 font-medium', zone === z.id ? 'text-green-600' : 'text-gray-400')}>
                          {isFreeShipping ? 'শিপিং — ফ্রি (৳০)' : `শিপিং — ${money(z.charge, sym)}`}
                        </span>
                      </button>
                    ))}
                  </div>
                </div>

                {/* Address */}
                <Field icon={ICON.map} label="সম্পূর্ণ ঠিকানা *" error={errors.shipping_address}>
                  <textarea
                    value={fields.shipping_address}
                    onChange={e => setFields(f => ({ ...f, shipping_address: e.target.value }))}
                    placeholder="বাড়ি নং, রোড, এলাকা, জেলা..."
                    required
                    rows={2}
                    className="w-full pl-10 pr-4 pt-3 pb-3 rounded-2xl border border-gray-200 text-[16px] sm:text-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 outline-none transition-all resize-none"
                  />
                </Field>

                {/* Delivery Note (if enabled by admin) */}
                {isDeliveryNoteEnabled && (
                  <Field icon={ICON.note} label={deliveryNoteLabel} error={errors.delivery_note}>
                    <textarea
                      value={fields.delivery_note}
                      onChange={e => setFields(f => ({ ...f, delivery_note: e.target.value }))}
                      placeholder="যেমন: বিকাল ৫টার পর ডেলিভারি দিন বা দারোয়ানের কাছে রাখুন..."
                      rows={2}
                      className="w-full pl-10 pr-4 pt-3 pb-3 rounded-2xl border border-gray-200 text-[16px] sm:text-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 outline-none transition-all resize-none"
                    />
                  </Field>
                )}

                {/* Coupon */}
                <div>
                  <label className="block text-xs font-bold text-gray-500 mb-2 uppercase tracking-wider">কুপন কোড (ঐচ্ছিক)</label>
                  <div className="flex gap-2">
                    <div className="relative flex-1">
                      <span className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                        <I d={ICON.tag} className="w-4 h-4" />
                      </span>
                      <input
                        type="text"
                        value={couponInput}
                        onChange={e => { setCouponInput(e.target.value.toUpperCase()); setCouponError(''); setCouponApplied(null); }}
                        placeholder="কোড লিখুন"
                        className="w-full pl-10 pr-4 h-11 rounded-2xl border border-gray-200 text-[16px] sm:text-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 outline-none transition-all uppercase"
                      />
                    </div>
                    <button
                      type="button"
                      onClick={applyCoupon}
                      disabled={couponLoading || !couponInput.trim()}
                      className="px-4 h-11 bg-gray-100 hover:bg-gray-200 disabled:opacity-50 font-bold text-sm text-gray-700 rounded-2xl transition-colors shrink-0"
                    >
                      {couponLoading ? '...' : 'Apply'}
                    </button>
                  </div>
                  {couponError && (
                    <p className="mt-1 text-xs text-amber-600 flex items-center gap-1">
                      <I d={ICON.tag} className="w-3 h-3" /> {couponError}
                    </p>
                  )}
                  {couponApplied && !couponError && (
                    <p className="mt-1 text-xs text-green-600 flex items-center gap-1">
                      <I d={ICON.check} className="w-3 h-3" /> কুপন কোড যোগ করা হয়েছে!
                    </p>
                  )}
                </div>

                {/* Order Summary */}
                <div className="bg-gray-50 rounded-2xl p-4 space-y-2.5 border border-gray-100">
                  <p className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">অর্ডার সারাংশ</p>
                  <div className="flex justify-between text-sm text-gray-600">
                    <span className="flex items-center gap-1.5"><I d={ICON.box} className="w-3.5 h-3.5" /> পণ্যমূল্য × {quantity}</span>
                    <span className="font-bold text-gray-900">{money(subtotal, sym)}</span>
                  </div>
                  <div className="flex justify-between text-sm text-gray-600 items-center">
                    <span className="flex items-center gap-1.5"><I d={ICON.truck} className="w-3.5 h-3.5" /> শিপিং চার্জ</span>
                    {isFreeShipping ? (
                      <span className="font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200 text-xs">
                        ৳০ (ফ্রি ডেলিভারি)
                      </span>
                    ) : (
                      <span className="font-bold text-gray-900">{money(shipping, sym)}</span>
                    )}
                  </div>
                  {discount > 0 && (
                    <div className="flex justify-between text-sm text-green-600">
                      <span className="flex items-center gap-1.5"><I d={ICON.tag} className="w-3.5 h-3.5" /> ছাড়</span>
                      <span className="font-bold">-{money(discount, sym)}</span>
                    </div>
                  )}
                  <div className="border-t border-gray-200 pt-2.5 flex justify-between items-center">
                    <span className="font-extrabold text-gray-900">মোট</span>
                    <span className="text-xl font-extrabold text-gray-900">{money(total, sym)}</span>
                  </div>
                </div>

              </div>
            </form>

            {/* ── Footer Submit ── */}
            <div className="p-4 sm:p-5 border-t border-gray-100 shrink-0 bg-white rounded-b-3xl">
              <button
                type="submit"
                form="cod-form"
                disabled={submitting}
                className={cls(
                  'w-full h-14 rounded-2xl font-extrabold text-white text-base flex items-center justify-center gap-3 transition-all shadow-lg',
                  submitting
                    ? 'bg-green-400 cursor-not-allowed'
                    : 'bg-green-600 hover:bg-green-700 active:scale-[0.98] shadow-green-500/30'
                )}
              >
                {submitting ? (
                  <>
                    <svg className="w-5 h-5 animate-spin" viewBox="0 0 24 24" fill="none">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    অর্ডার প্রক্রিয়া হচ্ছে...
                  </>
                ) : (
                  <>
                    <I d={ICON.shield} className="w-5 h-5" />
                    অর্ডার নিশ্চিত করুন — {money(total, sym)}
                  </>
                )}
              </button>
              <p className="text-center text-[10px] text-gray-400 mt-2">
                ডেলিভারির পর পেমেন্ট • ১০০% নিরাপদ • ফ্রড সুরক্ষিত
              </p>
            </div>
          </>
        )}
      </div>
    </>
  );
}
