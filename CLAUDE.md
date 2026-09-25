# Tech4TIME — frontend

The public site at **`tech4time.bd`**: seventeen pages, a contact form, and one inbound endpoint that
receives content from the admin. No build step, no framework — the files here are the files
that run on the server.

**The editor is not in this repository.** It is **`tech4time-website-backend`**, served at
`admin.tech4time.bd`, and it owns the content. This site renders from a local replica it is *sent*;
it never calls the backend during a request. [publish-api.md](docs/10-development/server-side/publish-api.md)

**Full documentation is in [docs/](docs/).** Start at [docs/README.md](docs/README.md), which routes
by intent. New to the project: [docs/00-orientation/README.md](docs/00-orientation/README.md), then
[docs/10-development/setup.md](docs/10-development/setup.md).

---

## Rules that must not be broken

Each has a reason, recorded in [docs/90-decisions/](docs/90-decisions/). If one seems wrong, read
its record before acting.

1. **No build step, no framework, no bundler, no package manager.** The files here are the files
   that run on the server.
2. **No CDN and no external origin.** Everything is self-hosted — with **one** exception, which is
   off unless somebody has switched it on: Google Analytics, from
   `?s=seo&site=crawl`. With that field empty no page reaches another origin at all and the CSP is
   unchanged; `tools/audit_pages.py` refuses any other external origin either way.
   [ADR 0021](docs/90-decisions/0021-analytics-is-off-until-somebody-turns-it-on.md)
3. **No inline styles or scripts.** The CSP is `style-src 'self'; script-src 'self'` — a `style=`
   attribute, a `<style>` block or an `onclick` will be refused by the browser.
4. **Every page must work with JavaScript off.** Progressive enhancement is a hard rule. Motion may
   decorate; it may never be the only route to anything.
5. **Content renders on the server.** No runtime `fetch()` for content, ever — including for the
   header and footer, and including from the backend.
6. **Never commit anything from the private store** (`t4t-private/`, `*.key`).
7. **`content/` is a replica.** It is written by `api/publish.php` and by nothing else — not by
   hand, not on the server, not by a deploy. The next publish overwrites anything you put there.
8. **Nine files are byte-identical** with `tech4time-website-backend`: `lib/html.php`,
   `lib/contract.php`, `lib/publish.php`, `lib/svg.php`, `lib/markdown.php`, `lib/store.php`,
   `lib/throttle.php`,
   `assets/icons/sprite.svg` and `assets/css/base.css`. Change one and you change both, in the
   same breath. **The list is `SHARED` in `tools/check_shared_lib.py`, not this sentence** —
   this one had been short of the real set, so read the map rather than the prose.
9. **`tools/` is never deployed.**
10. **Never edit a header, footer or dock in a page file.** There is nothing there to edit: they
    are `content/chrome.json`, emitted by `lib/body.php`, and their words are edited at
    `https://admin.tech4time.bd/?s=chrome`.
    [ADR 0023](docs/90-decisions/0023-the-header-and-footer-are-emitted-once.md)

---

## Where things are

| | |
|---|---|
| `pages/` `index.php` | the seventeen pages. **All of them are `.php`** now and render from `content/` — `404.php` was the last static one |
| `pages/services/detail.php` | not a page: it serves any service the editor added that has no directory |
| `sitemap.php` `robots.php` `manifest.php` `favicon.php` `assets/css/brand.css.php` | generated, and served at `/sitemap.xml`, `/robots.txt`, `/site.webmanifest` and `/favicon.ico` — those addresses must not change. The last had no answer at all until the mark became content; a browser probes it blindly, before it has read a line of the page |
| `lib/head.php` | every page's `<head>`, emitted once. Not shared markup: there is nothing to propagate |
| `lib/body.php` | every page's header, footer and dock, likewise — from `content/chrome.json` |
| `assets/` | css, js, fonts, icons, images — all self-hosted |
| `lib/` | server-side PHP: rendering, the contract, the publish format |
| `api/publish.php` | where the backend's content arrives. The only thing here that writes |
| `content/` | the replica the dynamic pages render from |
| `tools/` | build, audit and test scripts — never deployed |
| `docs/` | the documentation |
| `plans/` | designs, not deployed. A parked one is **not yet** true; a shipped one is marked **BUILT** at the top and kept only for the research and reasoning behind it. Either way `docs/` is what is true — never cite `plans/` for the state of the site |
| `../t4t-private/` | **outside the repo** — `secret.key`, `throttle.json`, `publish.key`. Never committed |

