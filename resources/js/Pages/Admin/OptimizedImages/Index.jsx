import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function OptimizedImagesIndex({ files = [], orphaned_count = 0, orphaned_formatted = '0 B', protected_count = 0, directory = 'public/uploads/optimized', pagination = {} }) {
  const [selected, setSelected] = useState([]);
  const [busy, setBusy] = useState(false);
  const [preview, setPreview] = useState(null);
  const orphanedFiles = files.filter(file => (file.status || 'orphaned') === 'orphaned');
  const currentPage = pagination.current_page || 1;
  const lastPage = pagination.last_page || 1;
  const perPage = pagination.per_page || 50;
  const pageNumbers = getPageNumbers(currentPage, lastPage);

  const toggle = filename => setSelected(current => current.includes(filename)
    ? current.filter(item => item !== filename)
    : [...current, filename]);

  const toggleAll = event => setSelected(event.target.checked ? orphanedFiles.map(file => file.filename) : []);

  const deleteFiles = filenames => {
    setBusy(true);
    router.post('/admin/system/optimized-images/delete', { filenames }, {
      preserveScroll: true,
      onFinish: () => setBusy(false),
      onSuccess: () => setSelected([]),
    });
  };

  const deleteSelected = () => {
    if (!selected.length || !window.confirm(`Delete ${selected.length} orphaned optimized image(s)? This cannot be undone.`)) return;
    deleteFiles(selected);
  };

  const goToPage = (page, limit = perPage) => {
    setBusy(true);
    router.get('/admin/system/optimized-images', { page, per_page: limit }, {
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => setSelected([]),
      onFinish: () => setBusy(false),
    });
  };

  return (
    <AdminLayout title="Optimized Images">
      <Head title="Optimized Images" />
      <div className="mx-auto max-w-7xl space-y-5">
        <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
          <div>
            <p className="text-xs font-bold uppercase tracking-widest text-orange-500">System</p>
            <h2 className="mt-1 text-2xl font-bold text-slate-900">Optimized image cleanup</h2>
            <p className="mt-1 text-sm text-slate-500">Review generated WebP files that are no longer referenced by a current product, category, banner, or logo.</p>
          </div>
          <div className="flex items-center gap-2">
            <label className="text-xs font-semibold text-slate-500" htmlFor="optimized-images-limit">Show</label>
            <select id="optimized-images-limit" value={perPage} onChange={event => goToPage(1, Number(event.target.value))} disabled={busy} className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 disabled:opacity-50">
              {[25, 50, 100, 250, 500].map(limit => <option key={limit} value={limit}>{limit} per page</option>)}
            </select>
            <button type="button" onClick={() => goToPage(currentPage)} disabled={busy} className="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-50">Rescan</button>
          </div>
        </div>

        <div className="grid gap-3 sm:grid-cols-3">
          <Stat label="Orphaned files" value={orphaned_count} />
          <Stat label="Orphaned size" value={orphaned_formatted} />
          <Stat label="Protected current variants" value={protected_count.toLocaleString()} />
        </div>

        <section className="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
          <div className="flex flex-col justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center">
            <div>
              <h3 className="font-bold text-slate-900">Orphaned files</h3>
              <p className="mt-1 text-xs text-slate-500">Only orphaned files are shown and eligible for deletion. Current referenced variants are never shown here. Directory: <code>{directory}</code>.</p>
            </div>
            <button type="button" onClick={deleteSelected} disabled={!selected.length || busy} className="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-40">Delete selected ({selected.length})</button>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full min-w-[900px] text-left text-sm">
              <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                  <th className="w-14 px-5 py-3">SL</th>
                  <th className="w-10 px-5 py-3"><input type="checkbox" aria-label="Select all orphaned files" checked={orphanedFiles.length > 0 && selected.length === orphanedFiles.length} onChange={toggleAll} /></th>
                  <th className="px-5 py-3">File</th>
                  <th className="px-5 py-3">Status</th>
                  <th className="px-5 py-3">Size</th>
                  <th className="px-5 py-3">Age</th>
                  <th className="px-5 py-3">Modified</th>
                  <th className="px-5 py-3 text-right">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {orphanedFiles.map((file, index) => (
                  <tr key={file.filename} className="hover:bg-slate-50/70">
                    <td className="px-5 py-3 text-xs font-semibold text-slate-400">{index + 1}</td>
                    <td className="px-5 py-3"><input type="checkbox" checked={selected.includes(file.filename)} onChange={() => toggle(file.filename)} aria-label={`Select ${file.filename}`} /></td>
                    <td className="px-5 py-3">
                      <button type="button" onClick={() => setPreview(file)} className="group flex items-center gap-3 text-left" aria-label={`Preview ${file.filename}`}>
                        <img src={`/uploads/optimized/${file.filename}`} alt="" loading="lazy" decoding="async" className="h-12 w-12 rounded-lg border border-slate-200 bg-slate-50 object-contain p-0.5 transition group-hover:border-orange-300" />
                        <span><p className="font-mono text-xs text-slate-700 group-hover:text-orange-600">{file.filename}</p><p className="mt-1 text-[11px] text-slate-400">{file.path}</p></span>
                      </button>
                    </td>
                    <td className="px-5 py-3"><span className="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold capitalize text-amber-700">{file.status || 'orphaned'}</span></td>
                    <td className="px-5 py-3 text-slate-600">{file.formatted}</td>
                    <td className="px-5 py-3 text-slate-600">{file.age_days} day{file.age_days === 1 ? '' : 's'}</td>
                    <td className="px-5 py-3 text-xs text-slate-500">{file.modified_at}</td>
                    <td className="px-5 py-3 text-right"><button type="button" onClick={() => { if (window.confirm('Delete this orphaned optimized image? This cannot be undone.')) deleteFiles([file.filename]); }} disabled={busy} className="rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100 disabled:opacity-50">Delete</button></td>
                  </tr>
                ))}
                {!orphanedFiles.length && <tr><td colSpan="8" className="px-5 py-12 text-center text-sm text-emerald-600">No orphaned optimized images found.</td></tr>}
              </tbody>
            </table>
          </div>

          <div className="flex flex-col justify-between gap-3 border-t border-slate-100 px-5 py-4 text-sm sm:flex-row sm:items-center">
            <p className="text-xs text-slate-500">Showing {pagination.from || 0}–{pagination.to || 0} of {pagination.total ?? orphaned_count} orphaned files</p>
            <div className="flex items-center gap-1">
              <button type="button" onClick={() => goToPage(currentPage - 1)} disabled={busy || currentPage <= 1} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40">Previous</button>
              {pageNumbers.map((page, index) => page === 'ellipsis'
                ? <span key={`ellipsis-${index}`} className="px-1 text-slate-400">…</span>
                : <button key={page} type="button" onClick={() => goToPage(page)} disabled={busy || page === currentPage} className={`rounded-lg px-3 py-1.5 text-xs font-semibold ${page === currentPage ? 'bg-orange-500 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50'} disabled:cursor-default`}>{page}</button>)}
              <button type="button" onClick={() => goToPage(currentPage + 1)} disabled={busy || currentPage >= lastPage} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40">Next</button>
            </div>
          </div>
        </section>
      </div>

      {preview && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/75 p-4" role="dialog" aria-modal="true" aria-label="Image preview" onClick={() => setPreview(null)}>
          <div className="relative max-h-[90vh] w-full max-w-4xl rounded-2xl bg-white p-3 shadow-2xl" onClick={event => event.stopPropagation()}>
            <button type="button" onClick={() => setPreview(null)} className="absolute right-3 top-3 z-10 rounded-full bg-slate-900/80 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-900" aria-label="Close image preview">Close</button>
            <img src={`/uploads/optimized/${preview.filename}`} alt={preview.filename} className="max-h-[78vh] w-full rounded-xl object-contain" />
            <p className="px-2 pb-1 pt-3 text-center font-mono text-xs text-slate-500">{preview.filename}</p>
          </div>
        </div>
      )}
    </AdminLayout>
  );
}

function getPageNumbers(current, last) {
  if (last <= 7) return Array.from({ length: last }, (_, index) => index + 1);

  const pages = new Set([1, last, current - 1, current, current + 1]);
  const ordered = [...pages].filter(page => page > 0 && page <= last).sort((a, b) => a - b);
  const result = [];

  ordered.forEach((page, index) => {
    if (index > 0 && page - ordered[index - 1] > 1) result.push('ellipsis');
    result.push(page);
  });

  return result;
}

function Stat({ label, value }) {
  return <div className="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm"><p className="text-xs uppercase tracking-wide text-slate-400">{label}</p><p className="mt-2 text-2xl font-bold text-slate-900">{value}</p></div>;
}
