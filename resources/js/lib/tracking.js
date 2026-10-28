/**
 * SHARTHAK — Central Tracking Utility
 *
 * Reads GTM / GA4 / Meta Pixel IDs from window.__tracking (injected by blade).
 * Sends events to all enabled platforms in one call.
 * Purchase events are deduplicated per provider. Meta waits for marketing consent.
 */

function cfg() {
  return window.__tracking || {};
}

function hasGtm() {
  return Boolean(cfg().gtm);
}

function hasGa4() {
  return Boolean(cfg().ga4);
}

function directGa4() {
  return hasGa4() && (!hasGtm() || cfg().ga4Mode !== 'gtm');
}

function hasPixel() {
  return Boolean(cfg().pixel);
}

function hasPixelConsent() {
  if (cfg().pixelConsent === false) return false;
  if (cfg().pixelConsentRequired === true) {
    return cfg().pixelConsent === true;
  }
  return true;
}

function hasHotjar() {
  return Boolean(cfg().hotjar);
}

/**
 * Load Hotjar only after an interaction. Consent managers can call
 * window.__loadHotjar directly when the visitor grants consent.
 */
export function loadHotjar(reason = 'consent') {
  if (!hasHotjar() || typeof document === 'undefined' || window.__hotjarLoaded) return;

  window.__hotjarLoaded = true;
  window.dataLayer = window.dataLayer || [];
  window.dataLayer.push({
    event: reason === 'interaction' ? 'tracking_interaction' : 'tracking_consent_granted',
  });
  window.hj = window.hj || function () { (window.hj.q = window.hj.q || []).push(arguments); };
  window._hjSettings = { hjid: Number(cfg().hotjar), hjsv: 6 };

  const script = document.createElement('script');
  script.async = true;
  script.src = `https://static.hotjar.com/c/hotjar-${cfg().hotjar}.js?sv=6`;
  script.dataset.hotjarDeferred = 'true';
  (document.head || document.getElementsByTagName('head')[0]).appendChild(script);
}

export function armHotjar() {
  if (!hasHotjar() || typeof window === 'undefined') return;

  window.__loadHotjar = () => loadHotjar('consent');
  const events = ['pointerdown', 'keydown', 'touchstart', 'scroll'];
  const trigger = () => {
    events.forEach(event => window.removeEventListener(event, trigger));
    loadHotjar('interaction');
  };
  events.forEach(event => window.addEventListener(event, trigger, { once: true, passive: true }));
}

armHotjar();

// ─── dataLayer push (GTM) ───────────────────────────────────────────────────
function dlPush(eventName, ecommercePayload) {
  if (!hasGtm() && !hasGa4()) return;
  window.dataLayer = window.dataLayer || [];
  window.dataLayer.push({ ecommerce: null }); // clear previous
  window.dataLayer.push({
    event: eventName,
    ecommerce: ecommercePayload,
  });
}

// ─── gtag direct (only when GA4 is present but NO GTM) ──────────────────────
function gtagEvent(eventName, params) {
  if (!directGa4()) return;
  if (typeof window.gtag === 'function') {
    window.gtag('event', eventName, { ...params, send_to: cfg().ga4 });
  }
}

// ─── Meta Pixel ─────────────────────────────────────────────────────────────
function pixelTrack(eventName, params = {}, eventId = null) {
  if (typeof window === 'undefined' || !hasPixel() || !hasPixelConsent()) return false;

  if (typeof window.fbq !== 'function' && typeof window.__loadMetaPixel === 'function') {
    window.__loadMetaPixel();
  }

  if (typeof window.fbq !== 'function') return false;

  try {
    if (eventId) {
      window.fbq('track', eventName, params, { eventID: eventId });
    } else {
      window.fbq('track', eventName, params);
    }
    return true;
  } catch {
    return false;
  }
}

function wasTracked(key, memoryKey) {
  window[memoryKey] = window[memoryKey] || new Set();
  if (window[memoryKey].has(key)) return true;
  try {
    if (sessionStorage.getItem(key)) {
      window[memoryKey].add(key);
      return true;
    }
  } catch {}
  return false;
}

function markTracked(key, memoryKey) {
  window[memoryKey] = window[memoryKey] || new Set();
  window[memoryKey].add(key);
  try { sessionStorage.setItem(key, '1'); } catch {}
}

function pendingMetaPurchases() {
  window.__pendingMetaPixelPurchases = window.__pendingMetaPixelPurchases || new Map();
  return window.__pendingMetaPixelPurchases;
}

