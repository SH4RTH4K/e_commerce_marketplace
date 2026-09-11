import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function MessagesIndex({ messages, status, q, counts }) {
  const [search, setSearch] = useState(q || '');
  const [selected, setSelected] = useState([]);
  const [bulkAction, setBulkAction] = useState('');

  const tabs = [
    { key: 'new', label: 'New' },
    { key: 'read', label: 'Read' },
    { key: 'archived', label: 'Archived' },
    { key: 'all', label: 'All' },
  ];

  const handleTab = (tab) => router.get('/admin/messages', { status: tab, q: search }, { preserveState: true });
  const handleFilter = () => router.get('/admin/messages', { status, q: search }, { preserveState: true });

  const toggleSelect = (id) => setSelected(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);
  const toggleAll = (e) => setSelected(e.target.checked ? (messages?.data || []).map(m => m.id) : []);

  const handleBulk = () => {
    if (!bulkAction || selected.length === 0) return;
    if (bulkAction === 'delete') { window.showConfirm(`Delete ${selected.length} message(s)?`, () => { router.post('/admin/' + window.location.pathname.split('/')[2] + '/bulk', { ids: selected, bulk_action: bulkAction }, { onSuccess: () => { setSelected([]); setBulkAction(''); } }); }); return; }
    router.post('/admin/messages/bulk', { ids: selected, bulk_action: bulkAction }, { onSuccess: () => { setSelected([]); setBulkAction(''); } });
  };

  return (
    <>
      <Head title="Messages" />
      <AdminLayout title="Messages">
        <div className="space-y-5">
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
              <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search messages…"
                className="flex-1 min-w-[200px] border border-gray-200 rounded-xl px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300" />
              <button type="submit" className="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-xl">Search</button>
            </form>

            {selected.length > 0 && (
              <div className="px-5 py-3 border-b border-gray-50 bg-orange-50 flex flex-wrap items-center gap-3">
                <span className="text-sm font-medium text-orange-700">{selected.length} selected</span>
                <select value={bulkAction} onChange={e => setBulkAction(e.target.value)} className="border border-orange-200 rounded-xl px-3 py-1.5 text-sm bg-white focus:outline-none">
                  <option value="">Choose action…</option>
                  <option value="read">Mark as Read</option>
                  <option value="archive">Archive</option>
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
                    <th className="px-5 py-3 w-10"><input type="checkbox" onChange={toggleAll} checked={selected.length === (messages?.data || []).length && (messages?.data || []).length > 0} className="h-4 w-4 accent-orange-500" /></th>
                    {['Name', 'Email', 'Phone', 'Subject', 'Date', 'Actions'].map(h => (
                      <th key={h} className={`px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide text-left ${h === 'Actions' ? 'text-right' : ''}`}>{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {(messages?.data || []).length === 0 ? (
                    <tr><td colSpan="7" className="px-5 py-12 text-center text-gray-400">No messages found.</td></tr>
                  ) : (messages?.data || []).map(msg => (
                    <tr key={msg.id} className={`hover:bg-gray-50/50 transition-colors ${selected.includes(msg.id) ? 'bg-orange-50/30' : ''} ${msg.status === 'new' ? 'font-medium' : ''}`}>
                      <td className="px-5 py-3.5"><input type="checkbox" checked={selected.includes(msg.id)} onChange={() => toggleSelect(msg.id)} className="h-4 w-4 accent-orange-500" /></td>
                      <td className="px-5 py-3.5">
                        <a href={`/admin/messages/${msg.id}`} className="text-gray-800 hover:text-orange-500 font-semibold">{msg.name}</a>
                      </td>
                      <td className="px-5 py-3.5 text-gray-500">{msg.email || '—'}</td>
                      <td className="px-5 py-3.5 text-gray-500">{msg.phone || '—'}</td>
                      <td className="px-5 py-3.5 text-gray-500 truncate max-w-[200px]">{msg.subject || '—'}</td>
                      <td className="px-5 py-3.5 text-gray-400 text-xs">{msg.created_at ? new Date(msg.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : ''}</td>
                      <td className="px-5 py-3.5 text-right">
                        <div className="flex items-center justify-end gap-1">
                          <a href={`/admin/messages/${msg.id}`} className="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg font-medium transition-colors">View</a>
                          {msg.status === 'new' && (
                            <button onClick={() => router.post(`/admin/messages/${msg.id}/read`)} className="px-2 py-1 text-xs bg-blue-50 hover:bg-blue-100 text-blue-600 rounded-lg font-medium transition-colors">Read</button>
                          )}
                          <button onClick={() => router.post(`/admin/messages/${msg.id}/archive`)} className="px-2 py-1 text-xs bg-gray-50 hover:bg-gray-100 text-gray-500 rounded-lg font-medium transition-colors">Archive</button>
                          <button onClick={() => { window.showConfirm('Delete?', () => router.delete(`/admin/messages/${msg.id}`)); }}
                            className="px-2 py-1 text-xs bg-red-50 hover:bg-red-100 text-red-600 rounded-lg font-medium transition-colors">Delete</button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {messages?.links && messages.links.length > 3 && (
              <div className="p-4 border-t border-gray-50 flex flex-wrap gap-1.5 items-center justify-center">
                {messages.links.map((link, i) => (
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