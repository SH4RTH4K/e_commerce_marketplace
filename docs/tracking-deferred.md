# Deferred tracker loading

GA4, GTM, and Meta Pixel keep their existing initial-page-load behavior so page views and storefront events are not delayed.

Hotjar is optional in the admin tracking settings. When a Hotjar site ID is configured directly, the Hotjar script is not present in the initial HTML. The storefront bundle arms a one-time listener for `pointerdown`, `keydown`, `touchstart`, or `scroll`, then loads Hotjar on the first interaction. A consent manager can load it explicitly with:

```js
window.__loadHotjar?.();
```

The loader emits `tracking_interaction` for the interaction path and `tracking_consent_granted` for the explicit consent path on `dataLayer`. If Hotjar is configured inside GTM instead of in the admin setting, change that Hotjar tag from an All Pages trigger to one of those custom-event triggers and publish the container. The application cannot change a remote GTM tag's trigger.

Verification:

- Initial page HTML must not contain `static.hotjar.com`.
- Trigger an interaction or call `window.__loadHotjar()` and confirm one Hotjar script request.
- Keep GA4/GTM page-view triggers and the existing Meta Pixel base tag unchanged.
- Confirm GA4 page views in DebugView, GTM events in Preview, and Meta events in Events Manager/Pixel Helper after deployment.
