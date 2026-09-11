import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { money } from '@/lib/utils';
import { useEffect, useState } from 'react';
import Confetti from 'react-confetti';
import { useWindowSize } from 'react-use';
import { trackPurchase } from '@/lib/tracking';

export default function OrderConfirmationPage({ order, trackingOrder }) {
  const { app } = usePage().props;
  const showDeliveryUpfront = app.settings?.cod_delivery_upfront !== false;

  const isCod = order.payment_method === 'cod';
  const isMobileBanking = ['bkash', 'nagad', 'rocket'].includes(order.payment_method);
  
  const paymentMethodLabel = isCod 
    ? 'Cash on Delivery' 
    : order.payment_method === 'bkash' ? 'bKash' 
    : order.payment_method === 'nagad' ? 'Nagad' 
    : order.payment_method === 'rocket' ? 'Rocket' 
    : order.payment_method;

  const shippingZoneLabel = order.shipping_zone === 'inside_dhaka' ? 'Inside Dhaka' : 'Outside Dhaka';
  const { width, height } = useWindowSize();
  const [showConfetti, setShowConfetti] = useState(true);

  useEffect(() => {
    const timer = setTimeout(() => setShowConfetti(false), 6000);
    return () => clearTimeout(timer);
  }, []);

  // Fire purchase event once — trackPurchase() deduplicates via sessionStorage
  useEffect(() => {
    if (trackingOrder) {
      trackPurchase(trackingOrder);
    }
  }, [trackingOrder]);

  return (
    <StorefrontLayout>
      <Head title="Order Confirmed" />
      
      {showConfetti && (
        <div className="fixed inset-0 z-50 pointer-events-none">
          <Confetti width={width} height={height} numberOfPieces={400} recycle={false} />
        </div>
      )}

      <section className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-14 relative z-10">
        <div className="text-center">
          <div className="mx-auto grid h-20 w-20 place-items-center rounded-full bg-green-100 text-green-600 shadow-sm">
            <svg className="h-10 w-10" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h1 className="mt-6 text-3xl sm:text-4xl font-black text-gray-900 tracking-tight">আপনার অর্ডারের জন্য ধন্যবাদ!</h1>
          
          {isCod ? (
            <p className="mt-3 text-lg text-gray-600 font-medium">
              আপনার অর্ডার <b className="text-gray-900">{order.order_number}</b> গ্রহণ করা হয়েছে। প্রোডাক্ট ডেলিভারি নেওয়ার সময় পেমেন্ট করবেন।
            </p>
          ) : (
            <>
              <p className="mt-3 text-lg text-gray-600 font-medium">
                আপনার অর্ডার <b className="text-gray-900">{order.order_number}</b> গ্রহণ করা হয়েছে এবং বর্তমানে <b className="text-orange-600">ভেরিফিকেশনের জন্য অপেক্ষমান</b>।
              </p>
              {isMobileBanking && (
                <p className="mt-2 text-sm text-gray-500 font-medium">আমরা শীঘ্রই আপনার {paymentMethodLabel} পেমেন্ট ভেরিফাই করবো।</p>
              )}
            </>
          )}

          <div className="mt-8 bg-blue-50/80 border border-blue-100 rounded-2xl p-6 text-left max-w-2xl mx-auto shadow-sm">
            <h3 className="font-bold text-blue-900 text-lg mb-2 flex items-center gap-2">
              <svg className="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              এরপর কী হবে?
            </h3>
            <p className="text-blue-800/80 leading-relaxed text-sm md:text-base font-medium">
              আমাদের একজন প্রতিনিধি শীঘ্রই আপনার অর্ডারটি কনফার্ম করার জন্য কল করবেন। 
              কনফার্ম হওয়ার পর, আমরা কুরিয়ারের মাধ্যমে আপনার প্রোডাক্টটি পাঠিয়ে দিবো। 
              কোনো জরুরি প্রশ্ন থাকলে আমাদের সাপোর্ট টিমে কল করতে পারেন।
            </p>
          </div>
        </div>

        <div className="mt-12 rounded-2xl border border-gray-100 bg-white shadow-sm overflow-hidden">
          <div className="p-6 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
            <h2 className="text-xl font-extrabold text-gray-900">Order summary</h2>
            <span className={`text-xs px-3 py-1.5 rounded-full font-black uppercase tracking-wider ${
              isCod ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'
            }`}>
              {order.status}
            </span>
          </div>
          
          <div className="divide-y divide-gray-100">
            {order.items.map(item => (
              <div key={item.id} className="flex items-center gap-5 p-6">
                <img src={item.image_url || ''} className="h-16 w-16 object-contain bg-gray-50 p-1 border border-gray-100 rounded-xl" alt={item.product_name} />
                <div className="flex-1">
                  <p className="font-bold text-gray-900 text-base">{item.product_name}</p>
                  <p className="text-sm font-medium text-gray-500 mt-1">
                    {item.variant && `${item.variant} · `}{money(item.unit_price)} × {item.quantity}
                  </p>
                </div>
                <div className="font-black text-gray-900">{money(item.line_total)}</div>
              </div>
            ))}
          </div>

          <div className="p-6 border-t border-gray-100 space-y-3 bg-gray-50/50">
            <div className="flex justify-between text-gray-600 font-medium">
              <span>Subtotal</span>
              <span className="text-gray-900 font-bold">{money(order.subtotal)}</span>
            </div>
            
            {order.discount_amount > 0 && (
              <div className="flex justify-between text-green-600 font-medium">
                <span>Coupon {order.coupon_code && `(${order.coupon_code})`}</span>
                <span className="font-bold">−{money(order.discount_amount)}</span>
              </div>
            )}
            
            <div className="flex justify-between text-gray-600 font-medium">
              <span>Shipping ({shippingZoneLabel})</span>
              <span className="text-gray-900 font-bold">{money(order.shipping_charge)}</span>
            </div>
            
            {order.tax > 0 && (
              <div className="flex justify-between text-gray-600 font-medium">
                <span>Tax</span>
                <span className="text-gray-900 font-bold">{money(order.tax)}</span>
              </div>
            )}

            {isCod && showDeliveryUpfront && (
              <>
                <div className="flex justify-between font-bold text-gray-900 pt-4 border-t border-gray-200 mt-4">
                  <span>Pay now <span className="text-gray-500 font-medium text-xs ml-1">(delivery)</span></span>
                  <span className="text-[#f15a24]">{money(order.shipping_charge)}</span>
                </div>
                <div className="flex justify-between font-bold text-orange-900 pt-3">
                  <span>Pay after delivery</span>
                  <span>{money(Math.max(0, order.total - order.shipping_charge))}</span>
                </div>
              </>
            )}

            <div className="flex justify-between font-black text-lg pt-4 border-t border-gray-200 mt-4">
              <span className="text-gray-900">Order total</span>
              <span className="text-[#f15a24] text-xl">{money(order.total)}</span>
            </div>
          </div>
        </div>

        <div className="mt-8 grid sm:grid-cols-2 gap-6">
          <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <h3 className="font-bold text-sm text-gray-400 uppercase tracking-wider mb-4">Delivery to</h3>
            <p className="font-extrabold text-gray-900 text-lg">{order.customer_name}</p>
            <p className="text-gray-600 font-medium mt-1">{order.customer_phone}</p>
            <p className="text-gray-600 font-medium mt-1 leading-relaxed">
              {order.shipping_address}<br />
              {order.city} {order.postal_code}
            </p>
          </div>
          
          <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <h3 className="font-bold text-sm text-gray-400 uppercase tracking-wider mb-4">Payment</h3>
            <p className="font-extrabold text-gray-900 text-lg">{paymentMethodLabel}</p>
            
            {isCod ? (
              <div className="mt-2 space-y-1">
                {showDeliveryUpfront ? (
                  <>
                    <p className="text-gray-600 font-medium">Pay now: <span className="font-bold text-gray-900">{money(order.shipping_charge)}</span> (delivery)</p>
                    <p className="text-gray-600 font-medium">Pay after delivery: <span className="font-bold text-gray-900">{money(Math.max(0, order.total - order.shipping_charge))}</span></p>
                  </>
                ) : (
                  <p className="text-gray-600 font-medium">সম্পূর্ণ <span className="font-bold text-gray-900">{money(order.total)}</span> ডেলিভারির সময় পরিশোধ করবেন।</p>
                )}
              </div>
            ) : isMobileBanking ? (
              <div className="mt-2 space-y-1">
                <p className="text-gray-600 font-medium">Sender: <span className="font-bold text-gray-900">{order.payment_sender_number}</span></p>
                <p className="text-gray-600 font-medium">Txn: <span className="font-bold text-gray-900">{order.payment_txn_id}</span></p>
              </div>
            ) : null}
            
            <p className="text-gray-600 font-medium mt-4 pt-4 border-t border-gray-100">
              Status: <span className={`font-black uppercase tracking-wide text-sm ${
                order.payment_status === 'verified' ? 'text-green-600' : 'text-orange-600'
              }`}>{order.payment_status}</span>
            </p>
          </div>
        </div>

        <div className="mt-10 flex flex-col sm:flex-row items-center justify-center gap-3">
          <a
            href={`/order/${order.order_number}/invoice?token=${order.confirmation_token}`}
            target="_blank"
            rel="noreferrer"
            className="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-white border-2 border-gray-200 text-gray-800 font-extrabold px-7 py-3.5 shadow-sm hover:bg-gray-50 transition-colors"
          >
            <svg className="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
              <path strokeLinecap="round" strokeLinejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            প্রিন্ট / ডাউনলোড ইনভয়েস
          </a>

          <Link href="/shop" className="w-full sm:w-auto inline-flex items-center justify-center rounded-xl bg-gray-900 text-white font-extrabold px-8 py-3.5 shadow-md shadow-gray-900/20 hover:bg-black transition-transform hover:-translate-y-0.5">
            Continue Shopping
          </Link>
        </div>
      </section>
    </StorefrontLayout>
  );
}