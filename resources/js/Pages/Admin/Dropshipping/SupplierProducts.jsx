import DropshippingSubpage from './Subpage';
import { router } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';

function Status({ value }) {
  const styles = { success: 'bg-green-50 text-green-700', failed: 'bg-red-50 text-red-700', active: 'bg-green-50 text-green-700', disabled: 'bg-gray-100 text-gray-600' };
  return <span className={`inline-flex px-2 py-1 rounded-full text-[11px] font-bold ${styles[value] || 'bg-gray-100 text-gray-600'}`}>{String(value || 'untested').replaceAll('_', ' ')}</span>;
}

function SupplierSyncCard({ supplier, onSync }) {
  const [run, setRun] = useState(null);
  const [progressDetails, setProgressDetails] = useState(null);
  const [webWorkerActive, setWebWorkerActive] = useState(false);
  const hadRunningRun = useRef(false);
  const workerBusy = useRef(false);
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

  useEffect(() => {
    let interval;
    const fetchRun = async () => {
      try {
        const response = await fetch(`/admin/dropshipping/suppliers/${supplier.id}/active-run`);
        if (response.ok) {
          const data = await response.json();
          if (! data && hadRunningRun.current) {
            hadRunningRun.current = false;
            router.reload({ only: ['products', 'suppliers', 'pagination'], preserveScroll: true });
          }
          setRun(data || null);
        }
        const progressResponse = await fetch(`/admin/dropshipping/suppliers/${supplier.id}/catalog/progress`);
        if (progressResponse.ok) {
          setProgressDetails(await progressResponse.json());
        }
      } catch (e) { }
    };
    fetchRun();
    interval = setInterval(fetchRun, 2000);
    return () => clearInterval(interval);
  }, [supplier.id]);

  const processOneQueuedJob = async () => {
    if (workerBusy.current) return;
    workerBusy.current = true;
    setWebWorkerActive(true);
    try {
      const response = await fetch(`/admin/dropshipping/suppliers/${supplier.id}/catalog/work`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
      });
      if (response.ok) {
        const data = await response.json();
        setRun(data.progress?.run || data.run || null);
        if (data.progress) {
          setProgressDetails(data.progress);
        }
      }
    } catch (e) {
    } finally {
      workerBusy.current = false;
      setWebWorkerActive(false);
    }
  };

  const handleSync = () => {
    onSync(supplier);
    setTimeout(() => setRun({ status: 'queued', processed_items: 0, total_items: 0 }), 500);
  };
  const resumeSync = () => {
    if (!run?.id) return;
    router.post(`/admin/dropshipping/runs/${run.id}/resume-catalog`, {}, { preserveScroll: true });
    setRun({ ...run, status: 'running' });
  };
  const pauseSync = () => {
    if (!run?.id) return;
    router.post(`/admin/dropshipping/runs/${run.id}/pause-catalog`, {}, { preserveScroll: true });
    setRun({ ...run, status: 'paused' });
  };
  const cancelSync = () => {
    if (!run?.id) return;
    router.post(`/admin/dropshipping/runs/${run.id}/cancel`, {}, { preserveScroll: true });
    setRun(null);
  };

  const isPaused = run?.status === 'paused';
  const isRunning = run && ['queued', 'running'].includes(run.status);
  const hasUnfinishedRun = isRunning || isPaused;
  const progress = hasUnfinishedRun && run.total_items > 0 ? Math.round((run.processed_items / run.total_items) * 100) : 0;
  const pageItems = progressDetails?.items || [];
  const waitingForWorker = isRunning && run.status === 'queued' && Number(run.total_items || 0) === 0;
  const statusText = isPaused
    ? 'Catalog sync stopped. Resume unfinished pages when ready.'
    : waitingForWorker
    ? 'Waiting for queue worker to start catalog sync...'
    : run?.status === 'running'
      ? 'Syncing products page by page...'
      : 'Preparing supplier catalog sync...';

  useEffect(() => {
    if (!isRunning) return;
    hadRunningRun.current = true;
    processOneQueuedJob();
    const interval = setInterval(processOneQueuedJob, 3000);
    return () => clearInterval(interval);
  }, [isRunning, run?.id]);

  return (
    <div className="flex flex-col gap-3 rounded-xl border border-gray-100 p-4 bg-gray-50/50">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="font-semibold text-gray-800">{supplier.name}</p>
          <p className="text-xs text-gray-500">{supplier.products_count || 0} mirrored products · {supplier.variants_count || 0} variants · connection <Status value={supplier.last_connection_status || 'untested'} /></p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {isRunning && <button type="button" onClick={processOneQueuedJob} disabled={webWorkerActive} className="px-3 py-2 rounded-lg bg-blue-700 hover:bg-blue-800 disabled:bg-blue-200 text-xs font-semibold text-white transition-colors">Process next page</button>}
          {isPaused && <button type="button" onClick={resumeSync} className="px-3 py-2 rounded-lg bg-blue-700 hover:bg-blue-800 text-xs font-semibold text-white transition-colors">Resume unfinished</button>}
          {isRunning && <button type="button" onClick={pauseSync} className="px-3 py-2 rounded-lg bg-gray-800 hover:bg-gray-900 text-xs font-semibold text-white transition-colors">Stop</button>}
          {hasUnfinishedRun && <button type="button" onClick={cancelSync} className="px-3 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-xs font-semibold text-white transition-colors">Kill run</button>}
          <button disabled={hasUnfinishedRun || supplier.last_connection_status !== 'success' || !supplier.is_active} onClick={handleSync} className="px-3 py-2 rounded-lg bg-orange-500 hover:bg-orange-600 disabled:bg-gray-200 disabled:text-gray-400 text-xs font-semibold text-white transition-colors">
            {hasUnfinishedRun ? 'Sync in progress' : 'Sync catalog'}
          </button>
        </div>
      </div>
      {hasUnfinishedRun && (
        <div className="mt-2 bg-white border border-gray-200 p-4 rounded-xl shadow-sm">
          <div className="flex items-center justify-between text-xs mb-2">
            <span className="font-semibold text-gray-700">{statusText}</span>
            <span className="text-gray-500 font-mono">{run.processed_items} / {run.total_items}</span>
          </div>
          <div className="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
            <div className="bg-orange-500 h-2.5 rounded-full transition-all duration-300" style={{ width: `${progress}%` }}></div>
          </div>
          {webWorkerActive && <p className="text-[10px] text-blue-700 mt-2 font-semibold">Processing one queued job from this page...</p>}
          {waitingForWorker && !webWorkerActive && <p className="text-[10px] text-amber-600 mt-2 font-semibold">Run php artisan queue:work for continuous background processing.</p>}
          <div className="mt-3 grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
            <div className="rounded-lg bg-gray-50 p-2"><span className="block text-gray-500">Mirrored products</span><strong>{progressDetails?.mirrored_products ?? supplier.products_count ?? 0}</strong></div>
            <div className="rounded-lg bg-gray-50 p-2"><span className="block text-gray-500">Mirrored variants</span><strong>{progressDetails?.mirrored_variants ?? supplier.variants_count ?? 0}</strong></div>
            <div className="rounded-lg bg-gray-50 p-2"><span className="block text-gray-500">Queue jobs</span><strong>{progressDetails?.queue_jobs ?? 'checking'}</strong></div>
            <div className="rounded-lg bg-gray-50 p-2"><span className="block text-gray-500">Run status</span><strong>{run.status}</strong></div>
          </div>
          {pageItems.length > 0 && <div className="mt-3 rounded-lg border border-gray-100 overflow-hidden">
            <div className="grid grid-cols-4 bg-gray-50 px-3 py-2 text-[10px] font-bold uppercase text-gray-400"><span>Page</span><span>Status</span><span>Started</span><span>Finished</span></div>
            <div className="max-h-56 overflow-y-auto divide-y divide-gray-50">
              {pageItems.map(item => <div key={item.item_key} className="grid grid-cols-4 px-3 py-2 text-[11px] text-gray-600">
                <span className="font-mono">{item.item_key.replace('page:', 'Page ')}</span>
                <span className={item.status === 'succeeded' ? 'font-semibold text-green-700' : item.status === 'failed' ? 'font-semibold text-red-700' : 'font-semibold text-amber-700'}>{item.status}</span>
                <span>{item.started_at ? new Date(item.started_at).toLocaleTimeString() : '-'}</span>
                <span>{item.finished_at ? new Date(item.finished_at).toLocaleTimeString() : '-'}</span>
              </div>)}
            </div>
          </div>}
        </div>
      )}
    </div>
  );
}

