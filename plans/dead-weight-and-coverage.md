# Dead weight and missing coverage

**Status: NOT BUILT. Parked deliberately on 2026-09-18, at the end of the closing sweep.**

**Applies to:** both — the dead stylesheets are in `tech4time-website-backend`, the rest is here.

This is a design, not documentation. Nothing below is true of the site today; `docs/` is for what
is. Everything here was **measured** during the closing sweep and the numbers are reproducible, but
no part of it has been acted on.

---

## Why this is its own phase, and not a sweep item

It was found during the sweep and deliberately left. The reasoning is the whole point of this file:

**These are the changes most likely to break the site silently.** Removing a CSS rule is not like
removing a function. A dead function fails loudly the moment something calls it; a stylesheet that
has lost a rule it needed renders *perfectly well* — just wrong. No check in either repository can
see it. `check_css.py` reads declarations, not what matched. `audit_pages.py` reads structure.
`check_responsive.py` and `check_focus.py` measure boxes and rings, not whether a border, a
background or a spacing step is the one that was intended. A wrong result and a right result are the
same shape.

So the priority for this phase, above any byte saved, is:

> **The integrity of the project is first. Nothing breaks. Not the styling, not the code, not the
> behaviour.** A change that saves 50 KB and costs one silent visual regression is a bad trade and
> is not to be made.

That is why this is parked rather than done: it needs a measured plan, an instrument that can prove
a removal changed nothing, and one change at a time — not a sweep.

---

## Build the instrument before removing anything

**This is the load-bearing recommendation of this file.** None of the removals below should happen
until there is something that can answer *"did this change what a visitor sees?"* with evidence.

Two halves, and neither is hard:

**1. A selector-coverage tool.** Render every page (and every signed-in admin screen), and for each
selector declared in the stylesheets record whether it matched anything, at each of the widths and
in each of the themes the site supports. `document.querySelectorAll(selector).length` over the
selectors parsed out of the CSS, driven by the same Firefox harness `check_responsive.py` and
`check_accreditations.py` already use.

That turns every removal from a judgement into a fact, and it is **stronger than the grep I did
during the sweep**: a grep proves a *name* appears nowhere, while this proves a *rule* matched
nothing on a real page, in a real browser, with the content actually loaded.

It has to cover: every width in `check_responsive.py`'s list, both themes, and — the part the sweep
could not do — **content states that are not the shipped seed**. A band switched on, a row hidden, a
document that has rows where the committed seed has none. See the note on the seed below.

**2. Before-and-after screenshots.** `tools/shoot_pages.py` already captures a full-document
screenshot per page through Firefox. Capture the whole site before a removal and after it, and
compare the images. Identical images are the only evidence that carries: it is a statement about
painted pixels, which is the thing being risked.

Pillow is already a CI dependency for the `firefox` job, so the comparison costs nothing new.

**Then, and only then:** remove **one family per commit**, re-run both instruments, and require the
screenshots to be byte-identical. A single commit per family also means a single commit to revert.

---

## The findings, as measured

### 1. Frontend dead CSS — 16 classes, ~5.3 KB raw

| | |
|---|---|
| `animations.css` | `animate-fade-in`, `animate-fade-in-up`, `is-spinning`, `shine` |
| `base.css` | `container--wide` (and `--container-wide`, its only reader) |
| `components.css` | `icon--lg`, `icon--xl`, `stat__value`, `logo-card`, `rte__surface` |
| `layout.css` | `grid--auto`, `grid--auto-sm`, `grid--auto-lg`, `grid--split`, `section--ruled`, `hero-circuit__charge--p4` |

**What was verified.** Unreachable from the rendered markup of all seventeen pages, from every file
in `assets/js/`, from `lib/` and `pages/`, from `tools/templates/`, and from `assets/xsl/sitemap.xsl`
— and **not reachable from editor content either**: `RT_ALLOWED_CLASSES` in `lib/html.php` is a
four-item allow-list (`ta-left`, `ta-center`, `ta-right`, `ta-justify`), so no class an editor types
survives `rt_sanitise_html()`.

**Three traps, and they are the reason this is parked.**

- **`.shine` is deliberate.** Its own comment in `animations.css` says the class *"is still here for
  surfaces that are not buttons"* — a documented, intentional utility kept after primary buttons
  stopped opting in. Removing it would override a recorded decision, not delete a leftover.
  `.logo-card`'s comment likewise begins *"Currently unused"*. **A comment that says a rule is kept
  on purpose outranks a grep that says nothing references it.**
- **`base.css` is one of the eight byte-identical shared files.** Editing it means editing the
  backend's copy in the same breath, re-recording `tools/shared-lib.sha256` in both, and a
  two-halves release. For 58 bytes.
- **Every stylesheet touched needs a cache-bust.** `HEAD_STYLES` in `lib/head.php` for the shared
  sheets, the `seo_head()` call for a page's own. `check_cache_bust.py` enforces it and will catch
  the omission — but the bump ships a new URL to every returning visitor, so it is a real cost.

