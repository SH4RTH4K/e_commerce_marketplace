import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { Head, usePage, useForm } from '@inertiajs/react';
import { imageUrl } from '@/lib/utils';

export default function TrackPage({ order }) {
  const { app } = usePage().props;
  const { data, setData, post, processing, errors } = useForm({
    order_number: '',
    phone: '',
  });

  const submit = (e) => {
    e.preventDefault();
    post('/track');
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'pending': return 'bg-yellow-500';
      case 'confirmed': return 'bg-cyan-500';
      case 'processing': return 'bg-blue-500';
      case 'shipped': return 'bg-indigo-500';
      case 'delivered': return 'bg-green-500';
      case 'cancelled': return 'bg-red-500';
      default: return 'bg-gray-300';
    }
  };

  const steps = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
  const currentStepIndex = order ? steps.indexOf(order.status) : -1;

  const getItemImage = (item) => {
    if (item.image_url) return item.image_url;
    if (item.image) return imageUrl(item.image, item.product_name);
    if (item.product?.images?.[0]?.path) return imageUrl(item.product.images[0].path, item.product_name);
    return null;
  };

  return (
    <StorefrontLayout>
      <Head title="Track Order" />
      <div className="max-w-3xl mx-auto px-4 py-12">
        <div className="text-center mb-8">
          <h1 className="text-3xl font-extrabold text-gray-900 tracking-tight">Track Your Order</h1>
          <p className="mt-2 text-gray-500">Enter your order details below to check the current status.</p>
        </div>

        {!order ? (
          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8 max-w-lg mx-auto">
            <form onSubmit={submit} className="space-y-5">
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-1">Order Number</label>
                <input
                  type="text"
                  value={data.order_number}
                  onChange={e => setData('order_number', e.target.value)}
                  className="w-full h-11 px-4 rounded-xl border border-gray-200 focus:border-[#f15a24] focus:ring focus:ring-[#f15a24]/20 transition-all outline-none"
                  placeholder="e.g. ORD-123456"
                  required
                />
                {errors.order_number && <p className="mt-1 text-sm text-red-500">{errors.order_number}</p>}
              </div>
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-1">Phone Number</label>
                <input
                  type="text"
                  value={data.phone}
                  onChange={e => setData('phone', e.target.value)}
                  className="w-full h-11 px-4 rounded-xl border border-gray-200 focus:border-[#f15a24] focus:ring focus:ring-[#f15a24]/20 transition-all outline-none"
                  placeholder="Billing Phone Number"
                  required
                />
                {errors.phone && <p className="mt-1 text-sm text-red-500">{errors.phone}</p>}
              </div>
              <button
                type="submit"
                disabled={processing}
                className="w-full h-11 bg-[#f15a24] hover:bg-[#d94b1a] text-white rounded-xl font-bold transition-colors disabled:opacity-70"
              >
                {processing ? 'Searching...' : 'Track Order'}
              </button>
            </form>
          </div>
        ) : (
          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div className="bg-gray-50 p-6 border-b border-gray-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
              <div>
                <p className="text-sm text-gray-500 font-semibold mb-1">Order <span className="text-gray-900 font-bold">#{order.order_number}</span></p>
                <p className="text-xs text-gray-400">Placed on {new Date(order.created_at).toLocaleDateString()}</p>
              </div>
              <div className="flex items-center gap-2">
                <span className={`w-2.5 h-2.5 rounded-full ${getStatusColor(order.status)}`}></span>
                <span className="text-sm font-bold text-gray-700 capitalize">{order.status}</span>
              </div>
            </div>

            {/* Tracking Timeline */}
            {order.status !== 'cancelled' && (
              <div className="p-8 border-b border-gray-100">
                <div className="relative flex justify-between items-center max-w-lg mx-auto">
                  <div className="absolute left-0 top-1/2 -translate-y-1/2 w-full h-1 bg-gray-200 rounded-full z-0"></div>
                  
                  <div className="absolute left-0 top-1/2 -translate-y-1/2 h-1 bg-[#f15a24] rounded-full z-0 transition-all duration-500" 
                       style={{ width: `${(Math.max(0, currentStepIndex) / (steps.length - 1)) * 100}%` }}></div>

                  {steps.map((step, index) => {
                    const isCompleted = index <= currentStepIndex;
                    const isActive = index === currentStepIndex;
                    return (
                      <div key={step} className="relative z-10 flex flex-col items-center gap-2">
                        <div className={`w-8 h-8 rounded-full flex items-center justify-center border-2 bg-white transition-colors
                          ${isCompleted ? 'border-[#f15a24] text-[#f15a24]' : 'border-gray-200 text-gray-300'}
                          ${isActive ? 'ring-4 ring-[#f15a24]/20' : ''}
                        `}>
                          {isCompleted ? (
                             <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="3" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7"/></svg>
                          ) : (
                             <div className="w-2.5 h-2.5 rounded-full bg-gray-200"></div>
                          )}
                        </div>
                        <span className={`text-[11px] font-bold uppercase tracking-wide absolute top-10 whitespace-nowrap ${isCompleted ? 'text-gray-800' : 'text-gray-400'}`}>
                          {step}
                        </span>
                      </div>
                    );
                  })}
                </div>
              </div>
            )}
            
            {order.status === 'cancelled' && (
               <div className="p-8 border-b border-gray-100 text-center text-red-500 font-bold text-lg">
                  This order has been cancelled.
               </div>
            )}

            <div className="p-6">
              <h3 className="text-sm font-bold text-gray-900 mb-4">Order Summary</h3>
              <div className="space-y-4">
                {order.items?.map(item => {
                  const thumb = getItemImage(item);
                  return (
                    <div key={item.id} className="flex items-center gap-4">
                      <div className="w-16 h-16 rounded-xl bg-gray-50 flex items-center justify-center shrink-0 border border-gray-100 overflow-hidden">
                        {thumb ? (
                          <img src={thumb} className="w-full h-full object-cover" alt={item.product_name} />
                        ) : (
                          <span className="text-2xl">📦</span>
                        )}
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="text-sm font-bold text-gray-800 truncate">{item.product_name}</p>
                        {item.variant && <p className="text-xs text-gray-500">{item.variant}</p>}
                        <p className="text-xs text-gray-500 mt-1">Qty: {item.quantity} × ৳{Number(item.unit_price).toLocaleString()}</p>
                      </div>
                      <div className="font-bold text-sm text-gray-900">
                        ৳{Number(item.line_total || item.quantity * item.unit_price).toLocaleString()}
                      </div>
                    </div>
                  );
                })}
              </div>

              <hr className="my-6 border-gray-100" />
              
              <div className="space-y-2 text-sm text-gray-600">
                <div className="flex justify-between">
                  <span>Subtotal</span>
                  <span className="font-semibold text-gray-900">৳{Number(order.subtotal || 0).toLocaleString()}</span>
                </div>
                {Number(order.discount_amount || 0) > 0 && (
                  <div className="flex justify-between text-green-600 font-medium">
                    <span>Discount {order.coupon_code ? `(${order.coupon_code})` : ''}</span>
                    <span>-৳{Number(order.discount_amount).toLocaleString()}</span>
                  </div>
                )}
                <div className="flex justify-between">
                  <span>Delivery Fee</span>
                  <span className="font-semibold text-gray-900">
                    {Number(order.shipping_charge || 0) === 0 ? 'Free' : `৳${Number(order.shipping_charge).toLocaleString()}`}
                  </span>
                </div>
                <hr className="my-2 border-gray-100" />
                <div className="flex justify-between items-center font-extrabold text-lg text-gray-900">
                  <span>Total</span>
                  <span className="text-[#f15a24]">৳{Number(order.total).toLocaleString()}</span>
                </div>
              </div>
            </div>
            
            <div className="bg-gray-50 p-6 border-t border-gray-100 text-center">
               <button onClick={() => window.location.href = '/track'} className="text-sm font-semibold text-gray-500 hover:text-gray-800 transition-colors">
                 Track Another Order
               </button>
            </div>
          </div>
        )}
      </div>
    </StorefrontLayout>
  );
}
