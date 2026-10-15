import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm } from '@inertiajs/react';

const labels = { meta_title: 'Meta Title', meta_description: 'Meta Description', meta_keywords: 'Meta Keywords' };

export default function SeoCompletion({ summary, samples = [], result }) {
  const { post, processing, errors } = useForm({ fill_missing: true });
  const remaining = summary.categories.incomplete + summary.products.incomplete;
  return <><Head title="Catalog SEO" /><AdminLayout title="Catalog SEO">
    <div className="max-w-6xl space-y-5">
      <div><a href="/admin/settings?tab=seo" className="text-sm text-gray-500 hover:text-orange-600">← SEO Settings</a>
        <h2 className="mt-3 text-2xl font-bold text-gray-900">Complete category and product SEO</h2>
        <p className="mt-2 text-sm text-gray-500">Fill blank titles, descriptions and keywords in English using each item’s name and details. Existing SEO text is kept.</p></div>
      <div className="grid gap-4 sm:grid-cols-2">{Object.entries(summary).map(([type, stats]) => <div key={type} className="rounded-2xl border border-gray-100 bg-white p-5">
        <h3 className="font-semibold capitalize text-gray-900">{type}</h3><p className="mt-2 text-2xl font-bold text-orange-600">{stats.incomplete.toLocaleString()} <span className="text-sm font-normal text-gray-500">of {stats.total.toLocaleString()} need SEO</span></p>
        <div className="mt-4 space-y-1 text-sm text-gray-600">{Object.entries(labels).map(([field, label]) => <p key={field} className="flex justify-between"><span>{label} missing</span><span>{stats[field].toLocaleString()}</span></p>)}</div>
      </div>)}</div>
      {result && <div role="status" className="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900">
        Filled {result.fields.toLocaleString()} SEO fields across {result.categories.toLocaleString()} categories and {result.products.toLocaleString()} products. Existing SEO fields were preserved.
        {result.skipped > 0 && <p className="mt-1">{result.skipped} items changed during the run and were skipped. Review the remaining preview before running again.</p>}
        <p className="mt-1 text-xs">Private metadata snapshot: {result.run_id}</p>
      </div>}
      <form onSubmit={event => { event.preventDefault(); post('/admin/settings/seo-completion', { preserveScroll: true }); }} className="flex flex-wrap items-center gap-3 rounded-xl border border-gray-100 bg-white p-4">
        <button type="submit" disabled={processing || !remaining} className="rounded-xl bg-orange-500 px-5 py-3 text-sm font-semibold text-white hover:bg-orange-600 disabled:opacity-50">{processing ? 'Completing SEO…' : remaining ? 'Fill all missing SEO fields' : 'All SEO fields complete'}</button>
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
