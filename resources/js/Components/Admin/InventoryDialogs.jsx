import { useEffect, useRef, useState } from 'react';
import { useForm } from '@inertiajs/react';

export function InventoryDialog({ title, children, onClose, busy = false }) {
  const container = useRef(null);
  useEffect(() => {
    const previous = document.activeElement;
    const overflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    container.current?.focus();
    const keydown = event => {
      if (event.key === 'Escape' && !busy) onClose();
      if (event.key !== 'Tab') return;
      const elements = [...container.current.querySelectorAll('button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), a[href]')];
      const first = elements[0]; const last = elements.at(-1);
      if (!first) { event.preventDefault(); return; }
      if (event.shiftKey && (document.activeElement === first || document.activeElement === container.current)) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && (document.activeElement === last || document.activeElement === container.current)) { event.preventDefault(); first.focus(); }
    };
    document.addEventListener('keydown', keydown);
    return () => { document.body.style.overflow = overflow; document.removeEventListener('keydown', keydown); previous?.focus?.(); };
  }, [busy, onClose]);
  return <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-3 sm:p-6" onClick={() => !busy && onClose()}>
    <section ref={container} tabIndex={-1} role="dialog" aria-modal="true" aria-labelledby="inventory-dialog-title" onClick={event => event.stopPropagation()}
      className="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl outline-none">
      <div className="flex items-center justify-between gap-4 px-5 py-4 border-b border-gray-100">
        <h2 id="inventory-dialog-title" className="text-lg font-bold text-slate-900">{title}</h2>
        <button type="button" onClick={onClose} disabled={busy} aria-label="Close dialog" className="rounded-lg px-3 py-1.5 text-gray-500 hover:bg-gray-100 disabled:opacity-40">✕</button>
      </div>
      <div className="p-5">{children}</div>
    </section>
  </div>;
}

export function StockAdjustmentDialog({ products, onClose, onSaved }) {
  const form = useForm({
    items: products.map(product => ({ product_id: product.id, expected_stock: Number(product.stock_quantity) })),
    operation: 'add', quantity: 1, reason: 'Restock', protect_supplier_stock: true,
  });
  const amount = Number(form.data.quantity);
  const preview = products.map(product => ({ ...product, next: form.data.operation === 'set' ? amount : Number(product.stock_quantity) + (form.data.operation === 'remove' ? -amount : amount) }));
  const valid = form.data.quantity !== '' && Number.isInteger(amount) && amount >= (form.data.operation === 'set' ? 0 : 1)
    && preview.every(product => product.next >= 0 && product.next <= 4294967295) && form.data.reason.trim().length > 0;
  const supplierProducts = products.some(product => product.stock_source !== 'local');
  const submit = event => {
    event.preventDefault();
    if (!valid || form.processing) return;
    form.post('/admin/inventory/adjust', { preserveScroll: true, onSuccess: onSaved });
  };
  return <InventoryDialog title={products.length === 1 ? 'Adjust product stock' : `Adjust ${products.length} products`} onClose={onClose} busy={form.processing}>
    <form onSubmit={submit} className="space-y-5">
      <p className="text-sm text-gray-500">Review each quantity before saving. Every adjustment is recorded with your name and reason.</p>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <label className="text-sm font-medium text-gray-700">Adjustment type
          <select value={form.data.operation} onChange={event => { const operation = event.target.value; form.setData(current => ({ ...current, operation, reason: operation === 'set' ? 'Inventory count correction' : operation === 'remove' ? 'Damaged or missing units' : 'Restock' })); }} disabled={form.processing} className="mt-1.5 w-full border border-gray-200 rounded-xl px-3 py-2.5">
            <option value="add">Add units</option><option value="remove">Remove units</option><option value="set">Set exact quantity</option>
          </select>
        </label>
        <label className="text-sm font-medium text-gray-700">{form.data.operation === 'set' ? 'New quantity for each product' : 'Units per product'}
          <input type="number" min={form.data.operation === 'set' ? 0 : 1} max="4294967295" step="1" required value={form.data.quantity} disabled={form.processing}
            onChange={event => form.setData('quantity', event.target.value)} className="mt-1.5 w-full border border-gray-200 rounded-xl px-3 py-2.5" />
        </label>
      </div>
      <div className="border border-gray-100 rounded-xl max-h-52 overflow-y-auto">
        <table className="w-full text-sm"><thead className="bg-gray-50 text-gray-500"><tr><th className="p-3 text-left">Product</th><th className="p-3 text-right">Current</th><th className="p-3 text-right">After saving</th></tr></thead>
          <tbody>{preview.map(product => <tr key={product.id} className="border-t border-gray-100"><td className="p-3"><p className="font-medium text-gray-800">{product.name}</p><p className="text-xs text-gray-400">SKU: {product.sku || '—'}</p></td><td className="p-3 text-right tabular-nums">{product.stock_quantity}</td><td className={`p-3 text-right font-bold tabular-nums ${product.next < 0 ? 'text-red-600' : 'text-orange-600'}`}>{Number.isFinite(product.next) ? product.next : '—'}</td></tr>)}</tbody>
        </table>
      </div>
      {products.some(product => product.has_variants) && <p className="text-xs text-gray-500">This adjusts total product availability. Manage individual variant options in the product editor.</p>}
      <label className="block text-sm font-medium text-gray-700">Reason / reference
        <textarea value={form.data.reason} onChange={event => form.setData('reason', event.target.value)} maxLength={500} required rows={2} disabled={form.processing} className="mt-1.5 w-full border border-gray-200 rounded-xl px-3 py-2.5" placeholder="Restock, stock count, damaged units, or purchase reference" />
      </label>
      {supplierProducts && <div className="bg-blue-50 border border-blue-100 rounded-xl p-3">
        <label className="flex items-start gap-2 text-sm text-blue-900"><input type="checkbox" checked={form.data.protect_supplier_stock} onChange={event => form.setData('protect_supplier_stock', event.target.checked)} disabled={form.processing} className="mt-1" />Pause supplier stock sync for these products</label>
        <p className="text-xs text-blue-700 mt-1.5 ml-5">{form.data.protect_supplier_stock ? 'The manual quantities will be kept. You can resume supplier sync from inventory later.' : 'The next enabled supplier sync may replace these manual quantities.'}</p>
      </div>}
      {Object.values(form.errors).map((error, index) => <p key={index} role="alert" className="text-sm text-red-600">{error}</p>)}
      <div className="flex justify-end gap-3 pt-2 border-t border-gray-100">
        <button type="button" onClick={onClose} disabled={form.processing} className="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-medium">Cancel</button>
        <button type="submit" disabled={!valid || form.processing} className="px-5 py-2.5 bg-orange-500 hover:bg-orange-600 disabled:opacity-50 rounded-xl text-sm font-semibold text-white">{form.processing ? 'Saving…' : 'Save adjustment'}</button>
      </div>
    </form>
  </InventoryDialog>;
}

