# Tools

**Applies to:** both

Every script in `tools/`. **None of them is deployed** — `.htaccess` blocks `/tools/` as a backstop,
but the real rule is that the directory never gets uploaded.

One exception is uploaded by hand, run, and deleted: `host-probe.php`.

Everything is Python 3 standard library, apart from the asset builders, which need **Pillow**. The
browser tests speak to geckodriver over its wire protocol — there is no Selenium.

---

## Running the site

| Script | Does |
|---|---|
| `serve.py` | Preview the whole site locally, including the PHP parts. `python3 tools/serve.py [port]` |
| `dev-router.php` | Router for the local preview server — resolves clean URLs the way `.htaccess` does |

---

## Checks — run these before committing

| Script | Proves |
|---|---|
| `check_contrast.py` | the palette meets WCAG 2.1 AA in both modes |
| `check_css.py` | every stylesheet's comments and braces balance, and no shorthand (`outline`, `border`) is handed a bare colour token — which parses, computes to a colour and a width, and draws nothing, because the shorthand reset the style to `none` |
| `check_shared_markup.py` | the hero circuit and the script blocks have not drifted between pages, and the two emitters still carry the script hooks whose absence nothing else would notice |
| `check_content_model.py` | the editor, the data and the page still describe the same thing, and no editor is unchecked |
| `check_secrets.py` | nothing protecting the admin has quietly stopped protecting it |
| `check_docs.py` | the documentation still describes the code: undocumented tools, libraries and **assets**; dead links; cited paths that have gone; constants whose documented values drifted. The asset and tool checks run **both** ways — a stylesheet or script named in the prose but absent from disk fails too, unless some document says in full which repository it moved to |
| `audit_pages.py` | every page: SEO, accessibility and structural correctness — including services that have no directory, read from the document and rendered through `detail.php`. Also that **a `srcset` of widths carries a `sizes=` beside it**: without one the browser is required to assume the picture fills the viewport and takes the widest file every time, so a ladder without it is not a missing improvement but a regression. And that a services page's **node ring still agrees with itself** — one spoke per node, and the spokes drawn first so they pass under the icons rather than over them. Both fail silently and neither is reported as broken; it is reported as the drawing looking wrong |
| `inject_icons.py --check` | every page's inlined icon block is current |
| `build_hero_circuit.py --check` | the hero circuit in `tools/templates/` is still the drawing the committed geometry makes — so a coordinate edited by hand, or a geometry re-resolved against different artwork and never rebuilt, is reported rather than shipped. Needs no browser |
| `build_deploy_set.py --check` | the set of files bound for the server holds nothing it must not, nothing is missing, and every `.php` in it parses with `short_open_tag=On` — the host's setting, not the laptop's |
| `verify_live.py <url>` | a deployed site still returns 403 for `lib/`, `content/` and dotted paths, still carries its headers, still answers on `/api/publish.php`, still serves a generated sitemap at `/sitemap.xml`, and still routes the services URLs |
| `check_cache_bust.py` | every stylesheet and script changed since `origin/main` is served from a URL that changed with it. Filenames are not content-hashed and `.htaccess` caches them for a year, so a changed file behind an unchanged URL reaches new visitors only — a release that looks complete from a clean cache and is absent for everybody who has been here before. Pass `--base` to compare against another revision |
| `check_shared_lib.py` | the three files both repositories hold identically have not been edited here |
| `check_icons.py` | every icon name either half can draw resolves to a symbol in the sprite. A name the sprite does not carry is **silent** — `<use href="#name">` paints nothing, with no error and no console line — so a blank box ships and reads as a design choice. `ADMIN_SECTIONS` named `cog` while the sprite had only `cogs`, and the Settings rail row and its Overview tile were empty for a release. Three lists have to agree: the sprite, the `*_ICONS` a picker offers, and `ADMIN_ICONS` — a name missing from the last draws in the site and not in the editor, which is the harder half to notice |
| `check_form_dom.py` | no script reads a property off a `<form>` that one of the form's own controls has hidden. `HTMLFormElement` is `[LegacyOverrideBuiltIns]`, so a control's **name wins over the interface's own property of that name**: a form holding `<input name="action">` answers `form.action` with that input, `fetch()` posts to `/[object%20HTMLInputElement]`, and the server answers 404. Ordinary submission is unaffected, which is why it can appear years after the field was added — on the day a script starts posting the form. It refuses eight members outright (`action`, `method`, `enctype`, `target`, `elements`, `submit`, `requestSubmit`, `reset`) and re-derives the rest from the markup, so a field added tomorrow that shadows something a script already reads fails tomorrow. Control names that shadow a property nothing reads are listed as a standing notice, not a fault |
| `check_shared_facts.py` | facts stated in two documents at once — the offices, the email and the telephone are authored in **both** `content/privacy.json` and `content/contact.json`, on purpose, so this asks whether the policy still states what the contact page manages. Compared on a normalised form, so it reports a different street and stays quiet about a different comma. It can only see the **seed**: content edited in the admin never passes through git, and that half is covered by the standing notice the privacy editor draws from the same function |
| `check_shared_repos.py` | the same files, compared against **the other repository** rather than a local digest — plus every same-named tool, which must match unless `DIVERGENT` says why not. This is the only check anywhere that can see the two halves drift apart; `check_shared_lib.py` structurally cannot. Needs both repos present, or `--clone` |