There is no `admin/`, no `lib/auth.php` and no password hash on this host. The private store has no
*name* for one — `t4t_private_path()` throws on a key it does not know — and
`tools/check_secrets.py` asserts it on every run.

---

## Where to change what

Full table: [docs/10-development/where-to-change-things.md](docs/10-development/where-to-change-things.md)

| Change | Where |
|---|---|
| A colour | `assets/css/theme.css` — tokens only, never a hex elsewhere. The **fourteen brand tokens** are editable at `https://admin.tech4time.bd/?s=settings&part=colour`, which overrides them through the generated `/assets/css/brand.css` and **refuses** anything below WCAG AA |
| Layout, components | `assets/css/layout.css`, `components.css` |
| Browser behaviour | `assets/js/` — modules register on `window.Tech4Time` |
| Header / footer / dock | **`https://admin.tech4time.bd/?s=chrome`** if it is words or links; `lib/body.php` if it is markup. Never a page file |
| The hero circuit around a page title | the artwork in `references/` → `build_hero_circuit.py` → `propagate_shared.py`. **Never the template by hand** |
| Anything in a page's `<head>` | `lib/head.php` if it is code, **`https://admin.tech4time.bd/?s=seo`** if it is words. Never a page file |
| A title, description, keywords, share card, crawl setting, the sitemap, `robots.txt`, the manifest | **`https://admin.tech4time.bd/?s=seo`** |
| Whether Google Analytics runs, and against which property | **`https://admin.tech4time.bd/?s=seo&site=crawl`** — a field, not a deploy |
| An icon | the markup, then `python3 tools/inject_icons.py` |
| A job post, a contact detail, a certification, a logo file, the privacy policy, the about or home page's copy | **`https://admin.tech4time.bd/`** — not a file, and not here |
| A milestone on the timeline | **`https://admin.tech4time.bd/?s=milestones`** — its own document, `content/milestones.json`, read by two pages: `/pages/milestones/` shows all of it and the company profile shows the most recent `MILESTONES_WINDOW` years and links there. Not the `?s=company` screen, which no longer holds the band |
| An accreditation badge on the About page (ISO 27001, SOC 2) | **`https://admin.tech4time.bd/?s=about`** — the Accreditations band. It ships hidden; switch it on once there is a badge on it. Not the `?s=certifications` screen, which is the separate Resource Certifications page and is about people's qualifications |
| Where the enquiry form's mail goes, and its subject line | **`https://admin.tech4time.bd/?s=settings&part=mail`**. What it is sent **as** is not editable — `SETTINGS_MAIL_FROM`, because the domain's SPF record is not something the editor can change |
| A page's address | `SEO_ROUTES` in `lib/contract.php`, and `.htaccess`. A route is code; the editor cannot add, rename or remove one |
| The shape of editable content | `lib/contract.php` — **and the same file in the backend** |
| How a document is signed | `lib/publish.php` — likewise byte-identical |
| Add a page | [adding-a-page.md](docs/10-development/frontend/adding-a-page.md) |
| Headers, caching, blocking | `.htaccess` — not read by the local dev server |

---

## Running it

```bash
python3 tools/serve.py          # http://localhost:8000  — NOT python3 -m http.server
```

**Every page needs PHP now** — the head is emitted by `lib/head.php` on the request, so there is no
page that is only markup. So do the contact handler, `api/publish.php`, and the three generated
files: `sitemap.php`, `robots.php` and `manifest.php`.

`tools/dev-router.php` is what makes the local server behave like the host — DirectoryIndex across
both extensions, the trailing slash Apache's DirectorySlash adds, the services route, the
sitemap's address, and the paths `.htaccess` refuses.
It is **not** a complete stand-in: `.htaccess` itself is never read locally, so the rewrite rules
can only be verified against the live host with `tools/verify_live.py`.

To watch content actually arrive, run the backend's `serve.py` beside this one and point it here:

```bash
# in tech4time-website-backend
T4T_PUBLISH_URL=http://localhost:8000/api/publish.php python3 tools/serve.py 8001
```

Both halves need the **same** `publish.key` in their private stores — `tools/make_publish_key.py`.

[docs/10-development/running-locally.md](docs/10-development/running-locally.md)

---

## Before committing

```bash
python3 tools/check_contrast.py        python3 tools/check_content_model.py
python3 tools/check_css.py             python3 tools/check_shared_repos.py
python3 tools/inject_icons.py --check  python3 tools/check_secrets.py
python3 tools/check_shared_markup.py   python3 tools/check_docs.py
python3 tools/check_shared_facts.py     python3 tools/check_form_dom.py
python3 tools/audit_pages.py           python3 tools/check_shared_lib.py
python3 tools/build_deploy_set.py --check
python3 tools/build_hero_circuit.py --check
python3 tools/check_icons.py
```

