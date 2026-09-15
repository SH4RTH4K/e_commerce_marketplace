import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import { fileToBase64, imageUrl } from '@/lib/utils';

const inputClass = "w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300";
const positionOptions = [
  ['top-left', 'Top - Left'], ['top-center', 'Top - Center'], ['top-right', 'Top - Right'],
  ['center-left', 'Center - Left'], ['center-center', 'Center - Center'], ['center-right', 'Center - Right'],
  ['bottom-left', 'Bottom - Left'], ['bottom-center', 'Bottom - Center'], ['bottom-right', 'Bottom - Right'],
];

function normalizePosition(value, fallback) {
  return { left: 'center-left', center: 'center-center', right: 'center-right' }[value] || value || fallback;
}

function imageFocusCss(position) {
  const [vertical, horizontal] = normalizePosition(position, 'center-center').split('-');
  return `${horizontal} ${vertical}`;
}

function textPositionStyle(position) {
  const [vertical, horizontal] = normalizePosition(position, 'center-left').split('-');
  return {
    alignItems: { left: 'flex-start', center: 'center', right: 'flex-end' }[horizontal],
    justifyContent: { top: 'flex-start', center: 'center', bottom: 'flex-end' }[vertical],
    textAlign: horizontal,
  };
}

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
    text_position: normalizePosition(banner.text_position, 'center-left'),
    image_position: normalizePosition(banner.image_position, 'center-center'),
    image_orientation: banner.image_orientation || 'landscape',
    position: banner.position || 0,
    is_active: banner.is_active ?? true,
    image_file: null,
    image_url: banner.image?.startsWith('http') ? banner.image : '',
  });
  const previewImage = data.image_url || (banner.image ? imageUrl(banner.image, banner.title) : '');

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
            {['hero', 'middle'].includes(data.placement) && (
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 rounded-xl border border-indigo-100 bg-indigo-50/50 p-4">
                <Field label="Text & Button Position" error={errors.text_position}>
                  <select value={data.text_position} onChange={e => setData('text_position', e.target.value)} className={inputClass}>
                    {positionOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                  </select>
                </Field>
                <Field label="Image Focus Area" error={errors.image_position}>
                  <select value={data.image_position} onChange={e => setData('image_position', e.target.value)} className={inputClass}>
                    {positionOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                  </select>
                </Field>
                <Field label="Image Orientation" error={errors.image_orientation}>
                  <select value={data.image_orientation} onChange={e => setData('image_orientation', e.target.value)} className={inputClass}>
                    <option value="landscape">Landscape / Wide — fill slider</option>
                    <option value="portrait">Portrait / Tall — show full image</option>
                    <option value="square">Square — show full image</option>
                  </select>
                </Field>
                <p className="sm:col-span-3 text-xs text-indigo-700">Landscape fills the banner and uses Image Focus Area for cropping. Portrait and Square show the complete image without cutting it off. These settings apply to both Hero Slider slides and Middle Banners.</p>
                <div className="sm:col-span-3 overflow-hidden rounded-xl border border-indigo-200 bg-slate-100">
                  <div className="flex items-center justify-between border-b border-indigo-100 bg-white px-3 py-2">
                    <p className="text-xs font-bold uppercase tracking-wide text-indigo-800">Live placement preview</p>
                    <p className="text-[11px] text-gray-500">Focus: {positionOptions.find(([value]) => value === data.image_position)?.[1]}</p>
                  </div>
                  <div
                    className="relative aspect-[16/7] bg-slate-200 bg-cover"
                    style={previewImage ? { backgroundImage: `url("${previewImage}")`, backgroundPosition: imageFocusCss(data.image_position), backgroundSize: data.image_orientation === 'landscape' ? 'cover' : 'contain', backgroundRepeat: 'no-repeat' } : undefined}
                  >
                    <div className="absolute inset-0 bg-black/10" />
                    <div className="absolute inset-0 flex flex-col p-4 sm:p-7" style={textPositionStyle(data.text_position)}>
                      <div className="max-w-[70%] rounded-lg bg-slate-900/85 px-3 py-2.5 text-white shadow-lg sm:px-5 sm:py-4">
                        <p className="text-base font-bold sm:text-xl">{data.title || 'Banner title'}</p>
                        {data.subtitle && <p className="mt-1 text-xs text-white/85 sm:text-sm">{data.subtitle}</p>}
                        <span className="mt-2 inline-block rounded-full bg-indigo-500 px-3 py-1 text-[10px] font-bold uppercase">{data.button_text || 'Shop now'}</span>
                      </div>
                    </div>
                    {!previewImage && <div className="absolute inset-0 grid place-items-center text-sm font-medium text-gray-500">Add an image to preview its focus area.</div>}
                  </div>
                </div>
              </div>
            )}
          </div>

          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 space-y-4">
            <h3 className="font-semibold text-gray-900 pb-3 border-b border-gray-50">Image</h3>
            {isEdit && banner.image && (
              <img src={imageUrl(banner.image, banner.title)} alt="" className="h-24 rounded-xl object-cover border border-gray-100" />
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
