import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm } from '@inertiajs/react';
import { buttonClass, inputClass, labels, Status } from './Shared';

const fields = {
  name: '', supplier_code: '', supplier_type: 'local', contact_person: '', mobile: '', whatsapp: '',
  email: '', website: '', business_address: '', city: '', district: '', division: '', country: 'Bangladesh',
  business_name: '', trade_license: '', tax_vat_bin: '', payment_terms: '', notes: '',
};
const groups = [
  { title: 'Contact details', fields: [['contact_person', 'Contact person'], ['mobile', 'Mobile number', 'tel'], ['whatsapp', 'WhatsApp number', 'tel'], ['email', 'Email address', 'email'], ['website', 'Website', 'url']] },
  { title: 'Business address', fields: [['business_address', 'Street / business address', 'textarea'], ['city', 'City'], ['district', 'District'], ['division', 'Division'], ['country', 'Country']] },
  { title: 'Business information', fields: [['business_name', 'Business/company name'], ['trade_license', 'Trade license number'], ['tax_vat_bin', 'Tax / VAT / BIN'], ['payment_terms', 'Payment terms'], ['notes', 'Notes', 'textarea']] },
];

function Field({ label, error, children }) {
  return <label className="block text-sm text-gray-700"><span className="font-medium">{label}</span>{children}{error && <span role="alert" className="mt-1 block text-xs text-red-600">{error}</span>}</label>;
}

export default function SupplierForm({ supplier, types = [], canManageDropshipping = false }) {
  const editing = Boolean(supplier?.id);
  const initial = Object.fromEntries(Object.entries(fields).map(([key, value]) => [key, supplier?.[key] ?? value]));
  const { data, setData, post, patch, processing, errors } = useForm(initial);
  const submit = event => {
    event.preventDefault();
    editing ? patch(`/admin/suppliers/${supplier.id}`) : post('/admin/suppliers');
  };
  return <><Head title={editing ? 'Edit Supplier' : 'Add Supplier'} /><AdminLayout title="">
    <form onSubmit={submit} className="mx-auto max-w-4xl space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3"><div>
        <h1 className="text-xl font-bold text-gray-900">{editing ? 'Edit Supplier' : 'Add Supplier'}</h1>
        <p className="mt-1 text-sm text-gray-500">Keep supplier contact and business information in one place.</p>
      </div><a href="/admin/suppliers" className={buttonClass}>Back to suppliers</a></div>
      {supplier?.integration && <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-blue-50 p-4 text-sm text-blue-800"><p>Integration: {supplier.integration}. Connection credentials are managed in Dropshipping API Settings.</p>{canManageDropshipping && <a href={`/admin/dropshipping/settings?supplier_id=${supplier.id}`} className="font-semibold underline">Connection settings</a>}</div>}
      <section className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <div className="flex items-center justify-between"><h2 className="font-bold text-gray-900">Basic information</h2>{editing && <Status active={supplier.is_active} />}</div>
        <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
          <Field label="Supplier name *" error={errors.name}><input required maxLength={120} value={data.name} onChange={e => setData('name', e.target.value)} className={`mt-1 ${inputClass}`} /></Field>
          <Field label="Supplier type *" error={errors.supplier_type}><select value={data.supplier_type} onChange={e => setData('supplier_type', e.target.value)} className={`mt-1 bg-white ${inputClass}`}>{types.map(type => <option key={type} value={type}>{labels[type] || type}</option>)}</select></Field>
          <Field label="Supplier code" error={errors.supplier_code}><input maxLength={40} value={data.supplier_code} onChange={e => setData('supplier_code', e.target.value)} placeholder="Generated automatically when left blank" className={`mt-1 ${inputClass}`} />{!editing && <span className="mt-1 block text-xs text-gray-500">A unique SUP code is assigned automatically. You can enter your own code.</span>}</Field>
        </div>
      </section>
      {!supplier?.integration && data.supplier_type === 'dropshipping' && <section className="rounded-2xl border border-orange-100 bg-orange-50 p-5 text-sm text-orange-900">
        <h2 className="font-bold">Dropshipping connection</h2>
        <p className="mt-2">Supplier type describes your business relationship. Manual dropshipping works without an API. For automatic product and stock updates, connect a supported supplier API.</p>
        {!editing ? <p className="mt-3 font-medium">Save this supplier first, then set up its API connection from the supplier details page.</p>
          : !supplier.is_active ? <p className="mt-3 font-medium">Activate this supplier before setting up an API connection.</p>
            : canManageDropshipping ? <a href={`/admin/dropshipping/settings?connect_supplier_id=${supplier.id}`} className="mt-3 inline-flex rounded-xl bg-orange-500 px-4 py-2.5 font-semibold text-white hover:bg-orange-600">Set up API connection</a>
              : <p className="mt-3">An administrator with Dropshipping access can set up the API connection.</p>}
      </section>}
      {groups.map(group => <section key={group.title} className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <h2 className="font-bold text-gray-900">{group.title}</h2>
        <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">{group.fields.map(([key, label, type = 'text']) => <Field key={key} label={label} error={errors[key]}>
          {type === 'textarea' ? <textarea value={data[key]} onChange={e => setData(key, e.target.value)} className={`mt-1 ${inputClass}`} rows={3} />
            : <input type={type} value={data[key]} onChange={e => setData(key, e.target.value)} className={`mt-1 ${inputClass}`} />}
        </Field>)}</div>
      </section>)}
      <div className="flex justify-end gap-3"><a href={editing ? `/admin/suppliers/${supplier.id}` : '/admin/suppliers'} className={buttonClass}>Cancel</a><button disabled={processing} className="rounded-xl bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white hover:bg-orange-600 disabled:opacity-50">{processing ? 'Saving…' : editing ? 'Save changes' : 'Create supplier'}</button></div>
    </form>
  </AdminLayout></>;
}
