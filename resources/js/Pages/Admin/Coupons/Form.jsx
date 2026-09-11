import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm, router } from '@inertiajs/react';

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

export default function CouponForm({ coupon }) {
  const isEdit = !!coupon.id;
  const { data, setData, post, put, processing, errors } = useForm({
    code: coupon.code || '',
    description: coupon.description || '',
    type: coupon.type || 'percentage',
    value: coupon.value || '',
    min_order_amount: coupon.min_order_amount || '',
    max_discount: coupon.max_discount || '',
    max_uses: coupon.max_uses || '',
    starts_at: coupon.starts_at ? coupon.starts_at.slice(0, 16) : '',
    expires_at: coupon.expires_at ? coupon.expires_at.slice(0, 16) : '',
    is_active: coupon.is_active ?? true,
  });

  const submit = (e) => {
    e.preventDefault();
    const payload = { ...data, is_active: data.is_active ? '1' : '0' };
    if (isEdit) {
      put(`/admin/coupons/${coupon.id}`, { data: payload });
    } else {
      post('/admin/coupons', { data: payload });
    }
  };

  return (
    <>
      <Head title={isEdit ? `Edit: ${coupon.code}` : 'Add Coupon'} />
      <AdminLayout title={isEdit ? 'Edit Coupon' : 'Add Coupon'}>
        <form onSubmit={submit} className="max-w-3xl space-y-5">
          <a href="/admin/coupons" className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 w-fit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            Coupons
          </a>

          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 space-y-4">
            <h3 className="font-semibold text-gray-900 pb-3 border-b border-gray-50">Coupon Details</h3>
            <div className="grid grid-cols-2 gap-4">
              <Field label="Code" required error={errors.code}>
                <input value={data.code} onChange={e => setData('code', e.target.value.toUpperCase())} className={inputClass} required placeholder="e.g. SAVE20" />
              </Field>
              <Field label="Type" required error={errors.type}>
                <select value={data.type} onChange={e => setData('type', e.target.value)} className={inputClass}>
                  <option value="percentage">Percentage (%)</option>
                  <option value="fixed">Fixed Amount (৳)</option>
                </select>
              </Field>
            </div>
            <Field label="Description" error={errors.description}>
              <input value={data.description} onChange={e => setData('description', e.target.value)} className={inputClass} placeholder="Optional description" />
            </Field>
            <div className="grid grid-cols-3 gap-4">
              <Field label={`Value ${data.type === 'percentage' ? '(%)' : '(৳)'}`} required error={errors.value}>
                <input type="number" min="0.01" step="0.01" value={data.value} onChange={e => setData('value', e.target.value)} className={inputClass} required />
              </Field>
              <Field label="Min Order Amount" error={errors.min_order_amount}>
                <input type="number" min="0" step="0.01" value={data.min_order_amount} onChange={e => setData('min_order_amount', e.target.value)} className={inputClass} placeholder="Optional" />
              </Field>
              <Field label="Max Discount" error={errors.max_discount}>
                <input type="number" min="0" step="0.01" value={data.max_discount} onChange={e => setData('max_discount', e.target.value)} className={inputClass} placeholder="Optional" />
              </Field>
            </div>
            <div className="grid grid-cols-3 gap-4">
              <Field label="Max Uses" error={errors.max_uses}>
                <input type="number" min="1" value={data.max_uses} onChange={e => setData('max_uses', e.target.value)} className={inputClass} placeholder="Unlimited" />
              </Field>
              <Field label="Starts At" error={errors.starts_at}>
                <input type="datetime-local" value={data.starts_at} onChange={e => setData('starts_at', e.target.value)} className={inputClass} />
              </Field>
              <Field label="Expires At" error={errors.expires_at}>
                <input type="datetime-local" value={data.expires_at} onChange={e => setData('expires_at', e.target.value)} className={inputClass} />
              </Field>
            </div>
            <label className="flex items-center gap-3 cursor-pointer">
              <input type="checkbox" checked={data.is_active} onChange={e => setData('is_active', e.target.checked)} className="h-4 w-4 accent-orange-500 rounded" />
              <span className="text-sm text-gray-700">Active</span>
            </label>
          </div>

          <div className="flex items-center gap-3">
            <button type="submit" disabled={processing} className="px-6 py-3 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white font-semibold rounded-xl transition-colors">
              {processing ? 'Saving…' : (isEdit ? 'Update Coupon' : 'Create Coupon')}
            </button>
            {isEdit && (
              <button type="button" onClick={() => { window.showConfirm(`Delete coupon "${coupon.code}"?`, () => router.delete(`/admin/coupons/${coupon.id}`)); }}
                className="px-4 py-2.5 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-sm font-medium rounded-xl transition-colors">Delete</button>
            )}
          </div>
        </form>
      </AdminLayout>
    </>
  );
}