| `check_style_budget.py` | how much style recalculation each page does **while sitting still** — nothing clicked, nothing scrolled. Needs Chrome. This is the only check here that can see a page burning a CPU core at a steady 60fps, which is what shipped on 2026-09-03 and what every frame-rate suite missed. It watches `/pages/company-profile/` now, and found something on the first run: `tech-sphere.js` re-armed its frame unconditionally, so fifty logos had their transforms invalidated every frame of every second the page was open — while the sphere sat several screens below the fold and nobody had ever seen it. 134ms of style per second against the 100ms ceiling; 31ms after an `IntersectionObserver` was put on it. Transform-only, so no layout and no paint, so `test_motion.py` reported a steady 60fps throughout. This is the second time that exact shape has shipped. **Note what it can and cannot see now:** it measures a page sitting still, and the sphere is paused while it sits still, so anything done inside that loop is invisible here by construction — `sphere_smoothness` in `test_motion.py` is what measures that |

## Checks that need a browser

| Script | Proves |
|---|---|
| `check_dark_mode.py` | every page as the browser actually paints it, in both themes |
| `check_hover.py` | every kind of interactive element visibly responds to a real pointer |
| `check_responsive.py` | no page scrolls sideways, no control is wider than the screen, and no tap target is under 24px, at seven widths from 320px up — then again with the navigation as full as the picker allows, and again with an uploaded logo of every shape from portrait to 24:1 |
| `check_focus.py` | tabbing every page: the focus ring can be seen, and nothing covers it |
| `check_accreditations.py` | the About page's **Accreditations** band, which no other check has ever seen. `content/about.json` ships with no `accreditations` key at all, so the four crawlers above walk that page and go straight past the band — it is built, styled, published, protected from the upload sweep and given a measured 289px picture slot, and then never rendered. This supplies its own document, measures the band and puts `content/` back: the grid really laying out 2 / 3 / 4 across its two breakpoints, read as painted x-positions rather than out of the CSSOM; every plate square, because `aspect-ratio: 1` is a request a tall mark or a wrapped caption can defeat; a very wide, a very tall and a badge-less tile all contained; no sideways scroll at 320px; `--artwork-plate` resolving to the **same** colour in both themes, which is the entire reason it is not a themed surface; and the caption clearing WCAG AA against the ground it actually sits on. Nothing shipped changes and the live band stays hidden |

All of them skip with a notice and exit 0 when Firefox or geckodriver is missing — except that `check_accreditations.py` fails rather than passing if it measured nothing at all, because a band that did not render would otherwise print `0/0` and exit 0.

---

## Tests

**Half the suite is in the other repository.** The editor's round trips, the sign-in and the browser
run over the admin went with the admin. What is here exercises the public site and the one endpoint
that writes to it.

### Over HTTP, against a real PHP server

