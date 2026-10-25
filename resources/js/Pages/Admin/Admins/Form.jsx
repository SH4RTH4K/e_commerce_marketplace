import AdminLayout from '@/Layouts/AdminLayout';
import PermissionMatrix from '@/Components/Admin/PermissionMatrix';
import { Head, router, useForm, usePage } from '@inertiajs/react';

const inputClass = 'w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300';

const roleIconPath = {
  admin: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
  manager: 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
  support: 'M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z',
  inventory: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
  staff: 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4',
};

const RoleIcon = ({ roleKey, className = 'w-4 h-4' }) => (
  <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
    <path strokeLinecap="round" strokeLinejoin="round" d={roleIconPath[roleKey] || roleIconPath.staff} />
  </svg>
);

export default function AdminForm({ admin, roles = [], availablePermissions = {} }) {
  const isEdit = Boolean(admin.id);
  const { auth } = usePage().props;
  const currentUserId = auth?.user?.id;
  const initialRole = admin.role || roles.find((role) => role.key === 'manager')?.key || roles[0]?.key || 'manager';
  const initialPerms = Array.isArray(admin.permissions) ? admin.permissions : [];

  const { data, setData, post, put, processing, errors } = useForm({
    name: admin.name || '',
    username: admin.username || '',
    email: admin.email || '',
    password: '',
    password_confirmation: '',
    role: initialRole,
    permissions: initialRole === 'admin' ? [] : initialPerms,
  });

  const isSuperAdmin = data.role === 'admin';

  const handleRoleSelect = (roleKey) => {
    const role = roles.find((item) => item.key === roleKey);

    setData((current) => ({
      ...current,
      role: roleKey,
      permissions: roleKey === 'admin'
        ? []
        : (Array.isArray(role?.permissions) ? [...role.permissions] : current.permissions),
    }));
  };



  const submit = (event) => {
    event.preventDefault();
    if (isEdit) put(`/admin/admins/${admin.id}`);
    else post('/admin/admins');
  };

  const title = isEdit ? 'Edit User Account' : 'Add User Account';

  return (
    <>
      <Head title={isEdit ? `Edit User: ${admin.name}` : 'Add User'} />
      <AdminLayout title={title}>
        <form onSubmit={submit} className="max-w-6xl space-y-6">
          <a href="/admin/admins" className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1.5 w-fit">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M19 12H5M12 5l-7 7 7 7" />
            </svg>
            Back to Users
          </a>

          <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_420px] gap-6 items-start">
            <section className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
              <div className="flex items-center gap-2 pb-3 border-b border-gray-100">
                <svg className="w-5 h-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <h3 className="font-bold text-gray-900">User Account</h3>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Field label="Full Name" required error={errors.name}>
                  <input value={data.name} onChange={(event) => setData('name', event.target.value)} className={inputClass} placeholder="Name" required />
                </Field>

                <Field label="Username" required error={errors.username}>
                  <input value={data.username} onChange={(event) => setData('username', event.target.value)} className={inputClass} placeholder="e.g. admin" autoComplete="username" required />
                </Field>

                <div className="sm:col-span-2">
                  <Field label="Email Address" required error={errors.email}>
                    <input type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} className={inputClass} placeholder="user@yourstore.com" required />
                  </Field>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <Field
                  label={isEdit ? 'New Password' : 'Password'}
                  hint={isEdit ? 'leave blank to keep current' : null}
                  required={!isEdit}
                  error={errors.password}
                >
                  <input type="password" value={data.password} onChange={(event) => setData('password', event.target.value)} className={inputClass} placeholder="Password" required={!isEdit} />
                </Field>

                <Field label="Confirm Password" required={!isEdit}>
                  <input
                    type="password"
                    value={data.password_confirmation}
                    onChange={(event) => setData('password_confirmation', event.target.value)}
                    className={inputClass}
                    placeholder="Confirm password"
                    required={!isEdit && Boolean(data.password)}
                  />
                </Field>
              </div>
            </section>

            <div className="contents">
              <section className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
                <div>
                  <div className="flex items-center gap-2 pb-1">
                    <svg className="w-5 h-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                      <path strokeLinecap="round" strokeLinejoin="round" d={roleIconPath.admin} />
                    </svg>
                    <h3 className="font-bold text-gray-900">Role Assignment</h3>
                  </div>
                  <p className="text-xs text-gray-500">Choose what access level this user has in the admin panel.</p>
                </div>

                <div className="grid grid-cols-1 gap-3 pt-2">
                  {roles.map((role) => {
                    const selected = data.role === role.key;

                    return (
                      <button
                        key={role.key}
                        type="button"
                        disabled={isEdit && !auth?.user?.is_super_admin}
                        onClick={() => handleRoleSelect(role.key)}
                        className={`p-4 rounded-xl border text-left transition-all relative ${
                          selected
                            ? 'border-orange-500 bg-orange-50/40 ring-2 ring-orange-400/20'
                            : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50/50'
                        }`}
                      >
                        <div className="flex items-center justify-between mb-1">
                          <div className="flex items-center gap-2 font-bold text-sm text-gray-900">
                            <span className="p-1 rounded-lg bg-orange-100 text-orange-600">
                              <RoleIcon roleKey={role.key} />
                            </span>
                            {role.name}
                          </div>
                          {selected && <span className="text-xs font-bold text-orange-600">Selected</span>}
                        </div>
                        <p className="text-xs text-gray-500 leading-relaxed">{role.description || (role.key === 'admin' ? 'Full, unrestricted access to all admin features.' : 'Choose exact permissions for this role.')}</p>
                      </button>
                    );
                  })}
                </div>

                {errors.role && <p className="text-red-500 text-xs mt-1">{errors.role}</p>}
              </section>

              <section className="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
                <div className="flex items-center justify-between pb-3 border-b border-gray-100">
                  <div>
                    <div className="flex items-center gap-2">
                      <svg className="w-5 h-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                      </svg>
                      <h3 className="font-bold text-gray-900">Role Permissions</h3>
                    </div>
                    <p className="text-xs text-gray-500 mt-0.5">Only Super Admin can change permission assignments.</p>
                  </div>

                  {isSuperAdmin && (
                    <span className="px-2.5 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700">
                      Full Access
                    </span>
                  )}
                </div>

                {isSuperAdmin ? (
                  <div className="rounded-xl border border-green-200 bg-green-50 p-4 text-xs text-green-800">Super Admin has full access to every function.</div>
                ) : (
                  <PermissionMatrix groups={availablePermissions} value={data.permissions} onChange={permissions => setData('permissions', permissions)} disabled={!auth?.user?.is_super_admin} />
                )}
              </section>
            </div>
          </div>

          <div className="flex items-center justify-between pt-2">
            <div className="flex items-center gap-3">
              <button
                type="submit"
                disabled={processing}
                className="px-6 py-3 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white font-semibold rounded-xl transition-all shadow-sm flex items-center gap-2"
              >
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                {processing ? 'Saving...' : (isEdit ? 'Update User Account' : 'Create User Account')}
              </button>

              <a href="/admin/admins" className="px-5 py-3 bg-white hover:bg-gray-50 border border-gray-200 text-gray-700 text-sm font-semibold rounded-xl transition-colors">
                Cancel
              </a>
            </div>

            {isEdit && admin.id !== currentUserId && (
              <button
                type="button"
                onClick={() => {
                  window.showConfirm(`Delete user account "${admin.name}"?`, () => router.delete(`/admin/admins/${admin.id}`));
                }}
                className="px-4 py-2.5 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-sm font-medium rounded-xl transition-colors"
              >
                Delete User
              </button>
            )}
          </div>
        </form>
      </AdminLayout>
    </>
  );
}

function Field({ label, required = false, hint = null, error = null, children }) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1.5">
        {label} {required && <span className="text-red-400">*</span>}
        {hint && <span className="text-gray-400 font-normal"> ({hint})</span>}
      </label>
      {children}
      {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
    </div>
  );
}
