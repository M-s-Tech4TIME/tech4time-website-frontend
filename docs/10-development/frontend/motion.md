# Motion

**Applies to:** frontend

Everything that moves, and the single rule that governs all of it.

---

## The rule

> **Motion may decorate. It may never be the only way to reach something.**

Every animated feature has a defined state for: JavaScript off, `prefers-reduced-motion`, and the
script failing to load. That state is never "the content is missing".

| Where | What moves | With JavaScript off |
|---|---|---|
| Every section | fades up as it scrolls in | visible, in place |
| Home hero | a terminal session typed a character at a time | the whole session fades in line by line, in CSS |
| About, Company Profile | specialities and photographs as slideshows | every slide at once, in the section's grid |
| Company Profile | experience figures count up | the real figures, which are in the markup |
| Company Profile | client logos arrive a row at a time | all of them, in place |
| Company Profile | a technology sphere you can take hold of and turn | a grid of logos with alt text |
| Pages with a title band | circuitry emerging from all four corners and along the top and bottom edges, a charge running three traces in each corner and band | the same circuit, still |
| Home hero | clusters of nodes drifting across the hero, linking to whatever comes in reach | nothing — a plain hero, which is the intended appearance (reduced motion keeps the picture, held still) |

---

## The scroll reveal

The most important piece, because it is the one that can hide content.

**Markers are applied by rule, not by hand.** `tools/apply_reveals.py` walks every page and marks
targets from one structural rule — each section's header, then its content — so the behaviour is
consistent and nobody has to remember to tag a new section.

```bash
python3 tools/apply_reveals.py            # dry run: report what it would do
python3 tools/apply_reveals.py --write    # apply
python3 tools/apply_reveals.py --strip    # remove every marker
```

**The five dynamic pages are the exception, and the tool says so.** `index.php`, `pages/about/`,
`pages/careers/`, `pages/contact/` and `pages/company-profile/` build part of themselves with a
`foreach`, so the markers on a repeated row live in the template rather than in the emitted
markup. All three modes report those pages and leave them alone. Their markers are maintained by
hand, in the renderer, and the rule they follow is the same one — the section header, then its
content.

**The home page is the one at the repository root**, and that mattered: the tool hardcoded
`ROOT/"index.html"` there and stopped seeing the file the moment it became `index.php` — silently,
because a page with no markers passes every check in `test_motion.py` without any of them testing
anything. It looks for both names now. Five other tools had the same root-level blind spot and were
fixed with it; the list is in the header comment of `index.php`.

