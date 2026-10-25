import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import React from 'react';

const providers = {
  mymemory: 'MyMemory (free)',
  google_cloud: 'Google Cloud Translation',
};

export default function TranslationSettings({ settings }) {
  const { flash, auth } = usePage().props;
  const user = auth?.user;
  const userPermissions = Array.isArray(user?.permissions) ? user.permissions : [];
  const canEdit = Boolean(user?.is_super_admin) || userPermissions.includes('translation_settings.edit');
  const canExecute = Boolean(user?.is_super_admin) || userPermissions.includes('translation_settings.execute');
  const { data, setData, put, processing, errors } = useForm({
    provider: settings.provider || 'mymemory',
    api_key: '',
    remove_api_key: false,
  });
  const [testing, setTesting] = React.useState(false);
  const [testResult, setTestResult] = React.useState(null);
  const isGoogle = data.provider === 'google_cloud';
  const hasUnsavedConfig = data.provider !== settings.provider || data.api_key.trim() !== '' || data.remove_api_key;

  const save = event => {
    event.preventDefault();
    setTestResult(null);
    put('/admin/settings/translation', {
      preserveScroll: true,
      onSuccess: () => {
        setData('api_key', '');
        setData('remove_api_key', false);
      },
    });
  };

  const testConnection = async () => {
    setTesting(true);
    setTestResult(null);
    try {
      const response = await fetch('/admin/settings/translation/test', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(payload.message || 'The translation provider test failed.');
      setTestResult({ ok: true, text: `${payload.message} Sample: ${payload.sample}` });
    } catch (error) {
      setTestResult({ ok: false, text: error.message || 'The translation provider test failed.' });
    } finally {
      setTesting(false);
    }
  };

  return <>
    <Head title="Translation API Settings" />
    <AdminLayout title="Translation API">
      <div className="max-w-4xl space-y-5">
        <div>
          <p className="text-xs font-semibold uppercase tracking-wide text-orange-600">System</p>
          <h2 className="mt-1 text-2xl font-bold text-gray-900">Translation API settings</h2>
          <p className="mt-2 text-sm text-gray-600">Choose the service used by Catalog SEO when translating Bengali product names.</p>
        </div>

        {flash?.status && <div role="status" className="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">{flash.status}</div>}

        <form onSubmit={save} className="space-y-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
          <div>
            <label htmlFor="translation-provider" className="mb-1 block text-sm font-semibold text-gray-800">Translation provider</label>
            <select
              id="translation-provider"
              value={data.provider}
              disabled={!canEdit}
              onChange={event => {
                setData('provider', event.target.value);
                if (event.target.value === 'google_cloud') setData('remove_api_key', false);
                setTestResult(null);
              }}
              className="w-full max-w-xl rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-800 disabled:bg-gray-50"
            >
              {Object.entries(providers).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
            </select>
            {errors.provider && <p className="mt-1 text-xs text-red-600">{errors.provider}</p>}
          </div>

          {isGoogle ? <div className="space-y-2 rounded-xl border border-blue-100 bg-blue-50/60 p-4">
            <div>
              <h3 className="text-sm font-semibold text-gray-900">Google Cloud API key</h3>
              <p className="mt-1 text-xs leading-5 text-gray-600">Use a key for the Cloud Translation Basic (v2) API. Google Cloud requires the Translation API enabled and billing configured; usage may incur charges, so set a project quota or budget. Product names are sent to Google when you request a translation.</p>
            </div>
            <label htmlFor="translation-api-key" className="block text-sm font-medium text-gray-700">API key</label>
            <input
              id="translation-api-key"
              type="password"
              autoComplete="new-password"
              value={data.api_key}
              onChange={event => setData('api_key', event.target.value)}
              disabled={!canEdit}
              placeholder={settings.hasApiKey ? 'Saved key is hidden; leave blank to keep it' : 'Paste your Google Cloud API key'}
              className="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm disabled:bg-gray-50"
            />
            {settings.hasApiKey && <p className="text-xs text-green-700">An API key is saved encrypted. It is never shown here.</p>}
            {errors.api_key && <p className="text-xs text-red-600">{errors.api_key}</p>}
            <p className="text-xs text-gray-500">The key is encrypted before storage. Leave the field empty to keep the current key.</p>
          </div> : <div className="rounded-xl border border-amber-100 bg-amber-50/70 p-4">
            <h3 className="text-sm font-semibold text-gray-900">MyMemory</h3>
            <p className="mt-1 text-xs leading-5 text-gray-600">No API key is needed. Anonymous use is limited to 5,000 characters per day. Product names are sent to MyMemory for translation.</p>
            {settings.hasApiKey && <label className="mt-3 flex items-center gap-2 text-xs text-gray-700">
              <input type="checkbox" checked={data.remove_api_key} disabled={!canEdit} onChange={event => setData('remove_api_key', event.target.checked)} className="rounded border-gray-300 text-orange-600 focus:ring-orange-500 disabled:opacity-50" />
              Remove the saved Google Cloud API key when saving
            </label>}
          </div>}

          <div className="flex flex-wrap items-center gap-3">
            {canEdit && <button type="submit" disabled={processing} className="rounded-xl bg-orange-500 px-5 py-3 text-sm font-semibold text-white hover:bg-orange-600 disabled:opacity-60">
              {processing ? 'Saving…' : 'Save translation settings'}
            </button>}
            {canExecute && <button
              type="button"
              onClick={testConnection}
              disabled={testing || hasUnsavedConfig || (isGoogle && !settings.hasApiKey)}
              className="rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
            >
              {testing ? 'Testing…' : 'Test saved provider'}
            </button>}
            {hasUnsavedConfig && <span className="text-xs text-gray-500">Save changes before testing.</span>}
          </div>

          {testResult && <p role="status" className={`rounded-xl p-3 text-sm ${testResult.ok ? 'border border-green-200 bg-green-50 text-green-800' : 'border border-red-200 bg-red-50 text-red-700'}`}>{testResult.text}</p>}
        </form>

        <div className="rounded-xl border border-gray-100 bg-white p-4 text-xs leading-5 text-gray-500">
          API keys are stored encrypted on the server and are not included in page data. Editing and testing are separately controlled by the <span className="font-semibold">Translation API Settings</span> role permissions.
        </div>
      </div>
    </AdminLayout>
  </>;
}
