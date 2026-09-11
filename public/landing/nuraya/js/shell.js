/** Nuraya chrome — promo bar + footer (driven by window.NURAYA) | v4 */
(function () {
  const cfg = window.NURAYA || {};
  if (cfg.hideChrome) return;
  const phone = cfg.phone || "01700-000000";
  const tel   = String(phone).replace(/-/g, "");
  const brand = cfg.brand || "Nuraya";
  const promo = cfg.promo_text || "১০০% সুতি অরবিন্দ ভয়েলের সালাত হিজাব — সারা দেশে ক্যাশ অন ডেলিভারি";
  const footerBlurb = cfg.footer_blurb || "পবিত্রতা, আরাম ও কোমল কাপড়ের সমন্বয়ে — সালাত হিজাব কালেকশন।";
  const sec = cfg.sections || {};
  const on  = (key) => sec[key] !== false;
  const bn  = cfg.buyNow || {};

  const navItems = [
    { key: "offer",    href: "#offer",    label: "অফার" },
    { key: "benefits", href: "#benefits", label: "সুবিধা" },
    { key: "reviews",  href: "#reviews",  label: "রিভিউ" },
    { key: "order",    href: "#order",    label: "চেকআউট" },
  ].filter((item) => on(item.key));

  const navHtml = navItems.length
    ? `<div>
        <h3 class="font-bold mb-3">সেকশন</h3>
        <ul class="space-y-2 text-sm text-white/75">
          ${navItems.map((item) => `<li><a href="${item.href}" class="hover:text-white transition-colors">${item.label}</a></li>`).join("")}
        </ul>
       </div>`
    : "";

  const orderCta =
    bn.url && bn.productId
      ? `<form method="POST" action="${bn.url}" class="inline-block mt-4">
           <input type="hidden" name="_token"      value="${bn.csrf || ""}" />
           <input type="hidden" name="product_id"  value="${bn.productId}" />
           <input type="hidden" name="qty"          value="1" />
           <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-[#7c3aed] text-white px-5 py-2.5 text-sm font-bold hover:brightness-110 transition-all">
             <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
             ${bn.cta || "এখনই অর্ডার"}
           </button>
         </form>`
      : "";

  /* ── Promo bar SVG icon ── */
  const tagIcon = `<svg class="inline-block w-3.5 h-3.5 mr-1 -mt-0.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>`;

  const header = `
  <div class="promo-bar text-white text-sm sticky top-0 z-50 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 py-2.5 text-center font-semibold tracking-wide">
      ${tagIcon} ${promo}
    </div>
  </div>`;

  const footer = `
  <footer class="mt-16 bg-[#0f0c29] text-white">
    <div class="max-w-7xl mx-auto px-4 pt-12 pb-8 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
      <div>
        <div class="text-2xl font-bold mb-2 tracking-tight">${brand}</div>
        <p class="text-white/65 text-sm leading-relaxed max-w-xs">${footerBlurb}</p>
      </div>
      ${navHtml}
      <div>
        <h3 class="font-bold mb-3">সাপোর্ট</h3>
        <p class="text-sm text-white/75 flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1.18h3a2 2 0 0 1 2 1.72c.127.96.36 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.91a16 16 0 0 0 6.29 6.29l.91-.86a2 2 0 0 1 2.11-.45c.907.34 1.85.573 2.81.7a2 2 0 0 1 1.72 2.03z"/></svg>
          <a href="tel:${tel}" class="hover:text-white transition-colors">${phone}</a>
        </p>
        <p class="text-sm text-white/65 mt-2 flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
          ক্যাশ অন ডেলিভারি
        </p>
        <p class="text-sm text-white/65 mt-1 flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          ৬ মাসের ওয়ারেন্টি
        </p>
        ${orderCta}
      </div>
    </div>
    <div class="border-t border-white/10 py-4 text-center text-xs text-white/45">© ${new Date().getFullYear()} ${brand}.</div>
  </footer>`;

  const h = document.getElementById("site-header");
  const f = document.getElementById("site-footer");
  if (h) h.innerHTML = header;
  if (f) f.innerHTML = footer;
})();
