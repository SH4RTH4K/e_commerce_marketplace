import AdminLayout from '@/Layouts/AdminLayout';
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

const ROLE_PRESETS = [
  {
    key: 'admin',
    label: 'Super Admin',
    desc: 'Full, unrestricted access to all features, settings, orders, and staff management.',
    perms: 'ALL',
  },
  {
    key: 'manager',
    label: 'Store Manager',
    desc: 'Products, orders, customers, marketing, media, and store settings.',
    perms: [
      'products.view', 'products.create', 'products.delete',
      'orders.view', 'orders.manage', 'orders.delete',
      'customers.manage', 'reviews.manage',
      'catalog.manage', 'marketing.manage', 'messages.manage',
      'media.manage', 'fraud_guard.manage', 'settings.manage',
    ],
  },
  {
    key: 'support',
    label: 'Order & Support Agent',
    desc: 'Orders, courier updates, customers, reviews, messages, and product lookup.',
    perms: [
      'orders.view', 'orders.manage',
      'customers.manage', 'reviews.manage', 'messages.manage', 'products.view',
    ],
  },
  {
    key: 'inventory',
    label: 'Product & Inventory Staff',
    desc: 'Products, stock, categories, and media without order or settings access.',
    perms: [
      'products.view', 'products.create',
      'catalog.manage', 'media.manage',
    ],
  },
  {
    key: 'staff',
    label: 'Custom Role',
    desc: 'Start with no preset and choose exact permissions manually.',
    perms: [],
  },
];

export default function AdminForm({ admin, availablePermissions = {} }) {
  const isEdit = Boolean(admin.id);
  const { auth } = usePage().props;
  const currentUserId = auth?.user?.id;
  const initialRole = admin.role || 'manager';
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

  const handleRoleSelect = (presetKey) => {
    const preset = ROLE_PRESETS.find((role) => role.key === presetKey);

    setData((current) => ({
      ...current,
      role: presetKey,
      permissions: preset && Array.isArray(preset.perms)
        ? [...preset.perms]
        : (presetKey === 'admin' ? [] : current.permissions),
    }));
  };

  const togglePerm = (permKey) => {
    if (isSuperAdmin) return;

    setData((current) => {
      const selected = new Set(current.permissions || []);
      if (selected.has(permKey)) selected.delete(permKey);
      else selected.add(permKey);

      return { ...current, permissions: Array.from(selected) };
    });
  };

  const toggleCategory = (items) => {
    if (isSuperAdmin) return;

    const itemKeys = Object.keys(items);
    const allSelected = itemKeys.every((key) => (data.permissions || []).includes(key));

    setData((current) => {
      const selected = new Set(current.permissions || []);
      itemKeys.forEach((key) => {
        if (allSelected) selected.delete(key);
        else selected.add(key);
      });

      return { ...current, permissions: Array.from(selected) };
    });
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
            Back to Users &amp; Roles
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
                  <input value={data.name} onChange={(event) => setData('name', event.target.value)} className={inputClass} placeholder="e.g. Tanvir Hasan" required />
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

            <div className="space-y-6">
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
                  {ROLE_PRESETS.map((preset) => {
                    const selected = data.role === preset.key;

                    return (
                      <button
                        key={preset.key}
                        type="button"
                        onClick={() => handleRoleSelect(preset.key)}
                        className={`p-4 rounded-xl border text-left transition-all relative ${
                          selected
                            ? 'border-orange-500 bg-orange-50/40 ring-2 ring-orange-400/20'
                            : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50/50'
                        }`}
                      >
                        <div className="flex items-center justify-between mb-1">
                          <div className="flex items-center gap-2 font-bold text-sm text-gray-900">
                            <span className="p-1 rounded-lg bg-orange-100 text-orange-600">
                              <RoleIcon roleKey={preset.key} />
                            </span>
                            {preset.label}
                          </div>
                          {selected && <span className="text-xs font-bold text-orange-600">Selected</span>}
                        </div>
                        <p className="text-xs text-gray-500 leading-relaxed">{preset.desc}</p>
                      </button>
                    );
                  })}
                </div>

                {errors.role && <p className="text-red-500 text-xs mt-1">{errors.role}</p>}
              </section>

              <section className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
                <div className="flex items-center justify-between pb-3 border-b border-gray-100">
                  <div>
                    <div className="flex items-center gap-2">
                      <svg className="w-5 h-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                      </svg>
                      <h3 className="font-bold text-gray-900">Role Permissions</h3>
                    </div>
                    <p className="text-xs text-gray-500 mt-0.5">Fine tune the role without changing user account details.</p>
                  </div>

                  {isSuperAdmin && (
                    <span className="px-2.5 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700">
                      Full Access
                    </span>
                  )}
                </div>

                {isSuperAdmin ? (
                  <div className="p-4 rounded-xl bg-green-50 border border-green-200 text-green-800 text-xs leading-relaxed flex items-start gap-2.5">
                    <RoleIcon roleKey="admin" className="w-4 h-4 text-green-600 shrink-0 mt-0.5" />
                    <div><strong>Super Admin</strong> can access every dashboard section and sensitive store setting.</div>
                  </div>
                ) : (
                  <div className="space-y-5">
                    {Object.entries(availablePermissions).map(([groupKey, group]) => {
                      const items = group.items || {};
                      const itemKeys = Object.keys(items);
                      const selectedCount = itemKeys.filter((key) => (data.permissions || []).includes(key)).length;
                      const allSelected = itemKeys.length > 0 && selectedCount === itemKeys.length;

                      return (
                        <div key={groupKey} className="rounded-xl border border-gray-100 p-4 bg-gray-50/40 space-y-3">
                          <div className="flex items-center justify-between gap-3">
                            <span className="text-xs font-extrabold text-gray-700 uppercase tracking-wider">{group.label}</span>
                            <button
                              type="button"
                              onClick={() => toggleCategory(items)}
                              className="text-[11px] font-semibold text-orange-600 hover:text-orange-700 whitespace-nowrap"
                            >
                              {allSelected ? 'Deselect All' : 'Select All'}
                            </button>
                          </div>

                          <div className="grid grid-cols-1 gap-2.5 pt-1">
                            {Object.entries(items).map(([permKey, permLabel]) => {
                              const checked = (data.permissions || []).includes(permKey);
                              const deleteAction = permKey.endsWith('.delete');

                              return (
                                <label
                                  key={permKey}
                                  className={`flex items-start gap-2.5 p-2.5 rounded-lg border cursor-pointer select-none transition-all ${
                                    checked
                                      ? deleteAction
                                        ? 'border-red-300 bg-red-50/50 text-red-900'
                                        : 'border-orange-300 bg-orange-50/40 text-orange-950'
                                      : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'
                                  }`}
                                >
                                  <input
                                    type="checkbox"
                                    checked={checked}
                                    onChange={() => togglePerm(permKey)}
                                    className="mt-0.5 rounded border-gray-300 text-orange-500 focus:ring-orange-400"
                                  />
                                  <span className="text-xs font-medium leading-snug">
                                    {permLabel}
                                    {deleteAction && <span className="ml-1 text-[10px] text-red-500 font-bold">(Delete)</span>}
                                  </span>
                                </label>
                              );
                            })}
                          </div>
                        </div>
                      );
                    })}
                  </div>
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
