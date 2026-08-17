/* ============================================================
   Tech4TIME — Main Script (vanilla JS, no dependencies)
   ============================================================ */
(function () {
  "use strict";

  /* ---------- 1. Sticky header state ---------- */
  var header = document.querySelector(".site-header");

  function onScroll() {
    if (!header) return;
    header.classList.toggle("is-scrolled", window.scrollY > 40);
  }
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  /* ---------- 2. Mobile navigation ---------- */
  var navToggle = document.querySelector(".nav-toggle");
  var siteNav = document.querySelector(".site-nav");

  if (navToggle && siteNav) {
    navToggle.addEventListener("click", function () {
      var isOpen = siteNav.classList.toggle("is-open");
      navToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
    });

    siteNav.addEventListener("click", function (e) {
      if (e.target.closest("a")) {
        siteNav.classList.remove("is-open");
        navToggle.setAttribute("aria-expanded", "false");
      }
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") {
        siteNav.classList.remove("is-open");
        navToggle.setAttribute("aria-expanded", "false");
      }
    });
  }

  /* ---------- 3. Scroll reveal ---------- */
  var revealEls = document.querySelectorAll("[data-reveal]");

  if ("IntersectionObserver" in window) {
    var revealObserver = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-visible");
            revealObserver.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.12, rootMargin: "0px 0px -40px 0px" }
    );
    revealEls.forEach(function (el) {
      revealObserver.observe(el);
    });
  } else {
    revealEls.forEach(function (el) {
      el.classList.add("is-visible");
    });
  }

  /* ---------- 4. Animated counters ---------- */
  var counters = document.querySelectorAll("[data-count]");

  if (counters.length) {
    function animateCounter(el) {
      var max = parseFloat(el.getAttribute("data-count"));
      var duration = 2000;
      var start = null;

      function step(ts) {
        if (!start) start = ts;
        var progress = Math.min((ts - start) / duration, 1);
        var eased = 1 - Math.pow(1 - progress, 3);
        var value = Math.round(max * eased);
        var suffix = el.getAttribute("data-suffix") || "";
        el.textContent = value + suffix;
        if (progress < 1) {
          window.requestAnimationFrame(step);
        }
      }
      window.requestAnimationFrame(step);
    }

    var counterObserver = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            animateCounter(entry.target);
            counterObserver.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.5 }
    );
    counters.forEach(function (el) {
      counterObserver.observe(el);
    });
  }

  /* ---------- 5. Accordion (branding page) ---------- */
  document.querySelectorAll(".accordion__item").forEach(function (item) {
    var headerEl = item.querySelector(".accordion__header");
    if (!headerEl) return;
    headerEl.addEventListener("click", function () {
      var isOpen = item.classList.toggle("is-open");
      headerEl.setAttribute("aria-expanded", isOpen ? "true" : "false");
    });
  });

  /* ------------------------------------------------------------
     6. Contact forms — submits through /api.php to the SiteJet
        form backend. The endpoint, payload field names and
        captcha mechanism are preserved exactly as on the
        original site; do not alter them.
     ------------------------------------------------------------ */
  function postFormData(url, data) {
    return fetch(url, {
      method: "POST",
      body: data,
      credentials: "same-origin",
    });
  }

  function initCaptcha(form) {
    var formId = form.getAttribute("data-form-id");
    var img = form.querySelector(".captcha__img");
    var hashInput = form.querySelector(".captcha__hash");
    var reloadBtn = form.querySelector(".captcha__reload");

    function loadCaptcha() {
      if (!img || !hashInput) return;
      var body = new FormData();
      body.append("id", formId);

      postFormData("/api.php/form_container/captcha", body)
        .then(function (res) {
          if (!res.ok) throw new Error("captcha request failed");
          return res.json();
        })
        .then(function (data) {
          if (data && data.image && data.hash) {
            img.src = "data:image/png;base64," + data.image;
            hashInput.value = data.hash;
          }
        })
        .catch(function () {
          img.alt = "Captcha could not be loaded";
        });
    }

    if (reloadBtn) {
      reloadBtn.addEventListener("click", function (e) {
        e.preventDefault();
        loadCaptcha();
      });
    }
    loadCaptcha();
  }

  function initForms() {
    document.querySelectorAll("form[data-form-id]").forEach(function (form) {
      var formId = form.getAttribute("data-form-id");
      var statusEl = form.querySelector(".form__status");
      var formLoadedAt = Date.now();

      initCaptcha(form);

      form.addEventListener("submit", function (e) {
        e.preventDefault();
        if (form.getAttribute("aria-busy") === "true") return;

        var submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) submitBtn.setAttribute("disabled", "disabled");

        var data = new FormData(form);
        data.append("id", formId);
        data.append("tac", Math.floor((Date.now() - formLoadedAt) / 1000));
        data.append("news", "1");
        data.append("tos", "1");

        postFormData("/api.php/form_container/submit", data)
          .then(function (res) {
            return res.text();
          })
          .then(function (html) {
            if (statusEl) {
              statusEl.classList.remove("is-visible");
            }

            var isSuccess = /wv-success|wv-message/i.test(html) && !/wrong security|e-mail|captcha/i.test(html);

            if (statusEl) {
              statusEl.className =
                "form__" + (isSuccess ? "success" : "error") + " is-visible";
              var text = isSuccess
                ? extractMessage(html) || "Your message has been sent. Thank you!"
                : extractMessage(html) || "Something went wrong. Please try again.";
              statusEl.textContent = text;
            }

            if (isSuccess) {
              form.reset();
              initCaptcha(form);
            }
          })
          .catch(function () {
            if (statusEl) {
              statusEl.className = "form__error is-visible";
              statusEl.textContent =
                "Could not submit the form. Please check your connection and try again.";
            }
          })
          .finally(function () {
            if (submitBtn) submitBtn.removeAttribute("disabled");
          });
      });
    });
  }

  function extractMessage(html) {
    var doc = new DOMParser().parseFromString(html, "text/html");
    var msg = doc.querySelector(".wv-message, .wv-success");
    if (msg) return msg.textContent.trim();
    var text = doc.body ? doc.body.textContent.trim() : "";
    return text || "";
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initForms);
  } else {
    initForms();
  }

  /* ---------- 7. Footer year ---------- */
  var yearEl = document.querySelector("[data-year]");
  if (yearEl) {
    yearEl.textContent = new Date().getFullYear();
  }
})();