export default function SupplierProducts({ products = [], suppliers = [], categories = [], stock_filter = 'all', stock_operator = '', stock_value = '', category_filter = '', search_filter = '', status_filter = '', import_status_filter = '', pagination = {} }) {
  const [selected, setSelected] = useState([]);
  const [search, setSearch] = useState(search_filter);
  const [operator, setOperator] = useState(stock_operator);
  const [value, setValue] = useState(stock_value);
  const [category, setCategory] = useState(category_filter);
  const [status, setStatus] = useState(status_filter || import_status_filter);
  const [pageSize, setPageSize] = useState(String(pagination.per_page || 100));

  const toggle = id => setSelected(current => current.includes(id) ? current.filter(item => item !== id) : [...current, id]);
  const toggleAll = () => setSelected(selected.length === products.length ? [] : products.map(product => product.id));
  const syncCatalog = supplier => router.post(`/admin/dropshipping/suppliers/${supplier.id}/catalog`, {}, { preserveScroll: true });
  const buildQuery = page => {
    const params = new URLSearchParams();
    if (page > 1) params.set('page', page);
    if (search.trim()) params.set('q', search.trim());
    if (stock_filter === 'positive') params.set('stock', 'positive');
    if (operator && value !== '') { params.set('stock_operator', operator); params.set('stock_value', value); }
    if (category) params.set('category', category);
    if (status) params.set('status', status);
    params.set('per_page', pageSize);
    return params.toString();
  };
  const applyFilters = event => { event.preventDefault(); setSelected([]); router.get(`/admin/dropshipping/products?${buildQuery(1)}`, {}, { preserveState: true, preserveScroll: true }); };
  const clearFilters = () => { setSearch(''); setOperator(''); setValue(''); setCategory(''); setStatus(''); setPageSize('100'); setSelected([]); router.get('/admin/dropshipping/products?per_page=100', {}, { preserveState: true, preserveScroll: true }); };
  const goToPage = page => { setSelected([]); router.get(`/admin/dropshipping/products?${buildQuery(page)}`, {}, { preserveState: true, preserveScroll: true }); };
  const batchImport = () => router.post('/admin/dropshipping/products/bulk-import', { ids: selected }, { preserveScroll: true, onSuccess: () => setSelected([]) });
  const totalProducts = suppliers.reduce((total, supplier) => total + Number(supplier.products_count || 0), 0);

  return <DropshippingSubpage title="Supplier Products" description="Browse the local supplier catalogue mirror and import selected products as drafts.">
    {suppliers.length > 0 && <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5"><div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 className="font-bold text-gray-900">Synchronize supplier catalog</h2><p className="text-sm text-gray-500 mt-1">Catalog sync runs in the queue and follows every supplier page, including large catalogs.</p></div><a href="/admin/dropshipping/settings" className="text-sm font-semibold text-orange-600 underline">API settings</a></div><div className="mt-4 space-y-3">{suppliers.map(supplier => <SupplierSyncCard key={supplier.id} supplier={supplier} onSync={syncCatalog} />)}</div></section>}
    <form onSubmit={applyFilters} className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm"><div className="flex flex-col gap-3 xl:flex-row xl:flex-wrap xl:items-end"><label className="min-w-[240px] flex-1 text-sm font-semibold text-gray-600">Search products<input type="search" value={search} onChange={event => setSearch(event.target.value)} placeholder="Search by name or SKU" className="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm" /></label><label className="min-w-[150px] flex-1 text-sm font-semibold text-gray-600">Category<select value={category} onChange={event => setCategory(event.target.value)} className="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm"><option value="">All categories</option>{categories.map(item => <option key={item} value={item}>{item}</option>)}</select></label><label className="min-w-[170px] flex-1 text-sm font-semibold text-gray-600">Status<select value={status} onChange={event => setStatus(event.target.value)} className="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm"><option value="">All statuses</option><option value="imported">Imported</option><option value="not_imported">Not imported</option></select></label><label className="min-w-[185px] flex-1 text-sm font-semibold text-gray-600">Stock comparison<select value={operator} onChange={event => setOperator(event.target.value)} className="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm"><option value="">Any stock</option><option value="gt">Greater than</option><option value="lt">Less than</option><option value="eq">Equal to</option></select></label><label className="min-w-[170px] flex-1 text-sm font-semibold text-gray-600">Stock value<input type="number" min="0" step="any" value={value} onChange={event => setValue(event.target.value)} placeholder="e.g. 10" className="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm" /></label><label className="w-36 shrink-0 text-sm font-semibold text-gray-600">Rows per page<select value={pageSize} onChange={event => setPageSize(event.target.value)} className="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm"><option value="25">25</option><option value="50">50</option><option value="100">100</option></select></label><button type="submit" className="shrink-0 rounded-lg bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-orange-600">Apply filters</button><button type="button" onClick={clearFilters} className="shrink-0 rounded-lg bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-200">Clear all</button></div></form>
    {products.length === 0 && <section className="bg-blue-50 border border-blue-200 rounded-2xl p-5"><h2 className="font-bold text-blue-900">No supplier products match these filters</h2><p className="text-sm text-blue-800 mt-1">Change the search, category, status, or stock comparison and try again.</p></section>}
    {products.length > 0 && <div className="bg-green-50 border border-green-200 rounded-2xl p-4 text-sm text-green-800"><strong>Showing {products.length} products on this page.</strong> {pagination.total || totalProducts} products match the current filters. Select products below to import draft copies for review. <a href="/admin/dropshipping/imported" className="font-semibold underline ml-1">View imported products</a></div>}
    <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden"><div className="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3"><div><h2 className="font-bold text-gray-900">Supplier catalogue</h2><p className="text-xs text-gray-500 mt-1">Showing 100 records per page. Batch import creates local draft products with their supplier stock.</p></div><button type="button" disabled={selected.length === 0} onClick={batchImport} className="px-3 py-2 rounded-lg bg-orange-500 hover:bg-orange-600 disabled:bg-gray-200 disabled:text-gray-400 text-xs font-semibold text-white">Import selected ({selected.length})</button></div><div className="overflow-x-auto"><table className="w-full text-sm"><thead><tr className="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-400"><th className="px-5 py-3 text-left"><input type="checkbox" aria-label="Select all visible products" checked={products.length > 0 && selected.length === products.length} onChange={toggleAll} /></th><th className="px-5 py-3 text-left">Product</th><th className="px-5 py-3 text-left">Supplier</th><th className="px-5 py-3 text-left">Calculated Prices</th><th className="px-5 py-3 text-left">Stock</th><th className="px-5 py-3 text-left">Link</th><th className="px-5 py-3 text-right">Action</th></tr></thead><tbody className="divide-y divide-gray-50">{products.length === 0 ? <tr><td colSpan="7" className="px-5 py-12 text-center text-sm text-gray-400">No products found.</td></tr> : products.map(product => <tr key={product.id}><td className="px-5 py-4"><input type="checkbox" aria-label={`Select ${product.name}`} checked={selected.includes(product.id)} onChange={() => toggle(product.id)} /></td><td className="px-5 py-4"><p className="font-semibold text-gray-800">{product.name}</p><p className="text-xs text-gray-400 font-mono">{product.supplier_product_id}</p></td><td className="px-5 py-4 text-xs text-gray-600">{product.supplier?.name || '—'}</td><td className="px-5 py-4 text-xs text-gray-600"><div className="grid grid-cols-2 gap-x-4 gap-y-1 min-w-[200px]"><div><span className="text-gray-400">Selling:</span> {product.calculated_selling ?? '—'}</div><div><span className="text-gray-400">Discounted:</span> {product.calculated_discounted ?? '—'}</div><div><span className="text-gray-400">Minimum:</span> {product.calculated_minimum ?? '—'}</div><div><span className="text-gray-400">Maximum:</span> {product.calculated_maximum ?? '—'}</div></div></td><td className="px-5 py-4 text-xs text-gray-600">{product.stock_qty ?? 'unknown'}{product.is_available === false ? ' · unavailable' : ''}</td><td className="px-5 py-4 text-xs">{product.linked_product ? <span className="text-green-700">Draft linked</span> : <span className="text-gray-400">Not imported</span>}</td><td className="px-5 py-4 text-right">{product.linked_product ? <span className="text-xs text-gray-400">Already imported</span> : <button onClick={() => router.post(`/admin/dropshipping/products/${product.id}/import`, {}, { preserveScroll: true })} className="px-3 py-1.5 rounded-lg bg-orange-500 hover:bg-orange-600 text-xs font-semibold text-white">Import draft</button>}</td></tr>)}</tbody></table></div></div>
    {pagination.last_page > 1 && <div className="flex items-center justify-between"><button disabled={(pagination.current_page || 1) <= 1} onClick={() => goToPage((pagination.current_page || 1) - 1)} className="px-3 py-2 rounded-lg bg-gray-100 disabled:opacity-40 text-xs font-semibold">Previous</button><span className="text-xs text-gray-500">Page {pagination.current_page} of {pagination.last_page} · {pagination.total} products</span><button disabled={(pagination.current_page || 1) >= pagination.last_page} onClick={() => goToPage((pagination.current_page || 1) + 1)} className="px-3 py-2 rounded-lg bg-gray-100 disabled:opacity-40 text-xs font-semibold">Next</button></div>}
  </DropshippingSubpage>;
}
