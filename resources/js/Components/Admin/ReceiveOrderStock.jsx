import { useForm } from '@inertiajs/react';

export default function ReceiveOrderStock({ orderId, item }) {
  const outstanding = Math.max(0, Number(item.sourcing_outstanding) || 0);
  const form = useForm({ quantity: outstanding, expected_outstanding: outstanding, reference: '' });
  const valid = Number.isInteger(Number(form.data.quantity)) && Number(form.data.quantity) >= 1 && Number(form.data.quantity) <= outstanding;
  if (!outstanding) return null;
  return <form onSubmit={event => {
    event.preventDefault();
    form.transform(data => ({ ...data, quantity: Number(data.quantity), expected_outstanding: outstanding }));
    form.post(`/admin/orders/${orderId}/items/${item.id}/receive-stock`, { preserveScroll: true });
  }} className="mt-3 space-y-3 rounded-xl border border-amber-200 bg-amber-50 p-3">
    <p className="text-xs font-semibold text-amber-900">Awaiting sourcing: {outstanding} unit{outstanding === 1 ? '' : 's'}</p>
    <p className="text-xs text-amber-800">Record only goods physically received. These units will be allocated to this order immediately.</p>
    <div className="flex flex-wrap items-end gap-3">
      <label className="text-xs text-gray-700">Quantity received<input aria-label={`Quantity received for ${item.product_name}`} type="number" min="1" max={outstanding} step="1" required value={form.data.quantity} onChange={event => form.setData('quantity', event.target.value)} disabled={form.processing} className="mt-1 block w-24 rounded-lg border border-amber-200 bg-white px-2 py-2 text-sm" /></label>
      <label className="min-w-40 flex-1 text-xs text-gray-700">Receipt reference (optional)<input aria-label={`Receipt reference for ${item.product_name}`} maxLength={120} value={form.data.reference} onChange={event => form.setData('reference', event.target.value)} disabled={form.processing} className="mt-1 block w-full rounded-lg border border-amber-200 bg-white px-2 py-2 text-sm" /></label>
      <button disabled={form.processing || !valid} className="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50">{form.processing ? 'Receiving…' : 'Receive stock for this order'}</button>
    </div>
    {Object.entries(form.errors).map(([key, error]) => <p key={key} role="alert" className="text-xs text-red-600">{error}</p>)}
  </form>;
}
