import { Head, useForm, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function BlockedPhonesIndex({ blockedPhones }) {
  const { data, setData, post, processing, errors, reset } = useForm({
    phone: '',
    reason: '',
  });

  const addBlock = (e) => {
    e.preventDefault();
    post('/admin/blocked-phones', {
      onSuccess: () => reset(),
    });
  };

  const removeBlock = (id) => {
    if (confirm('Remove this phone number block?')) {
      router.delete(`/admin/blocked-phones/${id}`);
    }
  };

  return (
    <AdminLayout>
      <Head title="Blocked Phone Numbers" />
      <div className="max-w-4xl mx-auto space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">📞 Blocked Phone Numbers</h1>
          <p className="text-gray-500 mt-1 text-sm">
            Block specific Bangladeshi phone numbers from placing orders.
          </p>
        </div>

        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
          <h2 className="font-semibold text-gray-800 mb-4">Block a Phone Number</h2>
          <form onSubmit={addBlock} className="flex flex-col sm:flex-row items-start gap-4">
            <div className="flex-1">
              <label className="block text-sm font-medium text-gray-700 mb-1">BD Phone Number</label>
              <input
                type="text"
                value={data.phone}
                onChange={(e) => setData('phone', e.target.value)}
                className="w-full h-11 px-4 rounded-xl border border-gray-200 focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 outline-none transition-all"
                placeholder="e.g. 01712345678"
                required
              />
              {errors.phone && <div className="text-red-500 text-sm mt-1">{errors.phone}</div>}
            </div>
            <div className="flex-1">
              <label className="block text-sm font-medium text-gray-700 mb-1">Reason (Optional)</label>
              <input
                type="text"
                value={data.reason}
                onChange={(e) => setData('reason', e.target.value)}
                className="w-full h-11 px-4 rounded-xl border border-gray-200 focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 outline-none transition-all"
                placeholder="e.g. Repeated fake orders"
              />
              {errors.reason && <div className="text-red-500 text-sm mt-1">{errors.reason}</div>}
            </div>
            <div className="sm:pt-7">
              <button
                type="submit"
                disabled={processing}
                className="h-11 px-6 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white font-medium rounded-xl whitespace-nowrap"
              >
                Block Number
              </button>
            </div>
          </form>
        </div>

        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <table className="w-full text-left text-sm whitespace-nowrap">
            <thead className="bg-gray-50 text-gray-600 font-medium border-b border-gray-100">
              <tr>
                <th className="px-6 py-4">Phone Number</th>
                <th className="px-6 py-4">Reason</th>
                <th className="px-6 py-4">Blocked At</th>
                <th className="px-6 py-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-50 text-gray-700">
              {blockedPhones.data.map((bp) => (
                <tr key={bp.id} className="hover:bg-gray-50/50">
                  <td className="px-6 py-4 font-mono text-blue-600">{bp.phone}</td>
                  <td className="px-6 py-4">{bp.reason || '—'}</td>
                  <td className="px-6 py-4 text-gray-500">
                    {new Date(bp.created_at).toLocaleString()}
                  </td>
                  <td className="px-6 py-4 text-right">
                    <button
                      onClick={() => removeBlock(bp.id)}
                      className="text-red-500 hover:text-red-700 text-xs font-semibold px-3 py-1.5 rounded-lg hover:bg-red-50 transition-colors"
                    >
                      Unblock
                    </button>
                  </td>
                </tr>
              ))}
              {blockedPhones.data.length === 0 && (
                <tr>
                  <td colSpan="4" className="px-6 py-12 text-center text-gray-400">
                    No blocked phone numbers yet.
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
