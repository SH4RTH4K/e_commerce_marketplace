import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid, Cell } from 'recharts';

/* ─── Stat Card ─────────────────────────────────────────────────── */
function StatCard({ label, value, sub, icon, href, accent = '#f97316', bg = '#fff7ed' }) {
  return (
    <a href={href} aria-label={`View ${label}`} className="block bg-white rounded-2xl p-5 shadow-sm border border-gray-100 hover:shadow-md hover:border-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-300 transition-all">
      <div className="flex items-start justify-between mb-4">
        <div className="p-2.5 rounded-xl" style={{ background: bg, color: accent }}>
          {icon}
        </div>
      </div>
      <p className="text-2xl font-bold text-gray-900 mb-1">{value}</p>
      <p className="text-xs font-medium text-gray-400 uppercase tracking-wide">{label}</p>
      {sub && <p className="text-xs text-gray-400 mt-1">{sub}</p>}
    </a>
  );
}

/* ─── Fulfillment status row ────────────────────────────────────── */
function FulfillmentBar({ label, count, total, color }) {
  const pct = total > 0 ? Math.round((count / total) * 100) : 0;
  return (
    <div className="flex items-center gap-3">
      <div className="w-2.5 h-2.5 rounded-full shrink-0" style={{ background: color }} />
      <div className="flex-1 min-w-0">
        <div className="flex items-center justify-between mb-1">
          <span className="text-xs font-medium text-gray-700 capitalize">{label}</span>
          <span className="text-xs text-gray-400">{count.toLocaleString()} ({pct}%)</span>
        </div>
        <div className="h-1.5 bg-gray-100 rounded-full overflow-hidden">
          <div className="h-full rounded-full transition-all duration-700" style={{ width: `${pct}%`, background: color }} />
        </div>
      </div>
    </div>
  );
}

const CustomTooltip = ({ active, payload, label }) => {
  if (active && payload?.length) {
    return (
      <div className="bg-white border border-gray-100 shadow-xl rounded-xl px-4 py-3 text-sm">
        <p className="text-gray-400 text-xs mb-0.5">{label}</p>
        <p className="font-bold text-gray-900">৳{payload[0].value?.toLocaleString()}</p>
      </div>
    );
  }
  return null;
};

