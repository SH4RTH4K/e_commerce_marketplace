import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm } from '@inertiajs/react';

const inputClass = "w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300";

export default function AdminPassword() {
  const { data, setData, put, processing, errors } = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  const submit = (e) => {
    e.preventDefault();
    put('/admin/admins/password', {
      onSuccess: () => setData({ current_password: '', password: '', password_confirmation: '' }),
    });
  };

  return (
    <>
      <Head title="Change Password" />
      <AdminLayout title="Change Password">
        <form onSubmit={submit} className="max-w-xl space-y-5">
          <a href="/admin/admins" className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 w-fit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            Administrators
          </a>

          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 space-y-4">
            <h3 className="font-semibold text-gray-900 pb-3 border-b border-gray-50">Security Settings</h3>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">Current Password <span className="text-red-400">*</span></label>
              <input type="password" value={data.current_password} onChange={e => setData('current_password', e.target.value)} className={inputClass} required />
              {errors.current_password && <p className="text-red-500 text-xs mt-1">{errors.current_password}</p>}
            </div>
            
            <div className="pt-2">
              <label className="block text-sm font-medium text-gray-700 mb-1.5">New Password <span className="text-red-400">*</span></label>
              <input type="password" value={data.password} onChange={e => setData('password', e.target.value)} className={inputClass} required />
              {errors.password && <p className="text-red-500 text-xs mt-1">{errors.password}</p>}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">Confirm New Password <span className="text-red-400">*</span></label>
              <input type="password" value={data.password_confirmation} onChange={e => setData('password_confirmation', e.target.value)} className={inputClass} required />
            </div>
          </div>

          <div>
            <button type="submit" disabled={processing} className="px-6 py-3 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white font-semibold rounded-xl transition-colors">
              {processing ? 'Updating…' : 'Update Password'}
            </button>
          </div>
        </form>
      </AdminLayout>
    </>
  );
}