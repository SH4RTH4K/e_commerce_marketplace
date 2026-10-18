import { useState } from 'react';
import { InventoryDialog } from './InventoryDialogs';

export default function QuickSupplierDialog({ onClose, onCreated }) {
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState({});
  const submit = async event => {
    event.preventDefault();
    setBusy(true);
    setErrors({});
    try {
      const response = await fetch('/admin/suppliers', {
        method: 'POST', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json',
          'X-XSRF-TOKEN': decodeURIComponent(document.cookie.split('; ').find(item => item.startsWith('XSRF-TOKEN='))?.slice(11) || '') },
        body: JSON.stringify(Object.fromEntries(new FormData(event.currentTarget))),
      });
      const result = await response.json();
      if (!response.ok) { setErrors(result.errors || { form: [result.message || 'Supplier could not be saved.'] }); return; }
      onCreated(result.supplier);
    } catch { setErrors({ form: ['Supplier could not be saved. Please try again.'] }); }
    finally { setBusy(false); }
  };
  return <InventoryDialog title="Add supplier" onClose={onClose} busy={busy}>
    <form onSubmit={submit} className="space-y-4 p-5">
      <p className="text-sm text-gray-500">Your product, images, and unsaved changes will stay in this form.</p>
      <label className="block text-sm font-medium text-gray-700">Supplier name<input name="name" required maxLength={120} className="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2.5" /></label>
      <label className="block text-sm font-medium text-gray-700">Supplier type<select name="supplier_type" className="mt-1 w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5"><option value="local">Local</option><option value="wholesale">Wholesale</option><option value="manufacturer">Manufacturer</option><option value="dropshipping">Dropshipping</option><option value="other">Other</option></select></label>
      <label className="block text-sm font-medium text-gray-700">Contact person<input name="contact_person" maxLength={120} className="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2.5" /></label>
      <label className="block text-sm font-medium text-gray-700">Mobile number<input name="mobile" maxLength={40} type="tel" className="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2.5" /></label>
      {Object.entries(errors).map(([key, messages]) => <p key={key} role="alert" className="text-sm text-red-600">{Array.isArray(messages) ? messages[0] : messages}</p>)}
      <div className="flex justify-end gap-3"><button type="button" disabled={busy} onClick={onClose} className="rounded-xl bg-gray-100 px-4 py-2.5 text-sm">Cancel</button><button disabled={busy} className="rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{busy ? 'Saving…' : 'Save supplier'}</button></div>
    </form>
  </InventoryDialog>;
}
