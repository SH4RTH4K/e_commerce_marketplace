import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import CategoryIcon from '@/Components/Storefront/CategoryIcon';

export default function CategoriesIndex({ categories }) {
  const [selected, setSelected] = useState([]);
  const [bulkAction, setBulkAction] = useState('');
  const [deleteModal, setDeleteModal] = useState(null);

  const toggleSelect = (id) => setSelected(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);
  const toggleAll = (e) => setSelected(e.target.checked ? (categories || []).map(c => c.id) : []);

  const handleBulk = () => {
    if (!bulkAction || selected.length === 0) return;
    if (bulkAction === 'delete') {
      setDeleteModal({ type: 'bulk' });
      return;
    }
    router.post('/admin/categories/bulk', { ids: selected, bulk_action: bulkAction }, { onSuccess: () => { setSelected([]); setBulkAction(''); } });
  };

  const confirmDelete = (category) => {
    setDeleteModal({ type: 'single', category });
  };

  const executeDelete = () => {
    if (!deleteModal) return;
    if (deleteModal.type === 'bulk') {
      router.post('/admin/categories/bulk', { ids: selected, bulk_action: 'delete' }, { onSuccess: () => { setSelected([]); setBulkAction(''); setDeleteModal(null); } });
    } else {
      router.delete(`/admin/categories/${deleteModal.category.id}`, {
        onSuccess: () => setDeleteModal(null),
        onError: (errors) => {
          setDeleteModal(null);
          alert("Failed to delete. It may be attached to products or have other constraints.");
        }
      });
    }
  };

  return (
    <>
      <Head title="Categories" />
      <AdminLayout title="Categories">
        <div className="space-y-5">
          <div className="flex items-center justify-between">
            <div />
            <a href="/admin/categories/create" className="px-4 py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl transition-colors flex items-center gap-2">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14M5 12h14"/></svg>
              Add Category
            </a>
          </div>

          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
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
                    <th className="px-5 py-3 w-10">
                      <input type="checkbox" onChange={toggleAll} checked={selected.length === (categories || []).length && (categories || []).length > 0} className="h-4 w-4 accent-orange-500" />
                    </th>
                    {['Category', 'Parent', 'Slug', 'Products', 'Position', 'Status', 'Actions'].map(h => (
                      <th key={h} className={`px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide text-left ${h === 'Actions' ? 'text-right' : ''}`}>{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {(categories || []).length === 0 ? (
                    <tr><td colSpan="7" className="px-5 py-12 text-center text-gray-400">No categories found.</td></tr>
                  ) : (categories || []).map(category => (
                    <tr key={category.id} className={`hover:bg-gray-50/50 transition-colors ${selected.includes(category.id) ? 'bg-orange-50/30' : ''}`}>
                      <td className="px-5 py-3.5">
                        <input type="checkbox" checked={selected.includes(category.id)} onChange={() => toggleSelect(category.id)} className="h-4 w-4 accent-orange-500" />
                      </td>
                      <td className="px-5 py-3.5">
                        <div className="flex items-center gap-3">
                          {/* Legacy image/emoji fallback retained for migration reference.
                          {category.image
                            ? <img src={category.image.startsWith('http') ? category.image : `/${category.image}`} alt="" className="h-10 w-10 rounded-xl object-cover shrink-0 border border-gray-100" />
                            : <div className="h-10 w-10 rounded-xl bg-orange-50 shrink-0 flex items-center justify-center text-lg">{category.icon || '📦'}</div>
                          )} */}
                          {category.image ? (
                            <img src={category.image.startsWith('http') ? category.image : `/${category.image}`} alt="" className="h-10 w-10 rounded-xl object-cover shrink-0 border border-gray-100" />
                          ) : (
                            <div className="h-10 w-10 rounded-xl bg-orange-50 text-orange-500 shrink-0 flex items-center justify-center border border-orange-100">
                              <CategoryIcon icon={category.icon} name={category.name} className="h-6 w-6" />
                            </div>
                          )}
                          <span className="font-semibold text-gray-800">{category.name}</span>
                        </div>
                      </td>
                      <td className="px-5 py-3.5 text-gray-500 font-medium text-xs">
                        {category.parent ? (
                          <span className="px-2 py-1 bg-gray-100 rounded-md">{category.parent.name}</span>
                        ) : (
                          <span className="text-gray-400">—</span>
                        )}
                      </td>
                      <td className="px-5 py-3.5 text-gray-500 font-mono text-xs">{category.slug}</td>
                      <td className="px-5 py-3.5 text-gray-600">{category.products_count ?? 0}</td>
                      <td className="px-5 py-3.5 text-gray-500">{category.menu_order ?? category.position ?? 0}</td>
                      <td className="px-5 py-3.5">
                        <span className={`px-2.5 py-1 text-xs font-medium rounded-full ${category.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                          {category.is_active ? 'Active' : 'Hidden'}
                        </span>
                      </td>
                      <td className="px-5 py-3.5 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <a href={`/admin/categories/${category.id}/edit`} className="px-2.5 py-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg font-medium transition-colors">Edit</a>
                          <button type="button" onClick={(e) => { e.preventDefault(); confirmDelete(category); }} className="px-2.5 py-1 text-xs bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-lg font-medium transition-colors">Delete</button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </AdminLayout>

      {/* Custom Delete Modal */}
      {deleteModal && (
        <div className="fixed inset-0 z-[9999] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4 transition-all">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 overflow-hidden relative">
            <div className="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center mb-4">
              <svg className="w-6 h-6" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </div>
            <h3 className="text-xl font-bold text-gray-900 mb-2">Are you sure?</h3>
            <p className="text-gray-500 text-sm mb-6">
              {deleteModal.type === 'bulk' 
                ? `You are about to permanently delete ${selected.length} categories.` 
                : `You are about to permanently delete "${deleteModal.category.name}".`}
              <br/>This action cannot be undone.
            </p>
            <div className="flex items-center justify-end gap-3 mt-6">
              <button type="button" onClick={() => setDeleteModal(null)} className="px-5 py-2.5 text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl transition-colors">Cancel</button>
              <button type="button" onClick={executeDelete} className="px-5 py-2.5 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-xl transition-colors">Yes, delete</button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
