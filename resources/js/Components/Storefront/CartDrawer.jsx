import { Fragment, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { money, imageUrl } from '@/lib/utils';
import { trackAddToCart } from '@/lib/tracking';

export default function CartDrawer({ isOpen, onClose }) {
  const { props } = usePage();
  const { cartItems = [], cartSubtotal = 0 } = props;
  const [processingKey, setProcessingKey] = useState(null);

  const updateQuantity = (item, qty) => {
    if (qty < 1) {
      removeItem(item.key);
      return;
    }
    // Fire add_to_cart only when quantity is increasing
    if (qty > item.qty) {
      trackAddToCart(
        { id: item.product_id, name: item.name, price: item.unit_price },
        qty - item.qty,
        item.variant || null
      );
    }
    setProcessingKey(item.key);
    router.post('/cart/update', { key: item.key, qty }, {
      preserveScroll: true,
      onFinish: () => setProcessingKey(null)
    });
  };

  const removeItem = (key) => {
    setProcessingKey(key);
    router.post('/cart/remove', { key }, {
      preserveScroll: true,
      onFinish: () => setProcessingKey(null)
    });
  };

  return (
    <>
      {/* Backdrop */}
      {isOpen && (
        <div 
          className="fixed inset-0 z-40 bg-gray-900/60 backdrop-blur-sm transition-opacity" 
          onClick={onClose}
        ></div>
      )}

      {/* Drawer */}
      <div className={`fixed inset-y-0 right-0 z-50 w-full max-w-sm bg-white shadow-2xl flex flex-col transition-transform duration-300 ease-in-out transform ${isOpen ? 'translate-x-0' : 'translate-x-full'}`}>
        
        {/* Header */}
        <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100">
          <h2 className="font-extrabold text-base sm:text-lg text-gray-900 flex items-center gap-2">
            <svg className="w-5 h-5 text-[#f15a24]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5">
              <path strokeLinecap="round" strokeLinejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
            </svg>
            SHOPPING CART
          </h2>
          <button 
            onClick={onClose}
            className="text-gray-400 hover:text-gray-700 bg-gray-50 hover:bg-gray-100 p-1.5 rounded-full transition-colors flex items-center gap-1 text-xs font-semibold px-3 cursor-pointer"
          >
            Close
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        {/* Items */}
        <div className="flex-1 overflow-y-auto p-5 custom-scrollbar">
          {cartItems.length === 0 ? (
            <div className="h-full flex flex-col items-center justify-center text-gray-400">
              <svg className="w-16 h-16 mb-4 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1">
                <path strokeLinecap="round" strokeLinejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
              </svg>
              <p className="font-medium">Your cart is empty.</p>
              <button onClick={onClose} className="mt-6 px-6 py-2 bg-[#f15a24] text-white rounded-xl font-bold text-sm hover:bg-[#d94a1a] transition">
                Continue Shopping
              </button>
            </div>
          ) : (
            <ul className="space-y-4 divide-y divide-gray-100">
              {cartItems.map((item) => (
                <li key={item.key} className={`pt-4 first:pt-0 flex gap-3.5 items-start ${processingKey === item.key ? 'opacity-50 pointer-events-none' : ''}`}>
                  <Link href={`/product/${item.slug}`} onClick={onClose} className="w-16 h-16 sm:w-20 sm:h-20 shrink-0 bg-gray-50 rounded-xl border border-gray-100 overflow-hidden block">
                    <img src={imageUrl(item.image, item.name)} alt={item.name} className="w-full h-full object-contain p-1" />
                  </Link>
                  
                  <div className="flex-1 min-w-0">
                    <div className="flex items-start justify-between gap-1.5">
                      <Link href={`/product/${item.slug}`} onClick={onClose} className="font-bold text-gray-900 text-xs sm:text-sm line-clamp-2 hover:text-[#f15a24] transition-colors leading-tight">
                        {item.name}
                      </Link>
                      <button 
                        type="button"
                        onClick={() => removeItem(item.key)}
                        className="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors shrink-0 -mt-1 -mr-1 cursor-pointer"
                        title="Remove item"
                        aria-label="Remove item"
                      >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                          <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                    </div>

                    {item.variant && (
                      <p className="text-[11px] font-semibold text-gray-500 bg-gray-100 inline-block px-1.5 py-0.5 rounded mt-1">
                        {item.variant}
                      </p>
                    )}
                    
                    <div className="flex items-center justify-between mt-2.5">
                      <div className="flex items-center border border-gray-200 rounded-lg overflow-hidden bg-gray-50 h-7">
                        <button 
                          type="button"
                          onClick={() => updateQuantity(item, item.qty - 1)}
                          className="w-7 h-full flex items-center justify-center text-gray-600 hover:bg-gray-200 hover:text-gray-900 transition-colors cursor-pointer"
                          title={item.qty === 1 ? "Remove" : "Decrease"}
                        >
                          {item.qty === 1 ? (
                            <svg className="w-3 h-3 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5">
                              <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                          ) : (
                            <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5"><path strokeLinecap="round" strokeLinejoin="round" d="M20 12H4" /></svg>
                          )}
                        </button>
                        <span className="w-8 text-center text-xs font-bold text-gray-900 select-none">
                          {item.qty}
                        </span>
                        <button 
                          type="button"
                          onClick={() => updateQuantity(item, item.qty + 1)}
                          className="w-7 h-full flex items-center justify-center text-gray-600 hover:bg-gray-200 hover:text-gray-900 transition-colors cursor-pointer"
                          title="Increase"
                        >
                          <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5"><path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" /></svg>
                        </button>
                      </div>
                      
                      <div className="flex flex-col items-end leading-none">
                        <span className="font-black text-[#f15a24] text-sm">{money(item.line_total)}</span>
                        {item.qty > 1 && (
                          <span className="text-[10px] font-bold text-gray-400 mt-1">{item.qty} × {money(item.unit_price)}</span>
                        )}
                      </div>
                    </div>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </div>

        {/* Footer */}
        {cartItems.length > 0 && (
          <div className="border-t border-gray-100 p-6 bg-gray-50/50">
            <div className="flex justify-between items-center mb-6">
              <span className="font-bold text-gray-600 text-sm">Subtotal</span>
              <span className="font-black text-[#f15a24] text-xl">{money(cartSubtotal)}</span>
            </div>
            
            <div className="flex flex-col gap-3">
              <Link
                href="/cart"
                onClick={onClose}
                className="w-full bg-white border-2 border-gray-200 hover:border-gray-900 text-gray-900 font-extrabold text-sm py-3.5 rounded-xl transition-colors text-center"
              >
                VIEW CART
              </Link>
              <Link
                href="/checkout"
                onClick={onClose}
                className="w-full bg-[#f15a24] hover:bg-[#d94a1a] text-white font-extrabold text-sm py-3.5 rounded-xl shadow-lg shadow-[#f15a24]/20 transition-all hover:-translate-y-0.5 text-center flex items-center justify-center gap-2"
              >
                CHECKOUT NOW
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5"><path strokeLinecap="round" strokeLinejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
              </Link>
            </div>
          </div>
        )}
      </div>
    </>
  );
}
