import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import DropshippingSubpage from './Subpage';

function MappingStatus({ status }) {
  if (status === 'mapped') {
    return <span className="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">Mapped</span>;
  }
  if (status === 'ready') {
    return <span className="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Ready to map</span>;
  }
  return <span className="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">Waiting for product import</span>;
}

function CountCard({ label, value, tone }) {
  const tones = {
    gray: 'border-gray-200 bg-white text-gray-900',
    green: 'border-green-200 bg-green-50 text-green-800',
    amber: 'border-amber-200 bg-amber-50 text-amber-800',
    blue: 'border-blue-200 bg-blue-50 text-blue-800',
  };

  return (
    <div className={`rounded-xl border px-4 py-3 ${tones[tone] || tones.gray}`}>
      <p className="text-2xl font-bold">{Number(value || 0).toLocaleString()}</p>
      <p className="mt-0.5 text-xs font-medium">{label}</p>
    </div>
  );
}

export default function VariationMapping({
  variants = [],
  local_variants = [],
  suppliers = [],
  pagination = {},
  filters = {},
  counts = {},
}) {
  const [search, setSearch] = useState(filters.q || '');
  const [supplierId, setSupplierId] = useState(filters.supplier_id ? String(filters.supplier_id) : '');
  const [status, setStatus] = useState(filters.status || 'all');
  const [perPage, setPerPage] = useState(String(filters.per_page || 50));
  const mirrored = Number(counts.total || 0) > 0;

  const filterPayload = (page = 1) => ({
    q: search,
    supplier_id: supplierId,
    status,
    per_page: perPage,
    page,
  });

  const applyFilters = event => {
    event.preventDefault();
    router.get('/admin/dropshipping/variations', filterPayload(), {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    });
  };

  const clearFilters = () => {
    setSearch('');
    setSupplierId('');
    setStatus('all');
    setPerPage('50');
    router.get('/admin/dropshipping/variations', {}, { preserveScroll: true, replace: true });
  };

  const goToPage = page => router.get('/admin/dropshipping/variations', {
    ...filters,
    page,
  }, { preserveScroll: true, preserveState: true });

  const autoMapReady = () => {
    window.showConfirm(
      'Automatically create matching local options and map every ready supplier variation?',
      () => router.post('/admin/dropshipping/variations/auto-map', {
        supplier_id: supplierId || null,
      }, { preserveScroll: true }),
    );
  };

  return (
    <DropshippingSubpage
      title="Variation Mapping"
      description="Review supplier variants, find unmapped records, and link them to their local product options."
    >
      <div className="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
        <strong className="mb-1 block">Why some variations are not mapped</strong>
        <p>Supplier variations can be mapped only after their supplier product is imported or linked to a local product. Imported products are mapped automatically; use “Auto-map ready” for any linked records still waiting.</p>
      </div>

      {mirrored && (
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          <CountCard label="Supplier variations" value={counts.total} tone="gray" />
          <CountCard label="Mapped" value={counts.mapped} tone="green" />
          <CountCard label="Ready to map" value={counts.ready} tone="amber" />
          <CountCard label="Waiting for product import" value={counts.awaiting_import} tone="blue" />
        </div>
      )}

      <form onSubmit={applyFilters} className="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
        <div className="flex flex-wrap items-end gap-3">
          <label className="min-w-[240px] flex-1">
            <span className="mb-1.5 block text-xs font-semibold text-gray-600">Search variations</span>
            <input
              type="search"
              value={search}
              onChange={event => setSearch(event.target.value)}
              placeholder="Product, variant ID, SKU, type, or value"
              className="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-orange-300"
            />
          </label>
          <label className="min-w-[180px]">
            <span className="mb-1.5 block text-xs font-semibold text-gray-600">Supplier</span>
            <select value={supplierId} onChange={event => setSupplierId(event.target.value)} className="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 outline-none">
              <option value="">All suppliers</option>
              {suppliers.map(supplier => <option key={supplier.id} value={supplier.id}>{supplier.name}</option>)}
            </select>
          </label>
          <label className="min-w-[205px]">
            <span className="mb-1.5 block text-xs font-semibold text-gray-600">Mapping status</span>
            <select value={status} onChange={event => setStatus(event.target.value)} className="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 outline-none">
              <option value="all">All variations</option>
              <option value="mapped">Mapped</option>
              <option value="unmapped">All unmapped</option>
              <option value="ready">Ready to map</option>
              <option value="awaiting_import">Waiting for product import</option>
            </select>
          </label>
          <label className="w-[125px]">
            <span className="mb-1.5 block text-xs font-semibold text-gray-600">Rows</span>
            <select value={perPage} onChange={event => setPerPage(event.target.value)} className="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 outline-none">
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>
          </label>
          <button type="submit" className="rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-orange-600">Apply filters</button>
          <button type="button" onClick={clearFilters} className="rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-200">Clear</button>
        </div>
      </form>

      {!mirrored && (
        <section className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
          <h2 className="font-bold text-gray-900">Supplier variants are not loaded yet</h2>
          <p className="mt-1 text-sm text-gray-500">Enable variant mappings in the supplier API profile, save the supplier connection, then run Catalog.</p>
          {suppliers.map(supplier => (
            <div key={supplier.id} className="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-100 p-3">
              <div>
                <p className="font-semibold text-gray-800">{supplier.name}</p>
                <p className="text-xs text-gray-500">{supplier.products_count || 0} mirrored products · {supplier.variants_count || 0} variants · connection {supplier.last_connection_status || 'untested'}</p>
              </div>
              <button disabled={supplier.last_connection_status !== 'success' || !supplier.is_active} onClick={() => router.post(`/admin/dropshipping/suppliers/${supplier.id}/catalog`, {}, { preserveScroll: true })} className="rounded-lg bg-orange-500 px-3 py-2 text-xs font-semibold text-white hover:bg-orange-600 disabled:bg-gray-200 disabled:text-gray-400">Run Catalog</button>
            </div>
          ))}
          <a href="/admin/dropshipping/settings" className="mt-4 inline-block text-sm font-semibold text-orange-600 underline">Open API Settings</a>
        </section>
      )}

      {mirrored && (
        <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
          <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
            <div>
              <h2 className="font-bold text-gray-900">Supplier variations</h2>
              <p className="mt-1 text-xs text-gray-500">Showing {variants.length} on this page from {pagination.total || 0} matching variations.</p>
            </div>
            <div className="flex items-center gap-3">
              <span className="text-xs text-gray-400">{variants.filter(variant => variant.linked).length} mapped on this page</span>
              <button type="button" onClick={autoMapReady} disabled={!counts.ready} className="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-400">Auto-map ready ({counts.ready || 0})</button>
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-400">
                  <th className="px-5 py-3 text-left">Supplier product</th>
                  <th className="px-5 py-3 text-left">Variant</th>
                  <th className="px-5 py-3 text-left">Attributes</th>
                  <th className="px-5 py-3 text-left">Status</th>
                  <th className="px-5 py-3 text-right">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-50">
                {variants.length === 0 ? (
                  <tr><td colSpan="5" className="px-5 py-14 text-center text-sm text-gray-400">No variations match these filters.</td></tr>
                ) : variants.map(variant => {
                  const options = local_variants.filter(localVariant => String(localVariant.product_id) === String(variant.local_product_id));
                  return (
                    <tr key={variant.id} className="align-top hover:bg-gray-50/50">
                      <td className="px-5 py-4 text-xs text-gray-600">
                        <p className="max-w-sm font-medium text-gray-700">{variant.product || '—'}</p>
                        <p className="mt-1 text-[11px] text-gray-400">{variant.supplier || 'Unknown supplier'}</p>
                        <p className="text-[11px] text-gray-400">{variant.local_product_name ? `Local: ${variant.local_product_name}` : 'Local product not imported'}</p>
                      </td>
                      <td className="px-5 py-4 text-xs text-gray-600">
                        <p className="font-mono">{variant.variant_id}</p>
                        {variant.sku && <p className="mt-1 text-[11px] text-gray-400">SKU: {variant.sku}</p>}
                      </td>
                      <td className="px-5 py-4 text-xs text-gray-600">{Object.entries(variant.attributes || {}).map(([key, value]) => `${key}: ${value}`).join(' · ') || 'Unlabelled'}</td>
                      <td className="px-5 py-4"><MappingStatus status={variant.mapping_status} /></td>
                      <td className="px-5 py-4 text-right">
                        {variant.mapping_status === 'awaiting_import' ? (
                          <Link href={`/admin/dropshipping/products?q=${encodeURIComponent(variant.product || '')}`} className="inline-flex rounded-lg bg-blue-50 px-2.5 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100">Find &amp; import product</Link>
                        ) : (
                          <form onSubmit={event => {
                            event.preventDefault();
                            router.post(`/admin/dropshipping/variations/${variant.id}/map`, Object.fromEntries(new FormData(event.currentTarget)), { preserveScroll: true });
                          }} className="flex justify-end gap-2">
                            <select name="product_variant_id" required disabled={options.length === 0} defaultValue={variant.mapped_variant_id || ''} className="max-w-52 rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-xs">
                              <option value="">{options.length ? 'Select local option' : 'Use Auto-map ready'}</option>
                              {options.map(option => <option key={option.id} value={option.id}>{option.type}: {option.value}</option>)}
                            </select>
                            <button disabled={options.length === 0} className="rounded-lg bg-orange-500 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-orange-600 disabled:bg-gray-200 disabled:text-gray-400">{variant.linked ? 'Update' : 'Map'}</button>
                          </form>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          <div className="flex items-center justify-between border-t border-gray-100 px-5 py-4">
            <button disabled={(pagination.current_page || 1) <= 1} onClick={() => goToPage((pagination.current_page || 1) - 1)} className="rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold disabled:opacity-40">Previous</button>
            <span className="text-xs text-gray-500">Page {pagination.current_page || 1} of {pagination.last_page || 1}</span>
            <button disabled={(pagination.current_page || 1) >= (pagination.last_page || 1)} onClick={() => goToPage((pagination.current_page || 1) + 1)} className="rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold disabled:opacity-40">Next</button>
          </div>
        </div>
      )}
    </DropshippingSubpage>
  );
}
