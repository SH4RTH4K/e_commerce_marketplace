import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import vm from 'node:vm';
import React, { act, useState } from 'react';
import { create } from 'react-test-renderer';
import { transformWithOxc } from 'vite';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
const filename = new URL('../../resources/js/Components/Admin/ProductPricingFields.jsx', import.meta.url);
const source = (await readFile(filename, 'utf8')).replace(/^import .*;\r?\n/gm, '').replace('export default function', 'function').replaceAll('export function', 'function');
const { code } = await transformWithOxc(source, filename.pathname, { jsx: { runtime: 'classic' } });
const context = vm.createContext({ React, money: value => `৳${Number(value)}` });
vm.runInContext(`${code}\n globalThis.Component = ProductPricingFields; globalThis.values = pricingValues;`, context);
const suggested = { selling_price: 705, minimum_price: 705, maximum_price: 990, regular_price: 990, sale_price: 891, enforce_minimum_price: true, enforce_maximum_price: true, protect_minimum_price: true };
const pricing = { linked: true, mode: 'auto', supplier: 'Supplier', cost: 590, supplier_maximum: 990, raw_selling: 590, regular_source: 'maximum', suggested };

async function mount(initial, sourcePricing = pricing) {
  let data;
  function Wrapper() {
    const [state, setState] = useState(initial);
    data = state;
    const setData = (key, value) => typeof key === 'function' ? setState(key) : setState(current => ({ ...current, [key]: value }));
    return React.createElement(context.Component, { data: state, setData, pricing: sourcePricing });
  }
  let renderer;
  await act(async () => { renderer = create(React.createElement(Wrapper)); });
  return {
    renderer, data: () => data,
    text: () => {
      const collect = value => typeof value === 'string' ? value : Array.isArray(value) ? value.map(collect).join('') : collect(value?.children ?? []);
      return collect(renderer.toJSON());
    },
    change: async (id, value) => { await act(async () => renderer.root.findByProps({ id }).props.onChange({ target: { value } })); },
    close: async () => { await act(async () => renderer.unmount()); },
  };
}

test('automatic prices show Selling separately from Regular and Discounted', async () => {
  const app = await mount(context.values({ regular_price: 1350, sale_price: 1215 }, pricing));
  try {
    assert.equal(app.data().selling_price, 705);
    assert.equal(app.data().regular_price, 990);
    assert.match(app.text(), /Customer price: ৳891/);
    assert.equal(app.renderer.root.findByProps({ id: 'product-selling_price' }).props.readOnly, true);
    await app.change('product-pricing-mode', 'manual');
    assert.equal(app.renderer.root.findByProps({ id: 'product-selling_price' }).props.readOnly, false);
  } finally { await app.close(); }
});

test('product override previews customer price, detects protected minimum and can return to rules', async () => {
  const app = await mount({ ...suggested, pricing_mode: 'manual' });
  try {
    await app.change('product-sale_price', '700');
    assert.match(app.text(), /below the protected minimum/);
    assert.match(app.text(), /Customer price: ৳700/);
    await app.change('product-pricing-mode', 'auto');
    assert.equal(app.data().sale_price, 891);
    assert.doesNotMatch(app.text(), /below the protected minimum/);
  } finally { await app.close(); }
});

test('blank discounted price uses Regular while zero remains a real discounted price', async () => {
  const app = await mount({ regular_price: 200, sale_price: '', selling_price: 200, pricing_mode: 'manual' }, { linked: false });
  try {
    assert.match(app.text(), /Customer price: ৳200/);
    await app.change('product-sale_price', '0');
    assert.match(app.text(), /Customer price: ৳0/);
    const values = context.values({ regular_price: 200, sale_price: 0 }, { mode: 'manual' });
    assert.equal(values.sale_price, 0);
  } finally { await app.close(); }
});

test('conflicting supplier rules show an explanation and still allow a manual override', async () => {
  const app = await mount({ ...suggested, pricing_mode: 'auto' }, { ...pricing, suggested: null, reason: 'calculated_minimum_exceeds_maximum' });
  try {
    assert.match(app.text(), /Supplier pricing is unavailable or conflicting/);
    await app.change('product-pricing-mode', 'manual');
    assert.equal(app.data().pricing_mode, 'manual');
    assert.equal(app.renderer.root.findByProps({ id: 'product-selling_price' }).props.readOnly, false);
  } finally { await app.close(); }
});
