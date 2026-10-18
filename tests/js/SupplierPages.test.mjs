import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import vm from 'node:vm';
import React, { act, useEffect, useState } from 'react';
import { create } from 'react-test-renderer';
import { transformWithOxc } from 'vite';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
globalThis.window = {};

async function load(relative, globals, exported) {
  const filename = new URL(`../../resources/js/${relative}`, import.meta.url);
  const source = (await readFile(filename, 'utf8')).replace(/^\uFEFF/, '').replace(/^import .*;\r?\n/gm, '')
    .replaceAll('export default function', 'function').replaceAll('export function', 'function').replaceAll('export const', 'const');
  const { code } = await transformWithOxc(source, filename.pathname, { jsx: { runtime: 'classic' } });
  const context = vm.createContext({ React, useEffect, useState, URLSearchParams, window: globalThis.window, ...globals });
  vm.runInContext(`${code}\n globalThis.result = {${exported.join(',')}};`, context);
  return context.result;
}
const router = { get() {}, patch() {} };
const shared = await load('Pages/Admin/Suppliers/Shared.jsx', { router }, ['buttonClass', 'inputClass', 'labels', 'formatDate', 'Pagination', 'Status', 'SupplierFilters', 'ToggleSupplier']);
const base = { ...shared, Head: () => null, AdminLayout: ({ children }) => React.createElement('main', {}, children) };
const supplier = { id: 12, name: 'Local Trading', supplier_code: 'SUP-00012', supplier_type: 'local', is_active: true, products_count: 1, mobile: '01712345678', whatsapp: '01812345678', business_address: 'Market Road', notes: 'Delivery contact', trade_license: 'TL-12' };
const page = { data: [], total: 0, last_page: 1 };
const text = renderer => JSON.stringify(renderer.toJSON());

async function mount(Component, props) {
  let renderer;
  await act(async () => { renderer = create(React.createElement(Component, props)); });
  return renderer;
}

test('supplier pages render complete data and independent paginated sections without sync-run props', async () => {
  const { SupplierShow } = await load('Pages/Admin/Suppliers/Show.jsx', base, ['SupplierShow']);
  const renderer = await mount(SupplierShow, { supplier, metrics: { products: 1, manual: 1, inventory_references: 1 },
    products: { ...page, data: [{ id: 7, name: 'Mouse', stock_quantity: 24, source_type: 'manual' }] },
    history: { ...page, data: [{ id: 1, product_name: 'Mouse', operation: 'add', movement_type: 'stock_in', stock_before: 4, stock_after: 24, change: 20, reason: 'Received', reference: 'GRN-1', stock_scope: 'local' }] },
    audits: { ...page, data: [{ id: 2, event: 'updated', changed_fields: ['mobile'], actor: 'Manager' }] } });
  try {
    for (const value of ['Market Road', '01812345678', 'TL-12', 'Delivery contact', 'GRN-1', 'Manager']) assert.ok(text(renderer).includes(value));
    assert.equal(renderer.root.findAllByProps({ href: '/admin/suppliers/12/edit' }).length, 0);
    assert.ok(renderer.root.findByProps({ id: 'products' }));
    assert.ok(renderer.root.findByProps({ id: 'inventory-history' }));
  } finally { await act(async () => renderer.unmount()); }
});

test('supplier list respects view-only access and report CSV keeps the selected supplier filter', async () => {
  const { SupplierIndex } = await load('Pages/Admin/Suppliers/Index.jsx', base, ['SupplierIndex']);
  const list = await mount(SupplierIndex, { suppliers: { ...page, data: [supplier], total: 1 }, canManage: false });
  try {
    assert.equal(list.root.findAllByProps({ href: '/admin/suppliers/create' }).length, 0);
    assert.ok(text(list).includes('01712345678'));
  } finally { await act(async () => list.unmount()); }
  const { SupplierReport } = await load('Pages/Admin/Suppliers/Report.jsx', base, ['SupplierReport']);
  const report = await mount(SupplierReport, { suppliers: page, filters: { supplier_id: 12, type: 'local', q: 'Trading' } });
  try {
    const url = report.root.findAllByType('a').find(link => link.props.href.includes('format=csv')).props.href;
    assert.ok(url.includes('supplier_id=12'));
    assert.ok(url.includes('type=local'));
    assert.ok(url.includes('q=Trading'));
  } finally { await act(async () => report.unmount()); }
});

