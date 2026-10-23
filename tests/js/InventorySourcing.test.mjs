import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import vm from 'node:vm';
import React, { act, useEffect, useRef, useState } from 'react';
import { create } from 'react-test-renderer';
import { router } from '@inertiajs/react';
import { transformWithOxc } from 'vite';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
const filename = new URL('../../resources/js/Pages/Admin/Inventory/Index.jsx', import.meta.url);
const source = (await readFile(filename, 'utf8')).replace(/^import .*;\r?\n/gm, '').replace('export default function', 'function');
const { code } = await transformWithOxc(source, filename.pathname, { jsx: { runtime: 'classic' } });
const clock = { id: 7, name: 'Clock', sku: 'CLOCK-7', stock_quantity: 0, sourced_stock_quantity: 0, available_stock_quantity: 0, supplier_stock_quantity: 0, allow_backorder: false, price: 585, is_published: true };
const speaker = { ...clock, id: 8, name: 'Speaker', stock_quantity: 2, available_stock_quantity: 2, allow_backorder: true };
const defaults = { products: { data: [clock, speaker], total: 2, current_page: 1, links: [] }, q: 'Clock', stock_level: 'out', supplier: '4', can_adjust: true };

async function mount(props = {}) {
  const calls = [];
  const originals = { get: router.get, post: router.post };
  for (const method of Object.keys(originals)) router[method] = (url, data, options) => calls.push({ method, url, data: JSON.parse(JSON.stringify(data)), options });
  const context = vm.createContext({ React, useEffect, useRef, useState, router, URLSearchParams,
    Head: () => null, AdminLayout: ({ children }) => React.createElement('main', {}, children),
    usePage: () => ({ props: { app: { currency_symbol: '৳' } } }),
    StockAdjustmentDialog: () => React.createElement('div', { role: 'dialog' }, 'Stock adjustment'),
    AdjustmentHistoryDialog: () => null, ResumeStockSyncDialog: () => null,
  });
  vm.runInContext(`${code}\nglobalThis.Component = InventoryIndex;`, context);
  let renderer;
  let currentProps = { ...defaults, ...props };
  await act(async () => { renderer = create(React.createElement(context.Component, currentProps)); });
  return { renderer, calls,
    button: label => renderer.root.findAllByType('button').find(button => button.children[0] === label),
    click: async element => { await act(async () => element.props.onClick()); },
    select: async (name, checked = true) => { await act(async () => renderer.root.findByProps({ 'aria-label': `Select ${name}` }).props.onChange({ target: { checked } })); },
    update: async props => { currentProps = { ...currentProps, ...props }; await act(async () => renderer.update(React.createElement(context.Component, currentProps))); },
    close: async () => { await act(async () => renderer.unmount()); Object.assign(router, originals); },
  };
}

test('inventory enables sourcing for selected IDs and preserves the filtered view without changing stock', async () => {
  const app = await mount();
  try {
    await app.select('Clock');
    await app.click(app.button('Enable sourcing'));
    assert.equal(app.calls.length, 1);
    assert.equal(app.calls[0].method, 'post');
    assert.equal(app.calls[0].url, '/admin/products/bulk');
    assert.deepEqual(app.calls[0].data, { ids: [7], bulk_action: 'enable_sourcing' });
    assert.equal(app.calls[0].options.preserveState, true);
    assert.equal(app.calls[0].options.preserveScroll, true);
    assert.equal(app.renderer.root.findByProps({ type: 'search' }).props.value, 'Clock');
    assert.match(JSON.stringify(app.renderer.toJSON()), /Actual stock is unchanged/);
    assert.match(JSON.stringify(app.renderer.toJSON()), /Current stock/);
  } finally { await app.close(); }
});

