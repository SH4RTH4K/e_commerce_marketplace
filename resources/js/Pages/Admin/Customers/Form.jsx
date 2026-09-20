import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router, useForm } from '@inertiajs/react';

const inputClass = 'w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300';

function Field({ label, error, children }) {
  return <div><label className="block text-sm font-medium text-gray-700 mb-1.5">{label}</label>{children}{error && <p className="text-red-500 text-xs mt-1">{error}</p>}</div>;
}

export default function CustomerForm({ customer }) {
  const { data, setData, put, processing, errors } = useForm({
    name: customer.name || '', email: customer.email || '', phone: customer.phone || '',
    address: customer.address || '', city: customer.city || '', postal_code: customer.postal_code || '',
    is_active: customer.is_active ?? true,
  });

  const submit = event => { event.preventDefault(); put(`/admin/customers/${customer.id}`); };
  const deleteCustomer = () => window.showConfirm(`Delete customer account "${customer.name}"? This cannot be undone.`, () => router.delete(`/admin/customers/${customer.id}`));

  return (
    <><Head title={`Edit Customer: ${customer.name}`} /><AdminLayout title="Edit Customer"><form onSubmit={submit} className="max-w-3xl space-y-5">
      <a href="/admin/customers" className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 w-fit">← Customers</a>
      <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 space-y-4">
        <div className="flex items-start justify-between gap-4 pb-3 border-b border-gray-50"><div><h2 className="font-semibold text-gray-900">Customer profile</h2><p className="text-xs text-gray-500 mt-1">Update customer contact and delivery details.</p></div><label className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer shrink-0"><input type="checkbox" checked={data.is_active} onChange={event => setData('is_active', event.target.checked)} className="h-4 w-4 accent-orange-500 rounded" />Active account</label></div>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4"><Field label="Name" error={errors.name}><input value={data.name} onChange={event => setData('name', event.target.value)} className={inputClass} required /></Field><Field label="Email" error={errors.email}><input type="email" value={data.email} onChange={event => setData('email', event.target.value)} className={inputClass} required /></Field><Field label="Phone" error={errors.phone}><input value={data.phone} onChange={event => setData('phone', event.target.value)} className={inputClass} /></Field><Field label="Postal code" error={errors.postal_code}><input value={data.postal_code} onChange={event => setData('postal_code', event.target.value)} className={inputClass} /></Field></div>
        <Field label="Address" error={errors.address}><input value={data.address} onChange={event => setData('address', event.target.value)} className={inputClass} /></Field><Field label="City" error={errors.city}><input value={data.city} onChange={event => setData('city', event.target.value)} className={inputClass} /></Field>
      </div>
      <div className="flex flex-wrap items-center gap-3"><button disabled={processing} className="px-5 py-2.5 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white text-sm font-semibold rounded-xl">{processing ? 'Saving…' : 'Save changes'}</button><button type="button" onClick={deleteCustomer} className="px-4 py-2.5 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-sm font-medium rounded-xl">Delete customer</button></div>
    </form></AdminLayout></>
  );
}
