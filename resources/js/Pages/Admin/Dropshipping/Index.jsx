import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';

const button = 'inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold';
const secondary = `${button} border border-gray-200 bg-white text-gray-700 hover:bg-gray-50`;
const count = value => Number(value || 0).toLocaleString('en-US');

export function setupSteps(supplier) {
  const url = path => `${path}?supplier_id=${supplier.id}`;
  const imported = Number(supplier.imported_products_count || 0);
  const published = Number(supplier.published_products_count || 0);
  return [
    { title: 'Connect supplier', description: 'Save access details and test once.', href: url('/admin/dropshipping/settings'), done: supplier.is_active && supplier.last_connection_status === 'success' },
    { title: 'Prepare prices & categories', description: 'Set your margins and map the categories you will use.', href: url('/admin/dropshipping/pricing'), done: Boolean(supplier.price_field_mapping?.cost_field && supplier.price_field_mapping?.maximum_field && supplier.pricing_rules?.selling && supplier.pricing_rules?.minimum && supplier.pricing_rules?.maximum && supplier.category_mappings_count > 0) },
    { title: 'Choose products', description: 'Browse supplier products and import your selection.', href: url('/admin/dropshipping/products'), done: imported > 0 },
    { title: 'Review & publish', description: 'Check photos, prices and options before publishing.', href: url('/admin/dropshipping/imported'), done: published > 0 && published === imported },
  ];
}