export function AdjustmentHistoryDialog({ product, onClose }) {
  const [page, setPage] = useState(1); const [retry, setRetry] = useState(0);
  const [history, setHistory] = useState(null); const [error, setError] = useState(''); const [loading, setLoading] = useState(true);
  useEffect(() => {
    const controller = new AbortController(); setLoading(true); setError('');
    fetch(`/admin/inventory/${product.id}/history?page=${page}`, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: controller.signal })
      .then(async response => { if (response.status === 401 || (response.redirected && response.url.includes('/login'))) throw new Error('Your session expired. Refresh and sign in again.'); if (!response.ok) throw new Error('Could not load adjustment history. Please retry.'); const result = await response.json(); if (!Array.isArray(result.data)) throw new Error('Your session may have expired. Refresh and sign in again.'); if (!controller.signal.aborted) setHistory(result); })
      .catch(problem => { if (!controller.signal.aborted) setError(problem.message); })
      .finally(() => { if (!controller.signal.aborted) setLoading(false); });
    return () => controller.abort();
  }, [product.id, page, retry]);
  return <InventoryDialog title="Adjustment history" onClose={onClose}>
    <p className="font-semibold text-slate-900 mb-1">{product.name}</p><p className="text-xs text-gray-500 mb-4">Admin inventory adjustments are recorded here. Order reservations and supplier syncs are not included.</p>
    {loading ? <p className="py-8 text-center text-gray-500">Loading history…</p> : error ? <div role="alert"><p className="text-red-600">{error}</p><button onClick={() => setRetry(value => value + 1)} className="mt-2 text-orange-600 font-semibold">Retry</button></div>
      : !history?.data.length ? <p className="py-8 text-center text-gray-500">No recorded adjustments yet.</p> : <div className="space-y-3">{history.data.map(entry => <article key={entry.id} className="border border-gray-100 rounded-xl p-3">
        <div className="flex justify-between gap-3"><span className="font-semibold text-gray-800">{entry.before} → {entry.after} units</span><span className={`font-semibold ${entry.change < 0 ? 'text-red-600' : 'text-green-700'}`}>{entry.change > 0 ? '+' : ''}{entry.change}</span></div>
        <p className="text-sm text-gray-700 mt-1">{entry.reason}</p>{entry.supplier_sync_paused && <p className="text-xs text-blue-600 mt-1">Supplier stock sync paused</p>}
        <p className="text-xs text-gray-400 mt-2">{entry.actor} · {entry.created_at} (Dhaka)</p>
      </article>)}<div className="flex justify-between items-center pt-2 text-sm"><button disabled={!history.prev_page_url} onClick={() => setPage(value => value - 1)} className="disabled:opacity-30">Previous</button><span>Page {history.current_page} of {history.last_page}</span><button disabled={!history.next_page_url} onClick={() => setPage(value => value + 1)} className="disabled:opacity-30">Next</button></div></div>}
  </InventoryDialog>;
}

export function ResumeStockSyncDialog({ product, onClose, onSaved }) {
  const form = useForm({});
  return <InventoryDialog title="Resume supplier stock sync" onClose={onClose} busy={form.processing}>
    <p className="text-sm text-gray-700 mb-4">For <strong>{product.name}</strong>, the next supplier sync will refresh stock using supplier availability. The current quantity stays at {product.stock_quantity} until a sync runs.</p>
    {Object.values(form.errors).map((message, index) => <p key={index} role="alert" className="text-red-600 text-sm mb-3">{message}</p>)}
    <div className="flex justify-end gap-3"><button onClick={onClose} disabled={form.processing} className="px-4 py-2 border rounded-xl">Cancel</button><button disabled={form.processing} onClick={() => form.post(`/admin/inventory/${product.id}/resume-sync`, { preserveScroll: true, onSuccess: onSaved })} className="px-4 py-2 bg-orange-500 text-white rounded-xl font-semibold disabled:opacity-50">{form.processing ? 'Saving…' : 'Resume sync'}</button></div>
  </InventoryDialog>;
}
