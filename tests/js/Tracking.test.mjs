import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import vm from 'node:vm';

const source = (await readFile(new URL('../../resources/js/lib/tracking.js', import.meta.url), 'utf8')).replaceAll('export function', 'function');
function setup(config = {}, blockedStorage = false) {
  const sent = []; const pixelSent = []; const saved = new Map();
  const listeners = new Map(); const appendedScripts = [];
  const window = {
    __tracking: config,
    location: { href: 'https://taqilife.com/', origin: 'https://taqilife.com' },
    dataLayer: [],
    gtag: (...args) => sent.push(args),
    fbq: (...args) => pixelSent.push(args),
    addEventListener: (event, handler) => { listeners.set(event, handler); },
    removeEventListener: event => { listeners.delete(event); },
    dispatchEvent: event => listeners.get(event.type)?.(event),
  };
  const document = {
    title: 'Store',
    createElement: () => ({ dataset: {}, set async(value) { this.isAsync = value; } }),
    head: { appendChild: script => appendedScripts.push(script) },
    getElementsByTagName: () => [],
  };
  const sessionStorage = { getItem: key => { if (blockedStorage) throw Error('Blocked'); return saved.get(key); }, setItem: (key, value) => { if (blockedStorage) throw Error('Blocked'); saved.set(key, value); } };
  const context = vm.createContext({ window, sessionStorage, URL, document });
  vm.runInContext(`${source}\nglobalThis.api = {trackPurchase, trackPageView, trackSearch, trackInitiateCheckout, trackAddToCart, trackViewItem, flushPendingMetaPixelPurchases, discardPendingMetaPixelPurchases};`, context);
  return { ...context.api, window, sent, pixelSent, saved, appendedScripts };
}

test('direct GA4 delivers commerce events even with an empty GTM container; GTM mode has only one sender', () => {
  const app = setup({ ga4: 'G-STORE', gtm: 'GTM-STORE', ga4Mode: 'direct' });
  app.trackAddToCart({ id: 3, name: 'Shirt', price: 100 }, 2, 'Size: L');
  assert.equal(app.sent.length, 1); assert.equal(app.sent[0][1], 'add_to_cart');
  assert.equal(app.sent[0][2].value, 200); assert.equal(app.sent[0][2].send_to, 'G-STORE');
  const managed = setup({ ga4: 'G-STORE', gtm: 'GTM-STORE', ga4Mode: 'gtm' });
  managed.trackViewItem({ id: 3, name: 'Shirt', price: 100 });
  assert.equal(managed.sent.length, 0);
  assert.equal(managed.window.dataLayer.filter(item => item.event === 'view_item').length, 1);
});

test('purchase excludes shipping and tax, distributes discount and deduplicates the transaction', () => {
  const app = setup({ ga4: 'G-STORE' });
  const order = { order_number: 'TEST-1', total: 930, shipping_charge: 80, tax: 50, items: [{ product_id: 1, product_name: 'Shirt', unit_price: 1000, quantity: 1 }] };
  app.trackPurchase(order); app.trackPurchase(order);
  const event = app.sent[0][2];
  assert.equal(app.sent.length, 1); assert.equal(event.value, 800);
  assert.equal(event.shipping, 80); assert.equal(event.tax, 50);
  assert.equal(event.items[0].price, 800); assert.equal(event.items[0].discount, 200);
  assert.equal(event.transaction_id, 'TEST-1');
  const blocked = setup({ ga4: 'G-STORE' }, true);
  assert.doesNotThrow(() => { blocked.trackPurchase(order); blocked.trackPurchase(order); });
  assert.equal(blocked.sent.length, 1);
});

test('disabled analytics does not consume a purchase, and checkout keeps zero-valued items', () => {
  const app = setup();
  const order = { order_number: 'TEST-2', total: 0, items: [] };
  app.trackPurchase(order); assert.equal(app.saved.size, 0);
  app.window.__tracking.ga4 = 'G-STORE'; app.trackPurchase(order); assert.equal(app.sent.length, 1);
  app.trackInitiateCheckout([{ product_id: 7, id: 'cart-key', name: 'Gift', price: 0, unit_price: 99, qty: 1 }], 0);
  assert.equal(app.sent[1][2].items[0].price, 0);
  assert.equal(app.sent[1][2].items[0].item_id, '7');
  app.trackPageView('/shop');
  assert.equal(app.sent[2][1], 'page_view'); assert.equal(app.sent[2][2].page_location, 'https://taqilife.com/shop');
});