Touched anything under `assets/`? Also **`python3 tools/check_cache_bust.py`** — filenames are not
content-hashed and `.htaccess` caches them for a year, so a changed file behind an unchanged URL
ships to new visitors only. A **stylesheet** is one edit — `HEAD_STYLES` in `lib/head.php` for the
five shared ones, the `seo_head()` call for a page's own. A **script** is still every page plus
`tools/templates/scripts.html`, which `propagate_shared.py` does not carry.

Touched `api/publish.php`, `lib/contract.php` or `lib/publish.php`? Also `test_publish.py` **and
`test_publish_asset.py`** — the second endpoint is easy to forget, and CI runs it — **and
`check_shared_lib.py --update`, and copy the changed file to the backend.**

Touched `lib/body.php`, `lib/chrome.php` or `content/chrome.json`? Also
**`python3 tools/test_chrome.py`**, which reads the words — hidden rows absent, `aria-current` on
one nav link and never the brand, the derived services column, and every page still right with
`content/chrome.json` deleted — and **`audit_pages.py`**, which reads the structure and is the only
check that sees the header, footer and dock a visitor actually receives, and
`check_shared_markup.py`, which asserts the emitter still carries the six `data-` hooks the scripts
bind to.

Touched `lib/head.php`, `lib/seo.php`, `sitemap.php`, `robots.php`, `manifest.php` or
`favicon.php`? Also
**`python3 tools/test_sitemap.py`** and `audit_pages.py`. All three of those files are served at an
address that looks static and none of them is; a PHP error in one ships as a 500 at a URL no page
links to, which Google fetches and people do not.

Touched the contact handler? Also `test_contact_handler.py`. Touched `lib/store.php`? Also
`test_store.py`.

Touched the company profile's growth bands — the timeline's window, `COMPANY_CLIENTS_WALL`,
`COMPANY_TECHNOLOGY_WALL`, or the column counts in `assets/css/pages/company-profile.css`? Also
**`python3 tools/audit_pages.py`**, which asserts what actually reached the markup, and
**`test_publish.py`**, which asserts that each cap divides every column count its grid uses. A cap
that does not is a ragged half-row above the button at exactly one width; a cap that stopped working
is a page several screens longer than it was meant to be, still saying everything it should. Both
fail silently. The caps and the tiers are **one design** — change one and change the other.

Touched the logo, or anything that draws it — `lib/body.php`, `lib/about.php`, `lib/seo.php`,
`lib/careers.php`? Also **`python3 tools/test_settings.py`**. The mark is in nine places across two
repositories and `content/settings.json` exists so that they are one; that suite changes it once and
reads it back out of all of them, plus the case a fresh clone is in, where the document is missing
and the site still has a logo.

Touched a renderer that draws an uploaded picture — `about_picture()`, `home_picture()`,
`company_picture()`, `branding_picture()`, `contact_flag_picture()` — or a number in
`CONTRACT_IMAGE_SLOTS`? Also **`python3 tools/test_pictures.py`** and `audit_pages.py`. The first
puts a ladder into each document and reads the markup back; the second is the only check that sees a
`srcset` of widths shipped with no `sizes=` beside it, which is a regression rather than a missing
improvement — without it the browser takes the widest rung on every screen. A width in that table is
**measured, not estimated**: two of the eight are widest on a phone or a tablet rather than a
desktop, and a third is flat across every width only because its grid caps the track — measure with
the number of rows the band will really hold, because `auto-fit` with a `1fr` maximum makes the
picture wider the fewer of them there are.

Touched the circuitry around a page title? It is **generated**: the drawing is the company's own
artwork in `references/`, `python3 tools/build_hero_circuit.py` redraws
`tools/templates/hero-circuit.html` from it and `propagate_shared.py` carries it to every page
that has a title band. `build_hero_circuit.py --check` refuses a template edited by hand and
needs no browser; only `--resolve`, which re-reads the SVG, wants Chrome. The geometry and the
stylesheet are one drawing — `circuit.js` reads both the ink and the pen out of `layout.css` — so
changing a viewBox means changing `LAYERS` there too.

**One composition, on every device, and every channel through it the same width.** `.hero-circuit`
is a **grid** — cluster / band / cluster across, two rows down, one `gap`. That gap is A, B, C and D
at once, equal by construction rather than by four numbers kept in step; the rows are `1fr`, so a
band and a cluster are each `(banner − gap) / 2` tall, which is what makes the vertical channels
match the horizontal ones. **`--hc-gap` must be a LENGTH:** `row-gap: %` resolves against height and
`column-gap: %` against width, so a percentage is unequal in pixels by definition.

