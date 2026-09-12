import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
import CourierShipModal from '@/Components/CourierShipModal';

function StatusBadge({ status }) {
  const map = { pending:'bg-amber-100 text-amber-700',confirmed:'bg-blue-100 text-blue-700',shipped:'bg-purple-100 text-purple-700',delivered:'bg-green-100 text-green-700',cancelled:'bg-red-100 text-red-700' };
  return <span className={`px-2.5 py-1 text-xs font-medium rounded-full capitalize ${map[status]||'bg-gray-100 text-gray-600'}`}>{status}</span>;
}
function PaymentBadge({ status }) {
  const map = { pending:'bg-amber-100 text-amber-700',verified:'bg-green-100 text-green-700',rejected:'bg-red-100 text-red-700',cod:'bg-gray-100 text-gray-600' };
  return <span className={`px-2.5 py-1 text-xs font-medium rounded-full capitalize ${map[status]||'bg-gray-100 text-gray-600'}`}>{status}</span>;
}

function normalizeRiskLevel(level) {
  const normalized = String(level || 'unknown')
    .toLowerCase()
    .trim()
    .replace(/[\s-]+/g, '_')
    .replace(/_+/g, '_');

  if (normalized.includes('danger')) return 'danger';
  if (normalized.includes('high')) return 'high';
  if (normalized.includes('medium') || normalized.includes('moderate')) return 'medium';
  if (normalized.includes('low')) return 'low';
  if (normalized.includes('safe') || normalized.includes('good')) return 'safe';
  return normalized;
}

function resolveRiskLevel(level, label) {
  const knownLevels = ['safe', 'low', 'medium', 'high', 'danger'];
  const normalizedLevel = normalizeRiskLevel(level);
  if (knownLevels.includes(normalizedLevel)) return normalizedLevel;

  const normalizedLabel = normalizeRiskLevel(label);
  return knownLevels.includes(normalizedLabel) ? normalizedLabel : normalizedLevel;
}

function riskDisplayLabel(level, label) {
  const normalized = resolveRiskLevel(level, label);
  const raw = String(label || level || normalized)
    .replace(/(?:[\s_-]+risk)+$/i, '')
    .trim();
  return raw || normalized;
}

function riskRatioClass(level, ratio) {
  const normalized = resolveRiskLevel(level);
  if (normalized === 'danger' || normalized === 'high') return 'text-red-500';
  if (normalized === 'medium') return 'text-amber-500';
  if (normalized === 'low') return 'text-blue-500';
  if (normalized === 'safe') return 'text-green-600';
  return Number(ratio) < 50 ? 'text-red-500' : 'text-green-600';
}

