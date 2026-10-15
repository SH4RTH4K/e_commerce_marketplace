import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import vm from 'node:vm';
import React, { act, useEffect, useRef, useState } from 'react';
import { create } from 'react-test-renderer';
import { router, useForm } from '@inertiajs/react';
import { transformWithOxc } from 'vite';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
globalThis.window = { setTimeout: () => 0, showConfirm: (_message, confirm) => confirm() };
async function compile(relative) {
  const filename = new URL(relative, import.meta.url);
  const source = (await readFile(filename, 'utf8')).replace(/^import .*;\r?\n/gm, '').replaceAll('export default function', 'function').replaceAll('export function', 'function').replaceAll('export const', 'const');
  return (await transformWithOxc(source, filename.pathname, { jsx: { runtime: 'classic' } })).code;
}
const utility = (await readFile(new URL('../../resources/js/lib/customerCrm.js', import.meta.url), 'utf8')).replaceAll('export ', '');
const [dialogs, crm, index, reviews] = await Promise.all([
  compile('../../resources/js/Components/Admin/InventoryDialogs.jsx'), compile('../../resources/js/Components/Admin/CustomerCrm.jsx'),
  compile('../../resources/js/Pages/Admin/Customers/Index.jsx'), compile('../../resources/js/Pages/Admin/Reviews/Index.jsx'),
]);
const customer = { key: 'account-7', account_id: 7, customer_name: 'CRM Customer', customer_phone: '01712345678', is_active: false, orders_count: 2, delivered_count: 1, cancelled_count: 0, open_count: 1, total_spent: 1000, open_value: 500, profile_url: '/admin/customers/profiles/account-7' };

async function mount(component, props) {
  const calls = []; const originals = Object.fromEntries(['get', 'post', 'patch'].map(method => [method, router[method]]));
  for (const method of Object.keys(originals)) router[method] = (url, data, options) => calls.push({ method, url, data: JSON.parse(JSON.stringify(data)), options });
  const context = vm.createContext({ React, useEffect, useRef, useState, router, useForm, URL, URLSearchParams,
    usePage: () => ({ url: '/admin/customers?segment=registered&q=old', props: {} }), Head: () => null,
    AdminLayout: ({ children }) => React.createElement('main', {}, children), window: globalThis.window,
    document: { body: { style: {} }, activeElement: { focus() {} }, addEventListener() {}, removeEventListener() {} },
  });
  vm.runInContext(`${utility}\n${dialogs}\n${crm}\n${index}\n${reviews}\nglobalThis.Component = ${component}; globalThis.safeReturn = safeCustomerReturn; globalThis.active = activeCustomerSegment;`, context);
  let renderer; let currentProps = props;
  await act(async () => { renderer = create(React.createElement(context.Component, props), { createNodeMock: () => ({ focus() {}, querySelectorAll: () => [] }) }); });
  return { renderer, calls, context,
    update: async next => { currentProps = { ...currentProps, ...next }; await act(async () => renderer.update(React.createElement(context.Component, currentProps))); },
    change: async (element, value) => { await act(async () => element.props.onChange({ target: { value } })); },
    click: async element => { await act(async () => element.props.onClick()); },
    submit: async () => { await act(async () => renderer.root.findByType('form').props.onSubmit({ preventDefault() {} })); },
    close: async () => { await act(async () => renderer.unmount()); for (const method of Object.keys(originals)) router[method] = originals[method]; },
  };
}

test('CRM account activation submits an explicit desired status with the real form hook', async () => {
  let closed = false;
  const app = await mount('CustomerAccountDialog', { customer, onClose() { closed = true; } });
  try {
    await app.click(app.renderer.root.findAllByType('button').find(button => button.children[0] === 'Activate account'));
    assert.equal(app.calls[0].method, 'patch'); assert.equal(app.calls[0].url, '/admin/customers/7/toggle');
    assert.equal(app.calls[0].data.is_active, true);
    await act(async () => app.calls[0].options.onError({ is_active: 'Could not activate account.' }));
    assert.equal(app.renderer.root.findByProps({ role: 'alert' }).children[0], 'Could not activate account.');
    assert.equal(closed, false);
  } finally { await app.close(); }
});

