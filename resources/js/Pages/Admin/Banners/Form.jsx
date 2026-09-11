import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import { fileToBase64 } from '@/lib/utils';

const inputClass = "w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300";

function Field({ label, error, children, required }) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1.5">{label}{required && <span className="text-red-400 ml-0.5">*</span>}</label>
      {children}
      {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
    </div>
  );
}

export default function BannerForm({ banner, placements, styles, products = [] }) {
  const isEdit = !!banner.id;
  const [submitting, setSubmitting] = useState(false);
  const { data, setData, processing, errors } = useForm({
    title: banner.title || '',
    subtitle: banner.subtitle || '',
    badge: banner.badge || '',
    product_id: banner.product_id || '',
    link_url: banner.link_url || '',
    button_text: banner.button_text || '',
    placement: banner.placement || 'hero',
    style: banner.style || 'brand',
    position: banner.position || 0,
    is_active: banner.is_active ?? true,
    image_file: null,
    image_url: banner.image?.startsWith('http') ? banner.image : '',
  });

  const submit = async (e) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      const formData = new FormData();
      Object.entries(data).forEach(([k, v]) => {
        if (k === 'image_file') return;
        if (typeof v === 'boolean') formData.append(k, v ? '1' : '0');
        else formData.append(k, v ?? '');
      });
      if (data.image_file) {
        const b64 = await fileToBase64(data.image_file);
        formData.append('image_file_b64', b64);
        formData.append('image_file_name', data.image_file.name || 'banner.jpg');
      }
      const options = {
        forceFormData: true,
        onFinish: () => setSubmitting(false),
        onError: () => setSubmitting(false),
      };
      if (isEdit) {
        formData.append('_method', 'PUT');
        router.post(`/admin/banners/${banner.id}`, formData, options);
      } else {
        router.post('/admin/banners', formData, options);
      }
    } catch (err) {
      setSubmitting(false);
      console.error(err);
    }
  };

  return (
    <>
      <Head title={isEdit ? 'Edit Banner' : 'Add Banner'} />
      <AdminLayout title={isEdit ? 'Edit Banner' : 'Add Banner'}>
        <form onSubmit={submit} className="max-w-3xl space-y-5">
          <a href="/admin/banners" className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 w-fit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            Banners
          </a>

          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 space-y-4">
            <h3 className="font-semibold text-gray-900 pb-3 border-b border-gray-50">Banner Details</h3>
            <Field label="Title" error={errors.title}><input value={data.title} onChange={e => setData('title', e.target.value)} className={inputClass} /></Field>
            <Field label="Subtitle" error={errors.subtitle}><textarea value={data.subtitle} onChange={e => setData('subtitle', e.target.value)} rows={2} className={inputClass} /></Field>
            <div className="grid grid-cols-2 gap-4">
              <Field label="Badge" error={errors.badge}><input value={data.badge} onChange={e => setData('badge', e.target.value)} className={inputClass} placeholder="e.g. NEW" /></Field>
              <Field label="Button Text" error={errors.button_text}><input value={data.button_text} onChange={e => setData('button_text', e.target.value)} className={inputClass} /></Field>
            </div>
            <Field label="Related Product" error={errors.product_id}>
              <select value={data.product_id} onChange={e => setData('product_id', e.target.value)} className={inputClass}>
                <option value="">No product link</option>
                {products.map(product => (
                  <option key={product.id} value={product.id}>
                    {product.name}{product.is_published ? '' : ' (unpublished)'}
                  </option>
                ))}
              </select>
              <p className="mt-1 text-xs text-gray-400">When selected, the banner button opens this product page. The custom URL is used only when no product is selected.</p>
            </Field>
            <Field label="Fallback Link URL" error={errors.link_url}><input value={data.link_url} onChange={e => setData('link_url', e.target.value)} className={inputClass} placeholder="/shop or https://..." /></Field>
            <div className="grid grid-cols-3 gap-4">
              <Field label="Placement" required error={errors.placement}>
                <select value={data.placement} onChange={e => setData('placement', e.target.value)} className={inputClass}>
                  {placements && Object.entries(placements).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                </select>
              </Field>
              <Field label="Style" required error={errors.style}>
                <select value={data.style} onChange={e => setData('style', e.target.value)} className={inputClass}>
                  {styles && Object.entries(styles).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                </select>
              </Field>
              <Field label="Position" error={errors.position}>
                <input type="number" min="0" value={data.position} onChange={e => setData('position', e.target.value)} className={inputClass} />
              </Field>
            </div>
          </div>

          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 space-y-4">
            <h3 className="font-semibold text-gray-900 pb-3 border-b border-gray-50">Image</h3>
            {isEdit && banner.image && (
              <img src={banner.image.startsWith('http') ? banner.image : `/${banner.image}`} alt="" className="h-24 rounded-xl object-cover border border-gray-100" />
            )}
            <Field label="Upload Image" error={errors.image_file}>
              <input type="file" accept="image/*" onChange={e => setData('image_file', e.target.files[0])} className={inputClass} />
            </Field>
            <Field label="Or Image URL" error={errors.image_url}>
              <input value={data.image_url} onChange={e => setData('image_url', e.target.value)} className={inputClass} placeholder="https://..." />
            </Field>
          </div>

          <div className="flex items-center gap-4">
            <label className="flex items-center gap-3 cursor-pointer">
              <input type="checkbox" checked={data.is_active} onChange={e => setData('is_active', e.target.checked)} className="h-4 w-4 accent-orange-500 rounded" />
              <span className="text-sm text-gray-700">Active (visible on store)</span>
            </label>
          </div>

          <div className="flex items-center gap-3">
            <button type="submit" disabled={processing || submitting} className="px-6 py-3 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white font-semibold rounded-xl transition-colors">
              {processing || submitting ? 'Saving…' : (isEdit ? 'Update Banner' : 'Create Banner')}
            </button>
            {isEdit && (
              <button type="button" onClick={() => { window.showConfirm('Delete this banner?', () => router.delete(`/admin/banners/${banner.id}`)); }}
                className="px-4 py-2.5 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-sm font-medium rounded-xl transition-colors">Delete</button>
            )}
          </div>
        </form>
      </AdminLayout>
    </>
  );
}
