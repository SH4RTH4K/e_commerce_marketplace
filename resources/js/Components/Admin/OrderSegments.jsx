import { orderSegments } from '@/lib/orderSegments';

export default function OrderSegments({ status = 'all', counts = {}, onChange, loading = false }) {
  const verification = orderSegments.at(-1);
  return <section className="rounded-2xl border border-gray-100 bg-white shadow-sm overflow-hidden">
    <nav aria-label="Order status segments" className="flex gap-1 overflow-x-auto px-3 pt-3 pb-2">
      {orderSegments.filter(segment => segment.key !== 'pending_verification').map(segment => <button
        key={segment.key} type="button" onClick={() => onChange(segment.key)} disabled={loading}
        aria-current={status === segment.key ? 'page' : undefined}
        className={`shrink-0 flex items-center gap-2 rounded-xl px-3 py-2.5 text-xs font-semibold transition disabled:opacity-50 ${status === segment.key ? 'bg-orange-500 text-white shadow-sm' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'}`}>
        {segment.label}<span className={`rounded-full px-2 py-0.5 text-[10px] tabular-nums ${status === segment.key ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500'}`}>{Number(counts[segment.key] || 0).toLocaleString()}</span>
      </button>)}
    </nav>
    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 bg-gray-50/50 px-4 py-3">
      <p className="text-[11px] text-gray-500">Status counts follow your search, payment method, and date filters.</p>
      <button type="button" onClick={() => onChange(verification.key)} disabled={loading}
        aria-current={status === verification.key ? 'page' : undefined}
        className={`inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold disabled:opacity-50 ${status === verification.key ? 'bg-orange-500 text-white' : 'bg-amber-50 text-amber-800 border border-amber-100 hover:bg-amber-100'}`}>
        {verification.label}<span className="rounded-full bg-white/70 px-2 py-0.5 text-[10px] text-amber-800 tabular-nums">{Number(counts[verification.key] || 0).toLocaleString()}</span>
      </button>
    </div>
  </section>;
}
