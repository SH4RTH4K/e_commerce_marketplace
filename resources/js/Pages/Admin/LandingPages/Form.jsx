import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm, router } from '@inertiajs/react';
import SectionEditor from './SectionEditor';

const inputClass = "w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300";

function Field({ label, error, children, required }) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1.5">
        {label}{required && <span className="text-red-400 ml-0.5">*</span>}
      </label>
      {children}
      {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
    </div>
  );
}

function DesignChooser({ designs }) {
  const handleChoose = (designKey) => {
    router.get('/admin/landing-pages/create', { design: designKey });
  };
  const handleToggle = (designKey) => {
    router.patch(`/admin/landing-pages/designs/${designKey}/toggle`, {}, { preserveScroll: true });
  };

  return (
    <>
      <Head title="Choose Landing Page Design" />
      <AdminLayout title="Create Landing Page">
        <div className="space-y-5">
          <div className="flex items-center justify-between">
            <div>
              <h2 className="text-lg font-semibold text-gray-900">Choose a Design</h2>
              <p className="text-sm text-gray-500 mt-0.5">Select a design template to start your landing page.</p>
            </div>
            <a href="/admin/landing-pages" className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
              Back
            </a>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            {designs && Object.entries(designs).map(([key, design]) => (
              <div key={key} className={`bg-white rounded-2xl border-2 transition-all overflow-hidden ${design.visible ? 'border-gray-100 hover:border-orange-200 shadow-sm' : 'border-dashed border-gray-200 opacity-60'}`}>
                {design.preview_url && (
                  <img src={design.preview_url} alt={design.label} className="w-full h-40 object-cover object-top" />
                )}
                {!design.preview_url && (
                  <div className="w-full h-40 bg-gradient-to-br from-orange-50 to-orange-100 flex items-center justify-center">
                    <span className="text-4xl">🎨</span>
                  </div>
                )}
                <div className="p-4 space-y-3">
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <h3 className="font-semibold text-gray-900">{design.label}</h3>
                      {design.description && <p className="text-xs text-gray-500 mt-0.5">{design.description}</p>}
                    </div>
                    <span className={`shrink-0 px-2 py-0.5 text-xs font-medium rounded-full ${design.visible ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                      {design.visible ? 'Visible' : 'Hidden'}
                    </span>
                  </div>
                  <div className="flex gap-2">
                    {design.visible && (
                      <button onClick={() => handleChoose(key)}
                        className="flex-1 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl transition-colors">
                        Use this design
                      </button>
                    )}
                    <button onClick={() => handleToggle(key)}
                      className="px-3 py-2 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-xl font-medium transition-colors">
                      {design.visible ? 'Hide' : 'Show'}
                    </button>
                    {design.preview_url && (
                      <a href={`/admin/landing-pages/designs/${key}/preview`} target="_blank" rel="noopener"
                        className="px-3 py-2 text-xs bg-blue-50 hover:bg-blue-100 text-blue-600 rounded-xl font-medium transition-colors">
                        Preview
                      </a>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </AdminLayout>
    </>
  );
}

export default function LandingPageForm({ page, choosingDesign, designs, products, sections, c }) {
  if (choosingDesign) {
    return <DesignChooser designs={designs} />;
  }

  const p = page || {};
  const isEdit = !!p.id;

  const { data, setData, post, put, processing, errors } = useForm({
    title:            p.title || '',
    slug:             p.slug  || '',
    product_id:       p.product_id || '',
    is_active:        p.is_active ?? true,
    meta_title:       c?.meta_title || '',
    meta_description: c?.meta_description || '',
  });

  // Page Setup submit — this is its OWN <form>, separate from SectionEditor forms.
  const submitSetup = (e) => {
    e.preventDefault();
    if (isEdit) {
      put(`/admin/landing-pages/${p.id}`, {
        preserveScroll: true,
        onSuccess: () => {
          router.reload({ only: ['page', 'c', 'sections'] });
        },
      });
    } else {
      post('/admin/landing-pages');
    }
  };

  return (
    <>
      <Head title={isEdit ? `Edit: ${p.title}` : 'Create Landing Page'} />
      <AdminLayout title={isEdit ? 'Edit Landing Page' : 'Create Landing Page'}>
        {/* ── Top nav bar — plain div, NOT a form ── */}
        <div className="max-w-4xl space-y-5">
          <div className="flex items-center justify-between">
            <a href="/admin/landing-pages" className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 w-fit">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
              Landing Pages
            </a>
            {isEdit && (
              <a href={`/lp/${p.slug}`} target="_blank" rel="noopener" className="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-600 text-sm font-medium rounded-lg transition-colors">
                Preview Live Page
              </a>
            )}
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-5 items-start">
            {/* ── Left column: Section Editors (each has its own <form> inside) ── */}
            <div className="md:col-span-2 space-y-5">
              {isEdit && sections && Object.entries(sections)
                .filter(([k]) => k !== 'setup')
                .map(([key, meta]) => (
                  <SectionEditor
                    key={key}
                    pageId={p.id}
                    sectionKey={key}
                    meta={meta}
                    initialContent={c}
                  />
                ))
              }

              {!isEdit && (
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                  <p className="text-gray-500">Please create the page first to edit design sections.</p>
                </div>
              )}
            </div>

            {/* ── Right column: Page Setup — its OWN isolated <form> ── */}
            <div className="space-y-5 sticky top-24">
              <form onSubmit={submitSetup}>
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 space-y-4">
                  <h3 className="font-semibold text-gray-900 pb-3 border-b border-gray-50">Page Setup</h3>

                  <Field label="Title" required error={errors.title}>
                    <input
                      value={data.title}
                      onChange={e => setData('title', e.target.value)}
                      className={inputClass}
                      required
                    />
                  </Field>

                  <Field label="URL Slug" error={errors.slug}>
                    <input
                      value={data.slug}
                      onChange={e => setData('slug', e.target.value)}
                      className={inputClass}
                      placeholder="Leave blank to auto-generate"
                    />
                  </Field>

                  <Field label="Linked Product" required error={errors.product_id}>
                    <select
                      value={data.product_id}
                      onChange={e => setData('product_id', e.target.value)}
                      className={inputClass}
                      required
                    >
                      <option value="">Select a product...</option>
                      {products?.map(prod => (
                        <option key={prod.id} value={prod.id}>
                          {prod.name} (৳{prod.sale_price || prod.regular_price})
                        </option>
                      ))}
                    </select>
                  </Field>

                  <label className="flex items-center gap-3 cursor-pointer pt-2">
                    <input
                      type="checkbox"
                      checked={data.is_active}
                      onChange={e => setData('is_active', e.target.checked)}
                      className="h-4 w-4 accent-orange-500 rounded"
                    />
                    <span className="text-sm font-medium text-gray-700">Published (Visible)</span>
                  </label>

                  <div className="pt-2 space-y-4">
                    <Field label="Meta Title" error={errors.meta_title}>
                      <input
                        value={data.meta_title}
                        onChange={e => setData('meta_title', e.target.value)}
                        className={inputClass}
                        placeholder="Defaults to page title"
                      />
                    </Field>

                    <Field label="Meta Description" error={errors.meta_description}>
                      <textarea
                        value={data.meta_description}
                        onChange={e => setData('meta_description', e.target.value)}
                        rows={3}
                        className={inputClass}
                      />
                    </Field>
                  </div>
                </div>

                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mt-5">
                  <button
                    type="submit"
                    disabled={processing}
                    className="w-full py-3 bg-slate-800 hover:bg-slate-900 disabled:opacity-60 text-white font-semibold rounded-xl transition-colors cursor-pointer"
                  >
                    {processing ? 'Saving...' : 'Save Page Setup (Title & URL)'}
                  </button>
                  {isEdit && (
                    <button
                      type="button"
                      onClick={() => {
                        if (confirm('Are you sure you want to delete this landing page?')) {
                          router.delete(`/admin/landing-pages/${p.id}`);
                        }
                      }}
                      className="w-full mt-3 py-2.5 bg-red-50 hover:bg-red-100 text-red-600 text-sm font-medium rounded-xl transition-colors cursor-pointer"
                    >
                      Delete Page
                    </button>
                  )}
                </div>
              </form>
            </div>
          </div>
        </div>
      </AdminLayout>
    </>
  );
}