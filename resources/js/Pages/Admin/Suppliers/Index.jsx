import AdminLayout from '@/Layouts/AdminLayout';
import { Head } from '@inertiajs/react';
import { buttonClass, labels, Pagination, Status, SupplierFilters, ToggleSupplier } from './Shared';

export default function SupplierIndex({ suppliers = {}, filters = {}, types = [], canManage = false }) {
  return <><Head title="Suppliers" /><AdminLayout title=""><div className="space-y-5">
    <div className="flex flex-wrap items-center justify-between gap-3"><div><h1 className="text-xl font-bold text-gray-900">Suppliers</h1><p className="mt-1 text-sm text-gray-500">Manage local, wholesale, manufacturer and dropshipping suppliers.</p></div><div className="flex flex-wrap gap-2"><a href="/admin/suppliers/report" className={buttonClass}>Supplier reports</a>{canManage && <a href="/admin/suppliers/create" className="rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-orange-600">Add Supplier</a>}</div></div>
    <SupplierFilters filters={filters} types={types} />
    <section className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
      <div className="border-b border-gray-100 px-5 py-4"><h2 className="font-bold text-gray-900">All Suppliers</h2><p className="mt-1 text-xs text-gray-500">{suppliers.total || 0} supplier(s)</p></div>
      <div className="overflow-x-auto"><table className="w-full text-sm"><thead><tr className="bg-gray-50 text-left text-xs uppercase text-gray-400">{['Code', 'Supplier', 'Type', 'Contact person', 'Mobile', 'Products', 'Status', 'Actions'].map(label => <th key={label} className="whitespace-nowrap px-5 py-3">{label}</th>)}</tr></thead>
        <tbody className="divide-y divide-gray-50">{(suppliers.data || []).map(supplier => <tr key={supplier.id}>
          <td className="whitespace-nowrap px-5 py-4 font-mono text-xs text-gray-500">{supplier.supplier_code}</td>
          <td className="px-5 py-4"><a href={`/admin/suppliers/${supplier.id}`} className="font-semibold text-gray-800 hover:text-orange-600">{supplier.name}</a>{supplier.integration && <p className="mt-1 text-xs text-gray-400">Integration: {supplier.integration}</p>}</td>
          <td className="px-5 py-4 text-gray-600">{labels[supplier.supplier_type] || supplier.supplier_type}</td>
          <td className="px-5 py-4 text-gray-600">{supplier.contact_person || '—'}</td><td className="whitespace-nowrap px-5 py-4 text-gray-600">{supplier.mobile || '—'}</td>
          <td className="px-5 py-4"><a href={`/admin/suppliers/${supplier.id}#products`} className="font-semibold text-orange-600 hover:underline">{supplier.products_count}</a></td>
          <td className="px-5 py-4"><Status active={supplier.is_active} /></td>
          <td className="px-5 py-4"><div className="flex flex-wrap gap-2"><a href={`/admin/suppliers/${supplier.id}`} className={buttonClass}>View</a><a href={`/admin/suppliers/${supplier.id}#products`} className={buttonClass}>Products</a>{canManage && <><a href={`/admin/suppliers/${supplier.id}/edit`} className={buttonClass}>Edit</a><ToggleSupplier supplier={supplier} /></>}</div></td>
        </tr>)}{!suppliers.data?.length && <tr><td colSpan={8} className="px-5 py-12 text-center text-gray-400">No suppliers found.</td></tr>}</tbody>
      </table></div><Pagination page={suppliers} />
    </section>
  </div></AdminLayout></>;
}
