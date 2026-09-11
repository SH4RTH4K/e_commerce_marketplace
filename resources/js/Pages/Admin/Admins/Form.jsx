import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const inputClass = "w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300";

const RoleIcon = ({ roleKey, className = "w-4 h-4" }) => {
  if (roleKey === 'admin') {
    return (
      <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
        <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
      </svg>
    );
  }
  if (roleKey === 'manager') {
    return (
      <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
        <path strokeLinecap="round" strokeLinejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
      </svg>
    );
  }
  if (roleKey === 'support') {
    return (
      <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
        <path strokeLinecap="round" strokeLinejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
      </svg>
    );
  }
  if (roleKey === 'inventory') {
    return (
      <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
        <path strokeLinecap="round" strokeLinejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
      </svg>
    );
  }
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
      <path strokeLinecap="round" strokeLinejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
    </svg>
  );
};

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
    desc: 'Can manage products, orders, customers, marketing, and media. Cannot manage admin staff.',
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
    desc: 'Can view and update orders, courier, customers, and messages. CANNOT delete orders/products or change settings.',
    perms: [
      'orders.view', 'orders.manage',
      'customers.manage', 'reviews.manage', 'messages.manage',
      'products.view',
    ],
  },
  {
    key: 'inventory',
    label: 'Product & Inventory Staff',
    desc: 'Can add, edit, and update stock/products, media, and categories. CANNOT delete products, see orders, or change settings.',
    perms: [
      'products.view', 'products.create',
      'catalog.manage', 'media.manage',
    ],
  },
  {
    key: 'staff',
    label: 'Custom Role',
    desc: 'Customize exact checkboxes and permissions manually.',
    perms: [],
  },
];

