import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { imageUrl } from '@/lib/utils';

export default function FlashSaleIndex({ flashProducts = [], available, q, endsAt, timerExpired = false, errors = {} }) {
  const [search, setSearch] = useState(q || '');
  const [endTime, setEndTime] = useState(endsAt ? endsAt.replace(' ', 'T').slice(0, 16) : '');
  const [selectedFlash, setSelectedFlash] = useState([]);
  const [selectedAvailable, setSelectedAvailable] = useState([]);
  const minEndTime = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16);

  const handleSearch = () => router.get('/admin/flash-sale', { q: search }, { preserveState: true });
  const handleAdd = (productId) => router.post(`/admin/flash-sale/${productId}`, {}, { preserveScroll: true });
  const handleRemove = (productId) => router.delete(`/admin/flash-sale/${productId}`, { preserveScroll: true });
  const handleSaveEndTime = () => router.put('/admin/flash-sale/ends-at', { flash_sale_ends_at: endTime }, { preserveScroll: true });

  const primaryImage = (product) => {
    const img = (product.images || []).find(i => i.is_primary) || (product.images || [])[0];
    return imageUrl(img?.path, product.name);
  };

  const supplierProduct = (product) => {
    const link = product.supplier_links?.[0] || product.supplierLinks?.[0];
    return link?.supplier_product || link?.supplierProduct || null;
  };

  const supplierName = (product) => supplierProduct(product)?.supplier?.name || '';
  const supplierCode = (product) => {
    const source = supplierProduct(product);
    return source?.product_code || source?.supplier_product_id || product.sku || '';
  };

  const productMeta = (product) => {
    const supplier = supplierName(product);
    const code = supplierCode(product);
    return [
      supplier ? `Supplier: ${supplier}` : null,
      code ? `Code: ${code}` : null,
    ].filter(Boolean).join(' | ');
  };

  const toggleSelected = (id, selected, setSelected) => {
    setSelected(selected.includes(id) ? selected.filter(item => item !== id) : [...selected, id]);
  };

  const bulk = (ids, action, setSelected) => {
    if (ids.length === 0) return;
    router.post('/admin/flash-sale/bulk', { ids, bulk_action: action }, {
      preserveScroll: true,
      onSuccess: () => setSelected([]),
    });
  };

  const moveFlashProduct = (productId, direction) => {
    const currentIndex = flashProducts.findIndex(product => product.id === productId);
    const targetIndex = currentIndex + direction;
    if (currentIndex < 0 || targetIndex < 0 || targetIndex >= flashProducts.length) return;

    const order = flashProducts.map(product => product.id);
    [order[currentIndex], order[targetIndex]] = [order[targetIndex], order[currentIndex]];
    router.put('/admin/flash-sale/reorder', { order }, { preserveScroll: true });
  };

  return (
    <>
      <Head title="Flash Sale" />
      <AdminLayout title="Flash Sale">
        <div className="space-y-6">
          <div className="grid gap-6 lg:grid-cols-[1fr_1.2fr]">
            <div className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
              <h3 className="mb-3 font-semibold text-gray-900">Flash Sale Timer</h3>
              {timerExpired && (
                <div className="mb-3 rounded-xl border border-red-100 bg-red-50 px-3.5 py-2.5 text-sm font-medium text-red-700">
                  The saved timer has expired. Set a future date/time to show Flash Sale on the storefront.
                </div>
              )}
              <div className="flex flex-wrap items-center gap-3">
                <input type="datetime-local" value={endTime} min={minEndTime} onChange={e => setEndTime(e.target.value)}
                  className={`rounded-xl border px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300 ${errors.flash_sale_ends_at ? 'border-red-300' : 'border-gray-200'}`} />
                <button type="button" onClick={handleSaveEndTime} className="rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-orange-600">Save</button>
                {endTime && (
                  <button type="button" onClick={() => { setEndTime(''); router.put('/admin/flash-sale/ends-at', { flash_sale_ends_at: '' }, { preserveScroll: true }); }}
                    className="px-3 py-2 text-sm text-gray-500 hover:text-gray-700">Clear</button>
                )}
              </div>
              {errors.flash_sale_ends_at && <p className="mt-2 text-xs font-medium text-red-600">{errors.flash_sale_ends_at}</p>}
              <p className="mt-3 text-xs text-gray-400">This countdown appears in the Flash Sale homepage section for both storefront templates.</p>
            </div>

            <div className="rounded-2xl border border-orange-100 bg-orange-50 p-5">
              <h3 className="font-semibold text-gray-900">How It Works</h3>
              <p className="mt-2 text-sm leading-6 text-gray-600">Add products, set the timer, then use Homepage Settings to show/hide the section, choose product limit, and choose manual or newest order.</p>
              <div className="mt-4 flex flex-wrap gap-2">
                <a href="/admin/settings" className="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-orange-600 shadow-sm">Homepage Settings</a>
                <a href="/" target="_blank" rel="noreferrer" className="rounded-xl bg-[#1f2430] px-4 py-2 text-sm font-semibold text-white">Preview Storefront</a>
              </div>
            </div>
          </div>

          <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div className="flex flex-col gap-3 border-b border-gray-50 p-5 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h3 className="font-semibold text-gray-900">Flash Sale Products ({flashProducts.length})</h3>
                <p className="mt-1 text-xs text-gray-400">Use arrows for manual order. Homepage can use this order.</p>
              </div>
              <button type="button" disabled={selectedFlash.length === 0} onClick={() => bulk(selectedFlash, 'remove', setSelectedFlash)}
                className="rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-600 disabled:cursor-not-allowed disabled:opacity-50">Remove Selected</button>
            </div>
            <div className="divide-y divide-gray-50">
              {flashProducts.length === 0 ? (
                <div className="px-5 py-12 text-center text-gray-400">No products in flash sale yet.</div>
              ) : flashProducts.map((product, index) => (
                <div key={product.id} className="flex items-center gap-4 px-5 py-3.5 transition-colors hover:bg-gray-50/50">
                  <input type="checkbox" checked={selectedFlash.includes(product.id)} onChange={() => toggleSelected(product.id, selectedFlash, setSelectedFlash)} className="h-4 w-4 rounded accent-orange-500" />
                  <img src={primaryImage(product)} alt="" className="h-11 w-11 shrink-0 rounded-xl border border-gray-100 object-cover" />
                  <div className="min-w-0 flex-1">
                    <p className="truncate font-semibold text-gray-800">{product.name}</p>
                    <p className="text-xs text-gray-400">{product.category?.name || 'No category'} · ৳{Number(product.regular_price).toLocaleString()}{product.sale_price ? ` → ৳${Number(product.sale_price).toLocaleString()}` : ''}</p>
                  </div>
                  <div className="flex items-center gap-1">
                    <button type="button" disabled={index === 0} onClick={() => moveFlashProduct(product.id, -1)} className="grid h-8 w-8 place-items-center rounded-lg border border-gray-200 text-gray-500 disabled:opacity-30">↑</button>
                    <button type="button" disabled={index === flashProducts.length - 1} onClick={() => moveFlashProduct(product.id, 1)} className="grid h-8 w-8 place-items-center rounded-lg border border-gray-200 text-gray-500 disabled:opacity-30">↓</button>
                    <button type="button" onClick={() => handleRemove(product.id)} className="ml-2 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-600 transition-colors hover:bg-red-100">Remove</button>
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div className="border-b border-gray-50 p-5">
              <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <h3 className="font-semibold text-gray-900">Add Products</h3>
                  <p className="mt-1 text-xs text-gray-400">Search by local product details or supplier product details.</p>
                </div>
                <button type="button" disabled={selectedAvailable.length === 0} onClick={() => bulk(selectedAvailable, 'add', setSelectedAvailable)}
                  className="rounded-xl bg-orange-500 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Add Selected</button>
              </div>
              <form onSubmit={e => { e.preventDefault(); handleSearch(); }} className="mt-3 flex gap-3">
                <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search product, SKU, brand, supplier, product code..."
                  className="flex-1 rounded-xl border border-gray-200 px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300" />
                <button type="submit" className="rounded-xl bg-orange-500 px-4 py-2 text-sm font-medium text-white hover:bg-orange-600">Search</button>
              </form>
            </div>
            <div className="divide-y divide-gray-50">
              {(available?.data || []).length === 0 ? (
                <div className="px-5 py-8 text-center text-sm text-gray-400">No available products found.</div>
              ) : (available?.data || []).map(product => (
                <div key={product.id} className="flex items-center gap-4 px-5 py-3.5 transition-colors hover:bg-gray-50/50">
                  <input type="checkbox" checked={selectedAvailable.includes(product.id)} onChange={() => toggleSelected(product.id, selectedAvailable, setSelectedAvailable)} className="h-4 w-4 rounded accent-orange-500" />
                  <img src={primaryImage(product)} alt="" className="h-10 w-10 shrink-0 rounded-xl border border-gray-100 object-cover" />
                  <div className="min-w-0 flex-1">
                    <p className="truncate font-semibold text-gray-800">{product.name}</p>
                    <p className="text-xs text-gray-400">৳{Number(product.regular_price).toLocaleString()}</p>
                    {productMeta(product) && <p className="mt-0.5 truncate text-xs text-gray-400">{productMeta(product)}</p>}
                  </div>
                  <button type="button" onClick={() => handleAdd(product.id)} className="rounded-lg border border-orange-200 bg-orange-50 px-3 py-1.5 text-xs font-medium text-orange-600 transition-colors hover:bg-orange-100">+ Add</button>
                </div>
              ))}
            </div>
            {available?.links && available.links.length > 3 && (
              <div className="flex flex-wrap items-center justify-center gap-1.5 border-t border-gray-50 p-4">
                {available.links.map((link, i) => (
                  link.url ? (
                    <button key={i} type="button" onClick={() => router.get(link.url)} className={`h-9 min-w-9 rounded-xl px-3 text-sm font-medium transition-colors ${link.active ? 'bg-orange-500 text-white' : 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50'}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                  ) : (
                    <span key={i} className="flex h-9 min-w-9 items-center justify-center px-3 text-sm text-gray-300" dangerouslySetInnerHTML={{ __html: link.label }} />
                  )
                ))}
              </div>
            )}
          </div>
        </div>
      </AdminLayout>
    </>
  );
}
