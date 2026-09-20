import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';

export default function CustomersIndex({ customers, term }) {
  const [search, setSearch] = useState(term || '');
  const { data = [], links = [] } = customers || {};

  const handleSearch = () => router.get('/admin/customers', { q: search }, { preserveState: true });
  const toggleCustomer = customer => router.patch(`/admin/customers/${customer.account_id}/toggle`, {}, { preserveScroll: true });
  const deleteCustomer = customer => {
    window.showConfirm(`Delete customer account "${customer.customer_name}"? This cannot be undone.`, () => {
      router.delete(`/admin/customers/${customer.account_id}`, { preserveScroll: true });
    });
  };

  return (
    <>
      <Head title="Customers" />
      <AdminLayout title="Customers">
        <div className="space-y-5">
          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <form onSubmit={e => { e.preventDefault(); handleSearch(); }} className="p-4 border-b border-gray-50 flex flex-wrap gap-3">
              <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search by name or phone…"
                className="flex-1 min-w-[200px] border border-gray-200 rounded-xl px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300" />
              <button type="submit" className="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-xl">Search</button>
            </form>

            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="bg-gray-50/50">
                    {['Customer', 'Phone', 'Email', 'Orders', 'Total Spent', 'Last Order', 'Status', 'Actions'].map(h => (
                      <th key={h} className={`px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide text-left ${h === 'Actions' ? 'text-right' : ''}`}>{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {data.length === 0 ? (
                    <tr><td colSpan="8" className="px-5 py-12 text-center text-gray-400">No customers found.</td></tr>
                  ) : data.map((customer, i) => (
                    <tr key={i} className="hover:bg-gray-50/50 transition-colors">
                      <td className="px-5 py-3.5">
                        <div className="flex items-center gap-3">
                          <div className="h-8 w-8 rounded-full bg-orange-100 flex items-center justify-center text-orange-600 font-bold text-xs shrink-0">
                            {(customer.customer_name || '?')[0].toUpperCase()}
                          </div>
                          <span className="font-semibold text-gray-800">{customer.customer_name}</span>
                        </div>
                      </td>
                      <td className="px-5 py-3.5 text-gray-600">{customer.customer_phone}</td>
                      <td className="px-5 py-3.5 text-gray-500">{customer.customer_email || '—'}</td>
                      <td className="px-5 py-3.5">
                        <span className="px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-50 text-blue-600">{customer.orders_count}</span>
                      </td>
                      <td className="px-5 py-3.5 font-medium text-gray-800">৳{Number(customer.total_spent || 0).toLocaleString()}</td>
                      <td className="px-5 py-3.5 text-gray-400 text-xs">
                        {customer.last_order_at ? new Date(customer.last_order_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'}
                      </td>
                      <td className="px-5 py-3.5">
                        {customer.account_id ? (
                          <button onClick={() => toggleCustomer(customer)} className={`px-2.5 py-1 text-xs font-medium rounded-full transition-colors ${customer.is_active ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'}`}>
                            {customer.is_active ? 'Active' : 'Inactive'}
                          </button>
                        ) : (
                          <span className="text-xs text-gray-400">Guest order</span>
                        )}
                      </td>
                      <td className="px-5 py-3.5 text-right">
                        {customer.account_id ? (
                          <div className="flex justify-end gap-1.5">
                            <a href={`/admin/customers/${customer.account_id}/edit`} className="px-2.5 py-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium transition-colors">Edit</a>
                            <button onClick={() => deleteCustomer(customer)} className="px-2.5 py-1 text-xs bg-red-50 hover:bg-red-100 text-red-600 rounded-lg font-medium transition-colors">Delete</button>
                          </div>
                        ) : Number(customer.orders_count) > 0 ? (
                          <a href={`/admin/customers/${encodeURIComponent(customer.customer_phone)}`} className="px-2.5 py-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg font-medium transition-colors">View Orders</a>
                        ) : (
                          <span className="text-xs text-gray-400">No orders yet</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {links && links.length > 3 && (
              <div className="p-4 border-t border-gray-50 flex flex-wrap gap-1.5 items-center justify-center">
                {links.map((link, i) => (
                  link.url ? (
                    <button key={i} onClick={() => router.get(link.url)} className={`min-w-[36px] h-9 px-3 rounded-xl text-sm font-medium transition-colors ${link.active ? 'bg-orange-500 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                  ) : (
                    <span key={i} className="min-w-[36px] h-9 px-3 flex items-center justify-center text-sm text-gray-300" dangerouslySetInnerHTML={{ __html: link.label }} />
                  )
                ))}
              </div>
            )}
          </div>
        </div>
      </AdminLayout>
    </>
  );
}
