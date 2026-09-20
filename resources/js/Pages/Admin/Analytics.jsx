import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';

function StatCard({ title, value, subtext, growth, isCurrency = false, icon, color = 'orange' }) {
  const isPositive = growth > 0;
  const isNeutral = growth === 0;

  const colorStyles = {
    orange: 'bg-orange-500/10 text-orange-600 border-orange-100',
    emerald: 'bg-emerald-500/10 text-emerald-600 border-emerald-100',
    blue: 'bg-blue-500/10 text-blue-600 border-blue-100',
    purple: 'bg-purple-500/10 text-purple-600 border-purple-100',
  };

  return (
    <div className="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm relative overflow-hidden flex flex-col justify-between">
      <div>
        <div className="flex items-center justify-between mb-3">
          <span className="text-xs font-semibold uppercase tracking-wider text-gray-400">{title}</span>
          <div className={`p-2 rounded-xl border ${colorStyles[color] || colorStyles.orange}`}>
            {icon}
          </div>
        </div>
        <div className="text-2xl sm:text-3xl font-black text-gray-900 tracking-tight">
          {isCurrency ? `৳${Number(value || 0).toLocaleString()}` : Number(value || 0).toLocaleString()}
        </div>
      </div>

      <div className="mt-4 pt-3 border-t border-gray-50 flex items-center justify-between text-xs">
        <span className="text-gray-500">{subtext}</span>
        {growth !== undefined && (
          <span className={`inline-flex items-center gap-1 font-bold px-2 py-0.5 rounded-full ${
            isPositive
              ? 'bg-emerald-50 text-emerald-700'
              : isNeutral
              ? 'bg-gray-100 text-gray-600'
              : 'bg-red-50 text-red-700'
          }`}>
            {isPositive ? '▲ +' : isNeutral ? '' : '▼ '}
            {growth}%
          </span>
        )}
      </div>
    </div>
  );
}

