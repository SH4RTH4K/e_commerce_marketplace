import { router } from '@inertiajs/react';
import { useState } from 'react';

export const labels = { local: 'Local', wholesale: 'Wholesale', manufacturer: 'Manufacturer', dropshipping: 'Dropshipping', other: 'Other' };
export const inputClass = 'w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-orange-400 focus:ring-orange-200';
export const buttonClass = 'rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-200';
export const formatDate = value => value ? new Date(value).toLocaleString() : '—';

export function Pagination({ page }) {
  if (!page?.links || page.last_page <= 1) return null;
  return <nav aria-label="Pagination" className="flex flex-wrap items-center gap-2 border-t border-gray-100 px-5 py-4">
    <span className="mr-2 text-xs text-gray-500">{page.from}–{page.to} of {page.total}</span>
    {page.links.map((link, index) => <button key={index} type="button" disabled={!link.url}
      onClick={() => router.get(link.url, {}, { preserveState: true, preserveScroll: true, replace: true })}
      aria-current={link.active ? 'page' : undefined}
      className={`rounded-lg px-3 py-1.5 text-xs ${link.active ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600'} disabled:opacity-40`}
      dangerouslySetInnerHTML={{ __html: link.label }} />)}
  </nav>;
}

export function Status({ active }) {
  return <span className={`rounded-full px-2 py-1 text-xs font-semibold ${active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'}`}>{active ? 'Active' : 'Inactive'}</span>;
}

export function ToggleSupplier({ supplier, className = buttonClass }) {
  const [processing, setProcessing] = useState(false);
  const toggle = () => {
    const proceed = () => router.patch(`/admin/suppliers/${supplier.id}/toggle`, {}, { preserveScroll: true, onStart: () => setProcessing(true), onFinish: () => setProcessing(false) });
    if (supplier.is_active) {
      const message = `Deactivate ${supplier.name}? Existing product and inventory references will be retained. New product assignments will be blocked while inactive.`;
      if (window.confirm(message)) proceed();
    } else proceed();
  };
  return <button type="button" className={`${className} disabled:opacity-50`} disabled={processing} onClick={toggle}>{supplier.is_active ? 'Deactivate' : 'Activate'}</button>;
}

export function SupplierFilters({ filters, types, action = '/admin/suppliers', supplierOptions }) {
  const submit = event => {
    event.preventDefault();
    router.get(action, Object.fromEntries(new FormData(event.currentTarget)), { preserveState: true, replace: true });
  };
  return <form onSubmit={submit} className="flex flex-wrap items-end gap-3 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
    <label className="min-w-48 flex-1 text-xs font-medium text-gray-600">Search
      <input name="q" defaultValue={filters.q || ''} placeholder="Name, code, contact, phone or email" className={`mt-1 ${inputClass}`} />
    </label>
    {supplierOptions && <label className="min-w-48 flex-1 text-xs font-medium text-gray-600">Supplier
      <select name="supplier_id" defaultValue={filters.supplier_id || ''} className={`mt-1 bg-white ${inputClass}`}>
        <option value="">All suppliers</option>{supplierOptions.map(supplier => <option key={supplier.id} value={supplier.id}>{supplier.name} {supplier.supplier_code ? `(${supplier.supplier_code})` : ''}</option>)}
      </select>
    </label>}
    <label className="min-w-36 text-xs font-medium text-gray-600">Type
      <select name="type" defaultValue={filters.type || ''} className={`mt-1 bg-white ${inputClass}`}><option value="">All types</option>{types.map(type => <option key={type} value={type}>{labels[type] || type}</option>)}</select>
    </label>
    <label className="min-w-32 text-xs font-medium text-gray-600">Status
      <select name="status" defaultValue={filters.status || ''} className={`mt-1 bg-white ${inputClass}`}><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
    </label>
    <button className="rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white">Search</button>
    <a href={action} className={buttonClass}>Reset</a>
  </form>;
}
