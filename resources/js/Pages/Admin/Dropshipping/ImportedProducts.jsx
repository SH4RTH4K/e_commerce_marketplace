import DropshippingSubpage from './Subpage';
import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function SyncStatus({ status }) {
  const styles = {
    queued: 'bg-amber-100 text-amber-800',
    running: 'bg-blue-100 text-blue-800',
  };

  return <span className={`inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold capitalize ${styles[status] || 'bg-gray-100 text-gray-700'}`}>{status || 'unknown'}</span>;
}

function ImportedProductSyncQueue({ runs }) {
  if (runs.length === 0) return null;

  const cancelRun = id => router.post(`/admin/dropshipping/runs/${id}/cancel`, {}, { preserveScroll: true });

  return <section aria-live="polite" className="overflow-hidden rounded-2xl border border-blue-200 bg-blue-50 shadow-sm">
    <div className="flex flex-col gap-3 border-b border-blue-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 className="font-bold text-blue-950">Price & data synchronization queue</h2>
        <p className="mt-1 text-sm text-blue-800">A price and data synchronization is already running for the selected supplier(s).</p>
      </div>
      <span className="w-fit rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-blue-800 shadow-sm">{runs.length} active {runs.length === 1 ? 'run' : 'runs'}</span>
    </div>
    <div className="overflow-x-auto">
      <table className="w-full text-sm">
        <thead><tr className="bg-blue-100/50 text-[10px] uppercase tracking-wider text-blue-700"><th className="px-5 py-3 text-left">Supplier</th><th className="px-5 py-3 text-left">Status</th><th className="px-5 py-3 text-left">Progress</th><th className="px-5 py-3 text-left">Queue detail</th><th className="px-5 py-3 text-right">Action</th></tr></thead>
        <tbody className="divide-y divide-blue-100 bg-white/70">
          {runs.map(run => {
            const total = run.total_items ?? run.requested_items;
            const isQueued = run.status === 'queued';
            return <tr key={run.id}>
              <td className="px-5 py-3"><p className="font-semibold text-gray-800">{run.supplier?.name || 'Unknown supplier'}</p><p className="text-xs text-gray-500">Run #{run.id}</p></td>
              <td className="px-5 py-3"><SyncStatus status={run.status} /></td>
              <td className="px-5 py-3 text-xs text-gray-700"><p className="font-semibold">{run.processed_items || 0} / {total || 0} products</p><p className="mt-0.5 text-gray-500">{run.success_items || 0} updated{run.failed_items ? ` · ${run.failed_items} failed` : ''}</p></td>
              <td className="px-5 py-3 text-xs text-gray-600">{isQueued ? 'Waiting for a queue worker to start.' : 'Updates are being processed now.'}</td>
              <td className="px-5 py-3 text-right"><button type="button" onClick={() => cancelRun(run.id)} className="rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100">Cancel</button></td>
            </tr>;
          })}
        </tbody>
      </table>
    </div>
  </section>;
}

