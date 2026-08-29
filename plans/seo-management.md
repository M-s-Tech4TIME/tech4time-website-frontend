# Bring SEO under admin control, for all sixteen pages

**Status: parked. Not built, not started, not scheduled.**

**Applies to:** both — the editor is in `tech4time-website-backend`, everything it edits is here.

This is a design, not documentation. It names files that do not exist. Nothing in `docs/` should
link to it and nothing here describes the site as it is today — that is what `docs/` is for, and
why this lives outside it.

---

## Why it is parked

SEO management is the **last** piece of admin work, deliberately. Every page of the site comes
under management first — the services page and its six sub-pages next, then the rest — and only
then does SEO get built on top of a site where every page is already dynamic.

That ordering is not incidental. Roughly half of the work below is the conversion of thirteen
static `.html` pages to `.php`, and every page that comes under content management does that
conversion on its own account. By the time this plan is picked up, much of its Phase 4 may already
be done and its shape will have changed.

**Read the *Revisit conditions* below before trusting any of this.**

Researched and measured on **2026-08-29** against frontend `95ca51f` and backend `b613adf`.

---

## Revisit conditions

Two parts of this design are expected to shift, and one is worth re-checking:

- **Phase 4's conversion list shrinks** as pages become manageable. Re-count what is still `.html`
  before costing it.
- **The "stays in code" boundary moves.** The per-page `Service` / `OfferCatalog` / `ItemList`
  JSON-LD is excluded below *only* because it mirrors a static body. Once the services page's body
  is managed, that schema should be generated from the same document instead — a better answer that
  is simply unavailable today.
- **Re-check** whether the three defects in *Context* are still open, and whether
  `CONTRACT_VERSION` is still 1.

---

## Context

Every SEO value on the site is hand-written into a file. Titles, descriptions, canonicals, Open
Graph and Twitter cards, the crawl directives, the Organization graph, `robots.txt` and
`sitemap.xml` — none of it can be changed without a developer and a deploy. The goal is total
control from `admin.tech4time.bd`, for **every** page.

**It is independent of the other page-management work, and it could be done at any time.** Company
Profile management landed complete, the signed image channel exists, and `api/publish.php` already
dispatches documents from a table. Adding a fourth document is a road three documents have walked.

**But it has one hard prerequisite.** ADR 0003 says content renders on the server and the frontend
never fetches. Thirteen of the sixteen pages are static `.html`, so nothing in `content/` can ever
reach their `<head>`. "SEO for every page" *requires* converting all thirteen to `.php`.

### Three defects found while measuring, fixed as part of this work

1. **The Organization JSON-LD is stale on fifteen pages.** Three office addresses and four contact
   points are pasted literally into all sixteen heads (`tools/templates/jsonld-base.html`, lines
   78–234 of every page). Only `pages/contact/index.php` renders them from `content/contact.json`
   via `contact_addresses()` / `contact_points()`. Edit an office in the admin today and fifteen
   pages keep publishing the old address.
2. **The `<head>` has no propagation tool and no drift check.** `tools/propagate_shared.py` covers
   header, dock, footer and hero-circuit only; `tools/templates/head.html` is read exactly once per
   page, at birth, by `tools/assemble_page.py`. Sixteen independent copies, nothing comparing them.
   This is *why* defect 1 exists.
3. **Four gate tools are blind to a root-level `.php` file.** `tools/audit_pages.py:39`,
   `tools/check_shared_markup.py:75`, `tools/inject_icons.py:54` and `tools/propagate_shared.py:76`
   all glob `ROOT.glob("*.html")`. Converting `index.html` (1187 lines) and `404.html` (739 lines)
   would drop the two biggest pages out of four checks **silently**. Phase 0, before anything moves.

Two smaller ones, also fixed: `.htaccess` strips `index.html` from URLs but has no `index.php` rule,
so the three existing PHP pages already answer at two addresses; and `tools/dev-router.php:42`
prefers `index.php` while `DirectoryIndex` (`.htaccess:104`) prefers `index.html`, so local and live
disagree about which file wins.

### Decisions taken

