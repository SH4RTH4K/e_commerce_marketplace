# SEO completion — 16 October 2026

## Verified issues and changes

- The rendered homepage had no H1. All storefront templates now have one visible store introduction; slide titles use H2. Categories show their existing descriptions, with saved meta descriptions as a fallback.
- Products with variant price adjustments omitted offers. ProductOfferService now enumerates the same one-option-per-type, additive selections as cart pricing. Variant links preselect their actual options, preserve the parent canonical URL and reject nonexistent combinations. Supported attributes use ProductGroup; other attributes retain accurate Product offers. Very large combinations use an accurate AggregateOffer range to bound page size.
- Availability now follows parent stock consumed by CartController and CheckoutController. Variant option stock alone cannot make an otherwise unorderable parent purchasable.
- Organization markup links to the real return policy. No blanket free-return promise or invented delivery times are emitted. Shipping markup is emitted only for explicitly free-delivery products. Approved customer reviews supply ratings; pending/rejected reviews do not.
- `/merchant-feed.xml` streams public products and real images, selectable prices, current stock, variant links and category paths. It does not expose internal costs, invent GTIN/MPN, or assert that manufacturer identifiers do not exist just because they are missing. Non-free shipping rules must be completed in Merchant Center using actual delivery areas and rates.
- The GTM container inspected during the audit had no tags. GA4 defaults to direct event delivery even when a GTM ID exists; an explicit GTM mode remains available for a correctly configured container. Pageviews have one direct sender. Add-to-cart fires after successful changes; checkout is sent on the checkout page; purchase excludes shipping/tax and allocates order discounts to item values. Purchase deduplication survives blocked sessionStorage. Admin pages do not load marketing tags.
- The mobile PageSpeed baseline downloaded 56,861 KiB and could not measure LCP. The largest supplier PNGs were roughly 6–7 MiB each. Storefront images now use cached, bounded WebP derivatives. Only saved public product/banner/category/logo references can be requested. Remote image fetching validates public DNS addresses, pins cURL resolution, rejects redirects and bounds download/pixel sizes. Original images remain intact and unsupported formats fall back safely.
- The blog had dated sample articles, a fictitious comment count, nonfunctional commenting and arbitrary URLs rendering the first article. It now publishes three factual shopping guides. Unknown articles return 404; template article URLs redirect to the guides index. Each guide has specific SEO metadata and article markup; guides are linked from the footer and sitemap.
- llms.txt category/product links now use Markdown link syntax. This is a discovery convenience, not a Google indexing requirement.

## Google setup

- Domain Search Console verification and sitemap submission were completed before this release. Discovery counts do not establish indexing.
- The audit found the www homepage indexed and the non-www homepage treated as a duplicate. The live canonical-host setting was changed to non-www. Google must recrawl before its selected canonical may change.
- Merchant Center was created with the owner's approval of Google's terms. Bangladesh is the target country. Business address, verified contact details, accurate shipping times/rules, site claim and product processing must be verified in its live setup; incomplete account setup must not be reported as completed listings.
- Keep the DNS verification TXT record and the existing retained Analytics properties.

## Ongoing work requiring real business activity

- Collect genuine customer reviews through the existing moderated review workflow. Do not invent ratings or reviews.
- Publish useful product-specific information when supplied by the manufacturer or business; keep existing descriptions and SEO text intact.
- Keep official social profiles consistent with the store URL and support details. Share relevant guides and products through business-owned channels only with explicit publishing authorization. Relevant earned links require actual relationships/content; they cannot be manufactured by a technical deployment.
- Monitor Search Console indexing, canonical selection and merchant diagnostics after processing. Monitor GA4 commerce against actual orders without placing fake purchases or polluting live reports.

## Validation

Meaningful tests cover variant prices and query selection, cart parity, feed XML/visibility/zero prices, approved ratings, return-policy honesty, guide discovery/404s, direct-vs-GTM delivery, discounted purchase values, blocked storage, image resizing/cache and private-address SSRF rejection. Full application tests, frontend tests, production build and live checks are required before declaring deployment complete.

Sources: [Product variants](https://developers.google.com/search/docs/appearance/structured-data/product-variants), [Return policy markup](https://developers.google.com/search/docs/appearance/structured-data/return-policy), [Canonical consolidation](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls), [GA4 ecommerce](https://developers.google.com/analytics/devguides/collection/ga4/ecommerce).
