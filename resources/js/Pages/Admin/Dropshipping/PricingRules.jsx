import DropshippingSubpage from './Subpage';
import { router } from '@inertiajs/react';
import { useState } from 'react';

const input = 'mt-1 w-full border border-gray-200 rounded-lg px-2.5 py-2 text-sm bg-white';
const money = value => value === null || value === undefined || value === '' ? 'BDT 0.00' : `BDT ${Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const adjustments = { none: 'No Adjustment', plus_fixed: 'Plus Fixed Amount', minus_fixed: 'Minus Fixed Amount', plus_percent: 'Plus Percentage', minus_percent: 'Minus Percentage' };
const roundingModes = [['none', 'None'], ['nearest', 'Nearest'], ['up', 'Round Up'], ['down', 'Round Down']];
const regularPriceSources = [
  ['capped_selling', 'Selling Price after enabled caps'],
  ['raw_selling', 'Selling Price before caps'],
  ['minimum', 'Minimum Price'],
  ['maximum', 'Maximum Price'],
  ['supplier_cost', 'Supplier Cost Price'],
  ['supplier_maximum', 'Supplier Maximum Price'],
];

function rounding(value) { return { mode: value?.mode || 'none', increment: value?.increment ?? 1 }; }
function SavedBadge({ saved }) { return <span className={`ml-2 inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${saved ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'}`}>{saved ? 'Saved' : 'Not saved'}</span>; }
function defaults(rules = {}) {
  return {
    selling: { base: rules.selling?.base || 'supplier_cost', adjustment_type: rules.selling?.adjustment_type || 'plus_percent', adjustment_value: rules.selling?.adjustment_value ?? 0, rounding: rounding(rules.selling?.rounding || rules.rounding) },
    minimum: { base: rules.minimum?.base || 'supplier_cost', adjustment_type: rules.minimum?.adjustment_type || 'plus_percent', adjustment_value: rules.minimum?.adjustment_value ?? 0, cap_enabled: rules.minimum?.cap_enabled ?? false, rounding: rounding(rules.minimum?.rounding) },
    maximum: { base: rules.maximum?.base || 'supplier_maximum', adjustment_type: rules.maximum?.adjustment_type || 'none', adjustment_value: rules.maximum?.adjustment_value ?? 0, cap_enabled: rules.maximum?.cap_enabled ?? false, rounding: rounding(rules.maximum?.rounding) },
    regular_price_source: rules.regular_price_source || 'capped_selling',
    discount: { type: rules.discount?.type || 'fixed', value: rules.discount?.value ?? 0, protect_minimum: rules.discount?.protect_minimum ?? true, rounding: rounding(rules.discount_rounding) },
  };
}

function Rounding({ rule, onChange }) {
  return <div className="rounded-lg border border-gray-200 p-3"><p className="text-xs font-semibold text-gray-700">Rounding</p><div className="grid grid-cols-2 gap-2 mt-2"><label className="text-xs text-gray-600">Mode<select value={rule.mode} onChange={event => onChange({ ...rule, mode: event.target.value })} className={input}>{roundingModes.map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></label><label className="text-xs text-gray-600">Increment<input type="number" min="0.01" step="0.01" value={rule.increment} onChange={event => onChange({ ...rule, increment: event.target.value })} className={input} /></label></div></div>;
}

function FormulaCard({ title, rule, setRule, capLabel, showCap = false }) {
  return <div className="rounded-xl border border-gray-200 p-4 space-y-3"><div><h3 className="font-semibold text-gray-900">{title}</h3><p className="text-xs text-gray-500">Formula and rounding are independent for this price.</p></div><label className="block text-xs text-gray-600">Base<select value={rule.base} onChange={event => setRule({ ...rule, base: event.target.value })} className={input}><option value="supplier_cost">Supplier Cost Price</option><option value="supplier_maximum">Supplier Maximum Price</option></select></label><label className="block text-xs text-gray-600">Adjustment<select value={rule.adjustment_type} onChange={event => setRule({ ...rule, adjustment_type: event.target.value })} className={input}>{Object.entries(adjustments).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></label><label className="block text-xs text-gray-600">Amount / Percentage<input type="number" min="0" step="0.01" value={rule.adjustment_value} onChange={event => setRule({ ...rule, adjustment_value: event.target.value })} className={input} /></label>{showCap && <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" checked={Boolean(rule.cap_enabled)} onChange={event => setRule({ ...rule, cap_enabled: event.target.checked })} /> {capLabel}</label>}<Rounding rule={rule.rounding} onChange={roundingRule => setRule({ ...rule, rounding: roundingRule })} /></div>;
}

function SupplierPricingCard({ supplier }) {
  const [mapping, setMapping] = useState({ cost_field: supplier.price_field_mapping?.cost_field || 'sale_price', maximum_field: supplier.price_field_mapping?.maximum_field || 'price' });
  const [rules, setRules] = useState(defaults(supplier.pricing_rules));
  const mappingSaved = Boolean(supplier.price_field_mapping?.cost_field && supplier.price_field_mapping?.maximum_field);
  const rulesSaved = Boolean(supplier.pricing_rules?.selling && supplier.pricing_rules?.minimum && supplier.pricing_rules?.maximum);
  const [preview, setPreview] = useState(null);
  const [previewValues, setPreviewValues] = useState({ cost: supplier.sample_cost ?? '', maximum: supplier.sample_maximum ?? '' });
  const fields = supplier.price_field_options || ['sale_price', 'price'];
  const update = (name, value) => setRules(current => ({ ...current, [name]: value }));
  const saveMapping = event => { event.preventDefault(); router.patch(`/admin/dropshipping/suppliers/${supplier.id}/price-mapping`, mapping, { preserveScroll: true }); };
  const saveRules = event => {
    event.preventDefault();
    router.patch(`/admin/dropshipping/suppliers/${supplier.id}/pricing`, {
      selling_base: rules.selling.base, selling_adjustment_type: rules.selling.adjustment_type, selling_adjustment_value: rules.selling.adjustment_value, selling_rounding_mode: rules.selling.rounding.mode, selling_rounding_increment: rules.selling.rounding.increment,
      minimum_base: rules.minimum.base, minimum_adjustment_type: rules.minimum.adjustment_type, minimum_adjustment_value: rules.minimum.adjustment_value, minimum_rounding_mode: rules.minimum.rounding.mode, minimum_rounding_increment: rules.minimum.rounding.increment, minimum_cap_enabled: rules.minimum.cap_enabled ? 1 : 0,
      maximum_base: rules.maximum.base, maximum_adjustment_type: rules.maximum.adjustment_type, maximum_adjustment_value: rules.maximum.adjustment_value, maximum_rounding_mode: rules.maximum.rounding.mode, maximum_rounding_increment: rules.maximum.rounding.increment, maximum_cap_enabled: rules.maximum.cap_enabled ? 1 : 0,
      regular_price_source: rules.regular_price_source,
      discount_type: rules.discount.type, discount_value: rules.discount.value, protect_minimum: rules.discount.protect_minimum ? 1 : 0, discount_rounding_mode: rules.discount.rounding.mode, discount_rounding_increment: rules.discount.rounding.increment,
    }, { preserveScroll: true });
  };
  const loadPreview = async () => { const token = document.querySelector('meta[name="csrf-token"]')?.content; const response = await fetch(`/admin/dropshipping/suppliers/${supplier.id}/pricing/preview`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token || '' }, body: JSON.stringify({ cost: previewValues.cost || null, maximum: previewValues.maximum || null, rules }) }); setPreview(await response.json()); };
  const regularPreviewPrice = preview?.regularSellingPrice ?? preview?.finalPrice ?? null;
  const discountPreviewAmount = preview?.discountAmount ?? null;
  const requestedDiscountPreviewAmount = preview?.requestedDiscountAmount ?? discountPreviewAmount;
  const priceAfterDiscount = preview?.priceAfterDiscount ?? preview?.finalSalePrice ?? null;
  const minimumProtectedDiscount = preview && priceAfterDiscount !== preview.finalSalePrice;
  const priceAfterMinimumCap = preview?.priceAfterMinimumCap ?? preview?.rawSellingPrice ?? null;
  const minimumCapRaisedPrice = preview && preview.minimumCapEnabled && preview.rawSellingPrice < preview.calculatedMinimumPrice;
  const maximumCapLoweredPrice = preview && preview.maximumCapEnabled && priceAfterMinimumCap > preview.calculatedMaximumPrice;

  return <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-5">
    <div><h2 className="font-bold text-gray-900">{supplier.name}</h2><p className="text-xs text-gray-400 font-mono mt-1">{supplier.key}</p></div>
    <form onSubmit={saveMapping} className="rounded-xl border border-orange-200 bg-orange-50/40 p-4"><h3 className="font-semibold text-gray-900">1. Supplier price field mapping <SavedBadge saved={mappingSaved} /></h3><p className="text-xs text-gray-600 mt-1 mb-3">Choose which API fields contain cost and maximum price.</p><div className="grid grid-cols-1 md:grid-cols-2 gap-3"><label className="text-xs text-gray-700">Supplier Cost Price Field<select value={mapping.cost_field} onChange={event => setMapping({ ...mapping, cost_field: event.target.value })} className={input}>{fields.map(field => <option key={field} value={field}>{field}</option>)}</select></label><label className="text-xs text-gray-700">Supplier Maximum Price Field<select value={mapping.maximum_field} onChange={event => setMapping({ ...mapping, maximum_field: event.target.value })} className={input}>{fields.map(field => <option key={field} value={field}>{field}</option>)}</select></label></div><button className="mt-3 px-3 py-2 rounded-lg bg-gray-900 text-xs font-semibold text-white">Save price mapping</button></form>
    <form onSubmit={saveRules} className="space-y-4"><h3 className="font-semibold text-gray-900">2. Pricing rules <SavedBadge saved={rulesSaved} /></h3><div className="grid grid-cols-1 md:grid-cols-3 gap-3"><FormulaCard title="Selling Price" rule={rules.selling} setRule={rule => update('selling', rule)} /><FormulaCard title="Minimum Price" rule={rules.minimum} setRule={rule => update('minimum', rule)} capLabel="Enforce minimum cap" showCap /><FormulaCard title="Maximum Price" rule={rules.maximum} setRule={rule => update('maximum', rule)} capLabel="Enforce maximum cap" showCap /></div><div className="rounded-xl border border-gray-200 p-4 space-y-3"><div><h3 className="font-semibold text-gray-900">Regular Price</h3><p className="text-xs text-gray-500 mt-1">Choose which calculated amount is saved as the product regular price.</p></div><label className="block text-xs text-gray-600">Regular price source<select value={rules.regular_price_source} onChange={event => update('regular_price_source', event.target.value)} className={input}>{regularPriceSources.map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></label></div><div className="rounded-xl border border-gray-200 p-4 space-y-3"><div><h3 className="font-semibold text-gray-900">Discounted Price</h3><p className="text-xs text-gray-500 mt-1">Discount is calculated from the selected Regular Price.</p></div><div className="grid grid-cols-1 md:grid-cols-3 gap-3"><label className="text-xs text-gray-600">Discount type<select value={rules.discount.type} onChange={event => update('discount', { ...rules.discount, type: event.target.value })} className={input}><option value="fixed">Fixed Amount</option><option value="percent">Percentage</option></select></label><label className="text-xs text-gray-600">Discount value<input type="number" min="0" step="0.01" value={rules.discount.value} onChange={event => update('discount', { ...rules.discount, value: event.target.value })} className={input} /></label><label className="flex items-center gap-2 text-sm text-gray-700 pt-5"><input type="checkbox" checked={Boolean(rules.discount.protect_minimum)} onChange={event => update('discount', { ...rules.discount, protect_minimum: event.target.checked })} /> Protect minimum during discount</label></div><Rounding rule={rules.discount.rounding} onChange={roundingRule => update('discount', { ...rules.discount, rounding: roundingRule })} /></div><button className="px-4 py-2.5 rounded-xl bg-orange-500 text-sm font-semibold text-white">Save pricing configuration</button></form>
    <div className="rounded-xl border border-blue-200 bg-blue-50/50 p-4">
      <div className="flex items-center justify-between mb-3"><div><h3 className="font-semibold text-gray-900">3. Price preview</h3><p className="text-xs text-gray-600">Preview uses the same backend PricingEngine used during import and sync.</p></div><button type="button" onClick={loadPreview} className="px-3 py-2 rounded-lg bg-blue-700 text-xs font-semibold text-white">Calculate preview</button></div>
      <div className="grid grid-cols-2 gap-3 mb-3"><label className="text-xs text-gray-600">Sample supplier cost<input type="number" min="0" step="0.01" value={previewValues.cost} onChange={event => setPreviewValues({ ...previewValues, cost: event.target.value })} className={input} placeholder="e.g. 490" /></label><label className="text-xs text-gray-600">Sample supplier maximum<input type="number" min="0" step="0.01" value={previewValues.maximum} onChange={event => setPreviewValues({ ...previewValues, maximum: event.target.value })} className={input} placeholder="e.g. 690" /></label></div>
      {preview ? <div className="space-y-2 text-xs">
        <div className="grid grid-cols-2 md:grid-cols-6 gap-2">
          <div className="bg-white rounded-lg p-3"><span className="text-gray-500">Raw selling</span><strong className="block text-gray-900">{money(preview.rawSellingPrice)}</strong></div>
          <div className="bg-white rounded-lg p-3"><span className="text-gray-500">Minimum</span><strong className="block text-gray-900">{money(preview.calculatedMinimumPrice)}</strong></div>
          <div className="bg-white rounded-lg p-3"><span className="text-gray-500">Maximum</span><strong className="block text-gray-900">{money(preview.calculatedMaximumPrice)}</strong></div>
          <div className="bg-white rounded-lg p-3"><span className="text-gray-500">Regular price</span><strong className="block text-gray-900">{money(regularPreviewPrice)}</strong></div>
          <div className="bg-white rounded-lg p-3"><span className="text-gray-500">Requested discount</span><strong className="block text-red-700">-{money(requestedDiscountPreviewAmount)}</strong></div>
          <div className="bg-white rounded-lg p-3"><span className="text-gray-500">Sale price</span><strong className="block text-gray-900">{money(preview.finalSalePrice)}</strong></div>
        </div>
        <div className="bg-white rounded-lg p-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"><span className="text-gray-600">Regular price source</span><strong className="text-gray-900">{regularPriceSources.find(([key]) => key === (preview.regularPriceSource || rules.regular_price_source))?.[1] || 'Selling Price after enabled caps'} = {money(regularPreviewPrice)}</strong></div>
        {minimumCapRaisedPrice && <div className="bg-white rounded-lg p-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"><span className="text-gray-600">Minimum cap</span><strong className="text-gray-900">{money(preview.rawSellingPrice)} raised to {money(preview.calculatedMinimumPrice)} minimum</strong></div>}
        {maximumCapLoweredPrice && <div className="bg-white rounded-lg p-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"><span className="text-gray-600">Maximum cap</span><strong className="text-gray-900">{money(priceAfterMinimumCap)} lowered to {money(preview.calculatedMaximumPrice)} maximum</strong></div>}
        <div className="bg-white rounded-lg p-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"><span className="text-gray-600">Discount calculation</span><strong className="text-gray-900">{money(regularPreviewPrice)} - {money(requestedDiscountPreviewAmount)} = {money(priceAfterDiscount)}</strong></div>
        {minimumProtectedDiscount && <div className="bg-white rounded-lg p-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"><span className="text-gray-600">Minimum protection</span><strong className="text-gray-900">{money(priceAfterDiscount)} raised to {money(preview.finalSalePrice)} minimum</strong></div>}
        <div className="bg-white rounded-lg p-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"><span className="text-gray-600">Applied discount</span><strong className="text-gray-900">{money(regularPreviewPrice)} - {money(preview.finalSalePrice)} = {money(discountPreviewAmount)}</strong></div>
        <div className="font-semibold">Status: <span className={preview.status === 'valid' ? 'text-green-700' : 'text-red-700'}>{preview.status}{preview.reason ? ` - ${preview.reason}` : ''}</span></div>
      </div> : <p className="text-xs text-gray-500">Enter sample values and click Calculate preview.</p>}
    </div>
  </div>;
}

export default function PricingRules({ suppliers = [] }) {
  return <DropshippingSubpage title="Pricing Rules" description="Configure independent formulas and separate rounding for Selling, Minimum, Maximum, Regular, and Discounted prices."><div className="bg-blue-50 border border-blue-200 rounded-2xl p-4 text-sm text-blue-800">Selling, Minimum, and Maximum can each choose Supplier Cost Price or Supplier Maximum Price as their base. Regular Price is selected by the admin from the calculated amounts. Discounted Price uses the selected Regular Price.</div><div className="space-y-5">{suppliers.length === 0 ? <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 text-sm text-gray-400">No suppliers configured.</div> : suppliers.map(supplier => <SupplierPricingCard key={supplier.id} supplier={supplier} />)}</div></DropshippingSubpage>;
}
