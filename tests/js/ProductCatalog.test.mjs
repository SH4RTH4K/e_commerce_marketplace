import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import vm from 'node:vm';
import React, { act, useEffect, useState } from 'react';
import { create } from 'react-test-renderer';
import { router } from '@inertiajs/react';
import { transformWithOxc } from 'vite';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
globalThis.window = { showConfirm: (_message, callback) => callback() };
const filename = new URL('../../resources/js/Pages/Admin/Products/Index.jsx', import.meta.url);
const source = (await readFile(filename, 'utf8')).replace(/^import .*;\r?\n/gm, '').replaceAll('export default function', 'function').replaceAll('export function', 'function');
const { code } = await transformWithOxc(source, filename.pathname, { jsx: { runtime: 'classic' } });
const product = { id: 7, name: 'Sample Speaker', sku: 'SKU-7', is_published: true, stock_quantity: 0, price: '0.00', regular_price: '500.00', sale_price: '0.00', image_url: '/storage/products/sample.jpg', url_key: 'sample-speaker-SKU-7', selling_price: '400.00', variants_count: 0, has_variants: false };
const defaults = { products: { data: [product], total: 1, current_page: 1, links: [] }, filters: { q: 'Speaker', category: 2, supplier: '', status: 'published', stock: '', type: '', sort: 'relevance', per_page: 15 }, canEdit: true, canDelete: false };

async function mount(props = {}) {
  const calls = []; const originals = Object.fromEntries(['get', 'patch', 'post', 'delete'].map(method => [method, router[method]]));
  for (const method of Object.keys(originals)) router[method] = (url, data, options) => calls.push({ method, url, data: JSON.parse(JSON.stringify(data)), options });
  const context = vm.createContext({ React, useState, useEffect, URLSearchParams, router, window: globalThis.window,
    Head: () => null, AdminLayout: ({ children }) => React.createElement('main', {}, children),
    usePage: () => ({ url: '/admin/products?q=Speaker&status=published&page=2', props: { app: { currency_symbol: '৳' }, errors: {} } }),
  });
  vm.runInContext(`${code}\nglobalThis.Component = ProductsIndex;`, context);
  let renderer; let currentProps = { ...defaults, ...props };
  await act(async () => { renderer = create(React.createElement(context.Component, currentProps)); });
  return { renderer, calls,
    change: async (element, value) => { await act(async () => element.props.onChange({ target: { value } })); },
    click: async element => { await act(async () => element.props.onClick()); },
    submit: async () => { await act(async () => renderer.root.findByType('form').props.onSubmit({ preventDefault() {} })); },
    select: async id => { await act(async () => renderer.root.findAllByProps({ 'aria-label': `Select product ${id}` })[0].props.onChange()); },
    update: async next => { currentProps = { ...currentProps, ...next }; await act(async () => renderer.update(React.createElement(context.Component, currentProps))); },
    close: async () => { await act(async () => renderer.unmount()); for (const method of Object.keys(originals)) router[method] = originals[method]; },
  };
}

test('search submits literal text with combined filters and no stale pagination', async () => {
  const app = await mount();
  try {
    await app.change(app.renderer.root.findByProps({ 'aria-label': 'Search products' }), '50% Speaker & Case');
    await app.change(app.renderer.root.findByProps({ 'aria-label': 'Stock level' }), 'out');
    await app.submit();
    assert.equal(app.calls[0].url, '/admin/products');
    assert.deepEqual(app.calls[0].data, { q: '50% Speaker & Case', category: 2, status: 'published', stock: 'out', sort: 'relevance', per_page: 15 });
    assert.equal(Object.hasOwn(app.calls[0].data, 'page'), false);
  } finally { await app.close(); }
});

test('sorting uses the applied search and resetting removes every active filter', async () => {
  const app = await mount();
  try {
    await app.change(app.renderer.root.findByProps({ 'aria-label': 'Search products' }), 'Unapplied search');
    await app.change(app.renderer.root.findByProps({ 'aria-label': 'Sort products' }), 'price_asc');
    assert.equal(app.calls[0].data.q, 'Speaker');
    await app.click(app.renderer.root.findAllByType('button').find(button => button.children[0] === 'Reset filters'));
    assert.deepEqual(app.calls[1].data, { sort: 'relevance', per_page: 15 });
  } finally { await app.close(); }
});

test('server filters refresh controls and selections clear on changed results', async () => {
  const app = await mount();
  try {
    await app.select(7);
    assert.equal(app.renderer.root.findAllByProps({ 'aria-label': 'Product bulk action' }).length, 1);
    await app.update({ filters: { ...defaults.filters, q: 'New search', status: 'draft' }, products: { ...defaults.products, data: [{ ...product, id: 8 }], current_page: 2 } });
    assert.equal(app.renderer.root.findByProps({ 'aria-label': 'Search products' }).props.value, 'New search');
    assert.equal(app.renderer.root.findByProps({ 'aria-label': 'Visibility' }).props.value, 'draft');
    assert.equal(app.renderer.root.findAllByProps({ 'aria-label': 'Product bulk action' }).length, 0);
    assert.equal(app.renderer.root.findAllByProps({ 'aria-label': 'Select product 8' }).every(input => !input.props.checked), true);
  } finally { await app.close(); }
});