| | |
|---|---|
| **Scope** | Everything except the body-mirroring schemas — see *What stays in code* |
| **Ownership** | One `?s=seo` screen owns all sixteen pages; Contact and Company lose their meta band |
| **Head** | One shared `lib/head.php` emitter; sixteen heads collapse to one function |
| **Rollout** | All remaining conversions in one change, proven non-visual |

---

## What comes under management, and what stays in code

**Managed** — every field below becomes editable at `admin.tech4time.bd/?s=seo`:

*Per page (×16):* `<title>` · meta description · share title (`og:title` / `twitter:title`) · share
image override · `robots` index/noindex · breadcrumb label · sitemap `changefreq` and `priority`.

*Site-wide:* site name · locale · `<html lang>` · default share card (uploaded) · Twitter card type ·
theme colours · Organization legal name, founding date, logo, price range, service types,
knows-about · `sameAs` links (repeatable) · opening hours (repeatable) · extra `robots.txt` rules.

*Generated, never hand-edited again:* `sitemap.xml` · `robots.txt` · `site.webmanifest` ·
BreadcrumbList JSON-LD on every page · the Organization / WebSite / ProfessionalService graph, whose
addresses and contact points come from `content/contact.json` — which closes defect 1.

**Stays in code, deliberately:**

- The large per-page `Service` / `OfferCatalog` / `ItemList` JSON-LD on the home and service pages.
  Those blocks are a mirror of the page body. Making them editable while the body is still static
  hands an operator a way to publish structured data that contradicts the visible page. They come
  under management when *that page's body* does, not before. (See *Revisit conditions* — this is
  the boundary most likely to have moved.)
- `ContactPage`, `AboutPage` and `JobPosting` — already generated from their own documents by
  `contact_page_schema()`, `company_page_schema()` and `careers_job_posting()`. Unchanged.
- Favicon paths, the font preload, the CSP meta, the stylesheet list, `X-Robots-Tag` in `.htaccess`.
  These are code, not content, and an editor able to break the CSP is a hazard, not a feature.
- **Routes.** A page's route is not editable and rows cannot be added, removed or reordered. The
  route list is `SEO_ROUTES` in `lib/contract.php`; adding a page is a code change, as it always
  was, and the SEO card for it then appears by itself. This is what makes it impossible to orphan a
  record or point one at a URL that does not exist.

---

## Phase 0 — Close the blind spots first

None of this depends on the feature; all of it must land before a page moves.

- **Widen the root glob to `*.php`** in `tools/audit_pages.py:39`,
  `tools/check_shared_markup.py:75`, `tools/inject_icons.py:54`, `tools/propagate_shared.py:76`.
  Without this, `index.php` and `404.php` leave four checks without a word.
- **`.htaccess`** — add the `index.php` twin of the existing clean-URL rule, beside it at `:93-95`:
  ```apache
  RewriteCond %{THE_REQUEST} \s/(.*/)?index\.php[\s?] [NC]
  RewriteRule ^(.*/)?index\.php$ /%1 [R=301,L]
  ```
  and change `DirectoryIndex index.html index.php` (`:104`) to `index.php index.html`, matching
  `tools/dev-router.php:42`. Putting `.php` first also means the new page wins even if a stale
  `index.html` survives on the host.
- **`tools/verify_live.py`** — assert `/pages/about/index.php` answers 301, not 200.

---

## Phase 1 — The shared contract

`lib/contract.php` is byte-identical across both repos. All of this is a two-repo change.

### 1.1 The route list

```php
// The canonical path of every page, in sitemap order. A page's SEO record is
// keyed by this and nothing else: the editor cannot add, remove or rename one.
const SEO_ROUTES = [
    '/'                              => 'Home',
    '/pages/services/'               => 'Services',
    '/pages/services/cybersecurity/' => 'Cybersecurity',
    // … eleven more, then:
    '/404.html'                      => 'Not found',
];
```

Ancestors are found by prefix match over this map, which is what makes BreadcrumbList derivable.

### 1.2 The model

`const CONTRACT_DOCUMENTS = ['careers', 'contact', 'company', 'seo'];`

Then a `4. SEO and page metadata` block following the contact/company pattern, with
`SEO_TEXT_FIELDS` (there are **no** rich fields — a meta description is plain text, always) and
`seo_defaults()`:

