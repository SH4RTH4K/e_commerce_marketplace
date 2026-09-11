import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { money, imageUrl } from '@/lib/utils';
import { useState } from 'react';
import { trackInitiateCheckout } from '@/lib/tracking';

export default function CartPage({ items, subtotal }) {
  const { app } = usePage().props;
  const isTemplateOne = app?.settings?.storefront_template === 'template-1';
  const [updatingKey, setUpdatingKey] = useState(null);

  const handleUpdate = (key, qty) => {
    setUpdatingKey(key);
    router.post('/cart/update', { key, qty }, {
      preserveScroll: true,
      onFinish: () => setUpdatingKey(null)
    });
  };

  const handleRemove = (key) => {
    if (confirm('Are you sure you want to remove this item?')) {
      router.post('/cart/remove', { key }, { preserveScroll: true });
    }
  };

  const itemsList = Object.values(items || {});
  const isEmpty = itemsList.length === 0;

  if (isTemplateOne) {
    return (
      <StorefrontLayout>
        <Head title="Shopping Cart" />
        <div className="template-1-page-title"><h1>Shopping Cart</h1></div>
        <main className="template-1-cart-page">
          {isEmpty ? (
            <div className="template-1-empty"><h2>Your cart is empty</h2><p>There are no products in your shopping cart.</p><Link href="/shop" className="template-1-primary-button">Continue Shopping</Link></div>
          ) : (
            <div className="template-1-cart-grid">
              <div>
                <table className="template-1-cart-table">
                  <thead><tr><th>Product</th><th>Price</th><th>Quantity</th><th>Total</th><th aria-label="Remove" /></tr></thead>
                  <tbody>
                    {itemsList.map(item => (
                      <tr key={item.key}>
                        <td><div className="template-1-cart-product"><Link href={`/product/${item.slug}`}><img src={imageUrl(item.image, item.name)} alt={item.name} /></Link><div><Link href={`/product/${item.slug}`}>{item.name}</Link>{item.variant && <small>{item.variant}</small>}</div></div></td>
                        <td>{money(item.price)}</td>
                        <td><div className="template-1-qty"><button type="button" onClick={() => handleUpdate(item.key, Math.max(0, item.qty - 1))} aria-label="Decrease quantity">-</button><output>{item.qty}</output><button type="button" onClick={() => handleUpdate(item.key, item.qty + 1)} aria-label="Increase quantity">+</button></div></td>
                        <td>{money(item.line_total)}</td>
                        <td><button type="button" className="template-1-cart-remove" onClick={() => handleRemove(item.key)} aria-label={`Remove ${item.name}`}>&times;</button></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
                <Link href="/shop" className="template-1-cart-continue">&larr; Continue Shopping</Link>
              </div>
              <aside className="template-1-cart-total">
                <h2>Cart Totals</h2>
                <div className="template-1-cart-total-row"><span>Subtotal</span><strong>{money(subtotal)}</strong></div>
                <div className="template-1-cart-total-row"><span>Shipping</span><span>Calculated at checkout</span></div>
                <div className="template-1-cart-total-row template-1-cart-grand-total"><strong>Total</strong><strong>{money(subtotal)}</strong></div>
                <Link href="/checkout" className="template-1-primary-button" style={{ width: '100%', marginTop: '20px' }}>Proceed to Checkout</Link>
              </aside>
            </div>
          )}
        </main>
      </StorefrontLayout>
    );
  }

  return (
    <StorefrontLayout>
      <Head title="Shopping Cart" />
      
      <main className="max-w-[1440px] mx-auto px-4 sm:px-5 py-6 sm:py-10">
        <div className="flex items-center justify-between mb-6 sm:mb-8">
          <h1 className="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">Shopping Cart</h1>
          {!isEmpty && (
            <span className="text-sm font-semibold text-gray-500 bg-gray-100 px-3 py-1 rounded-full">
              {itemsList.reduce((acc, item) => acc + item.qty, 0)} Items
            </span>
          )}
        </div>

        {isEmpty ? (
          <div className="bg-white rounded-2xl border border-dashed border-gray-200 p-16 sm:p-24 text-center flex flex-col items-center shadow-sm">
            <div className="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mb-5">
              <svg className="w-10 h-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1.5">
                <path strokeLinecap="round" strokeLinejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
              </svg>
            </div>
            <h2 className="text-xl font-bold text-gray-900 mb-2">Your cart is empty</h2>
            <p className="text-gray-500 mb-8 max-w-sm mx-auto">Looks like you haven't added anything to your cart yet.</p>
            <Link href="/shop" className="inline-flex items-center justify-center bg-[#f15a24] hover:bg-[#d94a1a] text-white font-bold px-8 py-3.5 rounded-xl transition-all shadow-md shadow-[#f15a24]/20 hover:-translate-y-0.5">
              Start Shopping
            </Link>
          </div>
        ) : (
          <div className="grid lg:grid-cols-12 gap-6 lg:gap-8">
            <div className="lg:col-span-8">
              <div className="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
                <div className="hidden sm:grid grid-cols-12 gap-4 p-4 bg-gray-50/50 border-b border-gray-100 text-xs font-bold text-gray-500 uppercase tracking-wider">
                  <div className="col-span-6">Product</div>
                  <div className="col-span-3 text-center">Quantity</div>
                  <div className="col-span-2 text-right">Total</div>
                  <div className="col-span-1"></div>
                </div>
                
                <div className="divide-y divide-gray-50">
                  {itemsList.map(item => (
                    <article key={item.key} className={`p-4 sm:p-5 transition-opacity ${updatingKey === item.key ? 'opacity-50' : ''}`}>
                      {/* Mobile Layout */}
                      <div className="sm:hidden">
                        <div className="flex gap-4">
                          <Link href={`/product/${item.slug}`} className="shrink-0 group">
                            <div className="w-20 h-20 rounded-xl overflow-hidden bg-gray-50 border border-gray-100">
                              <img src={imageUrl(item.image, item.name)} className="w-full h-full object-contain p-1 group-hover:scale-105 transition-transform" alt={item.name} />
                            </div>
                          </Link>
                          <div className="min-w-0 flex-1">
                            <div className="flex items-start justify-between gap-2">
                              <Link href={`/product/${item.slug}`} className="font-bold text-sm text-gray-900 leading-snug hover:text-[#f15a24] line-clamp-2 transition-colors">
                                {item.name}
                              </Link>
                              <button onClick={() => handleRemove(item.key)} className="p-1.5 -mr-1.5 -mt-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors" aria-label="Remove">
                                <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2 -2l1 -12M9 7V4h6v3"/></svg>
                              </button>
                            </div>
                            {item.variant && (
                              <p className="text-xs font-medium text-gray-500 mt-1 bg-gray-100 inline-block px-2 py-0.5 rounded-md truncate max-w-full">
                                {item.variant}
                              </p>
                            )}
                            <p className="text-[#f15a24] font-black text-sm mt-1.5">{money(item.price)}</p>
                          </div>
                        </div>
                        <div className="mt-4 flex items-center justify-between gap-3">
                          <div className="flex items-center border-2 border-gray-100 rounded-xl overflow-hidden bg-gray-50 h-10 w-28">
                            <button onClick={() => handleUpdate(item.key, Math.max(0, item.qty - 1))} className="w-8 h-full flex items-center justify-center text-gray-500 hover:bg-gray-200 hover:text-gray-900 transition-colors" aria-label="Decrease">−</button>
                            <span className="flex-1 text-center text-sm font-bold text-gray-900">{item.qty}</span>
                            <button onClick={() => handleUpdate(item.key, item.qty + 1)} className="w-8 h-full flex items-center justify-center text-gray-500 hover:bg-gray-200 hover:text-gray-900 transition-colors" aria-label="Increase">+</button>
                          </div>
                          <div className="font-black text-base text-gray-900">{money(item.line_total)}</div>
                        </div>
                      </div>

                      {/* Desktop Layout */}
                      <div className="hidden sm:grid grid-cols-12 gap-4 items-center">
                        <div className="col-span-6 flex items-center gap-4">
                          <Link href={`/product/${item.slug}`} className="shrink-0 group">
                            <div className="w-20 h-20 rounded-xl overflow-hidden bg-gray-50 border border-gray-100">
                              <img src={imageUrl(item.image, item.name)} className="w-full h-full object-contain p-1 group-hover:scale-105 transition-transform" alt={item.name} />
                            </div>
                          </Link>
                          <div className="min-w-0">
                            <Link href={`/product/${item.slug}`} className="font-bold text-gray-900 hover:text-[#f15a24] transition-colors line-clamp-2">
                              {item.name}
                            </Link>
                            {item.variant && (
                              <p className="text-xs font-medium text-gray-500 mt-1.5 bg-gray-100 inline-block px-2 py-0.5 rounded-md truncate max-w-full">
                                {item.variant}
                              </p>
                            )}
                            <p className="text-[#f15a24] font-black mt-1">{money(item.price)}</p>
                          </div>
                        </div>
                        
                        <div className="col-span-3 flex justify-center">
                          <div className="flex items-center border-2 border-gray-100 rounded-xl overflow-hidden bg-gray-50 h-10 w-28">
                            <button onClick={() => handleUpdate(item.key, Math.max(0, item.qty - 1))} className="w-8 h-full flex items-center justify-center text-gray-500 hover:bg-gray-200 hover:text-gray-900 transition-colors" aria-label="Decrease">−</button>
                            <span className="flex-1 text-center text-sm font-bold text-gray-900">{item.qty}</span>
                            <button onClick={() => handleUpdate(item.key, item.qty + 1)} className="w-8 h-full flex items-center justify-center text-gray-500 hover:bg-gray-200 hover:text-gray-900 transition-colors" aria-label="Increase">+</button>
                          </div>
                        </div>
                        
                        <div className="col-span-2 text-right font-black text-gray-900 text-base">
                          {money(item.line_total)}
                        </div>
                        
                        <div className="col-span-1 flex justify-end">
                          <button onClick={() => handleRemove(item.key)} className="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-xl transition-colors" aria-label="Remove">
                            <svg className="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2 -2l1 -12M9 7V4h6v3"/></svg>
                          </button>
                        </div>
                      </div>
                    </article>
                  ))}
                </div>
              </div>
              
              <div className="mt-6 sm:mt-8">
                <Link href="/shop" className="inline-flex items-center gap-2 text-gray-500 hover:text-[#f15a24] font-bold text-sm transition-colors">
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5"><path strokeLinecap="round" strokeLinejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                  Continue shopping
                </Link>
              </div>
            </div>
            
            <div className="lg:col-span-4 mt-6 lg:mt-0">
              <div className="bg-white rounded-2xl border border-gray-100 p-5 sm:p-7 shadow-sm sticky top-24">
                <h2 className="font-extrabold text-xl text-gray-900 mb-5 pb-4 border-b border-gray-100">Order Summary</h2>
                
                <div className="space-y-4 mb-6">
                  <div className="flex justify-between text-gray-600 font-medium">
                    <span>Subtotal</span>
                    <span className="text-gray-900">{money(subtotal)}</span>
                  </div>
                  <div className="flex justify-between text-gray-600 font-medium">
                    <span>Shipping</span>
                    <span className="text-sm text-gray-500">Calculated at checkout</span>
                  </div>
                </div>
                
                <div className="flex justify-between items-end border-t border-gray-100 pt-5 mb-8">
                  <span className="font-extrabold text-gray-900 text-lg">Total</span>
                  <span className="text-[#f15a24] font-black text-2xl">{money(subtotal)}</span>
                </div>
                
                <button
                  onClick={() => {
                    trackInitiateCheckout(itemsList, subtotal);
                    router.visit('/checkout');
                  }}
                  className="block w-full text-center bg-gray-900 hover:bg-black text-white font-bold py-4 rounded-xl shadow-md shadow-gray-900/20 transition-all hover:-translate-y-0.5 cursor-pointer"
                >
                  Proceed to Checkout
                </button>
                
                <div className="mt-6 flex items-center justify-center gap-4 grayscale opacity-50">
                  {/* Fake payment icons to make it look premium */}
                  <svg className="w-8 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M21.5 5.5h-19C1.67 5.5 1 6.17 1 7v10c0 .83.67 1.5 1.5 1.5h19c.83 0 1.5-.67 1.5-1.5V7c0-.83-.67-1.5-1.5-1.5zm-19 2h19v2h-19v-2zm19 8h-19v-4h19v4z"/></svg>
                  <svg className="w-8 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg>
                </div>
              </div>
            </div>
          </div>
        )}
      </main>
    </StorefrontLayout>
  );
}
