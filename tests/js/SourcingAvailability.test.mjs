import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import vm from 'node:vm';
import React, { act, useState, useEffect, useMemo, useRef } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { create } from 'react-test-renderer';
import { router, useForm } from '@inertiajs/react';
import { transformWithOxc } from 'vite';
import { orderQuantityLimit, productAvailability, requiresSourcing, sourcingMessage } from '../../resources/js/lib/productAvailability.js';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
globalThis.window = { setTimeout: () => 0 };
async function compile(path) {
  const filename = new URL(`../../resources/js/${path}`, import.meta.url);
  const source = (await readFile(filename, 'utf8')).replace(/^import .*;\r?\n/gm, '').replaceAll('export default function', 'function');
  return (await transformWithOxc(source, filename.pathname, { jsx: { runtime: 'classic' } })).code;
}
const availabilityCode = await compile('Components/Storefront/ProductAvailability.jsx');
const cardCode = await compile('Components/Storefront/ProductCard.jsx');
const detailCode = await compile('Pages/Storefront/Product.jsx');
const receiptCode = await compile('Components/Admin/ReceiveOrderStock.jsx');
const orderCode = await compile('Pages/Admin/Orders/Show.jsx');
const codCode = await compile('Components/Storefront/CodOrderModal.jsx');
const noticeCode = await compile('Components/Storefront/SourcingNotice.jsx');
const cartCode = await compile('Pages/Storefront/Cart.jsx');
const checkoutCode = await compile('Pages/Storefront/Checkout.jsx');
const product = { id: 9, name: 'Test Clock', sku: 'TEST-9', slug: 'clock', regular_price: 200, stock_quantity: 0, allow_backorder: true, images: [] };

function context(settings = {}) {
  const ctx = vm.createContext({ React, useState, useEffect, useMemo, useRef, useForm, router, URLSearchParams,
    orderQuantityLimit, productAvailability, requiresSourcing, sourcingMessage, money: value => `BDT ${value}`, imageUrl: () => '', whatsappNumber: () => '',
    usePage: () => ({ props: { app: { settings }, cartItems: [] } }),
    Head: () => null, StorefrontLayout: ({ children }) => React.createElement('main', {}, children),
    AdminLayout: ({ children }) => React.createElement('main', {}, children),
    Link: ({ children, ...props }) => React.createElement('a', props, children),
    ProductRating: () => null, ProductCard: () => null, CodOrderModal: () => null, PulseHeart: () => null,
    trackViewItem() {}, trackAddToCart() {}, localStorage: { getItem: () => null },
    window: { addEventListener() {}, removeEventListener() {}, location: { origin: 'http://localhost', pathname: '/product/clock' } },
  });
  vm.runInContext(availabilityCode, ctx);
  return ctx;
}

test('sourcing availability hides quantities at zero and positive stock while normal stock retains its quantity', () => {
  const ctx = context();
  vm.runInContext('globalThis.Component = ProductAvailability;', ctx);
  const render = value => renderToStaticMarkup(React.createElement(ctx.Component, { product: value, showMessage: true }));
  assert.match(render(product), /Available to order/);
  assert.match(render(product), /We source this item after receiving your order/);
  assert.doesNotMatch(render({ ...product, stock_quantity: 12345 }), /12,345|12345/);
  assert.doesNotMatch(render({ ...product, stock_quantity: 12345 }), /We source this item/);
  assert.equal(requiresSourcing({ ...product, stock_quantity: 1 }, 2), true);
  assert.match(render({ ...product, allow_backorder: false, stock_quantity: 8 }), /In Stock: 8/);
  assert.match(render({ ...product, allow_backorder: false }), /Out of Stock/);
});

test('zero-stock sourcing card can add to cart and disabled sourcing restores the blocked button', async () => {
  const ctx = context();
  vm.runInContext(`${cardCode}\nglobalThis.Component = ProductCard;`, ctx);
  const calls = []; const originalPost = router.post;
  router.post = (url, data) => calls.push({ url, data });
  let renderer;
  try {
    await act(async () => { renderer = create(React.createElement(ctx.Component, { product })); });
    assert.equal(renderer.root.findByType('button').props.disabled, false);
    await act(async () => renderer.root.findByType('button').props.onClick({ preventDefault() {} }));
    assert.equal(calls[0].url, '/cart/add');
    assert.deepEqual(JSON.parse(JSON.stringify(calls[0].data)), { product_id: 9, qty: 1 });
    await act(async () => renderer.update(React.createElement(ctx.Component, { product: { ...product, allow_backorder: false } })));
    assert.equal(renderer.root.findByType('button').props.disabled, true);
  } finally { if (renderer) await act(async () => renderer.unmount()); router.post = originalPost; }
});

