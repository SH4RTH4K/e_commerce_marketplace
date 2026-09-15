import { useState, useMemo, useEffect } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { Head, Link, useForm, router, usePage } from '@inertiajs/react';
import { money, imageUrl } from '@/lib/utils';
import { trackInitiateCheckout } from '@/lib/tracking';

export default function CheckoutPage({ 
  items, subtotal, shipInside, shipOutside, taxPercent, 
  user, couponCode, discount, paySettings, isFreeShipping
}) {
  const { app } = usePage().props;
  const [couponForm, setCouponForm] = useState({ code: '' });
  const [selectedKeys, setSelectedKeys] = useState([]);

  const form = useForm({
    customer_name: user?.name || '',
    customer_phone: user?.phone || '',
    customer_email: user?.email || '',
    shipping_address: user?.address || '',
    city: user?.city || '',
    postal_code: user?.postal_code || '',
    shipping_zone: 'inside_dhaka',
    payment_method: paySettings?.cod_enabled ? 'cod' : (paySettings?.bkash_enabled && paySettings?.bkash_number ? 'bkash' : ''),
    payment_sender_number: '',
    payment_txn_id: '',
    delivery_note: '',
  });

  const { data, setData, post, processing, errors } = form;
  const itemsList = Object.values(items || {});
  const allSelected = itemsList.length > 0 && selectedKeys.length === itemsList.length;

  useEffect(() => {
    const availableKeys = new Set(itemsList.map(item => item.key));
    setSelectedKeys(current => current.filter(key => availableKeys.has(key)));
  }, [items]);

  // Calculations
  const calcTotals = useMemo(() => {
    const fee = data.shipping_zone === 'inside_dhaka' ? shipInside : shipOutside;
    const taxable = Math.max(0, subtotal - discount);
    const tax = Math.round((taxable * taxPercent) / 100);
    const total = taxable + fee + tax;
    const payLater = Math.max(0, total - fee);

    return { fee, tax, total, payLater, taxable };
  }, [data.shipping_zone, subtotal, discount, shipInside, shipOutside, taxPercent]);

  const { fee, tax, total, payLater } = calcTotals;
  const isCod = data.payment_method === 'cod';

  // Abandoned Checkout Tracking
  useEffect(() => {
    const hasValidPhone = data.customer_phone && data.customer_phone.trim().length >= 6;
    const hasValidEmail = data.customer_email && data.customer_email.includes('@');
    if (!hasValidPhone && !hasValidEmail) return;

    const timer = setTimeout(() => {
      fetch('/checkout/ping', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        },
        body: JSON.stringify({
          ...data,
          cart_total: total,
          cart_items: itemsList
        })
      }).catch(e => console.error('Tracking ping failed', e));
    }, 2500);

    return () => clearTimeout(timer);
  }, [data.customer_name, data.customer_phone, data.customer_email, data.shipping_address, total]);

  // begin_checkout: fire once per checkout session (deduped so it doesn't double
  // with the Cart page's "Proceed to Checkout" click event)
  useEffect(() => {
    const dedupKey = 'checkout_tracked';
    if (sessionStorage.getItem(dedupKey)) return;
    sessionStorage.setItem(dedupKey, '1');
    trackInitiateCheckout(itemsList, subtotal);
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const applyCoupon = (e) => {
    e.preventDefault();
    router.post('/checkout/coupon', { code: couponForm.code }, { preserveScroll: true });
  };

  const removeCoupon = () => {
    router.post('/checkout/coupon/remove', {}, { preserveScroll: true });
  };

  const toggleItem = (key) => {
    setSelectedKeys(current => current.includes(key)
      ? current.filter(itemKey => itemKey !== key)
      : [...current, key]
    );
  };

  const toggleAll = () => {
    setSelectedKeys(allSelected ? [] : itemsList.map(item => item.key));
  };

  const removeItems = (keys) => {
    if (!keys.length || !window.confirm(`Remove ${keys.length === 1 ? 'this item' : 'these items'} from your order?`)) return;

    const endpoint = keys.length === 1 ? '/cart/remove' : '/cart/remove-many';
    const payload = keys.length === 1
      ? { key: keys[0], redirect_home_if_empty: '1' }
      : { keys, redirect_home_if_empty: '1' };

    router.post(endpoint, payload, {
      preserveScroll: true,
      onStart: () => setSelectedKeys([]),
    });
  };

  const submitOrder = (e) => {
    e.preventDefault();
    post('/checkout', { preserveScroll: true });
  };

  const hasMobileBanking = paySettings?.bkash_enabled || paySettings?.nagad_enabled || paySettings?.rocket_enabled;
  const payNumbers = {
    bkash: paySettings?.bkash_number,
    nagad: paySettings?.nagad_number,
    rocket: paySettings?.rocket_number
  };

  return (
    <StorefrontLayout>
      <Head title="Checkout" />

      {/* Header */}
      <header className="bg-white border-b border-gray-100">
        <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 h-16 lg:h-20 flex items-center justify-between">
          <Link href="/" className="flex items-center gap-2 group">
            {app?.logo_url ? (
              <img src={app.logo_url} alt={app?.name} className="h-9 w-auto max-w-[140px] object-contain" />
            ) : (
              <>
                <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-[#f15a24] to-[#d94a1a] flex items-center justify-center text-white shadow-md shadow-[#f15a24]/20 group-hover:shadow-[#f15a24]/40 transition-all">
                  <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5"><path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                </div>
                <span className="text-xl font-black text-gray-900 tracking-tight">{app?.name || 'SHARTHAK'}<span className="text-[#f15a24]">.</span></span>
              </>
            )}
            <span className="text-[10px] sm:text-xs font-semibold text-gray-400 uppercase tracking-widest ml-2 border-l border-gray-200 pl-3">Official Checkout</span>
          </Link>
          <div className="flex items-center gap-2 text-sm font-bold text-gray-500">
            <svg className="h-5 w-5 text-[#f15a24]" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg> 
            Secure Checkout
          </div>
        </div>
      </header>
      
      <main className="bg-gray-50 min-h-screen pb-16">
        <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 pt-8 pb-4">
          <ol className="flex items-center gap-3 text-sm font-bold">
            <li className="flex items-center gap-2 text-[#f15a24]">
              <span className="grid h-7 w-7 place-items-center rounded-full bg-[#f15a24] text-white text-xs shadow-md shadow-[#f15a24]/20">1</span> 
              <Link href="/cart" className="hover:underline">Cart</Link>
            </li>
            <li className="h-px w-8 bg-[#f15a24]/30"></li>
            <li className="flex items-center gap-2 text-[#f15a24]">
              <span className="grid h-7 w-7 place-items-center rounded-full bg-[#f15a24] text-white text-xs shadow-md shadow-[#f15a24]/20">2</span> 
              Delivery
            </li>
            <li className="h-px w-8 bg-gray-200"></li>
            <li className="flex items-center gap-2 text-gray-400">
              <span className="grid h-7 w-7 place-items-center rounded-full bg-gray-200 text-gray-500 text-xs">3</span> 
              Payment
            </li>
          </ol>
        </div>

        <section className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 pb-12">
          {Object.keys(errors).length > 0 && (
            <div className="mb-6 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm p-4 shadow-sm">
              <p className="font-bold flex items-center gap-2">
                <svg className="w-5 h-5 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                Please fix the following:
              </p>
              <ul className="list-disc list-inside mt-2 font-medium">
                {Object.values(errors).map((err, idx) => (
                  <li key={idx}>{err}</li>
                ))}
              </ul>
            </div>
          )}

          <div className="lg:grid lg:grid-cols-[1fr_400px] lg:gap-8 lg:items-start">
            <form onSubmit={submitOrder} className="space-y-6">
              
              <div className="rounded-2xl bg-white p-6 sm:p-8 border border-gray-100 shadow-sm">
                <h2 className="text-xl font-extrabold text-gray-900 mb-5">Delivery Information</h2>
                <div className="grid sm:grid-cols-2 gap-5">
                  <div className="sm:col-span-2">
                    <label className="block text-sm font-bold text-gray-900 mb-2">Full name</label>
                    <input 
                      required 
                      value={data.customer_name}
                      onChange={e => setData('customer_name', e.target.value)}
                      className="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm focus:border-[#f15a24] focus:ring-[#f15a24] font-medium" 
                    />
                  </div>
                  <div className="sm:col-span-2">
                    <label className="block text-sm font-bold text-gray-900 mb-2">
                      Phone number
                      <span className="text-xs font-normal text-gray-400 ml-2">BD number (01X-XXXXXXXX)</span>
                    </label>
                    <input 
                      required 
                      placeholder="e.g. 01712345678" 
                      value={data.customer_phone}
                      onChange={e => setData('customer_phone', e.target.value)}
                      className={`w-full rounded-xl border px-4 py-3 text-sm font-medium placeholder-gray-400 focus:ring-2 outline-none transition-all ${
                        errors.customer_phone
                          ? 'border-red-400 bg-red-50 focus:border-red-500 focus:ring-red-200'
                          : 'border-gray-300 bg-white focus:border-[#f15a24] focus:ring-[#f15a24]/20'
                      }`}
                    />
                    {errors.customer_phone && (
                      <div className="mt-2 flex items-start gap-2 text-red-600 text-sm bg-red-50 border border-red-200 rounded-xl px-3 py-2">
                        <svg className="w-4 h-4 mt-0.5 flex-shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span className="font-medium">{errors.customer_phone}</span>
                      </div>
                    )}
                  </div>

                  <div className="sm:col-span-2">
                    <label className="block text-sm font-bold text-gray-900 mb-2">Full Address</label>
                    <input 
                      required 
                      placeholder="House, road, area" 
                      value={data.shipping_address}
                      onChange={e => setData('shipping_address', e.target.value)}
                      className="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm focus:border-[#f15a24] focus:ring-[#f15a24] font-medium placeholder-gray-400" 
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-bold text-gray-900 mb-2">City</label>
                    <input 
                      required 
                      placeholder="e.g. Dhaka"
                      value={data.city}
                      onChange={e => setData('city', e.target.value)}
                      className="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm focus:border-[#f15a24] focus:ring-[#f15a24] font-medium placeholder-gray-400" 
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-bold text-gray-900 mb-2">Delivery zone</label>
                    <select 
                      value={data.shipping_zone}
                      onChange={e => setData('shipping_zone', e.target.value)}
                      className="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm focus:border-[#f15a24] focus:ring-[#f15a24] font-medium"
                    >
                      <option value="inside_dhaka">Inside Dhaka ({isFreeShipping || shipInside === 0 ? 'Free Delivery (৳0)' : `+${money(shipInside)}`})</option>
                      <option value="outside_dhaka">Outside Dhaka ({isFreeShipping || shipOutside === 0 ? 'Free Delivery (৳0)' : `+${money(shipOutside)}`})</option>
                    </select>
                  </div>

                  {(app?.settings?.checkout_delivery_note_enabled !== false && app?.settings?.checkout_delivery_note_enabled !== '0') && (
                    <div className="sm:col-span-2">
                      <label className="block text-sm font-bold text-gray-900 mb-2">
                        {app?.settings?.checkout_delivery_note_label || 'ডেলিভারি সংক্রান্ত বিশেষ নোট (ঐচ্ছিক)'}
                      </label>
                      <textarea
                        rows={2}
                        placeholder="যেমন: বিকাল ৫টার পর ডেলিভারি দিন বা দারোয়ানের কাছে রাখুন..."
                        value={data.delivery_note}
                        onChange={e => setData('delivery_note', e.target.value)}
                        className="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm focus:border-[#f15a24] focus:ring-[#f15a24] font-medium placeholder-gray-400 resize-none"
                      />
                    </div>
                  )}
                </div>
              </div>

              <div className="rounded-2xl bg-white p-6 sm:p-8 border border-gray-100 shadow-sm">
                <h2 className="text-xl font-extrabold text-gray-900">Payment method</h2>
                <div className="mt-5 space-y-3">
                  {paySettings?.cod_enabled && (
                    <label className={`flex items-center gap-4 rounded-xl border-2 px-4 py-4 cursor-pointer transition-colors ${data.payment_method === 'cod' ? 'border-[#f15a24] bg-[#f15a24]/5' : 'border-gray-200 hover:border-[#f15a24]/50'}`}>
                      <input 
                        type="radio" 
                        value="cod" 
                        checked={data.payment_method === 'cod'}
                        onChange={() => setData('payment_method', 'cod')}
                        className="text-[#f15a24] focus:ring-[#f15a24] border-gray-300 w-5 h-5" 
                      />
                      <span className="font-bold text-gray-900">Cash on Delivery</span>
                      <svg className="ml-auto h-6 w-6 text-[#f15a24]" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></svg>
                    </label>
                  )}
                  {paySettings?.bkash_enabled && paySettings?.bkash_number && (
                    <label className={`flex items-center gap-4 rounded-xl border-2 px-4 py-4 cursor-pointer transition-colors ${data.payment_method === 'bkash' ? 'border-[#e2136e] bg-[#e2136e]/5' : 'border-gray-200 hover:border-[#e2136e]/50'}`}>
                      <input 
                        type="radio" 
                        value="bkash" 
                        checked={data.payment_method === 'bkash'}
                        onChange={() => setData('payment_method', 'bkash')}
                        className="text-[#e2136e] focus:ring-[#e2136e] border-gray-300 w-5 h-5" 
                      />
                      <span className="font-bold text-gray-900">bKash</span>
                      <span className="ml-auto rounded-md bg-[#e2136e]/10 text-[#e2136e] text-sm font-extrabold px-3 py-1.5">{paySettings.bkash_number}</span>
                    </label>
                  )}
                  {paySettings?.nagad_enabled && paySettings?.nagad_number && (
                    <label className={`flex items-center gap-4 rounded-xl border-2 px-4 py-4 cursor-pointer transition-colors ${data.payment_method === 'nagad' ? 'border-[#f36b21] bg-[#f36b21]/5' : 'border-gray-200 hover:border-[#f36b21]/50'}`}>
                      <input 
                        type="radio" 
                        value="nagad" 
                        checked={data.payment_method === 'nagad'}
                        onChange={() => setData('payment_method', 'nagad')}
                        className="text-[#f36b21] focus:ring-[#f36b21] border-gray-300 w-5 h-5" 
                      />
                      <span className="font-bold text-gray-900">Nagad</span>
                      <span className="ml-auto rounded-md bg-[#f36b21]/10 text-[#f36b21] text-sm font-extrabold px-3 py-1.5">{paySettings.nagad_number}</span>
                    </label>
                  )}
                  {paySettings?.rocket_enabled && paySettings?.rocket_number && (
                    <label className={`flex items-center gap-4 rounded-xl border-2 px-4 py-4 cursor-pointer transition-colors ${data.payment_method === 'rocket' ? 'border-[#8c1561] bg-[#8c1561]/5' : 'border-gray-200 hover:border-[#8c1561]/50'}`}>
                      <input 
                        type="radio" 
                        value="rocket" 
                        checked={data.payment_method === 'rocket'}
                        onChange={() => setData('payment_method', 'rocket')}
                        className="text-[#8c1561] focus:ring-[#8c1561] border-gray-300 w-5 h-5" 
                      />
                      <span className="font-bold text-gray-900">Rocket</span>
                      <span className="ml-auto rounded-md bg-[#8c1561]/10 text-[#8c1561] text-sm font-extrabold px-3 py-1.5">{paySettings.rocket_number}</span>
                    </label>
                  )}
                </div>

                {!isCod && (
                  <div className="mt-5 grid sm:grid-cols-2 gap-5 p-5 bg-gray-50 rounded-xl border border-gray-100">
                    <div className="sm:col-span-2 text-sm text-gray-600 font-medium">
                      Send <b className="text-gray-900 text-base">{money(total)}</b> to <b className="text-gray-900 text-base">{payNumbers[data.payment_method] || "—"}</b>, then enter your payment details below.
                    </div>
                    <div>
                      <label className="block text-sm font-bold text-gray-900 mb-2">Your sender number</label>
                      <input 
                        required
                        value={data.payment_sender_number}
                        onChange={e => setData('payment_sender_number', e.target.value)}
                        className="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm focus:border-[#f15a24] focus:ring-[#f15a24] font-medium bg-white" 
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-bold text-gray-900 mb-2">Transaction ID</label>
                      <input 
                        required
                        value={data.payment_txn_id}
                        onChange={e => setData('payment_txn_id', e.target.value)}
                        className="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm focus:border-[#f15a24] focus:ring-[#f15a24] font-medium bg-white" 
                      />
                    </div>
                  </div>
                )}
              </div>

              <button 
                type="submit" 
                disabled={processing}
                className="w-full rounded-xl bg-gray-900 text-white font-extrabold text-lg py-5 shadow-lg shadow-gray-900/30 hover:bg-black transition-all hover:-translate-y-0.5 disabled:opacity-75 disabled:cursor-not-allowed disabled:transform-none"
              >
                <span>Place Order</span>
                <span className="mx-3 opacity-50">·</span>
                <span className="text-[#f15a24]">{money(total)}</span>
              </button>
              
              <p className="text-center text-sm font-medium text-gray-500">
                By placing your order you agree to our <Link href="/terms" className="underline hover:text-gray-900">Terms</Link> &amp; <Link href="/privacy" className="underline hover:text-gray-900">Privacy Policy</Link>.
              </p>
            </form>

            <aside className="mt-8 lg:mt-0 space-y-6">
              <div className="rounded-2xl bg-white p-6 sm:p-8 border border-gray-100 shadow-sm">
                <h2 className="text-xl font-extrabold text-gray-900">Coupon code</h2>
                {couponCode ? (
                  <div className="mt-4 flex items-center justify-between gap-3 bg-green-50 border border-green-100 rounded-xl px-5 py-4">
                    <div>
                      <p className="font-mono font-black text-green-700 text-lg">{couponCode}</p>
                      <p className="text-sm font-bold text-green-600 mt-0.5">You save {money(discount)}</p>
                    </div>
                    <button onClick={removeCoupon} type="button" className="text-sm font-extrabold text-gray-400 hover:text-red-600 transition-colors bg-white px-3 py-1.5 rounded-lg shadow-sm">Remove</button>
                  </div>
                ) : (
                  <form onSubmit={applyCoupon} className="mt-4 flex gap-3">
                    <input 
                      required
                      value={couponForm.code}
                      onChange={e => setCouponForm({ ...couponForm, code: e.target.value })}
                      placeholder="Promo code" 
                      className="flex-1 rounded-xl border-gray-200 px-4 py-3 text-sm focus:border-[#f15a24] focus:ring-[#f15a24] font-medium" 
                    />
                    <button type="submit" className="rounded-xl bg-gray-900 text-white font-bold px-6 shadow-md shadow-gray-900/20 hover:bg-black transition-colors">Apply</button>
                  </form>
                )}
              </div>

              <div className="rounded-2xl bg-white p-6 sm:p-8 border border-gray-100 shadow-sm sticky top-8">
                <div className="flex items-center justify-between gap-3">
                  <h2 className="text-xl font-extrabold text-gray-900">Your order</h2>
                  <label className="flex items-center gap-2 text-xs font-semibold text-gray-500 cursor-pointer">
                    <input type="checkbox" checked={allSelected} onChange={toggleAll} className="h-4 w-4 rounded border-gray-300 accent-orange-500" />
                    Select all
                  </label>
                </div>
                {selectedKeys.length > 0 && (
                  <button
                    type="button"
                    onClick={() => removeItems(selectedKeys)}
                    className="mt-3 inline-flex items-center gap-1.5 text-xs font-bold text-red-600 hover:text-red-700"
                  >
                    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2L17 7M9 7V4h6v3" /></svg>
                    Remove selected ({selectedKeys.length})
                  </button>
                )}
                <div className="mt-6 space-y-5">
                  {itemsList.map(item => (
                    <div key={item.key} className="flex gap-3 items-center">
                      <input
                        type="checkbox"
                        checked={selectedKeys.includes(item.key)}
                        onChange={() => toggleItem(item.key)}
                        className="h-4 w-4 shrink-0 rounded border-gray-300 accent-orange-500"
                        aria-label={`Select ${item.name}`}
                      />
                      <div className="relative shrink-0">
                        <img src={imageUrl(item.image, item.name)} className="h-16 w-16 rounded-xl object-contain border border-gray-100 bg-gray-50 p-1" alt={item.name} />
                        <span className="absolute -top-2 -right-2 grid h-6 w-6 place-items-center rounded-full bg-gray-900 text-white text-xs font-black shadow-md shadow-gray-900/20">
                          {item.qty}
                        </span>
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="text-sm font-bold text-gray-900 truncate leading-snug">{item.name}</p>
                        {item.variant && <p className="text-xs font-medium text-gray-500 mt-1">{item.variant}</p>}
                      </div>
                      <span className="text-sm font-black text-gray-900">{money(item.line_total)}</span>
                      <button
                        type="button"
                        onClick={() => removeItems([item.key])}
                        className="shrink-0 rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 transition-colors"
                        aria-label={`Remove ${item.name}`}
                        title="Remove item"
                      >
                        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2L17 7M9 7V4h6v3" /></svg>
                      </button>
                    </div>
                  ))}
                </div>
                
                <div className="mt-6 space-y-3 text-sm border-t border-gray-100 pt-6">
                  <div className="flex justify-between font-medium">
                    <span className="text-gray-500">Subtotal</span>
                    <span className="text-gray-900 font-bold">{money(subtotal)}</span>
                  </div>
                  {discount > 0 && (
                    <div className="flex justify-between font-medium text-green-600">
                      <span>Discount ({couponCode})</span>
                      <span className="font-bold">−{money(discount)}</span>
                    </div>
                  )}
                  <div className="flex justify-between font-medium items-center">
                    <span className="text-gray-500">Delivery</span>
                    {fee === 0 || isFreeShipping ? (
                      <span className="font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded text-xs">
                        Free (৳0)
                      </span>
                    ) : (
                      <span className="text-gray-900 font-bold">{money(fee)}</span>
                    )}
                  </div>
                  {tax > 0 && (
                    <div className="flex justify-between font-medium">
                      <span className="text-gray-500">Tax ({taxPercent}%)</span>
                      <span className="text-gray-900 font-bold">{money(tax)}</span>
                    </div>
                  )}
                </div>

                <div className="mt-5 pt-5 border-t border-gray-100 flex flex-col gap-2">
                  <div className="flex items-center justify-between">
                    <span className="font-extrabold text-gray-900 text-lg">Total</span>
                    <span className="text-3xl font-black text-[#f15a24]">{money(total)}</span>
                  </div>
                  {isCod && (
                    <p className="text-sm font-bold text-gray-500 text-right">
                      Payable upon delivery
                    </p>
                  )}
                </div>
              </div>
            </aside>
          </div>
        </section>
      </main>
    </StorefrontLayout>
  );
}
