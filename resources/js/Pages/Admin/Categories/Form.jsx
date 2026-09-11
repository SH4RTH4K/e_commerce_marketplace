import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import { fileToBase64 } from '@/lib/utils';
import CategoryIcon, { CATEGORY_ICON_OPTIONS } from '@/Components/Storefront/CategoryIcon';

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

export default function CategoryForm({ category, parents = [] }) {
  const isEdit = !!category.id;
  const [submitting, setSubmitting] = useState(false);
  const { data, setData, post, put, processing, errors } = useForm({
    name: category.name || '',
    slug: category.slug || '',
    parent_id: category.parent_id || '',
    icon: category.icon || '',
    description: category.description || '',
    position: category.position || 0,
    is_active: category.is_active ?? true,
    show_in_menu: category.show_in_menu ?? true,
    meta_title: category.meta_title || '',
    meta_description: category.meta_description || '',
    meta_keywords: category.meta_keywords || '',
    image_file: null,
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
        formData.append('image_file_name', data.image_file.name || 'category.jpg');
      }
      const options = {
        forceFormData: true,
        onFinish: () => setSubmitting(false),
        onError: () => setSubmitting(false),
      };
      if (isEdit) {
        formData.append('_method', 'PUT');
        router.post(`/admin/categories/${category.id}`, formData, options);
      } else {
        router.post('/admin/categories', formData, options);
      }
    } catch (err) {
      setSubmitting(false);
      console.error(err);
    }
  };

  return (
    <>
      <Head title={isEdit ? `Edit: ${category.name}` : 'Add Category'} />
      <AdminLayout title={isEdit ? 'Edit Category' : 'Add Category'}>
        <form onSubmit={submit} className="max-w-3xl space-y-5">
          <a href="/admin/categories" className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 w-fit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            Categories
          </a>

          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 space-y-4">
            <h3 className="font-semibold text-gray-900 pb-3 border-b border-gray-50">Category Details</h3>
            <Field label="Name" required error={errors.name}>
              <input value={data.name} onChange={e => setData('name', e.target.value)} className={inputClass} required />
            </Field>
            <div className="grid grid-cols-2 gap-4">
              <Field label="Slug (URL)" error={errors.slug}>
                <input value={data.slug} onChange={e => setData('slug', e.target.value)} className={inputClass} placeholder="auto-generated from name" />
              </Field>
              <Field label="Parent Category" error={errors.parent_id}>
                <select value={data.parent_id} onChange={e => setData('parent_id', e.target.value)} className={inputClass}>
                  <option value="">None (Top-Level)</option>
                  {parents.map(p => (
                    <option key={p.id} value={p.id}>{p.name}</option>
                  ))}
                </select>
              </Field>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <Field label="Category Icon" error={errors.icon}>
                <select value={data.icon} onChange={e => setData('icon', e.target.value)} className={inputClass}>
                  <option value="">Auto-detect from category name</option>
                  {data.icon && !CATEGORY_ICON_OPTIONS.some(option => option.value === data.icon) && (
                    <option value={data.icon}>Keep current icon</option>
                  )}
                  {CATEGORY_ICON_OPTIONS.map(option => (
                    <option key={option.value} value={option.value}>{option.label}</option>
                  ))}
                </select>
                <div className="mt-2 flex items-center gap-2 text-xs text-gray-500">
                  <span className="grid h-9 w-9 place-items-center rounded-xl bg-orange-50 text-orange-500">
                    <CategoryIcon icon={data.icon} name={data.name} className="h-6 w-6" />
                  </span>
                  <span>Premium icon preview</span>
                </div>
                {/*
                <input value={data.icon} onChange={e => setData('icon', e.target.value)} className={inputClass} placeholder="e.g. 🎽 or 📱" />
                */}
              </Field>
              <Field label="Position" error={errors.position}>
                <input type="number" min="0" value={data.position} onChange={e => setData('position', e.target.value)} className={inputClass} />
              </Field>
            </div>
            <Field label="Description" error={errors.description}>
              <textarea value={data.description} onChange={e => setData('description', e.target.value)} rows={3} className={inputClass} />
            </Field>
            <div className="grid grid-cols-2 gap-4">
              <Field label="Image" error={errors.image_file}>
                <input type="file" accept="image/*" onChange={e => setData('image_file', e.target.files[0])} className={inputClass} />
              </Field>
            </div>
            {isEdit && category.image && (
              <div className="flex items-center gap-3">
                <img src={category.image.startsWith('http') ? category.image : `/${category.image}`} alt="" className="h-16 w-16 rounded-xl object-cover border border-gray-100" />
                <span className="text-xs text-gray-400">Current image</span>
              </div>
            )}
            <div className="space-y-2">
              <label className="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" checked={data.is_active} onChange={e => setData('is_active', e.target.checked)} className="h-4 w-4 accent-orange-500 rounded" />
                <span className="text-sm text-gray-700">Active (visible on store)</span>
              </label>
              <label className="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" checked={data.show_in_menu} onChange={e => setData('show_in_menu', e.target.checked)} className="h-4 w-4 accent-orange-500 rounded" />
                <span className="text-sm text-gray-700">Show in Navbar / Menus</span>
              </label>
            </div>
          </div>

          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 space-y-4">
            <h3 className="font-semibold text-gray-900 pb-3 border-b border-gray-50">SEO</h3>
            <Field label="Meta Title" error={errors.meta_title}><input value={data.meta_title} onChange={e => setData('meta_title', e.target.value)} className={inputClass} /></Field>
            <Field label="Meta Description" error={errors.meta_description}><textarea value={data.meta_description} onChange={e => setData('meta_description', e.target.value)} rows={2} className={inputClass} /></Field>
            <Field label="Meta Keywords" error={errors.meta_keywords}><input value={data.meta_keywords} onChange={e => setData('meta_keywords', e.target.value)} className={inputClass} /></Field>
          </div>

          <div className="flex items-center gap-3">
            <button type="submit" disabled={processing || submitting} className="px-6 py-3 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white font-semibold rounded-xl transition-colors">
              {processing || submitting ? 'Saving…' : (isEdit ? 'Update Category' : 'Create Category')}
            </button>
            {isEdit && (
              <button type="button" onClick={() => { window.showConfirm(`Delete "${category.name}"?`, () => router.delete(`/admin/categories/${category.id}`)); }}
                className="px-4 py-2.5 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-sm font-medium rounded-xl transition-colors">
                Delete
              </button>
            )}
          </div>
        </form>
      </AdminLayout>
    </>
  );
}
