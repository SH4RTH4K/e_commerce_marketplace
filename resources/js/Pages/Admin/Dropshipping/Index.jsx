import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

function Status({ value }) {
  const colors = {
    success: 'bg-green-50 text-green-700',
    completed: 'bg-green-50 text-green-700',
    running: 'bg-blue-50 text-blue-700',
    queued: 'bg-amber-50 text-amber-700',
    untested: 'bg-gray-100 text-gray-600',
    failed: 'bg-red-50 text-red-700',
    completed_with_errors: 'bg-orange-50 text-orange-700',
    cancelled: 'bg-gray-100 text-gray-600',
  };
  return <span className={`inline-flex px-2 py-1 rounded-full text-[11px] font-bold ${colors[value] || 'bg-gray-100 text-gray-600'}`}>{String(value || 'unknown').replaceAll('_', ' ')}</span>;
}

function post(url) {
  router.post(url, {}, { preserveScroll: true });
}

export default function DropshippingIndex({ suppliers = [], runs = [], available_drivers = [] }) {
  const [editingSupplierId, setEditingSupplierId] = useState(null);
  const primarySupplier = suppliers[0];
  const connectionReady = primarySupplier?.last_connection_status === 'success';
  const mappingReady = Boolean(primarySupplier?.price_field_mapping?.cost_field && primarySupplier?.price_field_mapping?.maximum_field);
  const categoryReady = Boolean(primarySupplier?.categories_count > 0 && primarySupplier?.category_mappings_count >= primarySupplier?.categories_count);
  const variationReady = Boolean(primarySupplier?.variants_count > 0 && primarySupplier?.mapped_variants_count >= primarySupplier?.variants_count);
  const pricingReady = Boolean(primarySupplier?.pricing_rules?.selling && primarySupplier?.pricing_rules?.minimum && primarySupplier?.pricing_rules?.maximum);
  const productsReady = Boolean(primarySupplier?.products_count > 0);
  const workflowSteps = [
    { number: 1, label: 'Connect supplier', description: 'Open API Settings, save access, and confirm the connection.', href: '/admin/dropshipping/settings', done: connectionReady },
    { number: 2, label: 'Map supplier prices', description: 'Choose the API fields for cost and maximum price.', href: '/admin/dropshipping/pricing', done: mappingReady },
    { number: 3, label: 'Map categories', description: 'Load supplier categories and map them to local categories.', href: '/admin/dropshipping/categories', done: categoryReady },
    { number: 4, label: 'Map variations', description: 'Link supplier variants to matching local product options.', href: '/admin/dropshipping/variations', done: variationReady },
    { number: 5, label: 'Set pricing rules', description: 'Save selling, minimum, maximum, discount, and rounding rules.', href: '/admin/dropshipping/pricing', done: pricingReady },
    { number: 6, label: 'Choose products', description: 'Browse the local mirrored catalogue and import selected items.', href: '/admin/dropshipping/products', done: productsReady },
    { number: 7, label: 'Review and publish', description: 'Check imported drafts before publishing them.', href: '/admin/dropshipping/imported', done: false },
  ];

  return (
    <>
      <Head title="Dropshipping Suppliers" />
      <AdminLayout title="">
        <div className="space-y-5">
          <div>
            <h1 className="text-xl font-bold text-gray-900">Dropshipping Suppliers</h1>
            <p className="text-sm text-gray-400 mt-0.5">Local supplier mirror and queued synchronization status.</p>
          </div>

          <div className="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-sm text-amber-800">
            Imported products remain drafts until a merchant reviews and publishes them. Supplier actions run in the queue.
          </div>
          {primarySupplier?.last_connection_status === 'queued' ? <div className="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-sm text-amber-800"><strong className="block mb-1">A previous connection test is still queued.</strong>Click <strong>Test</strong> again to run the connection check immediately.</div> : primarySupplier?.last_connection_status === 'untested' || (primarySupplier && !primarySupplier?.last_connection_status) ? <div className="bg-blue-50 border border-blue-200 rounded-2xl p-4 text-sm text-blue-800"><strong className="block mb-1">Connection test is still pending.</strong>Click <strong>Test</strong>; the result will appear immediately. <a href="/admin/dropshipping/settings" className="font-semibold underline ml-1">Open API Settings</a></div> : primarySupplier?.last_connection_status === 'failed' ? <div className="bg-red-50 border border-red-200 rounded-2xl p-4 text-sm text-red-800"><strong className="block mb-1">Connection test failed.</strong>{primarySupplier.last_connection_message ? <span className="block mt-1">{primarySupplier.last_connection_message}</span> : null}<span className="block mt-1">Check the HTTPS URL, API-key header profile, API key, and secret key, then save the supplier and test again.</span></div> : null}
          {suppliers.length > 0 && runs.length === 0 && <div className="bg-blue-50 border border-blue-200 rounded-2xl p-4 text-sm text-blue-800"><strong className="block mb-1">Supplier is configured, but no sync has completed yet.</strong>After the connection succeeds, choose <strong>Catalog</strong>. The catalog request is queued; keep a queue worker running so products can appear here. <a href="/admin/dropshipping/settings" className="font-semibold underline ml-1">Open API Settings</a></div>}

          <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div className="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3 mb-4"><div><h2 className="font-bold text-gray-900">Product posting workflow</h2><p className="text-sm text-gray-500 mt-1">Complete each step in order. Imported products stay in draft status until review.</p></div><span className="text-xs text-gray-500 bg-gray-50 border border-gray-100 rounded-full px-3 py-1.5">{suppliers.length} supplier(s) configured</span></div>
            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-7 gap-3">{workflowSteps.map(step => <a key={step.number} href={step.href} className={`rounded-xl border p-4 transition ${step.done ? 'border-green-200 bg-green-50/50' : 'border-gray-200 hover:border-orange-300 hover:shadow-sm'}`}><span className={`inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold mb-3 ${step.done ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-600'}`}>{step.done ? '✓' : step.number}</span><strong className="block text-sm text-gray-900">{step.label}</strong><span className="block mt-1 text-xs leading-5 text-gray-500">{step.description}</span></a>)}</div>
            <div className="mt-4 rounded-xl border-l-4 border-orange-400 bg-orange-50 px-4 py-3 text-sm text-orange-900"><strong className="block mb-1">Next action: {primarySupplier ? (!connectionReady ? 'test the connection' : (!mappingReady ? 'map supplier prices' : (!categoryReady ? 'load and map categories' : (!variationReady ? 'map variations' : (!pricingReady ? 'set pricing rules' : (!productsReady ? 'choose products' : 'review and publish')))))) : 'connect your supplier'}</strong><span>{primarySupplier ? (!connectionReady ? 'Your supplier is saved, but the connection has not been confirmed yet.' : (!mappingReady ? 'Choose which supplier API fields contain cost and maximum price.' : (!categoryReady ? 'Run Catalog to load supplier categories, then map them to local categories.' : (!variationReady ? 'Link supplier variants after the supplier product has a local product mapping.' : (!pricingReady ? 'Save the independent selling, minimum, maximum, discount, and rounding rules.' : (!productsReady ? 'Open Supplier Products and import the products you want to review.' : 'Review imported drafts before publishing them.')))))) : 'Save the supplier API settings before browsing or importing products.'}</span></div>
          </section>

          <div id="configured-suppliers" className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
              <h2 className="font-bold text-gray-900">Configured suppliers</h2>
              <span className="text-xs text-gray-400">{suppliers.length} supplier(s)</span>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead><tr className="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-400">
                  <th className="px-5 py-3 text-left">Supplier</th><th className="px-5 py-3 text-left">Driver</th><th className="px-5 py-3 text-left">Connection</th><th className="px-5 py-3 text-left">Mirror</th><th className="px-5 py-3 text-right">Actions</th>
                </tr></thead>
                <tbody className="divide-y divide-gray-50">
                  {suppliers.length === 0 ? <tr><td colSpan="5" className="px-5 py-12 text-center text-sm text-gray-400">No suppliers configured.</td></tr> : suppliers.map(supplier => (
                    <tr key={supplier.id}>
                      <td className="px-5 py-4"><p className="font-semibold text-gray-800">{supplier.name}</p><p className="text-xs text-gray-400">{supplier.key}</p></td>
                      <td className="px-5 py-4"><p className="text-xs font-mono text-gray-600">{supplier.driver_key}</p><Status value={supplier.is_active ? 'active' : 'disabled'} /></td>
                      <td className="px-5 py-4"><Status value={supplier.last_connection_status || 'untested'} /></td>
                      <td className="px-5 py-4 text-xs text-gray-500">{supplier.products_count} product(s)</td>
                      <td className="px-5 py-4"><div className="flex justify-end gap-2 flex-wrap">
                        <button onClick={() => setEditingSupplierId(editingSupplierId === supplier.id ? null : supplier.id)} className="px-2.5 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-xs font-semibold text-gray-700">{editingSupplierId === supplier.id ? 'Close' : 'Edit'}</button>
                        <button onClick={() => post(`/admin/dropshipping/suppliers/${supplier.id}/test`)} className="px-2.5 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-xs font-semibold text-gray-700">Test</button>
                        <button onClick={() => router.patch(`/admin/dropshipping/suppliers/${supplier.id}/toggle`, {}, { preserveScroll: true })} className="px-2.5 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-xs font-semibold text-gray-700">{supplier.is_active ? 'Disable' : 'Enable'}</button>
                        <button onClick={() => post(`/admin/dropshipping/suppliers/${supplier.id}/catalog`)} className="px-2.5 py-1.5 rounded-lg bg-orange-500 hover:bg-orange-600 text-xs font-semibold text-white">Catalog</button>
                        <a href="/admin/dropshipping/pricing" className="px-2.5 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-xs font-semibold text-gray-700">Price setup</a>
                        <a href="/admin/dropshipping/products" className="px-2.5 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-xs font-semibold text-gray-700">Products</a>
                        <button onClick={() => post(`/admin/dropshipping/suppliers/${supplier.id}/price-stock`)} className="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-900 text-xs font-semibold text-white">Price/Stock</button>
                        {editingSupplierId === supplier.id && <form onSubmit={event => { event.preventDefault(); router.patch(`/admin/dropshipping/suppliers/${supplier.id}`, Object.fromEntries(new FormData(event.currentTarget)), { preserveScroll: true, onSuccess: () => setEditingSupplierId(null) }); }} className="basis-full grid grid-cols-1 md:grid-cols-3 gap-2 mt-2">
                          <input name="name" defaultValue={supplier.name} required className="border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs" aria-label="Supplier name" />
                          <select name="driver_key" defaultValue={supplier.driver_key} required className="border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs bg-white" aria-label="Supplier driver">{available_drivers.map(driver => <option key={driver} value={driver}>{driver}</option>)}</select>
                          <input name="base_url" type="url" defaultValue={supplier.base_url || ''} required placeholder="https://supplier.example" className="border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs" aria-label="Supplier base URL" />
                          <input name="api_key" type="password" autoComplete="new-password" placeholder="Replace API key (optional)" className="border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs" aria-label="Replace API key" />
                          <input name="secret_key" type="password" autoComplete="new-password" placeholder="Replace secret key (optional)" className="border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs" aria-label="Replace secret key" />
                          <button className="rounded-lg bg-orange-500 hover:bg-orange-600 px-2.5 py-1.5 text-xs font-semibold text-white">Save changes</button>
                        </form>}
                      </div></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          <div id="recent-sync-runs" className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div className="px-5 py-4 border-b border-gray-100"><h2 className="font-bold text-gray-900">Recent sync runs</h2></div>
            <div className="overflow-x-auto"><table className="w-full text-sm"><thead><tr className="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-400">
              <th className="px-5 py-3 text-left">Run</th><th className="px-5 py-3 text-left">Supplier</th><th className="px-5 py-3 text-left">Status</th><th className="px-5 py-3 text-left">Progress</th><th className="px-5 py-3 text-right">Action</th>
            </tr></thead><tbody className="divide-y divide-gray-50">
              {runs.length === 0 ? <tr><td colSpan="5" className="px-5 py-12 text-center text-sm text-gray-400">No sync runs yet.</td></tr> : runs.map(run => (
                <tr key={run.id}><td className="px-5 py-3 text-xs font-mono text-gray-500">#{run.id} · {run.type}</td><td className="px-5 py-3 text-sm text-gray-700">{run.supplier?.name || '—'}</td><td className="px-5 py-3"><Status value={run.status} /></td><td className="px-5 py-3 text-xs text-gray-500">{run.processed_items || 0} / {run.total_items ?? '—'} items</td><td className="px-5 py-3 text-right">{['queued', 'running'].includes(run.status) && <button onClick={() => post(`/admin/dropshipping/runs/${run.id}/cancel`)} className="px-2.5 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-xs font-semibold text-red-700">Cancel</button>}</td></tr>
              ))}
            </tbody></table></div>
          </div>
        </div>
      </AdminLayout>
    </>
  );
}
