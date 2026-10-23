import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import vm from 'node:vm';
import React, { act, useEffect, useState } from 'react';
import { create } from 'react-test-renderer';
import { router } from '@inertiajs/react';
import { transformWithOxc } from 'vite';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
globalThis.window = { showConfirm() {}, open() {} };
const segmentSource = (await readFile(new URL('../../resources/js/lib/orderSegments.js', import.meta.url), 'utf8')).replaceAll('export ', '');
const customerSource = (await readFile(new URL('../../resources/js/lib/customerCrm.js', import.meta.url), 'utf8')).replaceAll('export ', '');

async function compile(relative, component) {
  const filename = new URL(relative, import.meta.url);
  const source = (await readFile(filename, 'utf8')).replace(/^import .*;\r?\n/gm, '').replace(`export default function ${component}`, `function ${component}`);
  return (await transformWithOxc(source, filename.pathname, { jsx: { runtime: 'classic' } })).code;
}
const segmentsCode = await compile('../../resources/js/Components/Admin/OrderSegments.jsx', 'OrderSegments');
const pageCode = await compile('../../resources/js/Pages/Admin/Orders/Index.jsx', 'OrdersIndex');
const layoutCode = await compile('../../resources/js/Layouts/AdminLayout.jsx', 'AdminLayout');

const order = { id: 7, order_number: 'ORD-TEST-7', customer_name: 'Sample Customer', customer_phone: '01700000000', total: 1060, status: 'pending', payment_method: 'cod', payment_status: 'pending', items_count: 1 };
const defaults = { status: 'all', counts: { all: 1, pending: 1 }, orders: { data: [order], total: 1, current_page: 1, from: 1, to: 1, links: [] } };

async function mount(initial = {}, layout = false) {
  const gets = []; const originalGet = router.get;
  router.get = (url, data, options) => gets.push({ url, data: JSON.parse(JSON.stringify(data)), options });
  let page = { url: '/admin/orders', props: { auth: { user: { role: 'admin', name: 'Admin' } }, app: { name: 'Test Store' }, admin_badges: { orders_all: 6, orders_pending: 2, orders_processing: 1, orders_pending_verification: 1, pending_orders: 2 } } };
  const context = vm.createContext({ React, useEffect, useState, URL, router, window: globalThis.window,
    usePage: () => page, Head: () => null, Link: ({ children, ...props }) => React.createElement('a', props, children),
    AdminLayout: ({ children }) => React.createElement('div', {}, children),
  });
  vm.runInContext(`${segmentSource}\n${customerSource}\n${segmentsCode}\n${layout ? layoutCode : pageCode}\nglobalThis.Component = ${layout ? 'AdminLayout' : 'OrdersIndex'}; globalThis.resolve = activeOrderSegment; globalThis.detailHref = orderDetailHref; globalThis.returnUrl = orderReturnUrl;`, context);
  let renderer;
  let props = { ...defaults, ...initial };
  await act(async () => { renderer = create(React.createElement(context.Component, props)); });
  return {
    renderer, gets, resolve: context.resolve, detailHref: context.detailHref, returnUrl: context.returnUrl,
    update: async next => { props = { ...props, ...next }; await act(async () => renderer.update(React.createElement(context.Component, props))); },
    url: async url => { page = { ...page, url }; await act(async () => renderer.update(React.createElement(context.Component, props))); },
    click: async element => { await act(async () => element.props.onClick()); },
    select: async id => { await act(async () => renderer.root.findAllByProps({ 'aria-label': `Select ORD-TEST-${id}` })[0].props.onChange()); },
    close: async () => { await act(async () => renderer.unmount()); router.get = originalGet; },
  };
}

test('changing a status segment preserves applied filters and resets pagination', async () => {
  const app = await mount({ q: 'Customer', method: 'bkash', dateFilter: 'custom', fromDate: '2026-10-01', toDate: '2026-10-15', sort: 'oldest', per_page: 25 });
  try {
    const segments = app.renderer.root.findByProps({ 'aria-label': 'Order status segments' });
    await app.click(segments.findAllByType('button').find(button => button.children[0] === 'Processing'));
    assert.equal(app.gets[0].url, '/admin/orders');
    assert.deepEqual(app.gets[0].data, { status: 'processing', q: 'Customer', method: 'bkash', date_filter: 'custom', from_date: '2026-10-01', to_date: '2026-10-15', sort: 'oldest', per_page: 25 });
    assert.equal(Object.hasOwn(app.gets[0].data, 'page'), false);
  } finally { await app.close(); }
});