export function flushPendingMetaPixelPurchases() {
  if (!hasPixel() || !hasPixelConsent()) return;
  const pending = pendingMetaPurchases();
  for (const [key, purchase] of pending.entries()) {
    if (wasTracked(key, '__trackedMetaPixelPurchases')) {
      pending.delete(key);
      continue;
    }
    if (pixelTrack('Purchase', purchase.params, purchase.eventId)) {
      markTracked(key, '__trackedMetaPixelPurchases');
      pending.delete(key);
    }
  }
}

export function discardPendingMetaPixelPurchases() {
  pendingMetaPurchases().clear();
}

if (typeof window !== 'undefined') {
  window.__flushPendingMetaPixelPurchases = flushPendingMetaPixelPurchases;
  window.__discardPendingMetaPixelPurchases = discardPendingMetaPixelPurchases;
}

// ─── Unique event ID for dedup (Meta server-side vs client-side) ─────────────
function generateEventId() {
  return (
    Date.now().toString(36) +
    Math.random().toString(36).slice(2, 8)
  );
}

// ─── Public API ─────────────────────────────────────────────────────────────

/**
 * Track a page view. Called automatically on Inertia navigate.
 * @param {string} url
 */
export function trackPageView(url) {
  const path = new URL(url || window.location.href, window.location.origin).href;

  // GA4 via GTM dataLayer
  if (hasGtm() || hasGa4()) {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
      event: 'page_view',
      page_location: path,
    });
  }

  // GA4 direct (only when no GTM)
  gtagEvent('page_view', { page_location: path, page_title: document.title });

  // Meta Pixel (skip the very first load — blade already fired PageView)
  // We use a flag so SPA navigations fire correctly
  if (hasPixel()) {
    if (window.__trackingPageViewFired) {
      pixelTrack('PageView', {});
    } else {
      window.__trackingPageViewFired = true; // mark first load consumed
    }
  }
}

/**
 * Track a storefront search action.
 * The Meta Pixel helper applies the same marketing-consent gate as other Pixel events.
 */
export function trackSearch() {
  return pixelTrack('Search', {});
}

/**
 * Track product detail view.
 * @param {{ id: string|number, name: string, price: number, category?: string, variant?: string }} product
 */
export function trackViewItem(product) {
  const item = {
    item_id: String(product.id),
    item_name: product.name,
    price: Number(product.price) || 0,
    item_category: product.category || undefined,
    item_variant: product.variant || undefined,
    quantity: 1,
  };

  dlPush('view_item', {
    currency: cfg().currency || 'BDT',
    value: item.price,
    items: [item],
  });

  gtagEvent('view_item', {
    currency: cfg().currency || 'BDT',
    value: item.price,
    items: [item],
  });

  pixelTrack('ViewContent', {
    content_ids: [String(product.id)],
    contents: [{ id: String(product.id), quantity: 1, item_price: item.price }],
    content_name: product.name,
    content_category: product.category || undefined,
    content_type: 'product',
    value: item.price,
    currency: cfg().currency || 'BDT',
  });
}

/**
 * Track add to cart.
 * @param {{ id: string|number, name: string, price: number, category?: string }} product
 * @param {number} qty
 * @param {string|null} variant
 */
export function trackAddToCart(product, qty = 1, variant = null) {
  const item = {
    item_id: String(product.id),
    item_name: product.name,
    price: Number(product.price) || 0,
    item_category: product.category || undefined,
    item_variant: variant || undefined,
    quantity: qty,
  };

  dlPush('add_to_cart', {
    currency: cfg().currency || 'BDT',
    value: item.price * qty,
    items: [item],
  });

  gtagEvent('add_to_cart', {
    currency: cfg().currency || 'BDT',
    value: item.price * qty,
    items: [item],
  });

  pixelTrack('AddToCart', {
    content_ids: [String(product.id)],
    contents: [{ id: String(product.id), quantity: qty, item_price: item.price }],
    content_name: product.name,
    content_category: product.category || undefined,
    content_type: 'product',
    value: item.price * qty,
    currency: cfg().currency || 'BDT',
  });
}

/**
 * Track initiate checkout.
 * @param {Array<{ id: string|number, name: string, price: number, quantity: number, variant?: string }>} cartItems
 * @param {number} value  cart subtotal
 */
