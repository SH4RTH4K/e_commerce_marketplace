import { money } from '@/lib/utils';

export function pricingValues(product, pricing = { mode: 'manual' }) {
  return {
    regular_price: product.regular_price ?? '',
    sale_price: product.sale_price ?? '',
    selling_price: product.selling_price ?? pricing.suggested?.selling_price ?? product.regular_price ?? '',
    minimum_price: product.minimum_price ?? pricing.suggested?.minimum_price ?? '',
    maximum_price: product.maximum_price ?? pricing.suggested?.maximum_price ?? '',
    enforce_minimum_price: product.minimum_price != null ? product.enforce_minimum_price : (pricing.suggested?.enforce_minimum_price ?? false),
    enforce_maximum_price: product.maximum_price != null ? product.enforce_maximum_price : (pricing.suggested?.enforce_maximum_price ?? false),
    protect_minimum_price: product.minimum_price != null ? product.protect_minimum_price : (pricing.suggested?.protect_minimum_price ?? true),
    ...(pricing.mode === 'auto' && pricing.suggested ? pricing.suggested : {}),
    pricing_mode: pricing.mode ?? 'manual',
  };
}

export function pricingConflicts(data) {
  const minimum = data.enforce_minimum_price && data.minimum_price !== '' && data.minimum_price != null ? Number(data.minimum_price) : null;
  const maximum = data.enforce_maximum_price && data.maximum_price !== '' && data.maximum_price != null ? Number(data.maximum_price) : null;
  const selling = Number(data.selling_price === '' || data.selling_price == null ? data.regular_price : data.selling_price);
  const regular = Number(data.regular_price);
  const customer = data.sale_price !== '' && data.sale_price != null ? Number(data.sale_price) : regular;
  const conflicts = [];
  if (data.enforce_minimum_price && minimum === null) conflicts.push('Enter a minimum price or disable its cap.');
  if (data.enforce_maximum_price && maximum === null) conflicts.push('Enter a maximum price or disable its cap.');
  if (minimum !== null && maximum !== null && minimum > maximum) conflicts.push('Minimum exceeds maximum.');
  if (minimum !== null && selling < minimum) conflicts.push('Selling price is below minimum.');
  if (maximum !== null && selling > maximum) conflicts.push('Selling price exceeds maximum.');
  if (customer > regular) conflicts.push('Discounted price exceeds Regular Price.');
  if (minimum !== null && data.protect_minimum_price && customer < minimum) conflicts.push('Customer price is below the protected minimum.');
  if (maximum !== null && customer > maximum) conflicts.push('Customer price exceeds maximum.');
  return conflicts;
}

const fields = [
  ['selling_price', 'Selling Price', 'Selling target after enabled caps.'],
  ['sale_price', 'Discounted Price', 'Customer price. Leave blank to use Regular Price.'],
  ['minimum_price', 'Minimum Price', 'Lower limit when its cap is enabled.'],
  ['maximum_price', 'Maximum Price', 'Upper limit when its cap is enabled.'],
  ['regular_price', 'Regular Price', 'Reference price shown before discount.'],
];

export default function ProductPricingFields({ data, setData, errors = {}, pricing = { linked: false } }) {
  const automatic = data.pricing_mode === 'auto';
  const conflicts = automatic ? (pricing.suggested ? [] : ['Supplier pricing is unavailable or conflicting. Choose a product override.']) : pricingConflicts(data);
  const customerPrice = data.sale_price !== '' && data.sale_price != null ? Number(data.sale_price) : Number(data.regular_price);
  const chooseMode = mode => setData(current => ({ ...current, ...(mode === 'auto' && pricing.suggested ? pricing.suggested : {}), pricing_mode: mode }));

  return (
    <div className="space-y-4">
      {pricing.linked && (
        <div className="rounded-xl border border-blue-100 bg-blue-50/40 p-4 space-y-3">
          <p className="text-sm text-gray-700"><strong>{pricing.supplier}</strong> · Supplier cost: {money(pricing.cost)} · Supplier maximum: {money(pricing.supplier_maximum)}</p>
          <label htmlFor="product-pricing-mode" className="block text-sm font-medium text-gray-700">Pricing mode</label>
          <select id="product-pricing-mode" value={data.pricing_mode} onChange={event => chooseMode(event.target.value)} className="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm">
            <option value="auto" disabled={!pricing.suggested}>Use supplier rules</option>
            <option value="manual">Override for this product</option>
          </select>
          <p className="text-xs text-gray-600">{automatic ? 'Prices are calculated from the current supplier rules and refreshed during price sync.' : 'These prices apply only to this product and are kept during supplier price sync.'}</p>
          {automatic && <p className="text-xs text-gray-500">Regular Price source: {String(pricing.regular_source || 'capped_selling').replaceAll('_', ' ')}. Raw Selling Price: {money(pricing.raw_selling)}. <a href="/admin/dropshipping/pricing" target="_blank" rel="noopener noreferrer" className="text-orange-600 underline">View supplier rules</a></p>}
          {!pricing.suggested && pricing.reason && <p className="text-xs text-red-600">{String(pricing.reason).replaceAll('_', ' ')}</p>}
        </div>
      )}
      {errors.pricing_mode && <p className="text-xs text-red-600" role="alert">{errors.pricing_mode}</p>}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {fields.map(([key, label, hint]) => (
          <div key={key}>
            <label htmlFor={`product-${key}`} className="block text-sm font-medium text-gray-700 mb-1.5">{label} (৳)</label>
            <input id={`product-${key}`} type="number" min="0" max="9999999999.99" step="0.01" value={data[key] ?? ''}
              readOnly={automatic} onChange={event => setData(key, event.target.value)} onFocus={event => event.target.select()}
              required={key === 'regular_price'} aria-invalid={!!errors[key]} aria-describedby={`product-${key}-help`}
              className={`w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300 ${automatic ? 'bg-gray-50 text-gray-600' : ''}`} />
            <p id={`product-${key}-help`} className="text-xs text-gray-500 mt-1">{hint}</p>
            {errors[key] && <p className="text-xs text-red-600 mt-1" role="alert">{errors[key]}</p>}
          </div>
        ))}
      </div>
      <div className="flex flex-wrap gap-4 text-xs text-gray-700">
        {[
          ['enforce_minimum_price', 'Enforce minimum cap'],
          ['enforce_maximum_price', 'Enforce maximum cap'],
          ['protect_minimum_price', 'Protect minimum during discount'],
        ].map(([key, label]) => <label key={key} className="flex items-center gap-2"><input type="checkbox" checked={!!data[key]} disabled={automatic} onChange={event => setData(key, event.target.checked)} />{label}</label>)}
      </div>
      <div className={`rounded-xl p-4 border ${conflicts.length ? 'bg-red-50 border-red-200' : 'bg-green-50 border-green-100'}`} aria-live="polite">
        <p className="text-sm font-semibold text-gray-800">Customer price: {money(customerPrice)}</p>
        {pricing.cost != null && <p className="text-xs text-gray-600 mt-1">Profit before delivery and other costs: {money(customerPrice - Number(pricing.cost))}</p>}
        {conflicts.map(message => <p key={message} className="text-xs text-red-700 mt-1">{message}</p>)}
        {!conflicts.length && <p className="text-xs text-green-700 mt-1">Pricing is valid. Save the product to apply it.</p>}
      </div>
    </div>
  );
}
