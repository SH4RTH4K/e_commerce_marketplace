import { useEffect, useRef, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { money } from '@/lib/utils';

export default function AddOrderProducts({ order, taxPercent = 0 }) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(false);
  const [searchError, setSearchError] = useState('');
  const [retry, setRetry] = useState(0);
  const productCache = useRef(new Map());
  const form = useForm({ items: [] });

  useEffect(() => {
    if (!open) return;
    const search = query.trim();
    if (productCache.current.has(search)) {
      setProducts(productCache.current.get(search));
      setLoading(false);
      setSearchError('');
      return;
    }
    const controller = new AbortController();
    setLoading(true);
    setProducts([]);
    setSearchError('');
    const timeout = setTimeout(async () => {
      try {
        const response = await fetch(`/admin/orders/product-search?q=${encodeURIComponent(search)}`, {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
        });
        if (response.status === 401 || (response.redirected && response.url.includes('/login'))) {
          throw new Error('Your admin session expired. Refresh the page and sign in again.');
        }
        if (!response.ok) throw new Error('Could not load products. Please try again.');
        const results = await response.json();
        if (!Array.isArray(results)) throw new Error('Could not load products. Please try again.');
        if (!controller.signal.aborted) {
          productCache.current.set(search, results);
          setProducts(results);
        }
      } catch (error) {
        if (!controller.signal.aborted) setSearchError(error.message);
      } finally {
        if (!controller.signal.aborted) setLoading(false);
      }
    }, 250);
    return () => { clearTimeout(timeout); controller.abort(); };
  }, [open, query, retry]);

  const selectProduct = (product) => {
    form.clearErrors();
    const selected = form.data.items.find(item => item.product_id === product.id);
    if (selected) {
      form.setData('items', form.data.items.map(item => item.product_id === product.id
        ? { ...item, quantity: Math.min(Number(item.quantity) + 1, Number(product.stock_quantity), 100000) } : item));
    } else {
      form.setData('items', [...form.data.items, { ...product, product_id: product.id, quantity: 1 }]);
    }
  };

  const addedSubtotal = form.data.items.reduce((sum, item) => sum + Number(item.price) * Number(item.quantity), 0);
  const taxable = Math.max(0, Number(order.subtotal) + addedSubtotal - Number(order.discount_amount || 0));
  const tax = Math.round(taxable * Number(taxPercent)) / 100;
  const total = Math.round((taxable + Number(order.shipping_charge || 0) + tax) * 100) / 100;
  const validItems = form.data.items.length > 0 && form.data.items.every(item =>
    Number.isInteger(Number(item.quantity)) && Number(item.quantity) > 0
    && Number(item.quantity) <= Math.min(Number(item.stock_quantity), 100000));

  const submit = (event) => {
    event.preventDefault();
    form.transform(data => ({ items: data.items.map(item => ({ product_id: item.product_id, quantity: Number(item.quantity) })) }));
    form.post(`/admin/orders/${order.id}/items`, {
      preserveScroll: true,
      onError: () => productCache.current.clear(),
      onSuccess: () => { productCache.current.clear(); form.reset(); setQuery(''); setProducts([]); setOpen(false); },
    });
  };

  return (
    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
      <div className="flex items-center justify-between gap-3">
        <h2 className="font-semibold text-gray-900">Add Products</h2>
        <button type="button" onClick={() => setOpen(!open)} disabled={form.processing}
          aria-expanded={open} aria-controls="add-order-products"
          className="px-3.5 py-2 rounded-xl border border-orange-200 text-orange-600 hover:bg-orange-50 text-sm font-semibold disabled:opacity-60">
          {open ? 'Close' : 'Add Products'}
        </button>
      </div>
      {open && (
        <form id="add-order-products" onSubmit={submit} className="mt-4 space-y-4">
          <p className="text-xs text-gray-500">Add products to this pending order. The saved discount and delivery charge will be kept.</p>
          <div>
            <label htmlFor="order-product-search" className="block text-sm font-medium text-gray-700 mb-1.5">Search by product name or SKU</label>
            <input id="order-product-search" type="search" value={query} onChange={event => setQuery(event.target.value)}
              disabled={form.processing} placeholder="Search products…"
              className="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300" />
          </div>
          <div className="max-h-64 overflow-y-auto border border-gray-100 rounded-xl" aria-live="polite">
            {loading ? <p className="p-4 text-sm text-gray-500">Loading products…</p>
              : searchError ? (
                <div className="p-4 space-y-2">
                  <p className="text-sm text-red-600" role="alert">{searchError}</p>
                  <button type="button" onClick={() => setRetry(value => value + 1)} className="text-sm font-semibold text-orange-600 hover:underline">Retry loading products</button>
                </div>
              )
              : !products.length ? <p className="p-4 text-sm text-gray-500">No products found.</p>
              : products.map(product => {
                const selected = form.data.items.find(item => item.product_id === product.id);
                const unavailable = Math.min(Number(product.stock_quantity), 100000) <= Number(selected?.quantity || 0);
                return (
                  <div key={product.id} className="flex items-center gap-3 p-3 border-b last:border-b-0 border-gray-100">
                    <img src={product.image_url} alt="" className="w-10 h-10 rounded-lg object-contain shrink-0" />
                    <div className="min-w-0 flex-1">
                      <p className="text-sm font-medium text-gray-800 break-words">{product.name}</p>
                      <p className="text-xs text-gray-500">{product.sku && `SKU: ${product.sku} · `}{money(product.price)} · Stock: {product.stock_quantity}</p>
                    </div>
                    <button type="button" onClick={() => selectProduct(product)} disabled={unavailable || form.processing || (!selected && form.data.items.length >= 50)}
                      aria-label={`Add ${product.name}`}
                      className="px-3 py-1.5 bg-orange-50 text-orange-600 rounded-lg text-xs font-semibold disabled:opacity-50 shrink-0">
                      {Number(product.stock_quantity) <= 0 ? 'Out of stock' : 'Add'}
                    </button>
                  </div>
                );
              })}
          </div>
          {form.data.items.length > 0 && (
            <div className="space-y-3">
              <h3 className="text-sm font-semibold text-gray-800">Products to add</h3>
              {form.data.items.map((item, index) => (
                <div key={item.product_id} className="rounded-xl border border-gray-100 p-3 space-y-2">
                  <div className="flex flex-wrap items-center gap-3">
                    <div className="min-w-0 flex-1">
                      <p className="text-sm font-medium text-gray-800 break-words">{item.name}</p>
                      <p className="text-xs text-gray-500">{money(item.price)} each</p>
                    </div>
                    <label className="flex items-center gap-2 text-xs text-gray-600">
                      Qty
                      <input type="number" min="1" max={Math.min(Number(item.stock_quantity), 100000)} step="1" required value={item.quantity}
                        disabled={form.processing} aria-label={`Quantity for ${item.name}`}
                        onChange={event => form.setData('items', form.data.items.map(row => row.product_id === item.product_id ? { ...row, quantity: event.target.value } : row))}
                        className="w-20 border border-gray-200 rounded-lg px-2 py-1.5 text-sm" />
                    </label>
                    <button type="button" disabled={form.processing} onClick={() => { form.clearErrors(); form.setData('items', form.data.items.filter(row => row.product_id !== item.product_id)); }}
                      aria-label={`Remove ${item.name}`} className="text-xs text-red-600 hover:underline disabled:opacity-50">Remove</button>
                  </div>
                  {(form.errors[`items.${index}.quantity`] || form.errors[`items.${index}.product_id`]) && (
                    <p className="text-xs text-red-600" role="alert">{form.errors[`items.${index}.quantity`] || form.errors[`items.${index}.product_id`]}</p>
                  )}
                </div>
              ))}
              {validItems && <p className="text-sm font-semibold text-gray-800" aria-live="polite">Updated order total: {money(total)}</p>}
            </div>
          )}
          {form.errors.items && <p className="text-sm text-red-600" role="alert">{form.errors.items}</p>}
          <button type="submit" disabled={form.processing || !validItems}
            className="px-5 py-2.5 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white text-sm font-semibold rounded-xl">
            {form.processing ? 'Adding…' : 'Add to Order'}
          </button>
        </form>
      )}
    </div>
  );
}