/* ─── Main Dashboard ────────────────────────────────────────────── */
export default function Dashboard({ ordersCount, pendingCount, revenue, productsCount, pendingOrders, recentOrders, series, seriesMax, app, stats }) {
  // Derive stats with safe fallbacks
  const confirmedCount  = stats?.confirmed  ?? 0;
  const pendingOrdCount = stats?.pending    ?? pendingCount ?? 0;
  const courierCount    = stats?.shipped    ?? 0;
  const cancelledCount  = stats?.cancelled  ?? 0;
  const lowStockCount   = stats?.low_stock  ?? 0;
  const usersCount      = stats?.users      ?? 0;
  const totalOrders     = Number(ordersCount) || 0;

  const topCards = [
    {
      label: 'Total Revenue',
      value: `৳${Number(revenue).toLocaleString()}`,
      icon: (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <line x1="12" y1="1" x2="12" y2="23"></line>
          <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
        </svg>
      ),
      href: '/admin/analytics', accent: '#f97316', bg: '#fff7ed',
    },
    {
      label: 'Fulfillment Orders',
      value: Number(ordersCount).toLocaleString(),
      icon: (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
          <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
        </svg>
      ),
      href: '/admin/orders', accent: '#3b82f6', bg: '#eff6ff',
    },
    {
      label: 'Registered Users',
      value: Number(usersCount).toLocaleString(),
      icon: (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
          <circle cx="9" cy="7" r="4"></circle>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
      ),
      href: '/admin/customers', accent: '#8b5cf6', bg: '#f5f3ff',
    },
    {
      label: 'Low Stock Alerts',
      value: Number(lowStockCount).toLocaleString(),
      icon: (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
          <line x1="12" y1="9" x2="12" y2="13"></line>
          <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
      ),
      href: '/admin/inventory', accent: '#ef4444', bg: '#fef2f2',
    },
  ];

  const bottomCards = [
    { 
      label: 'Confirmed Orders',  
      value: confirmedCount.toLocaleString(),  
      href: '/admin/orders?status=confirmed', accent: '#10b981', bg: '#ecfdf5',
      icon: (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
          <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
      ) 
    },
    { 
      label: 'Pending Orders',    
      value: pendingOrdCount.toLocaleString(), 
      href: '/admin/orders?status=pending', accent: '#f59e0b', bg: '#fffbeb',
      icon: (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <polyline points="12 6 12 12 16 14"></polyline>
        </svg>
      ) 
    },
    { 
      label: 'Courier Shipments', 
      value: courierCount.toLocaleString(),    
      href: '/admin/orders?status=shipped', accent: '#6366f1', bg: '#eef2ff',
      icon: (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <rect x="1" y="3" width="15" height="13"></rect>
          <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
          <circle cx="5.5" cy="18.5" r="2.5"></circle>
          <circle cx="18.5" cy="18.5" r="2.5"></circle>
        </svg>
      ) 
    },
    { 
      label: 'Cancelled Orders',  
      value: cancelledCount.toLocaleString(),  
      href: '/admin/orders?status=cancelled', accent: '#ef4444', bg: '#fef2f2',
      icon: (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="15" y1="9" x2="9" y2="15"></line>
          <line x1="9" y1="9" x2="15" y2="15"></line>
        </svg>
      ) 
    },
  ];

  const chartData = (series || []).map(p => ({
    name: p.label,
    Revenue: Math.round(p.value),
  }));

  const fulfillmentData = [
    { label: 'Confirmed',  count: confirmedCount,  color: '#10b981' },
    { label: 'Pending',    count: pendingOrdCount,  color: '#f59e0b' },
    { label: 'Shipped',    count: courierCount,     color: '#6366f1' },
    { label: 'Cancelled',  count: cancelledCount,   color: '#ef4444' },
  ];

  return (
    <>
      <Head title="Analytics Dashboard" />
      <AdminLayout title="">
        <div className="space-y-6">

          {/* Page header */}
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
              <h1 className="text-xl font-bold text-gray-900">Analytics Dashboard</h1>
              <p className="text-sm text-gray-400 mt-0.5">Overview of your store performance</p>
            </div>
            <div className="flex items-center gap-2">
              <a href="/admin/orders"
                className="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl transition-colors flex items-center gap-1.5">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>
                </svg>
                View Orders
              </a>
            </div>
          </div>

          {/* Top 4 stat cards */}
          <div className="grid grid-cols-2 xl:grid-cols-4 gap-4">
            {topCards.map(c => <StatCard key={c.label} {...c} />)}
          </div>

          {/* Bottom 4 order-status cards */}
          <div className="grid grid-cols-2 xl:grid-cols-4 gap-4">
            {bottomCards.map(c => <StatCard key={c.label} {...c} />)}
          </div>

          {/* Chart + Fulfillment breakdown */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {/* Sales & Revenue chart */}
            <div className="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 lg:col-span-2">
              <div className="flex items-center justify-between mb-5">
                <div>
                  <h2 className="font-semibold text-gray-900">Sales & Revenue Trend</h2>
                  <p className="text-xs text-gray-400 mt-0.5">Last 14 days — BDT (৳)</p>
                </div>
                <span className="text-xs bg-orange-50 text-orange-600 font-medium px-3 py-1.5 rounded-full border border-orange-100">Live</span>
              </div>
              <ResponsiveContainer width="100%" height={220}>
                <BarChart data={chartData} margin={{ top: 4, right: 4, left: -20, bottom: 0 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" vertical={false} />
                  <XAxis dataKey="name" tick={{ fontSize: 10, fill: '#9ca3af' }} axisLine={false} tickLine={false} />
                  <YAxis tick={{ fontSize: 10, fill: '#9ca3af' }} axisLine={false} tickLine={false} />
                  <Tooltip content={<CustomTooltip />} cursor={{ fill: '#fff7ed', radius: 6 }} />
                  <Bar dataKey="Revenue" radius={[6, 6, 0, 0]} maxBarSize={36}>
                    {chartData.map((_, i) => (
                      <Cell key={i} fill={i === chartData.length - 1 ? '#f97316' : '#fed7aa'} />
                    ))}
                  </Bar>
                </BarChart>
              </ResponsiveContainer>
            </div>

            {/* Fulfillment Status */}
            <div className="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex flex-col">
              <div className="flex items-center justify-between mb-5">
                <div>
                  <h2 className="font-semibold text-gray-900">Fulfillment Status</h2>
                  <p className="text-xs text-gray-400 mt-0.5">{totalOrders.toLocaleString()} total orders</p>
                </div>
              </div>
              <div className="space-y-4 flex-1">
                {fulfillmentData.map(f => (
                  <FulfillmentBar key={f.label} {...f} total={totalOrders} />
                ))}
              </div>
              <a href="/admin/orders" className="mt-5 text-xs text-center text-orange-500 hover:text-orange-600 font-medium">
                Manage Orders →
              </a>
            </div>
          </div>

          {/* Pending Payments + Recent Orders */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {/* Pending Payments */}
            {(pendingOrders || []).length > 0 && (
              <div className="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
                <div className="flex items-center justify-between mb-4">
                  <h2 className="font-semibold text-gray-900 text-sm">Pending Payments</h2>
                  <a href="/admin/orders?status=pending_verification" className="text-xs text-orange-500 hover:text-orange-600 font-medium">View all</a>
                </div>
                <div className="space-y-3">
                  {(pendingOrders || []).map(order => (
                    <div key={order.id} className="flex items-center gap-3 p-3 rounded-xl bg-amber-50 border border-amber-100">
                      <div className="flex-1 min-w-0">
                        <a href={`/admin/orders/${order.id}`} className="text-sm font-semibold text-orange-600 hover:underline">{order.order_number}</a>
                        <p className="text-xs text-gray-500 truncate">{order.customer_name}</p>
                      </div>
                      <div className="text-right shrink-0">
                        <p className="text-sm font-bold text-gray-900">৳{Number(order.total).toLocaleString()}</p>
                        <button type="button" onClick={() => router.post(`/admin/orders/${order.id}/verify`)}
                          className="mt-1 text-[10px] bg-orange-500 hover:bg-orange-600 text-white px-2 py-0.5 rounded-md font-semibold transition-colors">
                          Verify
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Recent Orders */}
            <div className={`bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden ${(pendingOrders || []).length > 0 ? 'lg:col-span-2' : 'lg:col-span-3'}`}>
              <div className="flex items-center justify-between p-5 border-b border-gray-50">
                <h2 className="font-semibold text-gray-900">Recent Orders</h2>
                <a href="/admin/orders" className="text-sm text-orange-500 hover:text-orange-600 font-medium">All orders →</a>
              </div>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-gray-400 bg-gray-50/60">
                      {['Order', 'Customer', 'Total', 'Payment', 'Status', 'Date'].map(h => (
                        <th key={h} className="px-5 py-3 text-[11px] font-semibold uppercase tracking-wider">{h}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-50">
                    {(recentOrders || []).length === 0 ? (
                      <tr><td colSpan="6" className="px-5 py-10 text-center text-gray-400 text-sm">No recent orders.</td></tr>
                    ) : (recentOrders || []).map(order => (
                      <tr key={order.id} className="hover:bg-gray-50/50 transition-colors">
                        <td className="px-5 py-3.5">
                          <a href={`/admin/orders/${order.id}`} className="font-semibold text-orange-500 hover:text-orange-600">{order.order_number}</a>
                        </td>
                        <td className="px-5 py-3.5 text-gray-700">{order.customer_name}</td>
                        <td className="px-5 py-3.5 font-semibold text-gray-900">৳{Number(order.total).toLocaleString()}</td>
                        <td className="px-5 py-3.5">
                          <div className="flex items-center gap-1.5">
                            <span className="text-gray-500 text-xs">{order.payment_method_label || order.payment_method}</span>
                            <span className={`px-2 py-0.5 text-[10px] font-medium rounded-full capitalize ${
                              order.payment_status === 'verified' ? 'bg-green-100 text-green-700' :
                              order.payment_status === 'pending'  ? 'bg-amber-100 text-amber-700' :
                              'bg-gray-100 text-gray-600'}`}>
                              {order.payment_status}
                            </span>
                          </div>
                        </td>
                        <td className="px-5 py-3.5">
                          <span className={`px-2 py-0.5 text-[10px] font-medium rounded-full capitalize ${
                            order.status === 'delivered'  ? 'bg-green-100 text-green-700' :
                            order.status === 'confirmed'  ? 'bg-blue-100 text-blue-700' :
                            order.status === 'shipped'    ? 'bg-purple-100 text-purple-700' :
                            order.status === 'cancelled'  ? 'bg-red-100 text-red-700' :
                            'bg-amber-100 text-amber-700'}`}>
                            {order.status}
                          </span>
                        </td>
                        <td className="px-5 py-3.5 text-gray-400 text-xs">
                          {order.created_at_formatted || new Date(order.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })}
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