```
site        name, locale, lang, twitter_card, theme_light, theme_dark, share{}
identity    legal_name, founded, price_range, logo{},
            service_types[], knows_about[],
            sameas   → items[] {id, label, url, status}
            hours    → items[] {id, days, opens, closes, status}
crawl       extra_disallow[], status
pages       items[] → {route, title, description, share_title, share_image{},
                       robots, breadcrumb, changefreq, priority}
```

`share{}`, `logo{}` and `share_image{}` are the existing `contract_image_defaults()` record
(`{src, webp, width, height}`), so the signed upload channel from ADR 0019 works unchanged.

Companion functions mirroring the existing ones: `seo_normalise()`, `seo_page_defaults()`,
`seo_link_defaults()`, `seo_hours_defaults()`, `seo_shown()`, and:

```php
const SEO_LISTS = ['sameas' => 'seo_link_defaults', 'hours' => 'seo_hours_defaults'];
```

`seo_normalise()` **reconciles against `SEO_ROUTES`**: every route gets a record, filled from
defaults if absent; any record whose route is not in the constant is dropped. Adding a page to the
constant makes its card appear; removing one makes its record disappear. Nothing to keep in step by
hand.

### 1.3 One control for indexing, not two

`robots` is `index` or `noindex`, and **sitemap membership is derived from it** — an indexable page
is in the sitemap, a noindex page is not. There is no separate "include in sitemap" switch, because
the two can be set to contradict each other, and a noindex URL in a sitemap is a Search Console
warning. One control cannot be misconfigured.

### 1.4 Length limits live in one place

```php
const SEO_TITLE_MAX  = 65;   // audit_pages.py enforces the same number
const SEO_DESC_MIN   = 50;
const SEO_DESC_MAX   = 165;
const SEO_DESC_IDEAL = [150, 160];   // advice, not a refusal
```

`tools/audit_pages.py` stops hard-coding `65` / `50` / `165` and shells out for these, the way
`tools/check_content_model.py:137-157` already shells out for `CONTRACT_BOOKKEEPING`. One number,
two enforcers. `seo_validate()` refuses outside the hard range and *hints* outside the ideal one —
which also settles a live drift: `docs/10-development/frontend/adding-a-page.md:60` says 150–160,
`tools/audit_pages.py:335` enforces 50–165.

### 1.5 Uniqueness moves to where the edit happens

`tools/audit_pages.py:326,337` enforce site-unique title and description. Once those come from
`content/seo.json`, CI's verdict depends on data an operator can change *after* CI passed. So
`seo_validate()` (backend) refuses a duplicate title or description across records, at save time.
CI keeps its check as the backstop; the editor becomes the gate.

### 1.6 Remove `meta` from contact and company

`CONTACT_TEXT_FIELDS['meta']` and `COMPANY_TEXT_FIELDS['meta']` go, along with their
`*_defaults()['meta']` blocks and the `band-meta` fieldsets in the two editor sections. Each gains
one line pointing at the SEO screen.

Two sources for the same tag is a drift generator, and a "dormant fallback" copy is the same thing
with a nicer name. One source.

**`CONTRACT_VERSION` stays at 1**, and this is a judgment call worth stating. The version exists so
a mismatch is a *refusal* rather than a mis-render. Here both directions degrade safely: an old
frontend receiving a contact document without `meta` gets `contact_defaults()['meta']` back from
`contact_normalise()` — the shipped title, not a blank one — and an old frontend receiving a `seo`
document already refuses it with `unknown-document`. Bumping would guarantee a publish outage for
the whole deploy window and protect against nothing. The reasoning goes in the file's comment.

### 1.7 Dispatch

- `contract_normalise()` — add `'seo' => seo_normalise($data)` to the `match`.
- `contract_sanitise()` — add a `seo` branch returning `$data` unchanged. It must exist explicitly
  or the name reaches the `throw`; a no-op that is written down is not the same as a fall-through.
- `api/publish.php` — `'seo' => SEO_FILE` in `PUBLISH_FILES`, plus the `require_once`.
- `python3 tools/check_shared_lib.py --update`, then copy `lib/contract.php` and
  `tools/shared-lib.sha256` to the other repo. Both `dev` pushes must land together or
  `tools/check_shared_repos.py` fails both CI runs.

