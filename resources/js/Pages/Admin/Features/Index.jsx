import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

function FeatureIcon({ icon }) {
  if (!icon) return null;
  const str = icon.trim();
  if (str.startsWith('<svg')) {
    return (
      <div
        className="w-9 h-9 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center shrink-0 [&>svg]:w-5 [&>svg]:h-5"
        dangerouslySetInnerHTML={{ __html: str }}
      />
    );
  }
  if (str.startsWith('M') || str.startsWith('m') || (str.length > 5 && /[0-9]/.test(str))) {
    return (
      <div className="w-9 h-9 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center shrink-0 shadow-xs">
        <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" d={str} />
        </svg>
      </div>
    );
  }
  return (
    <div className="w-9 h-9 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center text-lg shrink-0 shadow-xs">
      {str}
    </div>
  );
}

export default function FeaturesIndex({ features }) {
  const [selected, setSelected] = useState([]);
  const [bulkAction, setBulkAction] = useState('');

  const toggleSelect = (id) => setSelected(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);
  const toggleAll = (e) => setSelected(e.target.checked ? (features || []).map(f => f.id) : []);

  const handleBulk = () => {
    if (!bulkAction || selected.length === 0) return;
    if (bulkAction === 'delete') {
      window.showConfirm(`Delete ${selected.length} feature(s)?`, () => {
        router.post('/admin/features/bulk', { ids: selected, bulk_action: bulkAction }, {
          onSuccess: () => { setSelected([]); setBulkAction(''); }
        });
      });
      return;
    }
    router.post('/admin/features/bulk', { ids: selected, bulk_action: bulkAction }, {
      onSuccess: () => { setSelected([]); setBulkAction(''); }
    });
  };

  const handleDelete = (feature) => {
    window.showConfirm(`Delete "${feature.title}"?`, () => {
      router.delete(`/admin/features/${feature.id}`);
    });
  };

  return (
    <>
      <Head title="Features &amp; Badges" />
      <AdminLayout title="Features &amp; Badges">
        <div className="space-y-5">
          <div className="flex items-center justify-between">
            <p className="text-sm text-gray-500">Store benefits shared by all storefront templates. Active items are shown in each template's benefits area.</p>
            <a href="/admin/features/create" className="px-4 py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl transition-colors flex items-center gap-2 shadow-xs">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14M5 12h14"/></svg>
              Add Feature
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
                    <th className="px-5 py-3 w-10"><input type="checkbox" onChange={toggleAll} checked={selected.length === (features || []).length && (features || []).length > 0} className="h-4 w-4 accent-orange-500" /></th>
                    {['Feature', 'Position', 'Status', 'Actions'].map(h => (
                      <th key={h} className={`px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide text-left ${h === 'Actions' ? 'text-right' : ''}`}>{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {(features || []).length === 0 ? (
                    <tr><td colSpan="5" className="px-5 py-12 text-center text-gray-400">No features found.</td></tr>
                  ) : (features || []).map(feature => (
                    <tr key={feature.id} className={`hover:bg-gray-50/50 transition-colors ${selected.includes(feature.id) ? 'bg-orange-50/30' : ''}`}>
                      <td className="px-5 py-3.5"><input type="checkbox" checked={selected.includes(feature.id)} onChange={() => toggleSelect(feature.id)} className="h-4 w-4 accent-orange-500" /></td>
                      <td className="px-5 py-3.5">
                        <div className="flex items-center gap-3">
                          <FeatureIcon icon={feature.icon} />
                          <div>
                            <p className="font-semibold text-gray-800">{feature.title}</p>
                            {feature.subtitle && <p className="text-xs text-gray-400">{feature.subtitle}</p>}
                          </div>
                        </div>
                      </td>
                      <td className="px-5 py-3.5 text-gray-500">{feature.position ?? 0}</td>
                      <td className="px-5 py-3.5">
                        <span className={`px-2.5 py-1 text-xs font-medium rounded-full ${feature.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                          {feature.is_active ? 'Active' : 'Hidden'}
                        </span>
                      </td>
                      <td className="px-5 py-3.5 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <a href={`/admin/features/${feature.id}/edit`} className="px-2.5 py-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg font-medium transition-colors">Edit</a>
                          <button onClick={() => handleDelete(feature)} className="px-2.5 py-1 text-xs bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-lg font-medium transition-colors">Delete</button>
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
    </>
  );
}