**The band tiles; it is never stretched.** `preserveAspectRatio="xMidYMid slice"` over a viewBox
`BAND_TILES` wide, so its *height* sets the scale and its width never does — one trace pitch at
360px, at 4K and at any zoom. `BAND_TILES` in `tools/build_hero_circuit.py` and `circuit.js` must
agree, and it is **odd** so the banner's centre stays a mirror axis. Charges and nodes sit on the
centre tile only: tiling animated elements is what cost a CPU core in 2026-09. **`circuit.js` clips
the canvas to each layer's box** — the `<svg>` crops `slice`'s overflow and the canvas does not, so
without it charges paint straight through the channels.

**Above 1280px the clusters are sized by the viewport, not the row**, so they do not shrink to
specks on a wide screen — 2.5% of the width at 3840 otherwise, against the artwork's 11.15%. They
then reach past their row and close **D** alone; A, B and C are untouched. The ceiling is the
banner's height: two clusters stacked down one edge must still fit, which caps them near 154px.

`hero_circuit()` measures eight viewports (its probe takes a **height** as well as a width — a media
query inside an iframe reads the iframe), and `hero_gaps()` photographs the banner and measures all
four channels **in pixels**, because boxes cannot see whether the ink stops.

Touched CSS, markup or motion? Also `test_motion.py`, `test_nav.py`, `test_theme.py`,
`check_hover.py`, `check_dark_mode.py`, `check_responsive.py`, `check_focus.py` — these need
Firefox and geckodriver, and leave processes behind if interrupted (`pkill firefox geckodriver`).

Touched the About page's **Accreditations** band, `about.css`, or `about_picture()`? Also
**`python3 tools/check_accreditations.py`**. It is the only check that has ever seen that band:
`content/about.json` ships with no `accreditations` key, so all four crawlers above walk the About
page and never render it. This one supplies its own document — a very wide badge, a very tall one, a
long caption and a tile with no badge at all — measures the grid, the squareness of the plates, the
320px behaviour and the painted colours in both themes, and puts `content/` back. It **fails rather
than passes** if it measured nothing, because a band that did not render prints `0/0` otherwise.

Changed how much of the page **moves**? Also **`python3 tools/check_style_budget.py`** (needs
Chrome). None of the suites above can see a page burning a CPU core while holding 60fps — that
shipped on 2026-09-03 and a person noticed it before any check did. **It runs in CI now**, which it
did not until 2026-09-11: it exits 0 with a notice when Chrome is absent, so it had been the one
check capable of proving nothing while reporting success.

**Added a suite? Add it to `.github/workflows/test.yml` in the same commit.** Nothing checks this,
and two suites had been sitting on disk unrun — `test_settings.py` and `test_pictures.py`, 97
checks asserted nowhere.

[docs/10-development/testing.md](docs/10-development/testing.md)

---

## Keep the documentation true

**Change the code, update the doc that owns it, in the same commit.** The ownership table is in
[docs/README.md](docs/README.md#which-doc-owns-what).

`python3 tools/check_docs.py` catches the mechanical half — an undocumented tool or library, a dead
link, a cited path that no longer exists, or a constant the prose quotes that has changed. It cannot
read prose; that part is on you.

**A path in backticks always means *this* repository.** A file in the other half is written with the
repository in front: `tech4time-website-backend/lib/auth.php`. `check_docs.py` enforces it.

---

## Status

Work happens on `dev`; pull requests to `main` need explicit approval, are merged with **Create a
merge commit**, and are followed by merging `main` back into `dev` -- which fast-forwards, so the two
branches end a release on the same commit instead of drifting one apart each time.
[ci-cd.md](docs/20-deployment/ci-cd.md)

**Live** at `https://tech4time.bd` — cPanel, LiteSpeed, PHP 8.2.33. **A push to `main` deploys it.**
Checks run, rsync over SSH, and the site is asked afterwards whether `lib/`, `content/` and dotted
paths still answer 403 and `/api/publish.php` still answers 405 —
[ci-cd.md](docs/20-deployment/ci-cd.md). Never sync `content/`; it is seeded with `--ignore-existing`
and the host's copy always wins.

**Not done, and not missing:** LCP/CLS/INP were measured in the lab against the live host on
2026-08-27 — 1.5 s / 0 / 24 ms, Lighthouse 99 —
[host-facts.md](docs/40-reference/host-facts.md). **Field** figures come from CrUX and need a
month of real traffic, so they cannot exist yet. Do not quote the lab numbers as field numbers.