test('private notes preserve failed drafts and clear the body only after saving', async () => {
  const app = await mount('CustomerNotes', { customer, notes: { data: [] }, returnTo: '/admin/customers?segment=registered', orderStatus: 'processing' });
  try {
    await app.submit(); assert.equal(app.calls.length, 0);
    await app.change(app.renderer.root.findByType('textarea'), 'Call back tomorrow.'); await app.submit();
    assert.equal(app.calls[0].data.body, 'Call back tomorrow.');
    assert.ok(app.calls[0].url.includes('/profiles/account-7/notes?'));
    assert.ok(app.calls[0].url.includes('order_status=processing'));
    await act(async () => app.calls[0].options.onError({ body: 'Please try again.' }));
    assert.equal(app.renderer.root.findByType('textarea').props.value, 'Call back tomorrow.');
    assert.equal(app.renderer.root.findByProps({ role: 'alert' }).children[0], 'Please try again.');
    await act(async () => app.calls[0].options.onSuccess({ props: {} }));
    assert.equal(app.renderer.root.findByType('textarea').props.value, '');
  } finally { await app.close(); }
});

test('customer segments preserve filters while export uses applied search values', async () => {
  const filters = { q: 'old', segment: 'registered', activity: '', sort: 'spent', per_page: 50 };
  const app = await mount('CustomersIndex', { customers: { data: [customer], total: 1, links: [] }, filters, summary: { registered: 1 } });
  try {
    await app.change(app.renderer.root.findByProps({ 'aria-label': 'Search customers' }), 'new');
    const exportLink = app.renderer.root.findAllByType('a').find(link => link.children[0] === 'Export CSV');
    assert.ok(exportLink.props.href.includes('q=old')); assert.ok(!exportLink.props.href.includes('q=new'));
    const nav = app.renderer.root.findByProps({ 'aria-label': 'Customer segments' });
    await app.click(nav.findAllByType('button').find(button => button.findAllByType('p')[0].children[0] === 'Guest Buyers'));
    assert.deepEqual(app.calls[0].data, { ...filters, q: 'new', segment: 'guest' });
    assert.equal(app.context.active('/admin/customers?sort=name&segment=repeat'), 'repeat');
    assert.equal(app.context.safeReturn('https://example.com'), '/admin/customers');
  } finally { await app.close(); }
});

test('review selection cannot carry into another segment or a different result page', async () => {
  const review = { id: 9, rating: 4, status: 'pending', author_name: 'Sample', body: 'Full review text', product: { name: 'Sample Product' } };
  const app = await mount('ReviewsIndex', { reviews: { data: [review], current_page: 1, total: 1, links: [] }, filters: { status: 'pending', q: '', rating: '', purchase: '' }, counts: { pending: 1 } });
  try {
    await act(async () => app.renderer.root.findByProps({ 'aria-label': 'Select review 9' }).props.onChange());
    assert.equal(app.renderer.root.findAllByProps({ 'aria-label': 'Review bulk action' }).length, 1);
    const tabs = app.renderer.root.findByProps({ 'aria-label': 'Review segments' });
    await app.click(tabs.findAllByType('button').find(button => button.children[0] === 'Approved'));
    assert.equal(app.renderer.root.findAllByProps({ 'aria-label': 'Review bulk action' }).length, 0);
    await act(async () => app.renderer.root.findByProps({ 'aria-label': 'Select review 9' }).props.onChange());
    await app.update({ reviews: { data: [{ ...review, id: 10 }], current_page: 2, total: 2, links: [] } });
    assert.equal(app.renderer.root.findByProps({ 'aria-label': 'Select review 10' }).props.checked, false);
    assert.equal(app.renderer.root.findAllByProps({ 'aria-label': 'Review bulk action' }).length, 0);
  } finally { await app.close(); }
});

test('review moderation exposes full feedback and submits only selected review ids', async () => {
  const review = { id: 9, rating: 4, status: 'pending', author_name: 'Sample', body: 'Full customer feedback.'.repeat(50), product: { name: 'Sample Product' } };
  const app = await mount('ReviewsIndex', { reviews: { data: [review], current_page: 1, total: 1, links: [] }, filters: { status: 'pending' } });
  try {
    assert.equal(app.renderer.root.findByType('details').findByType('p').children[0], review.body);
    await act(async () => app.renderer.root.findByProps({ 'aria-label': 'Select review 9' }).props.onChange());
    await app.change(app.renderer.root.findByProps({ 'aria-label': 'Review bulk action' }), 'approve');
    await app.click(app.renderer.root.findAllByType('button').find(button => button.children[0] === 'Apply to selected'));
    assert.equal(app.calls[0].url, '/admin/reviews/bulk');
    assert.deepEqual(app.calls[0].data, { ids: [9], bulk_action: 'approve' });
  } finally { await app.close(); }
});
