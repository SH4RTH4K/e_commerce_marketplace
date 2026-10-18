import DropshippingSubpage from './Subpage';
import { router } from '@inertiajs/react';

const statusColors = { completed: 'bg-green-50 text-green-700', running: 'bg-blue-50 text-blue-700', queued: 'bg-amber-50 text-amber-700', failed: 'bg-red-50 text-red-700', completed_with_errors: 'bg-orange-50 text-orange-700' };

function Status({ value }) {
  return <span className={`inline-flex rounded-full px-2 py-1 text-[11px] font-bold ${statusColors[value] || 'bg-gray-100 text-gray-600'}`}>{String(value || 'unknown').replaceAll('_', ' ')}</span>;
}

export default function SyncRuns({ runs = {}, suppliers = [], filters = {} }) {
  const rows = runs.data || [];
  const submit = event => { event.preventDefault(); const data = Object.fromEntries(new FormData(event.currentTarget)); router.get('/admin/dropshipping/sync-runs', data, { preserveState: true, replace: true }); };
  return <DropshippingSubpage title="Sync runs" description="Review supplier catalog, price, stock, and product synchronization history.">
    <section className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
      <form onSubmit={submit} className="grid grid-cols-1 gap-3 md:grid-cols-[1fr_260px_auto]">
        <input name="q" defaultValue={filters.q || ''} placeholder="Search supplier name or key" className="rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-orange-400 focus:ring-orange-200" />
        <select name="supplier_id" defaultValue={filters.supplier_id || ''} className="rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm"><option value="">All dropshipping suppliers</option>{suppliers.map(supplier => <option key={supplier.id} value={supplier.id}>{supplier.name}</option>)}</select>
        <button className="rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-orange-600">Search</button>
      </form>
    </section>
    <section className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
      <div className="border-b border-gray-100 px-5 py-4"><h2 className="font-bold text-gray-900">Sync history</h2><p className="mt-1 text-xs text-gray-500">{runs.total || 0} run(s) found</p></div>
      <div className="overflow-x-auto"><table className="w-full text-sm"><thead><tr className="bg-gray-50 text-left text-[10px] uppercase tracking-wider text-gray-400"><th className="px-5 py-3">Run</th><th className="px-5 py-3">Supplier</th><th className="px-5 py-3">Type</th><th className="px-5 py-3">Status</th><th className="px-5 py-3">Progress</th><th className="px-5 py-3">Started</th><th className="px-5 py-3 text-right">Action</th></tr></thead><tbody className="divide-y divide-gray-50">{rows.length === 0 ? <tr><td colSpan="7" className="px-5 py-12 text-center text-sm text-gray-400">No sync runs match your search.</td></tr> : rows.map(run => <tr key={run.id}><td className="px-5 py-4 font-mono text-xs text-gray-500">#{run.id}</td><td className="px-5 py-4"><p className="font-semibold text-gray-800">{run.supplier?.name || 'Unknown supplier'}</p><p className="text-xs text-gray-400">{run.supplier?.key}</p></td><td className="px-5 py-4 text-xs text-gray-600">{String(run.type || '').replaceAll('_', ' ')}</td><td className="px-5 py-4"><Status value={run.status} /></td><td className="px-5 py-4 text-xs text-gray-500">{run.processed_items || 0} / {run.total_items ?? '—'} items</td><td className="px-5 py-4 text-xs text-gray-500">{run.started_at ? new Date(run.started_at).toLocaleString() : '—'}</td><td className="px-5 py-4 text-right">{['queued', 'running'].includes(run.status) && <button onClick={() => router.post(`/admin/dropshipping/runs/${run.id}/cancel`, {}, { preserveScroll: true })} className="rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700">Cancel</button>}</td></tr>)}</tbody></table></div>
      {runs.links?.length > 3 && <div className="flex flex-wrap gap-2 border-t border-gray-100 px-5 py-4">{runs.links.map((link, index) => <button key={index} disabled={!link.url} onClick={() => link.url && router.get(link.url, {}, { preserveState: true, replace: true })} className={`rounded-lg px-3 py-1.5 text-xs ${link.active ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600'} ${!link.url ? 'opacity-40' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div>}
    </section>
  </DropshippingSubpage>;
}
