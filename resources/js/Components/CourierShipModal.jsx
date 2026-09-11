import { useState } from 'react';
import { router } from '@inertiajs/react';

const COURIERS = [
  { value: 'steadfast', label: 'Steadfast Courier', color: 'text-green-700' },
  { value: 'pathao',    label: 'Pathao Courier',    color: 'text-blue-700' },
  { value: 'redx',      label: 'RedX Courier',      color: 'text-red-700' },
];

function Spinner() {
  return (
    <svg className="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
    </svg>
  );
}

export default function CourierShipModal({ order, onClose }) {
  const [courier, setCourier]     = useState('steadfast');
  const [codAmount, setCodAmount] = useState(order.total ?? 0);
  const [weight, setWeight]       = useState('0.5');
  const [note, setNote]           = useState(order.note ?? '');
  const [loading, setLoading]     = useState(false);
  const [result, setResult]       = useState(null); // { success, message, tracking_code }

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setResult(null);

    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

      const res = await fetch(`/admin/orders/${order.id}/send-to-courier`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          courier_provider: courier,
          cod_amount:       parseFloat(codAmount),
          item_weight:      parseFloat(weight),
          note:             note,
        }),
      });

      const data = await res.json();

      if (res.ok && data.success) {
        setResult({ success: true, message: data.message, tracking_code: data.tracking_code });
        // Reload the page after a short delay so order status & courier info refresh
        setTimeout(() => { router.reload({ only: ['order'] }); }, 2000);
      } else {
        setResult({ success: false, message: data.message ?? 'An error occurred. Please check your courier API credentials.' });
      }
    } catch (err) {
      setResult({ success: false, message: err.message ?? 'Network error. Please try again.' });
    } finally {
      setLoading(false);
    }
  };

  const selectedCourier = COURIERS.find(c => c.value === courier);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      {/* Backdrop */}
      <div className="absolute inset-0 bg-black/50 backdrop-blur-sm" onClick={onClose} />

      {/* Modal */}
      <div className="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl overflow-hidden animate-[fadeIn_.2s_ease]">

        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gray-50/60">
          <div>
            <h2 className="font-black text-gray-900 text-lg">Ship via Courier</h2>
            <p className="text-xs text-gray-500 mt-0.5">Order #{order.order_number}</p>
          </div>
          <button onClick={onClose} className="p-2 rounded-xl hover:bg-gray-200 transition-colors text-gray-500">
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-4">

          {/* Courier Selection */}
          <div>
            <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Courier Provider</label>
            <div className="grid grid-cols-3 gap-2">
              {COURIERS.map(c => (
                <button key={c.value} type="button"
                  onClick={() => setCourier(c.value)}
                  className={`p-3 rounded-xl border-2 text-sm font-semibold transition-all ${
                    courier === c.value
                      ? 'border-orange-500 bg-orange-50 text-orange-700'
                      : 'border-gray-200 text-gray-600 hover:border-gray-300'
                  }`}>
                  {c.label}
                </button>
              ))}
            </div>
          </div>

          {/* Customer Info — Read-only preview */}
          <div className="bg-gray-50 rounded-xl p-4 space-y-2 text-sm">
            <p className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Delivery Details</p>
            <div className="grid grid-cols-2 gap-x-4 gap-y-1 text-gray-700">
              <div><span className="text-gray-400 text-xs">Name: </span>{order.customer_name}</div>
              <div><span className="text-gray-400 text-xs">Phone: </span>{order.customer_phone}</div>
              <div className="col-span-2"><span className="text-gray-400 text-xs">Address: </span>{order.shipping_address}, {order.city}</div>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            {/* COD Amount */}
            <div>
              <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">COD Amount (৳)</label>
              <input
                type="number" min="0" step="0.01" required
                value={codAmount}
                onChange={e => setCodAmount(e.target.value)}
                className="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500/30 focus:border-orange-400 outline-none"
              />
            </div>

            {/* Item Weight */}
            <div>
              <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Item Weight (kg)</label>
              <input
                type="number" min="0.1" step="0.1"
                value={weight}
                onChange={e => setWeight(e.target.value)}
                className="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500/30 focus:border-orange-400 outline-none"
              />
            </div>
          </div>

          {/* Delivery Note */}
          <div>
            <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Delivery Note (optional)</label>
            <textarea
              rows={2}
              value={note}
              onChange={e => setNote(e.target.value)}
              placeholder="e.g. Call before delivery"
              className="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500/30 focus:border-orange-400 outline-none resize-none"
            />
          </div>

          {/* Result Toast */}
          {result && (
            <div className={`rounded-xl p-4 text-sm font-medium flex items-start gap-3 ${
              result.success ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'
            }`}>
              <div className="shrink-0 mt-0.5">
                {result.success
                  ? <svg className="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"/></svg>
                  : <svg className="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                }
              </div>
              <div>
                <p>{result.message}</p>
                {result.tracking_code && (
                  <p className="mt-1 font-mono text-xs bg-white/60 inline-block px-2 py-0.5 rounded">
                    Parcel ID: {result.tracking_code}
                  </p>
                )}
              </div>
            </div>
          )}

          {/* Actions */}
          <div className="flex gap-3 pt-1">
            <button type="button" onClick={onClose}
              className="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
              Cancel
            </button>
            <button type="submit" disabled={loading || result?.success}
              className="flex-1 py-2.5 rounded-xl bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white text-sm font-semibold transition-colors flex items-center justify-center gap-2">
              {loading ? <><Spinner /> Shipping...</> : `Ship via ${selectedCourier?.label}`}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
