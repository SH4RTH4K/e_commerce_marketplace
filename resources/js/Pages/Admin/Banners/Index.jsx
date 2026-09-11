import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';

export default function BannersIndex({ banners, placements }) {
  const handleToggle = (id) => router.patch(`/admin/banners/${id}/toggle`);
  const handleDelete = (banner) => {
    window.showConfirm(`Delete "${banner.title || 'this banner'}" permanently?`, () => { router.delete(`/admin/banners/${banner.id}`); });
  };

  const placementLabel = (key) => placements?.[key] || key;

  return (
    <>
      <Head title="Banners" />
      <AdminLayout title="Banners">
        <div className="space-y-5">
          <div className="flex items-center justify-between">
            <div />
            <a href="/admin/banners/create" className="px-4 py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl transition-colors flex items-center gap-2">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14M5 12h14"/></svg>
              Add Banner
            </a>
          </div>

          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="bg-gray-50/50">
                    {['Banner', 'Placement', 'Style', 'Position', 'Status', 'Actions'].map(h => (
                      <th key={h} className={`px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide text-left ${h === 'Actions' ? 'text-right' : ''}`}>{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {(banners || []).length === 0 ? (
                    <tr><td colSpan="6" className="px-5 py-12 text-center text-gray-400">No banners found.</td></tr>
                  ) : (banners || []).map(banner => (
                    <tr key={banner.id} className="hover:bg-gray-50/50 transition-colors">
                      <td className="px-5 py-3.5">
                        <div className="flex items-center gap-3">
                          {banner.image
                            ? <img src={banner.image.startsWith('http') ? banner.image : `/${banner.image}`} alt="" className="h-10 w-20 rounded-lg object-cover shrink-0 border border-gray-100" />
                            : <div className="h-10 w-20 rounded-lg bg-gray-100 shrink-0 flex items-center justify-center text-xs text-gray-400">No image</div>
                          }
                          <div>
                            <p className="font-semibold text-gray-800">{banner.title || '—'}</p>
                            {banner.subtitle && <p className="text-xs text-gray-400 truncate max-w-[200px]">{banner.subtitle}</p>}
                          </div>
                        </div>
                      </td>
                      <td className="px-5 py-3.5">
                        <span className="px-2 py-0.5 text-xs font-medium rounded-full bg-blue-50 text-blue-600">{placementLabel(banner.placement)}</span>
                      </td>
                      <td className="px-5 py-3.5 text-gray-500 capitalize">{banner.style}</td>
                      <td className="px-5 py-3.5 text-gray-500">{banner.position ?? 0}</td>
                      <td className="px-5 py-3.5">
                        <button onClick={() => handleToggle(banner.id)}
                          className={`px-2.5 py-1 text-xs font-medium rounded-full transition-colors ${banner.is_active ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'}`}>
                          {banner.is_active ? 'Active' : 'Hidden'}
                        </button>
                      </td>
                      <td className="px-5 py-3.5 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <a href={`/admin/banners/${banner.id}/edit`} className="px-2.5 py-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg font-medium transition-colors">Edit</a>
                          <button onClick={() => handleDelete(banner)} className="px-2.5 py-1 text-xs bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-lg font-medium transition-colors">Delete</button>
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