import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import vm from 'node:vm';
import React, { act, useState, useEffect, useMemo, useRef } from 'react';
import { create } from 'react-test-renderer';
import { transformWithOxc } from 'vite';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
const filename = new URL('../../resources/js/Pages/Admin/SkuConfiguration.jsx', import.meta.url);
const source = (await readFile(filename, 'utf8')).replace(/^import .*;\r?\n/gm, '').replace('export default function', 'function');
const { code } = await transformWithOxc(source, filename.pathname, { jsx: { runtime: 'classic' } });
const config = { enabled: true, components: ['category', 'sequence'], static_prefix: '', suffix: '', separator: '-', case: 'upper', sequence_start: 1, sequence_padding: 4, category_codes: { 1: 'MEN', 2: 'SHIRT', 3: 'CASUAL' } };
async function mount(fetcher) {
    const requests = [];
    const context = vm.createContext({ React, useState, useEffect, useMemo, useRef,
        router: { patch: (...args) => requests.push(['patch', ...args]), post: (...args) => requests.push(['post', ...args]) },
        usePage: () => ({ props: { errors: {} } }), Head: () => null, Link: ({children, ...props}) => React.createElement('a', props, children),
        AdminLayout: ({children}) => React.createElement('main', {}, children),
        document: { querySelector: () => ({ content: 'test-csrf' }) }, fetch: fetcher,
    });
    vm.runInContext(`${code}\nglobalThis.Component = SkuConfiguration;`, context);
    let rendered;
    await act(async () => { rendered = create(React.createElement(context.Component, { config, categories: [
        {id: 1, name: 'Men', parent_id: null}, {id: 2, name: 'Shirts', parent_id: 1}, {id: 3, name: 'Casual', parent_id: 2},
    ], totals: { products: 3, missing: 1 } })); });
    return { rendered, requests, close: async () => act(async () => rendered.unmount()) };
}
function button(app, text) { return app.rendered.root.findAllByType('button').find(item => item.props.children === text); }

test('prefix setup edits each hierarchy independently and saves without requesting an existing-product update', async () => {
    const app = await mount(() => { throw new Error('Unexpected preview request'); });
    try {
        await act(async () => button(app, 'Prefix setup').props.onClick());
        for (const path of ['Men', 'Men / Shirts', 'Men / Shirts / Casual']) assert.ok(app.rendered.root.findByProps({ 'aria-label': `Prefix for ${path}` }));
        await act(async () => app.rendered.root.findByProps({ 'aria-label': 'Prefix for Men / Shirts' }).props.onChange({ target: { value: 'TOP' } }));
        await act(async () => app.rendered.root.findByType('form').props.onSubmit({ preventDefault() {} }));
        assert.equal(app.requests[0][0], 'patch');
        assert.equal(app.requests[0][1], '/admin/sku-configuration');
        assert.equal(app.requests[0][2].category_codes[2], 'TOP');
        assert.equal(app.requests[0][2].category_codes[1], 'MEN');
    } finally { await app.close(); }
});

test('existing SKUs require preview and review; changing scope invalidates its token', async () => {
    const previews = [];
    const app = await mount(async (url, args) => {
        previews.push([url, JSON.parse(args.body)]);
        return { ok: true, json: async () => ({ token: 'server-token', total: 1, changed_count: 1, errors: [], samples: [{ id: 5, name: 'Shirt', old_sku: 'OLD', new_sku: 'MEN-0001' }] }) };
    });
    try {
        await act(async () => button(app, 'Update existing products').props.onClick());
        await act(async () => button(app, 'Preview SKU changes').props.onClick());
        assert.equal(previews[0][0], '/admin/sku-configuration/preview');
        assert.equal(previews[0][1].scope, 'missing');
        assert.equal(button(app, 'Save configuration & update SKUs').props.disabled, true);
        const checkbox = app.rendered.root.findAllByType('input').find(item => item.props.type === 'checkbox');
        await act(async () => checkbox.props.onChange({ target: { checked: true } }));
        await act(async () => button(app, 'Save configuration & update SKUs').props.onClick());
        assert.equal(app.requests[0][1], '/admin/sku-configuration/apply');
        assert.equal(app.requests[0][2].token, 'server-token');
        assert.equal(app.requests[0][2].confirm, true);
        await act(async () => app.requests[0][3].onFinish());
        await act(async () => app.rendered.root.findAllByType('select')[0].props.onChange({ target: { value: 'all' } }));
        assert.equal(button(app, 'Save configuration & update SKUs'), undefined);
    } finally { await app.close(); }
});
