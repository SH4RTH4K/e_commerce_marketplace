import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { StockAdjustmentDialog, AdjustmentHistoryDialog, ResumeStockSyncDialog } from '@/Components/Admin/InventoryDialogs';

const inputClass = 'w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200';
function Icon({ path, className = 'w-4 h-4' }) {
  return <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d={path} /></svg>;
}
function StockBadge({ quantity }) {
  quantity = Number(quantity) || 0;
  const label = quantity === 0 ? 'Out of stock' : quantity <= 5 ? 'In stock · low' : 'In stock';
  return <span className={`inline-flex items-center gap-1.5 whitespace-nowrap text-xs font-medium px-2.5 py-1 rounded-full ${quantity === 0 ? 'bg-red-50 text-red-700' : quantity <= 5 ? 'bg-amber-50 text-amber-800' : 'bg-emerald-50 text-emerald-700'}`}><span className="h-1.5 w-1.5 rounded-full bg-current" />{label}</span>;
}
export default function InventoryIndex({ products, categories = [], suppliers = [], q = '', category = '', supplier = '', stock_level = '', sourcing = '', visibility = '', source = '', product_source = '', type = '', order = 'stock_asc', per_page = 20, summary = {}, matching_units = 0, matching_supplier_units = 0, can_adjust = false }) {
  const { props } = usePage();
  const currency = props.app?.currency_symbol || '৳';
  const price = value => value == null ? '—' : `${currency}${Number(value).toLocaleString('en-US', { maximumFractionDigits: 2 })}`;
  const applied = { q, category: category || '', supplier: supplier || '', stock_level, sourcing, visibility, source, product_source, type, order, per_page };
  const [filters, setFilters] = useState(applied);
  const [selected, setSelected] = useState([]); const [dialog, setDialog] = useState(null); const [loading, setLoading] = useState(false);
  const [sourcingAction, setSourcingAction] = useState('');
  const [sourcingErrors, setSourcingErrors] = useState([]);
  const sourcingPending = useRef(false);
  const rows = products.data || [];
  const resultIds = rows.map(product => product.id).join(',');
  useEffect(() => { setFilters(applied); setSelected([]); setSourcingErrors([]); }, [q, category, supplier, stock_level, sourcing, visibility, source, product_source, type, order, per_page, products.current_page]);
  useEffect(() => { setSelected([]); setSourcingErrors([]); }, [resultIds]);
  const visit = (overrides = {}) => {
    if (sourcingPending.current) return;
    setSelected([]);
    const parameters = Object.fromEntries(Object.entries({ ...filters, ...overrides }).filter(([, value]) => value !== '' && value != null));
    router.get('/admin/inventory', parameters, { preserveState: true, preserveScroll: true, onStart: () => setLoading(true), onFinish: () => setLoading(false) });
  };
  const filtered = !!(q || category || supplier || stock_level || sourcing || visibility || source || product_source || type);
  const selectedRows = rows.filter(product => selected.includes(product.id));
  const updateSourcing = action => {
    if (!can_adjust || loading || sourcingPending.current || !selectedRows.length) return;
    sourcingPending.current = true;
    setSourcingAction(action);
    setSourcingErrors([]);
    router.post('/admin/products/bulk', { ids: selectedRows.map(product => product.id), bulk_action: action }, {
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => setSelected([]),
      onError: errors => setSourcingErrors(Object.values(errors).flat()),
      onFinish: () => { sourcingPending.current = false; setSourcingAction(''); },
    });
  };
  const exportUrl = '/admin/inventory/export?' + new URLSearchParams(Object.fromEntries(Object.entries(applied).filter(([, value]) => value !== '' && value != null))).toString();
  const onSaved = () => { setDialog(null); setSelected([]); };
  const cards = [
    { label: 'All products', value: summary.total, level: '', color: 'text-slate-900', note: `${Number(summary.units || 0).toLocaleString()} local warehouse units | ${Number(summary.supplier_units || 0).toLocaleString()} supplier availability` },
    { label: 'Healthy stock', value: summary.in_stock, level: 'in', color: 'text-emerald-700', note: 'More than 5 units' },
    { label: 'Low stock', value: summary.low_stock, level: 'low', color: 'text-amber-700', note: '1–5 units · review replenishment' },
    { label: 'Out of stock', value: summary.out_stock, level: 'out', color: 'text-red-700', note: '0 units · sourcing-enabled products can still be ordered' },
  ];
  const selects = [
    ['category', 'Category', [['', 'All categories'], ...categories.map(item => [item.id, item.name])]],
    ['stock_level', 'Stock level', [['', 'All stock levels'], ['in', 'Healthy (>5)'], ['low', 'Low stock (1–5)'], ['out', 'Out of stock']]],
    ['sourcing', 'Sourcing status', [['', 'All products'], ['enabled', 'Sourcing enabled'], ['disabled', 'Sourcing disabled']]],
    ['visibility', 'Visibility', [['', 'All visibility'], ['published', 'Published'], ['draft', 'Draft']]],
    ['source', 'Stock source', [['', 'All stock sources'], ['local', 'Local inventory'], ['supplier', 'Supplier availability (all)'], ['manual', 'Supplier · manual stock']]],
    ['product_source', 'Product source', [['', 'All product sources'], ['manual', 'Manual'], ['dropshipping', 'Dropshipping'], ['other', 'Other']]],
    ['supplier', 'Supplier', [['', 'All suppliers'], ...suppliers.map(item => [item.id, item.name])]],
    ['type', 'Product type', [['', 'All product types'], ['simple', 'Simple'], ['variable', 'Variable']]],
  ];
  return <>
    <Head title="Inventory" />
    <AdminLayout title="Inventory">
      <div className="space-y-5 max-w-[1600px]">
        <header className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div><p className="text-[11px] text-orange-600 font-semibold uppercase tracking-widest mb-1">Catalog operations</p><h1 className="text-2xl font-bold text-slate-900">Inventory overview</h1><p className="text-sm text-gray-500 mt-1">Review availability, manage stock, and track manual adjustments.</p></div>
          <div className="flex flex-wrap gap-2">
            <a href="/admin/inventory/history" className="inline-flex gap-2 items-center px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50">Stock history</a>
            <a href={exportUrl} className="inline-flex gap-2 items-center px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50"><Icon path="M12 3v12m-4-4 4 4 4-4M4 17v4h16v-4" />Export CSV</a>
            {can_adjust && <a href="/admin/products/create" className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold"><Icon path="M12 5v14M5 12h14" />Add product</a>}
          </div>
        </header>
        <div className="grid grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4">
          {cards.map(card => <button key={card.label} type="button" disabled={!!sourcingAction} onClick={() => visit({ stock_level: card.level })} aria-pressed={stock_level === card.level}
            className={`text-left bg-white border rounded-2xl p-4 sm:p-5 shadow-sm transition hover:shadow-md ${stock_level === card.level ? 'border-orange-300 ring-1 ring-orange-100' : 'border-gray-100'}`}>
            <p className="text-xs font-semibold text-gray-500">{card.label}</p><p className={`text-3xl font-bold mt-2 tabular-nums ${card.color}`}>{Number(card.value || 0).toLocaleString()}</p><p className="text-[11px] text-gray-400 mt-2 leading-relaxed">{card.note}</p>
          </button>)}
        </div>
        <section className="rounded-2xl border border-gray-100 bg-white shadow-sm p-4 sm:p-5">
          <form onSubmit={event => { event.preventDefault(); visit(); }} className="space-y-4">
            <div className="flex flex-col sm:flex-row gap-3">
              <label className="flex-1"><span className="sr-only">Search inventory</span><input type="search" placeholder="Search product name or SKU…" value={filters.q} onChange={event => setFilters(current => ({ ...current, q: event.target.value }))} className={inputClass} /></label>
              <button disabled={loading || !!sourcingAction} className="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold rounded-xl disabled:opacity-50">{loading ? 'Loading…' : 'Apply filters'}</button>
              {filtered && <button type="button" disabled={!!sourcingAction} onClick={() => { if (sourcingPending.current) return; setSelected([]); router.get('/admin/inventory'); }} className="px-3 py-2.5 text-gray-500 text-sm hover:text-gray-900">Reset filters</button>}
            </div>
            <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">{selects.map(([key, label, options]) => <label key={key} className="text-xs font-medium text-gray-500">{label}<select value={filters[key]} onChange={event => setFilters(current => ({ ...current, [key]: event.target.value }))} className={`${inputClass} mt-1.5`}>{options.map(([value, name]) => <option key={value} value={value}>{name}</option>)}</select></label>)}</div>
            <div className="flex flex-wrap justify-between items-center gap-3 border-t border-gray-100 pt-3">
              <p className="text-xs text-gray-400">Overview cards use all filters except stock level.</p>
              <button type="button" onClick={() => visit({ stock_level: 'out', visibility: 'published' })} className="text-xs font-semibold text-orange-600 hover:underline">Review published products with no stock →</button>
            </div>
          </form>
        </section>
        <section className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" aria-busy={loading || !!sourcingAction}>
          <div className="flex flex-wrap items-center justify-between gap-3 p-4 border-b border-gray-100">
            <div><h2 className="font-semibold text-gray-900">{Number(products.total || 0).toLocaleString()} {filtered ? 'matching' : ''} products</h2><p className="text-xs text-gray-400 mt-1">{Number(matching_units).toLocaleString()} local warehouse units | {Number(matching_supplier_units).toLocaleString()} supplier availability in this view</p></div>
            <div className="flex items-center gap-3"><label className="sr-only" htmlFor="inventory-sort">Sort inventory</label><select id="inventory-sort" disabled={!!sourcingAction} value={filters.order} onChange={event => { setFilters(current => ({ ...current, order: event.target.value })); visit({ order: event.target.value }); }} className="border border-gray-200 rounded-lg px-2 py-2 text-xs"><option value="stock_asc">Stock: low to high</option><option value="stock_desc">Stock: high to low</option><option value="updated">Recently updated</option><option value="name_asc">Name: A–Z</option><option value="name_desc">Name: Z–A</option><option value="price_asc">Customer price: low to high</option><option value="price_desc">Customer price: high to low</option></select>
              <label className="text-xs text-gray-500">Rows <select disabled={!!sourcingAction} value={filters.per_page} onChange={event => { setFilters(current => ({ ...current, per_page: event.target.value })); visit({ per_page: event.target.value }); }} className="ml-1 border border-gray-200 rounded-lg px-2 py-2"><option>20</option><option>50</option><option>100</option></select></label>
            </div>
          </div>
          {sourcingErrors.length > 0 && <div role="alert" className="border-b border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">{sourcingErrors.map((error, index) => <p key={index}>{error}</p>)}</div>}
          {can_adjust && selectedRows.length > 0 && <div className="space-y-3 bg-orange-50 border-b border-orange-100 px-4 py-3">
            <div className="flex flex-wrap items-center justify-between gap-3"><span className="text-sm font-semibold text-orange-900">{selectedRows.length} selected on this page</span><div className="flex flex-wrap gap-3">
              <button type="button" disabled={!!sourcingAction} onClick={() => { if (!sourcingPending.current) { setSelected([]); setSourcingErrors([]); } }} className="text-xs text-orange-700 disabled:opacity-50">Clear selection</button>
              <button type="button" disabled={!!sourcingAction || loading} onClick={() => updateSourcing('enable_sourcing')} className="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-50">{sourcingAction === 'enable_sourcing' ? 'Enabling sourcing…' : 'Enable sourcing'}</button>
              <button type="button" disabled={!!sourcingAction || loading} onClick={() => updateSourcing('disable_sourcing')} className="rounded-lg border border-orange-200 bg-white px-3 py-2 text-xs font-semibold text-orange-900 hover:bg-orange-100 disabled:opacity-50">{sourcingAction === 'disable_sourcing' ? 'Disabling sourcing…' : 'Disable sourcing'}</button>
              <button type="button" disabled={!!sourcingAction} onClick={() => { if (!sourcingPending.current) setDialog({ type: 'adjust', products: selectedRows }); }} className="bg-orange-500 text-white rounded-lg px-3 py-2 text-xs font-semibold disabled:opacity-50">Adjust selected stock</button>
            </div></div>
            <p className="text-xs leading-relaxed text-orange-800">When stock reaches zero, sourcing lets customers place an order while availability and delivery timing are confirmed. Actual stock is unchanged.</p>
          </div>}
          <div className="overflow-x-auto">
            <table className="w-full text-sm min-w-[850px]">
              <thead className="bg-gray-50/80 text-gray-500 text-[11px] uppercase tracking-wide"><tr>{can_adjust && <th className="pl-4 py-3 w-10"><input type="checkbox" aria-label="Select this page" disabled={!!sourcingAction || loading} checked={rows.length > 0 && selectedRows.length === rows.length} onChange={event => { if (!sourcingPending.current) setSelected(event.target.checked ? rows.map(product => product.id) : []); }} /></th>}{['Product', 'Customer price', 'Available stock', 'Sourcing status', 'Stock source', 'Updated', 'Actions'].map(label => <th key={label} className={`px-4 py-3 text-left font-semibold ${label === 'Actions' ? 'text-right' : ''}`}>{label}</th>)}</tr></thead>
              <tbody className="divide-y divide-gray-100">{!rows.length ? <tr><td colSpan={can_adjust ? 8 : 7} className="py-16 text-center"><p className="font-semibold text-gray-600">No products found</p><p className="text-sm text-gray-400 mt-1">Try a different search or reset the filters.</p></td></tr> : rows.map(product => <tr key={product.id} className={`hover:bg-gray-50/60 ${selected.includes(product.id) ? 'bg-orange-50/30' : ''}`}>
                {can_adjust && <td className="pl-4 py-4"><input type="checkbox" aria-label={`Select ${product.name}`} disabled={!!sourcingAction || loading} checked={selected.includes(product.id)} onChange={event => { if (!sourcingPending.current) setSelected(current => event.target.checked ? [...current, product.id] : current.filter(id => id !== product.id)); }} /></td>}
                <td className="px-4 py-4 max-w-sm"><div className="flex items-start gap-3"><img src={product.image_url} alt="" className="w-12 h-12 object-contain rounded-xl border border-gray-100 bg-gray-50 shrink-0" /><div className="min-w-0">{can_adjust ? <a href={`/admin/products/${product.id}/edit`} className="font-semibold text-slate-800 hover:text-orange-600 leading-snug line-clamp-2">{product.name}</a> : <p className="font-semibold text-slate-800 leading-snug line-clamp-2">{product.name}</p>}<p className="text-xs text-gray-400 mt-1">SKU: {product.sku || '—'} · {product.category?.name || 'Uncategorized'}</p><div className="flex gap-2 items-center mt-1.5 text-[10px]"><span className={product.is_published ? 'text-emerald-700' : 'text-gray-400'}>{product.is_published ? 'Published' : 'Draft'}</span><span className="text-gray-300">·</span><span className="text-gray-500">{product.has_variants ? `${product.variants_count} variant options` : 'Simple product'}</span></div></div></div></td>
                <td className="px-4 py-4 align-top whitespace-nowrap"><p className="font-bold text-slate-900 tabular-nums">{price(product.price)}</p>{product.sale_price != null && Number(product.sale_price) < Number(product.regular_price) && <p className="text-xs text-gray-400 line-through">{price(product.regular_price)}</p>}<details className="mt-2 text-xs"><summary className="cursor-pointer text-gray-400 hover:text-orange-600">Price details</summary><dl className="space-y-1 mt-2 text-gray-500">{[['Regular', product.regular_price], ['Selling', product.selling_price], ['Discounted', product.sale_price], ['Minimum', product.minimum_price], ['Maximum', product.maximum_price]].map(([label, value]) => <div key={label} className="flex justify-between gap-3"><dt>{label}</dt><dd>{value == null ? (label === 'Discounted' ? 'No discount' : 'Not set') : price(value)}</dd></div>)}</dl>{product.price_details_source && <p className="mt-2 max-w-40 whitespace-normal text-[10px] text-gray-400">{product.price_details_source === 'supplier_snapshot' ? 'Includes prices from last supplier calculation.' : 'Includes estimates from current supplier rules.'}</p>}</details></td>
                <td className="px-4 py-4 align-top"><p className="text-xl font-bold text-slate-800 tabular-nums mb-1.5">{Number(product.available_stock_quantity ?? product.stock_quantity).toLocaleString()}</p><StockBadge quantity={product.available_stock_quantity ?? product.stock_quantity} /><p className="mt-2 text-xs text-gray-500">Current stock: {Number(product.stock_quantity || 0).toLocaleString()}</p><p className="mt-1 text-xs text-gray-500">Locally received: {Number(product.sourced_stock_quantity || 0).toLocaleString()}</p>{product.supplier_stock_quantity != null && <p className="mt-1 text-xs text-gray-500">Supplier availability: {Number(product.supplier_stock_quantity).toLocaleString()}</p>}</td>
                <td className="px-4 py-4 align-top">{product.allow_backorder ? <span className="inline-flex whitespace-nowrap rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">Enabled</span> : <span className="inline-flex whitespace-nowrap rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500">Disabled</span>}</td>
                <td className="px-4 py-4 align-top"><p className={`text-xs font-semibold ${product.stock_source === 'supplier' ? 'text-blue-700' : 'text-slate-600'}`}>{product.stock_source === 'supplier' ? 'Supplier-managed availability' : product.stock_type === 'supplier' ? 'Supplier availability / manual override' : 'Local inventory'}</p><p className="text-xs text-gray-400 mt-1">{product.supplier || 'Own inventory / No supplier'}</p><p className="text-[10px] text-gray-400 mt-1 capitalize">Source: {product.source_type || 'manual'}</p>{product.last_synced && <p className="text-[10px] text-gray-400 mt-1">Last sync: {product.last_synced}</p>}{can_adjust && product.can_resume_sync && <button disabled={!!sourcingAction} onClick={() => { if (!sourcingPending.current) setDialog({ type: 'resume', product }); }} className="text-xs text-orange-600 hover:underline mt-2">Resume supplier sync</button>}</td>
                <td className="px-4 py-4 align-top text-xs text-gray-400 min-w-28">{product.updated_at || '—'}</td>
                <td className="px-4 py-4 align-top text-right"><div className="flex flex-col gap-2 items-end">{can_adjust && <button disabled={!!sourcingAction} onClick={() => { if (!sourcingPending.current) setDialog({ type: 'adjust', products: [product] }); }} aria-label={`Adjust stock for ${product.name}`} className="px-3 py-1.5 rounded-lg bg-slate-900 text-white text-xs font-semibold hover:bg-slate-800 whitespace-nowrap">Adjust stock</button>}<button onClick={() => setDialog({ type: 'history', product })} aria-label={`Adjustment history for ${product.name}`} className="text-xs text-gray-500 hover:text-orange-600">History</button>{product.is_published && <a href={`/product/${product.url_key || product.slug}`} target="_blank" rel="noopener noreferrer" className="text-xs text-gray-400 hover:text-blue-600">View product ↗</a>}</div></td>
              </tr>)}</tbody>
            </table>
          </div>
          <footer className="border-t border-gray-100 p-4 flex flex-wrap justify-between items-center gap-3"><p className="text-xs text-gray-400">{products.from ? `${products.from}–${products.to} of ${Number(products.total).toLocaleString()}` : '0 results'}</p><nav aria-label="Inventory pagination" className="flex flex-wrap gap-1">{(products.links || []).map((link, index) => <button key={index} disabled={!link.url || loading || !!sourcingAction} aria-current={link.active ? 'page' : undefined} onClick={() => { if (sourcingPending.current) return; setSelected([]); router.get(link.url, {}, { preserveScroll: true }); }} className={`min-w-8 px-2.5 py-1.5 rounded-lg text-xs disabled:opacity-40 ${link.active ? 'bg-orange-500 text-white font-semibold' : 'border border-gray-200 text-gray-600 hover:bg-gray-50'}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</nav></footer>
        </section>
      </div>
      {dialog?.type === 'adjust' && <StockAdjustmentDialog products={dialog.products} suppliers={suppliers} onClose={() => setDialog(null)} onSaved={onSaved} />}
      {dialog?.type === 'history' && <AdjustmentHistoryDialog product={dialog.product} onClose={() => setDialog(null)} />}
      {dialog?.type === 'resume' && <ResumeStockSyncDialog product={dialog.product} onClose={() => setDialog(null)} onSaved={onSaved} />}
    </AdminLayout>
  </>;
}
