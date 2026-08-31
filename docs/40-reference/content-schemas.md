# Content schemas

**Applies to:** both

The two JSON files the dynamic pages render from, field by field.

**The defaults functions are the definition of the shape**, not these files —
`careers_load()` in `lib/careers.php`, and `contact_defaults()` / `contact_office_defaults()` /
`contact_reach_defaults()` in `lib/contact.php`. A JSON file is one instance of a shape, and an
optional field that happens to be absent from it is still a field.

Changing a shape means changing three things together — the model, the form and the renderer.
[content-model.md](../10-development/server-side/content-model.md).

---

## `content/careers.json`

```json
{
  "cv_form_url": "https://forms.gle/…",
  "updated": "2026-08-23T02:10:00+00:00",
  "jobs": [ { … } ]
}
```

| Field | Type | |
|---|---|---|
| `cv_form_url` | string | one link for the whole page, for speculative applications |
| `updated` | string | ISO 8601, written on save. Bookkeeping — nothing renders it |
| `jobs` | array | job posts, **in display order** |

### A job

| Field | Type | |
|---|---|---|
| `id` | string | generated on creation, never typed |
| `title` | string | the role |
| `employment_type` | string | full-time, part-time, contract… |
| `work_arrangement` | string | on-site, hybrid, remote |
| `location` | string | |
| `salary` | string | free text — may be a range or blank |
| `posted` | string | date |
| `closes` | string | date; drives `careers_open_jobs()` |
| `status` | string | `shown` or hidden |
| `apply_url` | string | the role's own application form — applications never post to this site |
| `about` | rich text | the description |
| `responsibilities` | rich text | |
| `requirements` | rich text | |
| `must_have` | rich text | |
| `certifications` | rich text | |
| `offers` | rich text | what the company offers |

Rich-text fields go through `rt_sanitise_html()` on save. `careers_job_posting()` emits `JobPosting`
structured data from these.

---

## `content/contact.json`

```json
{
  "updated": "…",
  "footer_synced": "…",
  "meta":    { "title": "…", "description": "…", "share_title": "…" },
  "hero":    { "title": "…", "subtitle": "…" },
  "form":    { "title": "…", "lead": "…", "subject_hint": "…", "note": "…",
               "service_types": [] },
  "reach":   { "status": "shown", "title": "…", "items": [] },
  "offices": { "status": "shown", "eyebrow": "…", "title": "…", "lead": "…",
               "items": [] }
}
```

| Field | |
|---|---|
| `updated` | ISO 8601, written on save. Bookkeeping |
| `footer_synced` | the fingerprint of the contact details as last pushed into the pages' footers. Drives the drift banner — [shared-markup.md](../10-development/frontend/shared-markup.md) |
| `meta` | `<title>`, meta description, Open Graph title |
| `hero` | the page's heading and subheading |
| `form` | the enquiry form's copy, and `service_types` — the subject options offered |
| `reach` | direct contact methods. `status` switches the whole band off |
| `offices` | the office list. `status` switches the whole band off |

**A band's `status` and a row's are separate switches, and both are honoured.**
`contact_shown_reach()` and `contact_shown_offices()` answer for both, which is why the structured
data cannot advertise a band the page does not draw. Only these two bands have a switch: the banner
and the enquiry form do not, because a contact page with no way to make contact is not a page
anybody meant to publish.

### A reach item

| Field | |
|---|---|
| `icon` | an icon name from `ADMIN_ICONS` |
| `label` | "Phone", "Email", … |
| `type` | `phone`, `email`, `url` or `text` — decides how `contact_reach_href()` links it |
| `values` | array of strings; several numbers under one label |
| `text` | free text, when `type` is `text` |
| `status` | `shown` or `hidden` — `contact_shown_reach()` filters on it |

### An office

| Field | |
|---|---|
| `id` | generated on creation, never typed |
| `name` | the city or office name |
| `flag` | a slug naming a flag that ships with the public site — `bangladesh`, `belgium`, `malaysia`. Cannot grow without a deploy, which is what `image` is for |
| `image` | an uploaded flag: `src`, `webp`, `width`, `height`, the same record a company logo uses. **Wins over `flag` when set.** Paths are checked against `CONTRACT_IMAGE_ROOTS` |
| `address` | |
| `phones` | array of strings |
| `hours` | opening hours |
| `languages` | array of strings |
| `status` | `shown` or hidden — `contact_shown_offices()` filters on it |
| `schema` | `street`, `locality`, `region`, `postal_code`, `country` — for `PostalAddress` structured data |

`contact_page_schema()` emits `ContactPage` and `PostalAddress` from these, and so does the
`Organization` graph at the top of `pages/contact/index.php` — `contact_addresses()` and
`contact_points()` are spliced into it. That block used to write the three offices out by hand,
which meant hiding an office took its card off the page and left its address being advertised to
Google. `contact_points()` emits one point per **phone**, not per office: an office listing three
numbers had two of them reachable on the page and invisible to a search engine.

---

## `content/about.json`

