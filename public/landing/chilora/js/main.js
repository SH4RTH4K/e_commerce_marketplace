/**
 * Chilora landing interactions
 */
(function () {
  const cfg = Object.assign(
    {
      phone: "01600-000000",
      offerEndHours: 18,
      regularPrice: 1650,
      offerPrice: 1200,
      shipping: { dhaka: 80, nearby: 100, outside: 130 },
    },
    window.CHILORA || {}
  );

  /* Countdown */
  const end = Date.now() + cfg.offerEndHours * 3600 * 1000;
  function tick() {
    const left = Math.max(0, end - Date.now());
    const h = Math.floor(left / 3600000);
    const m = Math.floor((left % 3600000) / 60000);
    const s = Math.floor((left % 60000) / 1000);
    const set = (id, v) => {
      const el = document.getElementById(id);
      if (el) el.textContent = String(v).padStart(2, "0");
    };
    set("cd-h", h);
    set("cd-m", m);
    set("cd-s", s);
  }
  tick();
  setInterval(tick, 1000);

  /* Scroll reveal */
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
  } else {
    reveals.forEach((el) => el.classList.add("is-visible"));
  }

  /* Back to top */
  const back = document.getElementById("back-top");
  window.addEventListener("scroll", () => {
    if (!back) return;
    back.classList.toggle("show", window.scrollY > 500);
  });
  back?.addEventListener("click", () => window.scrollTo({ top: 0, behavior: "smooth" }));

  /* Toast */
  const toast = document.getElementById("toast");
  function showToast(msg) {
    if (!toast) return;
    toast.textContent = msg;
    toast.classList.add("show");
    setTimeout(() => toast.classList.remove("show"), 2800);
  }

  /* Order form */
  const qtyInput = document.getElementById("qty");
  const qtyMinus = document.getElementById("qty-minus");
  const qtyPlus = document.getElementById("qty-plus");
  const shipInputs = document.querySelectorAll('input[name="shipping"]');
  const totalEl = document.getElementById("order-total");
  const subEl = document.getElementById("order-subtotal");
  const shipEl = document.getElementById("order-ship");
  const form = document.getElementById("order-form");

  function getShip() {
    const checked = document.querySelector('input[name="shipping"]:checked');
    return checked ? Number(checked.value) : cfg.shipping.dhaka;
  }

  function recalc() {
    const qty = Math.max(1, Number(qtyInput?.value || 1));
    if (qtyInput) qtyInput.value = qty;
    const sub = qty * cfg.offerPrice;
    const ship = getShip();
    const total = sub + ship;
    if (subEl) subEl.textContent = "৳ " + sub.toLocaleString("en-BD");
    if (shipEl) shipEl.textContent = "৳ " + ship.toLocaleString("en-BD");
    if (totalEl) totalEl.textContent = "৳ " + total.toLocaleString("en-BD");
    const btn = document.getElementById("submit-order");
    if (btn) btn.textContent = `অর্ডার করুন ৳ ${total.toLocaleString("en-BD")}`;
  }

  qtyMinus?.addEventListener("click", () => {
    qtyInput.value = Math.max(1, Number(qtyInput.value) - 1);
    recalc();
  });
  qtyPlus?.addEventListener("click", () => {
    qtyInput.value = Number(qtyInput.value) + 1;
    recalc();
  });
  qtyInput?.addEventListener("change", recalc);
  shipInputs.forEach((i) => i.addEventListener("change", recalc));
  recalc();

  form?.addEventListener("submit", (e) => {
    // Store checkout: let the form POST to cart.buy-now (no preventDefault)
    if (cfg.useStoreCheckout && form.getAttribute("action")) {
      return;
    }
    e.preventDefault();
    const name = form.name?.value?.trim() || "";
    const mobile = form.mobile?.value?.trim() || "";
    const address = form.address?.value?.trim() || "";
    if (!name || !mobile || !address) {
      showToast("সব তথ্য পূরণ করুন");
      return;
    }
    if (!/^01[0-9]{9}$/.test(mobile.replace(/[-\s]/g, ""))) {
      showToast("সঠিক মোবাইল নম্বর দিন");
      return;
    }
    const lead = {
      id: "L" + Date.now(),
      name,
      mobile,
      address,
      qty: Number(qtyInput.value),
      shipping: getShip(),
      total: Number(qtyInput.value) * cfg.offerPrice + getShip(),
      createdAt: new Date().toISOString(),
      status: "new",
    };
    const key = "chilora_leads";
    const list = JSON.parse(localStorage.getItem(key) || "[]");
    list.unshift(lead);
    localStorage.setItem(key, JSON.stringify(list));
    form.reset();
    if (qtyInput) qtyInput.value = 1;
    recalc();
    showToast("অর্ডার গ্রহণ হয়েছে! আমরা শীঘ্রই যোগাযোগ করব।");
  });

  /* Contact form */
  document.getElementById("contact-form")?.addEventListener("submit", (e) => {
    e.preventDefault();
    showToast("বার্তা পাঠানো হয়েছে — ধন্যবাদ!");
    e.target.reset();
  });
})();