test('suppliers navigation remains available when dropshipping is disabled and restores integration dashboard separately', async () => {
  const props = { auth: { user: { name: 'Manager', role: 'staff', permissions: ['suppliers.manage'] } }, dropshipping_enabled: false };
  const { AdminLayout } = await load('Layouts/AdminLayout.jsx', { usePage: () => ({ url: '/admin/suppliers', props }), router,
    orderSegments: [], activeOrderSegment: () => '', orderSegmentHref: () => '', customerSegments: [], activeCustomerSegment: () => '' }, ['AdminLayout']);
  const renderer = await mount(AdminLayout, {});
  try {
    assert.ok(renderer.root.findByProps({ href: '/admin/suppliers' }));
    assert.ok(renderer.root.findByProps({ href: '/admin/suppliers/create' }));
    assert.ok(renderer.root.findByProps({ href: '/admin/suppliers/report' }));
    assert.equal(renderer.root.findAllByProps({ href: '/admin/dropshipping' }).length, 0);
  } finally { await act(async () => renderer.unmount()); }
  props.dropshipping_enabled = true;
  props.auth.user.permissions = ['dropshipping.manage'];
  const integration = await mount(AdminLayout, {});
  try {
    assert.ok(integration.root.findByProps({ href: '/admin/dropshipping' }));
    assert.equal(integration.root.findAllByProps({ href: '/admin/suppliers' }).length, 0);
  } finally { await act(async () => integration.unmount()); }
});

test('saved dropshipping suppliers offer direct API setup across list, details and edit pages', async () => {
  const dropship = { ...supplier, supplier_type: 'dropshipping' };
  const useForm = initial => ({ data: initial, errors: {}, setData() {}, post() {}, patch() {} });
  const { SupplierForm } = await load('Pages/Admin/Suppliers/Form.jsx', { ...base, useForm }, ['SupplierForm']);
  const { SupplierShow } = await load('Pages/Admin/Suppliers/Show.jsx', base, ['SupplierShow']);
  const { SupplierIndex } = await load('Pages/Admin/Suppliers/Index.jsx', base, ['SupplierIndex']);
  for (const [Component, props] of [[SupplierForm, { supplier: dropship }], [SupplierShow, { supplier: dropship }],
    [SupplierIndex, { suppliers: { ...page, data: [dropship] } }]]) {
    const renderer = await mount(Component, { ...props, canManageDropshipping: true });
    try {
      assert.equal(renderer.root.findByProps({ href: '/admin/dropshipping/settings?connect_supplier_id=12' }).children.join(''), 'Set up API connection');
    } finally { await act(async () => renderer.unmount()); }
    for (const limitedProps of [{ canManageDropshipping: false }, { canManageDropshipping: true,
      ...(Component === SupplierIndex ? { suppliers: { ...page, data: [{ ...dropship, is_active: false }] } } : { supplier: { ...dropship, is_active: false } }) }]) {
      const limited = await mount(Component, { ...props, ...limitedProps });
      try { assert.equal(limited.root.findAllByProps({ href: '/admin/dropshipping/settings?connect_supplier_id=12' }).length, 0); }
      finally { await act(async () => limited.unmount()); }
    }
  }
});

test('new unsaved dropshipping suppliers explain save-first workflow without a connection navigation link', async () => {
  const useForm = initial => {
    const [data, update] = useState(initial);
    return { data, setData: (key, value) => update(previous => ({ ...previous, [key]: value })), errors: {}, post() {}, patch() {} };
  };
  const { SupplierForm } = await load('Pages/Admin/Suppliers/Form.jsx', { ...base, useForm }, ['SupplierForm']);
  const renderer = await mount(SupplierForm, { supplier: null, types: ['local', 'dropshipping'], canManageDropshipping: true });
  try {
    await act(async () => renderer.root.findByType('select').props.onChange({ target: { value: 'dropshipping' } }));
    assert.ok(text(renderer).includes('Save this supplier first'));
    assert.ok(text(renderer).includes('Manual dropshipping works without an API'));
    assert.equal(renderer.root.findAllByType('a').filter(link => link.props.href.includes('/admin/dropshipping')).length, 0);
  } finally { await act(async () => renderer.unmount()); }
});

test('connected suppliers open their existing API settings and do not offer another setup connection', async () => {
  const connected = { ...supplier, supplier_type: 'dropshipping', integration: 'mohasagor Dropshipping' };
  const useForm = initial => ({ data: initial, errors: {}, setData() {}, post() {}, patch() {} });
  const { SupplierForm } = await load('Pages/Admin/Suppliers/Form.jsx', { ...base, useForm }, ['SupplierForm']);
  const { SupplierShow } = await load('Pages/Admin/Suppliers/Show.jsx', base, ['SupplierShow']);
  const { SupplierIndex } = await load('Pages/Admin/Suppliers/Index.jsx', base, ['SupplierIndex']);
  for (const [Component, props] of [[SupplierForm, { supplier: connected }], [SupplierShow, { supplier: connected }],
    [SupplierIndex, { suppliers: { ...page, data: [connected] } }]]) {
    const renderer = await mount(Component, { ...props, canManageDropshipping: true });
    try {
      assert.ok(renderer.root.findByProps({ href: '/admin/dropshipping/settings?supplier_id=12' }));
      assert.equal(renderer.root.findAllByProps({ href: '/admin/dropshipping/settings?connect_supplier_id=12' }).length, 0);
    } finally { await act(async () => renderer.unmount()); }
  }
});