### 1.8 `seo_defaults()` carries the real content

Not placeholders. If `content/seo.json` is ever missing on the host, every page must still render
the correct title and description from the defaults — exactly as `contact_defaults()` and
`company_defaults()` already do. This is the whole safety net for the deploy.

---

## Phase 2 — The head emitter

### 2.1 `lib/seo.php` — the model (both repos)

`seo_load()`, `seo_page(string $route): array`, `seo_routes()`, `seo_sitemap_entries()`,
`seo_breadcrumb(string $route): array`. The backend copy adds `seo_save()` and `seo_validate()`;
the frontend copy does not, matching the split in `lib/company.php`.

### 2.2 `lib/head.php` — the renderer (frontend only)

```php
seo_head(string $route, array $styles = []): void   // charset → theme-init.js, everything
seo_jsonld(string $route): void                     // base graph + BreadcrumbList
seo_graph(): array                                  // Organization + WebSite + ProfessionalService
seo_lang(): string
```

`seo_graph()` `require_once`s `lib/contact.php` and calls the existing, already-tested
`contact_addresses()` and `contact_points()` for the address and contactPoint arrays. No new code
for the part that fixes defect 1 — one caller instead of sixteen literal copies.

`seo_breadcrumb()` walks `SEO_ROUTES` by prefix, using each ancestor's `breadcrumb` field (falling
back to its `SEO_ROUTES` label). Always in step with the route list, never hand-numbered.

### 2.3 What a page looks like afterwards

```php
<?php
declare(strict_types=1);
require __DIR__ . '/../../lib/head.php';
?>
<!DOCTYPE html>
<html lang="<?= h(seo_lang()) ?>">
<head>
<?php seo_head('/pages/services/', ['pages/services.css']); ?>
<?php seo_jsonld('/pages/services/'); ?>
<script type="application/ld+json">
… the page's own Service/OfferCatalog block, unchanged …
</script>
</head>
```

About 300 head lines become about 8. **The `<body>` is not touched at all.**

For the already-dynamic pages the preamble keeps its existing `require` and load call
(`contact_load()`, `careers_load()`, `company_load()`) and gains `lib/head.php` beside it.

### 2.4 Retire the template

`tools/templates/head.html` and `tools/templates/jsonld-base.html` are deleted — `lib/head.php` is
now the single copy, which is defect 2 closed permanently. `tools/assemble_page.py` emits a
`seo_head()` call and a `SEO_ROUTES` reminder instead of splicing a literal head, and
`tools/templates/README.md` loses the placeholder table (it is in `tools/check_docs.py`'s
`OUTLYING_PROSE`, so the prose is gated).

### 2.5 `sitemap.php`, `robots.php`, `manifest.php`

Three small renderers at the document root, reached by internal rewrite so the URLs never change:

```apache
RewriteRule ^sitemap\.xml$      /sitemap.php  [L]
RewriteRule ^robots\.txt$       /robots.php   [L]
RewriteRule ^site\.webmanifest$ /manifest.php [L]
```

`[L]`, not `[R]` — same URL, rendered content. This is the ADR-0003-consistent answer: no new write
path, and no collision with ADR 0016, which a generated file sitting in the deploy set would have.
`sitemap.php` keeps the `<?xml-stylesheet?>` line so `assets/xsl/sitemap.xsl` still renders it
readably in a browser, sets `Content-Type: application/xml`, and takes `lastmod` from the
document's `updated`. The three static files are deleted.

---

## Phase 3 — The editor

### 3.1 `sections/seo.php` (backend)

Modelled on the company section — one form, many bands. Bands and outline anchors:

| Anchor | Contents |
|---|---|
| `band-site` | name, locale, `lang`, twitter card, theme colours, default share card (upload) |
| `band-identity` | legal name, founded, logo (upload), price range, service types, knows-about |
| `band-social` | `sameAs` list — add / remove / reorder / hide |
| `band-hours` | opening hours list — add / remove / reorder / hide |
| `band-pages` | sixteen cards, one per route: title, description, share title, share image, robots, breadcrumb, changefreq, priority |
| `band-crawl` | extra `robots.txt` disallow lines |
| `band-uploads` | the unused-upload sweep |

