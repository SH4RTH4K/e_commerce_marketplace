/** Nuraya landing interactions */
(function () {
  const cfg = Object.assign(
    {
      phone: "01700-000000",
      offerEndHours: 23,
      priceMin: 550,
      priceMax: 630,
      shipping: { dhaka: 70, outside: 120 },
      imgBase: "/landing/nuraya/img",
      products: [
        { id: "n-34", name: "Nuraya Soft Veil — N-34", image: "veil-a.jpg" },
        { id: "n-33", name: "Nuraya Soft Veil — N-33", image: "veil-b.jpg" },
        { id: "n-32", name: "Nuraya Soft Veil — N-32", image: "veil-c.jpg" },
        { id: "n-31", name: "Nuraya Soft Veil — N-31", image: "veil-d.jpg" },
        { id: "n-29", name: "Nuraya Soft Veil — N-29", image: "veil-e.jpg" },
        { id: "n-28", name: "Nuraya Soft Veil — N-28", image: "veil-f.jpg" },
        { id: "n-24", name: "Nuraya Soft Veil — N-24", image: "veil-g.jpg" },
        { id: "n-20", name: "Nuraya Soft Veil — N-20", image: "veil-h.jpg" },
      ],
    },
    window.NURAYA || {}
  );

  function productImg(p) {
    const file = p.image || "thumb.jpg";
    return (cfg.imgBase || "/landing/nuraya/img").replace(/\/$/, "") + "/" + file.replace(/^\//, "");
  }

  /* Countdown */
  const end = Date.now() + cfg.offerEndHours * 3600 * 1000;
  function tick() {
    const left = Math.max(0, end - Date.now());
    const set = (id, v) => {
      const el = document.getElementById(id);
      if (el) el.textContent = String(v).padStart(2, "0");
    };
    set("cd-h", Math.floor(left / 3600000));
    set("cd-m", Math.floor((left % 3600000) / 60000));
    set("cd-s", Math.floor((left % 60000) / 1000));
  }
  tick();
  setInterval(tick, 1000);

  /* Fake live stats mild animation */
  const viewers = document.getElementById("stat-viewers");
  const orders = document.getElementById("stat-orders");
  if (viewers && orders) {
    let v = 45, o = 173;
    setInterval(() => {
      v = Math.max(28, Math.min(68, v + (Math.random() > 0.5 ? 1 : -1)));
      if (Math.random() > 0.7) o += 1;
      viewers.textContent = String(v);
      orders.textContent = String(o);
    }, 4000);
  }

  /* Reveal */
  const reveals = document.querySelectorAll(".reveal");
  if ("IntersectionObserver" in window) {
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((en) => {
          if (en.isIntersecting) {
            en.target.classList.add("is-visible");
            io.unobserve(en.target);
          }
        });
      },
      { threshold: 0.12 }
    );
    reveals.forEach((el) => io.observe(el));
  } else reveals.forEach((el) => el.classList.add("is-visible"));

  /* Back top */
  const back = document.getElementById("back-top");
  window.addEventListener("scroll", () => back?.classList.toggle("show", scrollY > 500));
  back?.addEventListener("click", () => scrollTo({ top: 0, behavior: "smooth" }));

  /* Toast */
  const toast = document.getElementById("toast");
  function showToast(msg) {
    if (!toast) return;
    toast.textContent = msg;
    toast.classList.add("show");
    setTimeout(() => toast.classList.remove("show"), 2800);
  }

  /* Cart / product select */
  const cartList = document.getElementById("cart-list");
  const cart = [];

  function productPrice() {
    return cfg.priceMin;
  }

  function renderCart() {
    if (!cartList) return;
    if (!cart.length) {
      cartList.innerHTML = `<p class="text-sm text-[var(--muted)]">কোনো পণ্য নির্বাচিত নেই — নিচে থেকে বেছে নিন অথবা “আরও যোগ” চাপুন।</p>`;
    } else {
      cartList.innerHTML = cart
        .map(
          (c, i) => `
        <div class="flex items-center justify-between gap-3 rounded-xl border border-[var(--line)] p-3 bg-[var(--mist)]/50">
          <div class="flex gap-3 items-center min-w-0">
            <img src="${productImg(c)}" alt="" class="h-12 w-12 rounded-lg object-cover fabric-ph" width="80" height="80" onerror="this.onerror=null;this.removeAttribute('src')" />
            <div class="min-w-0">
              <p class="font-bold text-sm truncate">${c.name}</p>
              <p class="text-xs text-[var(--muted)]">৳ ${productPrice()}</p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" data-dec="${i}" class="h-8 w-8 rounded-lg border font-bold" aria-label="Decrease">−</button>
            <span class="w-6 text-center font-bold">${c.qty}</span>
            <button type="button" data-inc="${i}" class="h-8 w-8 rounded-lg border font-bold" aria-label="Increase">+</button>
            <button type="button" data-rm="${i}" class="text-[var(--blush)] text-xs font-bold ml-1">✕</button>
          </div>
        </div>`
        )
        .join("");
    }
    recalc();
  }

  function addProduct(p) {
    const existing = cart.find((c) => c.id === p.id);
    if (existing) existing.qty += 1;
    else cart.push({ ...p, qty: 1 });
    document.querySelectorAll(".product-card").forEach((el) => {
      el.classList.toggle("is-selected", el.dataset.id === p.id);
    });
    renderCart();
    showToast("কার্টে যোগ হয়েছে");
  }

  document.getElementById("products")?.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-add]");
    if (!btn) return;
    const id = btn.dataset.add;
    const p = cfg.products.find((x) => x.id === id);
    if (p) addProduct(p);
  });

  document.getElementById("add-more")?.addEventListener("click", () => {
    document.getElementById("products")?.scrollIntoView({ behavior: "smooth" });
  });

  cartList?.addEventListener("click", (e) => {
    const dec = e.target.closest("[data-dec]");
    const inc = e.target.closest("[data-inc]");
    const rm = e.target.closest("[data-rm]");
    if (dec) {
      const i = +dec.dataset.dec;
      cart[i].qty = Math.max(1, cart[i].qty - 1);
      renderCart();
    }
    if (inc) {
      cart[+inc.dataset.inc].qty += 1;
      renderCart();
    }
    if (rm) {
      cart.splice(+rm.dataset.rm, 1);
      renderCart();
    }
  });

  function getShip() {
    const checked = document.querySelector('input[name="shipping"]:checked');
    return checked ? Number(checked.value) : 0;
  }

  function recalc() {
    const sub = cart.reduce((s, c) => s + c.qty * productPrice(), 0);
    const ship = getShip();
    const total = sub + (cart.length ? ship : 0);
    const subEl = document.getElementById("order-subtotal");
    const shipEl = document.getElementById("order-ship");
    const totalEl = document.getElementById("order-total");
    if (subEl) subEl.textContent = "৳" + sub;
    if (shipEl) shipEl.textContent = cart.length ? (ship ? "৳" + ship : "অপশন সিলেক্ট করুন") : "—";
    if (totalEl) totalEl.textContent = "৳" + total;
    const btn = document.getElementById("submit-order");
    if (btn) btn.textContent = cart.length ? `✓ অর্ডার নিশ্চিত করুন — ৳${total}` : "✓ অর্ডার নিশ্চিত করুন";
  }

  document.querySelectorAll('input[name="shipping"]').forEach((i) => i.addEventListener("change", recalc));

  /* Review slider */
  const rail = document.getElementById("review-rail");
  document.getElementById("rev-prev")?.addEventListener("click", () => rail?.scrollBy({ left: -320, behavior: "smooth" }));
  document.getElementById("rev-next")?.addEventListener("click", () => rail?.scrollBy({ left: 320, behavior: "smooth" }));

  /* Hero dots carousel */
  document.getElementById("hero-dots")?.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-hero]");
    if (!btn) return;
    const main = document.getElementById("hero-main");
    if (main) {
      const file = btn.dataset.heroFile || (btn.dataset.hero ? btn.dataset.hero.replace(/^nuraya-veil-/, "veil-") + ".jpg" : "hero.jpg");
      main.src = (cfg.imgBase || "/landing/nuraya/img").replace(/\/$/, "") + "/" + file.replace(/^\//, "");
      main.classList.add("fabric-ph");
    }
    document.querySelectorAll("#hero-dots button").forEach((b) => b.classList.remove("is-active"));
    btn.classList.add("is-active");
  });

  (function autoHero() {
    const dots = [...document.querySelectorAll("#hero-dots [data-hero]")];
    if (dots.length < 2) return;
    let i = 0;
    setInterval(() => {
      i = (i + 1) % dots.length;
      dots[i].click();
    }, 4500);
  })();

  /* Order form */
  document.getElementById("order-form")?.addEventListener("submit", (e) => {
    if (cfg.useStoreCheckout && e.target.getAttribute("action")) {
      return;
    }
    e.preventDefault();
    const form = e.target;
    if (!cart.length) {
      showToast("অন্তত একটি পণ্য যোগ করুন");
      return;
    }
    if (!getShip()) {
      showToast("ডেলিভারি অপশন সিলেক্ট করুন");
      return;
    }
    const name = form.name.value.trim();
    const mobile = form.mobile.value.trim().replace(/[-\s]/g, "");
    const address = form.address.value.trim();
    if (!name || !mobile || !address) {
      showToast("সব তথ্য পূরণ করুন");
      return;
    }
    if (!/^01[0-9]{9}$/.test(mobile)) {
      showToast("সঠিক মোবাইল নম্বর দিন");
      return;
    }
    const lead = {
      id: "N" + Date.now(),
      name,
      mobile,
      address,
      note: form.note.value.trim(),
      items: cart.map((c) => ({ id: c.id, name: c.name, qty: c.qty })),
      shipping: getShip(),
      total: cart.reduce((s, c) => s + c.qty * productPrice(), 0) + getShip(),
      createdAt: new Date().toISOString(),
      status: "new",
    };
    const list = JSON.parse(localStorage.getItem("nuraya_leads") || "[]");
    list.unshift(lead);
    localStorage.setItem("nuraya_leads", JSON.stringify(list));
    cart.length = 0;
    form.reset();
    renderCart();
    showToast("অর্ডার নিশ্চিত হয়েছে! আমরা শীঘ্রই যোগাযোগ করব।");
  });

  /* Seed first product optional — leave empty like reference */
  renderCart();
})();
