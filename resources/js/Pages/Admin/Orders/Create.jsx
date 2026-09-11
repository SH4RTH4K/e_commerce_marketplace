import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';

export default function OrderCreate({ initialProducts = [], shipInside = 60, shipOutside = 120, taxPercent = 0 }) {
  const { data, setData, post, processing, errors } = useForm({
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    shipping_address: '',
    city: '',
    postal_code: '',
    shipping_zone: 'inside_dhaka',
    items: [],
    discount_amount: 0,
    shipping_charge: shipInside,
    internal_note: '',
    delivery_note: '',
  });

  const [searchTerm, setSearchTerm] = useState('');
  const [searchResults, setSearchResults] = useState(initialProducts);
  const [isSearching, setIsSearching] = useState(false);
  const [dropdownOpen, setDropdownOpen] = useState(false);
  const searchTimeout = useRef(null);
  const searchContainerRef = useRef(null);

  // Cart state helper
  const [cartItems, setCartItems] = useState([]);

  useEffect(() => {
    // Sync cartItems with form data including custom price
    setData('items', cartItems.map(i => ({
      product_id: i.id,
      qty: i.qty,
      price: parseFloat(i.price) || 0,
    })));
  }, [cartItems]);

  useEffect(() => {
    setData('shipping_charge', data.shipping_zone === 'inside_dhaka' ? shipInside : shipOutside);
  }, [data.shipping_zone, shipInside, shipOutside]);

  // Close dropdown on outside click
  useEffect(() => {
    const handleClickOutside = (e) => {
      if (searchContainerRef.current && !searchContainerRef.current.contains(e.target)) {
        setDropdownOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleSearch = (term) => {
    setSearchTerm(term);
    setDropdownOpen(true);
    if (searchTimeout.current) clearTimeout(searchTimeout.current);

    if (!term.trim()) {
      setSearchResults(initialProducts);
      return;
    }

    setIsSearching(true);
    searchTimeout.current = setTimeout(() => {
      fetch(`/admin/orders/product-search?q=${encodeURIComponent(term)}`)
        .then(res => res.json())
        .then(results => {
          setSearchResults(results);
          setIsSearching(false);
        })
        .catch(() => setIsSearching(false));
    }, 250);
  };

  const addToCart = (product) => {
    if (product.stock_quantity < 1) {
      alert('This product is out of stock.');
      return;
    }

    setCartItems(prev => {
      const exists = prev.find(i => i.id === product.id);
      if (exists) {
        if (exists.qty + 1 > product.stock_quantity) {
          alert(`Cannot add more than available stock (${product.stock_quantity}).`);
          return prev;
        }
        return prev.map(i => i.id === product.id ? { ...i, qty: i.qty + 1 } : i);
      }
      return [...prev, { ...product, qty: 1, price: product.price }];
    });

    setSearchTerm('');
    setDropdownOpen(false);
  };

  const updateQty = (id, newQty) => {
    if (newQty < 1) return;
    setCartItems(prev => prev.map(i => {
      if (i.id === id) {
        if (newQty > i.stock_quantity) {
          alert(`Cannot exceed available stock (${i.stock_quantity}).`);
          return i;
        }
        return { ...i, qty: newQty };
      }
      return i;
    }));
  };

  const updatePrice = (id, newPrice) => {
    const p = parseFloat(newPrice);
    setCartItems(prev => prev.map(i => {
      if (i.id === id) {
        return { ...i, price: isNaN(p) ? 0 : Math.max(0, p) };
      }
      return i;
    }));
  };

  const removeFromCart = (id) => {
    setCartItems(prev => prev.filter(i => i.id !== id));
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    if (cartItems.length === 0) {
      alert('Please select at least one product for this order.');
      return;
    }
    post('/admin/orders', {
      preserveScroll: true,
    });
  };

  // Calculate Totals
  const subtotal = cartItems.reduce((acc, item) => acc + ((parseFloat(item.price) || 0) * item.qty), 0);
  const discount = parseFloat(data.discount_amount || 0);
  const shipping = parseFloat(data.shipping_charge || 0);
  const taxable  = Math.max(0, subtotal - discount);
  const tax      = Math.round(taxable * taxPercent / 100);
  const total    = taxable + shipping + tax;

  return (
    <>
      <Head title="Create Order" />
      <AdminLayout>
        <div className="max-w-5xl mx-auto space-y-6">
          {/* Header */}
          <div className="flex items-center gap-4">
            <Link href="/admin/orders" className="p-2 rounded-xl hover:bg-gray-100 text-gray-500 transition-colors">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
              </svg>
            </Link>
            <div>
              <h1 className="text-xl font-bold text-gray-900">Create Manual Order</h1>
              <p className="text-sm text-gray-500 mt-0.5">Select products, set custom pricing &amp; create order on behalf of customer</p>
            </div>
          </div>

          <form onSubmit={handleSubmit} className="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {/* Left Column: Products & Customer Details */}
            <div className="lg:col-span-2 space-y-6">

              {/* Product Selection Card */}
              <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-visible">
                <div className="p-5 border-b border-gray-50">
                  <div className="flex items-center justify-between">
                    <div>
                      <h2 className="text-base font-semibold text-gray-900">Select Products</h2>
                      <p className="text-xs text-gray-400 mt-0.5">Search or click to select products and customize prices</p>
                    </div>
                    {cartItems.length > 0 && (
                      <span className="text-xs bg-orange-100 text-orange-700 font-bold px-2.5 py-1 rounded-full">
                        {cartItems.length} {cartItems.length === 1 ? 'item' : 'items'} in order
                      </span>
                    )}
                  </div>

                  {/* Search and Product Selector */}
                  <div className="mt-4 relative" ref={searchContainerRef}>
                    <div className="relative">
                      <svg className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                      </svg>
                      <input
                        type="text"
                        placeholder="Click to browse or search products by name / SKU..."
                        value={searchTerm}
                        onFocus={() => setDropdownOpen(true)}
                        onChange={(e) => handleSearch(e.target.value)}
                        className="w-full pl-10 pr-10 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none transition-all"
                      />
                      {isSearching ? (
                        <div className="absolute right-3.5 top-1/2 -translate-y-1/2">
                          <svg className="animate-spin h-4 w-4 text-orange-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                          </svg>
                        </div>
                      ) : (
                        <button
                          type="button"
                          onClick={() => setDropdownOpen(!dropdownOpen)}
                          className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 p-1"
                        >
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                            <path d="M6 9l6 6 6-6"/>
                          </svg>
                        </button>
                      )}
                    </div>

                    {/* Search / Selection Dropdown */}
                    {dropdownOpen && (
                      <div className="absolute z-20 mt-2 w-full bg-white rounded-2xl shadow-2xl border border-gray-100 max-h-80 overflow-y-auto divide-y divide-gray-50">
                        {searchResults.length === 0 ? (
                          <div className="p-4 text-center text-xs text-gray-400">
                            No products found matching &ldquo;{searchTerm}&rdquo;
                          </div>
                        ) : (
                          searchResults.map(product => {
                            const isAdded = cartItems.some(i => i.id === product.id);
                            return (
                              <button
                                key={product.id}
                                type="button"
                                onClick={() => addToCart(product)}
                                className={`w-full flex items-center gap-3.5 p-3.5 text-left hover:bg-orange-50/50 transition-colors ${
                                  isAdded ? 'bg-orange-50/30' : ''
                                }`}
                              >
                                <div className="h-11 w-11 rounded-xl bg-gray-100 flex-shrink-0 overflow-hidden border border-gray-100 flex items-center justify-center">
                                  {product.image ? (
                                    <img
                                      src={product.image.startsWith('http') ? product.image : `/${product.image}`}
                                      alt={product.name}
                                      className="h-full w-full object-cover"
                                    />
                                  ) : (
                                    <svg className="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                  )}
                                </div>
                                <div className="flex-1 min-w-0">
                                  <p className="text-sm font-semibold text-gray-900 truncate">{product.name}</p>
                                  <div className="flex items-center gap-2 mt-0.5">
                                    <span className="text-xs text-gray-400 font-mono">SKU: {product.sku || 'N/A'}</span>
                                    <span className="text-xs text-gray-300">•</span>
                                    <span className={`text-xs font-semibold ${product.stock_quantity > 5 ? 'text-green-600' : product.stock_quantity > 0 ? 'text-amber-600' : 'text-red-500'}`}>
                                      Stock: {product.stock_quantity}
                                    </span>
                                  </div>
                                </div>
                                <div className="text-right shrink-0">
                                  <p className="text-sm font-extrabold text-gray-900">৳{Number(product.price).toLocaleString()}</p>
                                  {isAdded ? (
                                    <span className="text-[10px] text-green-600 font-bold bg-green-50 px-1.5 py-0.5 rounded">Added +</span>
                                  ) : (
                                    <span className="text-[10px] text-orange-600 font-bold bg-orange-50 px-1.5 py-0.5 rounded">+ Add</span>
                                  )}
                                </div>
                              </button>
                            );
                          })
                        )}
                      </div>
                    )}
                  </div>
                </div>

                {/* Selected Cart Items Table */}
                <div className="p-5">
                  {cartItems.length === 0 ? (
                    <div className="text-center py-10 text-gray-400 bg-gray-50/50 rounded-xl border border-dashed border-gray-200">
                      <svg className="mx-auto h-10 w-10 mb-2 opacity-40 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                      </svg>
                      <p className="text-sm font-medium text-gray-600">No products added to order yet</p>
                      <p className="text-xs text-gray-400 mt-1">Search or select from the dropdown above to add products</p>
                    </div>
                  ) : (
                    <div className="space-y-3">
                      <div className="hidden sm:grid sm:grid-cols-12 gap-3 text-xs font-semibold text-gray-400 uppercase pb-2 border-b border-gray-100">
                        <div className="col-span-5">Product</div>
                        <div className="col-span-3 text-center">Unit Price (Editable)</div>
                        <div className="col-span-2 text-center">Quantity</div>
                        <div className="col-span-2 text-right">Subtotal</div>
                      </div>

                      {cartItems.map(item => (
                        <div key={item.id} className="p-3 bg-gray-50/70 hover:bg-gray-50 rounded-xl border border-gray-100 flex flex-col sm:grid sm:grid-cols-12 sm:items-center gap-3 transition-colors">
                          {/* Product Info */}
                          <div className="sm:col-span-5 flex items-center gap-3 min-w-0">
                            <div className="h-10 w-10 rounded-lg bg-white overflow-hidden border border-gray-200 flex-shrink-0 flex items-center justify-center">
                              {item.image ? (
                                <img
                                  src={item.image.startsWith('http') ? item.image : `/${item.image}`}
                                  alt={item.name}
                                  className="h-full w-full object-cover"
                                />
                              ) : (
                                <svg className="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                              )}
                            </div>
                            <div className="min-w-0 flex-1">
                              <p className="text-sm font-semibold text-gray-900 truncate">{item.name}</p>
                              <p className="text-xs text-gray-400 font-mono">Stock: {item.stock_quantity}</p>
                            </div>
                          </div>

                          {/* Editable Price Option */}
                          <div className="sm:col-span-3 flex items-center justify-between sm:justify-center gap-2">
                            <span className="sm:hidden text-xs text-gray-500">Price:</span>
                            <div className="flex items-center bg-white border border-gray-200 rounded-lg px-2.5 py-1.5 focus-within:ring-2 focus-within:ring-orange-500/30 focus-within:border-orange-500 transition-all shadow-sm">
                              <span className="text-xs font-bold text-gray-400 mr-1">৳</span>
                              <input
                                type="number"
                                min="0"
                                step="any"
                                value={item.price}
                                onChange={e => updatePrice(item.id, e.target.value)}
                                className="w-20 text-sm font-bold text-gray-900 bg-transparent border-none p-0 focus:outline-none"
                                title="Custom Unit Price"
                              />
                            </div>
                          </div>

                          {/* Quantity Controls */}
                          <div className="sm:col-span-2 flex items-center justify-between sm:justify-center gap-2">
                            <span className="sm:hidden text-xs text-gray-500">Qty:</span>
                            <div className="flex items-center bg-white rounded-lg border border-gray-200 shadow-sm">
                              <button
                                type="button"
                                onClick={() => updateQty(item.id, item.qty - 1)}
                                className="px-2.5 py-1 text-gray-600 hover:bg-gray-100 rounded-l-lg transition-colors font-bold text-sm"
                              >
                                -
                              </button>
                              <span className="text-sm font-bold w-7 text-center">{item.qty}</span>
                              <button
                                type="button"
                                onClick={() => updateQty(item.id, item.qty + 1)}
                                className="px-2.5 py-1 text-gray-600 hover:bg-gray-100 rounded-r-lg transition-colors font-bold text-sm"
                              >
                                +
                              </button>
                            </div>
                          </div>

                          {/* Subtotal & Delete */}
                          <div className="sm:col-span-2 flex items-center justify-between sm:justify-end gap-2">
                            <span className="sm:hidden text-xs text-gray-500">Subtotal:</span>
                            <span className="text-sm font-extrabold text-gray-900">
                              ৳{Number((parseFloat(item.price) || 0) * item.qty).toLocaleString()}
                            </span>
                            <button
                              type="button"
                              onClick={() => removeFromCart(item.id)}
                              className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors ml-1"
                              title="Remove item"
                            >
                              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M3 6h18M8 6V4h8v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/>
                              </svg>
                            </button>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                  {errors.items && <p className="mt-2 text-sm text-red-500">{errors.items}</p>}
                </div>
              </div>

              {/* Customer Details Form Card */}
              <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
                <h2 className="text-base font-semibold text-gray-900">Customer Details</h2>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Full Name *</label>
                    <input
                      type="text"
                      required
                      value={data.customer_name}
                      onChange={e => setData('customer_name', e.target.value)}
                      placeholder="Customer Name"
                      className="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 outline-none transition-all"
                    />
                    {errors.customer_name && <p className="mt-1 text-xs text-red-500">{errors.customer_name}</p>}
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Phone Number *</label>
                    <input
                      type="tel"
                      required
                      placeholder="01XXXXXXXXX"
                      value={data.customer_phone}
                      onChange={e => setData('customer_phone', e.target.value)}
                      className="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 outline-none transition-all"
                    />
                    {errors.customer_phone && <p className="mt-1 text-xs text-red-500">{errors.customer_phone}</p>}
                  </div>

                  <div className="sm:col-span-2">
                    <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Email Address (Optional)</label>
                    <input
                      type="email"
                      placeholder="customer@example.com"
                      value={data.customer_email}
                      onChange={e => setData('customer_email', e.target.value)}
                      className="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 outline-none transition-all"
                    />
                    {errors.customer_email && <p className="mt-1 text-xs text-red-500">{errors.customer_email}</p>}
                  </div>

                  <div className="sm:col-span-2">
                    <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Delivery Address *</label>
                    <textarea
                      required
                      rows="2"
                      placeholder="House, Road, Area, District..."
                      value={data.shipping_address}
                      onChange={e => setData('shipping_address', e.target.value)}
                      className="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 outline-none transition-all resize-none"
                    />
                    {errors.shipping_address && <p className="mt-1 text-xs text-red-500">{errors.shipping_address}</p>}
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">City *</label>
                    <input
                      type="text"
                      required
                      placeholder="e.g. Dhaka, Chittagong"
                      value={data.city}
                      onChange={e => setData('city', e.target.value)}
                      className="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 outline-none transition-all"
                    />
                    {errors.city && <p className="mt-1 text-xs text-red-500">{errors.city}</p>}
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Shipping Zone</label>
                    <select
                      value={data.shipping_zone}
                      onChange={e => setData('shipping_zone', e.target.value)}
                      className="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 outline-none transition-all"
                    >
                      <option value="inside_dhaka">Inside Dhaka (৳{shipInside})</option>
                      <option value="outside_dhaka">Outside Dhaka (৳{shipOutside})</option>
                    </select>
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Postal Code (Optional)</label>
                    <input
                      type="text"
                      placeholder="e.g. 1216"
                      value={data.postal_code}
                      onChange={e => setData('postal_code', e.target.value)}
                      className="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 outline-none transition-all"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Internal Note (Optional)</label>
                    <input
                      type="text"
                      placeholder="Private note for staff"
                      value={data.internal_note}
                      onChange={e => setData('internal_note', e.target.value)}
                      className="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 outline-none transition-all"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Customer Delivery Note (Optional)</label>
                    <input
                      type="text"
                      placeholder="Special delivery note from customer"
                      value={data.delivery_note}
                      onChange={e => setData('delivery_note', e.target.value)}
                      className="w-full bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 outline-none transition-all"
                    />
                  </div>
                </div>
              </div>

            </div>

            {/* Right Column: Order Summary */}
            <div className="space-y-6">
              <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sticky top-24 space-y-4">
                <h2 className="text-base font-semibold text-gray-900">Order Summary</h2>

                <div className="space-y-3 text-sm">
                  <div className="flex justify-between text-gray-600">
                    <span>Subtotal ({cartItems.length} {cartItems.length === 1 ? 'item' : 'items'})</span>
                    <span className="font-semibold text-gray-900">৳{Number(subtotal).toLocaleString()}</span>
                  </div>

                  <div className="flex justify-between items-center text-gray-600">
                    <span>Discount</span>
                    <div className="w-28 relative">
                      <span className="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-xs">৳</span>
                      <input
                        type="number"
                        min="0"
                        step="any"
                        value={data.discount_amount}
                        onChange={e => setData('discount_amount', e.target.value)}
                        className="w-full bg-gray-50 border border-gray-200 rounded-lg pl-6 pr-2 py-1 text-right text-sm font-semibold focus:bg-white focus:ring-1 focus:ring-orange-500 focus:border-orange-500 outline-none"
                      />
                    </div>
                  </div>

                  <div className="flex justify-between items-center text-gray-600">
                    <span>Shipping</span>
                    <div className="w-28 relative">
                      <span className="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-xs">৳</span>
                      <input
                        type="number"
                        min="0"
                        step="any"
                        value={data.shipping_charge}
                        onChange={e => setData('shipping_charge', e.target.value)}
                        className="w-full bg-gray-50 border border-gray-200 rounded-lg pl-6 pr-2 py-1 text-right text-sm font-semibold focus:bg-white focus:ring-1 focus:ring-orange-500 focus:border-orange-500 outline-none"
                      />
                    </div>
                  </div>

                  {taxPercent > 0 && (
                    <div className="flex justify-between text-gray-600">
                      <span>Tax ({taxPercent}%)</span>
                      <span className="font-semibold text-gray-900">৳{Number(tax).toLocaleString()}</span>
                    </div>
                  )}

                  <div className="border-t border-gray-100 pt-3 mt-3 flex justify-between items-center">
                    <span className="font-bold text-gray-900 text-base">Grand Total</span>
                    <span className="text-xl font-black text-orange-600">৳{Number(total).toLocaleString()}</span>
                  </div>
                </div>

                <div className="pt-2">
                  <button
                    type="submit"
                    disabled={processing || cartItems.length === 0}
                    className="w-full py-3.5 bg-orange-500 hover:bg-orange-600 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-bold rounded-xl transition-all shadow-sm shadow-orange-200 flex items-center justify-center gap-2"
                  >
                    {processing ? (
                      <>
                        <svg className="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24" fill="none">
                          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                        </svg>
                        Creating Order...
                      </>
                    ) : (
                      'Create Order'
                    )}
                  </button>
                  <p className="text-center text-xs text-gray-400 mt-2.5">
                    Order will be created as pre-confirmed with Cash on Delivery.
                  </p>
                </div>
              </div>
            </div>

          </form>
        </div>
      </AdminLayout>
    </>
  );
}