Reuses the whole existing vocabulary: `admin_band_head()`, `admin_card_head()`,
`admin_status_field()`, `admin_image_fields()`, `admin_uploaded_files()`, `admin_send_picture()`,
`upload_accept()`.

The page cards get `admin_card_head()` **without** the up/down/remove buttons — the route list is
fixed. Only `sameas` and `hours` are true repeatable lists, and they use the proven
`name="do" value="sameas-up:3"` mechanics.

**Async is not optional and not new work.** The section inherits it entirely from the shell by
providing four hooks: `data-async` on the `<form>`, the `id="admin-body"` / `id="admin-main"`
regions from `admin_head()`, `data-editor` where a rich field would be (there are none here), and
the `"<band>-<verb>:<index>"` naming on every `name="do"` button so `admin-forms.js` can find the
new row. Nothing in this editor reloads the page — links swap through `admin-swap.js`, form posts
through `admin-forms.js`, confirmations through `admin-dialog.js`, results appear as sliding toasts
through `admin-toast.js`. That is the standing requirement for every page of the admin, present and
future, and this section is built to it from the first line.

**`max_input_vars` matters here.** Sixteen cards × eight fields, plus the site and identity bands,
is the largest form in the admin. `admin_form_tail()` and the `admin_form_truncated()` guard are
mandatory, as in the company section — not optional as in the contact one.

### 3.2 `public/assets/js/admin-seo.js` (backend, new, small)

Live character counters on title and description, coloured against `SEO_TITLE_MAX` /
`SEO_DESC_MIN` / `SEO_DESC_MAX` / `SEO_DESC_IDEAL`, and a search-result preview beside each page
card. Registers on `window.Tech4Time` and boots through `admin-init.js`'s `begin()`. Pure
decoration: with JavaScript off the fields still save and the server still validates. External file
— the CSP is `script-src 'self'`.

### 3.3 Registry and shell (backend)

- `lib/admin.php` — an `ADMIN_SECTIONS` row: `'seo' => ['label' => 'SEO & Metadata', 'icon' =>
  'globe', 'desc' => 'Titles, descriptions and how the site appears in search', 'view' => '']`.
  Registry order is rail order; after `company`, before `account`.
- `lib/admin.php` — `'seo'` into `ADMIN_PAGE_SECTIONS`.
- `sections/overview.php` — a `$cards` entry. Nothing enforces this; the overview would silently
  omit it.
- `tools/build_deploy_set.py` `REQUIRED` — `sections/seo.php`, `lib/seo.php`,
  `public/assets/js/admin-seo.js`.
- `.gitignore` — `content/seo.json.bak`.

---

## Phase 4 — The conversion

Every page still `.html` becomes `.php`. The body is untouched; only lines 1–2 and the head change.

**The non-visual proof, which is the whole safety property:**

1. Before touching anything, render all sixteen pages and save the output — `tools/audit_pages.py`'s
   existing `render_php()` for the dynamic pages, plain reads for the rest.
2. Extract the current head values from those files with a throwaway script in a scratch directory,
   and write `content/seo.json` from them. The seed is committed; the extractor is not — `tools/` is
   never deployed and a dead one-off would only make `tools/check_docs.py` demand documentation for
   it.
3. Convert.
4. Render again. Whitespace-normalised, the two sets must be identical. **If the seed is right,
   nothing a visitor or a crawler sees has changed.**

`404.html` → `404.php` needs `http_response_code(404)` as its first statement (correct both as an
`ErrorDocument` and on a direct request), and `.htaccess:110` becomes `ErrorDocument 404 /404.php`.

`content/seo.json` is committed in **both** repos and seeds a fresh host through `seed_source()`,
which falls back to `content/<name>.json` when there is no `deploy/seed/` special — the same
reasoning that already covers contact and company. No `deploy/seed/seo.json`: a site's titles have
no meaningful empty state.

---

## Phase 5 — Teach every check

