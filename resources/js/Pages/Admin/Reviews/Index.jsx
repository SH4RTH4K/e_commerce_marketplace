import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function ReviewsIndex({ reviews, status, q, counts }) {
  const [search, setSearch] = useState(q || '');
  const [selected, setSelected] = useState([]);
  const [bulkAction, setBulkAction] = useState('');

  const tabs = [
    { key: 'pending', label: 'Pending' },
    { key: 'approved', label: 'Approved' },
    { key: 'rejected', label: 'Rejected' },
    { key: 'all', label: 'All' },
  ];

  const handleTab = (tab) => router.get('/admin/reviews', { status: tab, q: search }, { preserveState: true });
  const handleFilter = () => router.get('/admin/reviews', { status, q: search }, { preserveState: true });

  const toggleSelect = (id) => setSelected(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);
  const toggleAll = (e) => setSelected(e.target.checked ? (reviews?.data || []).map(r => r.id) : []);

  const handleBulk = () => {
    if (!bulkAction || selected.length === 0) return;
    if (bulkAction === 'delete') { window.showConfirm(`Delete ${selected.length} review(s)?`, () => { router.post('/admin/' + window.location.pathname.split('/')[2] + '/bulk', { ids: selected, bulk_action: bulkAction }, { onSuccess: () => { setSelected([]); setBulkAction(''); } }); }); return; }
    router.post('/admin/reviews/bulk', { ids: selected, bulk_action: bulkAction }, { onSuccess: () => { setSelected([]); setBulkAction(''); } });
  };

  const stars = (rating) => '★'.repeat(rating) + '☆'.repeat(5 - rating);

  return (
    <>
      <Head title="Reviews" />
      <AdminLayout title="Reviews">
        <div className="space-y-5">
          {/* Tabs */}
          <div className="flex gap-1 bg-gray-100 p-1 rounded-xl w-fit">
            {tabs.map(tab => (
              <button key={tab.key} onClick={() => handleTab(tab.key)}
                className={`px-4 py-2 text-sm font-medium rounded-lg transition-colors ${status === tab.key ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'}`}>
                {tab.label}
                {counts?.[tab.key] > 0 && <span className="ml-1.5 px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-orange-100 text-orange-600">{counts[tab.key]}</span>}
              </button>
            ))}
          </div>

          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <form onSubmit={e => { e.preventDefault(); handleFilter(); }} className="p-4 border-b border-gray-50 flex flex-wrap gap-3">
              <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search reviews…"
                className="flex-1 min-w-[200px] border border-gray-200 rounded-xl px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300" />
              <button type="submit" className="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-xl">Search</button>
            </form>

            {selected.length > 0 && (
              <div className="px-5 py-3 border-b border-gray-50 bg-orange-50 flex flex-wrap items-center gap-3">
                <span className="text-sm font-medium text-orange-700">{selected.length} selected</span>
                <select value={bulkAction} onChange={e => setBulkAction(e.target.value)} className="border border-orange-200 rounded-xl px-3 py-1.5 text-sm bg-white focus:outline-none">
                  <option value="">Choose action…</option>
                  <option value="approve">Approve</option>
                  <option value="reject">Reject</option>
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
                    <th className="px-5 py-3 w-10"><input type="checkbox" onChange={toggleAll} checked={selected.length === (reviews?.data || []).length && (reviews?.data || []).length > 0} className="h-4 w-4 accent-orange-500" /></th>
                    {['Product', 'Author', 'Rating', 'Review', 'Status', 'Actions'].map(h => (
                      <th key={h} className={`px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide text-left ${h === 'Actions' ? 'text-right' : ''}`}>{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {(reviews?.data || []).length === 0 ? (
                    <tr><td colSpan="7" className="px-5 py-12 text-center text-gray-400">No reviews found.</td></tr>
                  ) : (reviews?.data || []).map(review => (
                    <tr key={review.id} className={`hover:bg-gray-50/50 transition-colors ${selected.includes(review.id) ? 'bg-orange-50/30' : ''}`}>
                      <td className="px-5 py-3.5"><input type="checkbox" checked={selected.includes(review.id)} onChange={() => toggleSelect(review.id)} className="h-4 w-4 accent-orange-500" /></td>
                      <td className="px-5 py-3.5">
                        <p className="font-medium text-gray-800 truncate max-w-[160px]">{review.product?.name || '—'}</p>
                      </td>
                      <td className="px-5 py-3.5">
                        <p className="text-gray-800">{review.author_name || review.user?.name || '—'}</p>
                        <p className="text-xs text-gray-400">{review.author_email || review.user?.email || ''}</p>
                      </td>
                      <td className="px-5 py-3.5 text-orange-400 text-xs tracking-wide">{stars(review.rating || 0)}</td>
                      <td className="px-5 py-3.5">
                        {review.title && <p className="font-medium text-gray-800 text-xs">{review.title}</p>}
                        <p className="text-gray-500 text-xs truncate max-w-[200px]">{review.body}</p>
                      </td>
                      <td className="px-5 py-3.5">
                        <span className={`px-2.5 py-1 text-xs font-medium rounded-full capitalize ${
                          review.status === 'approved' ? 'bg-green-100 text-green-700' :
                          review.status === 'rejected' ? 'bg-red-100 text-red-600' :
                          'bg-amber-100 text-amber-700'
                        }`}>{review.status}</span>
                      </td>
                      <td className="px-5 py-3.5 text-right">
                        <div className="flex items-center justify-end gap-1">
                          {review.status !== 'approved' && (
                            <button onClick={() => router.post(`/admin/reviews/${review.id}/approve`)} className="px-2 py-1 text-xs bg-green-50 hover:bg-green-100 text-green-700 rounded-lg font-medium transition-colors">Approve</button>
                          )}
                          {review.status !== 'rejected' && (
                            <button onClick={() => router.post(`/admin/reviews/${review.id}/reject`)} className="px-2 py-1 text-xs bg-amber-50 hover:bg-amber-100 text-amber-700 rounded-lg font-medium transition-colors">Reject</button>
                          )}
                          <button onClick={() => { window.showConfirm('Delete this review?', () => router.delete(`/admin/reviews/${review.id}`)); }}
                            className="px-2 py-1 text-xs bg-red-50 hover:bg-red-100 text-red-600 rounded-lg font-medium transition-colors">Delete</button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {reviews?.links && reviews.links.length > 3 && (
              <div className="p-4 border-t border-gray-50 flex flex-wrap gap-1.5 items-center justify-center">
                {reviews.links.map((link, i) => (
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