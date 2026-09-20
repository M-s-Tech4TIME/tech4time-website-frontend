# CSS

**Applies to:** frontend

Plain CSS3. No preprocessor, no PostCSS, no utility framework, no build step — the files you edit
are the files the browser loads.

---

## The cascade order, which is fixed

```
base.css        reset, self-hosted fonts, non-colour tokens, the fluid type scale
theme.css       every colour token, and the light/dark switch
layout.css      page scaffolding — containers, grids, sections
components.css  buttons, cards, forms, navigation, the shared furniture
animations.css  keyframes and reveal states
pages/<name>.css   optional, one page only
sitemap.css     the browser-facing view of the sitemap, and nothing else
```

There is no `admin.css` here. The editor's stylesheet moved with the editor —
`tech4time-website-backend/public/assets/css/admin.css`.

`sitemap.css` is the one file no page links: its only consumer is
`assets/xsl/sitemap.xsl`, which needs a stylesheet of its own because the CSP is `style-src 'self'`
and an XSLT cannot carry a `<style>` block.

Every page links them in that order. Later files depend on earlier ones, so the order is not
negotiable: `theme.css` defines the tokens `components.css` consumes.

---

## Colour lives in exactly one file

`theme.css` holds every colour as a custom property on `:root`. **Never write a hex value anywhere
else.**

```css
:root {
  --bg-base: #fafafa;
  --bg-surface: #f1f1f2;
  --bg-elevated: #ffffff;
  --text-primary: #111113;
  --text-secondary: #4a4a4e;
  --text-muted: #6a6a6e;
  --border-subtle: #e1e1e3;
  /* … */
}
```

The palette is pure monochrome with a metallic silver accent taken from the logo's clock face.

### How the theme switches

Three layers, in precedence order:

```css
:root { … }                              /* light — the default */

@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]) { … }  /* the OS preference */
}

:root[data-theme="dark"] { … }           /* an explicit choice wins, both ways */
```

1. `data-theme="light"` or `"dark"` on `<html>` is an explicit choice and always wins.
2. Otherwise `prefers-color-scheme` applies — **so the OS preference is honoured with JavaScript
   off**, which is the reason the media query exists rather than leaving it all to the toggle.
3. `assets/js/theme-init.js` stamps the attribute before the first paint, so there is no flash.

> **Adding a colour:** define it in all three blocks or none. A token defined only in the light
> block silently keeps its light value in dark mode, which is exactly the bug
> `check_dark_mode.py` exists to catch.

### Contrast is enforced

```bash
python3 tools/check_contrast.py
```

Every text/background pair must meet WCAG AA in both modes, and component boundaries the 3:1 bar.
The header of `theme.css` records which values were adjusted to pass and what they were before —
keep that going when you change one.

---

## Sizing

Mobile-first, `min-width` queries. The ladder is documented at the top of `base.css`:

| | |
|---|---|
| 480px | large phones |
| 768px | tablets, portrait |
| 1024px | tablets landscape / small laptops |
| 1280px | laptops |
| 1440px | desktop |
| 1920px | large and ultra-wide |

**Layout is fluid between these**, via `clamp()` and auto-fit grids, rather than snapping at each
step. Reach for a breakpoint only when something genuinely has to rearrange — not to resize it.

```css
/* preferred */
font-size: clamp(1.5rem, 1rem + 2vw, 2.5rem);
grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));

/* only when the arrangement itself must change */
@media (min-width: 48em) { … }
```

---

## Naming

BEM: `block__element--modifier`.

```css
.card { }
.card__title { }
.card__title--large { }
```

Component styles go in `components.css`. If it is used on one page only, `pages/<name>.css`.

---

## The rules that are not preferences

**No inline styles.** The CSP is `style-src 'self'` — a `style="…"` attribute or a `<style>` block
will be refused by the browser. This is deliberate: an inline style is what an injected payload
looks like, and forbidding the whole category means the browser rejects it without having to tell
the two apart.

**No CDN, no `@import` from another origin.** Fonts are self-hosted in `assets/fonts/`.

**No hex outside `theme.css`.**

**A size the browser has not worked out yet is not a size.** Four shipped faults have now come
from asking for one, and they all look the same afterwards:

- `max-height: 100%` on `.dock__nav`, against a `.dock__panel` that is absolutely positioned with no
  `top` and no `height`. A percentage resolved against a content-sized parent is **not a length**, so
  it resolved to nothing: the menu never became a scroll container, and in landscape about a hundred
  pixels of it could not be reached. The fix is a flex column, which gives the child a real height to
  bound against.
- `aspect-ratio` on `.accreditation__plate` and its logo, defeated by a grid item's **automatic
  minimum size** — which is its content, so the box refused to be smaller than the picture inside it.
  The fix is `min-height: 0` / `min-width: 0`.
- A cluster's width derived from `aspect-ratio` x its row height, in an `auto` grid column. **A grid
  sizes its columns before its rows**, so the row was not known when the column asked. Firefox and
  Chrome come back to it; Safari does not, and an iPhone drew four oversized corner fans with the
  band squeezed out of the middle. The fix is `container-type: size` on `.hero-circuit` and a width
  stated in `cqh`, so nothing is derived from a number still being decided.
- A band's height as `height: 100%` on a grid item. That percentage resolves against the grid
  **area** in Blink but the whole grid **container** in WebKit: an iPhone measured the band's box
  at 156x239 in a 414x239 hero, the row being 103, so `slice` magnified a 75-unit sliver 2.1x and
  the bands rendered as sparse verticals with dots where Android renders the dense run. `height:
  auto` is not the fix either — an `<svg>` is a replaced element, so `auto` falls back to width x
  intrinsic ratio and the band collapses to a 1px rule (`test_motion` caught it). The fix is the
  same outright `(100cqh − gap) / 2` the clusters state, which equals the row to the pixel on
  Blink and Gecko and replaces the 239px box with the 103px row on WebKit.

The pattern to watch for: **a percentage, an `aspect-ratio` or an `auto` track whose answer depends
on a box further along in the same layout pass.** It usually works in the browser you tested, which
is what makes it expensive.

**Anything an operator can upload gets a bound.** The header sizes the logo by its HEIGHT, so the
width it occupies is height x aspect ratio, and `.site-header__brand` is `flex-shrink: 0` — nothing
downstream can take that width back. That was safe while the mark was three committed files at
2.81:1 and stopped being safe when it became something somebody uploads: measured at 320px, 8:1
held and 10:1 pushed the page 56px sideways. Both lockups now carry a `max-width` with
`object-fit: contain`, which bounds the width without squashing the artwork. The third pass of
`check_responsive.py` is what keeps it bounded.

---

## Cache busting

Filenames are not content-hashed — there is no build step to hash them, and `.htaccess` caches CSS
for a year. A changed `base.css` will not reach a returning visitor on its own.

When you change one, either append a version to the `<link>` (`base.css?v=2`) or lower the
`max-age` for that type. [routine-deploys.md](../../20-deployment/routine-deploys.md)

---

## Checks

```bash
python3 tools/check_contrast.py    # WCAG AA, both modes
python3 tools/check_dark_mode.py   # every page as painted, both themes
python3 tools/check_hover.py       # every control visibly responds
```
