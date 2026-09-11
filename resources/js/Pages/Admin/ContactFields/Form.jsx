import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';

const inputClass = "w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300";

function Field({ label, error, children, hint }) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1.5">{label}</label>
      {children}
      {hint && <p className="text-xs text-gray-400 mt-1">{hint}</p>}
      {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
    </div>
  );
}

export default function ContactFieldPage({ field, types }) {
  const isEdit = !!field.id;
  const [typeValue, setTypeValue] = useState(field.type || 'text');

  const { data, setData, post, put, processing, errors } = useForm({
    label: field.label || '',
    type: field.type || 'text',
    placeholder: field.placeholder || '',
    help_text: field.help_text || '',
    position: field.position ?? 0,
    accept: field.accept || '.pdf,.jpg,.jpeg,.png,.doc,.docx',
    max_size_kb: field.max_size_kb || 4096,
    options_text: (field.options || []).join('\n'),
    is_required: field.is_required ?? false,
    is_active: field.is_active ?? true,
  });

  const handleTypeChange = (e) => {
    const val = e.target.value;
    setTypeValue(val);
    setData('type', val);
  };

  const submit = (e) => {
    e.preventDefault();
    if (isEdit) put(`/admin/contact-fields/${field.id}`);
    else post('/admin/contact-fields');
  };

  const showFileFields = typeValue === 'file';
  const showOptionsField = typeValue === 'select';

  return (
    <>
      <Head title={isEdit ? `Edit Field: ${field.label}` : 'Add Contact Field'} />
      <AdminLayout title={isEdit ? 'Edit Contact Field' : 'Add Contact Field'}>
        <form onSubmit={submit} className="max-w-2xl space-y-5">
          <a href="/admin/contact-fields" className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 w-fit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            Contact Form Fields
          </a>

          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 space-y-4">
            <h3 className="font-semibold text-gray-900 pb-3 border-b border-gray-50">Field Details</h3>

            <Field label="Label (visible to users)" error={errors.label}>
              <input value={data.label} onChange={e => setData('label', e.target.value)} className={inputClass} required placeholder="e.g. Phone Number" />
            </Field>

            <div className="grid grid-cols-2 gap-4">
              <Field label="Field Type" error={errors.type}>
                <select
                  value={data.type}
                  onChange={handleTypeChange}
                  className={inputClass}
                  disabled={isEdit && field.is_system}
                >
                  {types && Object.entries(types).map(([k, v]) => (
                    <option key={k} value={k}>{v}</option>
                  ))}
                </select>
                {isEdit && field.is_system && <p className="text-xs text-gray-400 mt-1">Type cannot be changed for system fields.</p>}
              </Field>

              <Field label="Position (order on form)" error={errors.position}>
                <input type="number" min="0" value={data.position} onChange={e => setData('position', e.target.value)} className={inputClass} />
              </Field>
            </div>

            <Field label="Placeholder text" error={errors.placeholder}>
              <input value={data.placeholder} onChange={e => setData('placeholder', e.target.value)} className={inputClass} placeholder="e.g. Enter your phone number" />
            </Field>

            <Field label="Help text (shown below the field)" error={errors.help_text}>
              <input value={data.help_text} onChange={e => setData('help_text', e.target.value)} className={inputClass} placeholder="e.g. We'll only use this for delivery updates." />
            </Field>

            {/* Select options */}
            {showOptionsField && (
              <Field label="Options (one per line)" error={errors.options_text} hint="Each line becomes a selectable option in the dropdown.">
                <textarea
                  value={data.options_text}
                  onChange={e => setData('options_text', e.target.value)}
                  rows={5}
                  className={`${inputClass} font-mono text-xs`}
                  placeholder={"Option A\nOption B\nOption C"}
                />
              </Field>
            )}

            {/* File upload config */}
            {showFileFields && (
              <div className="grid grid-cols-2 gap-4">
                <Field label="Accepted file types" error={errors.accept} hint="Comma-separated MIME or extensions">
                  <input value={data.accept} onChange={e => setData('accept', e.target.value)} className={inputClass} placeholder=".pdf,.jpg,.png" />
                </Field>
                <Field label="Max file size (KB)" error={errors.max_size_kb}>
                  <input type="number" min="64" max="10240" value={data.max_size_kb} onChange={e => setData('max_size_kb', e.target.value)} className={inputClass} />
                </Field>
              </div>
            )}

            <div className="flex gap-6 pt-2">
              <label className="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" checked={data.is_required} onChange={e => setData('is_required', e.target.checked)}
                  disabled={isEdit && field.is_system && ['name','email','message'].includes(field.key)}
                  className="h-4 w-4 accent-orange-500 rounded" />
                <span className="text-sm text-gray-700">Required field</span>
              </label>

              <label className="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" checked={data.is_active} onChange={e => setData('is_active', e.target.checked)}
                  disabled={isEdit && field.is_system && ['name','email','message'].includes(field.key)}
                  className="h-4 w-4 accent-orange-500 rounded" />
                <span className="text-sm text-gray-700">Active (show on form)</span>
              </label>
            </div>
          </div>

          <div className="flex items-center gap-3">
            <button type="submit" disabled={processing} className="px-6 py-3 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white font-semibold rounded-xl transition-colors">
              {processing ? 'Saving…' : (isEdit ? 'Update Field' : 'Add Field')}
            </button>
            {isEdit && !field.is_system && (
              <button type="button" onClick={() => { window.showConfirm(`Delete field "${field.label}"?`, () => router.delete(`/admin/contact-fields/${field.id}`)); }}
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