export default function DropshippingIndex({ suppliers = [], selected_supplier_id = null, unconnected_suppliers = [] }) {
  const supplier = suppliers.find(item => Number(item.id) === Number(selected_supplier_id)) || suppliers[0];
  const url = path => supplier ? `${path}?supplier_id=${supplier.id}` : path;
  const steps = supplier ? setupSteps(supplier) : [];
  const imported = Number(supplier?.imported_products_count || 0);
  const published = Number(supplier?.published_products_count || 0);
  const drafts = Math.max(0, imported - published);
  const next = supplier && !supplier.is_active ? { title: 'Enable this supplier', href: url('/admin/dropshipping/settings') }
    : drafts > 0 ? { title: `Review ${count(drafts)} unpublished products`, href: `${url('/admin/dropshipping/imported')}&status=draft` }
    : steps.find(step => !step.done) || { title: 'Browse more supplier products', href: url('/admin/dropshipping/products') };

  return <><Head title="Dropshipping" /><AdminLayout title=""><div className="space-y-5">
    <div className="flex flex-wrap items-start justify-between gap-3"><div><h1 className="text-2xl font-bold text-gray-900">Dropshipping</h1><p className="mt-1 text-sm text-gray-500">Connect a supplier, choose products, then review and publish.</p></div><a href="/admin/suppliers" className={secondary}>Manage supplier details</a></div>
    {unconnected_suppliers.length > 0 && <section className="rounded-2xl border border-orange-200 bg-orange-50 p-5">
      <h2 className="font-bold text-gray-900">Suppliers ready for API setup</h2><p className="mt-1 text-sm text-gray-600">These suppliers are saved with type Dropshipping. Add an API connection if you want to import and sync their products automatically. You can also keep managing their products manually.</p>
      <div className="mt-4 space-y-3">{unconnected_suppliers.map(item => <div key={item.id} className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white p-4"><div><strong className="text-gray-900">{item.name}</strong><p className="mt-1 text-xs text-gray-500">{item.supplier_code} · API not configured</p></div><a href={`/admin/dropshipping/settings?connect_supplier_id=${item.id}`} className={`${button} bg-orange-500 text-white hover:bg-orange-600`}>Set up API connection →</a></div>)}</div>
    </section>}
    {supplier ? <>
      <section className="rounded-2xl border border-orange-200 bg-white p-5 shadow-sm sm:p-6">
        <div className="flex flex-wrap items-start justify-between gap-4"><div><p className="text-xs font-semibold uppercase tracking-wide text-gray-500">Your dropshipping supplier</p><h2 className="mt-1 text-xl font-bold text-gray-900 sm:text-2xl">{supplier.name}</h2><div className="mt-2 flex flex-wrap items-center gap-2 text-xs"><span className={`rounded-full px-3 py-1 font-semibold ${supplier.is_active && supplier.last_connection_status === 'success' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-800'}`}>{!supplier.is_active ? 'Inactive' : supplier.last_connection_status === 'success' ? 'Connected' : supplier.last_connection_status === 'failed' ? 'Connection needs attention' : 'Connection not tested'}</span><a href={url('/admin/dropshipping/settings')} className="font-semibold text-orange-700 hover:underline">Connection settings</a><a href={`/admin/suppliers/${supplier.id}`} className="text-gray-600 hover:underline">Supplier details</a></div></div>
          {suppliers.length > 1 && <label className="text-xs font-medium text-gray-600">Switch supplier<select aria-label="Switch supplier" value={supplier.id} onChange={event => router.get('/admin/dropshipping', { supplier_id: event.target.value })} className="mt-1 block w-full rounded-xl border-gray-200 bg-white text-sm">{suppliers.map(item => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>}
        </div>
        <div className="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">{[
          ['Supplier catalog', supplier.products_count, '/admin/dropshipping/products'], ['Imported to your store', imported, '/admin/dropshipping/imported'], ['Published products', published, '/admin/dropshipping/imported'],
        ].map(([label, value, path]) => <a key={label} href={url(path) + (label === 'Published products' ? '&status=published' : '')} className="rounded-xl bg-gray-50 p-4 hover:bg-orange-50"><span className="text-sm text-gray-500">{label}</span><strong className="mt-1 block text-2xl text-gray-900">{count(value)}</strong></a>)}</div>
        <div className="mt-5 flex flex-col gap-3 rounded-xl bg-orange-50 p-4 sm:flex-row sm:items-center sm:justify-between"><div><p className="text-xs font-semibold uppercase text-orange-700">Next action</p><p className="mt-1 font-semibold text-gray-900">{next.title}</p><p className="mt-1 text-xs text-gray-600">Your saved connection and imported products are reused.</p></div><a href={next.href} className={`${button} bg-orange-500 text-white hover:bg-orange-600`}>Continue →</a></div>
      </section>
      <section className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm"><h2 className="font-bold text-gray-900">Your setup progress</h2><div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">{steps.map((step, index) => <a key={step.title} href={step.href} className={`rounded-xl border p-4 ${step.done ? 'border-green-200 bg-green-50/40' : 'border-gray-200 hover:border-orange-300'}`}><span className={`inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold ${step.done ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-600'}`}>{step.done ? '✓' : index + 1}</span><strong className="mt-3 block text-sm text-gray-900">{step.title}</strong><span className="mt-1 block text-xs leading-5 text-gray-500">{step.description}</span><span className={`mt-3 block text-xs font-semibold ${step.done ? 'text-green-700' : 'text-orange-700'}`}>{step.done ? 'Complete · Manage' : 'Open step →'}</span></a>)}</div><p className="mt-4 text-xs leading-5 text-gray-500">Map only the categories you plan to import. Product options can be mapped after import when needed.</p><a href={url('/admin/dropshipping/categories')} className="mt-2 inline-block text-sm font-semibold text-orange-700">Open category mapping →</a></section>
      <section className="grid gap-3 sm:grid-cols-3">{[['Browse supplier products', 'Select new products from the saved catalog.', '/admin/dropshipping/products'], ['Manage imported products', 'Review, update or publish store products.', '/admin/dropshipping/imported'], ['View sync history', 'Check progress and investigate failed runs.', '/admin/dropshipping/sync-runs']].map(([title, description, path]) => <a key={path} href={url(path)} className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm hover:border-orange-200"><strong className="text-sm text-gray-900">{title} →</strong><p className="mt-2 text-xs leading-5 text-gray-500">{description}</p></a>)}</section>
    </> : <section className="rounded-2xl border border-gray-100 bg-white p-8 text-center shadow-sm"><h2 className="text-lg font-bold text-gray-900">Connect your first dropshipping supplier</h2><p className="mx-auto mt-2 max-w-md text-sm text-gray-500">Choose an existing business supplier or add a new one, save its API access, then browse products.</p><a href="/admin/dropshipping/settings" className={`${button} mt-5 bg-orange-500 text-white`}>Connect supplier</a></section>}
  </div></AdminLayout></>;
}
