import { useState } from 'react';
import DropshippingSubpage from './Subpage';
import { router } from '@inertiajs/react';

const statusColors = {
  completed: 'bg-green-50 text-green-700 border-green-200',
  running: 'bg-blue-50 text-blue-700 border-blue-200',
  queued: 'bg-amber-50 text-amber-700 border-amber-200',
  failed: 'bg-red-50 text-red-700 border-red-200',
  completed_with_errors: 'bg-orange-50 text-orange-700 border-orange-200',
};

function Status({ value }) {
  return (
    <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-bold ${statusColors[value] || 'bg-gray-100 text-gray-600 border-gray-200'}`}>
      {String(value || 'unknown').replaceAll('_', ' ')}
    </span>
  );
}

export default function SyncRuns({ runs = {}, suppliers = [], filters = {} }) {
  const rows = runs.data || [];
  const [selectedRun, setSelectedRun] = useState(null);
  const [loadingDetails, setLoadingDetails] = useState(false);

  const submit = event => {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(event.currentTarget));
    router.get('/admin/dropshipping/sync-runs', data, { preserveState: true, replace: true });
  };

  const openDetails = async (run) => {
    setSelectedRun({
      ...run,
      items: run.failed_items_list || [],
    });
    setLoadingDetails(true);
    try {
      const response = await fetch(`/admin/dropshipping/runs/${run.id}/details`);
      if (response.ok) {
        const full = await response.json();
        setSelectedRun(full);
      }
    } catch (e) {
      // Fallback to already-loaded info in run object
    } finally {
      setLoadingDetails(false);
    }
  };

  return (
    <DropshippingSubpage title="Sync runs" description="Review supplier catalog, price, stock, and product synchronization history.">
      <section className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <form onSubmit={submit} className="grid grid-cols-1 gap-3 md:grid-cols-[1fr_260px_auto]">
          <input
            name="q"
            defaultValue={filters.q || ''}
            placeholder="Search supplier name or key"
            className="rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-orange-400 focus:ring-orange-200"
          />
          <select
            name="supplier_id"
            defaultValue={filters.supplier_id || ''}
            className="rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm"
          >
            <option value="">All dropshipping suppliers</option>
            {suppliers.map(supplier => (
              <option key={supplier.id} value={supplier.id}>{supplier.name}</option>
            ))}
          </select>
          <button className="rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-orange-600 transition-colors">
            Search
          </button>
        </form>
      </section>

      <section className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div className="border-b border-gray-100 px-5 py-4 flex items-center justify-between">
          <div>
            <h2 className="font-bold text-gray-900">Sync history</h2>
            <p className="mt-1 text-xs text-gray-500">{runs.total || 0} run(s) found</p>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-gray-50 text-left text-[10px] uppercase tracking-wider text-gray-400">
                <th className="px-5 py-3">Run</th>
                <th className="px-5 py-3">Supplier</th>
                <th className="px-5 py-3">Type</th>
                <th className="px-5 py-3">Status</th>
                <th className="px-5 py-3">Progress</th>
                <th className="px-5 py-3">Started</th>
                <th className="px-5 py-3 text-right">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-50">
              {rows.length === 0 ? (
                <tr>
                  <td colSpan="7" className="px-5 py-12 text-center text-sm text-gray-400">
                    No sync runs match your search.
                  </td>
                </tr>
              ) : (
                rows.map(run => (
                  <tr key={run.id} className="hover:bg-gray-50/50 transition-colors">
                    <td className="px-5 py-4 font-mono text-xs font-semibold text-gray-500">
                      #{run.id}
                    </td>
                    <td className="px-5 py-4">
                      <p className="font-semibold text-gray-800">{run.supplier?.name || 'Unknown supplier'}</p>
                      <p className="text-xs text-gray-400">{run.supplier?.key}</p>
                    </td>
                    <td className="px-5 py-4 text-xs font-medium text-gray-600">
                      {String(run.type || '').replaceAll('_', ' ')}
                    </td>
                    <td className="px-5 py-4">
                      <div className="flex flex-col items-start gap-1">
                        <div className="flex items-center gap-1.5">
                          <Status value={run.status} />
                          {run.failed_items > 0 && (
                            <span className="text-[11px] font-bold text-rose-600">
                              ({run.failed_items} failed)
                            </span>
                          )}
                        </div>
                        {run.error_summary && (
                          <button
                            type="button"
                            onClick={() => openDetails(run)}
                            className="flex items-center gap-1 text-left text-[11px] font-medium text-rose-600 hover:text-rose-700 hover:underline max-w-[240px]"
                            title="Click to view error details"
                          >
                            <svg className="w-3.5 h-3.5 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span className="truncate">{run.error_summary}</span>
                          </button>
                        )}
                      </div>
                    </td>
                    <td className="px-5 py-4 text-xs text-gray-500">
                      <span className="font-semibold text-gray-700">{run.processed_items || 0}</span> / {run.total_items ?? '—'} items
                      {run.success_items > 0 && (
                        <span className="ml-1 text-[11px] text-green-600">({run.success_items} ok)</span>
                      )}
                    </td>
                    <td className="px-5 py-4 text-xs text-gray-500">
                      {run.started_at ? new Date(run.started_at).toLocaleString() : '—'}
                    </td>
                    <td className="px-5 py-4 text-right">
                      <div className="flex items-center justify-end gap-2">
                        <button
                          type="button"
                          onClick={() => openDetails(run)}
                          className="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 shadow-sm transition-colors"
                        >
                          Details
                        </button>
                        {['queued', 'running'].includes(run.status) && (
                          <button
                            type="button"
                            onClick={() => router.post(`/admin/dropshipping/runs/${run.id}/cancel`, {}, { preserveScroll: true })}
                            className="rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100 transition-colors"
                          >
                            Cancel
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {runs.links?.length > 3 && (
          <div className="flex flex-wrap gap-2 border-t border-gray-100 px-5 py-4">
            {runs.links.map((link, index) => (
              <button
                key={index}
                disabled={!link.url}
                onClick={() => link.url && router.get(link.url, {}, { preserveState: true, replace: true })}
                className={`rounded-lg px-3 py-1.5 text-xs font-medium ${link.active ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600'} ${!link.url ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-200'}`}
                dangerouslySetInnerHTML={{ __html: link.label }}
              />
            ))}
          </div>
        )}
      </section>

      {/* Sync Run Details Modal */}
      {selectedRun && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
          <div className="relative w-full max-w-2xl max-h-[90vh] overflow-hidden rounded-2xl bg-white shadow-2xl flex flex-col animate-in fade-in zoom-in-95 duration-150">
            {/* Header */}
            <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4 bg-gray-50/50">
              <div className="flex items-center gap-3">
                <span className="font-mono text-sm font-bold text-gray-500">#{selectedRun.id}</span>
                <h3 className="text-base font-bold text-gray-900">
                  {selectedRun.supplier?.name || 'Sync Run Details'}
                </h3>
                <Status value={selectedRun.status} />
              </div>
              <button
                type="button"
                onClick={() => setSelectedRun(null)}
                className="rounded-xl p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
              >
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            {/* Body */}
            <div className="flex-1 overflow-y-auto p-6 space-y-5">
              {/* Stat Cards */}
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div className="rounded-xl border border-gray-100 bg-gray-50/50 p-3">
                  <p className="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Type</p>
                  <p className="mt-1 text-sm font-bold text-gray-800 capitalize">
                    {String(selectedRun.type || '—').replaceAll('_', ' ')}
                  </p>
                </div>
                <div className="rounded-xl border border-gray-100 bg-gray-50/50 p-3">
                  <p className="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Progress</p>
                  <p className="mt-1 text-sm font-bold text-gray-800">
                    {selectedRun.processed_items || 0} / {selectedRun.total_items ?? '—'}
                  </p>
                </div>
                <div className="rounded-xl border border-green-100 bg-green-50/50 p-3">
                  <p className="text-[11px] font-semibold text-green-600 uppercase tracking-wider">Succeeded</p>
                  <p className="mt-1 text-sm font-bold text-green-700">
                    {selectedRun.success_items || 0} items
                  </p>
                </div>
                <div className="rounded-xl border border-red-100 bg-red-50/50 p-3">
                  <p className="text-[11px] font-semibold text-red-600 uppercase tracking-wider">Failed</p>
                  <p className="mt-1 text-sm font-bold text-red-700">
                    {selectedRun.failed_items || 0} items
                  </p>
                </div>
              </div>

              {/* Timing */}
              <div className="rounded-xl border border-gray-100 bg-white p-3 text-xs text-gray-500 flex flex-wrap gap-x-6 gap-y-1">
                <div>
                  <span className="text-gray-400">Started:</span>{' '}
                  <span className="font-medium text-gray-700">
                    {selectedRun.started_at ? new Date(selectedRun.started_at).toLocaleString() : '—'}
                  </span>
                </div>
                <div>
                  <span className="text-gray-400">Finished:</span>{' '}
                  <span className="font-medium text-gray-700">
                    {selectedRun.finished_at ? new Date(selectedRun.finished_at).toLocaleString() : 'In progress'}
                  </span>
                </div>
              </div>

              {/* Error Summary Banner */}
              {selectedRun.error_summary && (
                <div className="rounded-xl border border-rose-200 bg-rose-50/80 p-4">
                  <div className="flex items-start gap-3">
                    <div className="rounded-lg bg-rose-100 p-1.5 text-rose-600 shrink-0">
                      <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                      </svg>
                    </div>
                    <div>
                      <h4 className="text-sm font-bold text-rose-900">Run Error Summary</h4>
                      <p className="mt-1 text-xs text-rose-800 leading-relaxed font-mono whitespace-pre-wrap break-all">
                        {selectedRun.error_summary}
                      </p>
                    </div>
                  </div>
                </div>
              )}

              {/* Item Level Details */}
              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <h4 className="text-xs font-bold uppercase tracking-wider text-gray-600">
                    Items & Batches ({selectedRun.items?.length || 0})
                  </h4>
                  {loadingDetails && (
                    <span className="text-xs text-orange-500 animate-pulse font-medium">
                      Loading latest items...
                    </span>
                  )}
                </div>

                {(!selectedRun.items || selectedRun.items.length === 0) ? (
                  <div className="rounded-xl border border-gray-100 bg-gray-50/50 p-6 text-center text-xs text-gray-400">
                    {loadingDetails ? 'Fetching item details...' : 'No individual items logged for this run.'}
                  </div>
                ) : (
                  <div className="overflow-hidden rounded-xl border border-gray-200">
                    <table className="w-full text-xs">
                      <thead className="bg-gray-50 text-left text-[10px] uppercase text-gray-400">
                        <tr>
                          <th className="px-4 py-2.5">Item Key</th>
                          <th className="px-4 py-2.5">Status</th>
                          <th className="px-4 py-2.5">Error / Details</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-gray-100">
                        {selectedRun.items.map((item, idx) => (
                          <tr key={item.id || idx} className="hover:bg-gray-50/50">
                            <td className="px-4 py-2.5 font-mono text-gray-600 font-semibold">
                              {item.key || item.item_key}
                            </td>
                            <td className="px-4 py-2.5">
                              <span className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold ${item.status === 'succeeded' ? 'bg-green-100 text-green-700' : item.status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600'}`}>
                                {item.status}
                              </span>
                            </td>
                            <td className="px-4 py-2.5 text-gray-700">
                              {item.error || item.error_summary ? (
                                <span className="font-mono text-red-600 bg-red-50 rounded px-1.5 py-0.5 break-all">
                                  {item.error || item.error_summary}
                                </span>
                              ) : (
                                <span className="text-gray-400">—</span>
                              )}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            </div>

            {/* Footer */}
            <div className="border-t border-gray-100 px-6 py-3.5 bg-gray-50 flex items-center justify-end">
              <button
                type="button"
                onClick={() => setSelectedRun(null)}
                className="rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-100 transition-colors shadow-sm"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </DropshippingSubpage>
  );
}
