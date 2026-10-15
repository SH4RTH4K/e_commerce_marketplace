import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { StockAdjustmentDialog, AdjustmentHistoryDialog, ResumeStockSyncDialog } from '@/Components/Admin/InventoryDialogs';

const inputClass = 'w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200';
function Icon({ path, className = 'w-4 h-4' }) {
  return <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d={path} /></svg>;
}
function StockBadge({ quantity }) {
  const label = quantity === 0 ? 'Out of stock' : quantity <= 5 ? 'Low stock' : 'Healthy stock';
  return <span className={`inline-flex items-center gap-1.5 whitespace-nowrap text-xs font-medium px-2.5 py-1 rounded-full ${quantity === 0 ? 'bg-red-50 text-red-700' : quantity <= 5 ? 'bg-amber-50 text-amber-800' : 'bg-emerald-50 text-emerald-700'}`}><span className="h-1.5 w-1.5 rounded-full bg-current" />{label}</span>;
}
export default function InventoryIndex({ products, categories = [], suppliers = [], q = '', category = '', supplier = '', stock_level = '', visibility = '', source = '', type = '', order = 'stock_asc', per_page = 20, summary = {}, matching_units = 0, can_adjust = false }) {
  const { props } = usePage();
  const currency = props.app?.currency_symbol || '৳';
  const price = value => value == null ? '—' : `${currency}${Number(value).toLocaleString('en-US', { maximumFractionDigits: 2 })}`;
  const applied = { q, category: category || '', supplier: supplier || '', stock_level, visibility, source, type, order, per_page };
  const [filters, setFilters] = useState(applied);
  const [selected, setSelected] = useState([]); const [dialog, setDialog] = useState(null); const [loading, setLoading] = useState(false);
  const rows = products.data || [];
  useEffect(() => { setFilters(applied); setSelected([]); }, [q, category, supplier, stock_level, visibility, source, type, order, per_page, products.current_page]);
  const visit = (overrides = {}) => {
    setSelected([]);
    const parameters = Object.fromEntries(Object.entries({ ...filters, ...overrides }).filter(([, value]) => value !== '' && value != null));
    router.get('/admin/inventory', parameters, { preserveState: true, preserveScroll: true, onStart: () => setLoading(true), onFinish: () => setLoading(false) });
  };
  const filtered = !!(q || category || supplier || stock_level || visibility || source || type);
  const selectedRows = rows.filter(product => selected.includes(product.id));
  const exportUrl = '/admin/inventory/export?' + new URLSearchParams(Object.fromEntries(Object.entries(applied).filter(([, value]) => value !== '' && value != null))).toString();
  const onSaved = () => { setDialog(null); setSelected([]); };
  const cards = [
    { label: 'All products', value: summary.total, level: '', color: 'text-slate-900', note: `${Number(summary.units || 0).toLocaleString()} units available` },
    { label: 'Healthy stock', value: summary.in_stock, level: 'in', color: 'text-emerald-700', note: 'More than 5 units' },
    { label: 'Low stock', value: summary.low_stock, level: 'low', color: 'text-amber-700', note: '1–5 units · review replenishment' },
    { label: 'Out of stock', value: summary.out_stock, level: 'out', color: 'text-red-700', note: '0 units · unavailable to order' },
  ];
  const selects = [
    ['category', 'Category', [['', 'All categories'], ...categories.map(item => [item.id, item.name])]],
    ['stock_level', 'Stock level', [['', 'All stock levels'], ['in', 'Healthy (>5)'], ['low', 'Low stock (1–5)'], ['out', 'Out of stock']]],
    ['visibility', 'Visibility', [['', 'All visibility'], ['published', 'Published'], ['draft', 'Draft']]],
    ['source', 'Stock source', [['', 'All stock sources'], ['local', 'Local inventory'], ['supplier', 'Supplier synced'], ['manual', 'Supplier · manual stock']]],
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
            <a href={exportUrl} className="inline-flex gap-2 items-center px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50"><Icon path="M12 3v12m-4-4 4 4 4-4M4 17v4h16v-4" />Export CSV</a>
            {can_adjust && <a href="/admin/products/create" className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold"><Icon path="M12 5v14M5 12h14" />Add product</a>}
          </div>
        </header>
        <div className="grid grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4">
          {cards.map(card => <button key={card.label} type="button" onClick={() => visit({ stock_level: card.level })} aria-pressed={stock_level === card.level}
            className={`text-left bg-white border rounded-2xl p-4 sm:p-5 shadow-sm transition hover:shadow-md ${stock_level === card.level ? 'border-orange-300 ring-1 ring-orange-100' : 'border-gray-100'}`}>
            <p className="text-xs font-semibold text-gray-500">{card.label}</p><p className={`text-3xl font-bold mt-2 tabular-nums ${card.color}`}>{Number(card.value || 0).toLocaleString()}</p><p className="text-[11px] text-gray-400 mt-2 leading-relaxed">{card.note}</p>
          </button>)}
        </div>
        <section className="rounded-2xl border border-gray-100 bg-white shadow-sm p-4 sm:p-5">
          <form onSubmit={event => { event.preventDefault(); visit(); }} className="space-y-4">
            <div className="flex flex-col sm:flex-row gap-3">
              <label className="flex-1"><span className="sr-only">Search inventory</span><input type="search" placeholder="Search product name or SKU…" value={filters.q} onChange={event => setFilters(current => ({ ...current, q: event.target.value }))} className={inputClass} /></label>
              <button disabled={loading} className="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold rounded-xl disabled:opacity-50">{loading ? 'Loading…' : 'Apply filters'}</button>
              {filtered && <button type="button" onClick={() => { setSelected([]); router.get('/admin/inventory'); }} className="px-3 py-2.5 text-gray-500 text-sm hover:text-gray-900">Reset filters</button>}
            </div>
            <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">{selects.map(([key, label, options]) => <label key={key} className="text-xs font-medium text-gray-500">{label}<select value={filters[key]} onChange={event => setFilters(current => ({ ...current, [key]: event.target.value }))} className={`${inputClass} mt-1.5`}>{options.map(([value, name]) => <option key={value} value={value}>{name}</option>)}</select></label>)}</div>
            <div className="flex flex-wrap justify-between items-center gap-3 border-t border-gray-100 pt-3">
              <p className="text-xs text-gray-400">Overview cards use all filters except stock level.</p>
              <button type="button" onClick={() => visit({ stock_level: 'out', visibility: 'published' })} className="text-xs font-semibold text-orange-600 hover:underline">Review published products with no stock →</button>
            </div>
          </form>
        </section>
        <section className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" aria-busy={loading}>
          <div className="flex flex-wrap items-center justify-between gap-3 p-4 border-b border-gray-100">
            <div><h2 className="font-semibold text-gray-900">{Number(products.total || 0).toLocaleString()} {filtered ? 'matching' : ''} products</h2><p className="text-xs text-gray-400 mt-1">{Number(matching_units).toLocaleString()} units in this view</p></div>
            <div className="flex items-center gap-3"><label className="sr-only" htmlFor="inventory-sort">Sort inventory</label><select id="inventory-sort" value={filters.order} onChange={event => { setFilters(current => ({ ...current, order: event.target.value })); visit({ order: event.target.value }); }} className="border border-gray-200 rounded-lg px-2 py-2 text-xs"><option value="stock_asc">Stock: low to high</option><option value="stock_desc">Stock: high to low</option><option value="updated">Recently updated</option><option value="name_asc">Name: A–Z</option><option value="name_desc">Name: Z–A</option><option value="price_asc">Customer price: low to high</option><option value="price_desc">Customer price: high to low</option></select>
              <label className="text-xs text-gray-500">Rows <select value={filters.per_page} onChange={event => { setFilters(current => ({ ...current, per_page: event.target.value })); visit({ per_page: event.target.value }); }} className="ml-1 border border-gray-200 rounded-lg px-2 py-2"><option>20</option><option>50</option><option>100</option></select></label>
            </div>
          </div>
          {selectedRows.length > 0 && <div className="flex items-center justify-between gap-3 bg-orange-50 border-b border-orange-100 px-4 py-3"><span className="text-sm font-semibold text-orange-900">{selectedRows.length} selected on this page</span><div className="flex gap-3"><button onClick={() => setSelected([])} className="text-xs text-orange-700">Clear selection</button><button onClick={() => setDialog({ type: 'adjust', products: selectedRows })} className="bg-orange-500 text-white rounded-lg px-3 py-2 text-xs font-semibold">Adjust selected stock</button></div></div>}
          <div className="overflow-x-auto">
            <table className="w-full text-sm min-w-[850px]">
              <thead className="bg-gray-50/80 text-gray-500 text-[11px] uppercase tracking-wide"><tr>{can_adjust && <th className="pl-4 py-3 w-10"><input type="checkbox" aria-label="Select this page" checked={rows.length > 0 && selectedRows.length === rows.length} onChange={event => setSelected(event.target.checked ? rows.map(product => product.id) : [])} /></th>}{['Product', 'Customer price', 'Available stock', 'Stock source', 'Updated', 'Actions'].map(label => <th key={label} className={`px-4 py-3 text-left font-semibold ${label === 'Actions' ? 'text-right' : ''}`}>{label}</th>)}</tr></thead>
              <tbody className="divide-y divide-gray-100">{!rows.length ? <tr><td colSpan={can_adjust ? 7 : 6} className="py-16 text-center"><p className="font-semibold text-gray-600">No products found</p><p className="text-sm text-gray-400 mt-1">Try a different search or reset the filters.</p></td></tr> : rows.map(product => <tr key={product.id} className={`hover:bg-gray-50/60 ${selected.includes(product.id) ? 'bg-orange-50/30' : ''}`}>
                {can_adjust && <td className="pl-4 py-4"><input type="checkbox" aria-label={`Select ${product.name}`} checked={selected.includes(product.id)} onChange={event => setSelected(current => event.target.checked ? [...current, product.id] : current.filter(id => id !== product.id))} /></td>}
                <td className="px-4 py-4 max-w-sm"><div className="flex items-start gap-3"><img src={product.image_url} alt="" className="w-12 h-12 object-contain rounded-xl border border-gray-100 bg-gray-50 shrink-0" /><div className="min-w-0">{can_adjust ? <a href={`/admin/products/${product.id}/edit`} className="font-semibold text-slate-800 hover:text-orange-600 leading-snug line-clamp-2">{product.name}</a> : <p className="font-semibold text-slate-800 leading-snug line-clamp-2">{product.name}</p>}<p className="text-xs text-gray-400 mt-1">SKU: {product.sku || '—'} · {product.category?.name || 'Uncategorized'}</p><div className="flex gap-2 items-center mt-1.5 text-[10px]"><span className={product.is_published ? 'text-emerald-700' : 'text-gray-400'}>{product.is_published ? 'Published' : 'Draft'}</span><span className="text-gray-300">·</span><span className="text-gray-500">{product.has_variants ? `${product.variants_count} variant options` : 'Simple product'}</span></div></div></div></td>
                <td className="px-4 py-4 align-top whitespace-nowrap"><p className="font-bold text-slate-900 tabular-nums">{price(product.price)}</p>{product.sale_price != null && Number(product.sale_price) < Number(product.regular_price) && <p className="text-xs text-gray-400 line-through">{price(product.regular_price)}</p>}<details className="mt-2 text-xs"><summary className="cursor-pointer text-gray-400 hover:text-orange-600">Price details</summary><dl className="space-y-1 mt-2 text-gray-500">{[['Regular', product.regular_price], ['Selling', product.selling_price], ['Discounted', product.sale_price], ['Minimum', product.minimum_price], ['Maximum', product.maximum_price]].map(([label, value]) => <div key={label} className="flex justify-between gap-3"><dt>{label}</dt><dd>{price(value)}</dd></div>)}</dl></details></td>
                <td className="px-4 py-4 align-top"><p className="text-xl font-bold text-slate-800 tabular-nums mb-1.5">{Number(product.stock_quantity).toLocaleString()}</p><StockBadge quantity={product.stock_quantity} /></td>
                <td className="px-4 py-4 align-top"><p className={`text-xs font-semibold ${product.stock_source === 'supplier' ? 'text-blue-700' : 'text-slate-600'}`}>{product.stock_source === 'supplier' ? 'Supplier synced' : product.stock_source === 'manual' ? 'Manual supplier stock' : 'Local inventory'}</p>{product.supplier && <p className="text-xs text-gray-400 mt-1">{product.supplier}</p>}{product.last_synced && <p className="text-[10px] text-gray-400 mt-1">Last sync: {product.last_synced}</p>}{can_adjust && product.can_resume_sync && <button onClick={() => setDialog({ type: 'resume', product })} className="text-xs text-orange-600 hover:underline mt-2">Resume supplier sync</button>}</td>
                <td className="px-4 py-4 align-top text-xs text-gray-400 min-w-28">{product.updated_at || '—'}</td>
                <td className="px-4 py-4 align-top text-right"><div className="flex flex-col gap-2 items-end">{can_adjust && <button onClick={() => setDialog({ type: 'adjust', products: [product] })} aria-label={`Adjust stock for ${product.name}`} className="px-3 py-1.5 rounded-lg bg-slate-900 text-white text-xs font-semibold hover:bg-slate-800 whitespace-nowrap">Adjust stock</button>}<button onClick={() => setDialog({ type: 'history', product })} aria-label={`Adjustment history for ${product.name}`} className="text-xs text-gray-500 hover:text-orange-600">History</button>{product.is_published && <a href={`/product/${product.url_key || product.slug}`} target="_blank" rel="noopener noreferrer" className="text-xs text-gray-400 hover:text-blue-600">View product ↗</a>}</div></td>
              </tr>)}</tbody>
            </table>
          </div>
          <footer className="border-t border-gray-100 p-4 flex flex-wrap justify-between items-center gap-3"><p className="text-xs text-gray-400">{products.from ? `${products.from}–${products.to} of ${Number(products.total).toLocaleString()}` : '0 results'}</p><nav aria-label="Inventory pagination" className="flex flex-wrap gap-1">{(products.links || []).map((link, index) => <button key={index} disabled={!link.url || loading} aria-current={link.active ? 'page' : undefined} onClick={() => { setSelected([]); router.get(link.url, {}, { preserveScroll: true }); }} className={`min-w-8 px-2.5 py-1.5 rounded-lg text-xs disabled:opacity-40 ${link.active ? 'bg-orange-500 text-white font-semibold' : 'border border-gray-200 text-gray-600 hover:bg-gray-50'}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</nav></footer>
        </section>
      </div>
      {dialog?.type === 'adjust' && <StockAdjustmentDialog products={dialog.products} onClose={() => setDialog(null)} onSaved={onSaved} />}
      {dialog?.type === 'history' && <AdjustmentHistoryDialog product={dialog.product} onClose={() => setDialog(null)} />}
      {dialog?.type === 'resume' && <ResumeStockSyncDialog product={dialog.product} onClose={() => setDialog(null)} onSaved={onSaved} />}
    </AdminLayout>
  </>;
}
