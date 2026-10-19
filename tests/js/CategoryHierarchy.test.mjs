import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import vm from 'node:vm';
import React, { act, useEffect, useRef, useState } from 'react';
import { create } from 'react-test-renderer';
import { transformWithOxc } from 'vite';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
async function compile(relative) {
  const filename = new URL(relative, import.meta.url);
  const source = (await readFile(filename, 'utf8')).replace(/^import .*;\r?\n/gm, '').replaceAll('export default function', 'function').replaceAll('export function', 'function');
  return (await transformWithOxc(source, filename.pathname, { jsx: { runtime: 'classic' } })).code;
}
const picker = await compile('../../resources/js/Components/Admin/CategoryPicker.jsx');
const navigation = await compile('../../resources/js/Components/Storefront/CategoryNavigation.jsx');
const options = [
  { id: 1, name: 'Electronics', parent_id: null, path_ids: [1] },
  { id: 2, name: 'Audio', parent_id: 1, path_ids: [1, 2] },
  { id: 3, name: 'Earbuds', parent_id: 2, path_ids: [1, 2, 3] },
  { id: 4, name: 'Home', parent_id: null, path_ids: [4] },
];
const category = { ...options[0], slug: 'electronics', children: [{ ...options[1], slug: 'audio', children: [{ ...options[2], slug: 'earbuds', children: [] }] }] };

async function mount(component, props) {
  const handlers = new Map(); let focused = false;
  const context = vm.createContext({ React, useEffect, useRef, useState,
    Link: ({ children, ...props }) => React.createElement('a', props, children),
    document: { activeElement: null, addEventListener: (name, fn) => handlers.set(name, fn), removeEventListener: name => handlers.delete(name) },
  });
  vm.runInContext(`${picker}\n${navigation}\nglobalThis.Component = ${component}; globalThis.hasActive = categoryHasActive;`, context);
  let renderer; let current = props;
  await act(async () => { renderer = create(React.createElement(context.Component, props), { createNodeMock: () => ({ contains: () => false, focus: () => { focused = true; } }) }); });
  return { renderer, context, handlers, focused: () => focused,
    update: async next => { current = { ...current, ...next }; await act(async () => renderer.update(React.createElement(context.Component, current))); },
    close: async () => { await act(async () => renderer.unmount()); },
  };
}

test('product category picker supports assignment at every layer and clears dependent layers on ancestor changes', async () => {
  const changes = []; const app = await mount('CategoryPicker', { categories: options, value: '3', onChange: value => changes.push(value) });
  try {
    assert.deepEqual(app.renderer.root.findAllByType('select').map(select => select.props.value), [1, 2, 3]);
    const change = async (index, value) => { await act(async () => app.renderer.root.findAllByType('select')[index].props.onChange({ target: { value } })); };
    await change(2, ''); assert.equal(changes.pop(), 2);
    await change(1, ''); assert.equal(changes.pop(), 1);
    await change(0, '4'); assert.equal(changes.pop(), '4');
    await app.update({ value: '4' });
    assert.deepEqual(app.renderer.root.findAllByType('select').map(select => select.props.value), [4, '', '']);
    assert.equal(app.renderer.root.findAllByType('select')[1].props.disabled, true);
    assert.equal(app.renderer.root.findAllByType('select')[2].props.disabled, true);
    await app.update({ value: '1' }); await change(1, '2'); assert.equal(changes.pop(), '2');
    await app.update({ value: '2' }); await change(2, '3'); assert.equal(changes.pop(), '3');
  } finally { await app.close(); }
});

test('desktop branch menu exposes all levels, highlights ancestors, and closes with Escape or outside interaction', async () => {
  const app = await mount('DesktopCategoryItem', { category, activeId: '3' });
  try {
    assert.equal(app.context.hasActive(category, '3'), true);
    assert.equal(app.renderer.root.findByType('a').props.href, '/category/electronics');
    assert.equal(app.renderer.root.findByType('a').props['aria-current'], 'true');
    await act(async () => app.renderer.root.findByType('button').props.onClick());
    assert.equal(app.renderer.root.findByType('button').props['aria-expanded'], true);
    const anchors = app.renderer.root.findAllByType('a');
    assert.ok(anchors.some(link => link.props.href === '/category/audio'));
    assert.ok(anchors.some(link => link.props.href === '/category/earbuds' && link.props['aria-current'] === 'page'));
    await act(async () => app.renderer.root.findAllByType('div').find(div => div.props.onKeyDown).props.onKeyDown({ key: 'Escape' }));
    assert.equal(app.renderer.root.findByType('button').props['aria-expanded'], false); assert.equal(app.focused(), true);
    await act(async () => app.renderer.root.findByType('button').props.onClick());
    await act(async () => app.handlers.get('pointerdown')({ target: {} }));
    assert.equal(app.renderer.root.findByType('button').props['aria-expanded'], false);
  } finally { await app.close(); }
});

test('mobile menu progressively expands levels and opens the current branch automatically', async () => {
  const app = await mount('MobileCategoryLinks', { categories: [category], activeId: null });
  try {
    assert.equal(app.renderer.root.findAllByType('a').length, 1);
    await act(async () => app.renderer.root.findByType('button').props.onClick());
    assert.equal(app.renderer.root.findAllByType('a').length, 2);
    await act(async () => app.renderer.root.findAllByType('button')[1].props.onClick());
    assert.equal(app.renderer.root.findAllByType('a').length, 3);
    await act(async () => app.renderer.root.findAllByType('button')[0].props.onClick());
    assert.equal(app.renderer.root.findAllByType('a').length, 1);
    await app.update({ activeId: 3 });
    assert.equal(app.renderer.root.findAllByType('a').length, 3);
    assert.equal(app.renderer.root.findAllByType('a')[2].props['aria-current'], 'page');
  } finally { await app.close(); }
});

test('category links prefetch on hover with short-lived data and dismiss the dropdown on selection', async () => {
  const app = await mount('DesktopCategoryItem', { category, activeId: null });
  try {
    await act(async () => app.renderer.root.findByType('button').props.onClick());
    const link = app.renderer.root.findAllByType('a').find(anchor => anchor.props.href === '/category/earbuds');
    assert.equal(link.props.prefetch, 'hover'); assert.equal(link.props.cacheFor, '10s');
    await act(async () => link.props.onClick());
    assert.equal(app.renderer.root.findByType('button').props['aria-expanded'], false);
  } finally { await app.close(); }
});