`hero-circuit__charge--p4` is the one clean case, and it is confirmed rather than assumed:
`tools/templates/hero-circuit.html` carries **four `--p1`, four `--p2` and four `--p3`** and no
`--p4`, while `layout.css` declares all four. The template is generated and `--check` refuses one
edited by hand, so `--p4` cannot match by construction rather than by current usage. Roughly 40
bytes, and the only removal here that needs no instrument to justify.

### 2. The backend ships the public site's stylesheets — ~50 KB on every admin screen

Every admin screen loads `base.css`, `components.css`, `layout.css`, `theme.css` and `admin.css`.
Measured against all the backend's own PHP, JS and `admin.css`:

| | declared | unreachable | biggest dead families |
|---|---|---|---|
| `public/assets/css/components.css` | 115 classes | **95** | `dock` (54), `slider` (10), `field` (9), `cta-band` (5) |
| `public/assets/css/layout.css` | 97 classes | **91** | `hero-circuit` (45), `site-footer` (17), `site-header` (8), `section` (6) |

The admin has no dock, no hero circuit, no site header or footer and no slider — it has its own
shell. **These two files are not in `SHARED`**, so unlike `base.css` they may diverge from the
frontend's copies freely, which makes this the largest saving for the least cross-repo friction.

The instrument above is what makes it safe: the admin's twenty-seven signed-in screens are exactly
what the selector-coverage tool should walk, reusing `check_admin_a11y.py`'s sign-in.

**Do not assume `admin.css` is independent of them.** It was written against a page that also loads
the other four, so it may lean on a base rule without declaring it. That is a question for the
instrument, not for a reading.

### 3. Two things with no test coverage anywhere

- **`lib/mailer.php` (backend)** — this is the one with teeth. It sends **password-reset codes**, and
  `mail_header_safe()` is a header-injection guard. A regression is either recovery failing silently
  or headers becoming injectable. Called from `lib/reset.php` and `sections/account.php`. Worth a
  suite on its own merits, ahead of anything in this file: a header break in a name or address
  refused, subject and body arriving intact, `false` treated as certain failure and `true` as merely
  probable success (which is what the file's own docblock says `mail()` means).
- **`admin-outline.js` (backend)** — marks your position in the *On this page* column. Its failure
  mode is cosmetic: without it the column is still a working list of anchors, which the file's own
  comment says in terms. Low priority, and honest to leave documented rather than covered.

### 4. `references/` — 352 KB reachable from nothing

- `t4t_circuitry.svg` — 345 KB, superseded by `t4t_circuitry_6000_2031_300.svg`, which is the master
  `build_hero_circuit.py` actually reads. **Check the one reference before deleting it**: the only
  mention anywhere is an Inkscape `export-filename="t4t_circuitry.svg"` attribute *inside the
  successor file*, which is provenance rather than a dependency — the successor was exported from
  it. Nothing loads it, but that attribute is the record of where the live artwork came from, so
  decide deliberately whether that lineage is worth 345 KB rather than deleting on a grep.
- `tech-sphere-demo.html` and `tech-sphere-demo-details.md` — a scratch prototype referenced by
  nothing. The HTML carries a `<style>` block, an inline `<script>` and four CDN URLs: **the only
  file in either repository that would break three of the hard rules if it were ever served.** It
  never is — `references/` is in `FORBIDDEN_TREES` in `build_deploy_set.py`.

Lowest risk of everything here, since none of it is deployed and all of it is in git history. It is
repository weight, not page weight.

---

## Suggested order

1. **`lib/mailer.php`'s suite.** Independent of everything else, and the only item here where the
   failure mode is a security property rather than an appearance.
2. **The instrument** — selector coverage, then before/after screenshots. Nothing is removed yet.
3. **`references/`.** Nothing deployed, everything recoverable; a safe first use of the new habit.
4. **The backend's dead families**, one family per commit, screenshots identical each time. Largest
   saving, and divergence from the frontend is already expected there.
5. **The frontend's 14 unambiguous classes**, leaving `.shine` and `.logo-card` alone unless the
   instrument contradicts their comments. `base.css` last, or not at all — 58 bytes against a
   two-halves release is a poor trade and there is no shame in leaving it.
6. **`admin-outline.js`**, if it is still worth doing by then.

---

## The thing that would have caught the one defect that got through

The sweep shipped one fault and found it on the live site: `SERVICES_ALTERNATE_NAMES` assumed a
service name the editor had since changed, so the graph asserted the practice is called HRaaS and is
also known as HRaaS. Every local render was right, because **the committed `content/` seed is not
what is live.**

That applies directly here. A selector-coverage tool that walks only the committed seed will report
a rule dead when the live document would have matched it — the About page's accreditations band is
the standing example, since `content/about.json` ships with no `accreditations` key at all and four
browser crawlers walked straight past that band for its whole life.

**So the instrument must drive content states, not just the shipped documents** — the way
`tools/check_accreditations.py` publishes its own band before measuring it. Anything less will
confidently recommend deleting a rule that the live site needs.
