/**
 * Chilora chrome — promo top bar + footer (driven by window.CHILORA) | v4
 */
(function () {
  const cfg = window.CHILORA || {};
  if (cfg.hideChrome) return;
  const phone = cfg.phone || "01600-000000";
  const brand = cfg.brand || "Chilora";
  const warranty = cfg.warranty || "৬ মাসের ওয়ারেন্টি";
  const promoLeft = cfg.promo_left || "ক্যাশ অন ডেলিভারি সারা দেশে";
  const footerBlurb = cfg.footer_blurb || "শিশুদের জন্য আনন্দময় শিক্ষার সেরা সঙ্গী — টকিং লার্নিং বুক ও প্র্যাকটিস কিট।";
  const tel = String(phone).replace(/-/g, "");
  const sec = cfg.sections || {};
  const on = (key) => sec[key] !== false;
  const bn = cfg.buyNow || {};

  const navItems = [
    { key: "offer",    href: "#offer",    label: "অফার" },
    { key: "features", href: "#features", label: "ফিচার" },
    { key: "why",      href: "#why",      label: "কেন " + brand },
    { key: "reviews",  href: "#reviews",  label: "রিভিউ" },
    { key: "order",    href: "#order",    label: "অর্ডার" },
  ].filter((item) => on(item.key));

  const navHtml = navItems.length
    ? `<div>
        <h3 class="font-bold mb-3 text-white">সেকশন</h3>
        <ul class="space-y-2 text-sm text-white/75">
          ${navItems.map((item) => `<li><a class="hover:text-white transition-colors" href="${item.href}">${item.label}</a></li>`).join("")}
        </ul>
      </div>`
    : "";

  const orderCta =
    bn.url && bn.productId
      ? `<form method="POST" action="${bn.url}" class="inline-block mt-4">
          <input type="hidden" name="_token" value="${bn.csrf || ""}" />
          <input type="hidden" name="product_id" value="${bn.productId}" />
          <input type="hidden" name="qty" value="1" />
          <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[#0284c7] hover:bg-[#0369a1] text-white px-5 py-2.5 text-sm font-bold shadow-md transition-all">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            ${bn.cta || "অর্ডার করতে চাই"}
          </button>
        </form>`
      : "";

  const header = `
  <div class="promo-marquee text-white text-sm sticky top-0 z-50 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 py-2.5 flex flex-wrap items-center justify-center gap-x-6 gap-y-1.5 text-center font-semibold">
      <span class="promo-item">
        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        ${promoLeft}
      </span>
      <span class="opacity-40 hidden sm:inline">|</span>
      <span class="promo-item">
        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        ${warranty}
      </span>
      <span class="opacity-40 hidden sm:inline">|</span>
      <a href="tel:${tel}" class="promo-item hover:underline">
        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1.18h3a2 2 0 0 1 2 1.72c.127.96.36 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.91a16 16 0 0 0 6.29 6.29l.91-.86a2 2 0 0 1 2.11-.45c.907.34 1.85.573 2.81.7a2 2 0 0 1 1.72 2.03z"/></svg>
        ${phone}
      </a>
    </div>
  </div>`;

  const footer = `
  <footer class="mt-20 border-t border-[#dbeafe] bg-[#0c1e36] text-white">
    <div class="max-w-7xl mx-auto px-4 py-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
      <div>
        <div class="font-display text-2xl font-bold mb-2 tracking-tight text-white">${brand}</div>
        <p class="text-white/70 text-sm leading-relaxed max-w-sm">${footerBlurb}</p>
      </div>
      ${navHtml}
      <div>
        <h3 class="font-bold mb-3 text-white">সাপোর্ট</h3>
        <ul class="space-y-2.5 text-sm text-white/75">
          <li class="flex items-center gap-2">
            <svg class="w-4 h-4 text-[#38bdf8]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1.18h3a2 2 0 0 1 2 1.72c.127.96.36 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.91a16 16 0 0 0 6.29 6.29l.91-.86a2 2 0 0 1 2.11-.45c.907.34 1.85.573 2.81.7a2 2 0 0 1 1.72 2.03z"/></svg>
            <a class="hover:text-white transition-colors font-medium" href="tel:${tel}">${phone}</a>
          </li>
          <li class="flex items-center gap-2">
            <svg class="w-4 h-4 text-[#38bdf8]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            <span>ক্যাশ অন ডেলিভারি</span>
          </li>
          <li class="flex items-center gap-2">
            <svg class="w-4 h-4 text-[#38bdf8]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span>${warranty}</span>
          </li>
        </ul>
        ${orderCta}
      </div>
    </div>
    <div class="border-t border-white/10 py-4 text-center text-xs text-white/50">
      © ${new Date().getFullYear()} ${brand}. সর্বস্বত্ব সংরক্ষিত।
    </div>
  </footer>`;

  const headerMount = document.getElementById("site-header");
  const footerMount = document.getElementById("site-footer");
  if (headerMount) headerMount.innerHTML = header;
  if (footerMount) footerMount.innerHTML = footer;
})();
