import { Head, useForm, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function BlockedDevicesIndex({ blockedDevices }) {
  const { data, setData, post, processing, errors, reset } = useForm({
    user_agent: '',
    reason: '',
  });

  const addBlock = (e) => {
    e.preventDefault();
    post('/admin/blocked-devices', {
      onSuccess: () => reset(),
    });
  };

  const removeBlock = (id) => {
    if (confirm('Remove this device block?')) {
      router.delete(`/admin/blocked-devices/${id}`);
    }
  };

  return (
    <AdminLayout>
      <Head title="Blocked Devices" />
      <div className="max-w-4xl mx-auto space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">💻 Blocked Devices</h1>
          <p className="text-gray-500 mt-1 text-sm">
            Block specific browsers/devices from placing orders using their User-Agent fingerprint.
          </p>
        </div>

        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
          <h2 className="font-semibold text-gray-800 mb-4">Block a Device</h2>
          <form onSubmit={addBlock} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                User-Agent String
              </label>
              <textarea
                rows={3}
                value={data.user_agent}
                onChange={(e) => setData('user_agent', e.target.value)}
                className="w-full px-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 outline-none transition-all text-sm font-mono"
                placeholder="Paste the User-Agent string here..."
                required
              />
              {errors.user_agent && (
                <div className="text-red-500 text-sm mt-1">{errors.user_agent}</div>
              )}
            </div>
            <div className="flex items-end gap-4">
              <div className="flex-1">
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Reason (Optional)
                </label>
                <input
                  type="text"
                  value={data.reason}
                  onChange={(e) => setData('reason', e.target.value)}
                  className="w-full h-10 px-4 rounded-xl border border-gray-200 focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 outline-none transition-all text-sm"
                  placeholder="e.g. Fake order bot"
                />
              </div>
              <button
                type="submit"
                disabled={processing}
                className="h-10 px-6 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white font-medium rounded-xl text-sm whitespace-nowrap"
              >
                Block Device
              </button>
            </div>
          </form>
        </div>

        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <table className="w-full text-left text-sm">
            <thead className="bg-gray-50 text-gray-600 font-medium border-b border-gray-100">
              <tr>
                <th className="px-6 py-4">Device Hash</th>
                <th className="px-6 py-4">User Agent</th>
                <th className="px-6 py-4">Reason</th>
                <th className="px-6 py-4">Blocked At</th>
                <th className="px-6 py-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-50 text-gray-700">
              {blockedDevices.data.map((device) => (
                <tr key={device.id} className="hover:bg-gray-50/50">
                  <td className="px-6 py-4 font-mono text-xs text-purple-600 max-w-[120px] truncate">
                    {device.device_hash?.substring(0, 12)}…
                  </td>
                  <td className="px-6 py-4 text-xs text-gray-500 max-w-[200px] truncate font-mono">
                    {device.user_agent || '—'}
                  </td>
                  <td className="px-6 py-4">{device.reason || '—'}</td>
                  <td className="px-6 py-4 text-gray-500">
                    {new Date(device.created_at).toLocaleString()}
                  </td>
                  <td className="px-6 py-4 text-right">
                    <button
                      onClick={() => removeBlock(device.id)}
                      className="text-red-500 hover:text-red-700 text-xs font-semibold px-3 py-1.5 rounded-lg hover:bg-red-50 transition-colors"
                    >
                      Unblock
                    </button>
                  </td>
                </tr>
              ))}
              {blockedDevices.data.length === 0 && (
                <tr>
                  <td colSpan="5" className="px-6 py-12 text-center text-gray-400">
                    No blocked devices yet.
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