function ImagePreview({ product }) {
  const [imageFailed, setImageFailed] = useState(false);
  const [isOpen, setIsOpen] = useState(false);
  const hasImage = Boolean(product.image_url) && !imageFailed;

  return <>
    <button
      type="button"
      onClick={() => hasImage && setIsOpen(true)}
      disabled={!hasImage}
      title={hasImage ? 'Preview product image' : 'No product image available'}
      aria-label={hasImage ? `Preview image for ${product.name}` : `No image for ${product.name}`}
      className={`relative h-14 w-14 shrink-0 overflow-hidden rounded-xl border bg-gray-50 transition ${hasImage ? 'border-gray-200 hover:border-orange-300 hover:ring-2 hover:ring-orange-100' : 'border-dashed border-gray-300'}`}
    >
      {hasImage ? <img
        src={product.image_url}
        alt={product.image_alt || product.name}
        loading="lazy"
        onError={() => setImageFailed(true)}
        className="h-full w-full object-cover"
      /> : <span className="flex h-full flex-col items-center justify-center gap-0.5 text-[9px] font-semibold uppercase tracking-wide text-gray-400">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" aria-hidden="true">
          <rect x="3" y="3" width="18" height="18" rx="2" />
          <circle cx="8.5" cy="8.5" r="1.5" />
          <path d="M21 15l-5-5L5 21" />
        </svg>
        No image
      </span>}
      {hasImage && product.image_count > 1 && <span className="absolute bottom-0.5 right-0.5 rounded bg-black/65 px-1 text-[9px] font-bold text-white">+{product.image_count - 1}</span>}
    </button>

    {isOpen && <div
      role="dialog"
      aria-modal="true"
      aria-label={`${product.name} image preview`}
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
      onClick={() => setIsOpen(false)}
    >
      <div className="relative max-h-[90vh] max-w-[min(90vw,720px)] rounded-2xl bg-white p-2 shadow-2xl" onClick={event => event.stopPropagation()}>
        <img src={product.image_url} alt={product.image_alt || product.name} className="max-h-[82vh] max-w-full rounded-xl object-contain" />
        <div className="flex items-center justify-between gap-3 px-2 pt-2">
          <p className="min-w-0 truncate text-xs font-semibold text-gray-700">{product.name}</p>
          <button type="button" onClick={() => setIsOpen(false)} className="shrink-0 rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200">Close</button>
        </div>
      </div>
    </div>}
  </>;
}