| Script | Exercises |
|---|---|
| `test_contact_handler.py` | `contact-handler.php`, including header injection and the captured message |
| `test_store.py` | `lib/store.php`: reading, writing, and the rule that a damaged file never becomes the backup |
| `test_publish.py` | `api/publish.php` and `publish_push()` over real HTTP: the happy path, and every way past it that does not involve holding the key. Plus one round trip per document — every field the model declares, set and then read off the rendered page. The milestones' round trip covers **two** pages, because one document feeds both: the whole timeline on `/pages/milestones/` and the five-year window on the company profile, including that an unreadable year is kept and that the graph describes what the markup carries. It also asserts that the two capped walls of logos lose nothing — every row is in the markup, inside a closed `<details>` — and that each cap divides every column count its grid uses, which is the property that keeps the row above the button from coming out ragged |
| `test_publish_asset.py` | the endpoint pictures arrive on: a signed picture accepted, everything else refused — a PHP script, a GIF, a script wearing a PNG header, and a header claiming a picture nobody could hold. Also that a vector file is accepted only when it is already the sanitiser's own output, so this host proves it rather than trusting the sender, and that the name is always this side's and never the sender's |
| `test_sitemap.py` | the three files that are addresses rather than pages — `/sitemap.xml`, `/robots.txt` and `/site.webmanifest`, each rendered by a `.php` file behind an internal rewrite. That all three answer with the right `Content-Type`, that the sitemap is well-formed and lists exactly the indexable routes, that a page set to noindex and a hidden service both leave it, that `robots.txt` always allows the whole site and always names the sitemap whatever the document says, and that a page which has never been published claims no `lastmod` rather than inventing today's date |
| `test_pictures.py` | what a picture stored at several widths turns into on the page. Each of the eight laddering slots emits `srcset` **and** the `sizes=` its slot declares, on the `<img>` and on the WebP `<source>`, with `src` still naming the top rung so a scraper that does not parse a candidate list gets a real picture. And the case every document is in today: **a picture with no ladder renders exactly as it did before ladders existed** — no `srcset`, no `sizes`, no change to the markup. A slot that declares no `sizes=` gets no candidate list even if its document somehow holds one |
| `test_settings.py` | that **one mark reaches every place the site draws it**. The logo is in nine places across two repositories and used to be eight separate copies — the chrome held two, typed by hand; the SEO screen had a logo upload completely disconnected from the header; the rest named committed files no editor could reach. So this changes the mark once and reads it back out of the header, the footer, the About lockup, `Organization.logo` and every job posting's hiring organisation. Also: **every page renders with `content/settings.json` missing**, which is where a fresh clone and a failed publish both are; the `identity.logo` override wins for the graph and **not** for the header; and an empty dark half draws the light mark rather than a hole, with the light lockup still eager and the dark one still lazy |
| `test_chrome.py` | the header, footer and dock every page carries, rendered from `content/chrome.json` by `lib/body.php`. That a hidden row is absent rather than faint, that `aria-current` lands on exactly one nav link and never on the brand, that the header and the dock agree about where you are, that the footer's services column follows `content/services.json` including a service hidden after the fact, that a contact row draws the `tel:` or `mailto:` form its kind calls for — and that **every page still renders correctly with `content/chrome.json` deleted**, which is the state a fresh clone and a failed publish are both in — while a band emptied on purpose stays empty, because filling that one back in would put links somebody deliberately removed back on every page |
| `test_svg.py` **shared** | the SVG sanitiser, tested as the security boundary it is: a real logo survives and still draws, sanitising it twice changes nothing — which is what lets the receiving host prove bytes are clean without editing them — and script, event handlers, entities, embedded rasters, animation, filters and any reference off the file are each refused rather than quietly stripped |

### In a real browser

| Script | Proves |
|---|---|
| `test_motion.py` | the scroll reveal never leaves anything unread — **and the sphere's two promises: that it takes the room it has, and that the plate is drawn the same size whatever size the sphere is.** The second only holds because the perspective is derived from the size; a fixed one magnifies the near face, so the check measures the plate at two sphere sizes on the same page rather than trusting the arithmetic. Also that the depth fade dims some plates and not others, and never to nothing. Its `sphere_smoothness` is the only measurement of what the sphere costs **while it is running** — `check_style_budget.py` measures a page at rest, where the sphere is paused, so it cannot see work done inside the loop. Plus the technology list across the sphere's 768px line, **both ways**: capped to `COMPANY_TECHNOLOGY_WALL` with the rest behind a `<details>` below it, every logo adopted onto the surface above it, and the counts holding after four crossings. A one-way test would pass on a sphere that adopts and never gives back, and the logos would then be missing from the grid for the rest of the visit. Also that a landscape phone gets a sphere no taller than 80% of its screen |
| `test_nav.py` | the navigation is usable at both widths. The link counts it checks are **read from `content/chrome.json`**, not typed, so the suite keeps meaning after somebody edits the nav — `CHROME_BAR_SLOTS` stays a literal, because four is code |
| `test_theme.py` | the theme switch behaves, with a real OS preference |

---

## Markup

| Script | Does |
|---|---|
| `assemble_page.py` | Write a new page's skeleton — the requires, the `<head>` calls, the `<body>` calls — around a per-page `<main>` block. **For creating a page, not maintaining one** |
| `build_hero_circuit.py` | Redraw the hero circuit's template from the company's own banner artwork in `references/`. `--resolve` re-reads the SVG through **Chrome** — Inkscape buries every shape under composed matrices and 542 clip paths, and the honest way to resolve that is to ask an SVG engine rather than reimplement one — and writes `tools/templates/hero-circuit.geometry.json`. Everything after that is plain Python over the committed geometry, so `--check` needs no browser and fails loudly rather than skipping. Then `propagate_shared.py` |
| `propagate_shared.py` | Push a change in `tools/templates/` out to every page — the hero circuit, and nothing else since [ADR 0023](../90-decisions/0023-the-header-and-footer-are-emitted-once.md) |
| `inject_icons.py` | Inline each page's icon subset from the master sprite |
| `apply_reveals.py` | Mark up the scroll-reveal targets on every page, from one structural rule |
| `htmltree.py` | *(a library)* a minimal HTML tree with source offsets, for tools that edit markup structurally |