test('inventory disables sourcing for a page selection and clears selection only after success', async () => {
  const app = await mount();
  try {
    await app.select('this page');
    await app.click(app.button('Disable sourcing'));
    assert.deepEqual(app.calls[0].data, { ids: [7, 8], bulk_action: 'disable_sourcing' });
    await act(async () => app.calls[0].options.onFinish());
    assert.equal(app.renderer.root.findByProps({ 'aria-label': 'Select Clock' }).props.checked, true);
    await app.click(app.button('Disable sourcing'));
    await act(async () => { app.calls[1].options.onSuccess(); app.calls[1].options.onFinish(); });
    assert.equal(app.renderer.root.findByProps({ 'aria-label': 'Select Clock' }).props.checked, false);
    assert.equal(app.button('Disable sourcing'), undefined);
  } finally { await app.close(); }
});

test('pending inventory sourcing blocks duplicate requests, selection changes and stock adjustment', async () => {
  const app = await mount();
  try {
    await app.select('Clock');
    const enable = app.button('Enable sourcing');
    await act(async () => { enable.props.onClick(); enable.props.onClick(); });
    assert.equal(app.calls.length, 1);
    assert.equal(app.button('Enabling sourcing…').props.disabled, true);
    assert.equal(app.button('Disable sourcing').props.disabled, true);
    assert.equal(app.button('Clear selection').props.disabled, true);
    assert.equal(app.button('Adjust selected stock').props.disabled, true);
    assert.equal(app.renderer.root.findByProps({ 'aria-label': 'Select Clock' }).props.disabled, true);
    await app.select('Clock', false);
    await app.click(app.button('Clear selection'));
    await app.click(app.button('Adjust selected stock'));
    await app.click(app.button('Disable sourcing'));
    await app.click(app.button('Reset filters'));
    assert.equal(app.renderer.root.findByProps({ 'aria-label': 'Select Clock' }).props.checked, true);
    assert.equal(app.renderer.root.findAllByProps({ role: 'dialog' }).length, 0);
    assert.equal(app.calls.length, 1);
  } finally { await app.close(); }
});

test('inventory sourcing validation is announced and retains selected products for retry', async () => {
  const app = await mount();
  try {
    await app.select('Clock');
    await app.click(app.button('Enable sourcing'));
    await act(async () => {
      app.calls[0].options.onError({ 'ids.0': 'The selected product no longer exists.' });
      app.calls[0].options.onFinish();
    });
    assert.equal(app.renderer.root.findByProps({ role: 'alert' }).findByType('p').children[0], 'The selected product no longer exists.');
    assert.equal(app.renderer.root.findByProps({ 'aria-label': 'Select Clock' }).props.checked, true);
    assert.equal(app.button('Enable sourcing').props.disabled, false);
    await app.click(app.button('Enable sourcing'));
    assert.equal(app.calls.length, 2);
    assert.equal(app.renderer.root.findAllByProps({ role: 'alert' }).length, 0);
  } finally { await app.close(); }
});

test('inventory clears stale sourcing selections when results, filters or pages change', async () => {
  const app = await mount();
  try {
    await app.select('Clock');
    await app.update({ products: { ...defaults.products, data: [clock] } });
    assert.equal(app.button('Enable sourcing'), undefined);
    await app.select('Clock');
    await app.update({ stock_level: 'in' });
    assert.equal(app.button('Enable sourcing'), undefined);
    await app.select('Clock');
    await app.update({ products: { ...defaults.products, data: [clock], current_page: 2 } });
    assert.equal(app.button('Enable sourcing'), undefined);
  } finally { await app.close(); }
});

test('read-only inventory has no sourcing or selection controls', async () => {
  const app = await mount({ can_adjust: false });
  try {
    assert.equal(app.renderer.root.findAllByProps({ type: 'checkbox' }).length, 0);
    assert.equal(app.button('Enable sourcing'), undefined);
    assert.equal(app.button('Disable sourcing'), undefined);
    assert.match(JSON.stringify(app.renderer.toJSON()), /Current stock/);
  } finally { await app.close(); }
});
