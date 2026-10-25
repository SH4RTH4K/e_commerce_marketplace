import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import vm from 'node:vm';
import React, { act, useState } from 'react';
import { create } from 'react-test-renderer';
import { transformWithOxc } from 'vite';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
const filename = new URL('../../resources/js/Components/Admin/PermissionMatrix.jsx', import.meta.url);
const source = (await readFile(filename, 'utf8')).replace(/^import .*;\r?\n/gm, '').replace('export default function', 'function');
const { code } = await transformWithOxc(source, filename.pathname, { jsx: { runtime: 'classic' } });
const context = vm.createContext({ React, useState });
vm.runInContext(`${code}\nglobalThis.Component = PermissionMatrix;`, context);
const groups = {
  products: { label: 'Products', items: { 'products.view': 'View', 'products.add': 'Add', 'products.edit': 'Edit', 'products.delete': 'Delete' } },
  sync_runs: { label: 'Sync Runs', items: { 'sync_runs.view': 'View', 'sync_runs.execute': 'Execute' } },
  roles: { label: 'Roles', super_admin_only: true, items: { 'roles.view': 'View', 'roles.add': 'Add' } },
};

test('the matrix keeps add, edit, delete and execute independent', async () => {
  const changes = [];
  let renderer;
  await act(async () => { renderer = create(React.createElement(context.Component, { groups, value: ['products.view'], onChange: value => changes.push([...value]) })); });
  try {
    await act(async () => renderer.root.findByProps({ 'aria-label': 'Products: Add' }).props.onChange());
    assert.deepEqual(changes[0], ['products.view', 'products.add']);
    assert.equal(renderer.root.findByProps({ 'aria-label': 'Products: Edit' }).props.checked, false);
    assert.equal(renderer.root.findByProps({ 'aria-label': 'Products: Delete' }).props.checked, false);
    assert.equal(renderer.root.findByProps({ 'aria-label': 'Sync Runs: Execute' }).props.checked, false);
    assert.equal(renderer.root.findAllByProps({ 'aria-label': 'Roles: Add' }).length, 0);
  } finally { await act(async () => renderer.unmount()); }
});

test('search and select shown cannot grant hidden functions or protected roles', async () => {
  const changes = [];
  let renderer;
  await act(async () => { renderer = create(React.createElement(context.Component, { groups, value: [], onChange: value => changes.push([...value]) })); });
  try {
    await act(async () => renderer.root.findByProps({ 'aria-label': 'Search permission functions' }).props.onChange({ target: { value: 'Sync' } }));
    assert.equal(renderer.root.findAllByProps({ 'aria-label': 'Products: Add' }).length, 0);
    await act(async () => renderer.root.findByType('button').props.onClick());
    assert.deepEqual(changes[0], ['sync_runs.view', 'sync_runs.execute']);
  } finally { await act(async () => renderer.unmount()); }
});

test('permission display for delegated user management has disabled inputs', async () => {
  let renderer;
  await act(async () => { renderer = create(React.createElement(context.Component, { groups, value: [], onChange() {}, disabled: true })); });
  try {
    assert.ok(renderer.root.findAllByType('input').filter(input => input.props.type === 'checkbox').every(input => input.props.disabled));
    assert.equal(renderer.root.findByType('button').props.disabled, true);
  } finally { await act(async () => renderer.unmount()); }
});