| Check | Change |
|---|---|
| `tools/check_content_model.py` (both) | `seo` into `COVERED_ELSEWHERE`, naming the round-trip tests — the editor loops over `SEO_ROUTES`, so a `SUBJECTS` entry would exempt exactly the fields most likely to drift. Also drop `meta.*` from contact's expectations. |
| `tools/audit_pages.py` | Root glob widened (Phase 0); read the three length constants from PHP; render `sitemap.php` / `robots.php` in `check_admin_is_hidden()` instead of reading deleted files; **new:** assert each page's rendered canonical equals its own directory URL, which catches a copy-pasted `seo_head()` route argument. |
| `tools/test_seo_admin.py` (backend, new) | Round-trips every field; add / remove / reorder / hide on `sameas` and `hours`; a duplicate title is refused; an over-length title is refused; a noindex page leaves the sitemap. |
| `tools/test_publish.py` (frontend) | A "the seo document travels the same road" group, plus the marker walk `COVERED_ELSEWHERE` points at. |
| `tools/test_sitemap.py` (frontend, new) | `sitemap.php` is well-formed XML; covers exactly the indexable routes and no others; `robots.php` names the sitemap; `manifest.php` is valid JSON; all three carry the right `Content-Type`. |
| `tools/verify_live.py` (both) | `/robots.txt`, `/sitemap.xml`, `/site.webmanifest` still 200 with the right type; every `SEO_ROUTES` entry answers 200; `/pages/about/index.php` → 301. |
| `tools/check_admin_a11y.py` (backend) | Crawl `?s=seo` — it becomes the largest form in the admin and the likeliest place for a focus or 320px failure. |
| `tools/build_deploy_set.py` (both) | Frontend `UPLOAD`: swap `index.html`→`index.php`, `404.html`→`404.php`, `robots.txt`→`robots.php`, `sitemap.xml`→`sitemap.php`, `site.webmanifest`→`manifest.php`. `REQUIRED`: all sixteen pages, the three renderers, `lib/seo.php`, `lib/head.php`. Backend as in 3.3. |
| `tools/check_shared_markup.py`, `tools/inject_icons.py`, `tools/propagate_shared.py` | Root glob widened (Phase 0); `head.html` / `jsonld-base.html` references removed. |
| `tools/check_docs.py` (both) | Passes only once Phase 6 is done. |
| Browser suites | Every converted page now renders through PHP: re-run `tools/test_motion.py`, `tools/test_nav.py`, `tools/test_theme.py`, `tools/check_focus.py`, `tools/check_hover.py`, `tools/check_dark_mode.py`, `tools/check_responsive.py`. |

---

## Phase 6 — Documentation

**New, and the point of it:** `docs/40-reference/seo.md` in both repos — the doc that finally *owns*
this subject. Today the knowledge is scattered across `shared-markup.md`, `adding-a-page.md`,
`content-schemas.md` and `tools.md`, and `docs/README.md`'s ownership table has no row for page
metadata in either repo. It gets one.

**New:** `docs/90-decisions/0020-page-metadata-is-content.md`, both repos (0019 is the highest in
use as of 2026-08-29 — re-check). It records: the head is emitted once, not pasted sixteen times;
routes are code and content is content; sitemap and robots are rendered, not stored; and why the
body-mirroring schemas stay in markup until their page's body is managed.

**Frontend, rewritten or amended:** `docs/00-orientation/repository-map.md` (the pages are now
`.php`; the three renderers), `docs/40-reference/content-schemas.md` (the `seo` schema; `meta` gone
from contact and company), `docs/10-development/where-to-change-things.md` (a title or description
now means the admin), `docs/10-development/frontend/adding-a-page.md` (**rewritten** — a page is
`.php`, calls `seo_head()`, gets one `SEO_ROUTES` line, and *no longer needs a manual `sitemap.xml`
edit*; also corrects the 150–160 vs 50–165 drift), `docs/10-development/frontend/shared-markup.md`
(`head.html` is gone), `docs/10-development/server-side/libraries.md`,
`docs/10-development/server-side/publish-api.md`, `docs/10-development/testing.md`,
`docs/40-reference/tools.md`, `docs/40-reference/security-model.md` (three new rewrites),
`docs/20-deployment/routine-deploys.md`, `CLAUDE.md`.

