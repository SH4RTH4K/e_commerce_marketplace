/* =========================================================================
   Shopzy storefront JS — server-backed cart + Ecommerce6-Shopzy theme UI behaviours.
   ========================================================================= */
(function () {
  "use strict";
  const $  = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
  const csrf = () => (document.querySelector('meta[name="csrf-token"]') || {}).content || "";
    const appBase = () => {
    const metaEl = document.querySelector('meta[name="app-base"]');
    const deriveFromScript = () => {
      const script = document.querySelector('script[src*="storefront.js"]');
      if (!script) return "";
      try {
        const u = new URL(script.getAttribute("src"), window.location.href);
        return u.pathname.replace(/\/theme\/js\/storefront\.js.*$/i, "").replace(/\/$/, "");
      } catch (e) {
        return "";
      }
    };
    if (metaEl) {
      const fromMeta = String(metaEl.content || "").replace(/\/$/, "");
      if (fromMeta) return fromMeta;
      // Meta present but empty usually means domain root. Only override when the
      // current page clearly lives under the asset-derived prefix (subdir hosting).
      const derived = deriveFromScript();
      if (derived && (window.location.pathname === derived || window.location.pathname.startsWith(derived + "/"))) {
        return derived;
      }
      return "";
    }
    return deriveFromScript();
  };
  const appUrl = (path) => {
    if (!path) return appBase() || "/";
    if (/^https?:\/\//i.test(path)) return path;
    return appBase() + (path.startsWith("/") ? path : "/" + path);
  };
  const money = (n) => "৳" + Number(n).toLocaleString("en-US", { maximumFractionDigits: 0 });

    async function api(url, body) {
    const res = await fetch(appUrl(url), {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": csrf(), "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
      body: JSON.stringify(body || {}),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      let message = data.message || "Request failed";
      if (res.status === 419) message = "Session expired. Please refresh the page and try again.";
      else if (res.status === 404) message = "Cart endpoint not found. Check APP_URL / public folder hosting.";
      else if (!data.message && res.status >= 500) message = "Server error. Please try again.";
      const err = new Error(message);
      err.data = data;
      err.status = res.status;
      throw err;
    }
    return data;
  }

  /* ---------------- Header scroll ---------------- */
  const header = $(".site-header");
  if (header) {
    const onScroll = () => header.classList.toggle("scrolled", window.scrollY > 16);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
  }

  /* ---------------- Overlay / panels ---------------- */
  const overlay = $("#overlay"), cartDrawer = $("#cartDrawer"), mobileMenu = $("#mobileMenu"), filterPanel = $("#filterPanel");
  const mobileCats = $("[data-mobile-menu]");
  const openOverlay = () => { overlay && overlay.classList.remove("opacity-0", "pointer-events-none", "hidden"); document.body.classList.add("no-scroll"); };
  const anyOpen = () =>
    (cartDrawer && !cartDrawer.classList.contains("translate-x-full")) ||
    (mobileMenu && !mobileMenu.classList.contains("-translate-x-full")) ||
    (filterPanel && !filterPanel.classList.contains("-translate-x-full"));
  const maybeClose = () => { if (!anyOpen()) { overlay && overlay.classList.add("opacity-0", "pointer-events-none"); document.body.classList.remove("no-scroll"); } };
  const closeDrawers = () => {
    cartDrawer && cartDrawer.classList.add("translate-x-full");
    mobileMenu && mobileMenu.classList.add("-translate-x-full");
    filterPanel && filterPanel.classList.add("-translate-x-full");
    document.body.classList.remove("cart-drawer-open");
  };
  const closeAll = () => {
    closeDrawers();
    closeMobileSearch();
    closeAccountMenus();
    closeFloatingChat();
    maybeClose();
  };

  function openCart() {
    closeMobileSearch();
    closeAccountMenus();
    closeFloatingChat();
    mobileMenu && mobileMenu.classList.add("-translate-x-full");
    filterPanel && filterPanel.classList.add("-translate-x-full");
    cartDrawer && cartDrawer.classList.remove("translate-x-full");
    document.body.classList.add("cart-drawer-open");
    openOverlay();
  }
  function closeCart() {
    cartDrawer && cartDrawer.classList.add("translate-x-full");
    document.body.classList.remove("cart-drawer-open");
    maybeClose();
  }

  function openMenu() {
    closeMobileSearch();
    closeAccountMenus();
    cartDrawer && cartDrawer.classList.add("translate-x-full");
    filterPanel && filterPanel.classList.add("-translate-x-full");
    if (mobileMenu) {
      mobileMenu.classList.remove("-translate-x-full");
      openOverlay();
      return;
    }
    if (mobileCats) mobileCats.classList.toggle("hidden");
  }

  function openFilter() {
    closeMobileSearch();
    closeAccountMenus();
    cartDrawer && cartDrawer.classList.add("translate-x-full");
    mobileMenu && mobileMenu.classList.add("-translate-x-full");
    filterPanel && filterPanel.classList.remove("-translate-x-full");
    openOverlay();
  }

  $$("[data-menu-btn], [data-open-menu]").forEach((b) => b.addEventListener("click", (e) => {
    e.preventDefault();
    openMenu();
  }));
  $$("[data-close-menu]").forEach((b) => b.addEventListener("click", () => { mobileMenu && mobileMenu.classList.add("-translate-x-full"); maybeClose(); }));
  $$("[data-open-filter]").forEach((b) => b.addEventListener("click", (e) => { e.preventDefault(); openFilter(); }));
  $$("[data-close-filter]").forEach((b) => b.addEventListener("click", () => { filterPanel && filterPanel.classList.add("-translate-x-full"); maybeClose(); }));
  $$("[data-open-cart]").forEach((b) => b.addEventListener("click", (e) => { e.preventDefault(); openCart(); }));
  $$("[data-close-cart]").forEach((b) => b.addEventListener("click", closeCart));
  if (overlay) overlay.addEventListener("click", closeAll);
  document.addEventListener("keydown", (e) => { if (e.key === "Escape") closeAll(); });

  /* ---------------- Mobile search ---------------- */
  function closeMobileSearch() {
    $$("#mobileSearchPanel").forEach((panel) => panel.classList.add("hidden"));
    $$("[data-toggle-search]").forEach((b) => b.setAttribute("aria-expanded", "false"));
  }
  function openMobileSearch(btn) {
    closeAccountMenus();
    closeDrawers();
    maybeClose();
    const panel = (btn && btn.closest("header")?.querySelector("#mobileSearchPanel")) || $("#mobileSearchPanel");
    if (!panel) return;
    panel.classList.remove("hidden");
    $$("[data-toggle-search]").forEach((b) => b.setAttribute("aria-expanded", "true"));
    const input = panel.querySelector("[data-mobile-search-input]");
    if (input) setTimeout(() => input.focus(), 30);
  }
  $$("[data-toggle-search]").forEach((btn) => {
    btn.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();
      const panel = btn.closest("header")?.querySelector("#mobileSearchPanel") || $("#mobileSearchPanel");
      if (!panel) return;
      const open = !panel.classList.contains("hidden");
      if (open) closeMobileSearch();
      else openMobileSearch(btn);
    });
  });

  /* ---------------- Account dropdown ---------------- */
  function closeAccountMenus() {
    $$("[data-account-dropdown]").forEach((d) => d.classList.add("hidden"));
    $$("[data-account-toggle]").forEach((b) => b.setAttribute("aria-expanded", "false"));
  }
  $$("[data-account-menu]").forEach((menu) => {
    const toggle = $("[data-account-toggle]", menu);
    const dropdown = $("[data-account-dropdown]", menu);
    if (!toggle || !dropdown) return;
    toggle.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();
      const willOpen = dropdown.classList.contains("hidden");
      closeAccountMenus();
      closeMobileSearch();
      if (willOpen) {
        dropdown.classList.remove("hidden");
        toggle.setAttribute("aria-expanded", "true");
      }
    });
  });
  document.addEventListener("click", (e) => {
    if (!e.target.closest("[data-account-menu]")) closeAccountMenus();
  });

  /* ---------------- Mega menu ---------------- */
  const megaBtn = $("#megaBtn"), megaMenu = $("#megaMenu");
  if (megaBtn && megaMenu) {
    megaBtn.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();
      megaMenu.classList.toggle("hidden");
    });
    document.addEventListener("click", (e) => {
      if (!megaMenu.contains(e.target) && !megaBtn.contains(e.target)) megaMenu.classList.add("hidden");
    });
  }

  /* ---------------- Cart badge + drawer sync (#cartItems / #cartEmpty / #cartFooter) ---------------- */
  function applyCart(data) {
    if (!data) return;
    const count = data.cart ? Number(data.cart.count) || 0 : 0;
    const subtotal = data.cart ? data.cart.subtotal : 0;

    $$(".cart-count").forEach((el) => {
      el.textContent = count;
      el.classList.toggle("hidden", count === 0);
      // Restart bump animation without triggering forced reflow
      el.classList.remove("bump");
      requestAnimationFrame(() => requestAnimationFrame(() => el.classList.add("bump")));
    });
    $$(".cart-total").forEach((el) => (el.textContent = money(subtotal)));

    const list = $("#cartItems");
    const empty = $("#cartEmpty");
    const footer = $("#cartFooter");
    const sub = $("#cartSubtotal");

    if (typeof data.drawer === "string" && list) {
      list.innerHTML = data.drawer.trim();
    }

    const hasItems = count > 0;
    if (list) list.classList.toggle("hidden", !hasItems);
    if (empty) empty.classList.toggle("hidden", hasItems);
    if (footer) footer.classList.toggle("hidden", !hasItems);
    if (sub) sub.textContent = money(subtotal);
  }

  // Event delegation — survives AJAX re-renders after +/- / remove
  if (cartDrawer) {
    cartDrawer.addEventListener("click", async (e) => {
      const checkout = e.target.closest("[data-cart-checkout]");
      if (checkout && cartDrawer.contains(checkout)) {
        // Phone: avoid lost taps from overlay/chat — force navigation
        e.preventDefault();
        window.location.assign(checkout.getAttribute("href") || appUrl("/checkout"));
        return;
      }

      const btn = e.target.closest("[data-cart-inc], [data-cart-dec], [data-cart-remove]");
      if (!btn || !cartDrawer.contains(btn)) return;
      e.preventDefault();
      e.stopPropagation();
      const key = btn.dataset.key;
      if (!key) return;
      try {
        if (btn.hasAttribute("data-cart-remove")) {
          applyCart(await api("/cart/remove", { key }));
          return;
        }
        const qty = Number(btn.dataset.qty) || 1;
        const next = btn.hasAttribute("data-cart-inc") ? qty + 1 : Math.max(0, qty - 1);
        applyCart(await api("/cart/update", { key, qty: next }));
      } catch (err) {
        toast(err.message || "Could not update cart.");
      }
    });
  }

  /* ---------------- Add to cart ---------------- */
  async function addToCart(productId, qty, variant, title, openAfter, redirectUrl) {
    try {
      const data = await api("/cart/add", { product_id: productId, qty: qty || 1, variant: variant || null });
      applyCart(data);
      if (redirectUrl) {
        window.location.href = redirectUrl;
        return true;
      }
      if (openAfter !== false) openCart();
      return true;
    } catch (err) {
      toast(err.message || "Could not add to cart. Please try again.");
      return false;
    }
  }

  /* Direct form POST — one browser navigation (same feel as opening a product page). */
  function orderNow(productId, qty, variant) {
    if (!productId) return false;
    const form = document.createElement("form");
    form.method = "POST";
    form.action = appUrl("/cart/buy-now");
    form.style.display = "none";
    const fields = {
      _token: csrf(),
      product_id: String(productId),
      qty: String(Math.max(1, qty || 1)),
    };
    if (variant) fields.variant = String(variant);
    Object.keys(fields).forEach((name) => {
      const input = document.createElement("input");
      input.type = "hidden";
      input.name = name;
      input.value = fields[name];
      form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
    return true;
  }


  function selectedVariantLabel() {
    const parts = [];
    $$("[data-variant-group]").forEach((group) => {
      const sel = group.querySelector(".variant-btn.is-selected");
      if (sel) parts.push(group.dataset.variantGroup + ": " + sel.dataset.value);
    });
    return parts.join(", ") || null;
  }

  $$(".add-to-cart, [data-add-cart]").forEach((btn) => btn.addEventListener("click", (e) => {
    e.preventDefault();
    if (btn.disabled) return;
    const productId = btn.dataset.productId || btn.dataset.id;
    const openAfter = btn.hasAttribute("data-open-after");
    addToCart(productId, 1, null, btn.dataset.title, openAfter);
  }));

  // Skip legacy pd handlers when #pdPurchaseForm exists (native POST form with AJAX handling)
  const pdBtn = $("#pdAddToCart");
  if (pdBtn && !$("#pdPurchaseForm")) {
    pdBtn.addEventListener("click", (e) => {
      e.preventDefault();
      if (pdBtn.disabled) return;
      const qty = Math.max(1, +($("#pdQty") ? $("#pdQty").value : 1) || 1);
      addToCart(pdBtn.dataset.productId, qty, selectedVariantLabel(), pdBtn.dataset.title, true);
    });
  }

  $$(".order-now, [data-order-now]").forEach((btn) => btn.addEventListener("click", (e) => {
    e.preventDefault();
    if (btn.disabled) return;
    const productId = btn.dataset.productId || btn.dataset.id;
    orderNow(productId, 1, null, btn.dataset.title);
  }));

  const pdBuyNow = $("#pdBuyNow") || $("[data-buy-now]");
  if (pdBuyNow && !$("#pdPurchaseForm")) {
    pdBuyNow.addEventListener("click", (e) => {
      e.preventDefault();
      if (pdBuyNow.disabled) return;
      const source = pdBtn || pdBuyNow;
      const qty = Math.max(1, +($("#pdQty") ? $("#pdQty").value : 1) || 1);
      orderNow(source.dataset.productId || pdBuyNow.dataset.productId, qty, selectedVariantLabel(), source.dataset.title || pdBuyNow.dataset.title);
    });
  }

  $$("[data-stepper]").forEach((wrap) => {
    const input = $("input", wrap);
    $("[data-inc]", wrap)?.addEventListener("click", () => { input.value = Math.max(1, (+input.value || 1) + 1); });
    $("[data-dec]", wrap)?.addEventListener("click", () => { input.value = Math.max(1, (+input.value || 1) - 1); });
    $$("[data-step]", wrap).forEach((b) => b.addEventListener("click", () => {
      const step = parseInt(b.dataset.step, 10) || 0;
      input.value = Math.max(1, (+input.value || 1) + step);
    }));
  });

  let toastTimer;
  function toast(msg) {
    let t = $("#toast") || $("#Shopzy-toast");
    if (!t) {
      t = document.createElement("div");
      t.id = "Shopzy-toast";
      t.className = "fixed bottom-6 left-1/2 -translate-x-1/2 z-[9999] bg-ink text-white text-sm px-5 py-3 rounded-xl shadow-lg opacity-0 transition-opacity duration-300 pointer-events-none";
      t.setAttribute("aria-live", "polite");
      document.body.appendChild(t);
    }
    t.textContent = msg;
    t.style.opacity = "1";
    t.style.pointerEvents = "none";
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      t.style.opacity = "0";
      t.style.pointerEvents = "none";
    }, 2200);
  }

  const revealEls = $$(".reveal, [data-reveal]");
  if ("IntersectionObserver" in window && revealEls.length) {
    const io = new IntersectionObserver((ents) => ents.forEach((en) => { if (en.isIntersecting) { en.target.classList.add("in-view", "revealed"); io.unobserve(en.target); } }), { threshold: 0.1 });
    revealEls.forEach((el) => io.observe(el));
  } else revealEls.forEach((el) => el.classList.add("in-view", "revealed"));

  
  /* ---------------- Floating chat ---------------- */
  const floatingChat = $("#floatingChat");
  const floatingChatTrigger = $("#floatingChatTrigger");
  const floatingChatActions = $("#floatingChatActions");
  const closeFloatingChat = () => {
    if (!floatingChatActions || !floatingChatTrigger) return;
    floatingChatActions.classList.remove("is-open");
    floatingChatActions.setAttribute("aria-hidden", "true");
    floatingChatTrigger.setAttribute("aria-expanded", "false");
    document.body.classList.remove("floating-chat-open");
  };
  if (floatingChat && floatingChatTrigger && floatingChatActions) {
    document.body.classList.add("has-floating-chat");
    let floatingChatLock = false;
    floatingChatTrigger.addEventListener("click", (e) => {
      e.stopPropagation();
      floatingChatLock = true;
      const open = !floatingChatActions.classList.contains("is-open");
      if (open) {
        floatingChatActions.classList.add("is-open");
        floatingChatActions.setAttribute("aria-hidden", "false");
        floatingChatTrigger.setAttribute("aria-expanded", "true");
        document.body.classList.add("floating-chat-open");
      } else {
        closeFloatingChat();
      }
      setTimeout(() => { floatingChatLock = false; }, 0);
    });
    floatingChatActions.addEventListener("click", (e) => e.stopPropagation());
    document.addEventListener("click", () => {
      if (floatingChatLock) return;
      closeFloatingChat();
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closeFloatingChat();
    });
  }

  const toTop = $("#backToTop") || $("[data-back-top]");
  if (toTop) {
    const tog = () => { const hide = window.scrollY < 400; toTop.classList.toggle("opacity-0", hide); toTop.classList.toggle("pointer-events-none", hide); };
    window.addEventListener("scroll", tog, { passive: true }); tog();
    toTop.addEventListener("click", () => window.scrollTo({ top: 0, behavior: "smooth" }));
  }

  const mainImg = $("#galleryMain") || $("[data-gallery-main]");
  if (mainImg) $$("[data-thumb]").forEach((t) => t.addEventListener("click", () => {
    mainImg.src = t.dataset.thumb;
    $$("[data-thumb]").forEach((x) => {
      x.classList.remove("ring-2", "ring-brand-500", "border-brand-600", "border-2");
      x.classList.add("border", "border-gray-200");
    });
    t.classList.add("border-2", "border-brand-600");
    t.classList.remove("border-gray-200");
  }));

  $$("[data-tabs]").forEach((group) => {
    $$("[data-tab]", group).forEach((tab) => tab.addEventListener("click", () => {
      const name = tab.dataset.tab;
      $$("[data-tab]", group).forEach((t) => {
        t.classList.remove("tab-active", "bg-brand-600", "text-white", "border-brand-600");
        t.classList.add("text-gray-500", "border-transparent");
      });
      tab.classList.add("tab-active");
      tab.classList.remove("text-gray-500", "border-transparent");
      const root = group;
      $$("[data-pane], [data-panel]", root).forEach((p) => {
        const key = p.dataset.pane || p.dataset.panel;
        p.classList.toggle("hidden", key !== name);
      });
    }));
  });

  $$("[data-acc-toggle]").forEach((btn) => btn.addEventListener("click", () => {
    const body = btn.nextElementSibling, icon = $("[data-acc-icon]", btn);
    const open = body.style.maxHeight && body.style.maxHeight !== "0px";
    if (open) { body.style.maxHeight = "0px"; icon && (icon.style.transform = "rotate(0deg)"); }
    else { body.style.maxHeight = body.scrollHeight + "px"; icon && (icon.style.transform = "rotate(180deg)"); }
  }));

  const range = $("#priceRange");
  if (range) {
    const label = $("#priceRangeLabel");
    const form = range.closest("form");
    const applyBtn = $("#priceApplyBtn");
    const upd = () => {
      if (label) label.textContent = "৳0 — ৳" + Number(range.value).toLocaleString("en-US");
    };
    range.addEventListener("input", upd);
    upd();

    if (applyBtn && form) {
      applyBtn.addEventListener("click", () => {
        let maxInput = form.querySelector("#priceMax");
        let minInput = form.querySelector("#priceMin");
        if (!maxInput) {
          maxInput = document.createElement("input");
          maxInput.type = "hidden";
          maxInput.name = "max";
          maxInput.id = "priceMax";
          form.appendChild(maxInput);
        }
        if (!minInput) {
          minInput = document.createElement("input");
          minInput.type = "hidden";
          minInput.name = "min";
          minInput.id = "priceMin";
          form.appendChild(minInput);
        }
        maxInput.value = range.value;
        minInput.value = "0";
        form.submit();
      });
    }
  }

  /* ---------------- Countdowns (data-countdown-end ISO datetime) ---------------- */
  $$("[data-countdown-end]").forEach((cd) => {
    const endAttr = cd.getAttribute("data-countdown-end");
    const end = endAttr ? Date.parse(endAttr) : NaN;
    if (!end || Number.isNaN(end)) return;
    const pad = (n) => String(n).padStart(2, "0");
    const tick = () => {
      let diff = Math.max(0, end - Date.now());
      const d = Math.floor(diff / 8.64e7); diff -= d * 8.64e7;
      const h = Math.floor(diff / 3.6e6); diff -= h * 3.6e6;
      const m = Math.floor(diff / 6e4); diff -= m * 6e4;
      const s = Math.floor(diff / 1e3);
      if ($("[data-d]", cd)) $("[data-d]", cd).textContent = pad(d);
      if ($("[data-h]", cd)) $("[data-h]", cd).textContent = pad(h);
      if ($("[data-m]", cd)) $("[data-m]", cd).textContent = pad(m);
      if ($("[data-s]", cd)) $("[data-s]", cd).textContent = pad(s);
    };
    tick();
    setInterval(tick, 1000);
  });
  /* ---------------- Hero slider (pointer swipe — DP-Site style) ---------------- */
  const slider = $("#heroSlider");
  if (slider) {
    const track = $("[data-hero-track]", slider);
    const slides = $$("[data-slide]", slider);
    const dots = $$("[data-dot]", slider);
    if (track && slides.length) {
      const DRAG_PX = 10;
      let idx = 0, timer, pointerId = null, startX = 0, dragX = 0, moved = false;
      let navLock = false, allowClick = false, suppressClick = false, startTarget = null;

      const width = () => slider.offsetWidth || 1;
      const paint = (offset = 0, animate = true) => {
        track.classList.toggle("is-dragging", !animate);
        track.style.transform = `translate3d(${-(idx * width()) + offset}px, 0, 0)`;
      };
      const syncDots = () => {
        dots.forEach((d, i) => {
          d.classList.toggle("bg-white", i === idx);
          d.classList.toggle("w-6", i === idx);
          d.classList.toggle("bg-white/50", i !== idx);
          d.classList.toggle("w-2", i !== idx);
        });
      };
      const go = (n, animate = true) => {
        idx = (n + slides.length) % slides.length;
        paint(0, animate);
        syncDots();
      };
      const next = () => go(idx + 1);
      const start = () => { if (slides.length > 1) timer = setInterval(next, 5000); };
      const stop = () => clearInterval(timer);

      const activateTarget = (target, e) => {
        const a = target?.closest?.("a");
        if (!a || !slider.contains(a) || !a.getAttribute("href")) return;
        navLock = true;
        if (e && (e.ctrlKey || e.metaKey || e.button === 1)) {
          window.open(a.href, "_blank");
        } else {
          allowClick = true;
          a.click();
          allowClick = false;
        }
      };

      const endDrag = (e) => {
        if (pointerId === null) return;
        const pid = pointerId;
        const wasMoved = moved;
        const target = startTarget;

        try { track.releasePointerCapture(pid); } catch (_) { /* ignore */ }
        pointerId = null;
        startTarget = null;
        slider.classList.remove("is-grabbing");
        track.classList.remove("is-dragging");

        if (wasMoved) {
          const threshold = width() * 0.16;
          if (dragX < -threshold) go(idx + 1);
          else if (dragX > threshold) go(idx - 1);
          else paint(0, true);
          suppressClick = true;
        } else {
          paint(0, true);
          if (target && e && (e.pointerType === "mouse" || e.pointerType === "pen")) {
            activateTarget(target, e);
          }
        }

        dragX = 0;
        moved = false;
        start();
      };

      if (slides.length > 1) {
        track.addEventListener("pointerdown", (e) => {
          if (e.pointerType === "mouse" && e.button !== 0) return;
          if (e.target.closest("[data-dot]")) return;
          pointerId = e.pointerId;
          startX = e.clientX;
          dragX = 0;
          moved = false;
          navLock = false;
          suppressClick = false;
          startTarget = e.target;
          stop();
          slider.classList.add("is-grabbing");
          track.classList.add("is-dragging");
          try { track.setPointerCapture(e.pointerId); } catch (_) { /* ignore */ }
        });
        track.addEventListener("pointermove", (e) => {
          if (e.pointerId !== pointerId) return;
          dragX = e.clientX - startX;
          if (!moved && Math.abs(dragX) > DRAG_PX) moved = true;
          if (moved) paint(dragX, false);
        });
        track.addEventListener("pointerup", (e) => { if (e.pointerId === pointerId) endDrag(e); });
        track.addEventListener("pointercancel", (e) => { if (e.pointerId === pointerId) endDrag(e); });

        slider.addEventListener("click", (e) => {
          if (allowClick) return;
          if (suppressClick || navLock) {
            e.preventDefault();
            e.stopPropagation();
            suppressClick = false;
            navLock = false;
          }
        }, true);

        $$("img, a", slider).forEach((el) => {
          el.addEventListener("dragstart", (ev) => ev.preventDefault());
        });

        dots.forEach((d, i) => d.addEventListener("click", (e) => {
          e.stopPropagation();
          go(i);
          stop();
          start();
        }));
        $("[data-hero-next]", slider)?.addEventListener("click", () => { next(); stop(); start(); });
        $("[data-hero-prev]", slider)?.addEventListener("click", () => { go(idx - 1); stop(); start(); });
        slider.addEventListener("mouseenter", stop);
        slider.addEventListener("mouseleave", start);
        let rafResize;
        window.addEventListener("resize", () => {
          cancelAnimationFrame(rafResize);
          rafResize = requestAnimationFrame(() => paint(0, false));
        });
      }

      go(0, false);
      start();
    }
  }

  (function imageFallback() {
    const hueFrom = (s) => { let h = 0; for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) % 360; return h; };
    const ph = (src) => {
      const hue = hueFrom(src || "x");
      const c1 = `hsl(${hue},64%,62%)`, c2 = `hsl(${(hue + 38) % 360},66%,42%)`;
      const svg = `<svg xmlns='http://www.w3.org/2000/svg' width='600' height='600'><defs><linearGradient id='g' x1='0' y1='0' x2='1' y2='1'><stop offset='0' stop-color='${c1}'/><stop offset='1' stop-color='${c2}'/></linearGradient></defs><rect width='600' height='600' fill='url(#g)'/><g fill='none' stroke='white' stroke-opacity='.55' stroke-width='14' stroke-linecap='round' stroke-linejoin='round'><rect x='170' y='185' width='260' height='230' rx='26'/><circle cx='250' cy='258' r='30'/><path d='M180 360l92-86 70 64 46-44 32 30'/></g></svg>`;
      return "data:image/svg+xml;charset=utf-8," + encodeURIComponent(svg);
    };
    const fix = (img) => { if (img.dataset.ph) return; img.dataset.ph = "1"; img.src = ph(img.getAttribute("src") || img.alt || ""); };
    $$("img").forEach((img) => { if (img.complete && img.naturalWidth === 0 && img.getAttribute("src")) fix(img); else img.addEventListener("error", () => fix(img)); });
  })();

  $("#year") && ($("#year").textContent = new Date().getFullYear());

  window.Storefront = { openCart, closeCart, addToCart, orderNow, applyCart, toast };
})();
