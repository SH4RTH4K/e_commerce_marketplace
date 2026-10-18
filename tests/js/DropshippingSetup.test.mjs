import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import vm from 'node:vm';
import React, { act, useEffect, useRef, useState } from 'react';
import { create } from 'react-test-renderer';
import { transformWithOxc } from 'vite';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
const requests = [];
const router = Object.fromEntries(['get', 'post', 'patch', 'reload'].map(method => [method, (...args) => requests.push([method, ...args])]));
const Shell = ({ children }) => React.createElement('main', {}, children);
async function load(file, exported, extra = {}) {
  const filename = new URL(`../../resources/js/Pages/Admin/Dropshipping/${file}.jsx`, import.meta.url);
  const source = (await readFile(filename, 'utf8')).replace(/^import .*;\r?\n/gm, '').replaceAll('export default function', 'function').replaceAll('export function', 'function');
  const { code } = await transformWithOxc(source, filename.pathname, { jsx: { runtime: 'classic' } });
  const context = vm.createContext({ React, useState, useEffect, useRef, URLSearchParams, router, Head: () => null, AdminLayout: Shell, DropshippingSubpage: Shell,
    usePage: () => ({ props: {} }), window: { setInterval, clearInterval }, ...extra });
  vm.runInContext(`${code}\nglobalThis.result = { ${exported.join(',')} };`, context);
  return context.result;
}
async function mount(Component, props) {
  requests.length = 0;
  let rendered;
  await act(async () => { rendered = create(React.createElement(Component, props)); });
  return rendered;
}
const supplier = { id: 1, name: 'Mohasagor Bangladesh', key: 'main-supplier', driver_key: 'mohasagor', is_active: true,
  last_connection_status: 'success', products_count: 3101, imported_products_count: 1363, published_products_count: 1356,
  price_field_mapping: { cost_field: 'sale_price', maximum_field: 'price' }, category_mappings_count: 1,
  pricing_rules: { selling: {}, minimum: {}, maximum: {} }, sync_rules: { import_images: true, import_variations: true } };

test('saved supplier opens with actual counts and pending reviews without repeating setup or syncing on load', async () => {
  const { DropshippingIndex, setupSteps } = await load('Index', ['DropshippingIndex', 'setupSteps']);
  const steps = setupSteps({ ...supplier, imported_products_count: 0, published_products_count: 0, mapped_variants_count: 0 });
  assert.equal(steps.length, 4);
  assert.equal(steps[1].done, true);
  assert.equal(steps[2].done, false);
  const rendered = await mount(DropshippingIndex, { suppliers: [supplier], selected_supplier_id: 1 });
  try {
    const output = JSON.stringify(rendered.toJSON());
    for (const text of ['Mohasagor Bangladesh', '3,101', '1,363', '1,356', 'Review 7 unpublished products']) assert.ok(output.includes(text));
    const next = rendered.root.findAllByType('a').find(link => link.props.children === 'Continue →');
    assert.equal(next.props.href, '/admin/dropshipping/imported?supplier_id=1&status=draft');
    assert.equal(requests.length, 0);
  } finally { await act(async () => rendered.unmount()); }
});

test('connection form defaults to saved Mohasagor and submits updates with explicit disabled import flags', async () => {
  const { ApiSettings } = await load('ApiSettings', ['ApiSettings'], { FormData: class { constructor(form) { this.values = form.values; } [Symbol.iterator]() { return Object.entries(this.values)[Symbol.iterator](); } } });
  const rendered = await mount(ApiSettings, { suppliers: [supplier], selected_supplier_id: 1, available_drivers: ['mohasagor'] });
  try {
    const form = rendered.root.findAllByType('form').find(item => item.findAllByProps({ name: 'api_key' }).length > 0);
    assert.ok(form);
    assert.equal(form.findByProps({ name: 'name' }).props.defaultValue, 'Mohasagor Bangladesh');
    const advanced = rendered.root.findAllByType('details').filter(item => item.findAllByType('summary').some(summary => String(summary.props.children).includes('Advanced')));
    assert.ok(advanced.length >= 2);
    assert.ok(advanced.every(item => !item.props.open));
    await act(async () => form.props.onSubmit({ preventDefault() {}, currentTarget: { values: { name: supplier.name, driver_key: 'mohasagor', base_url: 'https://supplier.example', api_key: '', secret_key: '' }, elements: { import_images: { checked: false }, import_variations: { checked: true } } } }));
    assert.equal(requests[0][0], 'patch');
    assert.equal(requests[0][1], '/admin/dropshipping/suppliers/1');
    assert.equal(requests[0][2].import_images, 0);
    assert.equal(requests[0][2].import_variations, 1);
    assert.equal(requests[0][2].api_key, '');
  } finally { await act(async () => rendered.unmount()); }
});

test('catalog supplier selection persists through applying filters and changing pages', async () => {
  const { SupplierProducts } = await load('SupplierProducts', ['SupplierProducts']);
  const rendered = await mount(SupplierProducts, { suppliers: [], selected_supplier_id: 1, pagination: { current_page: 1, last_page: 2, total: 150 } });
  try {
    const filter = rendered.root.findByProps({ 'aria-label': 'Filter by supplier' });
    await act(async () => filter.props.onChange({ target: { value: '8' } }));
    await act(async () => rendered.root.findByType('form').props.onSubmit({ preventDefault() {} }));
    assert.ok(requests.at(-1)[1].includes('supplier_id=8'));
    const next = rendered.root.findAllByType('button').find(button => String(button.props.children).trim() === 'Next');
    await act(async () => next.props.onClick());
    assert.ok(requests.at(-1)[1].includes('supplier_id=8'));
    assert.ok(requests.at(-1)[1].includes('page=2'));
  } finally { await act(async () => rendered.unmount()); }
});

