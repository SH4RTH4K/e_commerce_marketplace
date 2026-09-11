import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const buttonClass = 'inline-flex items-center justify-center rounded-xl px-3.5 py-2.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50';

function shortCommit(value) {
  return value ? String(value).slice(0, 12) : '—';
}

function dateValue(value) {
  if (!value) return '—';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

function StatusPill({ value }) {
  const styles = {
    'Up to date': 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    'Update Available': 'bg-blue-50 text-blue-700 ring-blue-200',
    'Local Changes Detected': 'bg-amber-50 text-amber-800 ring-amber-200',
    'Diverged Branch': 'bg-rose-50 text-rose-700 ring-rose-200',
    'Branch Not Found': 'bg-rose-50 text-rose-700 ring-rose-200',
    Disabled: 'bg-slate-100 text-slate-600 ring-slate-200',
    Completed: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  };
  return <span className={`inline-flex rounded-full px-3 py-1 text-xs font-bold ring-1 ${styles[value] || styles.Disabled}`}>{value || 'Unknown'}</span>;
}

function ActionButton({ children, tone = 'dark', ...props }) {
  const tones = {
    dark: 'bg-slate-900 text-white hover:bg-slate-700',
    orange: 'bg-orange-500 text-white hover:bg-orange-600',
    blue: 'bg-blue-600 text-white hover:bg-blue-700',
    light: 'bg-slate-100 text-slate-700 hover:bg-slate-200',
    danger: 'bg-rose-50 text-rose-700 hover:bg-rose-100',
  };
  return <button type="button" className={`${buttonClass} ${tones[tone]}`} {...props}>{children}</button>;
}

function ErrorText({ children }) {
  return children ? <p className="mt-1 text-xs font-medium text-rose-600">{children}</p> : null;
}

export default function GitRepository({ settings = {}, status = null, error = null, history = [] }) {
  const { props } = usePage();
  const [busy, setBusy] = useState(false);
  const form = useForm({
    enabled: Boolean(settings.enabled),
    repository_type: settings.repository_type || 'public',
    repository_url: settings.repository_url || 'https://github.com/SH4RTH4K/e_commerce_marketplace.git',
    branch: settings.branch || 'main',
    remote_name: settings.remote_name || 'origin',
    authentication: settings.authentication || 'none',
    secret: '',
    run_migrations: settings.run_migrations !== false,
    clear_cache: settings.clear_cache !== false,
    health_check: settings.health_check !== false,
  });

  const run = (method, path, data = {}, message = null) => {
    if (message && !window.confirm(message)) return;
    setBusy(true);
    router[method](path, data, { preserveScroll: true, onFinish: () => setBusy(false) });
  };

  const submitSettings = (event) => {
    event.preventDefault();
    setBusy(true);
    form.post('/admin/system/git-repository', { preserveScroll: true, onFinish: () => setBusy(false) });
  };

  const privateRepository = form.data.repository_type === 'private';
  const currentStatus = status?.status || (settings.enabled ? 'Not checked' : 'Disabled');
  const flashStatus = props.flash?.status;
  const flashError = props.flash?.error;

  return (
    <AdminLayout title="Git Repository">
      <Head title="Git Repository" />

      <div className="mx-auto max-w-7xl space-y-5">
        <section className="overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-slate-900 to-orange-900 p-6 text-white shadow-xl shadow-slate-200 sm:p-8">
          <div className="flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
            <div className="max-w-2xl">
              <p className="text-xs font-bold uppercase tracking-[0.24em] text-orange-300">System / Deployment</p>
              <h1 className="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Git Repository</h1>
              <p className="mt-3 text-sm leading-6 text-slate-300">Connect this cPanel installation to GitHub, review incoming commits, and deploy only after the server state is safe.</p>
            </div>
            <div className="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur">
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-300">Current state</p>
              <div className="mt-2"><StatusPill value={currentStatus} /></div>
            </div>
          </div>
        </section>

        {(flashStatus || flashError || error) && (
          <div className={`rounded-2xl border p-4 text-sm ${flashError || error ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'}`}>
            {flashError || error || flashStatus}
          </div>
        )}

        <section className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6">
          <div className="mb-5 flex flex-col justify-between gap-2 sm:flex-row sm:items-start">
            <div>
              <p className="text-xs font-bold uppercase tracking-widest text-orange-500">Connection</p>
              <h2 className="mt-1 text-xl font-bold text-slate-900">Repository settings</h2>
              <p className="mt-1 text-sm text-slate-500">The server must already be a Git checkout of the configured repository.</p>
            </div>
            <span className={`rounded-full px-3 py-1 text-xs font-bold ${form.data.enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>{form.data.enabled ? 'Integration enabled' : 'Integration disabled'}</span>
          </div>

          <form onSubmit={submitSettings} className="space-y-5">
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
              <label className="text-xs font-bold text-slate-600">Integration
                <select value={form.data.enabled ? '1' : '0'} onChange={event => form.setData('enabled', event.target.value === '1')} className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal text-slate-800 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
                  <option value="0">Off</option><option value="1">On</option>
                </select>
              </label>
              <label className="text-xs font-bold text-slate-600">Repository type
                <select value={form.data.repository_type} onChange={event => form.setData('repository_type', event.target.value)} className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal text-slate-800 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
                  <option value="public">Public</option><option value="private">Private</option>
                </select>
              </label>
              <label className="text-xs font-bold text-slate-600">Branch
                <input value={form.data.branch} onChange={event => form.setData('branch', event.target.value)} required className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal text-slate-800 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100" />
                <ErrorText>{form.errors.branch}</ErrorText>
              </label>
              <label className="text-xs font-bold text-slate-600">Remote name
                <input value={form.data.remote_name} onChange={event => form.setData('remote_name', event.target.value)} required className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal text-slate-800 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100" />
              </label>
            </div>

            <label className="block text-xs font-bold text-slate-600">GitHub repository URL
              <input value={form.data.repository_url} onChange={event => form.setData('repository_url', event.target.value)} required placeholder="https://github.com/company/project.git" className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal text-slate-800 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100" />
              <span className="mt-1 block text-xs font-normal text-slate-400">Use the repository URL configured in the deployed checkout. This page never pushes changes to GitHub.</span>
              <ErrorText>{form.errors.repository_url}</ErrorText>
            </label>

            <div className="grid gap-4 md:grid-cols-2">
              <label className="text-xs font-bold text-slate-600">Authentication
                <select value={form.data.authentication} onChange={event => form.setData('authentication', event.target.value)} className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal text-slate-800 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
                  <option value="none">None (public repository)</option><option value="ssh">SSH deploy key</option><option value="pat">Personal access token</option>
                </select>
                <ErrorText>{form.errors.authentication}</ErrorText>
              </label>
              <label className="text-xs font-bold text-slate-600">{privateRepository ? 'Private credential' : 'Replace credential'}
                <input type="password" value={form.data.secret} onChange={event => form.setData('secret', event.target.value)} autoComplete="new-password" placeholder={settings.has_secret ? 'Leave blank to keep the saved credential' : 'Only required for private repositories'} className="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-normal text-slate-800 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100" />
                <span className="mt-1 block text-xs font-normal text-slate-400">Encrypted at rest and never displayed after saving.</span>
                <ErrorText>{form.errors.secret}</ErrorText>
              </label>
            </div>

            <div className="flex flex-wrap gap-3 border-t border-slate-100 pt-4 text-sm text-slate-700">
              {[['run_migrations', 'Run migrations after deployment'], ['clear_cache', 'Clear and rebuild application cache'], ['health_check', 'Run a database health check']].map(([key, label]) => (
                <label key={key} className="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2"><input type="checkbox" checked={Boolean(form.data[key])} onChange={event => form.setData(key, event.target.checked)} />{label}</label>
              ))}
            </div>

            <div className="flex flex-wrap gap-2">
              <button type="submit" disabled={busy || form.processing} className={`${buttonClass} bg-orange-500 text-white hover:bg-orange-600`}>{form.processing ? 'Saving…' : 'Save settings'}</button>
              {form.data.enabled && <ActionButton tone="light" disabled={busy} onClick={() => run('post', '/admin/system/git-repository/test')}>Test connection</ActionButton>}
            </div>
          </form>
        </section>

        <section className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6">
          <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
            <div><p className="text-xs font-bold uppercase tracking-widest text-orange-500">Safe workflow</p><h2 className="mt-1 text-xl font-bold text-slate-900">Review before deployment</h2><p className="mt-1 text-sm text-slate-500">Fetch first, inspect the commit list, then deploy only a fast-forward update.</p></div>
            {status?.local && <div className="text-right text-xs text-slate-500"><p>Local <code className="font-semibold text-slate-800">{shortCommit(status.local)}</code></p><p>Remote <code className="font-semibold text-slate-800">{shortCommit(status.remote)}</code></p></div>}
          </div>

          <div className="mt-5 grid gap-3 md:grid-cols-3">
            {[
              ['01', 'Check for updates', 'Fetch GitHub and review commits without changing application files.', 'Check GitHub', 'post', '/admin/system/git-repository/check', 'blue'],
              ['02', 'Review server state', status?.status === 'Local Changes Detected' ? 'Tracked changes are blocking deployment.' : 'Only tracked changes block the safe deployment path; uploads and runtime files are kept.', null, null],
              ['03', 'Deploy reviewed update', 'Fast-forward the branch, run selected checks, and record the deployment.', status?.status === 'Update Available' ? 'Deploy update' : null, 'post', '/admin/system/git-repository/deploy', 'orange'],
            ].map(([number, title, description, label, method, path, tone]) => (
              <div key={number} className={`rounded-2xl border p-4 ${number === '02' && status?.status === 'Local Changes Detected' ? 'border-amber-200 bg-amber-50' : 'border-slate-100 bg-slate-50/70'}`}>
                <span className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-xs font-bold text-white">{number}</span>
                <h3 className="mt-3 font-bold text-slate-900">{title}</h3>
                <p className="mt-1 min-h-10 text-sm leading-5 text-slate-500">{description}</p>
                {label && <ActionButton tone={tone} disabled={busy || !settings.enabled} onClick={() => run(method, path)}>{label}</ActionButton>}
                {!label && number === '02' && status?.status === 'Local Changes Detected' && <ActionButton tone="danger" disabled={busy} onClick={() => run('post', '/admin/system/git-repository/discard-and-deploy', { confirmation: 'DISCARD LOCAL CHANGES' }, 'This discards tracked server changes and deploys the GitHub update. Untracked uploads are kept. Continue?')}>Discard tracked & deploy</ActionButton>}
              </div>
            ))}
          </div>

          {status?.commits?.length > 0 && <div className="mt-5 overflow-x-auto rounded-2xl border border-slate-100"><table className="w-full min-w-[680px] text-left text-sm"><thead className="bg-slate-50 text-[10px] uppercase tracking-widest text-slate-400"><tr><th className="px-4 py-3">Commit</th><th className="px-4 py-3">Subject</th><th className="px-4 py-3">Author</th><th className="px-4 py-3">Date</th></tr></thead><tbody className="divide-y divide-slate-100">{status.commits.map(commit => <tr key={commit.hash}><td className="px-4 py-3"><code className="rounded bg-slate-100 px-2 py-1 text-xs">{commit.short}</code></td><td className="px-4 py-3 font-medium text-slate-700">{commit.subject}</td><td className="px-4 py-3 text-slate-500">{commit.author}</td><td className="px-4 py-3 text-slate-500">{dateValue(commit.date)}</td></tr>)}</tbody></table></div>}

          {status?.local_changes?.length > 0 && <div className="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4"><h3 className="font-bold text-amber-900">Tracked server changes</h3><ul className="mt-2 space-y-1 font-mono text-xs text-amber-800">{status.local_changes.map(change => <li key={change}>{change}</li>)}</ul><p className="mt-3 text-xs text-amber-800">Untracked files are preserved. The discard action resets tracked files only.</p></div>}
        </section>

        <section className="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm sm:p-6">
          <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><div><p className="text-xs font-bold uppercase tracking-widest text-orange-500">Audit trail</p><h2 className="mt-1 text-xl font-bold text-slate-900">Deployment history</h2></div>{settings.enabled && <ActionButton tone="light" disabled={busy} onClick={() => run('post', '/admin/system/git-repository/pull', {}, 'Pull the latest fast-forward update now?')}>Pull latest</ActionButton>}</div>
          <div className="mt-4 overflow-x-auto"><table className="w-full min-w-[780px] text-left text-sm"><thead className="border-b border-slate-100 text-[10px] uppercase tracking-widest text-slate-400"><tr><th className="px-3 py-3">Date</th><th className="px-3 py-3">Change</th><th className="px-3 py-3">Commit</th><th className="px-3 py-3">Checks</th><th className="px-3 py-3">Status</th><th className="px-3 py-3 text-right">Action</th></tr></thead><tbody className="divide-y divide-slate-100">{history.length === 0 ? <tr><td colSpan="6" className="px-3 py-10 text-center text-sm text-slate-400">No deployments recorded yet.</td></tr> : history.map(item => <tr key={item.id}><td className="px-3 py-3 text-slate-500">{dateValue(item.completed_at || item.started_at)}</td><td className="max-w-[260px] px-3 py-3 font-medium text-slate-700">{item.commit_subject || `${item.commits_applied || 0} commit(s)`}</td><td className="px-3 py-3"><code className="text-xs text-slate-500">{shortCommit(item.deployed_commit || item.target_commit)}</code></td><td className="px-3 py-3 text-xs text-slate-500">Migration: {item.migration_status || '—'}<br />Health: {item.health_status || '—'}</td><td className="px-3 py-3"><StatusPill value={item.status === 'success' ? 'Completed' : item.status} /></td><td className="px-3 py-3 text-right">{item.status === 'success' && !item.rollback_of && <ActionButton tone="light" disabled={busy} onClick={() => run('post', `/admin/system/git-repository/rollback/${item.id}`, {}, 'Rollback source to the previous recorded commit? Database migrations will not be reversed.')}>Rollback</ActionButton>}</td></tr>)}</tbody></table></div>
          <p className="mt-4 text-xs leading-5 text-slate-400">Rollback creates a new source commit and does not reverse database migrations. Review migrations manually after any rollback.</p>
        </section>
      </div>
    </AdminLayout>
  );
}