test('sourcing queue preserves search filters and hides courier shipment links until stock is ready', async () => {
  const app = await mount({ q: 'Clock', counts: { awaiting_sourcing: 1 }, orders: { ...defaults.orders, data: [{ ...order, awaiting_sourcing: true }] } });
  try {
    const segments = app.renderer.root.findByProps({ 'aria-label': 'Order status segments' });
    await app.click(segments.findAllByType('button').find(button => button.children[0] === 'Awaiting sourcing'));
    assert.equal(app.gets[0].data.status, 'awaiting_sourcing');
    assert.equal(app.gets[0].data.q, 'Clock');
    assert.equal(app.renderer.root.findAllByType('a').some(link => String(link.props.href).includes('ship=1')), false);
    assert.match(JSON.stringify(app.renderer.toJSON()), /Awaiting sourcing/);
  } finally { await app.close(); }
});

test('bulk selections clear when a segment is opened or different results arrive', async () => {
  const app = await mount();
  try {
    await app.select(7);
    assert.equal(app.renderer.root.findAllByType('select').some(select => select.findAllByProps({ value: 'invoice' }).length), true);
    const segments = app.renderer.root.findByProps({ 'aria-label': 'Order status segments' });
    await app.click(segments.findAllByType('button').find(button => button.children[0] === 'Pending'));
    assert.equal(app.renderer.root.findAllByProps({ 'aria-label': 'Select ORD-TEST-7' }).every(input => !input.props.checked), true);
    await app.select(7);
    await app.update({ orders: { ...defaults.orders, current_page: 2, data: [{ ...order, id: 8, order_number: 'ORD-TEST-8' }] } });
    assert.equal(app.renderer.root.findAllByProps({ 'aria-label': 'Select ORD-TEST-8' }).every(input => !input.props.checked), true);
    assert.equal(app.renderer.root.findAllByType('select').some(select => select.findAllByProps({ value: 'invoice' }).length), false);
  } finally { await app.close(); }
});

test('server-normalized filters replace stale form values after navigation', async () => {
  const app = await mount({ q: 'old', method: 'bkash' });
  try {
    await app.update({ q: 'new', method: 'cod', dateFilter: 'today' });
    assert.equal(app.renderer.root.findByProps({ 'aria-label': 'Search orders' }).props.value, 'new');
    assert.equal(app.renderer.root.findByProps({ 'aria-label': 'Payment method' }).props.value, 'cod');
    assert.equal(app.renderer.root.findByProps({ 'aria-label': 'Order date range' }).props.value, 'today');
  } finally { await app.close(); }
});

test('sidebar highlights exactly the requested segment even with reordered query parameters', async () => {
  const app = await mount({}, true);
  try {
    await app.url('/admin/orders?q=Customer&status=processing&page=2');
    const active = app.renderer.root.findAllByType('a').filter(link => link.props['aria-current'] === 'page');
    assert.equal(active.length, 1);
    assert.equal(active[0].props.href, '/admin/orders?status=processing');
    assert.equal(app.resolve('/admin/orders?status=unknown'), 'all');
    assert.equal(app.resolve('/admin/orders?method=cod'), 'all');
    assert.equal(app.resolve('/admin/orders/create'), null);
    assert.equal(app.resolve('/admin/orders/44?ship=1'), 'all');
    const listUrl = '/admin/orders?status=processing&q=Customer&method=bkash&page=2';
    const detailUrl = app.detailHref(44, listUrl, true);
    assert.equal(app.returnUrl(detailUrl), listUrl);
    assert.equal(app.resolve(detailUrl), 'processing');
    assert.equal(app.returnUrl('/admin/orders/44?return_to=https%3A%2F%2Fexample.com'), '/admin/orders');
    const profile = '/admin/customers/profiles/account-7?order_status=processing';
    assert.equal(app.returnUrl('/admin/orders/44?return_to=' + encodeURIComponent(profile)), profile);
  } finally { await app.close(); }
});