export default function ImportedProducts({ products = [], categories = [], sync_runs = [], search_filter = '', category_filter = '', status_filter = '', stock_operator = '', stock_value = '', order_by = 'newest', pagination = {} }) {
  const [selected, setSelected] = useState([]);
  const [search, setSearch] = useState(search_filter);
  const [category, setCategory] = useState(category_filter);
  const [status, setStatus] = useState(status_filter);
  const [stockOperator, setStockOperator] = useState(stock_operator);
  const [stockValue, setStockValue] = useState(stock_value);
  const [orderBy, setOrderBy] = useState(order_by);
  const [pageSize, setPageSize] = useState(String(pagination.per_page || 100));
  const [prices, setPrices] = useState({});
  const [batchType, setBatchType] = useState('');
  const [batchSyncing, setBatchSyncing] = useState(false);
  const [flags, setFlags] = useState({ is_featured: false, is_new_arrival: false, is_best_seller: false, is_flash_sale: false });
  const currentPage = pagination.current_page || 1;
  const lastPage = pagination.last_page || 1;
  const perPage = pagination.per_page || 100;
  const totalProducts = pagination.total ?? products.length;
  const firstResult = totalProducts === 0 ? 0 : ((currentPage - 1) * perPage) + 1;
  const lastResult = Math.min(currentPage * perPage, totalProducts);
  const hasActiveSync = sync_runs.length > 0;

  useEffect(() => {
    if (!hasActiveSync) return undefined;

    const interval = window.setInterval(() => {
      router.reload({ only: ['sync_runs'], preserveScroll: true, preserveState: true });
    }, 5000);

    return () => window.clearInterval(interval);
  }, [hasActiveSync]);

  const toggle = id => setSelected(current => current.includes(id) ? current.filter(item => item !== id) : [...current, id]);
  const toggleAll = () => setSelected(selected.length === products.length ? [] : products.map(product => product.id));

  const syncSelected = () => {
    if (selected.length === 0) return;
    router.post('/admin/dropshipping/imported/bulk-sync', { ids: selected }, { preserveScroll: true, onSuccess: () => setSelected([]) });
  };

  const syncFiltered = () => {
    setBatchSyncing(true);
    router.post('/admin/dropshipping/imported/bulk-sync-filtered', {
      q: search.trim(),
      category,
      status,
      stock_operator: stockOperator,
      stock_value: stockValue,
    }, {
      preserveScroll: true,
      onSuccess: () => setSelected([]),
      onFinish: () => setBatchSyncing(false),
    });
  };

  const syncOne = id => router.post('/admin/dropshipping/imported/bulk-sync', { ids: [id] }, { preserveScroll: true });
  const setFlag = key => setFlags(current => ({ ...current, [key]: !current[key] }));

  const getCalculatedPrice = (product, type) => {
    let reg = product.regular_price;
    let sale = product.sale_price;
    if (type !== 'custom' && product.pricing) {
      if (type === 'selling') {
        reg = product.pricing.rawSellingPrice ?? product.pricing.targetSellingPrice;
        sale = '';
      } else if (type === 'discounted') {
        reg = product.pricing.regularSellingPrice ?? product.pricing.finalPrice;
        sale = product.pricing.finalSalePrice;
      } else if (type === 'minimum') {
        reg = product.pricing.calculatedMinimumPrice ?? product.pricing.minimumPrice;
        sale = '';
      } else if (type === 'maximum') {
        reg = product.pricing.calculatedMaximumPrice ?? product.pricing.ceilingPrice;
        sale = '';
      }
    }
    return { reg, sale };
  };

  const handleTypeChange = (id, type) => {
    const product = products.find(item => item.id === id);
    if (!product) return;
    const calc = getCalculatedPrice(product, type);
    setPrices(current => ({ ...current, [id]: { type, regular_price: calc.reg, sale_price: calc.sale } }));
  };

  const updatePrice = (id, field, value) => {
    setPrices(current => ({ ...current, [id]: { ...(current[id] || {}), type: 'custom', [field]: value } }));
  };

  const getPriceData = id => prices[id] || {
    type: 'custom',
    regular_price: products.find(product => product.id === id)?.regular_price ?? '',
    sale_price: products.find(product => product.id === id)?.sale_price ?? '',
  };

  const applyBatchPrice = type => {
    setBatchType(type);
    if (!type || selected.length === 0) return;
    const nextPrices = { ...prices };
    selected.forEach(id => {
      const product = products.find(item => item.id === id);
      if (!product) return;
      const calc = getCalculatedPrice(product, type);
      nextPrices[id] = { type, regular_price: calc.reg, sale_price: calc.sale };
    });
    setPrices(nextPrices);
  };

  const filterParams = () => {
    const params = new URLSearchParams();
    if (search.trim()) params.append('q', search.trim());
    if (category) params.append('category', category);
    if (status) params.append('status', status);
    if (stockOperator && stockValue !== '') {
      params.append('stock_operator', stockOperator);
      params.append('stock_value', stockValue);
    }
    params.append('order_by', orderBy);
    params.append('per_page', pageSize);
    return params.toString() ? `?${params.toString()}` : '';
  };

  const publishSelected = () => router.post('/admin/dropshipping/imported/bulk-publish', { ids: selected, prices, ...flags }, { preserveScroll: true, onSuccess: () => setSelected([]) });
  const unpublishSelected = () => router.post('/admin/dropshipping/imported/bulk-unpublish', { ids: selected }, { preserveScroll: true, onSuccess: () => setSelected([]) });
  const publishOne = id => router.patch(`/admin/dropshipping/imported/${id}/publish`, { ...flags, ...(prices[id] || {}) }, { preserveScroll: true });
  const unpublishOne = id => router.post('/admin/dropshipping/imported/bulk-unpublish', { ids: [id] }, { preserveScroll: true });
  const filterList = () => { setSelected([]); router.get(`/admin/dropshipping/imported${filterParams()}`, {}, { preserveScroll: true, preserveState: true }); };
  const clearFilter = () => { setSearch(''); setCategory(''); setStatus(''); setStockOperator(''); setStockValue(''); setOrderBy('newest'); setPageSize('100'); setSelected([]); router.get('/admin/dropshipping/imported?per_page=100', {}, { preserveScroll: true, preserveState: true }); };
  const goToPage = page => { setSelected([]); router.get(`/admin/dropshipping/imported?page=${page}${filterParams().replace('?', '&')}`, {}, { preserveScroll: true, preserveState: true }); };

  return <DropshippingSubpage title="Imported Products" description="Review integration-created local products before publishing them to the storefront.">
    <div className="flex items-center gap-2">
      <button type="button" disabled={selected.length === 0} onClick={publishSelected} className="rounded-lg bg-green-600 px-3 py-2 text-xs font-semibold text-white hover:bg-green-700 disabled:bg-gray-200 disabled:text-gray-400">Publish selected ({selected.length})</button>
      <button type="button" disabled={selected.length === 0} onClick={unpublishSelected} className="rounded-lg bg-gray-700 px-3 py-2 text-xs font-semibold text-white hover:bg-gray-800 disabled:bg-gray-200 disabled:text-gray-400">Unpublish selected</button>
    </div>
    <ImportedProductSyncQueue runs={sync_runs} />
    <section className="rounded-2xl border border-orange-100 bg-white p-5 shadow-sm">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div><h2 className="font-bold text-gray-900">Batch synchronize imported products</h2><p className="mt-1 text-sm text-gray-500">Queue price, stock, description, and supplier image updates from the latest catalog data for all {totalProducts} products matching the current filters.</p></div>
        <button type="button" disabled={totalProducts === 0 || batchSyncing} onClick={syncFiltered} className="shrink-0 rounded-xl bg-orange-500 px-5 py-3 text-sm font-bold text-white shadow-sm transition-colors hover:bg-orange-600 disabled:bg-gray-200 disabled:text-gray-400">
          {batchSyncing ? 'Queueing sync...' : `Batch sync prices & data (${totalProducts})`}
        </button>
      </div>
    </section>
    <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800"><strong>Review process:</strong> open the image preview, confirm price, stock, and mapped variants, then publish or leave the product as a draft. Use Unpublish when the image or listing no longer meets the storefront standard.</div>
    <div className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
      <div className="flex flex-col gap-3 xl:flex-row xl:flex-wrap xl:items-end">
        <label className="min-w-[240px] flex-1 text-sm font-semibold text-gray-600">Search products<input type="search" value={search} onChange={event => setSearch(event.target.value)} onKeyDown={event => { if (event.key === 'Enter') filterList(); }} placeholder="Search by name or SKU" className="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm" /></label>
        <label className="flex-1 text-sm font-semibold text-gray-600">Category<select value={category} onChange={event => setCategory(event.target.value)} className="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm"><option value="">All categories</option>{categories.map(item => <option key={item} value={item}>{item}</option>)}</select></label>
        <label className="w-40 text-sm font-semibold text-gray-600">Status<select value={status} onChange={event => setStatus(event.target.value)} className="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm"><option value="">All statuses</option><option value="published">Published</option><option value="draft">Draft (Unpublished)</option></select></label>
        <label className="w-40 text-sm font-semibold text-gray-600">Stock comparison<select value={stockOperator} onChange={event => setStockOperator(event.target.value)} className="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm"><option value="">Any stock</option><option value="gt">Greater than</option><option value="lt">Less than</option><option value="eq">Equal to</option></select></label>
        <label className="w-36 text-sm font-semibold text-gray-600">Stock value<input type="number" min="0" step="any" value={stockValue} onChange={event => setStockValue(event.target.value)} placeholder="e.g. 10" className="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm" /></label>
        <label className="w-44 text-sm font-semibold text-gray-600">Order by<select value={orderBy} onChange={event => setOrderBy(event.target.value)} className="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm"><option value="newest">Newest first</option><option value="oldest">Oldest first</option><option value="name_asc">Name: A to Z</option><option value="name_desc">Name: Z to A</option><option value="price_asc">Price: Low to High</option><option value="price_desc">Price: High to Low</option><option value="stock_asc">Stock: Low to High</option><option value="stock_desc">Stock: High to Low</option></select></label>
        <label className="w-28 text-sm font-semibold text-gray-600">Rows per page<select value={pageSize} onChange={event => setPageSize(event.target.value)} className="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm"><option value="25">25</option><option value="50">50</option><option value="100">100</option></select></label>
        <button type="button" onClick={filterList} className="rounded-lg bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-orange-600">Apply filters</button>
        <button type="button" onClick={clearFilter} className="rounded-lg bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-200">Clear</button>
      </div>
    </div>
    <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
      <div className="flex flex-col gap-4 border-b border-gray-100 px-5 py-4">
        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div><h2 className="font-bold text-gray-900">Local imported products</h2><p className="mt-1 text-xs text-gray-500">Supplier variants are automatically created and linked after import or catalog resync.</p></div>
          <div className="flex flex-wrap items-center gap-3"><span className="text-xs text-gray-400">Showing {products.length} of {pagination.total || products.length}</span><button type="button" disabled={selected.length === 0} onClick={publishSelected} className="rounded-lg bg-green-600 px-3 py-2 text-xs font-semibold text-white hover:bg-green-700 disabled:bg-gray-200 disabled:text-gray-400">Publish selected</button><button type="button" disabled={selected.length === 0} onClick={syncSelected} className="rounded-lg bg-blue-100 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-200 disabled:bg-gray-200 disabled:text-gray-400">Sync selected</button></div>
        </div>
        <div className="flex flex-wrap gap-4 border-t border-gray-100 pt-3 text-xs text-gray-700">
          <label className="flex items-center gap-2"><input type="checkbox" checked={flags.is_featured} onChange={() => setFlag('is_featured')} /> Featured / Trending</label>
          <label className="flex items-center gap-2"><input type="checkbox" checked={flags.is_new_arrival} onChange={() => setFlag('is_new_arrival')} /> New Arrival</label>
          <label className="flex items-center gap-2"><input type="checkbox" checked={flags.is_best_seller} onChange={() => setFlag('is_best_seller')} /> Best Seller</label>
          <label className="flex items-center gap-2"><input type="checkbox" checked={flags.is_flash_sale} onChange={() => setFlag('is_flash_sale')} /> Flash Sale</label>
          <label className="ml-auto flex items-center gap-1 border-l border-gray-200 pl-4 font-semibold text-orange-700">Batch fill prices:<select value={batchType} onChange={event => applyBatchPrice(event.target.value)} disabled={selected.length === 0} className="rounded border border-orange-200 bg-white px-2 py-1 text-xs text-gray-800 disabled:opacity-50"><option value="">-- Select formula output --</option><option value="selling">Selling Price</option><option value="discounted">Discounted Price</option><option value="minimum">Minimum Price</option><option value="maximum">Maximum Price</option></select></label>
        </div>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead><tr className="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-400"><th className="px-5 py-3 text-left"><input type="checkbox" aria-label="Select all products" checked={products.length > 0 && selected.length === products.length} onChange={toggleAll} /></th><th className="px-5 py-3 text-left">SL</th><th className="px-3 py-3 text-left">Image</th><th className="px-5 py-3 text-left">Product</th><th className="px-5 py-3 text-left">Category</th><th className="px-5 py-3 text-left">Supplier</th><th className="px-5 py-3 text-left">Variants</th><th className="min-w-[200px] px-5 py-3 text-left">Storefront Price</th><th className="px-5 py-3 text-left">Stock</th><th className="px-5 py-3 text-left">Status</th><th className="px-5 py-3 text-right">Action</th></tr></thead>
          <tbody className="divide-y divide-gray-50">
            {products.length === 0 ? <tr><td colSpan="11" className="px-5 py-12 text-center text-sm text-gray-400">No imported products match the current filters.</td></tr> : products.map((product, index) => {
              const pData = getPriceData(product.id);
              return <tr key={product.id} className="transition-colors hover:bg-orange-50/20">
                <td className="px-5 py-4"><input type="checkbox" aria-label={`Select ${product.name}`} checked={selected.includes(product.id)} onChange={() => toggle(product.id)} /></td>
                <td className="px-5 py-4 text-xs font-medium text-gray-500">{((pagination.current_page || 1) - 1) * (pagination.per_page || 100) + index + 1}</td>
                <td className="px-3 py-4"><ImagePreview product={product} /></td>
                <td className="px-5 py-4"><a href={`/admin/products/${product.id}/edit`} className="font-semibold text-orange-600 hover:underline">{product.name}</a><p className="mt-0.5 text-xs text-gray-400">SKU: {product.sku || '?'}</p></td>
                <td className="px-5 py-4 text-xs capitalize text-gray-600">{product.category ? product.category.replace(/-/g, ' ') : '-'}</td>
                <td className="px-5 py-4 text-xs text-gray-600">{product.supplier || '?'}</td>
                <td className="px-5 py-4 text-xs"><a href="/admin/dropshipping/variations" className={product.supplier_variants === product.mapped_variants ? 'text-green-700' : 'text-amber-700'}>{product.mapped_variants}/{product.supplier_variants} mapped</a></td>
                <td className="px-5 py-4 text-xs text-gray-600">{product.is_published ? <div className="space-y-1"><p>Regular: {product.regular_price ?? '-'}</p><p>Sale: {product.sale_price ?? '-'}</p></div> : <div className="flex flex-col gap-1.5"><select value={pData.type} onChange={event => handleTypeChange(product.id, event.target.value)} className={`w-full rounded border px-1.5 py-0.5 text-[10px] font-semibold ${pData.type === 'custom' ? 'border-gray-200 text-gray-500' : 'border-orange-300 bg-orange-50 text-orange-800'}`}><option value="custom">Custom Price</option><option value="selling">Selling Price</option><option value="discounted">Discounted Price</option><option value="minimum">Minimum Price</option><option value="maximum">Maximum Price</option></select><div className="flex gap-2"><label className="flex flex-col gap-0.5"><span className="text-[9px] font-semibold uppercase tracking-wider text-gray-400">Reg</span><input type="number" step="0.01" className="w-20 rounded border border-gray-200 px-1.5 py-0.5 text-xs text-gray-900" value={pData.regular_price} onChange={event => updatePrice(product.id, 'regular_price', event.target.value)} /></label><label className="flex flex-col gap-0.5"><span className="text-[9px] font-semibold uppercase tracking-wider text-gray-400">Sale</span><input type="number" step="0.01" className="w-20 rounded border border-gray-200 px-1.5 py-0.5 text-xs text-gray-900" value={pData.sale_price} onChange={event => updatePrice(product.id, 'sale_price', event.target.value)} /></label></div></div>}</td>
                <td className="px-5 py-4 text-xs text-gray-600">{product.stock_quantity}</td>
                <td className="px-5 py-4"><span className={`text-xs font-semibold ${product.is_published ? 'text-green-700' : 'text-amber-700'}`}>{product.is_published ? 'Published' : 'Draft'}</span></td>
                <td className="px-5 py-4 text-right"><div className="flex items-center justify-end gap-2">{product.is_published ? <button onClick={() => unpublishOne(product.id)} className="rounded-lg bg-gray-100 px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200">Unpublish</button> : <button onClick={() => publishOne(product.id)} className="rounded-lg bg-green-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-green-700">Publish</button>}<button onClick={() => syncOne(product.id)} className="rounded-lg bg-blue-50 px-2.5 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-100">Sync</button></div></td>
              </tr>;
            })}
          </tbody>
        </table>
      </div>
    </div>
    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-1 py-3"><span className="text-xs text-gray-500">Showing {firstResult}-{lastResult} of {totalProducts} products · {perPage} rows per page · Page {currentPage} of {lastPage}</span><div className="flex items-center gap-2"><button disabled={currentPage <= 1} onClick={() => goToPage(currentPage - 1)} className="rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700 disabled:cursor-not-allowed disabled:opacity-40">Previous</button><button disabled={currentPage >= lastPage} onClick={() => goToPage(currentPage + 1)} className="rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700 disabled:cursor-not-allowed disabled:opacity-40">Next</button></div></div>
  </DropshippingSubpage>;
}
