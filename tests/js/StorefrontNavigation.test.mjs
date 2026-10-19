import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import vm from 'node:vm';
import React, { act, useEffect, useRef, useState } from 'react';
import { create } from 'react-test-renderer';
import { transformWithOxc } from 'vite';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
const filename = new URL('../../resources/js/Components/Storefront/PageTransition.jsx', import.meta.url);
const source = (await readFile(filename, 'utf8')).replace(/^import .*;\r?\n/gm, '').replaceAll('export default function', 'function').replaceAll('export function', 'function');
const { code } = await transformWithOxc(source, filename.pathname, { jsx: { runtime: 'classic' } });

async function mount({ pathname = '/', prefetch = null } = {}) {
  const handlers = new Map(); let navigations = 0;
  const context = vm.createContext({ React, useEffect, useRef, useState, URL,
    window: { location: { origin: 'https://shop.test', pathname } },
    router: {
      on: (name, callback) => { handlers.set(name, callback); return () => handlers.delete(name); },
      getPrefetching: () => prefetch,
    },
  });
  vm.runInContext(`${code}\nglobalThis.Component = PageTransition;`, context);
  let renderer;
  await act(async () => {
    renderer = create(React.createElement(context.Component, {
      categories: [{ slug: 'fashion', name: 'Fashion', children: [{ slug: 'shirt', name: 'Shirt' }] }],
      onNavigate: () => { navigations++; },
    }, React.createElement('p', { id: 'original-content' }, 'Current page')));
  });
  return { renderer, handlers, navigations: () => navigations,
    visit: (id, overrides = {}) => ({ id, url: new URL('https://shop.test/category/shirt'), method: 'get', only: [], except: [], reset: [], ...overrides }),
    emit: async (name, detail, event = {}) => { await act(async () => handlers.get(name)?.({ detail, ...event })); },
    busy: () => renderer.root.findAllByProps({ 'aria-busy': true }).length > 0,
    skeleton: () => renderer.root.findAllByType('section').length > 0,
    original: () => renderer.root.findAllByProps({ id: 'original-content' }).length > 0,
    close: async () => { await act(async () => renderer.unmount()); },
  };
}

test('category clicks replace old content with a named accessible product skeleton and close menus immediately', async () => {
  const app = await mount(); const visit = app.visit('shirt');
  try {
    await app.emit('before', { visit });
    assert.equal(app.navigations(), 1); assert.equal(app.busy(), true); assert.equal(app.original(), false);
    assert.equal(app.renderer.root.findByType('h1').children[0], 'Shirt');
    assert.equal(app.renderer.root.findByProps({ role: 'status' }).children.includes('Loading Shirt products'), true);
    assert.equal(app.renderer.root.findAllByProps({ className: 'storefront-loading-card' }).length, 10);
    await app.emit('finish', { visit });
    assert.equal(app.busy(), false); assert.equal(app.original(), true);
  } finally { await app.close(); }
});

test('hover prefetch stays invisible and cached navigation completes without start or finish events', async () => {
  const app = await mount();
  try {
    const prefetch = app.visit('prefetch', { prefetch: true });
    await app.emit('before', { visit: prefetch }); await app.emit('start', { visit: prefetch });
    assert.equal(app.busy(), false); assert.equal(app.navigations(), 0);
    const visit = app.visit('cached'); await app.emit('before', { visit });
    assert.equal(app.skeleton(), true);
    await app.emit('finish', { visit: prefetch }); assert.equal(app.busy(), true);
    await app.emit('navigate', { visitId: visit.id, cached: true });
    assert.equal(app.busy(), false); assert.equal(app.original(), true);
  } finally { await app.close(); }
});

test('an interrupted old request cannot clear a newer category transition', async () => {
  const app = await mount();
  try {
    const old = app.visit('old'); const next = app.visit('next', { url: new URL('https://shop.test/category/fashion') });
    await app.emit('before', { visit: old }); await app.emit('before', { visit: next });
    await app.emit('finish', { visit: old }); await app.emit('error', { visitId: old.id });
    assert.equal(app.busy(), true); assert.equal(app.renderer.root.findByType('h1').children[0], 'Fashion');
    await app.emit('success', { visitId: next.id }); assert.equal(app.busy(), false);
  } finally { await app.close(); }
});

test('same-category filtering retains the page and background pagination or mutations keep their own feedback', async () => {
  const app = await mount({ pathname: '/category/shirt' });
  try {
    const visit = app.visit('sort', { url: new URL('https://shop.test/category/shirt?sort=newest') });
    await app.emit('before', { visit });
    assert.equal(app.busy(), true); assert.equal(app.original(), true); assert.equal(app.skeleton(), false);
    await app.emit('finish', { visit });
    for (const overrides of [{ only: ['products'] }, { async: true }, { method: 'post' }]) {
      await app.emit('before', { visit: app.visit('background', overrides) }); assert.equal(app.busy(), false);
    }
  } finally { await app.close(); }
});

test('cancelled visits and failed in-flight prefetch consumption restore usable content', async () => {
  let rejectPrefetch;
  const response = new Promise((_resolve, reject) => { rejectPrefetch = reject; });
  const app = await mount({ prefetch: { response } });
  try {
    const cancelled = app.visit('cancelled');
    const event = { detail: { visit: cancelled }, defaultPrevented: false };
    await act(async () => { app.handlers.get('before')(event); event.defaultPrevented = true; });
    assert.equal(app.busy(), false);
    await app.emit('before', { visit: app.visit('network') });
    await act(async () => { rejectPrefetch(new Error('Network disconnected')); await Promise.resolve(); });
    assert.equal(app.busy(), false); assert.equal(app.original(), true);
  } finally { await app.close(); }
  assert.equal(app.handlers.size, 0);
});

test('HTTP and network failures release loading feedback instead of leaving a permanent skeleton', async () => {
  const app = await mount();
  try {
    await app.emit('before', { visit: app.visit('http') }); await app.emit('httpException', { response: { status: 500 } });
    assert.equal(app.busy(), false);
    await app.emit('before', { visit: app.visit('network') }); await app.emit('networkError', { error: { url: 'https://shop.test/category/shirt' } });
    assert.equal(app.busy(), false);
  } finally { await app.close(); }
});
