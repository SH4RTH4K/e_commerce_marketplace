import { Head, useForm, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function BlockedIpsIndex({ blockedIps }) {
  const { data, setData, post, processing, errors, reset } = useForm({
    ip_address: '',
    reason: '',
  });

  const addBlock = (e) => {
    e.preventDefault();
    post('/admin/blocked-ips', {
      onSuccess: () => reset(),
    });
  };

  const removeBlock = (id) => {
    if (confirm('Are you sure you want to unblock this IP?')) {
      router.delete(`/admin/blocked-ips/${id}`);
    }
  };

  return (
    <AdminLayout>
      <Head title="Blocked IPs" />
      <div className="max-w-4xl mx-auto space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Blocked IPs</h1>
          <p className="text-gray-500 mt-1">Manage IP addresses blocked from placing orders.</p>
        </div>

        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
          <form onSubmit={addBlock} className="flex flex-col sm:flex-row items-start gap-4">
            <div className="flex-1">
              <label className="block text-sm font-medium text-gray-700 mb-1">IP Address</label>
              <input
                type="text"
                value={data.ip_address}
                onChange={(e) => setData('ip_address', e.target.value)}
                className="w-full h-11 px-4 rounded-xl border border-gray-200 focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 outline-none transition-all"
                placeholder="e.g. 192.168.1.1"
                required
              />
              {errors.ip_address && <div className="text-red-500 text-sm mt-1">{errors.ip_address}</div>}
            </div>
            <div className="flex-1">
              <label className="block text-sm font-medium text-gray-700 mb-1">Reason (Optional)</label>
              <input
                type="text"
                value={data.reason}
                onChange={(e) => setData('reason', e.target.value)}
                className="w-full h-11 px-4 rounded-xl border border-gray-200 focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 outline-none transition-all"
                placeholder="e.g. Fake orders"
              />
              {errors.reason && <div className="text-red-500 text-sm mt-1">{errors.reason}</div>}
            </div>
            <div className="sm:pt-7">
              <button
                type="submit"
                disabled={processing}
                className="h-11 px-6 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white font-medium rounded-xl whitespace-nowrap"
              >
                Block IP
              </button>
            </div>
          </form>
        </div>

        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <table className="w-full text-left text-sm whitespace-nowrap">
            <thead className="bg-gray-50 text-gray-600 font-medium border-b border-gray-100">
              <tr>
                <th className="px-6 py-4">IP Address</th>
                <th className="px-6 py-4">Reason</th>
                <th className="px-6 py-4">Blocked At</th>
                <th className="px-6 py-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-50 text-gray-700">
              {blockedIps.data.map((block) => (
                <tr key={block.id} className="hover:bg-gray-50/50">
                  <td className="px-6 py-4 font-mono text-red-600">{block.ip_address}</td>
                  <td className="px-6 py-4">{block.reason || '—'}</td>
                  <td className="px-6 py-4 text-gray-500">
                    {new Date(block.created_at).toLocaleString()}
                  </td>
                  <td className="px-6 py-4 text-right">
                    <button
                      onClick={() => removeBlock(block.id)}
                      className="text-red-500 hover:text-red-700 text-xs font-semibold px-3 py-1.5 rounded-lg hover:bg-red-50 transition-colors"
                    >
                      Unblock
                    </button>
                  </td>
                </tr>
              ))}
              {blockedIps.data.length === 0 && (
                <tr>
                  <td colSpan="4" className="px-6 py-12 text-center text-gray-500">
                    No blocked IPs found.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </AdminLayout>
  );
}
