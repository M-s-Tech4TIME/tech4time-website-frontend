/* ==========================================================================
   Tech4TIME — theme-init.js
   Everything that has to be decided BEFORE first paint: the visitor's saved
   colour-mode choice, and whether the scroll reveal is armed.

   This is the one script loaded synchronously in <head> (no defer/async).
   It has to run before the browser paints, or the page renders in the default
   mode for a frame and then flips — the "flash of wrong theme".

   The project forbids inline <script> so a strict Content-Security-Policy can
   be applied. An external, render-blocking file achieves the same result: it is
   a few hundred bytes from the same origin, already in the HTTP cache after the
   first page, and needs no 'unsafe-inline' in the CSP.

   Deliberately minimal: it only applies an EXPLICIT stored choice. With nothing
   stored, no data-theme attribute is set and the prefers-color-scheme block in
   theme.css decides — which keeps OS-preference support working with
   JavaScript disabled.

   It also stamps the rendering ENGINE, for the same pre-paint reason. The
   hero circuit's grid sizes its corner clusters in container query units,
   which Blink and Gecko resolve and WebKit does not — every iOS browser is
   WebKit by Apple mandate, so is desktop Safari, and all of them drew four
   oversized corner fans with the band squeezed out. layout.css carries a
   WebKit-only override gated on this attribute, with fully definite sizing
   that leaves no column width for any engine to guess at.

   Engine, never browser or version: "Chrome 153 on iOS" IS WebKit, and a
   version test would need maintaining against every release. The test below
   names the WebKit carriers — iOS devices of any browser, and Safari without
   Chromium anywhere in the string — and everything else keeps the shared
   path, which is measured pixel-identical in Firefox and Chrome.
   ========================================================================== */

(function () {
  "use strict";

  var root = document.documentElement;

  try {
    var engineUA = window.navigator ? window.navigator.userAgent || "" : "";
    var isIOS = /iPhone|iPad|iPod|CriOS|FxiOS|EdgiOS/.test(engineUA);
    var isDesktopSafari = /Safari\//.test(engineUA) &&
      !/(Chrome|Chromium|Android)/.test(engineUA);
    if (isIOS || isDesktopSafari) {
      /* Before first paint, like the theme: a late stamp would flash the
         broken grid for a frame on exactly the devices being fixed. */
      root.setAttribute("data-engine", "webkit");
    }
  } catch (error) {
    /* Reading the UA string cannot usefully fail, and a missed stamp only
       means the shared path — the banner is decoration either way. */
  }

  var STORAGE_KEY = "tech4time-theme";

  try {
    var stored = window.localStorage.getItem(STORAGE_KEY);
    if (stored === "light" || stored === "dark") {
      document.documentElement.setAttribute("data-theme", stored);
    }
  } catch (error) {
    /* localStorage can throw in private mode or when storage is blocked.
       The OS preference remains the fallback, so there is nothing to do. */
  }

  /* ------------------------------------------------------------------------
     Arm the scroll reveal.

     animations.js is deferred, so it runs only once the document has been
     parsed — and on a long page over a slow connection the browser may well
     have painted the top of it by then. If the hidden state were applied from
     there, content would appear, disappear, and fade back in. Arming it here,
     before the first frame, means an element is either hidden from the start
     or never hidden at all.

     Only armed when the reveal can actually happen. The visitor who asked for
     less motion, and the browser with no IntersectionObserver, both get the
     page with nothing hidden — the reveal is decoration, and decoration must
     never be the reason something cannot be read.
     ------------------------------------------------------------------------ */
  var wantsMotion = !(
    window.matchMedia &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches
  );

  if (wantsMotion && "IntersectionObserver" in window) {
    root.classList.add("js-reveal");

    /* The safety net for the case this file cannot see: animations.js failing
       to arrive at all — a dropped request, a proxy mangling it, a parse error.
       Without this, hiding content here would hide it permanently. It marks the
       root when it starts; if the load event arrives and no such mark exists,
       the hidden state is lifted and the page is simply static. */
    window.addEventListener("load", function () {
      if (!root.hasAttribute("data-reveal-ready")) {
        root.classList.remove("js-reveal");
      }
    });
  }
})();
