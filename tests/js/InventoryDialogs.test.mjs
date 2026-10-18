import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import vm from 'node:vm';
import React, { act, useEffect, useRef, useState } from 'react';
import { create } from 'react-test-renderer';
import { router, useForm } from '@inertiajs/react';
import { transformWithOxc } from 'vite';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
globalThis.window = { setTimeout: () => 0 };
const filename = new URL('../../resources/js/Components/Admin/InventoryDialogs.jsx', import.meta.url);
const source = (await readFile(filename, 'utf8')).replace(/^import .*;\r?\n/gm, '').replaceAll('export function', 'function');
const { code } = await transformWithOxc(source, filename.pathname, { jsx: { runtime: 'classic' } });
const product = { id: 7, name: 'Inventory Backpack', sku: 'BAG-7', stock_quantity: 10, stock_source: 'supplier' };

async function mount(products = [product]) {
  const posts = []; const originalPost = router.post;
  router.post = (url, data, options) => posts.push({ url, data: JSON.parse(JSON.stringify(data)), options });
  const document = { body: { style: {} }, activeElement: { focus() {} }, addEventListener() {}, removeEventListener() {} };
  const context = vm.createContext({ React, useEffect, useRef, useState, useForm, document });
  vm.runInContext(`${code}\n globalThis.Component = StockAdjustmentDialog;`, context);
  let renderer; let saved = false;
  await act(async () => { renderer = create(React.createElement(context.Component, { products, onClose() {}, onSaved() { saved = true; } }), { createNodeMock: () => ({ focus() {}, querySelectorAll: () => [] }) }); });
  return {
    renderer, posts, saved: () => saved,
    change: async (type, value) => { await act(async () => (type === 'select' ? renderer.root.findByProps({ 'aria-label': 'Movement type' }) : renderer.root.findByType(type)).props.onChange({ target: { value } })); },
    quantity: async value => { await act(async () => renderer.root.findByProps({ type: 'number' }).props.onChange({ target: { value } })); },
    submit: async () => { await act(async () => renderer.root.findByType('form').props.onSubmit({ preventDefault() {} })); },
    close: async () => { await act(async () => renderer.unmount()); router.post = originalPost; },
  };
}

test('stock adjustment submits expected quantities and supplier protection with the real form hook', async () => {
  const app = await mount();
  try {
    await app.quantity('3'); await app.submit();
    assert.equal(app.posts.length, 1);
    assert.equal(app.posts[0].url, '/admin/inventory/adjust');
    assert.deepEqual(app.posts[0].data.items, [{ product_id: 7, expected_stock: 10 }]);
    assert.equal(app.posts[0].data.quantity, '3');
    assert.equal(app.posts[0].data.operation, 'add');
    assert.equal(app.posts[0].data.movement_type, 'stock_in');
    assert.equal('supplier_id' in app.posts[0].data, false);
    assert.equal(app.posts[0].data.protect_supplier_stock, true);
    await act(async () => app.posts[0].options.onSuccess({ props: {} }));
    assert.equal(app.saved(), true);
  } finally { await app.close(); }
});

test('movement form saves receipt supplier and reference without editing product assignment', async () => {
  const app = await mount();
  try {
    await app.change('select', 'return_in');
    await act(async () => app.renderer.root.findByProps({ 'aria-label': 'Movement supplier' }).props.onChange({ target: { value: '42' } }));
    await act(async () => app.renderer.root.findByProps({ placeholder: 'Receipt, order, or stock count reference' }).props.onChange({ target: { value: 'RETURN-42' } }));
    await app.submit();
    assert.equal(app.posts[0].data.movement_type, 'return_in');
    assert.equal(app.posts[0].data.operation, 'add');
    assert.equal(app.posts[0].data.supplier_id, '42');
    assert.equal(app.posts[0].data.reference, 'RETURN-42');
  } finally { await app.close(); }
});

test('setting zero is allowed, but fractional quantities and over-removal disable saving', async () => {
  const app = await mount();
  try {
    await app.change('select', 'adjustment'); await app.quantity('0');
    assert.equal(app.renderer.root.findByProps({ type: 'submit' }).props.disabled, false);
    await app.quantity('1.5');
    assert.equal(app.renderer.root.findByProps({ type: 'submit' }).props.disabled, true);
    await app.change('select', 'stock_out'); await app.quantity('11');
    assert.equal(app.renderer.root.findByProps({ type: 'submit' }).props.disabled, true);
    await app.submit(); assert.equal(app.posts.length, 0);
  } finally { await app.close(); }
});

test('bulk preview shows every product and submits each original quantity', async () => {
  const app = await mount([product, { ...product, id: 8, name: 'Second Product', stock_quantity: 2 }]);
  try {
    assert.equal(app.renderer.root.findAllByType('tbody')[0].findAllByType('tr').length, 2);
    await app.quantity('4'); await app.submit();
    assert.deepEqual(app.posts[0].data.items, [{ product_id: 7, expected_stock: 10 }, { product_id: 8, expected_stock: 2 }]);
    await act(async () => app.posts[0].options.onError({ items: 'Stock changed. Refresh inventory.' }));
    const alerts = app.renderer.root.findAllByProps({ role: 'alert' });
    assert.equal(alerts[0].children[0], 'Stock changed. Refresh inventory.');
    assert.equal(app.saved(), false);
  } finally { await app.close(); }
});