```json
{
  "updated":  "…",
  "revision": 0,
  "meta":        { "title": "…", "description": "…", "share_title": "…" },
  "hero":        { "title": "…", "subtitle": "…" },
  "story":       { "status": "shown", "items": [] },
  "specialties": { "status": "shown", "title": "…", "interval": 10000, "items": [] },
  "whyus":       { "status": "shown", "title": "…", "items": [] },
  "cta":         { "status": "shown", "title": "…", "label": "…", "href": "…", "icon": "…" }
}
```

| Field | |
|---|---|
| `updated` · `revision` | bookkeeping — see *Rules that apply to both* |
| `meta` | `<title>`, meta description, Open Graph title |
| `hero` | the page's heading and subheading. No `status`: a page with no title is not a page with a section switched off |
| `story` | the image-and-prose sections. `status` switches the whole run of them off |
| `specialties` | the slideshow. `interval` is milliseconds, clamped 2000–60000 |
| `whyus` | the grid of short reasons |
| `cta` | the closing band and its one button |

**`story` has no `title` of its own.** Every heading on that part of the page belongs to a row,
which is what lets a section be added, reordered or hidden on its own.

### A story section

| Field | |
|---|---|
| `id` | minted from the heading; also the `<h2>`'s id and what the section's `aria-labelledby` points at |
| `heading` | the section's `<h2>` |
| `body` | sanitised HTML — one or two `<p>`. The only rich field on this page |
| `layout` | `photograph`, or `logo` for the light/dark wordmark lockup |
| `side` | `left` or `right`; `right` renders `.about-split--reverse` |
| `alt` | what the picture shows. Required even for `logo`, because the lockup is what gets announced |
| `image` | `{ src, webp, width, height }`. The picture, or the light half of a logo pair |
| `image_dark` | the dark half of a logo pair. Only `layout: "logo"` draws it |
| `status` | `shown` or `hidden` |

**A `logo` row draws a pair, and each half falls back on its own:**

| uploaded | light mode | dark mode |
|---|---|---|
| nothing | the shipped lockup | the shipped lockup |
| `image` only | the upload | **the same upload** |
| both | `image` | `image_dark` |

The middle row is the one worth explaining. Falling back to the shipped *dark* logo there would
put the old mark beside the new one, which is the one outcome nobody wants from "we changed our
logo". A new light logo may read poorly on a dark background; the previous brand does not read
poorly, it is wrong. The editor says so and offers the second slot.

**This is the logo in that section and nowhere else.** The header, the footer, the browser tab,
the social share card and `Organization.logo` in the structured data are shared markup and build
artefacts, not content, and still need a developer and a deploy.

A picture record is kept rather than cleared on a row whose layout is not `logo`, so switching back
does not lose it — which is also why `about_images()` counts both halves when the unused-upload
sweep asks what is in use.

**The light and shaded backgrounds alternate by position, not by a stored field.** It is a rhythm
down the page, so a reordered or added section keeps the stripe instead of carrying a stale copy
of it.

### A speciality, and a why-us card

The same shape.

| Field | |
|---|---|
| `id` | minted from the title |
| `icon` | a name from `ABOUT_ICONS`. Anything else is dropped on save and on receipt |
| `title` | the card's heading |
| `text` | one paragraph, plain text |
| `status` | `shown` or `hidden` |

**The specialities repeat the six service names that also appear on the home page and
`/pages/services/`.** The home page now has a content source of its own, so the taxonomy lives in
**three** places — `content/about.json`, `content/home.json` and `pages/services/index.html` — with
no owner. The home page's `Service` ItemList used to be a fourth and is now generated from
`services.items`, so that copy is gone. The remaining three are to be reconciled when the services
page comes under management; nothing enforces that they agree today.

**About's why-us cards and the company profile's `principles` express overlapping ideas** — Robust
Security and Security First, Client-Centric Approach and Client Partnership — and are deliberately
separate: different wording, different icons, different markup, on different pages. Editing one is
worth a look at the other.

## `content/home.json`

```json
{
  "updated":  "…",
  "revision": 0,
  "meta":         { "title": "…", "description": "…", "share_title": "…" },
  "hero":         { "title": "…", "accent": "…", "cta_label": "…", "cta_href": "…" },
  "badges":       { "status": "shown", "items": [] },
  "tags":         { "status": "shown", "items": [] },
  "terminal":     { "status": "shown", "title": "…", "summary": "…", "items": [] },
  "capabilities": { "status": "shown", "title": "…", "lead": "…", "items": [] },
  "services":     { "status": "shown", "eyebrow": "…", "title": "…", "lead": "…",
                    "schema_name": "…", "schema_description": "…", "items": [] },
  "destinations": { "status": "shown", "eyebrow": "…", "title": "…", "lead": "…", "items": [] },
  "cta":          { "status": "shown", "icon": "…", "title": "…", "text": "…",
                    "label": "…", "href": "…" }
}
```

