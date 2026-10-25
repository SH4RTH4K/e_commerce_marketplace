import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function OptimizedImagesTrash({ batches = [], trash_retention_days = 30 }) {
  const [busy, setBusy] = useState(false);
  const [preview, setPreview] = useState(null);

  const post = (path, data = {}) => {
    setBusy(true);
    router.post(path, data, { preserveScroll: true, onFinish: () => setBusy(false) });
  };

  const restore = file => {
    if (!window.confirm(`Restore ${file.filename} to the optimized image directory?`)) return;
    post('/admin/system/recovery-trash/restore', { batch: file.batch, filename: file.filename });
  };

  const purge = () => {
    if (!window.confirm(`Permanently remove all recovery batches older than ${trash_retention_days} days? This cannot be undone.`)) return;
    post('/admin/system/recovery-trash/purge');
  };

  return (
    <AdminLayout title="Recovery Trash">
      <Head title="Recovery Trash" />
      <div className="mx-auto max-w-7xl space-y-5">
        <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
          <div>
            <p className="text-xs font-bold uppercase tracking-widest text-orange-500">System</p>
            <h2 className="mt-1 text-2xl font-bold text-slate-900">Recovery Trash</h2>
            <p className="mt-1 text-sm text-slate-500">Privately held optimized images can be previewed and restored for {trash_retention_days} days.</p>
          </div>
          <button type="button" onClick={purge} disabled={busy || !batches.some(batch => batch.expired)} className="rounded-xl bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-40">Purge expired</button>
        </div>

        {batches.map(batch => (
          <section key={batch.id} className="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
            <div className="flex flex-col justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center">
              <div>
                <h3 className="font-bold text-slate-900">Recovery batch <code className="text-xs font-medium text-slate-500">{batch.id}</code></h3>
                <p className="mt-1 text-xs text-slate-500">{batch.file_count} file(s) · {batch.formatted} · Created {formatDate(batch.created_at)} · {batch.expired ? 'Expired' : `Expires ${formatDate(batch.expires_at)}`}</p>
              </div>
              <span className={`rounded-full px-3 py-1 text-xs font-semibold ${batch.expired ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700'}`}>{batch.expired ? 'Expired' : `${Math.max(0, trash_retention_days - batch.age_days)} days remaining`}</span>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[760px] text-left text-sm">
                <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-5 py-3">File</th><th className="px-5 py-3">Size</th><th className="px-5 py-3 text-right">Actions</th></tr></thead>
                <tbody className="divide-y divide-slate-100">
                  {batch.files.map(file => {
                    const item = { ...file, batch: batch.id };
                    return <tr key={file.filename} className="hover:bg-slate-50/70"><td className="px-5 py-3"><button type="button" onClick={() => setPreview(item)} className="flex items-center gap-3 text-left"><img src={file.preview_url} alt="" loading="lazy" className="h-12 w-12 rounded-lg border border-slate-200 bg-slate-50 object-contain p-0.5" /><span className="font-mono text-xs text-slate-700">{file.filename}</span></button></td><td className="px-5 py-3 text-slate-600">{file.formatted}</td><td className="px-5 py-3 text-right"><button type="button" onClick={() => restore(item)} disabled={busy || batch.expired} className="rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-40">Restore</button></td></tr>;
                  })}
                </tbody>
              </table>
            </div>
          </section>
        ))}

        {!batches.length && <div className="rounded-2xl border border-dashed border-slate-200 bg-white p-12 text-center text-sm text-emerald-600 shadow-sm">Recovery Trash is empty.</div>}
      </div>

      {preview && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/75 p-4" role="dialog" aria-modal="true" aria-label="Recovery image preview" onClick={() => setPreview(null)}><div className="relative max-h-[90vh] w-full max-w-4xl rounded-2xl bg-white p-3 shadow-2xl" onClick={event => event.stopPropagation()}><button type="button" onClick={() => setPreview(null)} className="absolute right-3 top-3 z-10 rounded-full bg-slate-900/80 px-3 py-1.5 text-sm font-semibold text-white">Close</button><img src={preview.preview_url} alt={preview.filename} className="max-h-[78vh] w-full rounded-xl object-contain" /><p className="px-2 pb-1 pt-3 text-center font-mono text-xs text-slate-500">{preview.filename}</p></div></div>}
    </AdminLayout>
  );
}

function formatDate(value) {
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}
