# Deferred tracker loading

GA4 and GTM keep their existing initial-page-load behavior so page views and storefront events are not delayed. When consent is required, Meta Pixel loads only after the visitor allows marketing tracking. If an order is confirmed before that choice, its Meta Purchase event stays in memory and is sent once consent is granted; rejecting tracking discards the pending event. GA4/GTM and Meta use separate purchase deduplication so one provider cannot consume another provider's event.

Hotjar is optional in the admin tracking settings. When a Hotjar site ID is configured directly, the Hotjar script is not present in the initial HTML. The storefront bundle arms a one-time listener for `pointerdown`, `keydown`, `touchstart`, or `scroll`, then loads Hotjar on the first interaction. A consent manager can load it explicitly with:

```js
window.__loadHotjar?.();
```

The loader emits `tracking_interaction` for the interaction path and `tracking_consent_granted` for the explicit consent path on `dataLayer`. If Hotjar is configured inside GTM instead of in the admin setting, change that Hotjar tag from an All Pages trigger to one of those custom-event triggers and publish the container. The application cannot change a remote GTM tag's trigger.

Verification:

- Initial page HTML must not contain `static.hotjar.com`.
- Trigger an interaction or call `window.__loadHotjar()` and confirm one Hotjar script request.
- Keep GA4/GTM page-view triggers and the existing Meta Pixel base tag unchanged.
- Select **Website** in Events Manager Test Events before checking the site feed.
- Accept marketing tracking, then confirm Meta PageView, ViewContent, AddToCart, and InitiateCheckout events in Events Manager/Pixel Helper. Confirm Purchase only after a genuine successful order; do not create a fake live order for testing.
- Confirm GA4 page views in DebugView and GTM events in Preview after deployment.

## Meta Conversions API Purchase events

When enabled in **Admin → Settings → Tracking**, the server sends a Purchase after order creation only when the visitor's marketing-consent cookie is granted. The browser and server use the same stable order event ID so Meta can deduplicate the two copies. The server token is encrypted in the settings table and is never included in page props or URLs. The server sends purchase value, currency, product IDs, normalized SHA-256 hashes of email/phone/account ID, and the country hash; it does not send delivery address, payment details, IP address, or user agent.

Setup and verification:

1. Keep **Require visitor permission before loading the Meta Pixel** enabled.
2. In Events Manager, generate the dataset's Conversions API access token and paste it into Admin → Settings → Tracking. Enable Meta Conversions API and save; leave the token field blank for later edits so the encrypted token stays in place.
3. In Events Manager, select **Website** and keep Test Events open. Verify browser events with Meta Ads Data Advisor (the renamed Meta Pixel Helper).
4. Verify a Purchase only with a genuine successful order. The browser Pixel and server event should both report the same event ID, and Meta should identify the server copy as deduplicated. Never make a fake live order solely to test tracking.
5. Check Admin → Settings → Tracking after save: the token should display as saved, without showing its value. A failed Meta request must not block checkout and must not mark the order as sent.
