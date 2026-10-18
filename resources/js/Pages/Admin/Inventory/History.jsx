import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const movementLabels = { opening: 'Opening stock', stock_in: 'Stock in / Receive', stock_out: 'Stock out', adjustment: 'Adjustment', return_in: 'Return in', damaged: 'Damaged / Lost', sync_resume: 'Resume supplier sync', add: 'Stock in', remove: 'Stock out', set: 'Adjustment' };
const inputClass = 'w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-200';

export default function StockHistory({ movements, suppliers = [], q = '', supplier = '', stock_scope = '', movement_type = '' }) {
  const applied = { q, supplier: supplier || '', stock_scope, movement_type };
  const [filters, setFilters] = useState(applied);
  const [busy, setBusy] = useState(false);
  useEffect(() => setFilters(applied), [q, supplier, stock_scope, movement_type]);
  const exportUrl = '/admin/inventory/history/export?' + new URLSearchParams(Object.fromEntries(Object.entries(applied).filter(([, value]) => value !== '')));
  const submit = event => {
    event.preventDefault();
    router.get('/admin/inventory/history', filters, { preserveState: true, preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });
  };
  return <>
    <Head title="Stock History" />
    <AdminLayout title="Stock History">
      <div className="max-w-[1600px] space-y-5">
        <header className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div><h1 className="text-2xl font-bold text-slate-900">Stock movement history</h1><p className="mt-1 text-sm text-gray-500">Opening stock, admin movements, and order reservations retain the supplier recorded at the time. API availability changes remain in Dropshipping sync history.</p></div>
          <div className="flex shrink-0 gap-2"><a href="/admin/inventory" className="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold">Inventory</a><a href={exportUrl} className="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white">Export CSV</a></div>
        </header>
        <form onSubmit={submit} className="grid gap-3 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:grid-cols-2 xl:grid-cols-5">
          <label className="text-xs font-medium text-gray-500">Search<input type="search" value={filters.q} onChange={event => setFilters(current => ({ ...current, q: event.target.value }))} placeholder="Product, SKU, or reference" className={`${inputClass} mt-1.5`} /></label>
          <label className="text-xs font-medium text-gray-500">Supplier at movement<select value={filters.supplier} onChange={event => setFilters(current => ({ ...current, supplier: event.target.value }))} className={`${inputClass} mt-1.5`}><option value="">All suppliers</option>{suppliers.map(item => <option key={item.id} value={item.id}>{item.name}{item.supplier_code ? ` (${item.supplier_code})` : ''}</option>)}</select></label>
          <label className="text-xs font-medium text-gray-500">Stock ownership<select value={filters.stock_scope} onChange={event => setFilters(current => ({ ...current, stock_scope: event.target.value }))} className={`${inputClass} mt-1.5`}><option value="">All stock ownership</option><option value="local">Local warehouse stock</option><option value="supplier">Supplier-managed availability</option></select></label>
          <label className="text-xs font-medium text-gray-500">Movement type<select value={filters.movement_type} onChange={event => setFilters(current => ({ ...current, movement_type: event.target.value }))} className={`${inputClass} mt-1.5`}><option value="">All movements</option>{Object.entries(movementLabels).filter(([key]) => !['add', 'remove', 'set'].includes(key)).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></label>
          <div className="flex items-end gap-3"><button disabled={busy} className="rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{busy ? 'Loading...' : 'Apply filters'}</button><a href="/admin/inventory/history" className="py-2.5 text-sm text-gray-500">Reset</a></div>
        </form>
        <section className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm" aria-busy={busy}>
          <div className="border-b border-gray-100 px-5 py-4 font-semibold text-gray-800">{Number(movements.total || 0).toLocaleString()} recorded movements</div>
          <div className="overflow-x-auto"><table className="w-full min-w-[1000px] text-sm">
            <thead className="bg-gray-50 text-left text-xs text-gray-500"><tr>{['Product', 'Supplier', 'Movement', 'Stock balance', 'Reference / Reason', 'Performed by'].map(label => <th key={label} className="px-5 py-3 font-semibold">{label}</th>)}</tr></thead>
            <tbody className="divide-y divide-gray-100">{(movements.data || []).map(item => <tr key={item.id}>
              <td className="px-5 py-4"><p className="font-semibold text-gray-800">{item.product_name}</p><p className="mt-1 text-xs text-gray-400">{item.sku || 'No SKU'} | #{item.id}</p></td>
              <td className="px-5 py-4"><p className="text-gray-700">{item.supplier || 'Own inventory / No supplier'}</p>{item.supplier_code && <p className="mt-1 text-xs text-gray-400">{item.supplier_code}</p>}</td>
              <td className="px-5 py-4"><p className="font-medium text-gray-700">{movementLabels[item.movement_type || item.operation] || item.operation}</p><p className="mt-1 text-xs text-gray-400">{item.stock_scope === 'supplier' ? 'Supplier availability' : 'Local inventory'}</p></td>
              <td className="px-5 py-4 tabular-nums"><p className="font-semibold text-gray-800">{item.before} → {item.after}</p><p className={`mt-1 text-xs ${item.change < 0 ? 'text-red-600' : 'text-green-700'}`}>{item.change > 0 ? '+' : ''}{item.change} units</p></td>
              <td className="max-w-xs px-5 py-4"><p className="font-medium text-gray-700">{item.reference || 'No reference'}</p><p className="mt-1 text-xs text-gray-500">{item.reason}</p></td>
              <td className="px-5 py-4 text-xs text-gray-500"><p>{item.actor}</p><p className="mt-1">{item.created_at} (Dhaka)</p></td>
            </tr>)}{!movements.data?.length && <tr><td colSpan={6} className="px-5 py-12 text-center text-gray-400">No matching stock movements.</td></tr>}</tbody>
          </table></div>
          <footer className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 p-4"><p className="text-xs text-gray-400">{movements.from ? `${movements.from}-${movements.to} of ${movements.total}` : '0 results'}</p><nav aria-label="Stock history pagination" className="flex gap-1">{(movements.links || []).map((link, index) => <button key={index} disabled={!link.url || busy} aria-current={link.active ? 'page' : undefined} onClick={() => router.get(link.url, {}, { preserveScroll: true })} className={`rounded-lg px-3 py-1.5 text-xs disabled:opacity-40 ${link.active ? 'bg-orange-500 font-semibold text-white' : 'border border-gray-200 text-gray-600'}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</nav></footer>
        </section>
      </div>
    </AdminLayout>
  </>;
}