**Backend:** `docs/00-orientation/repository-map.md`, `docs/40-reference/content-schemas.md`,
`docs/10-development/where-to-change-things.md`, `docs/30-operations/content-runbook.md` (how to use
the SEO editor, including what noindex actually does),
`docs/10-development/server-side/adding-an-editor.md`,
`docs/10-development/server-side/libraries.md`, `docs/10-development/testing.md`,
`docs/40-reference/tools.md`, `CLAUDE.md`.

---

## Verification

Backend first for the shared contract, then frontend — `tools/check_shared_repos.py` clones the
sibling and fails until both halves match.

```bash
# backend
php -l on every changed file
python3 tools/check_shared_lib.py      python3 tools/check_content_model.py
python3 tools/check_css.py             python3 tools/check_contrast.py
python3 tools/check_secrets.py         python3 tools/check_docs.py
python3 tools/build_deploy_set.py --check
python3 tools/test_admin_auth.py       python3 tools/test_seo_admin.py
python3 tools/test_contact_admin.py    python3 tools/test_company_admin.py
python3 tools/test_careers_admin.py    python3 tools/test_upload.py
python3 tools/test_publish_client.py   python3 tools/test_store.py
python3 tools/test_qr.py

# frontend
python3 tools/check_contrast.py        python3 tools/check_content_model.py
python3 tools/check_css.py             python3 tools/check_shared_repos.py
python3 tools/inject_icons.py --check  python3 tools/check_secrets.py
python3 tools/check_shared_markup.py   python3 tools/check_docs.py
python3 tools/audit_pages.py           python3 tools/check_shared_lib.py
python3 tools/build_deploy_set.py --check
python3 tools/test_publish.py          python3 tools/test_publish_asset.py
python3 tools/test_sitemap.py          python3 tools/test_contact_handler.py
python3 tools/test_store.py

# browser (Firefox + geckodriver; leave processes behind if interrupted)
frontend: test_motion.py test_nav.py test_theme.py check_hover.py
          check_dark_mode.py check_responsive.py check_focus.py
backend:  test_editor.py test_admin_forms.py check_admin_a11y.py
pkill firefox geckodriver
```

**The non-visual proof** — the sixteen rendered pages, before and after, whitespace-normalised,
byte-identical. This is the check that matters most and it is not optional.

**End to end, by hand, both halves running:**

```bash
python3 tools/serve.py                                          # frontend :8000
T4T_PUBLISH_URL=http://localhost:8000/api/publish.php \
  python3 tools/serve.py 8001                                   # backend
```

In the admin: change the home page title, set a page to noindex, upload a new share card, add a
`sameAs` link, edit the opening hours. After each, confirm at `localhost:8000` that the `<head>`
changed, that `/sitemap.xml` gained or lost the URL, and that the Organization graph carries the new
link — **with JavaScript disabled as well as on**, and confirm the admin never reloaded.

Then validate the output externally: the rendered JSON-LD through Google's Rich Results Test, the
share card through the Facebook and LinkedIn debuggers, and the sitemap against the schema.

**Deploy order — frontend first.** The new frontend renders from `content/seo.json` and no longer
reads contact's `meta`, while still accepting `careers` / `contact` / `company` from the old
backend. There is no moment when either half needs something the other has stopped sending. Push
both `dev` branches together (or `tools/check_shared_repos.py` fails both), then merge frontend
`main`, let it deploy and verify, then backend `main`. `content/` is seeded with
`--ignore-existing`, so `seo.json` — a file that does not yet exist on the host — is seeded
correctly by that mechanism.

Afterwards: `tools/verify_live.py` against both hosts, and one real edit through
`admin.tech4time.bd` landing on `tech4time.bd`.

---

## Order of work

1. Phase 0 — the four globs, the `index.php` rewrite, `DirectoryIndex` order
2. Phase 1 — the contract in both repos, the dispatch, the digests
3. Phase 4 steps 1–2 — render every page, extract and commit `content/seo.json`
4. Phase 2 — `lib/seo.php`, `lib/head.php`, the three renderers
5. Phase 4 steps 3–4 — convert what is left, prove non-visual
6. Phase 3 — the editor, then `admin-seo.js`
7. Phase 1.6 — remove the `meta` bands from contact and company
8. Phase 5 — the checks
9. Phase 6 — the documentation and the ADR
10. Merge, deploy frontend then backend, verify live
