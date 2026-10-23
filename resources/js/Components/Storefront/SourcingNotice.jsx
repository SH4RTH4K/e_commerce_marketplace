import { sourcingMessage } from '@/lib/productAvailability';

export default function SourcingNotice({ awaiting = false }) {
  return <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
    <p className="font-semibold">{awaiting ? 'Awaiting sourcing' : 'Items available to order'}</p>
    <p className="mt-1 leading-relaxed">{awaiting ? 'Your order has been received. We are arranging the required items and will confirm availability and delivery timing before dispatch.' : sourcingMessage}</p>
  </div>;
}
