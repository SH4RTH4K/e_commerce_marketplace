import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import vm from 'node:vm';
import React, { act, useEffect, useRef, useState } from 'react';
import { create } from 'react-test-renderer';
import { transformWithOxc } from 'vite';
import { router, useForm } from '@inertiajs/react';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
globalThis.window = { setTimeout: () => 0 };
const filename = new URL('../../resources/js/Components/AddOrderProducts.jsx', import.meta.url);
const source = (await readFile(filename, 'utf8')).replace(/^import .*;\r?\n/gm, '').replace('export default function', 'function');
const transformed = await transformWithOxc(source, filename.pathname, { jsx: { runtime: 'classic' } });
const product = { id: 7, name: 'Test backpack', sku: 'BAG-7', price: 200, stock_quantity: 5, image_url: '/bag.jpg' };

async function mount(responses, submitErrors = null) {
  const calls = [];
  const submissions = [];
  const originalPost = router.post;
  router.post = (url, data, options) => {
    submissions.push({ url, data: JSON.parse(JSON.stringify(data)), options });
    options.onBefore?.({});
    options.onStart?.({});
  };
  const timers = new Map();
  let nextTimer = 0;
  const context = vm.createContext({
    React, useEffect, useRef, useState, useForm, AbortController,
    money: value => `৳${value}`,
    setTimeout: callback => { timers.set(++nextTimer, callback); return nextTimer; },
    clearTimeout: id => timers.delete(id),
    fetch: async (url, options) => {
      calls.push({ url, options });
      const response = responses.shift();
      if (response instanceof Error) throw response;
      if (typeof response === 'function') return response(options.signal);
      return response ?? { ok: true, status: 200, json: async () => [product] };
    },
  });
  vm.runInContext(`${transformed.code}\n globalThis.Component = AddOrderProducts;`, context);
  let renderer;
  await act(async () => { renderer = create(React.createElement(context.Component, { order: { id: 44, subtotal: 711, total: 831, shipping_charge: 120 } })); });
  const button = label => renderer.root.findAllByType('button').find(node => node.props.children === label);
  const click = async label => { await act(async () => { button(label).props.onClick(); }); };
  const flush = async () => {
    await act(async () => {
      const callbacks = [...timers.values()];
      timers.clear();
      for (const callback of callbacks) await callback();
    });
  };
  const startRequests = async () => {
    const requests = [];
    await act(async () => {
      const callbacks = [...timers.values()];
      timers.clear();
      for (const callback of callbacks) requests.push(callback());
      await Promise.resolve();
    });
    return requests;
  };
  return {
    renderer, calls, submissions, click, flush, startRequests,
    text: () => JSON.stringify(renderer.toJSON()),
    search: async value => { await act(async () => { renderer.root.findByProps({ id: 'order-product-search' }).props.onChange({ target: { value } }); }); },
    submit: async () => {
      await act(async () => {
        renderer.root.findByType('form').props.onSubmit({ preventDefault() {} });
        const submission = submissions.at(-1);
        if (submitErrors) submission.options.onError(submitErrors);
        else await submission.options.onSuccess({ props: {} });
        submission.options.onFinish({});
      });
    },
    close: async () => { await act(async () => renderer.unmount()); router.post = originalPost; },
  };
}

test('reopening a loaded picker reuses products without another request', async () => {
  const app = await mount([]);
  try {
    await app.click('Add Products'); await app.flush();
    assert.match(app.text(), /Test backpack/);
    await app.click('Close'); await app.click('Add Products'); await app.flush();
    assert.equal(app.calls.length, 1);
    assert.match(app.text(), /Test backpack/);
    assert.doesNotMatch(app.text(), /Could not load products/);
    assert.equal(app.calls[0].options.cache, 'no-store');
    assert.equal(app.calls[0].options.credentials, 'same-origin');
  } finally { await app.close(); }
});

test('closing before the debounced request starts does not leave an error', async () => {
  const app = await mount([]);
  try {
    await app.click('Add Products'); await app.click('Close'); await app.flush();
    assert.equal(app.calls.length, 0);
    await app.click('Add Products'); await app.flush();
    assert.equal(app.calls.length, 1);
    assert.match(app.text(), /Test backpack/);
  } finally { await app.close(); }
});

test('a cancelled request cannot overwrite the next successful opening', async () => {
  let resolveFirst;
  const app = await mount([() => new Promise(resolve => { resolveFirst = resolve; })]);
  try {
    await app.click('Add Products');
    const [pending] = await app.startRequests();
    await app.click('Close'); await app.click('Add Products'); await app.flush();
    await act(async () => { resolveFirst({ ok: false, status: 500 }); await pending; });
    assert.match(app.text(), /Test backpack/);
    assert.doesNotMatch(app.text(), /Could not load products/);
  } finally { await app.close(); }
});

test('a failed request can be retried successfully without closing the picker', async () => {
  const app = await mount([{ ok: false, status: 503 }]);
  try {
    await app.click('Add Products'); await app.flush();
    assert.match(app.text(), /Could not load products/);
    await app.click('Retry loading products'); await app.flush();
    assert.equal(app.calls.length, 2);
    assert.match(app.text(), /Test backpack/);
    assert.doesNotMatch(app.text(), /Could not load products/);
  } finally { await app.close(); }
});

test('searches have separate cached results and saving clears cached stock', async () => {
  const app = await mount([]);
  try {
    await app.click('Add Products'); await app.flush();
    await app.search('BAG-7'); await app.flush();
    await app.click('Close'); await app.click('Add Products'); await app.flush();
    assert.equal(app.calls.length, 2);
    await app.click('Add');
    await app.submit();
    await app.click('Add Products'); await app.flush();
    assert.equal(app.calls.length, 3);
    assert.equal(app.calls[2].url, '/admin/orders/product-search?q=');
  } finally { await app.close(); }
});

test('login redirects display a session-expiry message', async () => {
  const app = await mount([{ ok: true, status: 200, redirected: true, url: 'https://taqilife.com/admin/login' }]);
  try {
    await app.click('Add Products'); await app.flush();
    assert.match(app.text(), /Your admin session expired/);
  } finally { await app.close(); }
});

test('Add to Order posts the selected product quantities with the real Inertia form hook', async () => {
  const app = await mount([]);
  try {
    await app.click('Add Products'); await app.flush();
    await app.click('Add'); await app.click('Add');
    await app.submit();
    assert.equal(app.submissions.length, 1);
    assert.equal(app.submissions[0].url, '/admin/orders/44/items');
    assert.deepEqual(app.submissions[0].data, { items: [{ product_id: 7, quantity: 2 }] });
    assert.equal(app.submissions[0].options.preserveScroll, true);
    assert.doesNotMatch(app.text(), /Products to add/);
  } finally { await app.close(); }
});

test('stock validation errors keep the selection so the admin can correct it', async () => {
  const app = await mount([], { 'items.0.quantity': 'Only one item remains in stock.' });
  try {
    await app.click('Add Products'); await app.flush(); await app.click('Add');
    await app.submit();
    assert.match(app.text(), /Products to add/);
    assert.match(app.text(), /Only one item remains in stock/);
    assert.equal(app.renderer.root.findByProps({ type: 'submit' }).props.disabled, false);
  } finally { await app.close(); }
});
