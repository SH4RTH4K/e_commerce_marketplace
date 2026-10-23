import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import vm from 'node:vm';
import React, { act, useState } from 'react';
import { create } from 'react-test-renderer';
import { useForm, router } from '@inertiajs/react';
import { transformWithOxc } from 'vite';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
globalThis.window = { setTimeout: () => 0 };
const filename = new URL('../../resources/js/Pages/Admin/Products/Form.jsx', import.meta.url);
const source = (await readFile(filename, 'utf8')).replace(/^import .*;\r?\n/gm, '').replace('export default function', 'function');
const { code } = await transformWithOxc(source, filename.pathname, { jsx: { runtime: 'classic' } });
const QuickSupplierDialog = () => React.createElement('aside', {}, 'Quick supplier');

async function mount(overrides = {}) {
  const context = vm.createContext({ React, useState, useForm, router, window: globalThis.window,
    Head: () => null, AdminLayout: ({ children }) => React.createElement('main', {}, children),
    RichTextEditor: () => null, CategoryPicker: () => null, ProductPricingFields: () => null, QuickSupplierDialog,
    pricingValues: () => ({ pricing_mode: 'manual', regular_price: 500, sale_price: '', selling_price: 500 }), pricingConflicts: () => [],
  });
  vm.runInContext(`${code}\nglobalThis.Component = ProductForm;`, context);
  let renderer;
  await act(async () => { renderer = create(React.createElement(context.Component, {
    product: { id: 1, name: 'Existing manual product', stock_quantity: 10, source_type: 'manual', images: [], variants: [] },
    categories: [], suppliers: [{ id: 1, name: 'API Business', supplier_type: 'dropshipping', is_active: true }, { id: 2, name: 'Inactive', is_active: false }], canManageSuppliers: true, ...overrides,
  })); });
  return { renderer, close: async () => { await act(async () => renderer.unmount()); } };
}

test('quick supplier creation preserves unsaved product name and source while selecting new supplier', async () => {
  const app = await mount();
  try {
    const name = () => app.renderer.root.findByProps({ placeholder: 'e.g. Premium Cotton Casual Shirt' });
    await act(async () => name().props.onChange({ target: { value: 'Unsaved product name' } }));
    const add = app.renderer.root.findAllByType('button').find(button => button.children[0] === '+ Add New Supplier');
    await act(async () => add.props.onClick());
    await act(async () => app.renderer.root.findByType(QuickSupplierDialog).props.onCreated({ id: 3, name: 'Quick supplier', supplier_type: 'local', is_active: true }));
    assert.equal(name().props.value, 'Unsaved product name');
    assert.equal(app.renderer.root.findByProps({ 'aria-label': 'Product supplier' }).props.value, 3);
    assert.equal(app.renderer.root.findByProps({ 'aria-label': 'Product source' }).props.value, 'manual');
    assert.equal(app.renderer.root.findAllByType(QuickSupplierDialog).length, 0);
  } finally { await app.close(); }
});

test('choosing a dropshipping business supplier does not convert manual source or expose inactive assignments', async () => {
  const app = await mount();
  try {
    const selector = app.renderer.root.findByProps({ 'aria-label': 'Product supplier' });
    assert.equal(selector.findAllByType('option').length, 2);
    await act(async () => selector.props.onChange({ target: { value: '1' } }));
    assert.equal(app.renderer.root.findByProps({ 'aria-label': 'Product source' }).props.value, 'manual');
    assert.equal(app.renderer.root.findByProps({ 'aria-label': 'Product supplier' }).props.value, '1');
  } finally { await app.close(); }
});

test('integration ownership is read-only in the normal editor and shows external reference', async () => {
  const app = await mount({ assignment: { locked: true, integration: 'API Business', external_product_id: 'EXT-3002', stock_source: 'Supplier-managed availability' }, product: { id: 8, name: 'Imported', supplier_id: 1, source_type: 'dropshipping' } });
  try {
    assert.equal(app.renderer.root.findByProps({ 'aria-label': 'Product source' }).props.disabled, true);
    assert.equal(app.renderer.root.findByProps({ 'aria-label': 'Product supplier' }).props.disabled, true);
    assert.equal(app.renderer.root.findAllByType('button').some(button => button.children[0] === '+ Add New Supplier'), false);
    assert.match(JSON.stringify(app.renderer.toJSON()), /EXT-3002/);
  } finally { await app.close(); }
});

test('sourcing switch is independent of supplier ownership and does not change actual inventory', async () => {
  const app = await mount({ assignment: { locked: true }, product: { id: 8, name: 'Imported', stock_quantity: 0, allow_backorder: false, sourced_stock_quantity: 2, supplier_id: 1, source_type: 'dropshipping' } });
  try {
    const toggle = () => app.renderer.root.findByProps({ 'aria-label': 'Allow orders when out of stock' });
    assert.equal(toggle().props.checked, false);
    await act(async () => toggle().props.onChange({ target: { checked: true } }));
    assert.equal(toggle().props.checked, true);
    assert.equal(app.renderer.root.findByProps({ placeholder: '0' }).props.value, 0);
    assert.match(JSON.stringify(app.renderer.toJSON()), /Locally received stock available/);
    assert.equal(app.renderer.root.findAllByType('input').some(input => input.props.name === 'sourced_stock_quantity'), false);
    await act(async () => toggle().props.onChange({ target: { checked: false } }));
    assert.equal(toggle().props.checked, false);
  } finally { await app.close(); }
});