| Field | |
|---|---|
| `updated` · `revision` | bookkeeping — see *Rules that apply to both* |
| `meta` | `<title>`, meta description, Open Graph and Twitter titles |
| `hero` | the page's only `<h1>`, the phrase drawn in the accent colour, and one button. No `status`: a front page with no heading is not a page with a section switched off |
| `badges` · `tags` | `{ id, icon, label, status }` — the pills under the heading |
| `terminal` | the decorative SOC console. `summary` is the one line a screen reader is given instead of it |
| `capabilities` | `{ id, icon, title, status }` — the technical domains |
| `services` | `{ id, icon, title, text, href, label, link_hint, status }` |
| `destinations` | the same plus `alt`, `image{}` and `image_dark{}` |
| `cta` | the closing panel. `title` holds a newline, which becomes the `<br>` |

**Six lists, the most of any document here.** `HOME_LISTS` names them and `home_normalise()` drives
itself off that, so a seventh is added by being added there.

**A terminal line is `{ id, kind, tone, prompt, text, status }`.** `kind` is `command` or `output`;
`tone` is `plain`, `success` or `alert` and is ignored on a command. **The blinking caret is not a
row** — it is emitted after the last line by `home_terminal_lines()`, so it cannot be deleted,
duplicated or stranded in the middle.

**`hero.accent` is a phrase, not markup.** The renderer wraps its first exact occurrence in the
title. It has to match exactly, capitals included; the editor refuses a save where it does not, and
the page falls back to a plain heading if one ever gets through.

**`link_hint` is the visually-hidden tail on a card's link** — "for Cybersecurity". It is a field
rather than something derived from the title, because the wording differs from it: the card titled
"IT Consultancy & Training" reads "and", not "&".

**`schema_name` and `schema_description` are the only fields here nobody sees on the page.** They
name the `Service` ItemList in the `<head>`. Each service's own entry is generated from its card, so
there is no second copy of the six to keep true — there was, and it had drifted.

**Both halves of a destination picture are counted by `home_images()`**, so an unused-file sweep
cannot offer to delete a dark image the moment it is uploaded.

## Which pictures get a light/dark pair, and which do not

Asked and settled on 2026-08-31. Every managed picture on the site, and why it is or is not a pair:

| Page | What | n | Treatment | Pair? |
|---|---|---|---|---|
| Home | Get to Know Us cards | 3 | white plate, both modes | **yes** |
| About | photograph sections | 4 | white plate, both modes | **yes** |
| About | the logo section | 1 | themed surface | **yes** |
| Company | client logos | 9 | white plate, both modes | no |
| Company | technology logos | 50 | white plate, both modes | no |
| Company | journey photographs | 3 | no plate, full-bleed | no |

**The three that are pairs are line art or a wordmark** — dark ink that needs a light ground, kept on
`--artwork-plate` in both modes. If the company ever has artwork drawn for a dark page, the slot is
there. **With nothing uploaded the markup is exactly what it was before the slot existed**: one
`<picture>`, no theme-swap classes, no second element. The page does not pay for an unused feature.

**The client and technology logos are deliberately NOT pairs.** They are other companies' brand
marks and the white plate is a legibility guarantee, not a default — several client marks are close
to solid black and vanished into the dark theme's elevated surface at about 1.4:1 before the plate
was introduced. A dark slot there would invite somebody to break that guarantee with artwork this
company does not own. One generic, consistent presentation is the right answer for a logo wall.

**The journey photographs are NOT pairs either, for a different reason.** They have no plate at all
— `object-fit: cover`, full-bleed — and they are photographs. A photograph carries its own content
edge to edge and reads correctly in either mode, so a second version would be two copies of the same
picture. Full-bleed is also simply how they look best.

The rule, stated once: **a picture gets a second slot when the page has to supply its background.
It does not when the picture is its own background, or when a fixed plate is a guarantee rather
than a default.**

## Rules that apply to both

**Written atomically.** `store_write()` writes a temp file and renames it over the target, keeping
one `.bak`. A visitor loading the page mid-save reads either the old file or the new one.

**Rich text is sanitised on save** by `rt_sanitise_html()`, which writes new tags from an allow-list
rather than passing anything through. There is no `style` attribute — the CSP blocks inline styles,
so alignment is a class from a fixed list.

**Everything is escaped on output** with `h()`, regardless of having been sanitised on the way in.

**Bookkeeping fields** — `updated`, `footer_synced` — are exempt from the content-model check in
both directions. Nothing renders them and the form does not write them.

**Ids are generated, not typed.** `careers_slug()` and `contact_slug()` make them.

**`.htaccess` redirects `index.php` as well as `index.html`** to the directory that holds it. It
covered only `.html` until the home page became PHP, and `https://tech4time.bd/pages/about/index.php`
answered 200 — a second URL for a page that already had one, which is the duplicate-content problem
those rules exist to prevent. Do not simplify the rule back to one extension.

---

## On the host, these files are the real data

Written by people through `https://admin.tech4time.bd/`. **Never upload them to a live server** —
[routine-deploys.md](../20-deployment/routine-deploys.md).

The repository's copies are development data, kept deliberately rich because an empty file exercises
no renderer. [environments.md](../20-deployment/environments.md)
