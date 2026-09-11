import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router, usePage } from '@inertiajs/react';

const RoleIcon = ({ roleKey, className = "w-3.5 h-3.5" }) => {
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

const ROLE_META = {
  admin:     { label: 'Super Admin', color: 'bg-purple-100 text-purple-700 border-purple-200' },
  manager:   { label: 'Store Manager', color: 'bg-blue-100 text-blue-700 border-blue-200' },
  support:   { label: 'Support & Orders', color: 'bg-emerald-100 text-emerald-700 border-emerald-200' },
  inventory: { label: 'Product & Stock', color: 'bg-amber-100 text-amber-700 border-amber-200' },
  staff:     { label: 'Custom Staff', color: 'bg-gray-100 text-gray-700 border-gray-200' },
};

export default function AdminsIndex({ admins = [] }) {
  const { auth } = usePage().props;
  const currentUserId = auth?.user?.id;

  const handleDelete = (admin) => {
    if (admin.id === currentUserId) return alert("You cannot delete your own account.");
    window.showConfirm(`Delete staff account "${admin.name}"?`, () => {
      router.delete(`/admin/admins/${admin.id}`);
    });
  };

  return (
    <>
      <Head title="Staff & Admins" />
      <AdminLayout title="Staff & Roles Management">
        <div className="space-y-5">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <p className="text-sm text-gray-500">Manage store managers, support agents, product uploaders, and their permissions.</p>
            </div>
            <div className="flex items-center gap-3">
              <a
                href="/admin/admins/password"
                className="px-4 py-2.5 bg-white hover:bg-gray-50 border border-gray-200 text-gray-700 text-sm font-semibold rounded-xl transition-colors shadow-sm"
              >
                Change My Password
              </a>
              <a
                href="/admin/admins/create"
                className="px-4 py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl transition-colors flex items-center gap-2 shadow-sm"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8zM16 11h6M19 8v6"/></svg>
                Add Staff Member
              </a>
            </div>
          </div>

          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="bg-gray-50/70 border-b border-gray-100">
                    <th className="px-5 py-3.5 text-xs font-bold text-gray-500 uppercase tracking-wider text-left">Staff Member</th>
                    <th className="px-5 py-3.5 text-xs font-bold text-gray-500 uppercase tracking-wider text-left">Username</th>
                    <th className="px-5 py-3.5 text-xs font-bold text-gray-500 uppercase tracking-wider text-left">Email Address</th>
                    <th className="px-5 py-3.5 text-xs font-bold text-gray-500 uppercase tracking-wider text-left">Assigned Role</th>
                    <th className="px-5 py-3.5 text-xs font-bold text-gray-500 uppercase tracking-wider text-left">Access Scope</th>
                    <th className="px-5 py-3.5 text-xs font-bold text-gray-500 uppercase tracking-wider text-left">Created Date</th>
                    <th className="px-5 py-3.5 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {admins.map((admin) => {
                    const roleInfo = ROLE_META[admin.role] || ROLE_META.staff;
                    const isSuper = admin.role === 'admin';
                    const permCount = Array.isArray(admin.permissions) ? admin.permissions.length : 0;

                    return (
                      <tr key={admin.id} className="hover:bg-gray-50/50 transition-colors">
                        {/* Member */}
                        <td className="px-5 py-4">
                          <div className="flex items-center gap-3">
                            <img
                              src={`https://ui-avatars.com/api/?name=${encodeURIComponent(admin.name)}&background=f15a24&color=fff&size=64`}
                              alt=""
                              className="h-10 w-10 rounded-full shrink-0 border border-gray-100"
                            />
                            <div>
                              <p className="font-bold text-gray-900 flex items-center gap-1.5">
                                {admin.name}
                                {admin.id === currentUserId && (
                                  <span className="text-[10px] bg-orange-100 text-orange-700 font-semibold px-1.5 py-0.5 rounded">You</span>
                                )}
                              </p>
                            </div>
                          </div>
                        </td>

                        {/* Username */}
                        <td className="px-5 py-4 text-gray-900 font-mono text-xs font-semibold">{admin.username || '—'}</td>

                        {/* Email */}
                        <td className="px-5 py-4 text-gray-600 font-mono text-xs">{admin.email}</td>

                        {/* Role */}
                        <td className="px-5 py-4">
                          <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold rounded-lg border ${roleInfo.color}`}>
                            <RoleIcon roleKey={admin.role} className="w-3.5 h-3.5" />
                            {roleInfo.label}
                          </span>
                        </td>

                        {/* Scope */}
                        <td className="px-5 py-4">
                          {isSuper ? (
                            <span className="inline-flex items-center gap-1 text-xs font-semibold text-green-700 bg-green-50 px-2 py-0.5 rounded">
                              <svg className="w-3 h-3 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                              </svg>
                              All Permissions
                            </span>
                          ) : permCount > 0 ? (
                            <span className="text-xs font-medium text-gray-700 bg-gray-100 px-2 py-0.5 rounded">
                              {permCount} Active Permissions
                            </span>
                          ) : (
                            <span className="text-xs font-medium text-gray-400 bg-gray-50 px-2 py-0.5 rounded">
                              Restricted
                            </span>
                          )}
                        </td>

                        {/* Date */}
                        <td className="px-5 py-4 text-gray-400 text-xs">
                          {new Date(admin.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })}
                        </td>

                        {/* Actions */}
                        <td className="px-5 py-4 text-right">
                          <div className="flex items-center justify-end gap-1.5">
                            <a
                              href={`/admin/admins/${admin.id}/edit`}
                              className="px-3 py-1.5 text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-semibold transition-colors"
                            >
                              Edit &amp; Permissions
                            </a>
                            {admin.id !== currentUserId && (
                              <button
                                onClick={() => handleDelete(admin)}
                                className="px-3 py-1.5 text-xs bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-lg font-semibold transition-colors"
                              >
                                Delete
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </AdminLayout>
    </>
  );
}