function RiskBadge({ level, label }) {
  const map = {
    safe:   'bg-green-100 text-green-700 border-green-200',
    low:    'bg-blue-100 text-blue-700 border-blue-200',
    medium: 'bg-amber-100 text-amber-700 border-amber-200',
    high:   'bg-red-100 text-red-700 border-red-200',
    danger: 'bg-rose-100 text-rose-700 border-rose-200',
  };
  const normalized = resolveRiskLevel(level, label);
  const cls = map[normalized] || 'bg-gray-100 text-gray-600 border-gray-200';
  const icon = normalized === 'danger' || normalized === 'high' ? '⚠ ' : normalized === 'safe' ? '✓ ' : '● ';
  return (
    <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wide border ${cls}`}>
      {icon}{riskDisplayLabel(level, label)} Risk
    </span>
  );
}

function getCsrfToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.content : '';
}

function CourierBreakdown({ result }) {
  const rows = Object.entries(result?.data || {})
    .filter(([key, value]) => key !== 'summary' && value && typeof value === 'object' && value.total_parcel !== undefined)
    .map(([key, value]) => {
      const total = Number(value.total_parcel) || 0;
      const success = Number(value.success_parcel) || 0;
      return {
        key,
        name: value.name || key.replace(/[-_]/g, ' ').replace(/\b\w/g, character => character.toUpperCase()),
        logo: value.logo || null,
        total,
        success,
        cancelled: value.cancelled_parcel === undefined ? Math.max(0, total - success) : Number(value.cancelled_parcel) || 0,
        ratio: value.success_ratio === undefined ? (total > 0 ? (success / total) * 100 : 0) : Number(value.success_ratio) || 0,
      };
    });

  if (!rows.length) return null;

  return (
    <div className="border border-gray-100 rounded-xl overflow-x-auto">
      <table className="w-full text-xs">
        <thead className="bg-gray-50 text-gray-500">
          <tr>
            <th className="px-3 py-2.5 text-left font-semibold">Courier</th>
            <th className="px-3 py-2.5 text-center font-semibold">Total</th>
            <th className="px-3 py-2.5 text-center font-semibold">Success</th>
            <th className="px-3 py-2.5 text-center font-semibold">Cancelled</th>
            <th className="px-3 py-2.5 text-right font-semibold">Rate</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-50">
          {rows.map(row => (
            <tr key={row.key}>
              <td className="px-3 py-2.5">
                <div className="flex items-center gap-2">
                  {row.logo && <img src={row.logo} alt="" className="w-7 h-5 object-contain" onError={event => { event.currentTarget.style.display = 'none'; }} />}
                  <span className="font-medium text-gray-800">{row.name}</span>
                </div>
              </td>
              <td className="px-3 py-2.5 text-center text-gray-600">{row.total}</td>
              <td className="px-3 py-2.5 text-center font-semibold text-green-600">{row.success}</td>
              <td className="px-3 py-2.5 text-center text-red-500">{row.cancelled}</td>
              <td className="px-3 py-2.5 text-right font-semibold text-gray-700">{row.ratio.toFixed(1)}%</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

/* ─────────────────────────────────────────────────────────────────────────────
   Fraud & History Panel
   ───────────────────────────────────────────────────────────────────────────── */
function FraudHistoryPanel({ order }) {
  const [activeTab, setActiveTab] = useState('fraud');

  // ── Fraud Check ──────────────────────────────────────────────────────
  const [fraudLoading, setFraudLoading] = useState(false);
  const [fraudResult, setFraudResult]   = useState(null);
  const [fraudError, setFraudError]     = useState(null);

  const runFraudCheck = async () => {
    if (!order.customer_phone) return;
    setFraudLoading(true);
    setFraudError(null);
    setFraudResult(null);
    try {
      const res = await fetch('/admin/orders/check-fraud', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          phone: order.customer_phone,
          customer_name: order.customer_name,
          order_id: order.id,
        }),
      });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || 'Unknown error');
      setFraudResult(json);
    } catch (err) {
      setFraudError(err.message);
    } finally {
      setFraudLoading(false);
    }
  };

  // ── Customer History ─────────────────────────────────────────────────
  const [histLoading, setHistLoading] = useState(false);
  const [histResult, setHistResult]   = useState(null);
  const [histError, setHistError]     = useState(null);
  const [histLoaded, setHistLoaded]   = useState(false);

  const loadHistory = useCallback(async () => {
    if (histLoaded || !order.customer_phone) return;
    setHistLoading(true);
    setHistError(null);
    try {
      const params = new URLSearchParams({ phone: order.customer_phone, order_id: order.id });
      const res = await fetch(`/admin/orders/customer-history?${params}`, {
        headers: { 'Accept': 'application/json' },
      });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || 'Failed to load history');
      setHistResult(json);
      setHistLoaded(true);
    } catch (err) {
      setHistError(err.message);
    } finally {
      setHistLoading(false);
    }
  }, [histLoaded, order.customer_phone, order.id]);

  useEffect(() => {
    if (activeTab === 'history') loadHistory();
  }, [activeTab, loadHistory]);

  return (
    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      {/* Panel header */}
      <div className="p-5 border-b border-gray-100 flex items-center gap-3">
        <svg className="w-4 h-4 text-violet-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
          <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
        <h3 className="font-semibold text-gray-900 text-sm">Fraud &amp; History</h3>
        <span className="text-xs text-gray-400 font-mono bg-gray-50 px-2 py-0.5 rounded-lg border border-gray-100">
          {order.customer_phone}
        </span>
      </div>

      {/* Sub-tabs */}
      <div className="flex border-b border-gray-100">
        {[
          { key: 'fraud',   label: '🛡 Fraud Check' },
          { key: 'history', label: '📋 Order History' },
        ].map(t => (
          <button
            key={t.key}
            onClick={() => setActiveTab(t.key)}
            className={`flex-1 py-2.5 text-xs font-semibold transition-all ${
              activeTab === t.key
                ? 'text-violet-600 border-b-2 border-violet-500 bg-violet-50/40'
                : 'text-gray-400 hover:text-gray-600'
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {/* ── Fraud Check Tab ── */}
      {activeTab === 'fraud' && (
        <div className="p-5 space-y-4">
          <div className="flex items-center gap-3">
            <div className="flex-1 bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm text-gray-700 font-mono">
              {order.customer_phone || '—'}
            </div>
            <button
              onClick={runFraudCheck}
              disabled={fraudLoading || !order.customer_phone}
              className="shrink-0 px-4 py-2.5 bg-violet-500 hover:bg-violet-600 disabled:opacity-50 text-white text-sm font-semibold rounded-xl transition-colors flex items-center gap-2"
            >
              {fraudLoading ? (
                <>
                  <svg className="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                  </svg>
                  Checking…
                </>
              ) : 'Check Fraud'}
            </button>
          </div>

          {fraudError && (
            <div className="bg-red-50 border border-red-200 rounded-xl p-3.5 text-sm text-red-700 flex items-start gap-2">
              <svg className="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              {fraudError}
            </div>
          )}

          {!fraudResult && !fraudLoading && !fraudError && (
            <p className="text-xs text-gray-400 text-center py-6">
              Click &ldquo;Check Fraud&rdquo; to query BD Courier's database for this phone number.
            </p>
          )}

          {fraudResult && (() => {
            const summary = fraudResult.data?.summary || {};
            const total   = summary.total_parcel    || 0;
            const success = summary.success_parcel  || 0;
            const failed  = summary.cancelled_parcel|| 0;
            const ratio   = fraudResult._success_ratio || 0;
            const level   = fraudResult._risk_level;
            const label   = fraudResult._risk_label;
            const normalizedLevel = resolveRiskLevel(level, label);
            const reports = fraudResult.reports || [];

            const bannerCls =
              normalizedLevel === 'danger' ? 'bg-rose-50 border-rose-200' :
              normalizedLevel === 'high'   ? 'bg-red-50 border-red-200'   :
              normalizedLevel === 'medium' ? 'bg-amber-50 border-amber-200':
              normalizedLevel === 'low'    ? 'bg-blue-50 border-blue-200'  :
                                   'bg-green-50 border-green-200';

            const ratioCls = riskRatioClass(normalizedLevel, ratio).replace('-500', '-600');

            return (
              <div className="space-y-4">
                {/* Risk banner */}
                <div className={`rounded-xl p-4 border flex items-center justify-between ${bannerCls}`}>
                  <div>
                    <p className="text-xs text-gray-500 mb-1.5">Risk Assessment</p>
                    <RiskBadge level={level} label={label} />
                  </div>
                  <div className="text-right">
                    <p className={`text-3xl font-black ${ratioCls}`}>{ratio.toFixed(0)}%</p>
                    <p className="text-xs text-gray-500">Success Rate</p>
                  </div>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-3 gap-3">
                  {[
                    { label: 'Total', value: total, color: 'text-gray-800' },
                    { label: 'Delivered', value: success, color: 'text-green-600' },
                    { label: 'Cancelled', value: failed, color: 'text-red-500' },
                  ].map(s => (
                    <div key={s.label} className="bg-gray-50 rounded-xl p-3 text-center border border-gray-100">
                      <p className={`text-xl font-black ${s.color}`}>{s.value}</p>
                      <p className="text-xs text-gray-400 mt-0.5">{s.label}</p>
                    </div>
                  ))}
                </div>

                <CourierBreakdown result={fraudResult} />

                {/* Fraud reports */}
                {reports.length > 0 && (
                  <div className="bg-red-50 border border-red-200 rounded-xl p-3.5">
                    <p className="text-xs font-bold text-red-700 flex items-center gap-1.5 mb-2">
                      <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                      </svg>
                      {reports.length} Fraud Report(s) on File
                    </p>
                    <ul className="space-y-1.5">
                      {reports.map((r, i) => (
                        <li key={i} className="text-xs text-red-700 bg-white rounded-lg px-3 py-2 border border-red-100">
                          {r.details} <span className="text-red-400">({r.courierName})</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
              </div>
            );
          })()}
        </div>
      )}

      {/* ── History Tab ── */}
      {activeTab === 'history' && (
        <div className="p-5 space-y-4">
          {histLoading && (
            <div className="flex items-center justify-center py-8 gap-3 text-gray-400">
              <svg className="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
              </svg>
              Loading order history…
            </div>
          )}

          {histError && (
            <div className="bg-red-50 border border-red-200 rounded-xl p-3.5 text-sm text-red-700 flex items-start gap-2">
              <svg className="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              {histError}
            </div>
          )}

          {histResult && (
            <>
              {/* Summary */}
              <div className="grid grid-cols-2 gap-3">
                {[
                  { label: 'Total Orders',  value: histResult.summary.total,        color: 'text-gray-800' },
                  {
                    label: 'Success Rate',
                    value: `${histResult.summary.success_rate}%`,
                    color: histResult.summary.success_rate >= 75 ? 'text-green-600'
                         : histResult.summary.success_rate >= 50 ? 'text-amber-600'
                         :                                         'text-red-600',
                  },
                  { label: 'Delivered', value: histResult.summary.delivered, color: 'text-green-600' },
                  { label: 'Cancelled', value: histResult.summary.cancelled, color: 'text-red-500'  },
                ].map(s => (
                  <div key={s.label} className="bg-gray-50 rounded-xl p-3 border border-gray-100 text-center">
                    <p className={`text-xl font-black ${s.color}`}>{s.value}</p>
                    <p className="text-xs text-gray-400 mt-0.5">{s.label}</p>
                  </div>
                ))}
              </div>

              {/* Table */}
              {histResult.orders.length === 0 ? (
                <p className="text-xs text-gray-400 text-center py-6">
                  No previous orders found for this phone number.
                </p>
              ) : (
                <div className="border border-gray-100 rounded-xl overflow-hidden">
                  <table className="w-full text-xs">
                    <thead>
                      <tr className="bg-gray-50 text-gray-400 uppercase tracking-wide font-semibold">
                        <th className="px-3.5 py-2.5 text-left">Order #</th>
                        <th className="px-3.5 py-2.5 text-right">Amount</th>
                        <th className="px-3.5 py-2.5 text-center">Status</th>
                        <th className="px-3.5 py-2.5 text-right">Date</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-50">
                      {histResult.orders.map(o => (
                        <tr
                          key={o.id}
                          className={`hover:bg-gray-50/50 transition-colors ${o.id === order.id ? 'bg-violet-50/40' : ''}`}
                        >
                          <td className="px-3.5 py-2.5">
                            <a
                              href={`/admin/orders/${o.id}`}
                              className={`font-semibold hover:underline ${o.id === order.id ? 'text-violet-600' : 'text-orange-500'}`}
                            >
                              {o.order_number}
                            </a>
                            {o.id === order.id && (
                              <span className="ml-1.5 text-[10px] bg-violet-100 text-violet-600 px-1.5 py-0.5 rounded-full font-medium">
                                current
                              </span>
                            )}
                          </td>
                          <td className="px-3.5 py-2.5 text-right font-semibold text-gray-800">
                            ৳{Number(o.total).toLocaleString()}
                          </td>
                          <td className="px-3.5 py-2.5 text-center">
                            <StatusBadge status={o.status} />
                          </td>
                          <td className="px-3.5 py-2.5 text-right text-gray-400">{o.date}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </>
          )}
        </div>
      )}
    </div>
  );
}

/* ─────────────────────────────────────────────────────────────────────────────
   Edit Customer Details Modal
   ───────────────────────────────────────────────────────────────────────────── */
function EditCustomerModal({ order, onClose }) {
  const { data, setData, patch, processing, errors } = useForm({
    customer_name: order.customer_name || '',
    customer_phone: order.customer_phone || '',
    customer_email: order.customer_email || '',
    shipping_address: order.shipping_address || '',
    city: order.city || '',
    postal_code: order.postal_code || '',
    shipping_zone: order.shipping_zone || 'inside_dhaka',
    delivery_note: order.delivery_note || '',
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    patch(`/admin/orders/${order.id}`, {
      preserveScroll: true,
      onSuccess: () => onClose(),
    });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/50 backdrop-blur-sm" onClick={onClose} />
      <div className="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl overflow-hidden animate-[fadeIn_.2s_ease]">
        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gray-50/60">
          <div>
            <h2 className="font-black text-gray-900 text-lg">Edit Customer Details</h2>
            <p className="text-xs text-gray-500 mt-0.5">Order #{order.order_number}</p>
          </div>
          <button onClick={onClose} className="p-2 rounded-xl hover:bg-gray-200 transition-colors text-gray-500">
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="sm:col-span-2">
              <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Customer Name *</label>
              <input
                type="text" required
                value={data.customer_name}
                onChange={e => setData('customer_name', e.target.value)}
                className="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500/30 focus:border-orange-400 outline-none"
              />
              {errors.customer_name && <p className="text-xs text-red-500 mt-1">{errors.customer_name}</p>}
            </div>

            <div>
              <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Phone Number *</label>
              <input
                type="text" required
                value={data.customer_phone}
                onChange={e => setData('customer_phone', e.target.value)}
                className="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500/30 focus:border-orange-400 outline-none"
              />
              {errors.customer_phone && <p className="text-xs text-red-500 mt-1">{errors.customer_phone}</p>}
            </div>

            <div>
              <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Email (Optional)</label>
              <input
                type="email"
                value={data.customer_email}
                onChange={e => setData('customer_email', e.target.value)}
                className="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500/30 focus:border-orange-400 outline-none"
              />
              {errors.customer_email && <p className="text-xs text-red-500 mt-1">{errors.customer_email}</p>}
            </div>

            <div className="sm:col-span-2">
              <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Delivery Address *</label>
              <textarea
                rows={2} required
                value={data.shipping_address}
                onChange={e => setData('shipping_address', e.target.value)}
                className="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500/30 focus:border-orange-400 outline-none resize-none"
              />
              {errors.shipping_address && <p className="text-xs text-red-500 mt-1">{errors.shipping_address}</p>}
            </div>

            <div>
              <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">City</label>
              <input
                type="text"
                value={data.city}
                onChange={e => setData('city', e.target.value)}
                className="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500/30 focus:border-orange-400 outline-none"
              />
              {errors.city && <p className="text-xs text-red-500 mt-1">{errors.city}</p>}
            </div>

            <div>
              <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Postal Code</label>
              <input
                type="text"
                value={data.postal_code}
                onChange={e => setData('postal_code', e.target.value)}
                className="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500/30 focus:border-orange-400 outline-none"
              />
              {errors.postal_code && <p className="text-xs text-red-500 mt-1">{errors.postal_code}</p>}
            </div>

            <div className="sm:col-span-2">
              <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Shipping Zone</label>
              <select
                value={data.shipping_zone}
                onChange={e => setData('shipping_zone', e.target.value)}
                className="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500/30 focus:border-orange-400 outline-none"
              >
                <option value="inside_dhaka">Inside Dhaka</option>
                <option value="outside_dhaka">Outside Dhaka</option>
              </select>
            </div>

            <div className="sm:col-span-2">
              <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Delivery Note / Special Instructions</label>
              <textarea
                rows={2}
                value={data.delivery_note}
                onChange={e => setData('delivery_note', e.target.value)}
                placeholder="Special delivery instructions from customer..."
                className="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500/30 focus:border-orange-400 outline-none resize-none"
              />
              {errors.delivery_note && <p className="text-xs text-red-500 mt-1">{errors.delivery_note}</p>}
            </div>
          </div>

          <div className="flex gap-3 pt-3 border-t border-gray-100">
            <button type="button" onClick={onClose}
              className="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
              Cancel
            </button>
            <button type="submit" disabled={processing}
              className="flex-1 py-2.5 rounded-xl bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white text-sm font-semibold transition-colors flex items-center justify-center gap-2">
              {processing ? 'Saving...' : 'Update Details'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

/* ─────────────────────────────────────────────────────────────────────────────
   Main Order Show page
   ───────────────────────────────────────────────────────────────────────────── */
export default function OrderShow({ order, bdcourier }) {
  const [showCourierModal, setShowCourierModal] = useState(false);
  const [showEditCustomerModal, setShowEditCustomerModal] = useState(false);
  const bdcourierRiskLevel = resolveRiskLevel(bdcourier?._risk_level, bdcourier?._risk_label);

  // Auto-open modal when navigated with ?ship=1
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('ship') === '1') {
      setShowCourierModal(true);
      window.history.replaceState({}, '', window.location.pathname);
    }
  }, []);

  const { data, setData, patch, processing } = useForm({
    status: order.status,
    payment_status: order.payment_status,
    internal_note: order.internal_note || '',
  });

  const submit = (e) => {
    e.preventDefault();
    patch(`/admin/orders/${order.id}`);
  };

  const handleVerify = () => router.post(`/admin/orders/${order.id}/verify`);
  const handleReject = () => { window.showConfirm('Reject payment?', () => router.post(`/admin/orders/${order.id}/reject`)); };

  const statuses = ['pending','confirmed','shipped','delivered','cancelled'];
  const paymentStatuses = ['pending','verified','rejected'];

  return (
    <>
      <Head title={`Order ${order.order_number}`} />
      <AdminLayout title={`Order ${order.order_number}`}>
        <div className="max-w-5xl space-y-5">
          {/* Header */}
          <div className="flex flex-wrap items-center gap-3">
            <a href="/admin/orders" className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
              Orders
            </a>
            <span className="text-gray-300">/</span>
            <span className="text-sm font-semibold text-gray-800">{order.order_number}</span>
            <div className="ml-auto flex items-center gap-2">
              <StatusBadge status={order.status} />
              <PaymentBadge status={order.payment_status} />
              <a
                href={`/admin/orders/${order.id}/invoice`}
                target="_blank"
                rel="noreferrer"
                className="ml-1 inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white hover:bg-gray-50 border border-gray-200 text-gray-700 text-xs font-semibold transition-colors shadow-sm"
              >
                <svg className="w-3.5 h-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Print Invoice
              </a>
              <button
                onClick={() => setShowCourierModal(true)}
                className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-orange-500 hover:bg-orange-600 text-white text-xs font-semibold transition-colors shadow-sm"
              >
                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12a2 2 0 002 2h8a2 2 0 002-2l1-12M10 12a1 1 0 100 2h4a1 1 0 100-2h-4z"/>
                </svg>
                Ship via Courier
              </button>
            </div>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
            {/* Left / main column */}
            <div className="lg:col-span-2 space-y-5">
              {/* Items table */}
              <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div className="p-5 border-b border-gray-50">
                  <h2 className="font-semibold text-gray-900">Items Ordered</h2>
                </div>
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="bg-gray-50/50 text-xs font-semibold text-gray-400 uppercase">
                        <th className="px-5 py-3 text-left">Product</th>
                        <th className="px-5 py-3 text-center">Qty</th>
                        <th className="px-5 py-3 text-right">Price</th>
                        <th className="px-5 py-3 text-right">Subtotal</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-50">
                      {(order.items || []).map(item => (
                        <tr key={item.id}>
                          <td className="px-5 py-3.5">
                            <p className="font-medium text-gray-800">{item.product_name}</p>
                            {item.variant_label && <p className="text-xs text-gray-400">{item.variant_label}</p>}
                          </td>
                          <td className="px-5 py-3.5 text-center text-gray-600">{item.quantity}</td>
                          <td className="px-5 py-3.5 text-right text-gray-600">৳{Number(item.unit_price).toLocaleString()}</td>
                          <td className="px-5 py-3.5 text-right font-semibold text-gray-900">৳{Number(item.subtotal).toLocaleString()}</td>
                        </tr>
                      ))}
                    </tbody>
                    <tfoot className="divide-y divide-gray-100 bg-gray-50/50">
                      <tr>
                        <td colSpan="3" className="px-5 py-2.5 text-right text-xs font-medium text-gray-500">Subtotal</td>
                        <td className="px-5 py-2.5 text-right text-xs font-semibold text-gray-800">৳{Number(order.subtotal || 0).toLocaleString()}</td>
                      </tr>
                      {Number(order.discount_amount || 0) > 0 && (
                        <tr>
                          <td colSpan="3" className="px-5 py-2.5 text-right text-xs font-medium text-green-600">
                            Discount {order.coupon_code ? `(${order.coupon_code})` : ''}
                          </td>
                          <td className="px-5 py-2.5 text-right text-xs font-bold text-green-600">-৳{Number(order.discount_amount).toLocaleString()}</td>
                        </tr>
                      )}
                      <tr>
                        <td colSpan="3" className="px-5 py-2.5 text-right text-xs font-medium text-gray-500">
                          Shipping ({order.shipping_zone === 'inside_dhaka' ? 'Inside Dhaka' : 'Outside Dhaka'})
                        </td>
                        <td className="px-5 py-2.5 text-right text-xs font-semibold text-gray-800">
                          {Number(order.shipping_charge || 0) === 0 ? <span className="text-green-600 font-bold">Free</span> : `৳${Number(order.shipping_charge).toLocaleString()}`}
                        </td>
                      </tr>
                      {Number(order.tax || 0) > 0 && (
                        <tr>
                          <td colSpan="3" className="px-5 py-2.5 text-right text-xs font-medium text-gray-500">Tax / VAT</td>
                          <td className="px-5 py-2.5 text-right text-xs font-semibold text-gray-800">৳{Number(order.tax).toLocaleString()}</td>
                        </tr>
                      )}
                      <tr className="border-t-2 border-gray-200 bg-orange-50/30">
                        <td colSpan="3" className="px-5 py-3.5 text-right font-bold text-gray-800 text-sm">Total Amount</td>
                        <td className="px-5 py-3.5 text-right font-black text-lg text-orange-600">৳{Number(order.total).toLocaleString()}</td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>

              {/* Update form */}
              <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h2 className="font-semibold text-gray-900 mb-4">Update Order</h2>
                <form onSubmit={submit} className="space-y-4">
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1.5">Order Status</label>
                      <select value={data.status} onChange={e => setData('status', e.target.value)}
                        className="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300 capitalize">
                        {statuses.map(s => <option key={s} value={s} className="capitalize">{s}</option>)}
                      </select>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1.5">Payment Status</label>
                      <select value={data.payment_status} onChange={e => setData('payment_status', e.target.value)}
                        className="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                        {paymentStatuses.map(s => <option key={s} value={s}>{s}</option>)}
                      </select>
                    </div>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1.5">Internal Note</label>
                    <textarea value={data.internal_note} onChange={e => setData('internal_note', e.target.value)} rows={3}
                      className="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300 resize-none"
                      placeholder="Private note (not visible to customer)…" />
                  </div>
                  <div className="flex items-center gap-3">
                    <button type="submit" disabled={processing}
                      className="px-5 py-2.5 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white text-sm font-semibold rounded-xl transition-colors">
                      {processing ? 'Saving…' : 'Save Changes'}
                    </button>
                    {order.payment_status === 'pending' && (
                      <>
                        <button type="button" onClick={handleVerify} className="px-5 py-2.5 bg-green-500 hover:bg-green-600 text-white text-sm font-semibold rounded-xl transition-colors">✓ Verify Payment</button>
                        <button type="button" onClick={handleReject} className="px-5 py-2.5 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-sm font-semibold rounded-xl transition-colors">✕ Reject Payment</button>
                      </>
                    )}
                  </div>
                </form>
              </div>

              {/* ── Fraud & History Panel ── */}
              {order.customer_phone && <FraudHistoryPanel order={order} />}
            </div>

            {/* Right column */}
            <div className="space-y-5">
              {/* Payment Details Card */}
              <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <div className="flex items-center justify-between mb-3.5">
                  <h3 className="font-semibold text-gray-900 text-sm flex items-center gap-2">
                    <svg className="w-4 h-4 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    Payment Details
                  </h3>
                  <PaymentBadge status={order.payment_status} />
                </div>
                <dl className="space-y-3 text-sm">
                  <div className="flex items-center justify-between">
                    <dt className="text-gray-400 text-xs">Payment Method</dt>
                    <dd className="font-bold text-gray-800 uppercase tracking-wide text-xs bg-gray-100 px-2 py-0.5 rounded">
                      {order.payment_method_label || order.payment_method}
                    </dd>
                  </div>

                  {order.payment_sender_number && (
                    <div className="flex items-center justify-between pt-2 border-t border-gray-50">
                      <dt className="text-gray-400 text-xs">Sender Phone</dt>
                      <dd className="font-mono font-bold text-gray-900 text-xs">
                        <a href={`tel:${order.payment_sender_number}`} className="hover:text-orange-600 hover:underline">
                          {order.payment_sender_number}
                        </a>
                      </dd>
                    </div>
                  )}

                  {order.payment_txn_id && (
                    <div className="pt-2 border-t border-gray-50">
                      <dt className="text-gray-400 text-xs mb-1">Transaction ID (TrxID)</dt>
                      <dd className="font-mono font-bold text-xs bg-amber-50 border border-amber-200 text-amber-900 px-2.5 py-1.5 rounded-lg select-all">
                        {order.payment_txn_id}
                      </dd>
                    </div>
                  )}

                  <div className="flex items-center justify-between pt-2 border-t border-gray-50">
                    <dt className="text-gray-400 text-xs">Total Amount</dt>
                    <dd className="font-black text-gray-900 text-base">
                      ৳{Number(order.total).toLocaleString()}
                    </dd>
                  </div>

                  {order.payment_status === 'pending' && (
                    <div className="pt-3 border-t border-gray-100 grid grid-cols-2 gap-2">
                      <button
                        type="button"
                        onClick={handleVerify}
                        className="w-full py-2 bg-green-500 hover:bg-green-600 text-white text-xs font-bold rounded-xl transition-colors text-center"
                      >
                        ✓ Verify
                      </button>
                      <button
                        type="button"
                        onClick={handleReject}
                        className="w-full py-2 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-xs font-bold rounded-xl transition-colors text-center"
                      >
                        ✕ Reject
                      </button>
                    </div>
                  )}
                </dl>
              </div>

              {/* Customer info */}
              <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <div className="flex items-center justify-between mb-4">
                  <h3 className="font-semibold text-gray-900 text-sm">Customer Details</h3>
                  <button
                    type="button"
                    onClick={() => setShowEditCustomerModal(true)}
                    className="inline-flex items-center gap-1 text-xs font-semibold text-orange-600 hover:text-orange-700 bg-orange-50 hover:bg-orange-100 px-2.5 py-1 rounded-lg transition-colors"
                  >
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    Edit
                  </button>
                </div>
                <dl className="space-y-3 text-sm">
                  <div><dt className="text-gray-400 text-xs mb-0.5">Name</dt><dd className="font-medium text-gray-800">{order.customer_name}</dd></div>
                  <div><dt className="text-gray-400 text-xs mb-0.5">Phone</dt><dd className="text-gray-700 font-mono">{order.customer_phone}</dd></div>
                  {order.customer_email && <div><dt className="text-gray-400 text-xs mb-0.5">Email</dt><dd className="text-gray-700">{order.customer_email}</dd></div>}
                  <div><dt className="text-gray-400 text-xs mb-0.5">Address</dt><dd className="text-gray-700">{order.shipping_address}</dd></div>
                  {order.city && <div><dt className="text-gray-400 text-xs mb-0.5">City / Zone</dt><dd className="text-gray-700">{order.city} {order.shipping_zone ? `(${order.shipping_zone.replace('_', ' ')})` : ''}</dd></div>}
                  {order.postal_code && <div><dt className="text-gray-400 text-xs mb-0.5">Postal Code</dt><dd className="text-gray-700">{order.postal_code}</dd></div>}
                  {order.delivery_note && (
                    <div className="pt-2.5 mt-2 border-t border-amber-200/80 bg-amber-50/70 rounded-xl p-3">
                      <dt className="text-amber-800 text-xs font-bold uppercase tracking-wider mb-1 flex items-center gap-1.5">
                        <svg className="w-3.5 h-3.5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2"><path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Customer Delivery Note (ডেলিভারি নোট)
                      </dt>
                      <dd className="text-sm font-semibold text-amber-950 whitespace-pre-wrap">{order.delivery_note}</dd>
                    </div>
                  )}
                  {order.ip_address && (
                    <div className="pt-2 mt-2 border-t border-gray-100">
                      <dt className="text-gray-400 text-xs mb-0.5">IP Address</dt>
                      <dd className="flex items-center gap-2 flex-wrap">
                        <span className="font-mono text-xs text-gray-600">{order.ip_address}</span>
                        <button
                          onClick={() => {
                            if (confirm('Block this IP address?')) {
                              router.post('/admin/blocked-ips', { ip_address: order.ip_address, reason: `Blocked from order #${order.order_number}` }, { preserveScroll: true });
                            }
                          }}
                          className="text-xs bg-red-50 text-red-600 hover:bg-red-100 px-2 py-1 rounded font-medium transition-colors"
                        >Block IP</button>
                        {order.user_agent && (
                          <button
                            onClick={() => {
                              if (confirm('Block this device fingerprint? This will prevent all future orders from this browser/device.')) {
                                router.post('/admin/blocked-devices', { user_agent: order.user_agent, reason: `Blocked from order #${order.order_number}` }, { preserveScroll: true });
                              }
                            }}
                            className="text-xs bg-purple-50 text-purple-600 hover:bg-purple-100 px-2 py-1 rounded font-medium transition-colors"
                          >Block Device</button>
                        )}
                      </dd>
                    </div>
                  )}
                  {order.customer_phone && (
                    <div className="pt-2 mt-2 border-t border-gray-100">
                      <dt className="text-gray-400 text-xs mb-1.5">Block Actions</dt>
                      <dd>
                        <button
                          onClick={() => {
                            if (confirm(`Block phone number ${order.customer_phone}? They won't be able to place any more orders.`)) {
                              router.post('/admin/blocked-phones', { phone: order.customer_phone, reason: `Blocked from order #${order.order_number}` }, { preserveScroll: true });
                            }
                          }}
                          className="text-xs bg-blue-50 text-blue-600 hover:bg-blue-100 px-2 py-1 rounded font-medium transition-colors"
                        >Block Phone ({order.customer_phone})</button>
                      </dd>
                    </div>
                  )}
                </dl>
              </div>

              {/* BD Courier Fraud Info (SSR-loaded) */}
              {bdcourier && (
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                  <div className="p-5 border-b border-gray-100 flex items-center justify-between">
                    <h3 className="font-semibold text-gray-900 text-sm flex items-center gap-2">
                      <svg className="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                        <path strokeLinecap="round" strokeLinejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 1h8zm0 0h2a1 1 0 001-1v-3.586a1 1 0 00-.293-.707l-3-3A1 1 0 0014 8h-1v8z" />
                      </svg>
                      BD Courier Report
                    </h3>
                    <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${
                      bdcourierRiskLevel === 'danger' ? 'bg-rose-100 text-rose-700' :
                      bdcourierRiskLevel === 'high'   ? 'bg-red-100 text-red-700'   :
                      bdcourierRiskLevel === 'medium' ? 'bg-amber-100 text-amber-700':
                      bdcourierRiskLevel === 'low'    ? 'bg-blue-100 text-blue-700'  :
                                                           'bg-green-100 text-green-700'
                    }`}>
                      {riskDisplayLabel(bdcourier._risk_level, bdcourier._risk_label)} Risk
                    </span>
                  </div>
                  <div className="p-5 space-y-4">
                    <div className="flex items-center gap-4">
                      <div className="w-14 h-14 rounded-full border-4 border-gray-50 flex items-center justify-center flex-shrink-0">
                        <span className={`font-bold text-sm ${riskRatioClass(bdcourierRiskLevel, bdcourier._success_ratio)}`}>
                          {bdcourier._success_ratio.toFixed(0)}%
                        </span>
                      </div>
                      <div>
                        <p className="text-2xl font-black text-gray-900">{bdcourier.data?.summary?.total_parcel || 0}</p>
                        <p className="text-xs text-gray-500">Total Parcels</p>
                      </div>
                      <div className="ml-auto text-right">
                        <p className="text-sm font-semibold text-green-600">{bdcourier.data?.summary?.success_parcel || 0} ✓</p>
                        <p className="text-sm font-semibold text-red-500">{bdcourier.data?.summary?.cancelled_parcel || 0} ✕</p>
                      </div>
                    </div>
                    <CourierBreakdown result={bdcourier} />
                    {bdcourier.reports && bdcourier.reports.length > 0 && (
                      <div className="bg-red-50 border border-red-200 rounded-lg p-3">
                        <p className="text-xs font-semibold text-red-700 flex items-center gap-1 mb-1">
                          <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                          </svg>
                          {bdcourier.reports.length} Fraud Report(s)
                        </p>
                        <ul className="text-xs text-red-600 space-y-1 pl-4 list-disc">
                          {bdcourier.reports.map((r, i) => <li key={i}>{r.details} ({r.courierName})</li>)}
                        </ul>
                      </div>
                    )}
                  </div>
                </div>
              )}

              {/* Payment info */}
              <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h3 className="font-semibold text-gray-900 mb-4 text-sm">Payment Details</h3>
                <dl className="space-y-3 text-sm">
                  <div><dt className="text-gray-400 text-xs mb-0.5">Method</dt><dd className="font-medium text-gray-800">{order.payment_method_label}</dd></div>
                  {order.payment_reference && <div><dt className="text-gray-400 text-xs mb-0.5">Reference / TxID</dt><dd className="text-gray-700 font-mono text-xs break-all">{order.payment_reference}</dd></div>}
                  <div><dt className="text-gray-400 text-xs mb-0.5">Status</dt><dd><PaymentBadge status={order.payment_status} /></dd></div>
                </dl>
              </div>

              {/* Order meta */}
              <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h3 className="font-semibold text-gray-900 mb-4 text-sm">Order Info</h3>
                <dl className="space-y-3 text-sm">
                  <div><dt className="text-gray-400 text-xs mb-0.5">Order Number</dt><dd className="font-mono font-semibold text-gray-800">{order.order_number}</dd></div>
                  <div><dt className="text-gray-400 text-xs mb-0.5">Placed</dt><dd className="text-gray-700">{order.created_at_formatted}</dd></div>
                  {order.note && <div><dt className="text-gray-400 text-xs mb-0.5">Customer Note</dt><dd className="text-gray-700 italic">{order.note}</dd></div>}
                </dl>
              </div>

              {/* Courier info */}
              {order.courier_provider && (
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                  <h3 className="font-semibold text-gray-900 mb-4 text-sm flex items-center gap-2">
                    <svg className="w-4 h-4 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12a2 2 0 002 2h8a2 2 0 002-2l1-12"/>
                    </svg>
                    Courier Info
                  </h3>
                  <dl className="space-y-3 text-sm">
                    <div><dt className="text-gray-400 text-xs mb-0.5">Provider</dt><dd className="font-medium text-gray-800 capitalize">{order.courier_provider}</dd></div>
                    {order.courier_tracking_code && <div><dt className="text-gray-400 text-xs mb-0.5">Tracking Code</dt><dd className="font-mono font-semibold text-orange-600 text-xs">{order.courier_tracking_code}</dd></div>}
                    {order.courier_consignment_id && <div><dt className="text-gray-400 text-xs mb-0.5">Consignment ID</dt><dd className="font-mono text-gray-700 text-xs">{order.courier_consignment_id}</dd></div>}
                    {order.courier_status && <div><dt className="text-gray-400 text-xs mb-0.5">Courier Status</dt><dd className="inline-block px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700 capitalize">{order.courier_status}</dd></div>}
                  </dl>
                </div>
              )}
            </div>
          </div>
        </div>
      </AdminLayout>

      {showCourierModal && (
        <CourierShipModal order={order} onClose={() => setShowCourierModal(false)} />
      )}

      {showEditCustomerModal && (
        <EditCustomerModal order={order} onClose={() => setShowEditCustomerModal(false)} />
      )}
    </>
  );
}