test('publishing sends an explicit desired state and bulk actions only send current selection', async () => {
  const app = await mount();
  try {
    await app.click(app.renderer.root.findAllByProps({ 'aria-label': 'Set to draft Sample Speaker' })[0]);
    assert.equal(app.calls[0].method, 'patch'); assert.deepEqual(app.calls[0].data, { is_published: false });
    await app.select(7); await app.change(app.renderer.root.findByProps({ 'aria-label': 'Product bulk action' }), 'deactivate');
    await app.click(app.renderer.root.findAllByType('button').find(button => button.children[0] === 'Apply to selected'));
    assert.deepEqual(app.calls[1].data, { ids: [7], bulk_action: 'deactivate' });
    assert.equal(app.renderer.root.findAllByProps({ value: 'delete' }).length, 0);
  } finally { await app.close(); }
});

test('read-only catalog shows zero customer prices without edit or publication controls', async () => {
  const app = await mount({ canEdit: false, canDelete: false });
  try {
    assert.equal(app.renderer.root.findAllByProps({ 'aria-label': 'Select products on this page' }).length, 0);
    assert.equal(app.renderer.root.findAllByProps({ 'aria-label': 'Set to draft Sample Speaker' }).length, 0);
    assert.equal(app.renderer.root.findAllByType('a').some(link => String(link.props.href).includes('/edit')), false);
    assert.ok(app.renderer.root.findAllByType('p').some(paragraph => paragraph.children.includes('৳0')));
    assert.equal(app.renderer.root.findAllByType('img').every(image => image.props.src === '/storage/products/sample.jpg'), true);
  } finally { await app.close(); }
});

test('catalog keeps actual inventory visible with a sourcing override and separate received stock', async () => {
  const app = await mount({ products: { ...defaults.products, data: [{ ...product, stock_quantity: 0, sourced_stock_quantity: 3, available_stock_quantity: 3, supplier_stock_quantity: 0, allow_backorder: true }] } });
  try {
    const html = JSON.stringify(app.renderer.toJSON());
    assert.match(html, /Current stock/);
    assert.match(html, /Locally received/);
    assert.match(html, /Supplier availability/);
    assert.match(html, /Sourcing enabled/);
    assert.ok(app.renderer.root.findAllByType('p').some(node => node.children.includes('3')));
  } finally { await app.close(); }
});

for (const action of ['enable_sourcing', 'disable_sourcing']) {
  test(`${action} submits only selected products and clears selection after success`, async () => {
    const app = await mount({ products: { ...defaults.products, data: [product, { ...product, id: 8 }, { ...product, id: 9 }] } });
    try {
      await app.select(7);
      await app.select(8);
      const menu = app.renderer.root.findByProps({ 'aria-label': 'Product bulk action' });
      assert.equal(menu.findByProps({ value: action }).children[0], action === 'enable_sourcing' ? 'Enable sourcing' : 'Disable sourcing');
      await app.change(menu, action);
      const apply = app.renderer.root.findAllByType('button').find(button => button.children[0] === 'Apply to selected');
      await app.click(apply);

      assert.equal(app.calls[0].method, 'post');
      assert.equal(app.calls[0].url, '/admin/products/bulk');
      assert.deepEqual(app.calls[0].data, { ids: [7, 8], bulk_action: action });
      await act(async () => app.calls[0].options.onStart());
      assert.equal(app.renderer.root.findByProps({ 'aria-label': 'Product bulk action' }).props.disabled, true);
      assert.equal(app.renderer.root.findAllByType('button').find(button => button.children[0] === 'Apply to selected').props.disabled, true);
      await app.click(app.renderer.root.findAllByType('button').find(button => button.children[0] === 'Apply to selected'));
      assert.equal(app.calls.length, 1);
      await act(async () => { app.calls[0].options.onSuccess(); app.calls[0].options.onFinish(); });
      assert.equal(app.renderer.root.findAllByProps({ 'aria-label': 'Product bulk action' }).length, 0);
    } finally { await app.close(); }
  });
}

test('delete-only staff cannot select sourcing bulk actions', async () => {
  const app = await mount({ canEdit: false, canDelete: true });
  try {
    await app.select(7);
    const menu = app.renderer.root.findByProps({ 'aria-label': 'Product bulk action' });
    assert.equal(menu.findAllByType('option').some(option => ['enable_sourcing', 'disable_sourcing'].includes(option.props.value)), false);
    assert.equal(menu.findByProps({ value: 'delete' }).children[0], 'Delete');
  } finally { await app.close(); }
});

