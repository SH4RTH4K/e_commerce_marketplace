import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function FlashSaleIndex({ flashProducts, available, q, endsAt }) {
  const [search, setSearch] = useState(q || '');
  const [endTime, setEndTime] = useState(endsAt ? endsAt.replace(' ', 'T').slice(0, 16) : '');

  const handleSearch = () => router.get('/admin/flash-sale', { q: search }, { preserveState: true });
  const handleAdd = (productId) => router.post(`/admin/flash-sale/${productId}`);
  const handleRemove = (productId) => router.delete(`/admin/flash-sale/${productId}`);
  const handleSaveEndTime = () => router.put('/admin/flash-sale/ends-at', { flash_sale_ends_at: endTime });

  const primaryImage = (product) => {
    const img = (product.images || []).find(i => i.is_primary) || (product.images || [])[0];
    return img?.path ? `/${img.path}` : null;
  };

  return (
    <>
      <Head title="Flash Sale" />
      <AdminLayout title="Flash Sale">
        <div className="space-y-6">
          {/* End time */}
          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <h3 className="font-semibold text-gray-900 mb-3">Flash Sale Timer</h3>
            <div className="flex items-center gap-3">
              <input type="datetime-local" value={endTime} onChange={e => setEndTime(e.target.value)}
                className="border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300" />
              <button onClick={handleSaveEndTime} className="px-4 py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl transition-colors">Save</button>
              {endTime && (
                <button onClick={() => { setEndTime(''); router.put('/admin/flash-sale/ends-at', { flash_sale_ends_at: '' }); }}
                  className="px-3 py-2 text-sm text-gray-500 hover:text-gray-700">Clear</button>
              )}
            </div>
          </div>

          {/* Current flash products */}
          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div className="p-5 border-b border-gray-50">
              <h3 className="font-semibold text-gray-900">Flash Sale Products ({(flashProducts || []).length})</h3>
            </div>
            <div className="divide-y divide-gray-50">
              {(flashProducts || []).length === 0 ? (
                <div className="px-5 py-12 text-center text-gray-400">No products in flash sale yet.</div>
              ) : (flashProducts || []).map(product => (
                <div key={product.id} className="flex items-center gap-4 px-5 py-3.5 hover:bg-gray-50/50 transition-colors">
                  {primaryImage(product)
                    ? <img src={primaryImage(product)} alt="" className="h-10 w-10 rounded-xl object-cover shrink-0 border border-gray-100" />
                    : <div className="h-10 w-10 rounded-xl bg-gray-100 shrink-0" />
                  }
                  <div className="flex-1 min-w-0">
                    <p className="font-semibold text-gray-800 truncate">{product.name}</p>
                    <p className="text-xs text-gray-400">{product.category?.name} · ৳{Number(product.regular_price).toLocaleString()}{product.sale_price ? ` → ৳${Number(product.sale_price).toLocaleString()}` : ''}</p>
                  </div>
                  <button onClick={() => handleRemove(product.id)} className="px-3 py-1.5 text-xs bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-lg font-medium transition-colors">Remove</button>
                </div>
              ))}
            </div>
          </div>

          {/* Available products */}
          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div className="p-5 border-b border-gray-50">
              <h3 className="font-semibold text-gray-900 mb-3">Add Products</h3>
              <form onSubmit={e => { e.preventDefault(); handleSearch(); }} className="flex gap-3">
                <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search products to add…"
                  className="flex-1 border border-gray-200 rounded-xl px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300" />
                <button type="submit" className="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-xl">Search</button>
              </form>
            </div>
            <div className="divide-y divide-gray-50">
              {(available?.data || []).length === 0 ? (
                <div className="px-5 py-8 text-center text-gray-400 text-sm">No available products found.</div>
              ) : (available?.data || []).map(product => (
                <div key={product.id} className="flex items-center gap-4 px-5 py-3.5 hover:bg-gray-50/50 transition-colors">
                  {primaryImage(product)
                    ? <img src={primaryImage(product)} alt="" className="h-10 w-10 rounded-xl object-cover shrink-0 border border-gray-100" />
                    : <div className="h-10 w-10 rounded-xl bg-gray-100 shrink-0" />
                  }
                  <div className="flex-1 min-w-0">
                    <p className="font-semibold text-gray-800 truncate">{product.name}</p>
                    <p className="text-xs text-gray-400">৳{Number(product.regular_price).toLocaleString()}</p>
                  </div>
                  <button onClick={() => handleAdd(product.id)} className="px-3 py-1.5 text-xs bg-orange-50 hover:bg-orange-100 text-orange-600 border border-orange-200 rounded-lg font-medium transition-colors">+ Add</button>
                </div>
              ))}
            </div>
            {available?.links && available.links.length > 3 && (
              <div className="p-4 border-t border-gray-50 flex flex-wrap gap-1.5 items-center justify-center">
                {available.links.map((link, i) => (
                  link.url ? (
                    <button key={i} onClick={() => router.get(link.url)} className={`min-w-[36px] h-9 px-3 rounded-xl text-sm font-medium transition-colors ${link.active ? 'bg-orange-500 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                  ) : (
                    <span key={i} className="min-w-[36px] h-9 px-3 flex items-center justify-center text-sm text-gray-300" dangerouslySetInnerHTML={{ __html: link.label }} />
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