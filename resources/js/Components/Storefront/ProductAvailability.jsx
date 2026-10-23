import { productAvailability, requiresSourcing, sourcingMessage } from '@/lib/productAvailability';

export default function ProductAvailability({ product, showMessage = false, quantity = 1, className = '' }) {
  const { stock, canOrder, hideQuantity, label } = productAvailability(product);
  return <div className={className}>
    <p className={`text-sm font-semibold ${canOrder ? 'text-green-700' : 'text-red-600'}`}>
      {label}{stock > 0 && !hideQuantity ? `: ${stock.toLocaleString()}` : ''}
    </p>
    {showMessage && requiresSourcing(product, quantity) && <p className="mt-2 text-xs leading-relaxed text-gray-600">{sourcingMessage}</p>}
  </div>;
}