---

## Asset generation

Run rarely — usually only when the source artwork changes. **These need Pillow.**

| Script | Does |
|---|---|
| `build_icon_sprite.py` | Build the self-hosted SVG icon sprite from Font Awesome Free metadata |
| `build_images.py` | Copy, rename and optimise the site's content images |
| `build_logos.py` | Normalise the master logo artwork into the web asset set — **the committed seed, not the live mark.** A company changing its logo uploads it at `?s=settings&part=logo`, which stores it under `/uploads/` and sends it here; this rebuilds what a fresh install shows before anybody has. Different input, different output, different lifecycle, so it stays |
| `build_favicons.py` | Generate the favicon set from the 512px master |
| `build_og_image.py` | Build the 1200×630 Open Graph / Twitter Card share image |
| `fetch_fonts.py` | Fetch and self-host the Inter variable font (latin + latin-ext) |
| `stage_live_images.py` | Copy the live site's imagery into `tools/masters/` under readable names |

Sources live in `tools/masters/`.

---

## Looking at things

| Script | Does |
|---|---|
| `shoot_pages.py` | Photograph pages in headless Firefox, into `tools/shots/` (gitignored) |

---

## Publishing

The two halves and the one route between them — [the publish API](../10-development/server-side/publish-api.md).

| Script | Does |
|---|---|
| `make_publish_key.py` | Create the key both halves sign content with. Run **once**, then copy the printed value into the other half's private store by hand |
| `check_shared_lib.py` | Assert the four shared PHP files and the icon sprite against a committed digest. `--update` re-records after a deliberate change |

`make_publish_key.py` is deliberately not automatic. Every other secret here creates itself on first
use; this one must not, because a key that appears by itself appears **differently** on each host and
the failure reads as "signature rejected" until somebody thinks of it.

The backend's `reconcile.py` sends anything this site is behind on. It needs no status endpoint:
every answer from `api/publish.php` carries the revision this host holds — the refusals as well as
the acceptance — so an attempt *is* the question, and an attempt refused as `not-newer` has changed
nothing.

---

## Host tools — upload, run, delete

### `host-probe.php`

Answers the questions that can only be answered on the server, and that all fail quietly:

- the PHP version
- whether argon2id is available, and how long a hash takes
- where the private store resolves to, and **whether it is outside the web root**
- whether `mail()` works — it sends a real test message

```
1. upload to public_html/ by hand
2. set PROBE_TOKEN as its header instructs
3. load it once, read the report
4. DELETE IT
```

It refuses to run until the token is changed, and its recipient is hard-coded so it cannot be
pointed anywhere else.

### The admin's own tools are in tech4time-website-backend

`tech4time-website-backend/tools/admin-cli.php` — the rescue tool that resets a password, issues recovery
codes, unpairs the authenticator and reads the audit log over SSH — belongs with the accounts it
edits. So do these:

| | |
|---|---|
| `tech4time-website-backend/tools/test_admin_auth.py` | the whole sign-in cycle |
| `tech4time-website-backend/tools/test_careers_admin.py` | the job post editor |
| `tech4time-website-backend/tools/test_contact_admin.py` | the contact page editor |
| `tech4time-website-backend/tools/test_editor.py` | the editor in a real browser |
| `tech4time-website-backend/tools/admin_session.py` | *(not run directly)* signs a test in |
| `tech4time-website-backend/tools/reconcile.py` | re-sends anything this site is behind on |

Nothing here can reach an account: this half holds no password hash and no name for a file that
could contain one. `check_secrets.py` asserts that on every run.

---

## Directories

| | |
|---|---|
| `tools/templates/` | the canonical markup for what is still copied into pages: the hero circuit and the script tags |
| `tools/masters/` | source artwork for the asset builders |
| `tools/shots/` | screenshot output, gitignored |

---

## Adding a tool

A docstring saying **what it proves and how to run it**, standard library only (or Pillow), exits
non-zero on failure, prints what failed rather than that something did, and cleans up after itself.

Then add it to this page — `check_docs.py` fails until you do.
