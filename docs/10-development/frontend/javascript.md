# JavaScript

**Applies to:** frontend

Vanilla ES5-compatible JavaScript in IIFEs. No framework, no bundler, no modules, no transpiler.
Eleven files, each doing one thing.

---

## The rule that governs all of it

**Every page must work with JavaScript off.**

Forms post natively. Content is in the markup. Navigation works. Nothing is hidden unless the code
has already established it can reveal it again.

JavaScript here is decoration and convenience. It is never the only route to anything, and it is
never the security boundary — `assets/js/forms.js` validates the contact form for the visitor's
benefit, and `contact-handler.php` validates it for real.

---

## The module pattern

Each file wraps itself in an IIFE and registers on `window.Tech4Time`:

```js
(function (global) {
  "use strict";

  function init() { /* … */ }

  global.Tech4Time = global.Tech4Time || {};
  global.Tech4Time.nav = { init: init, close: close };
})(window);
```

`main.js` loads last, walks a list of module names, and calls each `init()` **inside a try/catch**:

```js
var MODULES = ["theme", "nav", "animations", "forms", "dashboard",
               "techSphere", "slider", "terminal"];
```

Two consequences worth knowing:

- **A page that does not ship a module simply has no entry for it.** Nothing breaks; the loop skips
  what is not there. Pages link only the scripts they need.
- **A module that throws is contained.** One broken feature cannot take the rest of the page's
  behaviour down with it.

To add a module: write the file, register it on `window.Tech4Time`, add its name to `MODULES`, and
link it in the pages that need it (via `tools/templates/` if that is every page).

---

## Loading order

```html
<head>
  <script src="/assets/js/theme-init.js"></script>   <!-- SYNCHRONOUS. The only one. -->
</head>
<body>
  …page…
  <script defer src="/assets/js/nav.js"></script>
  <script defer src="/assets/js/animations.js"></script>
  …
  <script defer src="/assets/js/main.js"></script>   <!-- last -->
</body>
```

**`theme-init.js` is the only synchronous script**, and it earns that because it carries two
decisions that must be made before the first frame is painted:

1. which theme to paint — otherwise a dark-mode visitor sees a white flash
2. whether the scroll reveal is armed — otherwise content is hidden by CSS that may never be
   undone

Everything else is deferred, at the end of `<body>`.

---

## The files

| File | Does |
|---|---|
| `theme-init.js` | paints the theme before first paint; arms the reveal; registers the watchdog |
| `theme-toggle.js` | the light/dark control |
| `nav.js` | navigation, the mobile menu, focus management |
| `animations.js` | scroll reveal, counting figures, client logo entrances |
| `slider.js` | the slideshows on About and Company Profile |
| `terminal.js` | the typed terminal session on the homepage |
| `tech-sphere.js` | the draggable technology sphere on Company Profile |
| `neural.js` | the drifting neural mesh behind the homepage hero |
| `circuit.js` | the charges running through the title band's circuitry, on the fourteen pages that have one. Reads its geometry out of the SVG already in the page and paints on one canvas, because the same thing in CSS cost a CPU core — [motion.md](motion.md#the-mistake-this-band-is-shaped-around) |
| `forms.js` | contact form convenience validation |
| `dashboard.js` | the tabbed panels on the service detail pages |
| `main.js` | bootstrap; runs each module's `init()` |

**None of the admin's scripts are here.** They moved with the editor:

- `tech4time-website-backend/public/assets/js/admin-init.js` — its pre-paint work
- `tech4time-website-backend/public/assets/js/admin-nav.js` — its icon rail
- `tech4time-website-backend/public/assets/js/editor.js` — repeatable rows, reordering, previews

Nothing in this repository loads under `/admin/`, because there is no `/admin/` on this host.
`check_docs.py` requires those full paths: naming a file that is not here is allowed only when some
document says where it went, which is what stops a dead name from living in the prose forever.

> `dashboard.js` is named for the NextJS build's dashboards, whose Proactive/Reactive style switches
> the service pages port to static markup. It has never had anything to do with the admin.

---

## The watchdog, and why it exists

`animations.js` hides elements so it can reveal them on scroll. If that file never arrives — a
network failure, a blocked request, a syntax error — the content stays hidden forever.

So `theme-init.js`, which is synchronous and therefore always runs, registers a watchdog that lifts
the hidden state at the load event regardless. And nothing is hidden at all under
`prefers-reduced-motion`, or with scripting off.

**The principle generalises:** never hide something until you have established you can show it
again. [motion.md](motion.md)

---

## Conventions

- ES5-compatible syntax, `"use strict"`, IIFE per file.
- No inline handlers — `onclick=` is forbidden by the CSP. Attach listeners in the module.
- No inline `<script>` blocks, for the same reason.
- Query the DOM once and keep the reference; these pages are not re-rendered.
- Respect `prefers-reduced-motion` in anything that moves.
- Keyboard parity: anything a pointer can do, a keyboard must be able to do.

---

## Checks

```bash
python3 tools/test_nav.py       # navigation, both widths, keyboard included
python3 tools/test_theme.py     # the switch, with a real OS preference
python3 tools/test_motion.py    # nothing is left hidden
python3 tools/check_hover.py    # every control visibly responds
```