export function trackInitiateCheckout(cartItems = [], value = 0) {
  const items = cartItems.map((ci) => ({
    item_id: String(ci.product_id ?? ci.id),
    item_name: ci.name || ci.product_name,
    price: Number(ci.price ?? ci.unit_price) || 0,
    item_variant: ci.variant || undefined,
    quantity: Number(ci.qty || ci.quantity) || 1,
  }));

  dlPush('begin_checkout', {
    currency: cfg().currency || 'BDT',
    value: Number(value) || 0,
    items,
  });

  gtagEvent('begin_checkout', {
    currency: cfg().currency || 'BDT',
    value: Number(value) || 0,
    items,
  });

  pixelTrack('InitiateCheckout', {
    content_ids: items.map((i) => i.item_id),
    contents: items.map((i) => ({ id: i.item_id, quantity: i.quantity, item_price: i.price })),
    content_type: 'product',
    value: Number(value) || 0,
    currency: cfg().currency || 'BDT',
    num_items: items.reduce((s, i) => s + i.quantity, 0),
  });
}

/**
 * Track purchase. Deduplicates via sessionStorage so a page refresh
 * never fires the event a second time.
 *
 * @param {{
 *   order_number: string,
 *   total: number,
 *   subtotal: number,
 *   tax: number,
 *   shipping_charge: number,
 *   coupon_code?: string,
 *   currency?: string,
 *   items: Array<{ product_id: string|number, product_name: string, unit_price: number, quantity: number, variant?: string }>
 * }} order
 */
export function trackPurchase(order) {
  if ((!hasGtm() && !hasGa4() && !hasPixel()) || !order?.order_number) return;

  const items = (order.items || []).map((item) => ({
    item_id: String(item.product_id),
    item_name: item.product_name,
    price: Number(item.unit_price) || 0,
    item_variant: item.variant || undefined,
    quantity: Number(item.quantity) || 1,
  }));

  const gross = items.reduce((sum, item) => sum + item.price * item.quantity, 0);
  const merchandiseValue = Math.max(0, Number(order.total) - (Number(order.tax) || 0) - (Number(order.shipping_charge) || 0));
  const discountRatio = gross > 0 ? Math.max(0, Math.min(1, (gross - merchandiseValue) / gross)) : 0;
  items.forEach(item => {
    const original = item.price;
    item.price = Number((original * (1 - discountRatio)).toFixed(6));
    if (discountRatio > 0) item.discount = Number((original - item.price).toFixed(6));
  });

  const currency = order.currency || cfg().currency || 'BDT';
  const purchaseValue = Number(order.total) || 0;

  // Keep analytics deduplication separate from Meta: Meta can be waiting on consent
  // while GA4/GTM is already allowed to record this order.
  const analyticsKey = 'tracked_order_analytics_' + order.order_number;
  if ((hasGtm() || hasGa4()) && !wasTracked(analyticsKey, '__trackedAnalyticsPurchases')) {
    markTracked(analyticsKey, '__trackedAnalyticsPurchases');

    // GTM / GA4 via dataLayer
    dlPush('purchase', {
      transaction_id: order.order_number,
      value: Number(merchandiseValue.toFixed(2)),
      currency,
      tax: Number(order.tax) || 0,
      shipping: Number(order.shipping_charge) || 0,
      coupon: order.coupon_code || undefined,
      items,
    });

    // GA4 direct (no GTM)
    gtagEvent('purchase', {
      transaction_id: order.order_number,
      value: Number(merchandiseValue.toFixed(2)),
      currency,
      tax: Number(order.tax) || 0,
      shipping: Number(order.shipping_charge) || 0,
      coupon: order.coupon_code || undefined,
      items,
    });
  }

  // Meta Pixel has its own consent-aware deduplication. Keep pending purchases
  // in memory only until the visitor accepts; never persist a declined event.
  const metaKey = 'tracked_meta_pixel_purchase_' + order.order_number;
  if (hasPixel() && !wasTracked(metaKey, '__trackedMetaPixelPurchases')) {
    const pending = pendingMetaPurchases();
    const queued = pending.get(metaKey);
    const purchase = queued || {
      eventId: order.meta_event_id || generateEventId(),
      params: {
        content_ids: items.map((i) => i.item_id),
        contents: items.map((i) => ({ id: i.item_id, quantity: i.quantity, item_price: i.price })),
        content_type: 'product',
        value: purchaseValue,
        currency,
        num_items: items.reduce((s, i) => s + i.quantity, 0),
      },
    };

    if (pixelTrack('Purchase', purchase.params, purchase.eventId)) {
      markTracked(metaKey, '__trackedMetaPixelPurchases');
      pending.delete(metaKey);
    } else if (cfg().pixelConsentRequired === true && cfg().pixelConsentStatus !== 'denied') {
      pending.set(metaKey, purchase);
    }
  }
}
