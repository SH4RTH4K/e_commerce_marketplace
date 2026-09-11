import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';

export default function MessageShow({ message }) {
  return (
    <>
      <Head title={`Message from ${message.name}`} />
      <AdminLayout title="Message Detail">
        <div className="max-w-3xl space-y-5">
          <a href="/admin/messages" className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 w-fit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            Messages
          </a>

          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
            <div className="flex items-start justify-between">
              <div>
                <h2 className="text-lg font-bold text-gray-900">{message.name}</h2>
                <p className="text-sm text-gray-500">{message.email}</p>
                {message.phone && <p className="text-sm text-gray-500">{message.phone}</p>}
              </div>
              <div className="flex items-center gap-2">
                <span className={`px-2.5 py-1 text-xs font-medium rounded-full capitalize ${
                  message.status === 'new' ? 'bg-blue-100 text-blue-700' :
                  message.status === 'archived' ? 'bg-gray-100 text-gray-500' :
                  'bg-green-100 text-green-700'
                }`}>{message.status}</span>
                <span className="text-xs text-gray-400">{message.created_at ? new Date(message.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : ''}</span>
              </div>
            </div>

            {message.subject && (
              <div>
                <p className="text-xs text-gray-400 uppercase tracking-wide mb-1">Subject</p>
                <p className="text-gray-800 font-medium">{message.subject}</p>
              </div>
            )}

            {message.message && (
              <div>
                <p className="text-xs text-gray-400 uppercase tracking-wide mb-1">Message</p>
                <div className="bg-gray-50 rounded-xl p-4 text-gray-700 text-sm whitespace-pre-wrap">{message.message}</div>
              </div>
            )}

            {/* Dynamic field values */}
            {(message.values || []).length > 0 && (
              <div>
                <p className="text-xs text-gray-400 uppercase tracking-wide mb-2">Additional Fields</p>
                <div className="space-y-2">
                  {message.values.map((val, i) => (
                    <div key={i} className="flex gap-3 text-sm">
                      <span className="text-gray-500 font-medium min-w-[120px]">{val.label || val.key}:</span>
                      {val.file_path ? (
                        <a href={`/${val.file_path}`} target="_blank" rel="noopener" className="text-orange-500 hover:text-orange-600 underline">{val.file_name || 'Download file'}</a>
                      ) : (
                        <span className="text-gray-700">{val.value || '—'}</span>
                      )}
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>

          <div className="flex items-center gap-2">
            {message.status === 'new' && (
              <button onClick={() => router.post(`/admin/messages/${message.id}/read`)} className="px-4 py-2.5 bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium rounded-xl transition-colors">Mark as Read</button>
            )}
            {message.status !== 'archived' && (
              <button onClick={() => router.post(`/admin/messages/${message.id}/archive`)} className="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-xl transition-colors">Archive</button>
            )}
            <button onClick={() => { window.showConfirm('Delete this message?', () => router.delete(`/admin/messages/${message.id}`)); }}
              className="px-4 py-2.5 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-sm font-medium rounded-xl transition-colors">Delete</button>
          </div>
        </div>
      </AdminLayout>
    </>
  );
}