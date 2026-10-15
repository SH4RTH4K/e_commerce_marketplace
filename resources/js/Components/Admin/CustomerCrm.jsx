import { router, useForm } from '@inertiajs/react';
import { InventoryDialog } from '@/Components/Admin/InventoryDialogs';

export const crmInput = 'w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200';
export const crmMoney = value => `৳${Number(value || 0).toLocaleString('en-US', { maximumFractionDigits: 2 })}`;

export function CustomerAccountBadge({ customer }) {
  return <span className={`inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ${!customer.account_id ? 'bg-slate-100 text-slate-600' : customer.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800'}`}>{!customer.account_id ? 'Guest buyer' : customer.is_active ? 'Active account' : 'Inactive account'}</span>;
}

export function CrmPagination({ pagination, label = 'Pagination', onVisit }) {
  return <footer className="flex flex-wrap justify-between items-center gap-3 border-t border-gray-100 p-4">
    <p className="text-xs text-gray-400">{pagination.from ? `${pagination.from}–${pagination.to} of ${Number(pagination.total).toLocaleString()}` : '0 results'}</p>
    <nav aria-label={label} className="flex flex-wrap gap-1">{(pagination.links || []).map((link, index) => <button key={index} type="button" disabled={!link.url} aria-current={link.active ? 'page' : undefined} onClick={() => onVisit ? onVisit(link.url) : router.get(link.url, {}, { preserveState: true, preserveScroll: true })} dangerouslySetInnerHTML={{ __html: link.label }} className={`rounded-lg px-2.5 py-1.5 text-xs font-semibold disabled:opacity-40 ${link.active ? 'bg-orange-500 text-white' : 'bg-gray-50 text-gray-600 hover:bg-gray-100'}`} />)}</nav>
  </footer>;
}

export function CustomerAccountDialog({ customer, onClose }) {
  const active = !customer.is_active;
  const form = useForm({ is_active: active });
  return <InventoryDialog title={active ? 'Activate customer account' : 'Deactivate customer account'} onClose={onClose} busy={form.processing}>
    <p className="text-sm text-gray-600 mb-5">{active ? 'Activate' : 'Deactivate'} the account for <strong>{customer.customer_name}</strong>. {active ? 'Existing orders and customer notes will remain available.' : 'The customer will be signed out and unable to sign in. Existing orders and notes remain available to staff.'}</p>
    {Object.values(form.errors).map((error, index) => <p key={index} role="alert" className="text-red-600 text-sm mb-3">{error}</p>)}
    <div className="flex justify-end gap-3"><button type="button" disabled={form.processing} onClick={onClose} className="rounded-xl border border-gray-200 px-4 py-2.5 text-sm">Cancel</button><button type="button" disabled={form.processing} onClick={() => form.patch(`/admin/customers/${customer.account_id}/toggle`, { preserveScroll: true, onSuccess: onClose })} className="rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{form.processing ? 'Saving…' : active ? 'Activate account' : 'Deactivate account'}</button></div>
  </InventoryDialog>;
}

export function CustomerNotes({ customer, notes, returnTo, orderStatus = 'all' }) {
  const form = useForm({ body: '' });
  const submit = event => {
    event.preventDefault();
    if (!form.data.body.trim() || form.processing) return;
    const query = new URLSearchParams({ return_to: returnTo, order_status: orderStatus });
    form.post(`${customer.profile_url}/notes?${query}`, { preserveScroll: true, onSuccess: () => form.reset('body') });
  };
  return <section className="rounded-2xl border border-gray-100 bg-white shadow-sm overflow-hidden">
    <div className="p-5 border-b border-gray-100"><h2 className="font-semibold text-gray-900">Private staff notes</h2><p className="text-xs text-gray-400 mt-1">Keep support and delivery context here. These notes are not shown to customers.</p></div>
    <form onSubmit={submit} className="p-5 space-y-3"><label className="block text-sm font-medium text-gray-700">Add a note<textarea value={form.data.body} onChange={event => form.setData('body', event.target.value)} maxLength={2000} rows={3} disabled={form.processing} className={`${crmInput} mt-2`} placeholder="Customer request, follow-up, or support context" /></label>{form.errors.body && <p role="alert" className="text-sm text-red-600">{form.errors.body}</p>}<div className="flex justify-between items-center gap-3"><span className="text-xs text-gray-400">{form.data.body.length}/2000</span><button type="submit" disabled={!form.data.body.trim() || form.processing} className="rounded-xl bg-orange-500 px-4 py-2.5 text-xs font-semibold text-white disabled:opacity-40">{form.processing ? 'Saving…' : 'Add private note'}</button></div></form>
    <div className="px-5 pb-5 space-y-3">{!notes.data?.length ? <p className="text-sm text-gray-400">No customer notes yet.</p> : notes.data.map(note => <article key={note.id} className="rounded-xl bg-slate-50 border border-gray-100 p-4"><p className="text-sm text-gray-700 whitespace-pre-wrap break-words">{note.body}</p><p className="text-[11px] text-gray-400 mt-2">{note.author} · {note.created_at} (Dhaka)</p></article>)}</div>
    {notes.last_page > 1 && <CrmPagination pagination={notes} label="Customer notes pagination" />}
  </section>;
}