test('both storefront templates allow a zero-stock sourced product and retain selected variants when ordering', async () => {
  const calls = []; const originalPost = router.post;
  router.post = (url, data) => calls.push({ url, data });
  try {
    for (const template of ['template-1', 'template-2']) {
      const ctx = context({ storefront_template: template, product_page_cod_enabled: false, product_page_whatsapp_enabled: false, product_page_call_enabled: false });
      vm.runInContext(`${detailCode}\nglobalThis.Component = ProductPage;`, ctx);
      let renderer;
      try {
        await act(async () => { renderer = create(React.createElement(ctx.Component, { product, related: [], features: [], sizes: [], colors: [], weights: [], reviews: { data: [], total: 0 }, variantGroups: [{ name: 'Color', options: [{ value: 'Red' }, { value: 'Blue' }] }], initialVariants: { Color: 'Blue' } })); });
        const addButton = renderer.root.findAllByType('button').find(button => button.findAllByType('span').some(span => span.children.includes('ADD TO CART')));
        assert.equal(addButton.props.disabled, false, template);
        assert.match(JSON.stringify(renderer.toJSON()), /Available to order/);
        await act(async () => addButton.props.onClick());
        assert.deepEqual(JSON.parse(JSON.stringify(calls.at(-1))), { url: '/cart/add', data: { product_id: 9, variant: 'Color: Blue', qty: 1 } });
      } finally { if (renderer) await act(async () => renderer.unmount()); }
    }
  } finally { router.post = originalPost; }
});

test('receiving stock submits the outstanding snapshot and quantity without confirming the order', async () => {
  const ctx = context();
  vm.runInContext(`${receiptCode}\nglobalThis.Component = ReceiveOrderStock;`, ctx);
  const calls = []; const originalPost = router.post;
  router.post = (url, data, options) => calls.push({ url, data: JSON.parse(JSON.stringify(data)), options });
  let renderer;
  try {
    await act(async () => { renderer = create(React.createElement(ctx.Component, { orderId: 44, item: { id: 7, product_name: 'Test Clock', sourcing_outstanding: 3 } })); });
    const quantity = () => renderer.root.findByProps({ 'aria-label': 'Quantity received for Test Clock' });
    for (const value of ['', '0', '4', '1.5']) {
      await act(async () => quantity().props.onChange({ target: { value } }));
      assert.equal(renderer.root.findByType('button').props.disabled, true);
    }
    await act(async () => quantity().props.onChange({ target: { value: '2' } }));
    await act(async () => renderer.root.findByProps({ 'aria-label': 'Receipt reference for Test Clock' }).props.onChange({ target: { value: 'Receipt A' } }));
    await act(async () => renderer.root.findByType('form').props.onSubmit({ preventDefault() {} }));
    assert.equal(calls[0].url, '/admin/orders/44/items/7/receive-stock');
    assert.deepEqual(calls[0].data, { quantity: 2, expected_outstanding: 3, reference: 'Receipt A' });
    await act(async () => calls[0].options.onError({ expected_outstanding: 'This item has changed. Reload the order.' }));
    assert.match(JSON.stringify(renderer.toJSON()), /This item has changed/);
  } finally { if (renderer) await act(async () => renderer.unmount()); router.post = originalPost; }
});

