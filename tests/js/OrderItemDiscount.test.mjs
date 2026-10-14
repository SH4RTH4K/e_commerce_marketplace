import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import vm from 'node:vm';
import React, { act, useEffect } from 'react';
import { create } from 'react-test-renderer';
import { router, useForm } from '@inertiajs/react';
import { transformWithOxc } from 'vite';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
globalThis.window = { setTimeout: () => 0 };
const filename = new URL('../../resources/js/Components/OrderItemDiscount.jsx', import.meta.url);
const source = (await readFile(filename, 'utf8')).replace(/^import .*;\r?\n/gm, '').replace('export default function', 'function');
const { code } = await transformWithOxc(source, filename.pathname, { jsx: { runtime: 'classic' } });

async function mount(unitDiscount = 0) {
  const submissions = [];
  const originalPatch = router.patch;
  router.patch = (url, data, options) => submissions.push({ url, data: JSON.parse(JSON.stringify(data)), options });
  const context = vm.createContext({ React, useEffect, useForm, money: value => `৳${value}` });
  vm.runInContext(`${code}\n globalThis.Component = OrderItemDiscount;`, context);
  const item = { id: 9, product_name: 'Test Product', quantity: 3, unit_price: 200, unit_discount: unitDiscount };
  let renderer;
  await act(async () => { renderer = create(React.createElement(context.Component, { orderId: 44, item })); });
  return {
    renderer, submissions,
    text: () => JSON.stringify(renderer.toJSON()),
    change: async value => { await act(async () => renderer.root.findByType('input').props.onChange({ target: { value } })); },
    submit: async () => { await act(async () => renderer.root.findByType('form').props.onSubmit({ preventDefault() {} })); },
    close: async () => { await act(async () => renderer.unmount()); router.patch = originalPatch; },
  };
}

test('product discount previews quantity and submits only the selected order item', async () => {
  const app = await mount();
  try {
    await app.change('50');
    assert.match(app.text(), /Product total: ৳450/);
    await app.submit();
    assert.equal(app.submissions.length, 1);
    assert.equal(app.submissions[0].url, '/admin/orders/44/items/9/discount');
    assert.deepEqual(app.submissions[0].data, { unit_discount: '50' });
    assert.equal(app.submissions[0].options.preserveScroll, true);
  } finally { await app.close(); }
});

test('zero removes a product discount and a full discount previews zero', async () => {
  const app = await mount(25);
  try {
    await app.change('200');
    assert.match(app.text(), /Product total: ৳0/);
    await app.change('0');
    assert.match(app.text(), /Product total: ৳600/);
    await app.submit();
    assert.deepEqual(app.submissions[0].data, { unit_discount: '0' });
  } finally { await app.close(); }
});

test('invalid amounts disable saving and server validation errors stay visible', async () => {
  const app = await mount();
  try {
    for (const value of ['', '-1', '201']) {
      await app.change(value);
      assert.equal(app.renderer.root.findByType('button').props.disabled, true);
    }
    await app.change('50'); await app.submit();
    await act(async () => app.submissions[0].options.onError({ unit_discount: 'Reduce the order discount first.' }));
    assert.match(app.text(), /Reduce the order discount first/);
    assert.equal(app.renderer.root.findByType('input').props.value, '50');
  } finally { await app.close(); }
});