test('shirt search excludes the sorted T-Shirt category without changing the search text', async () => {
  const app = await mount({ categories: [{ id: 2, name: 'Clothing', label: 'Clothing' }, { id: 3, name: 'T-Shirt', label: 'Clothing / T-Shirt', parent_id: 2 }] });
  try {
    const exclusion = app.renderer.root.findByProps({ 'aria-label': 'Exclude category' });
    assert.equal(exclusion.findByProps({ value: 3 }).children[0], 'Clothing / T-Shirt');
    await app.change(app.renderer.root.findByProps({ 'aria-label': 'Search products' }), 'Shirt');
    await app.change(exclusion, '3');
    await app.change(app.renderer.root.findByProps({ 'aria-label': 'Category scope' }), 'direct');
    await app.submit();
    assert.deepEqual(app.calls[0].data, { q: 'Shirt', category: 2, exclude_category: '3', category_scope: 'direct', status: 'published', sort: 'relevance', per_page: 15 });
    assert.equal(app.renderer.root.findAllByType('p').some(paragraph => paragraph.children.includes('You have unapplied changes. Apply filters to update results.')), true);
  } finally { await app.close(); }
});

test('clearing the source category resets direct scope and leaves text search available', async () => {
  const app = await mount({ filters: { ...defaults.filters, category_scope: 'direct' } });
  try {
    await app.change(app.renderer.root.findByProps({ 'aria-label': 'Category' }), '');
    const scope = app.renderer.root.findByProps({ 'aria-label': 'Category scope' });
    assert.equal(scope.props.value, 'all');
    assert.equal(scope.props.disabled, true);
    await app.submit();
    assert.deepEqual(app.calls[0].data, { q: 'Speaker', status: 'published', sort: 'relevance', per_page: 15 });
  } finally { await app.close(); }
});

test('default category scope does not mark an unfiltered catalog active', async () => {
  const app = await mount({ filters: {} });
  try {
    assert.equal(app.renderer.root.findByProps({ 'aria-label': 'Category scope' }).props.disabled, true);
    assert.equal(app.renderer.root.findAllByType('button').some(button => button.children[0] === 'Reset filters'), false);
  } finally { await app.close(); }
});

test('applied category exclusions and scope survive sorting rows and visibility and reset together', async () => {
  const app = await mount({ filters: { ...defaults.filters, exclude_category: 3, category_scope: 'direct' } });
  try {
    await app.change(app.renderer.root.findByProps({ 'aria-label': 'Exclude category' }), '9');
    await app.change(app.renderer.root.findByProps({ 'aria-label': 'Sort products' }), 'name');
    await app.change(app.renderer.root.findByProps({ 'aria-label': 'Products per page' }), '30');
    await app.click(app.renderer.root.findAllByType('button').find(button => button.findAllByType('p').some(paragraph => paragraph.children[0] === 'Draft')));
    for (const call of app.calls) {
      assert.equal(call.data.exclude_category, 3);
      assert.equal(call.data.category_scope, 'direct');
      assert.equal(call.data.category, 2);
    }
    assert.equal(app.calls[0].data.sort, 'name');
    assert.equal(app.calls[1].data.per_page, '30');
    assert.equal(app.calls[2].data.status, 'draft');
    const inventory = app.renderer.root.findAllByType('a').find(link => link.children[0] === 'Inventory');
    assert.equal(inventory.props.href.includes('exclude_category'), false);
    assert.equal(inventory.props.href.includes('category_scope'), false);
    await app.click(app.renderer.root.findAllByType('button').find(button => button.children[0] === 'Reset filters'));
    assert.deepEqual(app.calls[3].data, { sort: 'relevance', per_page: 15 });
  } finally { await app.close(); }
});

test('server changes to category exclusion or scope refresh controls and clear stale selections', async () => {
  const app = await mount();
  try {
    await app.select(7);
    await app.update({ filters: { ...defaults.filters, exclude_category: 3 } });
    assert.equal(app.renderer.root.findByProps({ 'aria-label': 'Exclude category' }).props.value, 3);
    assert.equal(app.renderer.root.findAllByProps({ 'aria-label': 'Product bulk action' }).length, 0);
    await app.select(7);
    await app.update({ filters: { ...defaults.filters, exclude_category: 3, category_scope: 'direct' } });
    assert.equal(app.renderer.root.findByProps({ 'aria-label': 'Category scope' }).props.value, 'direct');
    assert.equal(app.renderer.root.findAllByProps({ 'aria-label': 'Product bulk action' }).length, 0);
    await app.select(7);
    await app.submit();
    assert.equal(app.renderer.root.findAllByProps({ 'aria-label': 'Product bulk action' }).length, 0);
  } finally { await app.close(); }
});

test('pagination follows filtered server links and clears the current bulk selection', async () => {
  const nextPage = '/admin/products?q=Shirt&category=2&exclude_category=3&category_scope=direct&page=2';
  const app = await mount({ filters: { ...defaults.filters, q: 'Shirt', exclude_category: 3, category_scope: 'direct' }, products: { ...defaults.products, links: [{ url: nextPage, label: 'Next', active: false }] } });
  try {
    await app.select(7);
    await app.click(app.renderer.root.findByProps({ 'aria-label': 'Product pagination' }).findByType('button'));
    assert.equal(app.calls[0].url, nextPage);
    assert.deepEqual(app.calls[0].data, {});
    assert.equal(app.renderer.root.findAllByProps({ 'aria-label': 'Product bulk action' }).length, 0);
  } finally { await app.close(); }
});