test('Meta Search sends no query text and respects marketing consent', () => {
  const allowed = setup({ pixel: '3577038609126077', pixelConsent: true });
  assert.equal(allowed.trackSearch(), true);
  assert.equal(allowed.pixelSent.length, 1);
  assert.equal(allowed.pixelSent[0][0], 'track');
  assert.equal(allowed.pixelSent[0][1], 'Search');
  assert.deepEqual({ ...allowed.pixelSent[0][2] }, {});

  const declined = setup({ pixel: '3577038609126077', pixelConsent: false });
  assert.equal(declined.trackSearch(), false);
  assert.equal(declined.pixelSent.length, 0);
});

test('Meta Purchase waits for consent without being consumed by the GA4 event', () => {
  const app = setup({
    ga4: 'G-STORE',
    pixel: '3577038609126077',
    pixelConsentRequired: true,
    pixelConsent: false,
    pixelConsentStatus: 'pending',
  });
  const order = {
    order_number: 'CONSENT-ORDER-1',
    total: 930,
    shipping_charge: 80,
    tax: 50,
    items: [{ product_id: 1, product_name: 'Shirt', unit_price: 1000, quantity: 1 }],
  };

  app.trackPurchase(order);
  assert.equal(app.sent.filter(([, name]) => name === 'purchase').length, 1);
  assert.equal(app.pixelSent.length, 0);
  assert.equal(app.saved.has('tracked_meta_pixel_purchase_CONSENT-ORDER-1'), false);

  app.window.__tracking.pixelConsent = true;
  app.window.__tracking.pixelConsentStatus = 'granted';
  app.flushPendingMetaPixelPurchases();
  app.trackPurchase(order);
  app.flushPendingMetaPixelPurchases();

  assert.equal(app.sent.filter(([, name]) => name === 'purchase').length, 1);
  assert.equal(app.pixelSent.length, 1);
  assert.equal(app.pixelSent[0][0], 'track');
  assert.equal(app.pixelSent[0][1], 'Purchase');
  assert.equal(app.pixelSent[0][2].currency, 'BDT');
  assert.equal(app.pixelSent[0][2].value, 930);
  assert.ok(app.pixelSent[0][3].eventID);
  assert.equal(app.saved.get('tracked_meta_pixel_purchase_CONSENT-ORDER-1'), '1');
});

test('rejecting marketing consent discards an unsent Meta Purchase', () => {
  const app = setup({
    pixel: '3577038609126077',
    pixelConsentRequired: true,
    pixelConsent: false,
    pixelConsentStatus: 'pending',
  });
  const order = { order_number: 'CONSENT-ORDER-2', total: 300, items: [] };

  app.trackPurchase(order);
  assert.equal(app.window.__pendingMetaPixelPurchases.size, 1);

  app.window.__tracking.pixelConsentStatus = 'denied';
  app.discardPendingMetaPixelPurchases();
  app.flushPendingMetaPixelPurchases();

  assert.equal(app.window.__pendingMetaPixelPurchases.size, 0);
  assert.equal(app.pixelSent.length, 0);
  assert.equal(app.saved.has('tracked_meta_pixel_purchase_CONSENT-ORDER-2'), false);
});

test('consented Meta Purchase uses the standard Purchase event and deduplicates', () => {
  const app = setup({
    pixel: '3577038609126077',
    pixelConsentRequired: true,
    pixelConsent: true,
    pixelConsentStatus: 'granted',
  });
  const order = { order_number: 'CONSENT-ORDER-3', total: 300, items: [] };

  app.trackPurchase(order);
  app.trackPurchase(order);

  assert.equal(app.pixelSent.length, 1);
  assert.equal(app.pixelSent[0][1], 'Purchase');
  assert.ok(app.pixelSent[0][3].eventID);
});

test('Hotjar is absent until interaction and remains callable through the consent hook', () => {
  const app = setup({ hotjar: '12345678' });
  assert.equal(app.appendedScripts.length, 0);
  app.window.dispatchEvent({ type: 'pointerdown' });
  assert.equal(app.appendedScripts.length, 1);
  assert.equal(app.appendedScripts[0].src, 'https://static.hotjar.com/c/hotjar-12345678.js?sv=6');
  assert.equal(app.window.dataLayer.at(-1).event, 'tracking_interaction');

  const consent = setup({ hotjar: '12345678' });
  consent.window.__loadHotjar();
  assert.equal(consent.appendedScripts.length, 1);
  assert.equal(consent.window.dataLayer.at(-1).event, 'tracking_consent_granted');
});