export default function AdminForm({ admin, availablePermissions = {} }) {
  const isEdit = !!admin.id;
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

  const handleRoleSelect = (presetKey) => {
    const preset = ROLE_PRESETS.find(p => p.key === presetKey);
    setData(d => ({
      ...d,
      role: presetKey,
      permissions: preset && Array.isArray(preset.perms) ? [...preset.perms] : (presetKey === 'admin' ? [] : d.permissions),
    }));
  };

  const isSuperAdmin = data.role === 'admin';

  const togglePerm = (permKey) => {
    if (isSuperAdmin) return;
    setData(d => {
      const current = new Set(d.permissions || []);
      if (current.has(permKey)) current.delete(permKey);
      else current.add(permKey);
      return { ...d, permissions: Array.from(current) };
    });
  };

  const toggleCategory = (groupKey, items) => {
    if (isSuperAdmin) return;
    const itemKeys = Object.keys(items);
    const allSelected = itemKeys.every(k => (data.permissions || []).includes(k));
    
    setData(d => {
      const current = new Set(d.permissions || []);
      if (allSelected) {
        itemKeys.forEach(k => current.delete(k));
      } else {
        itemKeys.forEach(k => current.add(k));
      }
      return { ...d, permissions: Array.from(current) };
    });
  };

  const submit = (e) => {
    e.preventDefault();
    if (isEdit) put(`/admin/admins/${admin.id}`);
    else post('/admin/admins');
  };

  return (
    <>
      <Head title={isEdit ? `Edit Staff: ${admin.name}` : 'Add Staff Member'} />
      <AdminLayout title={isEdit ? 'Edit Staff Member' : 'Add Staff Member'}>
        <form onSubmit={submit} className="max-w-3xl space-y-6">
          <a href="/admin/admins" className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1.5 w-fit">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            Back to Staff &amp; Admins
          </a>

          {/* ── 1. Account Details ── */}
          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
            <div className="flex items-center gap-2 pb-3 border-b border-gray-100">
              <svg className="w-5 h-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                <path strokeLinecap="round" strokeLinejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              <h3 className="font-bold text-gray-900">Account Credentials</h3>
            </div>
            
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1.5">Full Name <span className="text-red-400">*</span></label>
                <input value={data.name} onChange={e => setData('name', e.target.value)} className={inputClass} placeholder="e.g. Tanvir Hasan" required />
                {errors.name && <p className="text-red-500 text-xs mt-1">{errors.name}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1.5">Username <span className="text-red-400">*</span></label>
                <input value={data.username} onChange={e => setData('username', e.target.value)} className={inputClass} placeholder="e.g. admin" autoComplete="username" required />
                {errors.username && <p className="text-red-500 text-xs mt-1">{errors.username}</p>}
              </div>
              
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1.5">Email Address <span className="text-red-400">*</span></label>
                <input type="email" value={data.email} onChange={e => setData('email', e.target.value)} className={inputClass} placeholder="staff@yourstore.com" required />
                {errors.email && <p className="text-red-500 text-xs mt-1">{errors.email}</p>}
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1.5">
                  {isEdit ? 'New Password' : 'Password'} {isEdit ? <span className="text-gray-400 font-normal">(leave blank to keep current)</span> : <span className="text-red-400">*</span>}
                </label>
                <input type="password" value={data.password} onChange={e => setData('password', e.target.value)} className={inputClass} placeholder="••••••••" required={!isEdit} />
                {errors.password && <p className="text-red-500 text-xs mt-1">{errors.password}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1.5">Confirm Password {isEdit ? '' : <span className="text-red-400">*</span>}</label>
                <input type="password" value={data.password_confirmation} onChange={e => setData('password_confirmation', e.target.value)} className={inputClass} placeholder="••••••••" required={!isEdit && !!data.password} />
              </div>
            </div>
          </div>

          {/* ── 2. Role Selector ── */}
          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
            <div>
              <div className="flex items-center gap-2 pb-1">
                <svg className="w-5 h-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <h3 className="font-bold text-gray-900">Select Staff Role</h3>
              </div>
              <p className="text-xs text-gray-500">Pick a role preset below. Permissions can also be fine-tuned individually.</p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
              {ROLE_PRESETS.map((preset) => {
                const isSelected = data.role === preset.key;
                return (
                  <button
                    key={preset.key}
                    type="button"
                    onClick={() => handleRoleSelect(preset.key)}
                    className={`p-4 rounded-xl border text-left transition-all relative ${
                      isSelected
                        ? 'border-orange-500 bg-orange-50/40 ring-2 ring-orange-400/20'
                        : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50/50'
                    }`}
                  >
                    <div className="flex items-center justify-between mb-1">
                      <div className="flex items-center gap-2 font-bold text-sm text-gray-900">
                        <span className="p-1 rounded-lg bg-orange-100 text-orange-600">
                          <RoleIcon roleKey={preset.key} className="w-4 h-4" />
                        </span>
                        {preset.label}
                      </div>
                      {isSelected && (
                        <span className="w-5 h-5 rounded-full bg-orange-500 text-white flex items-center justify-center text-xs">✓</span>
                      )}
                    </div>
                    <p className="text-xs text-gray-500 leading-relaxed">{preset.desc}</p>
                  </button>
                );
              })}
            </div>
            {errors.role && <p className="text-red-500 text-xs mt-1">{errors.role}</p>}
          </div>

          {/* ── 3. Granular Permissions Checklist ── */}
          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
            <div className="flex items-center justify-between pb-3 border-b border-gray-100">
              <div>
                <div className="flex items-center gap-2">
                  <svg className="w-5 h-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                  </svg>
                  <h3 className="font-bold text-gray-900">Detailed Access &amp; Permissions</h3>
                </div>
                <p className="text-xs text-gray-500 mt-0.5">Control exactly what this user is allowed to view, create, edit, or delete.</p>
              </div>
              {isSuperAdmin && (
                <span className="px-2.5 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700">
                  Full Access Granted
                </span>
              )}
            </div>

            {isSuperAdmin ? (
              <div className="p-4 rounded-xl bg-green-50 border border-green-200 text-green-800 text-xs leading-relaxed flex items-start gap-2.5">
                <svg className="w-4 h-4 text-green-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <div>
                  <strong>Super Admin</strong> has unrestricted access to all dashboard sections, orders, delete operations, and sensitive store settings.
                </div>
              </div>
            ) : (
              <div className="space-y-6">
                {Object.entries(availablePermissions).map(([groupKey, group]) => {
                  const itemKeys = Object.keys(group.items || {});
                  const selectedCount = itemKeys.filter(k => (data.permissions || []).includes(k)).length;
                  const allSelected = selectedCount === itemKeys.length;

                  return (
                    <div key={groupKey} className="rounded-xl border border-gray-100 p-4 bg-gray-50/40 space-y-3">
                      <div className="flex items-center justify-between">
                        <span className="text-xs font-extrabold text-gray-700 uppercase tracking-wider">
                          {group.label}
                        </span>
                        <button
                          type="button"
                          onClick={() => toggleCategory(groupKey, group.items)}
                          className="text-[11px] font-semibold text-orange-600 hover:text-orange-700"
                        >
                          {allSelected ? 'Deselect All' : 'Select All'}
                        </button>
                      </div>

                      <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5 pt-1">
                        {Object.entries(group.items || {}).map(([permKey, permLabel]) => {
                          const isChecked = (data.permissions || []).includes(permKey);
                          const isDeleteAction = permKey.endsWith('.delete');

                          return (
                            <label
                              key={permKey}
                              className={`flex items-start gap-2.5 p-2.5 rounded-lg border cursor-pointer select-none transition-all ${
                                isChecked
                                  ? isDeleteAction 
                                    ? 'border-red-300 bg-red-50/50 text-red-900' 
                                    : 'border-orange-300 bg-orange-50/40 text-orange-950'
                                  : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'
                              }`}
                            >
                              <input
                                type="checkbox"
                                checked={isChecked}
                                onChange={() => togglePerm(permKey)}
                                className="mt-0.5 rounded border-gray-300 text-orange-500 focus:ring-orange-400"
                              />
                              <span className="text-xs font-medium leading-snug">
                                {permLabel}
                                {isDeleteAction && <span className="ml-1 text-[10px] text-red-500 font-bold">(Delete)</span>}
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
          </div>

          {/* ── Submit / Delete Actions ── */}
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
                {processing ? 'Saving…' : (isEdit ? 'Update Staff Account' : 'Create Staff Account')}
              </button>

              <a
                href="/admin/admins"
                className="px-5 py-3 bg-white hover:bg-gray-50 border border-gray-200 text-gray-700 text-sm font-semibold rounded-xl transition-colors"
              >
                Cancel
              </a>
            </div>

            {isEdit && admin.id !== currentUserId && (
              <button
                type="button"
                onClick={() => {
                  window.showConfirm(`Delete staff account "${admin.name}"?`, () => router.delete(`/admin/admins/${admin.id}`));
                }}
                className="px-4 py-2.5 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-sm font-medium rounded-xl transition-colors"
              >
                Delete Account
              </button>
            )}
          </div>
        </form>
      </AdminLayout>
    </>
  );
}
