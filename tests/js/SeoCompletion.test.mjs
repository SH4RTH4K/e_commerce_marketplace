import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import vm from 'node:vm';
import React, { act } from 'react';
import { create } from 'react-test-renderer';
import { router, useForm } from '@inertiajs/react';
import { transformWithOxc } from 'vite';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
const filename = new URL('../../resources/js/Pages/Admin/SeoCompletion.jsx', import.meta.url);
const source = (await readFile(filename, 'utf8')).replace(/^import .*;\r?\n/gm, '').replace('export default function', 'function');
const { code } = await transformWithOxc(source, filename.pathname, { jsx: { runtime: 'classic' } });
const stats = { total: 3, incomplete: 2, meta_title: 1, meta_description: 2, meta_keywords: 1 };

async function mount(summary) {
  const calls = []; const original = router.post;
  router.post = (url, data, options) => calls.push({ url, data, options });
  const context = vm.createContext({ React, useForm, Head: () => null, AdminLayout: ({children}) => React.createElement('main', {}, children) });
  vm.runInContext(`${code}\nglobalThis.Component = SeoCompletion;`, context);
  let renderer;
  await act(async () => { renderer = create(React.createElement(context.Component, { summary })); });
  return { renderer, calls, close: async () => { await act(async () => renderer.unmount()); router.post = original; } };
}

test('SEO completion submits only an explicit fill-missing intent with the real form hook', async () => {
  const app = await mount({ categories: stats, products: stats });
  try {
    await act(async () => app.renderer.root.findByType('form').props.onSubmit({ preventDefault() {} }));
    assert.equal(app.calls[0].url, '/admin/settings/seo-completion');
    assert.equal(app.calls[0].data.fill_missing, true);
    assert.deepEqual(Object.keys(app.calls[0].data), ['fill_missing']);
  } finally { await app.close(); }
});

test('a complete catalog cannot be submitted again through the completion button', async () => {
  const complete = { ...stats, incomplete: 0, meta_title: 0, meta_description: 0, meta_keywords: 0 };
  const app = await mount({ categories: complete, products: complete });
  try { assert.equal(app.renderer.root.findByType('button').props.disabled, true); }
  finally { await app.close(); }
});