On the about page the per-paragraph markers inside a story section are put back at render time by
`about_reveal_paragraphs()`, because the prose is one rich-text field and the number of paragraphs
is a property of the content. See [libraries.md](../server-side/libraries.md#aboutphp).

### The failure that had to be designed out

`animations.js` hides elements so it can reveal them on scroll. If that file never arrives — a
network failure, a blocked request, a syntax error you introduced — **the content stays hidden
forever**.

Three defences, in order:

1. **Nothing is hidden unless the code has already established it can reveal it again.** The hiding
   is applied by script, not by a stylesheet that loads regardless.
2. **`theme-init.js` is synchronous and therefore always runs.** It registers a watchdog that lifts
   the hidden state at the load event, whatever happened to `animations.js`.
3. **Nothing is hidden at all** under `prefers-reduced-motion`, or with scripting off.

`tools/test_motion.py` is the proof: every page, scrolled end to end, with every marked element
required to finish opaque. It is the check to run after touching anything in this area.

---

## Slideshows

`assets/js/slider.js`. Auto-advancing, and therefore subject to WCAG 2.2.2 — moving content that
starts automatically and lasts more than five seconds must be pausable.

They stop:

- on hover
- on focus
- when the tab is in the background
- **on demand** — there is a pause control, which is the WCAG requirement
- and they never start at all under `prefers-reduced-motion`

Without JavaScript, every slide renders at once in the grid the section already had. The slideshow
is a way of *saving space*, not a way of *storing content*.

---

## The technology sphere

`assets/js/tech-sphere.js`. Company Profile. Draggable in any direction, with momentum.

Without JavaScript it is a plain grid of logos with alt text. It must never be the only place a
technology name appears. Below 768px there is no sphere at any time — the grid is what everyone
gets, capped at `COMPANY_TECHNOLOGY_WALL` with the rest behind a `<details>`, and the script moves
that tail onto the surface when it takes over. So the grid is not a fallback nobody sees: it is what
a phone shows, every time.

**It turns only while it is on screen**, and that is a correctness matter rather than a nicety. The
loop's per-frame work is two custom properties on one element, which invalidates the transform of
every logo under it — so fifty elements had their styles recalculated every frame of every second
the page was open, including while the sphere was several screens below the fold. Transform-only, so
no layout and no paint, so **every frame-rate check on this page reported a steady 60fps throughout**.
`tools/check_style_budget.py` is the one instrument that can see it: 134ms of style per second
against a 100ms ceiling, with the sphere never once in view. An `IntersectionObserver` cancels the
frame when it leaves and asks for one again when it returns; `--on` is untouched, so the arrangement
stays exactly where the reader left it.

That is the same failure mode as the hero circuit's in 2026-09, found the same way, and it is the
reason that check now watches this page at all.

---

## The terminal

`assets/js/terminal.js`. The homepage hero types a session a character at a time, with output
arriving in blocks.

Without JavaScript the whole session fades in line by line in pure CSS — the text is in the markup,
so it is readable and indexable either way.

---

## The hero mesh

`assets/js/neural.js` — the only `<canvas>` on the site. **There is no mesh in the markup**: the
module builds the whole thing, its container included, and removes it again when it should not be
there.

Three states, and the middle one is the easy thing to get wrong:

| | |
|---|---|
| scripting off | nothing at all — no canvas, no container, not even an empty box |
| reduced motion | the same picture, drawn once and never again |
| otherwise | the picture, moving |

**Reduced motion asks for stillness, not for blankness.** The mesh is drawn exactly as it would be
on any other frame and then left alone — no loop, no timer, nothing scheduled — and repainted only
when the geometry or the palette actually changes under it. There is no separate static version to
build or maintain; it is the same code drawing one frame.

**Scripting off is different, and deliberately so.** There the hero is plain, and that is the
intended appearance rather than a degraded one. This is the one place the rule at the top of this
page is tested: "motion may decorate, it may never be the only way to reach something" permits
exactly this, because the mesh carries no words, no links and no meaning. A feature that vanishes
entirely is only acceptable when its absence costs a visitor nothing — apply that test before
copying this pattern anywhere else.

Switching reduced motion on and off while the page is open works, and rebuilds rather than mutates:
whether the mesh moves is fixed per instance, so there is no state that can be half-way between the
two. Each instance undoes every observer and media listener it added, or the discarded ones would
go on repainting a canvas no longer in the page.

**Why a canvas.** A CSS animation interpolates fixed properties on fixed elements: the browser has
to know at parse time that a line runs from A to B. A link between two wandering nodes has no such
endpoints — where it lands depends on where both happen to be this instant. Canvas has no elements
at all; every frame is cleared and redrawn from current positions, so a link is
`if (distance(a, b) < reach) draw it`, recomputed sixty times a second. Links appear as nodes drift
together and vanish as they part, and that is the whole effect. SVG animated in CSS cannot do it:
a link there must live in the same `<g>` as both of its nodes, so its shapes are fixed.

**What the canvas has to do by hand**, because it cannot inherit anything:

- **Colour.** Read from the `--neural-*` custom properties declared on `.hero-neural` in
  `assets/css/pages/home.css`, and re-read on every theme change — a `MutationObserver` on `data-theme` and a `prefers-color-scheme` listener. Get
  this wrong and it is invisible: **no check in this repository can see canvas pixels.**
- **Reduced motion.** `base.css` stops CSS animation globally; it does nothing to
  `requestAnimationFrame`. The module watches the media query live and rebuilds itself in still
  mode when it turns on, which is the only thing that can stop the loop.
- **Stopping.** The loop halts when the hero scrolls out of view and when the tab is hidden. A
  continuous loop on the site's most visited page has to earn its frames.

**Why there are three fields.** The hero's aspect ratio runs from about 2.2:1 on a desktop to
0.29:1 on a phone. One sliced `viewBox` across that range either crops to a fifth of its width or
scales the nodes into blobs, so there is a landscape, an intermediate and a portrait field, and
exactly one is displayed. The two that are not are `display: none`, which means they generate no
boxes and **run none of their animations**.

Checked by `hero_mesh()` and `hero_frame_budget()` in `tools/test_motion.py` — the only frame
budget on the home page, and the only test anywhere that asserts a canvas has *stopped*. The
reduced-motion pass asserts the mesh is there, is painted, and does **not** move — three separate
claims, because "blank" and "held still" both look like "not moving" from a distance. The
scripting-off pass asserts the opposite of everything else in this suite: that nothing of the mesh
is in the page at all.

---

## The circuit around the page title

`tools/templates/hero-circuit.html` + `assets/css/layout.css`. Every interior page opens with a
title band, and the band is framed by circuitry. Six layers: a band across the top and the bottom,
and a cluster emerging from each of the four corners.

**It is the company's own artwork, extracted rather than interpreted.** It used to be drawn from a
description of the printed material — diagonal chevrons in the bands, typed by hand as path
coordinates. The artwork itself is now in `references/`, and
`tools/build_hero_circuit.py` reads it: 18 traces, 50 pads and 7 vias in a cluster, 26 / 77 / 11 in
a band half, each trace clipped against its own clip rectangle, because the cluster's diagonal edge
**is** that clip and not drawn geometry. Nothing in the template is typed; `--check` refuses a
coordinate edited by hand.

**The composition is the artwork's too, and it is measured.** Rendered at its own 2.954 : 1 and
scanned for ink, the artwork puts a cluster at **11.15%** of the width, a gap at **5.60%**, the band
at **63.30%**, another gap at **4.80%**, and the band's height at **29.25%**. Note that its own two
horizontal gaps are not equal to each other.

### Every channel is the same width, and a grid is what makes that true

There are four channels: cluster→band on the left, band→cluster on the right, top band→bottom band,
and top cluster→bottom cluster down each edge. They used to be four numbers arrived at four
different ways — the horizontal pair from a share of the **width**, the vertical pair from the
band's own height — so they agreed only by accident at one aspect ratio. Measured before this:
**40%** of the width clear on a phone, and **5.9%** the moment a band appeared at 768px. A sevenfold
step at one pixel of viewport, and the reason a person said they could not see the gap anywhere.

`.hero-circuit` is a **grid** now: cluster / band / cluster across, two rows down, one `gap`.

- **The gap is stated once.** All four are that one value, equal *by construction* rather than by
  four numbers kept in step by hand.
- **The rows are `1fr`**, so a band and a cluster are each `(banner − gap) / 2` tall. That is what
  makes the vertical channels match the horizontal ones, and `hero_circuit()` asserts the two
  heights are equal so a failure says so where it happens rather than three checks away.
- **`--hc-gap` must be a LENGTH, never a percentage.** `row-gap: %` resolves against the container's
  **height** and `column-gap: %` against its **width** — a percentage gap is unequal in pixels by
  definition, which is the exact fault this exists to prevent. Falsified: setting it to `8%` fails
  the gap check at every width.

**What it costs, and where that is paid.** A cluster sized by the banner's *height* takes its share
of the *width* from the banner's shape rather than the artwork's: 19% of a 440px banner, 11.4% at
1024px — the artwork's own proportion — but **7.0% at 1440 and 2.5% at 3840**. On a wide screen the
corners read as specks and the band carries the composition alone.

**So above 1280px the cluster is sized by the viewport instead**, and is allowed to reach past its
row. Three of the four channels are untouched — A and B are still the grid's column gap, and C is
still the space between two bands that still fill their rows. Only **D**, down each edge, closes up:
116/116/101/**53** at 1440 and 128/128/132/**22** at 1920. That is a deliberate trade, and it is the
artwork's own shape, where the clusters very nearly meet down the sides.

1280px and not 1024px because at 1024 a row-sized cluster is *already* 11.4%; starting lower would
make it smaller, which is the opposite of the point.

**The ceiling is the banner's height, not taste.** A cluster is very nearly square and the banner is
about 332px tall at every desktop width, so two stacked down one edge cannot exceed that — above
about 154px they meet. `hero_circuit()` asserts both ends: that the cluster reaches past its row, and
that twice its height still fits the banner. Falsified both ways.

**The band's column has no floor**, deliberately. A floor there is paid for by the clusters — the
column takes their width, their height stays the row's, and `meet` then letterboxes the drawing in a
box of the wrong ratio, which opens the vertical channel past the gap. Measured at 390×300 it went
to 100px against a 32px gap. So on the narrowest phones the band is a narrow window onto the artwork
rather than a wide one, at true scale, and every channel stays equal.

**A mask that was built and then removed.** The cluster's diagonal fade leaves its ink at about a
third of full where the band begins, so a second mask was intersected with it to end the ink
cleanly. Measured, it widened the horizontal channels by **2–4px out of 115** and removing it failed
no check at all — the `<svg>` already clips at the box, so the grid alone delivers the channel. It
is recorded here because the reasoning sounded right and the measurement did not support it.

### The band tiles; it is not stretched, and it used to be

The bands were `preserveAspectRatio="none"` — one copy of a 1440 × 114 drawing stretched across
whatever width the band got. The reasoning was that a band runs a fixed height across a box whose
width is the screen's, and stretching a horizontal run only makes it a longer run. True of a run and
false of everything around it: pads become ovals, vias ellipses, every vertical trace thins and
every horizontal one thickens. Measured, the two scales agreed at **exactly one viewport**:

| viewport | horizontal | vertical | |
|---|---|---|---|
| 768 | 0.34 | 0.90 | 2.6× squashed |
| 1440 | 0.64 | 0.90 | 1.4× squashed |
| **1920** | 0.86 | 0.90 | **1.05× — the width it was composed for** |
| 3840 | 2.02 | 0.90 | 2.2× stretched |
| 15360 (4K at 25% zoom) | 9.07 | 0.90 | **10× stretched** |

Reported from a zoomed-out browser as stretched and old. Nothing had ever measured it, which is how
a drawing correct at one width shipped to every width.

**Now `xMidYMid slice` over a viewBox fifteen tiles wide.** `slice` scales **uniformly** and crops,
and with a viewBox that wide the band's **height** decides the scale and its width never does — one
scale and one trace pitch at every viewport and every zoom. `BAND_TILES` in
`tools/build_hero_circuit.py` and `circuit.js` must agree.

- **The tile count is a margin, not a correctness condition.** Fifteen covers a 5K display at
  Chrome's minimum 25% zoom. Past that the width governs and the drawing *magnifies* — `slice` is
  uniform by definition, so it cannot distort again.
- **Nothing repeats on an ordinary screen.** Measured, the band fits inside *one* tile at every
  width up to 1920px; it first repeats near 2048px, ×2.24 at 3840.
- **Odd on purpose**, so the viewBox's centre is a mirror axis and the banner's middle is still where
  the two halves meet.
- **Charges and nodes are not tiled** — ninety animated elements per band under a `<use>`d group is
  the shape that cost a CPU core on 2026-09-03. They sit on the centre tile, so with scripting off on
  a wide screen the charges run in the middle of the band only.
- **`circuit.js` clips the canvas to each layer's box.** The `<svg>` crops what `slice` deliberately
  overflows and the canvas does not, so without it charges paint straight through the channels: a
  115px channel measured 40px of clear pixels, and at 1920px the banner scanned as one unbroken
  mass. It was not a problem while the band was `"none"`, because the drawing then exactly filled its
  box.
- **The band's three pen tiers are gone**, and so is the cluster's ladder. Both drawings are sized by
  the banner's height now, so their scales vary about 1.5× rather than the 3.5× that tracked viewport
  width — one pen each covers it, and tiers keyed to width would be measuring the wrong variable.

**Pure inline SVG animated in CSS.** The charges move to a canvas when there is scripting; without
it the SVG's own run, so there is no page that needs JavaScript for this, and the reduced-motion
block in `base.css` freezes either.

**One set of geometry.** Everything is declared once, in the first layer's `<defs>`, and the other
five reference it and are mirrored in CSS. That is not tidiness: a duplicate `id` is a hard
failure in `tools/audit_pages.py`, so four corners cannot each carry their own copy. Change the
**artwork**, then `python3 tools/build_hero_circuit.py`, then `python3 tools/propagate_shared.py` —
never the template by hand, and never a page.

**The two bands are one current.** The flow runs left to right along the top and right to left
along the bottom, at a single shared duration, so the two edges read as one circuit going round the
band rather than two animations that happen to be near each other. Everywhere else here a shared
duration is the fault being avoided; along the bands it is the requirement. Two things have to
cancel for that to hold: the right half of each band is the left half mirrored, so a charge running
forward there travels the wrong way and is reversed by `--mirrored`; and the bottom band is the top
one turned over, which inverts the whole flow again. `hero_circuit()` measures the direction *after*
cancelling both, so it checks what a visitor sees rather than the sign in the stylesheet.

**The circuits branch out from their own edge, once.** Every corner trace starts on one of the two
edges meeting at that corner, and every band trace starts at the outer edge — so a clip expanding
from that same origin uncovers each trace from its root outward. Six animated elements instead of
the two hundred it would take to draw every trace on individually. The four corners are given no
stagger: they emerge together.

That is a property of the geometry, not a hope about it. Inkscape stores a polyline in whatever
order it was drawn and the artwork ran about two to one the wrong way — eighteen of the band's
twenty-six went from the pad back up to the edge. Invisible in the still drawing and very visible
once a charge is on it, because both the canvas and the CSS fallback take a trace's direction as
given. `build_hero_circuit.py` turns every trace outward before emitting it.

**The charges are painted on a canvas, and the SVG's own are the fallback.** `circuit.js` reads
every trace's geometry out of the SVG that is already in the page — there is no second copy of the
drawing — and paints a charge on **all 176** of them on a single `<canvas>`. Once it is measured
and drawing it adds `hero-circuit--canvas`, which switches the SVG's charges and junction dots off.
Until then, and for ever without JavaScript, the SVG's **24** CSS charges run instead. The two are
never both live, and `hero_circuit()` checks that in both directions.

**And it paints them at the weight the drawing is.** That was a flat 2.6 device pixels on every
layer at every size, which was survivable while the band was full-bleed and is not now: a band's box
runs from about 1,220px down to 345px for the same 1,440-unit viewBox, so the drawing under the
charge renders from 2.2px down to 0.9px. Measured at 768px, a charge held at 2.6 over a trace at
0.74 stopped being the lit part of a line and became the only part — a smear of moving white over a
drawing nobody could see. `circuit.js` now reads `.hero-circuit__wires`' own `stroke-width` off the
stylesheet and scales it the way the browser does, for the same reason it reads the ink there: one
source, and a stylesheet change carries across by itself.

Why the trouble: a charge in CSS is a style recalculation per animated trace per frame, and 176 of
those is a CPU core. A canvas has no style to recalculate. It costs slightly more while the band is
on screen and **less** overall, because CSS animations keep running when scrolled past and the
canvas stops — measured 187 ms/s against 126 with the band visible, and 135 against 175 once
scrolled below it.

**And it draws at the screen's resolution, which it did not used to.** `MAX_DPR` was **1**, on the
reasoning that thin strokes of one colour behind a title cannot show the difference and cost in
direct proportion. That was decided at a desk, where the choice is between one device pixel and
two. On a phone it is between one and three, and the layer it applies to is the brightest thing in
the banner — the charges, in the accent colour, moving, over traces that are SVG and therefore
always drawn at the screen's full resolution. A sharp drawing with a soft glow crawling over it was
reported from a phone as the *whole banner* looking pixelated. It was the only part of it that was.

**`check_style_budget.py` cannot see this change, and adding a flag to it would not help.** It
measures `RecalcStyleDuration + LayoutDuration`. Canvas rasterisation is neither, so it reports the
same figure whatever `MAX_DPR` is — a check that proves nothing while reporting success, which is
the trap the rest of this file is shaped around. The cost lives in **main-thread task time**, and
it has to be measured against the *same* device scale factor on both sides: forcing Chrome to 3×
scales the whole page, so comparing an old 1× run against a new 3× one measures mostly the page.

Measured on `/pages/about/`, headless Chrome, milliseconds of task time per second:

| 1440×900, bands drawn | 1× | 2× | 3× |
|---|---|---|---|
| before (`MAX_DPR = 1`) | 135 | 139 | 137 |
| after (`MAX_DPR = 3`) | 137 | **182** | **242** |

| 390×844, bands stood down | 1× | 2× | 3× |
|---|---|---|---|
| before | 201 | 195 | 200 |
| after | 197 | 208 | 221 |

The before rows are flat because the canvas was pinned regardless of the screen. Run-to-run noise
is about ±25, so read the 1× column as *unchanged* — which it is by construction:
`Math.min(devicePixelRatio, 3)` is still 1 on a non-retina screen, and such a machine pays nothing
for this at all.

**A desktop is 1× or 2×, never 3×**, so the real worst case is the 2× desktop column: 182 against
139, about +31%. The phone — the case this was reported from — is nearly free at +7%, because the
bands standing down pays for most of the extra resolution. These are software-rasterised figures
from headless Chrome; a real browser composites a canvas on the GPU, so treat them as an upper
bound rather than as what a visitor's machine does.

**The box is watched with a `ResizeObserver`, not a `resize` listener.** `measure()` computes every
trace position, every scale and the canvas backing store in one pass, so one stale read is the
whole layer wrong until something else moves it. A `resize` event on mobile can arrive *before*
layout has settled after a rotation, which is exactly when the box has changed most. A
`ResizeObserver` on `.hero-circuit` fires when that element's own box changes, after layout. It
also covers two reflows the window listener never saw: a mobile address bar collapsing, and the
webfont landing and rewrapping the title to a different number of lines. The listener remains as
the fallback.

Three things that were needed to make it pay, none of them optional:

- **The junction dots moved onto the canvas too.** Left in the SVG they were the only thing still
  animating there, which kept the whole document rendering at 60 fps whatever the canvas did.
- **30 frames a second.** A charge crossing a trace over four seconds is not made smoother by
  drawing it twice as often, and this halves the cost of the layer. It was *also* 1× device
  pixels, and that half is gone — see below.
- **It stops when the band is off screen**, and when the tab is hidden. Note that an
  `IntersectionObserver` measures against the *top-level* viewport, so inside an off-screen iframe
  it correctly stops — which reads as a blank canvas if you are testing through one.

**The fallback's four clusters share three durations**, which everywhere else on this site would be the fault
being avoided. Here it is measured: twelve distinct durations are twelve distinct computed styles,
which the browser can then share between no two elements — 55ms of style recalculation per second
against 35ms for the same twenty-four charges on three. The clusters are mirror images of each
other, so a shared phase reads as the board lighting symmetrically. Within one cluster the three
still differ, because two charges at one speed in one corner would read as a single thick line.

`hero_circuit()` checks the shape rather than the cost — that no charge wraps a group, and that
each drives exactly one trace — because the cost is not observable from a browser test.

**Neighbouring lines flow against each other.** Consecutive traces are dealt round-robin into the
six groups, so no two neighbours share one, and the odd groups carry `--back`, which reverses them.
That is what makes a cluster read as a working board rather than a fan of parallel arrows.

**The bands are the current; the corners are the board.** Each band runs its whole circuit in 4s,
every corner charge somewhere between 12s and 35s — three to nine times slower. Every path carries
`pathLength="100"`, so a duration *is* a speed here regardless of how long the path really is, and
`hero_circuit()` compares the two directly.

**Density is free; motion is not.** A static trace is rasterised once. A charge animates
`stroke-dashoffset`, which is not compositor-accelerated and repaints its path every frame, on
fourteen pages, above the fold, for as long as the tab is open.

### The mistake this band is shaped around

The charges were once carried on groups: a `<g>` wrapping a `<use>` of a *whole group* of traces,
on the reasoning that forty animated elements must be cheaper than two hundred. **That reasoning
was wrong, and it shipped.**

`stroke-dashoffset` is an *inherited* property. Animating it on a group makes the browser push the
new value down through every `<use>` shadow tree beneath it, every frame — so the "forty elements"
were really several hundred nodes of inherited-style propagation per frame. Measured with
Lighthouse on `/pages/about/`:

Read from the browser's own counter — Chrome's `RecalcStyleDuration`, on a page left alone with
nothing clicked or scrolled. This is what the page costs simply by being open:

| | `/pages/about/` | `/pages/services/` |
|---|---|---|
| before the circuit was replaced | 38 ms/s | 35 ms/s |
| **charges on groups (shipped 2026-09-03)** | **895 ms/s** | **842 ms/s** |
| 24 charges, flattened, 12 durations | 55 ms/s | — |
| 24 charges, flattened, 3 durations | 38 ms/s | 29 ms/s |
| **the same, redrawn from the artwork — 176 traces, not 216 (now)** | **33 ms/s** | **28 ms/s** |

895 ms of style recalculation per second is most of a CPU core, spent forever, on fourteen pages.
Three things are worth keeping from that table. Flattening was the larger half of the fault, not a
refinement. Sharing durations across the four clusters was worth another 20 ms/s, because distinct
computed values defeat the browser's style-sharing cache. And the cost is close to linear in the
number of charges — about 1.1 ms/s each — so raising it is a decision with a price attached.

**Masks and clip-paths were measured and are not the problem** — removing all six changed nothing
outside noise. They were the obvious suspects and they were innocent; the numbers said so before
anything was changed on their account.

**No frame-rate test in this repository noticed any of it.** `hero_frame_budget()` reported 17 ms
median throughout, because an idle desktop has the headroom to burn most of a core on style
recalculation and still hit 60 fps. The person who noticed was the user, on the live site.

That gap is now covered by **`tools/check_style_budget.py`**, which asks Chrome directly rather
than counting frames. Run it whenever you change how much of this drawing moves — and note that a
frame counter, including the ones in this file, will tell you nothing.

**The pen is widened as the drawing shrinks.** An SVG stroke scales with its drawing, and the two
drawings here shrink at very different rates. Read off a browser rather than derived on paper:

| viewport | band box | scale | cluster box | scale |
|---|---|---|---|---|
| 1920px | 1223px | 0.85 | 228 × 246 | 1.14 |
| 1440px | 914px | 0.63 | 171 × 184 | 0.86 |
| 1024px | 646px | 0.45 | 122 × 131 | 0.61 |
| 768px | 481px | 0.33 | 91 × 98 | 0.46 |
| 360px | 345px | 0.24 | 72 × 77 | 0.36 |

Left alone at one weight every line falls under a pixel below about 1024px and the drawing turns to
haze — which is not the same failure as being absent, and no check would have called it. Two tiers
in `layout.css` widen the stroke to hold the rendered weight between about one and two device pixels
throughout, measured at 0.86 to 2.04 across that table; the numbers there are that arithmetic, not
taste. The corner layer carries `aspect-ratio: 200 / 215` — the cluster's own ratio, so `meet`
leaves no margin and the scale stays exactly `width / 200`.

**A mark is not a stroke, and the pen cannot rescue it.** The artwork carries about three times the
component marks the drawing it replaced did — 77 in each band half against roughly 24. Widening a
stroke does nothing for a filled shape, so below a tablet they stop reading as components and start
reading as speckle over the traces. The narrow tier carries them lighter instead.

**And `hero_circuit()` now measures all of it**, which nothing did before. The band's share of the
banner's height and width, the cluster's share of its width, the gap between them, and the rendered
weight of both pens — read off `getBoundingClientRect()` at 1440, 768 and 390, not estimated. Every
one of those is a number in a `clamp()` that some other change could retune for some other reason,
on every interior page, with no other check saying anything. Falsified against four deliberate
breaks before it was trusted: the cluster back at `20vw`, the band back to full width, the old pen,
and a band squeezed to a third. The first draft caught three of the four — a band restored to full
width passed everything, because only the lower bound on its width was asserted. That is what the
gap check is for.

**Every animation must rest on its declared value.** `base.css` sets no `animation-fill-mode`, so
when reduced motion collapses an animation the element reverts to its *specified* value, not to
the `to` keyframe. The charges end at `stroke-dashoffset: 0`, which is also their base; the nodes
declare `opacity: 0.3` on the rule and deviate around it with `animation-direction: alternate`; and
the emerging clip declares the *revealed* state on the rule, with the keyframes running up to it —
had that been the other way round, asking for less movement would have emptied the band. A
trace animated on from nothing would freeze **invisible**, and the band would be blank for
everyone who asked for less movement. `hero_circuit()` and the reduced-motion pass in
`tools/test_motion.py` are what hold that.

**The mobile navigation panel's circuit was deliberately left in the older design.** It is not a
title band, it is only on screen while the menu is open, and `tools/test_nav.py` asserts on its
sixteen charges by name. The two are no longer a matched pair; that is a decision, not drift.

---

## Writing new motion

Ask three questions before you start:

1. **What does this look like with JavaScript off?** If the answer is "nothing", stop.
2. **What does it do under `prefers-reduced-motion`?** It should not run.
3. **If the script fails, is anything unreachable?** If yes, it must not hide anything.

Then:

- Put keyframes that more than one page uses in `assets/css/animations.css`.
  Keyframes belonging to one component live with that component — the title band's
  circuit (`hero-charge`, `hero-pulse`) is in `assets/css/layout.css`, the dock's in
  `assets/css/components.css`, the hero mesh's in `assets/css/pages/home.css`. A page-specific animation in the shared sheet is
  bytes every other page downloads and never uses.
- Put behaviour in a module registered on `window.Tech4Time`.
- Check `matchMedia("(prefers-reduced-motion: reduce)")` before starting anything.
- If it auto-advances, give it a pause control.
- Run `python3 tools/test_motion.py`.

---

## Checks

```bash
python3 tools/test_motion.py     # nothing is left hidden, on any page
python3 tools/check_hover.py     # every control visibly responds to a pointer
python3 tools/check_dark_mode.py # both themes, as painted
```
