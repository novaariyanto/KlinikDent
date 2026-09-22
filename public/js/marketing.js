(() => {
  window.dataLayer = window.dataLayer || [];

  const track = (event, extra = {}) => {
    window.dataLayer.push({ event, ...extra });
  };

  const header = document.querySelector("[data-header]");
  const nav = document.querySelector("[data-nav]");
  const toggle = document.querySelector("[data-nav-toggle]");

  const onScroll = () => {
    header?.classList.toggle("is-stuck", window.scrollY > 8);
  };

  onScroll();
  window.addEventListener("scroll", onScroll, { passive: true });

  toggle?.addEventListener("click", () => {
    const open = nav.classList.toggle("is-open");
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
    toggle.setAttribute("aria-label", open ? "Tutup menu" : "Buka menu");
  });

  nav?.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => {
      nav.classList.remove("is-open");
      toggle?.setAttribute("aria-expanded", "false");
      toggle?.setAttribute("aria-label", "Buka menu");
    });
  });

  document.querySelectorAll("[data-odo-chart]").forEach((chart) => {
    const numberEl = chart.querySelector("[data-odo-number]");
    const labelEl = chart.querySelector("[data-odo-label]");
    const noteEl = chart.querySelector("[data-odo-note]");

    chart.querySelectorAll("[data-odo-focus]").forEach((button) => {
      button.addEventListener("click", () => {
        const number = button.dataset.odoFocus || "";
        chart.querySelectorAll("[data-odo-tooth]").forEach((tooth) => {
          tooth.classList.toggle("is-selected", tooth.getAttribute("data-odo-tooth") === number);
        });
        chart.querySelectorAll("[data-odo-focus]").forEach((item) => {
          item.classList.toggle("is-active", item === button);
        });
        if (numberEl) numberEl.textContent = number;
        if (labelEl) labelEl.textContent = button.dataset.odoLabel || "Sehat";
        if (noteEl) noteEl.textContent = button.dataset.odoNote || "Tidak ada temuan.";
      });
    });
  });

  document.querySelectorAll("[data-track]").forEach((el) => {
    el.addEventListener("click", () => track(el.getAttribute("data-track")));
  });

  document.querySelectorAll("[data-track-submit]").forEach((form) => {
    form.addEventListener("submit", () => track(form.getAttribute("data-track-submit")));
  });

  document.querySelectorAll("[data-faq] details").forEach((item) => {
    item.addEventListener("toggle", () => {
      if (item.open) {
        track("faq_opened", {
          question: item.querySelector("summary")?.textContent?.trim() || "",
        });
      }
    });
  });

  const pricing = document.querySelector("[data-track-view='pricing_viewed']");
  if (pricing && "IntersectionObserver" in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          track("pricing_viewed");
          observer.disconnect();
        }
      });
    }, { threshold: 0.35 });
    observer.observe(pricing);
  }

  // In-app activation events (trial_started, clinic_created, first_patient_created,
  // first_visit_created, first_odontogram_created, first_billing_created) belong in
  // the authenticated app, not this public landing.
})();