test('imported products keep supplier scope when clearing other filters', async () => {
  const { ImportedProducts } = await load('ImportedProducts', ['ImportedProducts']);
  const rendered = await mount(ImportedProducts, { suppliers: [supplier], selected_supplier_id: 1 });
  try {
    assert.equal(rendered.root.findByProps({ 'aria-label': 'Filter by supplier' }).props.value, '1');
    const clear = rendered.root.findAllByType('button').find(button => button.props.children === 'Clear');
    await act(async () => clear.props.onClick());
    assert.ok(requests.at(-1)[1].includes('supplier_id=1'));
  } finally { await act(async () => rendered.unmount()); }
});

test('workflow navigation keeps the selected supplier when moving between setup pages', async () => {
  const { DropshippingSubpage } = await load('Subpage', ['DropshippingSubpage'], { usePage: () => ({ url: '/admin/dropshipping/settings?supplier_id=1', props: { selected_supplier_id: 1, suppliers: [supplier] } }) });
  const rendered = await mount(DropshippingSubpage, { title: 'Connection' });
  try {
    const nav = rendered.root.findByProps({ 'aria-label': 'Dropshipping workflow' });
    assert.ok(nav.findAllByType('a').every(link => link.props.href.includes('supplier_id=1')));
    assert.ok(JSON.stringify(rendered.toJSON()).includes('Mohasagor Bangladesh'));
  } finally { await act(async () => rendered.unmount()); }
});

test('unconfigured dropshipping suppliers have a direct setup action beside the saved connection', async () => {
  const { DropshippingIndex } = await load('Index', ['DropshippingIndex']);
  const rendered = await mount(DropshippingIndex, { suppliers: [supplier], unconnected_suppliers: [{ id: 2, name: 'Test Supplier', supplier_code: '0001' }] });
  try {
    const action = rendered.root.findByProps({ href: '/admin/dropshipping/settings?connect_supplier_id=2' });
    assert.ok(action);
    const output = JSON.stringify(rendered.toJSON());
    assert.ok(output.includes('Test Supplier'));
    assert.ok(output.includes('API not configured'));
    assert.ok(output.includes('Mohasagor Bangladesh'));
    assert.equal(requests.length, 0);
  } finally { await act(async () => rendered.unmount()); }
});

test('setup link selects the existing business supplier and saves that supplier instead of another account', async () => {
  const { ApiSettings } = await load('ApiSettings', ['ApiSettings'], { FormData: class { constructor(form) { this.values = form.values; } [Symbol.iterator]() { return Object.entries(this.values)[Symbol.iterator](); } } });
  const target = { id: 2, name: 'Test Supplier', supplier_code: '0001', supplier_type: 'dropshipping' };
  const newerConnection = { ...supplier, id: 99, name: 'Newest saved account' };
  const rendered = await mount(ApiSettings, { suppliers: [newerConnection, supplier], selected_supplier_id: null, connect_supplier_id: 2, unconnected_suppliers: [target], available_drivers: ['mohasagor'] });
  try {
    const form = rendered.root.findAllByType('form').find(item => item.findAllByProps({ name: 'api_key' }).length > 0);
    assert.equal(form.findByProps({ name: 'supplier_id' }).props.value, '2');
    assert.equal(form.findByProps({ name: 'name' }).props.defaultValue, 'Test Supplier');
    assert.ok(JSON.stringify(rendered.toJSON()).includes('Connect Test Supplier'));
    await act(async () => form.props.onSubmit({ preventDefault() {}, currentTarget: { values: { supplier_id: '2', name: target.name, driver_key: 'mohasagor', base_url: 'https://supplier.example', api_key: 'new-key', secret_key: 'new-secret' }, elements: { import_images: { checked: false }, import_variations: { checked: true } } } }));
    assert.equal(requests[0][0], 'post');
    assert.equal(requests[0][1], '/admin/dropshipping/suppliers');
    assert.equal(requests[0][2].supplier_id, '2');
    await act(async () => requests[0][3].onSuccess({ props: { selected_supplier_id: 2, suppliers: [newerConnection, { ...target, driver_key: 'mohasagor' }, supplier] } }));
    const account = rendered.root.findAllByType('select').find(select => select.props.value === '2' && select.findAllByType('option').some(option => option.props.value === ''));
    assert.ok(account, 'the saved target remains selected even when another account has a greater ID');
  } finally { await act(async () => rendered.unmount()); }
});

test('pending setup keeps the target supplier visible and prevents navigation into another suppliers products', async () => {
  const { DropshippingSubpage } = await load('Subpage', ['DropshippingSubpage'], { usePage: () => ({ url: '/admin/dropshipping/settings?connect_supplier_id=2', props: { suppliers: [supplier], connect_supplier_id: 2 } }) });
  const rendered = await mount(DropshippingSubpage, { title: 'Connection', supplier: { id: 2, name: 'Test Supplier' }, connectionPending: true });
  try {
    const nav = rendered.root.findByProps({ 'aria-label': 'Dropshipping workflow' });
    assert.equal(nav.findAllByType('a').length, 1);
    assert.equal(nav.findByType('a').props.href, '/admin/dropshipping/settings?connect_supplier_id=2');
    assert.equal(nav.findAllByProps({ 'aria-disabled': 'true' }).length, 4);
    assert.ok(JSON.stringify(rendered.toJSON()).includes('Test Supplier'));
    assert.equal(rendered.root.findAllByType('a').find(link => link.props.children === '← Dropshipping overview').props.href, '/admin/dropshipping');
  } finally { await act(async () => rendered.unmount()); }
});
