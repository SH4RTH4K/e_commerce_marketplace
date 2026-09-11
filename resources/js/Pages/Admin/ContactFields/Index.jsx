import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';

export default function ContactFormFieldsPage({ fields, types }) {
  const handleToggle = (id) => router.patch(`/admin/contact-fields/${id}/toggle`, {}, { preserveScroll: true });
  const handleDelete = (field) => {
    if (field.is_system) return alert('Default system fields cannot be deleted. You can deactivate them instead.');
    window.showConfirm(`Delete field "${field.label}"?`, () => { router.delete(`/admin/contact-fields/${field.id}`); });
  };

  const typeLabel = (key) => types?.[key] || key;

  return (
    <>
      <Head title="Contact Form Fields" />
      <AdminLayout title="Contact Form Fields">
        <div className="space-y-5">
          <div className="flex items-center justify-between">
            <p className="text-sm text-gray-500">Manage the fields shown on your store's contact page.</p>
            <a href="/admin/contact-fields/create" className="px-4 py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl transition-colors flex items-center gap-2">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14M5 12h14"/></svg>
              Add Field
            </a>
          </div>

          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="bg-gray-50/50">
                    {['Label', 'Key', 'Type', 'Required', 'Position', 'Status', 'Actions'].map(h => (
                      <th key={h} className={`px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide text-left ${h === 'Actions' ? 'text-right' : ''}`}>{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {(fields || []).length === 0 ? (
                    <tr><td colSpan="7" className="px-5 py-12 text-center text-gray-400">No fields found.</td></tr>
                  ) : (fields || []).map(field => (
                    <tr key={field.id} className="hover:bg-gray-50/50 transition-colors">
                      <td className="px-5 py-3.5">
                        <div>
                          <p className="font-semibold text-gray-800">{field.label}</p>
                          {field.is_system && <span className="text-[10px] font-medium text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded-full">system</span>}
                        </div>
                      </td>
                      <td className="px-5 py-3.5 text-gray-400 font-mono text-xs">{field.key}</td>
                      <td className="px-5 py-3.5">
                        <span className="px-2 py-0.5 text-xs font-medium rounded-full bg-blue-50 text-blue-600 capitalize">{typeLabel(field.type)}</span>
                      </td>
                      <td className="px-5 py-3.5">
                        {field.is_required
                          ? <span className="px-2 py-0.5 text-xs font-medium rounded-full bg-orange-50 text-orange-600">Required</span>
                          : <span className="text-gray-300 text-xs">Optional</span>
                        }
                      </td>
                      <td className="px-5 py-3.5 text-gray-500">{field.position ?? 0}</td>
                      <td className="px-5 py-3.5">
                        <button onClick={() => handleToggle(field.id)}
                          disabled={field.is_system && field.is_active && ['name','email','message'].includes(field.key)}
                          className={`px-2.5 py-1 text-xs font-medium rounded-full transition-colors disabled:opacity-40 disabled:cursor-not-allowed ${field.is_active ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'}`}>
                          {field.is_active ? 'Active' : 'Hidden'}
                        </button>
                      </td>
                      <td className="px-5 py-3.5 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <a href={`/admin/contact-fields/${field.id}/edit`} className="px-2.5 py-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg font-medium transition-colors">Edit</a>
                          {!field.is_system && (
                            <button onClick={() => handleDelete(field)} className="px-2.5 py-1 text-xs bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-lg font-medium transition-colors">Delete</button>
                          )}
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