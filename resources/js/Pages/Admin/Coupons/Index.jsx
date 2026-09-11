import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function CouponsIndex({ coupons, q }) {
  const [search, setSearch] = useState(q || '');
  const [selected, setSelected] = useState([]);
  const [bulkAction, setBulkAction] = useState('');

  const handleFilter = () => router.get('/admin/coupons', { q: search }, { preserveState: true });
  const toggleSelect = (id) => setSelected(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);
  const toggleAll = (e) => setSelected(e.target.checked ? (coupons?.data || []).map(c => c.id) : []);

  const handleBulk = () => {
    if (!bulkAction || selected.length === 0) return;
    if (bulkAction === 'delete') { window.showConfirm(`Delete ${selected.length} coupon(s)?`, () => { router.post('/admin/' + window.location.pathname.split('/')[2] + '/bulk', { ids: selected, bulk_action: bulkAction }, { onSuccess: () => { setSelected([]); setBulkAction(''); } }); }); return; }
    router.post('/admin/coupons/bulk', { ids: selected, bulk_action: bulkAction }, { onSuccess: () => { setSelected([]); setBulkAction(''); } });
  };

  const handleDelete = (coupon) => {
    window.showConfirm(`Delete coupon "${coupon.code}"?`, () => { router.delete(`/admin/coupons/${coupon.id}`); });
  };

  return (
    <>
      <Head title="Coupons" />
      <AdminLayout title="Coupons">
        <div className="space-y-5">
          <div className="flex items-center justify-between">
            <div />
            <a href="/admin/coupons/create" className="px-4 py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl transition-colors flex items-center gap-2">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14M5 12h14"/></svg>
              Add Coupon
            </a>
          </div>

          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <form onSubmit={e => { e.preventDefault(); handleFilter(); }} className="p-4 border-b border-gray-50 flex flex-wrap gap-3">
              <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search coupon code…"
                className="flex-1 min-w-[200px] border border-gray-200 rounded-xl px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300" />
              <button type="submit" className="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-xl">Search</button>
            </form>

            {selected.length > 0 && (
              <div className="px-5 py-3 border-b border-gray-50 bg-orange-50 flex flex-wrap items-center gap-3">
                <span className="text-sm font-medium text-orange-700">{selected.length} selected</span>
                <select value={bulkAction} onChange={e => setBulkAction(e.target.value)} className="border border-orange-200 rounded-xl px-3 py-1.5 text-sm bg-white focus:outline-none">
                  <option value="">Choose action…</option>
                  <option value="activate">Activate</option>
                  <option value="deactivate">Deactivate</option>
                  <option value="delete">Delete</option>
                </select>
                <button onClick={handleBulk} disabled={!bulkAction} className="px-4 py-1.5 bg-orange-500 hover:bg-orange-600 disabled:opacity-50 text-white text-sm font-medium rounded-xl">Apply</button>
                <button onClick={() => setSelected([])} className="text-sm text-gray-500 ml-auto">Clear</button>
              </div>
            )}

            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="bg-gray-50/50">
                    <th className="px-5 py-3 w-10"><input type="checkbox" onChange={toggleAll} checked={selected.length === (coupons?.data || []).length && (coupons?.data || []).length > 0} className="h-4 w-4 accent-orange-500" /></th>
                    {['Code', 'Type', 'Value', 'Min Order', 'Max Discount', 'Uses', 'Dates', 'Status', 'Actions'].map(h => (
                      <th key={h} className={`px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide text-left ${h === 'Actions' ? 'text-right' : ''}`}>{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {(coupons?.data || []).length === 0 ? (
                    <tr><td colSpan="10" className="px-5 py-12 text-center text-gray-400">No coupons found.</td></tr>
                  ) : (coupons?.data || []).map(coupon => (
                    <tr key={coupon.id} className={`hover:bg-gray-50/50 transition-colors ${selected.includes(coupon.id) ? 'bg-orange-50/30' : ''}`}>
                      <td className="px-5 py-3.5"><input type="checkbox" checked={selected.includes(coupon.id)} onChange={() => toggleSelect(coupon.id)} className="h-4 w-4 accent-orange-500" /></td>
                      <td className="px-5 py-3.5 font-mono font-semibold text-gray-800">{coupon.code}</td>
                      <td className="px-5 py-3.5 text-gray-500 capitalize">{coupon.type}</td>
                      <td className="px-5 py-3.5 text-gray-800 font-medium">{coupon.type === 'percentage' ? `${coupon.value}%` : `৳${Number(coupon.value).toLocaleString()}`}</td>
                      <td className="px-5 py-3.5 text-gray-500">{coupon.min_order_amount ? `৳${Number(coupon.min_order_amount).toLocaleString()}` : '—'}</td>
                      <td className="px-5 py-3.5 text-gray-500">{coupon.max_discount ? `৳${Number(coupon.max_discount).toLocaleString()}` : '—'}</td>
                      <td className="px-5 py-3.5 text-gray-500">{coupon.used_count ?? 0}{coupon.max_uses ? `/${coupon.max_uses}` : ''}</td>
                      <td className="px-5 py-3.5 text-xs text-gray-400">
                        {coupon.starts_at ? new Date(coupon.starts_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) : '—'}
                        {' → '}
                        {coupon.expires_at ? new Date(coupon.expires_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) : '—'}
                      </td>
                      <td className="px-5 py-3.5">
                        <span className={`px-2.5 py-1 text-xs font-medium rounded-full ${coupon.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                          {coupon.is_active ? 'Active' : 'Inactive'}
                        </span>
                      </td>
                      <td className="px-5 py-3.5 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <a href={`/admin/coupons/${coupon.id}/edit`} className="px-2.5 py-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg font-medium transition-colors">Edit</a>
                          <button onClick={() => handleDelete(coupon)} className="px-2.5 py-1 text-xs bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-lg font-medium transition-colors">Delete</button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {coupons?.links && coupons.links.length > 3 && (
              <div className="p-4 border-t border-gray-50 flex flex-wrap gap-1.5 items-center justify-center">
                {coupons.links.map((link, i) => (
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