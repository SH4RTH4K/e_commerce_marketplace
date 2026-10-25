import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm } from '@inertiajs/react';
import React from 'react';

const labels = { meta_title: 'Meta Title', meta_description: 'Meta Description', meta_keywords: 'Meta Keywords' };
const translatorUrl = name => 'https://translate.google.com/?sl=bn&tl=en&text=' + encodeURIComponent(name) + '&op=translate';

export default function SeoCompletion({ summary, samples = [], englishNames = [], result }) {
  const { data, setData, post, processing, errors } = useForm({ fill_missing: true, english_names: {} });
  const [translatingId, setTranslatingId] = React.useState(null);
  const [translationMessages, setTranslationMessages] = React.useState({});
  const remaining = summary.categories.incomplete + summary.products.incomplete;
  const bengaliScript = /[\u0980-\u09FF]/;
  const invalidEnglishNames = englishNames.filter(item => {
    const name = (data.english_names[item.id] || '').trim();
    return !name || name.length > 300 || !/[A-Za-z]/.test(name) || bengaliScript.test(name);
  });
  const translateName = async (event, item) => {
    event.preventDefault();
    if (translatingId !== null) return;

    setTranslatingId(item.id);
    setTranslationMessages(current => ({ ...current, [item.id]: null }));
    try {
      const response = await fetch('/admin/settings/seo-completion/translate', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify({ name: item.name }),
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(payload.message || 'Translation failed. Try again or use Google Translate.');

      setData('english_names', { ...data.english_names, [item.id]: payload.translation });
      setTranslationMessages(current => ({ ...current, [item.id]: { type: 'success', text: 'Translation filled in. Review it before completing SEO.' } }));
    } catch (error) {
      setTranslationMessages(current => ({ ...current, [item.id]: { type: 'error', text: error.message || 'Translation failed. Use Google Translate instead.' } }));
    } finally {
      setTranslatingId(null);
    }
  };
  return <><Head title="Catalog SEO" /><AdminLayout title="Catalog SEO">
    <div className="max-w-6xl space-y-5">
      <div><a href="/admin/settings?tab=seo" className="text-sm text-gray-500 hover:text-orange-600">← SEO Settings</a>
        <h2 className="mt-3 text-2xl font-bold text-gray-900">Complete category and product SEO</h2>
        <p className="mt-2 text-sm text-gray-500">Fill blank titles, descriptions and keywords in English using each item’s name and details. Existing SEO text is kept.</p></div>
      <div className="grid gap-4 sm:grid-cols-2">{Object.entries(summary).map(([type, stats]) => <div key={type} className="rounded-2xl border border-gray-100 bg-white p-5">
        <h3 className="font-semibold capitalize text-gray-900">{type}</h3><p className="mt-2 text-2xl font-bold text-orange-600">{stats.incomplete.toLocaleString()} <span className="text-sm font-normal text-gray-500">of {stats.total.toLocaleString()} need SEO</span></p>
        <div className="mt-4 space-y-1 text-sm text-gray-600">{Object.entries(labels).map(([field, label]) => <p key={field} className="flex justify-between"><span>{label} missing</span><span>{stats[field].toLocaleString()}</span></p>)}</div>
      </div>)}</div>
      {remaining > 0 && englishNames.length > 0 && <section className="rounded-2xl border border-amber-200 bg-amber-50/50 p-5">
        <h3 className="font-semibold text-gray-900">English SEO names required</h3>
        <p className="mt-1 text-sm text-gray-600">Translate to English fills the matching field automatically. Review the machine translation before completing SEO; original product names stay unchanged.</p>
        <div className="mt-4 space-y-4">{englishNames.map(item => {
          const value = data.english_names[item.id] || '';
          const isInvalid = invalidEnglishNames.some(invalid => invalid.id === item.id);
          return <label key={item.id} className="block">
            <span className="flex flex-wrap items-center justify-between gap-2">
              <span className="text-sm text-gray-700">{item.name} <span className="text-xs text-gray-400">({item.count} products)</span></span>
              <a href={translatorUrl(item.name)} target="_blank" rel="noopener noreferrer" aria-busy={translatingId === item.id} onClick={event => translateName(event, item)} className="shrink-0 text-sm font-medium text-orange-600 hover:underline">{translatingId === item.id ? 'Translating…' : 'Translate to English'}</a>
            </span>
            <input aria-label={'English SEO name ' + item.id} aria-required="true" aria-invalid={isInvalid} maxLength={300} disabled={translatingId !== null} value={value} onChange={event => setData('english_names', {...data.english_names, [item.id]: event.target.value})} className="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm disabled:bg-gray-50" />
            {translationMessages[item.id] && <span role="status" className={`mt-1 block text-xs ${translationMessages[item.id].type === 'error' ? 'text-red-600' : 'text-green-700'}`}>
              {translationMessages[item.id].text}{translationMessages[item.id].type === 'error' && <> <a href={translatorUrl(item.name)} target="_blank" rel="noopener noreferrer" className="font-medium underline">Open Google Translate instead</a>.</>}
            </span>}
            {isInvalid && value.trim() !== '' && <span className="mt-1 block text-xs text-red-600">Use an English name with at least one English letter and no Bengali characters (up to 300 characters).</span>}
          </label>;
        })}</div>
        <p role="status" className="mt-4 text-sm text-amber-800">{invalidEnglishNames.length > 0 ? invalidEnglishNames.length + ' English SEO name' + (invalidEnglishNames.length === 1 ? '' : 's') + ' still required.' : 'All English SEO names are ready.'}</p>
      </section>}
      {result && <div role="status" className="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900">
        Filled {result.fields.toLocaleString()} SEO fields across {result.categories.toLocaleString()} categories and {result.products.toLocaleString()} products. Existing SEO fields were preserved.
        {result.skipped > 0 && <p className="mt-1">{result.skipped} items changed during the run and were skipped. Review the remaining preview before running again.</p>}
        <p className="mt-1 text-xs">Private metadata snapshot: {result.run_id}</p>
      </div>}
      <form onSubmit={event => { event.preventDefault(); post('/admin/settings/seo-completion', { preserveScroll: true }); }} className="flex flex-wrap items-center gap-3 rounded-xl border border-gray-100 bg-white p-4">
        <button type="submit" disabled={processing || !remaining || invalidEnglishNames.length > 0} className="rounded-xl bg-orange-500 px-5 py-3 text-sm font-semibold text-white hover:bg-orange-600 disabled:opacity-50">{processing ? 'Completing SEO…' : !remaining ? 'All SEO fields complete' : invalidEnglishNames.length > 0 ? 'Complete English SEO names above' : 'Fill all missing SEO fields'}</button>
        <p className="text-xs text-gray-500">Includes published and draft products, and all category levels. Updates only SEO metadata.</p>
        {Object.values(errors).map((error, index) => <p role="alert" key={index} className="w-full text-sm text-red-600">{error}</p>)}
      </form>
      {samples.length > 0 && <div className="space-y-3"><h3 className="font-semibold text-gray-900">Preview of missing fields</h3><p className="text-xs text-gray-500">Up to 12 categories and 12 products are shown. The action fills missing fields across the complete catalog.</p>
        {samples.map(item => <article key={`${item.type}-${item.id}`} className="rounded-xl border border-gray-100 bg-white p-5">
          <div className="flex items-start justify-between gap-4"><div><span className="text-xs font-medium capitalize text-orange-600">{item.type} · #{item.id}</span><h4 className="mt-1 font-semibold text-gray-900">{item.name}</h4></div><a href={item.edit_url} className="shrink-0 text-sm text-orange-600">Edit</a></div>
          <dl className="mt-4 grid gap-3">{Object.entries(labels).map(([field, label]) => <div key={field}><dt className="text-xs font-semibold text-gray-500">{label} {item.proposed[field] ? '· will fill' : '· kept'}</dt><dd className="mt-1 break-words text-sm text-gray-700">{item.proposed[field] || item.current[field]}</dd></div>)}</dl>
        </article>)}
      </div>}
    </div>
  </AdminLayout></>;
}
