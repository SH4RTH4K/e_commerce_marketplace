import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { Head, Link } from '@inertiajs/react';
import { imageUrl } from '@/lib/utils';

export default function AccountShow({ order }) {
  if (!order) return null;

  return (
    <StorefrontLayout>
      <Head title={`Order #${order.order_number}`} />
      <div className="max-w-4xl mx-auto px-4 py-8 md:py-12">
        <Link href="/account?tab=orders" className="inline-flex items-center gap-2 text-sm font-semibold text-gray-500 hover:text-gray-900 mb-6">
          <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
          Back to Orders
        </Link>
        
        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <div className="bg-gray-50 p-6 border-b border-gray-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
              <h1 className="text-xl font-bold text-gray-900">Order #{order.order_number}</h1>
              <p className="text-sm text-gray-500 mt-1">Placed on {new Date(order.created_at).toLocaleString()}</p>
            </div>
            <div className="flex items-center gap-3">
              <a
                href={`/order/${order.order_number}/invoice`}
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white border border-gray-200 text-xs font-bold text-gray-700 hover:bg-gray-50 transition-colors shadow-sm"
              >
                <svg className="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                প্রিন্ট / ডাউনলোড ইনভয়েস
              </a>
              <span className={`px-3 py-2 rounded-xl text-xs font-bold uppercase tracking-wider
                ${order.status === 'delivered' ? 'bg-green-100 text-green-700' : 
                  order.status === 'cancelled' ? 'bg-red-100 text-red-700' : 
                  'bg-yellow-100 text-yellow-700'}
              `}>
                {order.status}
              </span>
            </div>
          </div>

          <div className="p-6 md:p-8 grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
              <h3 className="text-sm font-bold text-gray-900 mb-3 uppercase tracking-wide">Shipping Information</h3>
              <div className="text-sm text-gray-600 space-y-1">
                <p className="font-bold text-gray-800">{order.customer_name}</p>
                <p>{order.customer_phone}</p>
                {order.customer_email && <p>{order.customer_email}</p>}
                <p>{order.shipping_address}</p>
                {order.city && <p>{order.city} {order.shipping_zone ? `(${order.shipping_zone.replace('_', ' ')})` : ''}</p>}
                {order.delivery_note && (
                  <div className="mt-3 p-3 bg-amber-50 rounded-xl border border-amber-100 text-amber-950">
                    <p className="font-bold text-xs text-amber-800 mb-1 flex items-center gap-1">
                      <span>📝</span> Delivery Note (ডেলিভারি নোট):
                    </p>
                    <p className="text-xs font-medium whitespace-pre-wrap">{order.delivery_note}</p>
                  </div>
                )}
              </div>
            </div>
            <div>
               <h3 className="text-sm font-bold text-gray-900 mb-3 uppercase tracking-wide">Payment Summary</h3>
               <div className="text-sm text-gray-600 space-y-2">
                 <div className="flex justify-between">
                   <span>Payment Method</span>
                   <span className="font-semibold text-gray-900 uppercase text-xs bg-gray-100 px-2 py-0.5 rounded">
                     {order.payment_method_label || order.payment_method}
                   </span>
                 </div>
                 {order.payment_txn_id && (
                   <div className="flex justify-between">
                     <span>TrxID</span>
                     <span className="font-mono font-bold text-gray-900 text-xs">{order.payment_txn_id}</span>
                   </div>
                 )}
                 <div className="flex justify-between">
                   <span>Subtotal</span>
                   <span>৳{Number(order.subtotal || 0).toLocaleString()}</span>
                 </div>
                 <div className="flex justify-between">
                   <span>Shipping Charge</span>
                   <span>
                     {Number(order.shipping_charge || 0) === 0 ? (
                       <span className="text-green-600 font-semibold">Free</span>
                     ) : (
                       `৳${Number(order.shipping_charge).toLocaleString()}`
                     )}
                   </span>
                 </div>
                 {Number(order.discount_amount || 0) > 0 && (
                   <div className="flex justify-between text-green-600 font-medium">
                     <span>Discount {order.coupon_code ? `(${order.coupon_code})` : ''}</span>
                     <span>-৳{Number(order.discount_amount).toLocaleString()}</span>
                   </div>
                 )}
                 {Number(order.tax || 0) > 0 && (
                   <div className="flex justify-between text-gray-600">
                     <span>Tax</span>
                     <span>৳{Number(order.tax).toLocaleString()}</span>
                   </div>
                 )}
                 <hr className="border-gray-100 my-2" />
                 <div className="flex justify-between text-base font-bold text-gray-900">
                   <span>Total Amount</span>
                   <span className="text-[#f15a24]">৳{Number(order.total).toLocaleString()}</span>
                 </div>
               </div>
            </div>
          </div>

          <div className="border-t border-gray-100 p-6 md:p-8">
            <h3 className="text-base font-bold text-gray-900 mb-6">Items in this order</h3>
            <div className="space-y-4">
              {order.items?.map(item => {
                const thumb = item.image_url || (item.image ? imageUrl(item.image, item.product_name) : (item.product?.images?.[0]?.path ? imageUrl(item.product.images[0].path, item.product_name) : null));
                return (
                  <div key={item.id} className="flex items-center gap-4 p-4 rounded-xl border border-gray-100">
                    <div className="w-16 h-16 rounded-xl bg-gray-50 flex items-center justify-center shrink-0 border border-gray-100 overflow-hidden">
                       {thumb ? (
                          <img src={thumb} className="w-full h-full object-cover" alt={item.product_name} />
                       ) : (
                          <span className="text-2xl">📦</span>
                       )}
                    </div>
                    <div className="flex-1 min-w-0">
                      <Link href={`/product/${item.product?.slug || ''}`} className="text-sm font-bold text-gray-800 hover:text-[#f15a24] truncate block">
                        {item.product_name}
                      </Link>
                      {item.variant && <p className="text-xs text-gray-500 mt-0.5">{item.variant}</p>}
                      <p className="text-xs text-gray-500 mt-1">Qty: {item.quantity} × ৳{Number(item.unit_price).toLocaleString()}</p>
                    </div>
                    <div className="font-bold text-sm text-gray-900 text-right">
                      ৳{Number(item.line_total || item.quantity * item.unit_price).toLocaleString()}
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </div>
      </div>
    </StorefrontLayout>
  );
}