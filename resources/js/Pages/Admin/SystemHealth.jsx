import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';
import { useRef, useState } from 'react';

const checks = [
  ['database', 'Database', 'Connection to the configured database'],
  ['storage', 'Storage', 'Runtime directories and log access'],
  ['php', 'PHP runtime', 'Required PHP version'],
  ['laravel', 'Laravel', 'Framework version'],
  ['disk', 'Disk space', 'Available filesystem capacity'],
  ['environment', 'Environment', 'Production-safe debug configuration'],
];

const buttonClass = 'inline-flex items-center justify-center rounded-xl px-3.5 py-2 text-sm font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-50';

function formatBytes(value) {
  const bytes = Number(value || 0);
  if (!bytes) return '—';
  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  let amount = bytes;
  let index = 0;
  while (amount >= 1024 && index < units.length - 1) {
    amount /= 1024;
    index += 1;
  }
  return `${amount.toFixed(index ? 1 : 0)} ${units[index]}`;
}

function formatDate(value) {
  if (!value) return '—';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

function StatusIcon({ ok }) {
  return ok ? (
    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">✓</span>
  ) : (
    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100 text-amber-600">!</span>
  );
}

function ActionButton({ children, onClick, tone = 'slate', disabled = false }) {
  const tones = {
    slate: 'bg-slate-900 text-white hover:bg-slate-700',
    blue: 'bg-blue-600 text-white hover:bg-blue-700',
    emerald: 'bg-emerald-600 text-white hover:bg-emerald-700',
    amber: 'bg-amber-100 text-amber-800 hover:bg-amber-200',
    red: 'bg-red-50 text-red-700 hover:bg-red-100',
  };
  return <button type="button" className={`${buttonClass} ${tones[tone]}`} onClick={onClick} disabled={disabled}>{children}</button>;
}

export default function SystemHealth({ health = {}, migrations = {}, backups = [], backupRetentionDays = 14 }) {
  const [busy, setBusy] = useState(false);
  const databaseFile = useRef(null);
  const mediaFile = useRef(null);
  const fullFile = useRef(null);

  const post = (path, data = {}) => {
    setBusy(true);
    router.post(path, data, { preserveScroll: true, onFinish: () => setBusy(false) });
  };

  const confirmPost = (message, path) => {
    if (window.confirm(message)) post(path);
  };

  const restore = (event, field, inputRef, path, message) => {
    event.preventDefault();
    const file = inputRef.current?.files?.[0];
    if (!file) return;
    if (!window.confirm(message)) return;

    const form = new FormData();
    form.append(field, file);
    setBusy(true);
    router.post(path, form, {
      forceFormData: true,
      preserveScroll: true,
      onFinish: () => {
        setBusy(false);
        if (inputRef.current) inputRef.current.value = '';
      },
    });
  };

  return (
    <AdminLayout title="System Health">
      <Head title="System Health" />

      <div className="mx-auto max-w-7xl space-y-5">
        <div className="flex flex-col justify-between gap-2 sm:flex-row sm:items-end">
          <div>
            <p className="text-xs font-bold uppercase tracking-widest text-orange-500">Operations</p>
            <h2 className="mt-1 text-2xl font-bold text-slate-900">System health & backups</h2>
            <p className="mt-1 text-sm text-slate-500">Live checks and recovery tools for this store.</p>
          </div>
          <p className="text-xs text-slate-400">Retention: {backupRetentionDays} days</p>
        </div>

        <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {checks.map(([key, label, description]) => {
            const item = health[key] || { ok: false, message: 'Unavailable' };
            return (
              <div key={key} className="flex items-start gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                <StatusIcon ok={item.ok} />
                <div className="min-w-0">
                  <h3 className="font-semibold text-slate-900">{label}</h3>
                  <p className="mt-1 break-words text-sm text-slate-500">{item.message}</p>
                  <p className="mt-1 text-[11px] text-slate-400">{description}</p>
                </div>
              </div>
            );
          })}
        </section>

        <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
          <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div>
              <h3 className="font-bold text-slate-900">Database migrations</h3>
              <p className={`mt-1 text-sm ${migrations.ok ? 'text-emerald-600' : 'text-amber-600'}`}>{migrations.message || 'Unable to check migrations.'}</p>
            </div>
            {!migrations.ok && (
              <ActionButton tone="amber" disabled={busy} onClick={() => confirmPost('Run all pending database migrations now?', '/admin/system-health/migrations/run')}>
                Run pending migrations
              </ActionButton>
            )}
          </div>
          <div className="mt-4 flex flex-wrap gap-2 text-xs text-slate-500">
            <span className="rounded-full bg-slate-100 px-3 py-1">Applied: {migrations.ran ?? 0}</span>
            <span className="rounded-full bg-slate-100 px-3 py-1">Total: {migrations.total ?? 0}</span>
            {(migrations.pending || []).length > 0 && <span className="rounded-full bg-amber-50 px-3 py-1 text-amber-700">Pending: {migrations.pending.length}</span>}
          </div>
          {(migrations.pending || []).length > 0 && (
            <div className="mt-3 rounded-xl bg-amber-50 p-3 text-xs text-amber-800">{migrations.pending.join(', ')}</div>
          )}
        </section>

        <div className="grid gap-5 xl:grid-cols-[1.25fr_.75fr]">
          <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
              <div>
                <h3 className="font-bold text-slate-900">Create backups</h3>
                <p className="mt-1 text-sm text-slate-500">Backups are stored privately outside the public web root.</p>
              </div>
              <div className="flex flex-wrap gap-2">
                <ActionButton disabled={busy} onClick={() => post('/admin/system-health/backup')}>Database</ActionButton>
                <ActionButton tone="blue" disabled={busy} onClick={() => post('/admin/system-health/media-backup')}>Media</ActionButton>
                <ActionButton tone="emerald" disabled={busy} onClick={() => post('/admin/system-health/full-backup')}>Full</ActionButton>
              </div>
            </div>

            <div className="mt-5 space-y-4 border-t border-slate-100 pt-5">
              <RestoreForm label="Restore database" accept=".sql.gz,application/gzip" inputRef={databaseFile} field="backup" path="/admin/system-health/restore" busy={busy} onSubmit={(event) => restore(event, 'backup', databaseFile, '/admin/system-health/restore', 'This replaces the current database. Continue?')} />
              <RestoreForm label="Restore media" accept=".tar.gz,application/gzip" inputRef={mediaFile} field="media_backup" path="/admin/system-health/media-restore" busy={busy} onSubmit={(event) => restore(event, 'media_backup', mediaFile, '/admin/system-health/media-restore', 'This overwrites matching uploaded media files. Continue?')} />
              <RestoreForm label="Restore full backup" accept=".tar.gz,application/gzip" inputRef={fullFile} field="full_backup" path="/admin/system-health/full-restore" busy={busy} onSubmit={(event) => restore(event, 'full_backup', fullFile, '/admin/system-health/full-restore', 'This replaces the database and uploaded media. Continue?')} />
            </div>
          </section>

          <section className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <h3 className="font-bold text-slate-900">Maintenance</h3>
            <p className="mt-1 text-sm text-slate-500">Use after code, design, or configuration changes.</p>
            <ActionButton tone="amber" disabled={busy} onClick={() => confirmPost('Clear application caches and rebuild compiled views?', '/admin/system-health/clear-cache')}>
              Clear & rebuild caches
            </ActionButton>
            <div className="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
              <p><strong>Automatic backup:</strong> daily at 02:00</p>
              <p className="mt-1"><strong>Scheduler:</strong> run <code className="rounded bg-white px-1.5 py-0.5 text-xs">php artisan schedule:run</code> every minute.</p>
              <p className="mt-3 text-xs text-slate-500">Database and full restores create a safety backup first.</p>
            </div>
          </section>
        </div>

        <section className="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
          <div className="border-b border-slate-100 px-5 py-4"><h3 className="font-bold text-slate-900">Backup history</h3></div>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[760px] text-left text-sm">
              <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-5 py-3">Backup</th><th className="px-5 py-3">Size</th><th className="px-5 py-3">Status</th><th className="px-5 py-3">Created</th><th className="px-5 py-3 text-right">Actions</th></tr></thead>
              <tbody className="divide-y divide-slate-100">
                {backups.map((backup) => (
                  <tr key={backup.id} className="hover:bg-slate-50/70">
                    <td className="px-5 py-3"><p className="font-semibold text-slate-800">{backup.filename}</p><p className="text-xs text-slate-400">By {backup.created_by || 'scheduler'}</p>{backup.error && <p className="mt-1 max-w-md text-xs text-red-600">{backup.error}</p>}</td>
                    <td className="px-5 py-3 text-slate-600">{formatBytes(backup.size_bytes)}</td>
                    <td className="px-5 py-3"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${backup.status === 'completed' ? 'bg-emerald-100 text-emerald-700' : backup.status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'}`}>{backup.status}</span></td>
                    <td className="px-5 py-3 text-xs text-slate-500">{formatDate(backup.created_at)}</td>
                    <td className="px-5 py-3 text-right"><div className="flex justify-end gap-2">{backup.status === 'completed' && <a className="rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100" href={`/admin/system-health/backups/${backup.id}/download`}>Download</a>}<button type="button" className="rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100" onClick={() => confirmPost('Permanently delete this backup file?', `/admin/system-health/backups/${backup.id}/delete`)}>Delete</button></div></td>
                  </tr>
                ))}
                {backups.length === 0 && <tr><td colSpan="5" className="px-5 py-10 text-center text-sm text-slate-400">No backups exist yet.</td></tr>}
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </AdminLayout>
  );
}

function RestoreForm({ label, accept, inputRef, field, busy, onSubmit }) {
  return <form onSubmit={onSubmit} className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><p className="text-sm font-semibold text-slate-800">{label}</p><p className="text-xs text-slate-400">Generated {field === 'backup' ? '.sql.gz' : '.tar.gz'} files only.</p></div><div className="flex flex-wrap gap-2"><input ref={inputRef} type="file" name={field} accept={accept} required className="max-w-full text-xs text-slate-500 file:mr-2 file:rounded-lg file:border-0 file:bg-slate-100 file:px-2.5 file:py-1.5 file:text-xs file:font-semibold file:text-slate-700" /><button type="submit" disabled={busy} className={`${buttonClass} bg-amber-100 text-amber-800 hover:bg-amber-200`}>Restore</button></div></form>;
}