test('COD ordering permits sourcing and blocks submission after sourcing is disabled at zero stock', async () => {
  const ctx = context();
  const requests = [];
  ctx.document = { body: { style: {} }, documentElement: { style: {} }, querySelector: () => null };
  ctx.trackInitiateCheckout = () => {};
  ctx.fetch = async (url, options) => {
    requests.push({ url, data: JSON.parse(options.body) });
    return { ok: false, json: async () => ({ errors: { qty: 'Availability changed. Please try again.' } }) };
  };
  vm.runInContext(`${codCode}\nglobalThis.Component = CodOrderModal;`, ctx);
  let renderer;
  const props = { open: true, onClose() {}, product, qty: 3, variant: 'Color: Blue' };
  try {
    await act(async () => { renderer = create(React.createElement(ctx.Component, props)); });
    const submitButton = () => renderer.root.findByProps({ form: 'cod-form' });
    assert.equal(submitButton().props.disabled, false);
    assert.match(JSON.stringify(renderer.toJSON()), /We source this item after receiving your order/);
    await act(async () => renderer.root.findByProps({ id: 'cod-form' }).props.onSubmit({ preventDefault() {} }));
    assert.equal(requests[0].url, '/quick-order');
    assert.equal(requests[0].data.qty, 3);
    assert.equal(requests[0].data.variant, 'Color: Blue');
    assert.match(JSON.stringify(renderer.toJSON()), /Availability changed/);
    await act(async () => renderer.update(React.createElement(ctx.Component, { ...props, product: { ...product, allow_backorder: false } })));
    assert.equal(submitButton().props.disabled, true);
    assert.match(JSON.stringify(renderer.toJSON()), /Out of Stock/);
    await act(async () => renderer.root.findByProps({ id: 'cod-form' }).props.onSubmit({ preventDefault() {} }));
    assert.equal(requests.length, 1);
    assert.match(JSON.stringify(renderer.toJSON()), /This quantity is no longer available/);
  } finally { if (renderer) await act(async () => renderer.unmount()); }
});

test('cart templates and checkout disclose sourcing when the ordered quantity exceeds available units', async () => {
  const items = { clock: { key: 'clock', product_id: 9, name: 'Test Clock', slug: 'clock', qty: 2, price: 200, line_total: 400, stock_quantity: 1, allow_backorder: true } };
  for (const [code, name, settings, extra] of [
    [cartCode, 'CartPage', { storefront_template: 'template-1' }, {}],
    [cartCode, 'CartPage', { storefront_template: 'template-2' }, {}],
    [checkoutCode, 'CheckoutPage', {}, { shipInside: 60, shipOutside: 120, taxPercent: 0, discount: 0, paySettings: { cod_enabled: true } }],
  ]) {
    const ctx = context(settings);
    ctx.trackInitiateCheckout = () => {};
    vm.runInContext(`${noticeCode}\n${code}\nglobalThis.Component = ${name};`, ctx);
    let renderer;
    try {
      await act(async () => { renderer = create(React.createElement(ctx.Component, { items, subtotal: 400, ...extra })); });
      assert.match(JSON.stringify(renderer.toJSON()), /We source this item after receiving your order/, `${name} ${settings.storefront_template || ''}`);
      await act(async () => renderer.update(React.createElement(ctx.Component, { items: { clock: { ...items.clock, qty: 1 } }, subtotal: 200, ...extra })));
      assert.doesNotMatch(JSON.stringify(renderer.toJSON()), /We source this item after receiving your order/);
    } finally { if (renderer) await act(async () => renderer.unmount()); }
  }
});

test('pending sourcing order blocks confirmation and shipment and gates receipt controls by permission', async () => {
  const ctx = context();
  ctx.orderReturnUrl = () => '/admin/orders';
  ctx.usePage = () => ({ url: '/admin/orders/44' });
  ctx.AddOrderProducts = () => null;
  ctx.OrderItemDiscount = () => null;
  ctx.CourierShipModal = () => null;
  ctx.useCallback = React.useCallback;
  ctx.window.location.search = '';
  vm.runInContext(`${receiptCode}\n${orderCode}\nglobalThis.Component = OrderShow;`, ctx);
  const props = { order: { id: 44, order_number: 'TEST-44', status: 'pending', payment_status: 'pending', subtotal: 200, total: 200, awaiting_sourcing: true, items: [{ id: 7, product_name: 'Test Clock', quantity: 1, sourcing_required: true, reserved_stock_quantity: 0, sourcing_outstanding: 1 }] } };
  let renderer;
  try {
    await act(async () => { renderer = create(React.createElement(ctx.Component, props)); });
    const ship = renderer.root.findAllByType('button').find(button => button.children.includes('Ship via Courier'));
    assert.equal(ship.props.disabled, true);
    for (const status of ['confirmed', 'processing', 'shipped', 'delivered']) {
      assert.equal(renderer.root.findByProps({ value: status }).props.disabled, true);
    }
    assert.equal(renderer.root.findAllByProps({ 'aria-label': 'Quantity received for Test Clock' }).length, 0);
    await act(async () => renderer.update(React.createElement(ctx.Component, { ...props, canReceiveStock: true })));
    assert.equal(renderer.root.findByProps({ 'aria-label': 'Quantity received for Test Clock' }).props.value, 1);
  } finally { if (renderer) await act(async () => renderer.unmount()); }
});
