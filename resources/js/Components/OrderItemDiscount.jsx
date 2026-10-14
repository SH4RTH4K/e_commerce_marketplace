import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { money } from '@/lib/utils';

export default function OrderItemDiscount({ orderId, item }) {
  const form = useForm({ unit_discount: item.unit_discount ?? 0 });
  useEffect(() => { form.setData('unit_discount', item.unit_discount ?? 0); }, [item.unit_discount]);
  const amount = Number(form.data.unit_discount);
  const valid = form.data.unit_discount !== '' && Number.isFinite(amount) && amount >= 0 && amount <= Number(item.unit_price);
  const changed = amount !== Number(item.unit_discount || 0);
  const lineTotal = Math.round((Number(item.unit_price) - amount) * Number(item.quantity) * 100) / 100;
  const inputId = `item-discount-${item.id}`;

  const submit = event => {
    event.preventDefault();
    form.patch(`/admin/orders/${orderId}/items/${item.id}/discount`, { preserveScroll: true });
  };

  return (
    <form onSubmit={submit} className="mt-3 space-y-1.5">
      <label htmlFor={inputId} className="block text-xs font-medium text-gray-600">Discount per unit (৳)</label>
      <div className="flex items-center gap-2">
        <input id={inputId} type="number" min="0" max={item.unit_price} step="0.01" required
          value={form.data.unit_discount} onChange={event => form.setData('unit_discount', event.target.value)}
          disabled={form.processing} aria-invalid={!!form.errors.unit_discount} aria-describedby={`${inputId}-help ${inputId}-error`}
          className="w-24 border border-gray-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300" />
        <button type="submit" disabled={form.processing || !valid || !changed}
          className="px-3 py-1.5 bg-orange-500 hover:bg-orange-600 disabled:opacity-50 text-white text-xs font-semibold rounded-lg">
          {form.processing ? 'Saving…' : 'Save'}
        </button>
      </div>
      <p id={`${inputId}-help`} className="text-xs text-gray-500">Enter 0 to remove.{valid && changed && ` Product total: ${money(lineTotal)}`}</p>
      <p id={`${inputId}-error`} className="text-xs text-red-600" role="alert">{form.errors.unit_discount}</p>
      {form.recentlySuccessful && <p className="text-xs text-green-600" role="status">Product discount saved.</p>}
    </form>
  );
}
