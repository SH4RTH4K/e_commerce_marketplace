import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function LandingPagesIndex({ pages, q }) {
  const [search, setSearch] = useState(q || '');

  const handleSearch = () => router.get('/admin/landing-pages', { q: search }, { preserveState: true });

  const handleDelete = (page) => {
    window.showConfirm(`Delete landing page "${page.title}"?`, () => { router.delete(`/admin/landing-pages/${page.id}`); });
  };

  return (
    <>
      <Head title="Landing Pages" />
      <AdminLayout title="Landing Pages">
        <div className="space-y-5">
          <div className="flex items-center justify-between">
            <p className="text-sm text-gray-500">Custom marketing landing pages for products or campaigns.</p>
            <a href="/admin/landing-pages/create" className="px-4 py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl transition-colors flex items-center gap-2">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14M5 12h14"/></svg>
              Create Page
            </a>
          </div>

          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <form onSubmit={e => { e.preventDefault(); handleSearch(); }} className="p-4 border-b border-gray-50 flex flex-wrap gap-3">
              <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search pages…"
                className="flex-1 min-w-[200px] border border-gray-200 rounded-xl px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300" />
              <button type="submit" className="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-xl">Search</button>
            </form>

            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="bg-gray-50/50">
                    {['Title', 'Slug', 'Status', 'Actions'].map(h => (
                      <th key={h} className={`px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide text-left ${h === 'Actions' ? 'text-right' : ''}`}>{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {(pages?.data || []).length === 0 ? (
                    <tr><td colSpan="4" className="px-5 py-12 text-center text-gray-400">No landing pages found.</td></tr>
                  ) : (pages?.data || []).map(page => (
                    <tr key={page.id} className="hover:bg-gray-50/50 transition-colors">
                      <td className="px-5 py-3.5 font-semibold text-gray-800">{page.title}</td>
                      <td className="px-5 py-3.5 text-gray-500 font-mono text-xs">{page.slug}</td>
                      <td className="px-5 py-3.5">
                        <span className={`px-2.5 py-1 text-xs font-medium rounded-full ${page.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                          {page.is_active ? 'Published' : 'Draft'}
                        </span>
                      </td>
                      <td className="px-5 py-3.5 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <a href={`/lp/${page.slug}`} target="_blank" rel="noopener" className="px-2.5 py-1 text-xs bg-blue-50 hover:bg-blue-100 text-blue-600 rounded-lg font-medium transition-colors">View</a>
                          <a href={`/admin/landing-pages/${page.id}/edit`} className="px-2.5 py-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg font-medium transition-colors">Edit</a>
                          <button onClick={() => handleDelete(page)} className="px-2.5 py-1 text-xs bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-lg font-medium transition-colors">Delete</button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {pages?.links && pages.links.length > 3 && (
              <div className="p-4 border-t border-gray-50 flex flex-wrap gap-1.5 items-center justify-center">
                {pages.links.map((link, i) => (
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