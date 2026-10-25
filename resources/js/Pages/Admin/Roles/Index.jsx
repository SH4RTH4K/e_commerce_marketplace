import AdminLayout from '@/Layouts/AdminLayout';
import PermissionMatrix from '@/Components/Admin/PermissionMatrix';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const inputClass = 'w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300';

const emptyForm = {
  key: '',
  name: '',
  description: '',
  permissions: [],
};

export default function RolesIndex({ roles = [], availablePermissions = {} }) {
  const { props } = usePage();
  const errors = props.errors || {};
  const [editing, setEditing] = useState(null);
  const { data, setData, post, put, processing, reset, clearErrors } = useForm(emptyForm);

  const beginCreate = () => {
    setEditing(null);
    clearErrors();
    reset();
    setData({ ...emptyForm });
  };

  const beginEdit = (role) => {
    setEditing(role);
    clearErrors();
    setData({
      key: role.key || '',
      name: role.name || '',
      description: role.description || '',
      permissions: Array.isArray(role.permissions) ? role.permissions : [],
    });
  };

  const cancel = () => {
    setEditing(null);
    clearErrors();
    reset();
    setData({ ...emptyForm });
  };

  const submit = (event) => {
    event.preventDefault();
    const options = { preserveScroll: true };
    if (editing) {
      put(`/admin/roles/${editing.id}`, options);
    } else {
      post('/admin/roles', options);
    }
  };



  const deleteRole = (role) => {
    if (role.is_system) return;
    const message = `Delete role "${role.name}"? Users must be reassigned first.`;
    const confirmDelete = () => router.delete(`/admin/roles/${role.id}`, { preserveScroll: true });
    if (typeof window.showConfirm === 'function') window.showConfirm(message, confirmDelete);
    else if (window.confirm(message)) confirmDelete();
  };

  return (
    <>
      <Head title="Roles" />
      <AdminLayout title="Role Management">
        <div className="space-y-6">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <p className="text-sm text-gray-500">Create reusable access profiles and control every admin permission from one place.</p>
            </div>
            <div className="flex items-center gap-3">
              <a href="/admin/admins" className="px-4 py-2.5 bg-white hover:bg-gray-50 border border-gray-200 text-gray-700 text-sm font-semibold rounded-xl shadow-sm">Users</a>
              <button type="button" onClick={beginCreate} className="px-4 py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl shadow-sm">Add Role</button>
            </div>
          </div>

          {errors.role && <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{errors.role}</div>}
          {props.flash?.status && <div className="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{props.flash.status}</div>}

          <div className="grid grid-cols-1 xl:grid-cols-[320px_minmax(0,1fr)] gap-6 items-start">
            <section className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
              <div className="px-5 py-4 border-b border-gray-100">
                <h2 className="font-bold text-gray-900">Available roles</h2>
                <p className="text-xs text-gray-500 mt-1">Built-in roles are protected; custom roles can be deleted after all users are reassigned.</p>
              </div>
              <div className="divide-y divide-gray-100">
                {roles.map((role) => {
                  const permissionCount = Array.isArray(role.permissions) ? role.permissions.length : 'All';
                  return (
                    <div key={role.id} className="px-5 py-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
                      <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                          <h3 className="font-bold text-gray-900">{role.name}</h3>
                          <span className={`text-[10px] uppercase tracking-wider font-bold px-2 py-1 rounded-full ${role.is_system ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700'}`}>
                            {role.is_system ? 'Built-in' : 'Custom'}
                          </span>
                        </div>
                        <p className="font-mono text-xs text-gray-400 mt-1">{role.key}</p>
                        {role.description && <p className="text-sm text-gray-500 mt-1">{role.description}</p>}
                        <p className="text-xs text-gray-500 mt-2">{role.users_count || 0} assigned user(s) · {permissionCount === 'All' ? 'All permissions' : `${permissionCount} permission(s)`}</p>
                      </div>
                      <div className="flex items-center gap-2 shrink-0">
                        <button type="button" onClick={() => beginEdit(role)} className="px-3 py-1.5 text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-semibold">Edit</button>
                        {role.is_system ? (
                          <span className="px-3 py-1.5 text-xs text-gray-400">Protected</span>
                        ) : (
                          <button type="button" onClick={() => deleteRole(role)} className="px-3 py-1.5 text-xs bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-lg font-semibold">Delete</button>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            </section>

            <section className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
              <div className="flex items-center justify-between gap-3 pb-3 border-b border-gray-100">
                <div>
                  <h2 className="font-bold text-gray-900">{editing ? 'Edit role' : 'Add role'}</h2>
                  <p className="text-xs text-gray-500 mt-1">A role controls the permissions inherited by its assigned users.</p>
                </div>
                {editing && <button type="button" onClick={cancel} className="text-xs font-semibold text-gray-500 hover:text-gray-800">Cancel</button>}
              </div>

              <form onSubmit={submit} className="space-y-4 pt-4">
                <Field label="Role key" error={errors.key} hint="lowercase, used internally">
                  <input value={data.key} onChange={(event) => setData('key', event.target.value)} className={inputClass} disabled={Boolean(editing)} required={!editing} placeholder="e.g. warehouse_lead" />
                </Field>
                <Field label="Role name" required error={errors.name}>
                  <input value={data.name} onChange={(event) => setData('name', event.target.value)} className={inputClass} required placeholder="e.g. Warehouse Lead" />
                </Field>
                <Field label="Description" error={errors.description}>
                  <textarea value={data.description} onChange={(event) => setData('description', event.target.value)} className={`${inputClass} min-h-20`} placeholder="What this role is for" />
                </Field>

                {editing?.key === 'admin' ? (
                  <div className="rounded-xl bg-green-50 border border-green-200 p-3 text-xs text-green-800">Super Admin always has full access. Its permission checkboxes are managed by the system.</div>
                ) : (
                  <PermissionMatrix groups={availablePermissions} value={data.permissions} onChange={permissions => setData('permissions', permissions)} />
                )}

                <button type="submit" disabled={processing} className="w-full px-5 py-3 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white text-sm font-semibold rounded-xl">{processing ? 'Saving...' : (editing ? 'Save Role' : 'Create Role')}</button>
              </form>
            </section>
          </div>
        </div>
      </AdminLayout>
    </>
  );
}

function Field({ label, required = false, hint = null, error = null, children }) {
  return <div><label className="block text-sm font-medium text-gray-700 mb-1.5">{label} {required && <span className="text-red-400">*</span>} {hint && <span className="text-gray-400 font-normal">({hint})</span>}</label>{children}{error && <p className="text-red-500 text-xs mt-1">{error}</p>}</div>;
}
