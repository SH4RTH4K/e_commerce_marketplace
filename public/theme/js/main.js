/* =========================================================================
   SHOPZY — Theme JavaScript (vanilla, no dependencies, 100% original)
   header scroll, mega menu, mobile menu, cart drawer, add-to-cart, wishlist,
   hero slider, flash countdown, qty steppers, reveal, back-to-top, gallery,
   tabs, accordions, filters, toast.
   ========================================================================= */
(function () {
  "use strict";
  const $  = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));

  /* ---------------- Cart ---------------- */
  const Cart = {
    items: JSON.parse(localStorage.getItem("shopzy_cart") || "[]"),
    save() { localStorage.setItem("shopzy_cart", JSON.stringify(this.items)); },
    count() { return this.items.reduce((n, i) => n + i.qty, 0); },
    subtotal() { return this.items.reduce((n, i) => n + i.price * i.qty, 0); },
    add(p) { const f = this.items.find((i) => i.id === p.id); if (f) f.qty += p.qty || 1; else this.items.push({ ...p, qty: p.qty || 1 }); this.save(); },
    remove(id) { this.items = this.items.filter((i) => i.id !== id); this.save(); },
    setQty(id, q) { const it = this.items.find((i) => i.id === id); if (it) it.qty = Math.max(1, q); this.save(); },
  };
  const money = (n) => "$" + Number(n).toFixed(2);

  /* ---------------- Header scroll ---------------- */
  const header = $(".site-header");
  if (header) { const f = () => header.classList.toggle("scrolled", window.scrollY > 16); f(); window.addEventListener("scroll", f, { passive: true }); }

  /* ---------------- Overlay / panels ---------------- */
  const overlay = $("#overlay"), cartDrawer = $("#cartDrawer"), mobileMenu = $("#mobileMenu"), filterPanel = $("#filterPanel");
  const openOverlay = () => { overlay && overlay.classList.remove("opacity-0", "pointer-events-none"); document.body.classList.add("no-scroll"); };
  const anyOpen = () =>
    (cartDrawer && !cartDrawer.classList.contains("translate-x-full")) ||
    (mobileMenu && !mobileMenu.classList.contains("-translate-x-full")) ||
    (filterPanel && !filterPanel.classList.contains("-translate-x-full"));
  const maybeClose = () => { if (!anyOpen()) { overlay && overlay.classList.add("opacity-0", "pointer-events-none"); document.body.classList.remove("no-scroll"); } };
  const closeAll = () => {
    cartDrawer && cartDrawer.classList.add("translate-x-full");
    mobileMenu && mobileMenu.classList.add("-translate-x-full");
    filterPanel && filterPanel.classList.add("-translate-x-full");
    maybeClose();
  };

  $$("[data-open-menu]").forEach((b) => b.addEventListener("click", () => { mobileMenu && mobileMenu.classList.remove("-translate-x-full"); openOverlay(); }));
  $$("[data-close-menu]").forEach((b) => b.addEventListener("click", () => { mobileMenu && mobileMenu.classList.add("-translate-x-full"); maybeClose(); }));
  $$("[data-open-filter]").forEach((b) => b.addEventListener("click", () => { filterPanel && filterPanel.classList.remove("-translate-x-full"); openOverlay(); }));
  $$("[data-close-filter]").forEach((b) => b.addEventListener("click", () => { filterPanel && filterPanel.classList.add("-translate-x-full"); maybeClose(); }));

  function openCart() { cartDrawer && cartDrawer.classList.remove("translate-x-full"); openOverlay(); renderCart(); }
  function closeCart() { cartDrawer && cartDrawer.classList.add("translate-x-full"); maybeClose(); }
  $$("[data-open-cart]").forEach((b) => b.addEventListener("click", (e) => { e.preventDefault(); openCart(); }));
  $$("[data-close-cart]").forEach((b) => b.addEventListener("click", closeCart));
  if (overlay) overlay.addEventListener("click", closeAll);
  document.addEventListener("keydown", (e) => { if (e.key === "Escape") closeAll(); });

  /* ---------------- Mega menu ---------------- */
  const megaBtn = $("#megaBtn"), megaMenu = $("#megaMenu");
  if (megaBtn && megaMenu) {
    megaBtn.addEventListener("click", (e) => { e.stopPropagation(); megaMenu.classList.toggle("hidden"); });
    document.addEventListener("click", (e) => { if (!megaMenu.contains(e.target) && e.target !== megaBtn) megaMenu.classList.add("hidden"); });
  }

  /* ---------------- Badges ---------------- */
  function updateCount() {
    const c = Cart.count();
    $$(".cart-count").forEach((el) => { el.textContent = c; el.classList.toggle("hidden", c === 0); el.classList.remove("bump"); void el.offsetWidth; el.classList.add("bump"); });
    $$(".cart-total").forEach((el) => (el.textContent = money(Cart.subtotal())));
  }

  /* ---------------- Render cart ---------------- */
  function renderCart() {
    const list = $("#cartItems"), empty = $("#cartEmpty"), sub = $("#cartSubtotal");
    if (!list) return;
    if (Cart.items.length === 0) { list.innerHTML = ""; empty && empty.classList.remove("hidden"); }
    else {
      empty && empty.classList.add("hidden");
      list.innerHTML = Cart.items.map((i) => `
        <div class="flex gap-3 py-4 border-b border-slate-100">
          <img src="${i.img}" alt="${i.title}" class="h-16 w-16 rounded-lg object-cover bg-slate-100" loading="lazy">
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-ink truncate">${i.title}</p>
            <p class="text-xs text-slate-500 mt-0.5">${i.variant || ""}</p>
            <div class="mt-2 flex items-center justify-between">
              <div class="inline-flex items-center rounded-md border border-slate-200">
                <button class="px-2.5 py-1 text-slate-500 hover:text-brand-600" data-qty-dec="${i.id}">&minus;</button>
                <span class="w-7 text-center text-sm font-semibold">${i.qty}</span>
                <button class="px-2.5 py-1 text-slate-500 hover:text-brand-600" data-qty-inc="${i.id}">+</button>
              </div>
              <span class="text-sm font-bold text-brand-600">${money(i.price * i.qty)}</span>
            </div>
          </div>
          <button class="text-slate-300 hover:text-brand-600 self-start" data-cart-remove="${i.id}" aria-label="Remove">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18" stroke-linecap="round"/></svg>
          </button>
        </div>`).join("");
    }
    if (sub) sub.textContent = money(Cart.subtotal());
    list.querySelectorAll("[data-qty-inc]").forEach((b) => b.addEventListener("click", () => { const id = b.dataset.qtyInc; const it = Cart.items.find((x) => x.id === id); Cart.setQty(id, it.qty + 1); renderCart(); updateCount(); }));
    list.querySelectorAll("[data-qty-dec]").forEach((b) => b.addEventListener("click", () => { const id = b.dataset.qtyDec; const it = Cart.items.find((x) => x.id === id); Cart.setQty(id, it.qty - 1); renderCart(); updateCount(); }));
    list.querySelectorAll("[data-cart-remove]").forEach((b) => b.addEventListener("click", () => { Cart.remove(b.dataset.cartRemove); renderCart(); updateCount(); }));
  }

  /* ---------------- Add to cart ---------------- */
  $$("[data-add-cart]").forEach((btn) => btn.addEventListener("click", (e) => {
    e.preventDefault();
    const p = {
      id: btn.dataset.id || ("s" + Math.random().toString(36).slice(2, 8)),
      title: btn.dataset.title || "Product",
      price: parseFloat(btn.dataset.price || "0"),
      img: btn.dataset.img || "",
      variant: btn.dataset.variant || "",
      qty: parseInt(btn.dataset.qty || "1", 10),
    };
    Cart.add(p); updateCount(); toast(`Added “${p.title}” to cart`);
    if (btn.hasAttribute("data-open-after")) openCart();
  }));

  /* ---------------- Wishlist ---------------- */
  $$(".wish-btn").forEach((b) => b.addEventListener("click", (e) => { e.preventDefault(); b.classList.toggle("is-active"); toast(b.classList.contains("is-active") ? "Added to wishlist" : "Removed from wishlist"); }));

  /* ---------------- Steppers ---------------- */
  $$("[data-stepper]").forEach((wrap) => {
    const input = $("input", wrap);
    $("[data-inc]", wrap)?.addEventListener("click", () => { input.value = Math.max(1, (+input.value || 1) + 1); });
    $("[data-dec]", wrap)?.addEventListener("click", () => { input.value = Math.max(1, (+input.value || 1) - 1); });
  });

  /* ---------------- Toast ---------------- */
  let toastTimer;
  function toast(msg) {
    let t = $("#toast");
    if (!t) { t = document.createElement("div"); t.id = "toast"; t.className = "toast fixed z-[60] bottom-6 left-1/2 -translate-x-1/2 translate-y-24 opacity-0 rounded-lg bg-ink text-white text-sm font-semibold px-5 py-3 shadow-2xl flex items-center gap-2"; document.body.appendChild(t); }
    t.innerHTML = `<svg class="h-4 w-4 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/></svg><span>${msg}</span>`;
    requestAnimationFrame(() => { t.style.transform = "translateX(-50%) translateY(0)"; t.style.opacity = "1"; });
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { t.style.transform = "translateX(-50%) translateY(6rem)"; t.style.opacity = "0"; }, 2400);
  }

  /* ---------------- Hero slider ---------------- */
  const slider = $("#heroSlider");
  if (slider) {
    const slides = $$("[data-slide]", slider);
    const dots = $$("[data-dot]", slider);
    let idx = 0, timer;
    const go = (n) => {
      idx = (n + slides.length) % slides.length;
      slides.forEach((s, i) => s.classList.toggle("is-hidden", i !== idx));
      dots.forEach((d, i) => { d.classList.toggle("bg-white", i === idx); d.classList.toggle("w-6", i === idx); d.classList.toggle("bg-white/50", i !== idx); d.classList.toggle("w-2", i !== idx); });
    };
    const next = () => go(idx + 1);
    const start = () => { timer = setInterval(next, 5000); };
    const stop = () => clearInterval(timer);
    dots.forEach((d, i) => d.addEventListener("click", () => { go(i); stop(); start(); }));
    $("[data-hero-next]", slider)?.addEventListener("click", () => { next(); stop(); start(); });
    $("[data-hero-prev]", slider)?.addEventListener("click", () => { go(idx - 1); stop(); start(); });
    slider.addEventListener("mouseenter", stop); slider.addEventListener("mouseleave", start);
    go(0); start();
  }

  /* ---------------- Countdowns ---------------- */
  $$("[data-countdown]").forEach((cd) => {
    const hrs = parseFloat(cd.getAttribute("data-countdown") || "10");
    const end = Date.now() + hrs * 3.6e6;
    const pad = (n) => String(n).padStart(2, "0");
    const tick = () => {
      let diff = Math.max(0, end - Date.now());
      const h = Math.floor(diff / 3.6e6); diff -= h * 3.6e6;
      const m = Math.floor(diff / 6e4); diff -= m * 6e4;
      const s = Math.floor(diff / 1e3);
      $("[data-h]", cd) && ($("[data-h]", cd).textContent = pad(h));
      $("[data-m]", cd) && ($("[data-m]", cd).textContent = pad(m));
      $("[data-s]", cd) && ($("[data-s]", cd).textContent = pad(s));
    };
    tick(); setInterval(tick, 1000);
  });

  /* ---------------- Scroll reveal ---------------- */
  const revealEls = $$(".reveal");
  if ("IntersectionObserver" in window && revealEls.length) {
    const io = new IntersectionObserver((ents) => ents.forEach((en) => { if (en.isIntersecting) { en.target.classList.add("in-view"); io.unobserve(en.target); } }), { threshold: 0.1 });
    revealEls.forEach((el) => io.observe(el));
  } else revealEls.forEach((el) => el.classList.add("in-view"));

  /* ---------------- Back to top ---------------- */
  const toTop = $("#backToTop");
  if (toTop) {
    const tog = () => { const hide = window.scrollY < 500; toTop.classList.toggle("opacity-0", hide); toTop.classList.toggle("pointer-events-none", hide); };
    window.addEventListener("scroll", tog, { passive: true }); tog();
    toTop.addEventListener("click", () => window.scrollTo({ top: 0, behavior: "smooth" }));
  }

  /* ---------------- Product gallery ---------------- */
  const mainImg = $("#galleryMain");
  if (mainImg) $$("[data-thumb]").forEach((t) => t.addEventListener("click", () => {
    mainImg.src = t.dataset.thumb;
    $$("[data-thumb]").forEach((x) => x.classList.remove("ring-2", "ring-brand-500"));
    t.classList.add("ring-2", "ring-brand-500");
  }));

  /* ---------------- Tabs ---------------- */
  $$("[data-tab]").forEach((tab) => tab.addEventListener("click", () => {
    const g = tab.closest("[data-tabs]"), name = tab.dataset.tab;
    $$("[data-tab]", g).forEach((t) => { t.classList.remove("text-brand-600", "border-brand-500"); t.classList.add("text-slate-500", "border-transparent"); });
    tab.classList.add("text-brand-600", "border-brand-500"); tab.classList.remove("text-slate-500", "border-transparent");
    $$("[data-panel]", g).forEach((p) => p.classList.toggle("hidden", p.dataset.panel !== name));
  }));

  /* ---------------- Accordions ---------------- */
  $$("[data-acc-toggle]").forEach((btn) => btn.addEventListener("click", () => {
    const body = btn.nextElementSibling, icon = $("[data-acc-icon]", btn);
    const open = body.style.maxHeight && body.style.maxHeight !== "0px";
    if (open) { body.style.maxHeight = "0px"; icon && (icon.style.transform = "rotate(0deg)"); }
    else { body.style.maxHeight = body.scrollHeight + "px"; icon && (icon.style.transform = "rotate(180deg)"); }
  }));

  /* ---------------- Price range ---------------- */
  const range = $("#priceRange");
  if (range) { const label = $("#priceRangeLabel"); const upd = () => label && (label.textContent = "$0 — $" + range.value); range.addEventListener("input", upd); upd(); }

  /* ---------------- Newsletter ---------------- */
  $$("[data-newsletter]").forEach((f) => f.addEventListener("submit", (e) => { e.preventDefault(); f.reset(); toast("Subscribed! 🎉"); }));

  /* ---------------- Demo images: retry throttled loads, then graceful fallback ---------------- */
  (function imageFix() {
    const hueFrom = (s) => { let h = 0; for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) % 360; return h; };
    const ph = (src) => {
      const hue = hueFrom(src || "x");
      const c1 = `hsl(${hue},64%,62%)`, c2 = `hsl(${(hue + 38) % 360},66%,42%)`;
      const svg = `<svg xmlns='http://www.w3.org/2000/svg' width='600' height='600'><defs><linearGradient id='g' x1='0' y1='0' x2='1' y2='1'><stop offset='0' stop-color='${c1}'/><stop offset='1' stop-color='${c2}'/></linearGradient></defs><rect width='600' height='600' fill='url(#g)'/><g fill='none' stroke='white' stroke-opacity='.55' stroke-width='14' stroke-linecap='round' stroke-linejoin='round'><rect x='170' y='185' width='260' height='230' rx='26'/><circle cx='250' cy='258' r='30'/><path d='M180 360l92-86 70 64 46-44 32 30'/></g></svg>`;
      return "data:image/svg+xml;charset=utf-8," + encodeURIComponent(svg);
    };
    const onErr = (img) => {
      const orig = img.dataset.os || img.getAttribute("src") || "";
      const tries = +(img.dataset.try || 0);
      if (tries < 3 && /picsum\.photos/.test(orig)) {
        img.dataset.os = orig; img.dataset.try = String(tries + 1);
        const base = orig.split("#")[0];
        setTimeout(() => { img.src = base + (base.indexOf("?") > -1 ? "&" : "?") + "retry=" + Date.now(); }, 500 + tries * 800);
        return;
      }
      if (img.dataset.ph) return; img.dataset.ph = "1"; img.src = ph(orig || img.alt || "");
    };
    $$("img").forEach((img) => { img.addEventListener("error", () => onErr(img)); if (img.complete && img.naturalWidth === 0 && img.getAttribute("src")) onErr(img); });
  })();

  /* ---------------- Init ---------------- */
  updateCount(); renderCart();
  $("#year") && ($("#year").textContent = new Date().getFullYear());
})();
