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
| Pages with a title band | circuitry animating along all four edges | the same circuit, still |
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
technology name appears.

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

## Writing new motion

Ask three questions before you start:

1. **What does this look like with JavaScript off?** If the answer is "nothing", stop.
2. **What does it do under `prefers-reduced-motion`?** It should not run.
3. **If the script fails, is anything unreachable?** If yes, it must not hide anything.

Then:

- Put keyframes that more than one page uses in `assets/css/animations.css`.
  Keyframes belonging to one component live with that component — the circuitry's
  are in `assets/css/layout.css` and `assets/css/components.css`, the hero mesh's
  in `assets/css/pages/home.css`. A page-specific animation in the shared sheet is
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