export default function Analytics({
  range = '30d',
  rangeLabel = 'Last 30 Days',
  startDate,
  endDate,
  metrics = {},
  growth = {},
  monthComparison = {},
  chartData = [],
  topProducts = [],
  categoryBreakdown = [],
  paymentMethods = [],
  courierStats = [],
  reviewsSummary = {},
  customerSummary = {},
  paymentHealth = {},
  customerInsights = {},
  inventorySummary = {},
}) {
  const [chartMetric, setChartMetric] = useState('revenue'); // 'revenue' | 'orders'
  const [hoveredPoint, setHoveredPoint] = useState(null);
  const [customRangeModal, setCustomRangeModal] = useState(false);
  const [customStart, setCustomStart] = useState(startDate || '');
  const [customEnd, setCustomEnd] = useState(endDate || '');

  const quickRanges = [
    { key: 'today', label: 'Today' },
    { key: '7d', label: 'Last 7 Days' },
    { key: '30d', label: 'Last 30 Days' },
    { key: 'this_month', label: 'This Month' },
    { key: 'last_month', label: 'Last Month' },
    { key: 'ytd', label: 'Year to Date' },
    { key: 'all', label: 'All Time' },
  ];

  const handleRangeChange = (r) => {
    router.get('/admin/analytics', { range: r }, { preserveState: false });
  };

  const handleCustomSubmit = (e) => {
    e.preventDefault();
    if (!customStart || !customEnd) return;
    router.get('/admin/analytics', { range: 'custom', start_date: customStart, end_date: customEnd }, { preserveState: false });
  };

  // ─── Chart Calculations ─────────────────────────────────────────────
  const maxChartValue = useMemo(() => {
    if (!chartData.length) return 100;
    const maxVal = Math.max(...chartData.map(d => chartMetric === 'revenue' ? d.revenue : d.orders), 0);
    return maxVal === 0 ? 100 : maxVal;
  }, [chartData, chartMetric]);

  const totalChartSum = useMemo(() => {
    if (!chartData.length) return 0;
    return chartData.reduce((acc, d) => acc + (chartMetric === 'revenue' ? d.revenue : d.orders), 0);
  }, [chartData, chartMetric]);

  const printReport = () => {
    window.print();
  };

  return (
    <>
      <Head title="Business Analytics &amp; Reports" />
      <AdminLayout title="">
        <div className="space-y-6 max-w-7xl mx-auto pb-10">

          {/* ── Top Header & Filter Bar ── */}
          <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
            <div>
              <div className="flex items-center gap-2">
                <div className="w-8 h-8 rounded-xl bg-orange-500/10 text-orange-600 flex items-center justify-center">
                  <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                  </svg>
                </div>
                <h1 className="text-xl font-bold text-gray-900">Business Analytics &amp; Sales Reports</h1>
              </div>
              <p className="text-xs text-gray-500 mt-1">
                Showing statistics for <strong className="text-gray-800">{rangeLabel}</strong> ({startDate} to {endDate})
              </p>
            </div>

            {/* Quick Range Selector */}
            <div className="flex flex-wrap items-center gap-1.5 bg-gray-50 p-1.5 rounded-xl border border-gray-200/60 text-xs">
              {quickRanges.map(r => (
                <button
                  key={r.key}
                  onClick={() => handleRangeChange(r.key)}
                  className={`px-3 py-1.5 rounded-lg font-semibold transition-all ${
                    range === r.key
                      ? 'bg-white text-orange-600 shadow-sm border border-gray-200/80 font-bold'
                      : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100/80'
                  }`}
                >
                  {r.label}
                </button>
              ))}
              <button
                onClick={() => setCustomRangeModal(true)}
                className={`px-3 py-1.5 rounded-lg font-semibold transition-all flex items-center gap-1 ${
                  range === 'custom'
                    ? 'bg-white text-orange-600 shadow-sm border border-gray-200/80 font-bold'
                    : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100/80'
                }`}
              >
                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Custom
              </button>

              <button
                onClick={printReport}
                className="px-2.5 py-1.5 text-gray-500 hover:text-gray-800 hover:bg-gray-200/60 rounded-lg transition-colors border-l border-gray-200 ml-1"
                title="Print Report"
              >
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
              </button>
            </div>
          </div>

          {/* ── Custom Range Dialog Modal ── */}
          {customRangeModal && (
            <div className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
              <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-gray-100 space-y-4">
                <div className="flex items-center justify-between pb-3 border-b border-gray-100">
                  <h3 className="font-bold text-gray-900 text-sm">Select Custom Date Range</h3>
                  <button onClick={() => setCustomRangeModal(false)} className="text-gray-400 hover:text-gray-600 text-sm">✕</button>
                </div>
                <form onSubmit={handleCustomSubmit} className="space-y-4">
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="block text-xs font-semibold text-gray-600 mb-1">Start Date</label>
                      <input
                        type="date"
                        value={customStart}
                        onChange={e => setCustomStart(e.target.value)}
                        className="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-orange-300 focus:outline-none"
                        required
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-gray-600 mb-1">End Date</label>
                      <input
                        type="date"
                        value={customEnd}
                        onChange={e => setCustomEnd(e.target.value)}
                        className="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-orange-300 focus:outline-none"
                        required
                      />
                    </div>
                  </div>
                  <div className="flex justify-end gap-2 pt-2">
                    <button
                      type="button"
                      onClick={() => setCustomRangeModal(false)}
                      className="px-4 py-2 text-xs text-gray-600 hover:bg-gray-100 rounded-xl font-medium"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      className="px-5 py-2 bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold rounded-xl shadow-sm"
                    >
                      Apply Filter
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}

          {/* ── Key Performance Metrics Grid ── */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <StatCard
              title="Gross Sales Revenue"
              value={metrics.gross_revenue}
              isCurrency={true}
              growth={growth.revenue}
              subtext={`৳${Number(metrics.delivered_revenue || 0).toLocaleString()} realized`}
              color="orange"
              icon={
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              }
            />

            <StatCard
              title="Total Orders Placed"
              value={metrics.total_orders}
              growth={growth.orders}
              subtext={`${metrics.delivered_orders} delivered, ${metrics.cancelled_orders} cancelled`}
              color="blue"
              icon={
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
              }
            />

            <StatCard
              title="Delivery Success Rate"
              value={`${metrics.delivery_success_rate}%`}
              subtext={`${metrics.delivered_orders} successfully delivered`}
              growth={growth.delivered}
              color="emerald"
              icon={
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              }
            />

            <StatCard
              title="Average Order Value"
              value={metrics.aov}
              isCurrency={true}
              growth={growth.aov}
              subtext="Revenue per order placed"
              color="purple"
              icon={
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                </svg>
              }
            />
          </div>

          {/* ── This Month vs Last Month Dedicated Comparison Card ── */}
          <div className="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 rounded-2xl p-6 text-white shadow-md border border-slate-800">
            <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-4 border-b border-white/10">
              <div>
                <div className="flex items-center gap-2">
                  <span className="px-2.5 py-0.5 rounded-full bg-orange-500 text-white font-bold text-[10px] uppercase tracking-wider">Month vs Month</span>
                  <h3 className="font-bold text-base text-white">
                    {monthComparison.this_month_name} vs {monthComparison.last_month_name} Performance
                  </h3>
                </div>
                <p className="text-xs text-slate-300 mt-1">Real-time performance comparison against last month's full figures.</p>
              </div>

              <div className="flex items-center gap-2 text-xs">
                <span className="flex items-center gap-1 text-slate-300"><span className="w-2 h-2 rounded-full bg-orange-400"></span> {monthComparison.this_month_name} (Current)</span>
                <span className="flex items-center gap-1 text-slate-400"><span className="w-2 h-2 rounded-full bg-slate-500"></span> {monthComparison.last_month_name} (Previous)</span>
              </div>
            </div>

            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-5">
              {/* Sales Revenue */}
              <div className="bg-white/5 rounded-xl p-4 border border-white/5 space-y-1">
                <p className="text-[11px] text-slate-400 font-semibold uppercase">Sales Revenue</p>
                <div className="text-lg sm:text-xl font-bold text-white">৳{Number(monthComparison.this_month_revenue || 0).toLocaleString()}</div>
                <div className="text-[11px] text-slate-400 flex items-center justify-between pt-1">
                  <span>Last Mo: ৳{Number(monthComparison.last_month_revenue || 0).toLocaleString()}</span>
                  <span className={`font-bold ${monthComparison.revenue_diff_percent >= 0 ? 'text-emerald-400' : 'text-red-400'}`}>
                    {monthComparison.revenue_diff_percent >= 0 ? '+' : ''}{monthComparison.revenue_diff_percent}%
                  </span>
                </div>
              </div>

              {/* Total Orders */}
              <div className="bg-white/5 rounded-xl p-4 border border-white/5 space-y-1">
                <p className="text-[11px] text-slate-400 font-semibold uppercase">Total Orders</p>
                <div className="text-lg sm:text-xl font-bold text-white">{monthComparison.this_month_orders}</div>
                <div className="text-[11px] text-slate-400 flex items-center justify-between pt-1">
                  <span>Last Mo: {monthComparison.last_month_orders}</span>
                  <span className={`font-bold ${monthComparison.orders_diff_percent >= 0 ? 'text-emerald-400' : 'text-red-400'}`}>
                    {monthComparison.orders_diff_percent >= 0 ? '+' : ''}{monthComparison.orders_diff_percent}%
                  </span>
                </div>
              </div>

              {/* Delivered Orders */}
              <div className="bg-white/5 rounded-xl p-4 border border-white/5 space-y-1">
                <p className="text-[11px] text-slate-400 font-semibold uppercase">Delivered Orders</p>
                <div className="text-lg sm:text-xl font-bold text-emerald-400">{monthComparison.this_month_delivered}</div>
                <div className="text-[11px] text-slate-400 flex items-center justify-between pt-1">
                  <span>Last Mo: {monthComparison.last_month_delivered}</span>
                  <span className="text-slate-300">
                    {monthComparison.this_month_orders > 0 ? Math.round((monthComparison.this_month_delivered / monthComparison.this_month_orders) * 100) : 0}% deliv.
                  </span>
                </div>
              </div>

              {/* Average Order Value */}
              <div className="bg-white/5 rounded-xl p-4 border border-white/5 space-y-1">
                <p className="text-[11px] text-slate-400 font-semibold uppercase">Avg Order Value</p>
                <div className="text-lg sm:text-xl font-bold text-white">৳{Number(monthComparison.this_month_aov || 0).toLocaleString()}</div>
                <div className="text-[11px] text-slate-400 flex items-center justify-between pt-1">
                  <span>Last Mo: ৳{Number(monthComparison.last_month_aov || 0).toLocaleString()}</span>
                  <span className="text-slate-300">AOV</span>
                </div>
              </div>
            </div>
          </div>

          {/* ── Main Interactive Timeline Chart ── */}
          <div className="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm space-y-5">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
              <div>
                <h3 className="font-bold text-gray-900 text-base">Sales &amp; Orders Timeline</h3>
                <p className="text-xs text-gray-500">
                  Total for period: <strong className="text-gray-900">{chartMetric === 'revenue' ? `৳${Number(totalChartSum).toLocaleString()}` : `${totalChartSum} orders`}</strong>
                </p>
              </div>

              {/* Metric Toggle */}
              <div className="flex items-center bg-gray-100 p-1 rounded-xl text-xs font-semibold self-start sm:self-auto">
                <button
                  onClick={() => setChartMetric('revenue')}
                  className={`px-3 py-1.5 rounded-lg transition-all ${
                    chartMetric === 'revenue'
                      ? 'bg-white text-orange-600 shadow-sm font-bold'
                      : 'text-gray-600 hover:text-gray-900'
                  }`}
                >
                  Sales Revenue (৳)
                </button>
                <button
                  onClick={() => setChartMetric('orders')}
                  className={`px-3 py-1.5 rounded-lg transition-all ${
                    chartMetric === 'orders'
                      ? 'bg-white text-orange-600 shadow-sm font-bold'
                      : 'text-gray-600 hover:text-gray-900'
                  }`}
                >
                  Order Volume
                </button>
              </div>
            </div>

            {/* Interactive Bar/Column Chart Container */}
            <div className="relative pt-4">
              {hoveredPoint && (
                <div className="mb-2 p-2.5 bg-slate-900 text-white rounded-xl text-xs inline-flex items-center gap-4 shadow-lg animate-fade-in">
                  <span className="font-bold text-orange-400">{hoveredPoint.full_date}</span>
                  <span>Revenue: <strong>৳{Number(hoveredPoint.revenue).toLocaleString()}</strong></span>
                  <span>Orders: <strong>{hoveredPoint.orders}</strong></span>
                  <span>Delivered: <strong className="text-emerald-400">{hoveredPoint.delivered}</strong></span>
                </div>
              )}

              <div className="h-64 flex items-end gap-1.5 sm:gap-2 border-b border-gray-100 pb-2 px-1">
                {chartData.map((d, idx) => {
                  const val = chartMetric === 'revenue' ? d.revenue : d.orders;
                  const heightPercent = maxChartValue > 0 ? Math.max(4, Math.round((val / maxChartValue) * 100)) : 4;
                  const isHovered = hoveredPoint && hoveredPoint.label === d.label;

                  return (
                    <div
                      key={idx}
                      className="flex-1 flex flex-col items-center h-full justify-end group cursor-pointer relative"
                      onMouseEnter={() => setHoveredPoint(d)}
                      onMouseLeave={() => setHoveredPoint(null)}
                    >
                      {/* Bar Fill */}
                      <div
                        className={`w-full max-w-[32px] rounded-t-md transition-all duration-200 ${
                          isHovered
                            ? 'bg-orange-600 shadow-md shadow-orange-200'
                            : val > 0
                            ? 'bg-orange-500 group-hover:bg-orange-600'
                            : 'bg-gray-100 group-hover:bg-gray-200'
                        }`}
                        style={{ height: `${heightPercent}%` }}
                      />

                      {/* Label on every few ticks */}
                      {(chartData.length <= 14 || idx % Math.ceil(chartData.length / 10) === 0 || idx === chartData.length - 1) && (
                        <span className="text-[10px] text-gray-400 mt-2 truncate max-w-full font-medium">
                          {d.label}
                        </span>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
          </div>

          {/* ── 2-Column: Order Status Distribution & Category Revenue Share ── */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            {/* 1. Order Status Breakdown */}
            <div className="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm space-y-4">
              <div className="flex items-center justify-between pb-3 border-b border-gray-100">
                <h3 className="font-bold text-gray-900 text-sm">Order Fulfillment &amp; Status Breakdown</h3>
                <span className="text-xs text-gray-400 font-semibold">{metrics.total_orders} Total Orders</span>
              </div>

              {/* Status Bars */}
              <div className="space-y-3.5">
                {[
                  { label: 'Delivered', count: metrics.delivered_orders, color: 'bg-emerald-500', text: 'text-emerald-700', bg: 'bg-emerald-50' },
                  { label: 'Shipped / In Transit', count: metrics.shipped_orders, color: 'bg-purple-500', text: 'text-purple-700', bg: 'bg-purple-50' },
                  { label: 'Confirmed', count: metrics.confirmed_orders, color: 'bg-blue-500', text: 'text-blue-700', bg: 'bg-blue-50' },
                  { label: 'Pending Verification', count: metrics.pending_orders, color: 'bg-amber-500', text: 'text-amber-700', bg: 'bg-amber-50' },
                  { label: 'Cancelled / Returned', count: metrics.cancelled_orders, color: 'bg-red-500', text: 'text-red-700', bg: 'bg-red-50' },
                ].map((st) => {
                  const percent = metrics.total_orders > 0 ? Math.round((st.count / metrics.total_orders) * 100) : 0;
                  return (
                    <div key={st.label} className="space-y-1.5">
                      <div className="flex items-center justify-between text-xs">
                        <span className="font-semibold text-gray-700 flex items-center gap-2">
                          <span className={`w-2.5 h-2.5 rounded-full ${st.color}`} />
                          {st.label}
                        </span>
                        <span className="font-bold text-gray-900">
                          {st.count} <span className="text-gray-400 font-normal">({percent}%)</span>
                        </span>
                      </div>
                      <div className="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                        <div className={`h-2 rounded-full ${st.color} transition-all duration-500`} style={{ width: `${percent}%` }} />
                      </div>
                    </div>
                  );
                })}
              </div>

              <div className="p-3.5 rounded-xl bg-gray-50 border border-gray-100 text-xs flex items-center justify-between text-gray-600">
                <span>Cancellation Rate: <strong className="text-red-600">{metrics.cancellation_rate}%</strong></span>
                <span>Delivery Success: <strong className="text-emerald-600">{metrics.delivery_success_rate}%</strong></span>
              </div>
            </div>

            {/* 2. Category Share Breakdown */}
            <div className="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm space-y-4">
              <div className="flex items-center justify-between pb-3 border-b border-gray-100">
                <h3 className="font-bold text-gray-900 text-sm">Revenue by Product Category</h3>
                <span className="text-xs text-gray-400 font-semibold">{categoryBreakdown.length} Categories</span>
              </div>

              {categoryBreakdown.length === 0 ? (
                <div className="py-12 text-center text-gray-400 text-xs">No sales recorded for categories in this period.</div>
              ) : (
                <div className="space-y-3.5 max-h-72 overflow-y-auto pr-1">
                  {categoryBreakdown.map((cat) => (
                    <div key={cat.id} className="space-y-1.5">
                      <div className="flex items-center justify-between text-xs">
                        <span className="font-semibold text-gray-800">{cat.name}</span>
                        <span className="font-bold text-gray-900">
                          ৳{Number(cat.revenue).toLocaleString()} <span className="text-gray-400 font-normal">({cat.share_percent}%)</span>
                        </span>
                      </div>
                      <div className="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                        <div className="h-2 rounded-full bg-orange-500 transition-all duration-500" style={{ width: `${cat.share_percent}%` }} />
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* ── Top Selling Products Leaderboard ── */}
          <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden space-y-0">
            <div className="p-5 border-b border-gray-100 flex items-center justify-between">
              <div className="flex items-center gap-2">
                <svg className="w-5 h-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                </svg>
                <h3 className="font-bold text-gray-900 text-sm">Top Selling Products in Selected Period</h3>
              </div>
              <a href="/admin/products" className="text-xs text-orange-600 hover:underline font-semibold">View All Products ↗</a>
            </div>

            {topProducts.length === 0 ? (
              <div className="py-12 text-center text-gray-400 text-xs">No product sales found for this period.</div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="bg-gray-50/70 text-gray-500 text-xs uppercase font-bold text-left border-b border-gray-100">
                      <th className="px-5 py-3.5"># Rank</th>
                      <th className="px-5 py-3.5">Product</th>
                      <th className="px-5 py-3.5">Category</th>
                      <th className="px-5 py-3.5 text-center">Units Sold</th>
                      <th className="px-5 py-3.5 text-right">Revenue Generated</th>
                      <th className="px-5 py-3.5 text-center">Stock Left</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-50">
                    {topProducts.map((p, idx) => (
                      <tr key={idx} className="hover:bg-gray-50/50 transition-colors">
                        <td className="px-5 py-3 font-bold text-gray-400 text-xs">#{idx + 1}</td>
                        <td className="px-5 py-3">
                          <div className="flex items-center gap-3">
                            {p.image ? (
                              <img src={p.image} alt="" className="w-9 h-9 rounded-lg object-cover border border-gray-100 shrink-0" />
                            ) : (
                              <div className="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400 text-xs shrink-0">SZ</div>
                            )}
                            <div>
                              <p className="font-bold text-gray-900 text-xs sm:text-sm line-clamp-1">{p.name}</p>
                              <p className="text-[11px] text-gray-400 font-mono">SKU: {p.sku}</p>
                            </div>
                          </div>
                        </td>
                        <td className="px-5 py-3 text-xs text-gray-600 font-medium">{p.category}</td>
                        <td className="px-5 py-3 text-center font-bold text-gray-900 text-xs sm:text-sm">{p.units_sold}</td>
                        <td className="px-5 py-3 text-right font-black text-orange-600 text-xs sm:text-sm">
                          ৳{Number(p.total_revenue).toLocaleString()}
                        </td>
                        <td className="px-5 py-3 text-center">
                          <span className={`px-2.5 py-0.5 rounded-full text-xs font-semibold ${
                            p.stock === 0 ? 'bg-red-100 text-red-700' :
                            p.stock <= 5 ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700'
                          }`}>
                            {p.stock} units
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>

          {/* ── 3-Column Grid: Payment Methods, Customer CRM, and Reviews Sentiment ── */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            {/* 1. Payment Methods */}
            <div className="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm space-y-4">
              <h3 className="font-bold text-gray-900 text-sm pb-2 border-b border-gray-100 flex items-center gap-2">
                <svg className="w-4 h-4 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                Payment Gateways Share
              </h3>

              {paymentMethods.length === 0 ? (
                <p className="text-xs text-gray-400 py-6 text-center">No payment data available.</p>
              ) : (
                <div className="space-y-3">
                  {paymentMethods.map(pm => (
                    <div key={pm.method} className="space-y-1">
                      <div className="flex items-center justify-between text-xs">
                        <span className="font-semibold text-gray-700">{pm.label}</span>
                        <span className="font-bold text-gray-900">{pm.count} ({pm.share_percent}%)</span>
                      </div>
                      <div className="w-full bg-gray-100 rounded-full h-2">
                        <div className="h-2 rounded-full bg-blue-500" style={{ width: `${pm.share_percent}%` }} />
                      </div>
                      <div className="text-[10px] text-gray-400 text-right">৳{Number(pm.revenue).toLocaleString()}</div>
                    </div>
                  ))}
                </div>
              )}
            </div>

            {/* 2. Customer CRM & Abandoned Recovery */}
            <div className="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm space-y-4">
              <h3 className="font-bold text-gray-900 text-sm pb-2 border-b border-gray-100 flex items-center gap-2">
                <svg className="w-4 h-4 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                Customer &amp; Cart Recovery
              </h3>

              <div className="space-y-3 text-xs">
                <div className="flex items-center justify-between p-2.5 rounded-xl bg-gray-50">
                  <span className="text-gray-600">New Registered Customers</span>
                  <strong className="text-gray-900">{customerSummary.new_customers}</strong>
                </div>
                <div className="flex items-center justify-between p-2.5 rounded-xl bg-gray-50">
                  <span className="text-gray-600">Total Customer Database</span>
                  <strong className="text-gray-900">{customerSummary.total_customers}</strong>
                </div>
                <div className="flex items-center justify-between p-2.5 rounded-xl bg-amber-50 text-amber-900">
                  <span>Abandoned Checkouts</span>
                  <strong>{customerSummary.abandoned_carts}</strong>
                </div>
                <div className="flex items-center justify-between p-2.5 rounded-xl bg-emerald-50 text-emerald-900">
                  <span>Recovered Carts</span>
                  <strong>{customerSummary.recovered_carts} ({customerSummary.recovery_rate}%)</strong>
                </div>
                <div className="p-2.5 rounded-xl bg-emerald-500/10 text-emerald-700 text-xs font-bold text-center">
                  ৳{Number(customerSummary.recovered_revenue || 0).toLocaleString()} Recovered Sales
                </div>
              </div>
            </div>

            {/* 3. Customer Reviews Sentiment */}
            <div className="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm space-y-4">
              <div className="flex items-center justify-between pb-2 border-b border-gray-100">
                <h3 className="font-bold text-gray-900 text-sm flex items-center gap-2">
                  <svg className="w-4 h-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                  </svg>
                  Customer Reviews
                </h3>
                <span className="text-xs font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-md">
                  ★ {reviewsSummary.average_rating} / 5.0
                </span>
              </div>

              <div className="space-y-2">
                {[5, 4, 3, 2, 1].map(stars => {
                  const count = (reviewsSummary.stars && reviewsSummary.stars[stars]) || 0;
                  const total = reviewsSummary.approved || 1;
                  const pct = Math.round((count / total) * 100);
                  return (
                    <div key={stars} className="flex items-center gap-2 text-xs">
                      <span className="w-8 font-semibold text-gray-500">{stars} ★</span>
                      <div className="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
                        <div className="h-2 rounded-full bg-amber-400" style={{ width: `${pct}%` }} />
                      </div>
                      <span className="w-8 text-right text-gray-400 font-medium">{count}</span>
                    </div>
                  );
                })}
              </div>

              <div className="pt-2 border-t border-gray-50 flex items-center justify-between text-xs text-gray-500">
                <span>{reviewsSummary.approved} Approved Reviews</span>
                {reviewsSummary.pending > 0 && (
                  <span className="text-amber-600 font-bold">{reviewsSummary.pending} Pending Moderation</span>
                )}
              </div>
            </div>

          </div>

          {/* ── Operational reports ── */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div className="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm space-y-4">
              <div className="flex items-center justify-between pb-2 border-b border-gray-100">
                <h3 className="font-bold text-gray-900 text-sm">Payment Clearance</h3>
                <a href="/admin/orders" className="text-xs font-semibold text-orange-600 hover:underline">Review orders →</a>
              </div>
              <div className="space-y-2.5 text-xs">
                {[
                  ['Verified', paymentHealth.verified, 'bg-emerald-50 text-emerald-800'],
                  ['Awaiting verification', paymentHealth.pending, 'bg-amber-50 text-amber-800'],
                  ['Rejected', paymentHealth.rejected, 'bg-red-50 text-red-800'],
                ].map(([label, data, color]) => (
                  <div key={label} className={`rounded-xl p-3 ${color}`}>
                    <div className="flex justify-between gap-3"><span>{label}</span><strong>{data?.count || 0} orders</strong></div>
                    <div className="mt-1 font-bold">৳{Number(data?.revenue || 0).toLocaleString()}</div>
                  </div>
                ))}
              </div>
            </div>

            <div className="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm space-y-4">
              <div className="flex items-center justify-between pb-2 border-b border-gray-100">
                <h3 className="font-bold text-gray-900 text-sm">Customer Loyalty &amp; Cities</h3>
                <a href="/admin/customers" className="text-xs font-semibold text-orange-600 hover:underline">View customers →</a>
              </div>
              <div className="grid grid-cols-2 gap-2 text-xs">
                <div className="rounded-xl bg-blue-50 p-3"><span className="text-blue-700">Unique buyers</span><strong className="block mt-1 text-lg text-blue-950">{customerInsights.unique_buyers || 0}</strong></div>
                <div className="rounded-xl bg-purple-50 p-3"><span className="text-purple-700">Returning buyers</span><strong className="block mt-1 text-lg text-purple-950">{customerInsights.repeat_buyers || 0}</strong></div>
              </div>
              <p className="text-xs text-gray-500">Repeat buyers: <strong className="text-gray-900">{customerInsights.repeat_buyer_rate || 0}%</strong> · Repeat orders: <strong className="text-gray-900">{customerInsights.repeat_order_rate || 0}%</strong></p>
              {(customerInsights.top_cities || []).length > 0 ? (
                <div className="space-y-1.5 text-xs">
                  {customerInsights.top_cities.map((city) => <div key={city.name} className="flex justify-between gap-3"><span className="truncate text-gray-600">{city.name}</span><strong className="shrink-0 text-gray-900">{city.orders} · ৳{Number(city.revenue).toLocaleString()}</strong></div>)}
                </div>
              ) : <p className="text-xs text-gray-400 text-center py-2">No delivery-city data for this period.</p>}
            </div>

            <div className="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm space-y-4">
              <div className="flex items-center justify-between pb-2 border-b border-gray-100">
                <h3 className="font-bold text-gray-900 text-sm">Inventory Readiness</h3>
                <a href="/admin/inventory" className="text-xs font-semibold text-orange-600 hover:underline">Manage stock →</a>
              </div>
              <div className="grid grid-cols-2 gap-2 text-xs">
                <a href="/admin/inventory?stock_level=out" className="rounded-xl bg-red-50 p-3 hover:bg-red-100"><span className="text-red-700">Out of stock</span><strong className="block mt-1 text-lg text-red-950">{inventorySummary.out_of_stock || 0}</strong></a>
                <a href="/admin/inventory?stock_level=low" className="rounded-xl bg-amber-50 p-3 hover:bg-amber-100"><span className="text-amber-700">Low stock</span><strong className="block mt-1 text-lg text-amber-950">{inventorySummary.low_stock || 0}</strong></a>
                <div className="rounded-xl bg-emerald-50 p-3"><span className="text-emerald-700">Published</span><strong className="block mt-1 text-lg text-emerald-950">{inventorySummary.published_products || 0}</strong></div>
                <div className="rounded-xl bg-gray-50 p-3"><span className="text-gray-600">Units on hand</span><strong className="block mt-1 text-lg text-gray-950">{Number(inventorySummary.units_on_hand || 0).toLocaleString()}</strong></div>
              </div>
              <p className="text-xs text-gray-500">{inventorySummary.total_products || 0} products total · {inventorySummary.draft_products || 0} drafts</p>
            </div>
          </div>

        </div>
      </AdminLayout>
    </>
  );
}
