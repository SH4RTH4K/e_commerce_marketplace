import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';

export default function CustomerShow({ orders, customer }) {
  return (
    <>
      <Head title={`Customer: ${customer?.customer_name || 'Details'}`} />
      <AdminLayout title="Customer Details">
        <div className="max-w-4xl space-y-5">
          <a href="/admin/customers" className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 w-fit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            Customers
          </a>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
            {/* Customer Info Card */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:col-span-1 flex flex-col items-center text-center space-y-4">
              <div className="h-20 w-20 rounded-full bg-orange-100 flex items-center justify-center text-orange-600 font-bold text-2xl shrink-0">
                {(customer?.customer_name || '?')[0].toUpperCase()}
              </div>
              <div>
                <h2 className="text-lg font-bold text-gray-900">{customer?.customer_name}</h2>
                <p className="text-sm text-gray-500">{customer?.customer_phone}</p>
                {customer?.customer_email && <p className="text-sm text-gray-500 mt-0.5">{customer.customer_email}</p>}
              </div>

              <div className="w-full pt-4 border-t border-gray-50 flex justify-around text-center mt-2">
                <div>
                  <p className="text-xs text-gray-400 uppercase tracking-wide">Orders</p>
                  <p className="font-semibold text-gray-800 text-lg">{(orders || []).length}</p>
                </div>
                <div>
                  <p className="text-xs text-gray-400 uppercase tracking-wide">Total Spent</p>
                  <p className="font-semibold text-gray-800 text-lg">৳{Number((orders || []).reduce((sum, o) => sum + parseFloat(o.total || 0), 0)).toLocaleString()}</p>
                </div>
              </div>
            </div>

            {/* Order History */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 md:col-span-2 overflow-hidden">
              <div className="p-5 border-b border-gray-50 flex items-center justify-between">
                <h3 className="font-semibold text-gray-900">Order History</h3>
              </div>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="bg-gray-50/50">
                      {['Order #', 'Date', 'Amount', 'Status', ''].map(h => (
                        <th key={h} className={`px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide text-left ${h === '' ? 'text-right' : ''}`}>{h}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-50">
                    {(orders || []).length === 0 ? (
                      <tr><td colSpan="5" className="px-5 py-8 text-center text-gray-400">No orders found.</td></tr>
                    ) : (orders || []).map(order => (
                      <tr key={order.id} className="hover:bg-gray-50/50 transition-colors">
                        <td className="px-5 py-3.5">
                          <a href={`/admin/orders/${order.id}`} className="font-semibold text-gray-800 hover:text-orange-500">#{order.order_number}</a>
                        </td>
                        <td className="px-5 py-3.5 text-gray-500">{new Date(order.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })}</td>
                        <td className="px-5 py-3.5 font-medium text-gray-800">৳{Number(order.total).toLocaleString()}</td>
                        <td className="px-5 py-3.5">
                          <span className={`px-2 py-0.5 text-xs font-medium rounded-full capitalize ${
                            order.status === 'delivered' ? 'bg-green-100 text-green-700' :
                            order.status === 'cancelled' ? 'bg-red-100 text-red-600' :
                            'bg-blue-100 text-blue-700'
                          }`}>{order.status}</span>
                        </td>
                        <td className="px-5 py-3.5 text-right">
                          <a href={`/admin/orders/${order.id}`} className="px-2.5 py-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg font-medium transition-colors">View</a>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </AdminLayout>
    </>
  );
}