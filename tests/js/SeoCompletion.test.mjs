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

async function mount(summary, englishNames = [], fetcher = globalThis.fetch) {
  const calls = []; const original = router.post;
  router.post = (url, data, options) => calls.push({ url, data, options });
  const context = vm.createContext({ React, useForm, fetch: fetcher, document: { querySelector: () => ({ content: 'test-token' }) }, Head: () => null, AdminLayout: ({children}) => React.createElement('main', {}, children) });
  vm.runInContext(`${code}\nglobalThis.Component = SeoCompletion;`, context);
  let renderer;
  await act(async () => { renderer = create(React.createElement(context.Component, { summary, englishNames })); });
  return { renderer, calls, close: async () => { await act(async () => renderer.unmount()); router.post = original; } };
}

test('SEO completion submits only an explicit fill-missing intent with the real form hook', async () => {
  const app = await mount({ categories: stats, products: stats });
  try {
    await act(async () => app.renderer.root.findByType('form').props.onSubmit({ preventDefault() {} }));
    assert.equal(app.calls[0].url, '/admin/settings/seo-completion');
    assert.equal(app.calls[0].data.fill_missing, true);
    assert.deepEqual(Object.keys(app.calls[0].data), ['fill_missing', 'english_names']);
  } finally { await app.close(); }
});

test('a complete catalog cannot be submitted again through the completion button', async () => {
  const complete = { ...stats, incomplete: 0, meta_title: 0, meta_description: 0, meta_keywords: 0 };
  const app = await mount({ categories: complete, products: complete });
  try { assert.equal(app.renderer.root.findByType('button').props.disabled, true); }
  finally { await app.close(); }
});

test('bulk completion stays disabled until every Bengali product name has a valid English SEO name', async () => {
  const englishNames = [
    { id: 3374, name: 'রাসূল আমার ভালোবাসা Islamic Hoodie (Royal Blue)', count: 1 },
    { id: 3376, name: 'মুসাফির Islamic Hoodie (Sea Green)', count: 1 },
  ];
  const app = await mount({ categories: stats, products: stats }, englishNames);
  try {
    const button = () => app.renderer.root.findAllByType('button').find(item => item.props.type === 'submit');
    const translateButtons = app.renderer.root.findAllByType('button').filter(button => button.props.children === 'Translate to English');
    assert.equal(translateButtons.length, 2);
    assert.equal(translateButtons[0].props.type, 'button');
    assert.equal(translateButtons[0].props.disabled, false);
    const googleLinks = app.renderer.root.findAllByType('a').filter(link => link.props.children === 'Open Google Translate');
    const translateUrl = new URL(googleLinks[0].props.href);
    assert.equal(translateUrl.origin, 'https://translate.google.com');
    assert.equal(translateUrl.searchParams.get('sl'), 'bn');
    assert.equal(translateUrl.searchParams.get('tl'), 'en');
    assert.equal(translateUrl.searchParams.get('text'), englishNames[0].name);
    assert.equal(googleLinks[0].props.target, '_blank');
    assert.equal(googleLinks[0].props.rel, 'noopener noreferrer');
    assert.equal(button().props.disabled, true);
    assert.equal(button().props.children, 'Complete English SEO names above');
    assert.match(JSON.stringify(app.renderer.toJSON()), /2 English SEO names still required/);

    await act(async () => {
      app.renderer.root.findByProps({ 'aria-label': 'English SEO name 3374' }).props.onChange({ target: { value: 'The Prophet Is My Love Islamic Hoodie Royal Blue' } });
    });
    assert.equal(button().props.disabled, true);

    await act(async () => {
      app.renderer.root.findByProps({ 'aria-label': 'English SEO name 3376' }).props.onChange({ target: { value: 'Traveler Islamic Hoodie Sea Green' } });
    });
    assert.equal(button().props.disabled, false);
    assert.equal(button().props.children, 'Fill all missing SEO fields');
  } finally { await app.close(); }
});

test('Translate to English fills the matching field from the saved-provider endpoint', async () => {
  const englishNames = [{ id: 3374, name: 'রাসূল আমার ভালোবাসা Islamic Hoodie (Royal Blue)', count: 1 }];
  const requests = [];
  const fetcher = async (url, options) => {
    requests.push({ url, options });
    return { ok: true, json: async () => ({ translation: 'The Messenger Is My Love Islamic Hoodie (Royal Blue)' }) };
  };
  const app = await mount({ categories: stats, products: stats }, englishNames, fetcher);
  try {
    const translateButton = app.renderer.root.findAllByType('button').find(button => button.props.children === 'Translate to English');
    await act(async () => {
      await translateButton.props.onClick({ preventDefault() {} });
    });
    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, '/admin/settings/seo-completion/translate');
    assert.equal(JSON.parse(requests[0].options.body).name, englishNames[0].name);
    assert.equal(app.renderer.root.findByProps({ 'aria-label': 'English SEO name 3374' }).props.value, 'The Messenger Is My Love Islamic Hoodie (Royal Blue)');
  } finally { await app.close(); }
});

test('Bengali script cannot be submitted as an English SEO name', async () => {
  const englishNames = [{ id: 3374, name: 'Bengali product title', count: 1 }];
  const app = await mount({ categories: stats, products: stats }, englishNames);
  try {
    await act(async () => {
      app.renderer.root.findByProps({ 'aria-label': 'English SEO name 3374' }).props.onChange({ target: { value: 'বাংলা Hoodie' } });
    });
    assert.equal(app.renderer.root.findAllByType('button').find(button => button.props.type === 'submit').props.disabled, true);
    assert.equal(app.renderer.root.findByProps({ 'aria-label': 'English SEO name 3374' }).props['aria-invalid'], true);
  } finally { await app.close(); }
});
