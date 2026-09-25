# Content schemas

**Applies to:** both

The **thirteen** JSON files the dynamic pages render from, field by field — `CONTRACT_DOCUMENTS` is
the list. Ten of them are a page or a set of pages, and `content/services.json` carries seven on
its own; the other three are on **every** page — `seo` for the `<head>`, `chrome` for the header,
footer and dock, and `settings` for the mark, the icons and the brand colours.

**The defaults functions are the definition of the shape**, not these files — `careers_defaults()`,
`contact_defaults()`, `company_defaults()`, `about_defaults()`, `home_defaults()`,
`services_defaults()`, `certifications_defaults()`, `branding_defaults()`, `privacy_defaults()`,
`seo_defaults()` and `chrome_defaults()`, all in **`lib/contract.php`**, which the two repositories
hold byte-identical. (They lived in `lib/careers.php` and `lib/contact.php` until the repository split;
the prose here said so for some time after it stopped being true.) A JSON file is one instance of a
shape, and an optional field that happens to be absent from it is still a field.

Changing a shape means changing three things together — the model, the form and the renderer.
[content-model.md](../10-development/server-side/content-model.md).

---

## `content/careers.json`

```json
{
  "cv_form_url": "https://forms.gle/…",
  "updated": "2026-08-23T02:10:00+00:00",
  "meta": { … },
  "jobs": [ { … } ]
}
```

| Field | Type | |
|---|---|---|
| `cv_form_url` | string | one link for the whole page, for speculative applications |
| `updated` | string | ISO 8601, written on save. Bookkeeping — nothing renders it |
| `meta` | object | everything the `<head>` says about this page — see [The `meta` band](#the-meta-band-on-every-document). This document **gained** one: the careers page's title and description were literal strings in the page file, and the only two on the site nobody could change |
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
  "meta":    { … },
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
| `meta` | everything the `<head>` says about this page — see [The `meta` band](#the-meta-band-on-every-document) |
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
| `schema` | `street`, `locality`, `region`, `postal_code`, `country` — for `PostalAddress` structured data — plus `latitude` and `longitude`, which are the only pair in this document **checked** rather than trimmed: `contact_coordinate()` refuses anything outside ±90 / ±180, anything with a stray character, and scientific notation, and both halves are needed or no `geo` is emitted. Stored as strings so `23.80` keeps its trailing zero |

**`contact_images()` counts an office picture when the unused-upload sweep asks what is in use** —
and it did not exist until it had to. `contract_images()` put `contact` in the arm that returns the
`meta` band alone, so an office photograph was claimed by nothing: every other screen's sweep
counted it unused and offered to delete a picture that was on this page. The `flag` slug is
deliberately not counted, because it names a file that ships with the site rather than one anybody
uploaded.

`contact_page_schema()` emits `ContactPage` and `PostalAddress` from these, and so does the
`Organization` graph at the top of `pages/contact/index.php` — `contact_addresses()` and
`contact_points()` are spliced into it. That block used to write the three offices out by hand,
which meant hiding an office took its card off the page and left its address being advertised to
Google. `contact_points()` emits one point per **phone**, not per office: an office listing three
numbers had two of them reachable on the page and invisible to a search engine.

---

## `content/milestones.json`

```json
{
  "updated":  "…",
  "revision": 0,
  "meta":     { … },
  "hero":     { "title": "…", "subtitle": "…" },
  "timeline": {
    "status":  "shown",
    "eyebrow": "…",
    "title":   "…",
    "lead":    "<p>…</p>",
    "items":   [ { "id": "…", "year": "2024", "title": "…", "text": "…",
                   "status": "shown" } ]
  }
}
```

| Field | Kind | Notes |
|---|---|---|
| `timeline.lead` | rich text | the only markup in the document |
| `items[].year` | text | **free text, not a number.** The editor asks for `2024` or `2024–2025` and refuses anything else; the contract sees hand-edited files too, and `milestones_recent()` keeps a row whose year it cannot read rather than dropping it |
| `items[].title` `items[].text` | text | what happened, and a sentence about it |
| `items[].status` | `shown` \| `hidden` | hiding is not deleting |

**One document, two pages.** `/pages/milestones/` renders the whole timeline;
`/pages/company-profile/` renders the most recent `MILESTONES_WINDOW` (5) years of the same list
and links there for the rest. Both load `assets/css/pages/milestones.css`, so there is no second
copy of the styling either.

**The company document still carries a `milestones` band.** It is deprecated and deliberately still
in the contract: `milestones_load()` reads through to it — heading, introduction and entries —
until this document has been saved once, so no deploy can land in a state where the company profile
has no timeline. The condition is `revision === 0`, not a missing file and not an empty list; see
[`milestones.php`](../10-development/server-side/libraries.md#milestonesphp). Removing that band
would change the meaning of every `content/company.json` already written, which is what
`CONTRACT_VERSION` exists to stop.

**The rows moved without being rewritten.** `company_milestone_defaults()` and
`milestones_entry_defaults()` declare the same five fields, in the same order, with the same names.
A field added to one and not the other is a field lost on the way across.

---

## `content/about.json`

```json
{
  "updated":  "…",
  "revision": 0,
  "meta":        { … },
  "hero":        { "title": "…", "subtitle": "…" },
  "story":       { "status": "shown", "items": [] },
  "specialties": { "status": "shown", "title": "…", "interval": 10000, "items": [] },
  "whyus":       { "status": "shown", "title": "…", "items": [] },
  "accreditations": { "status": "hidden", "title": "…", "items": [] },
  "cta":         { "status": "shown", "title": "…", "label": "…", "href": "…", "icon": "…" }
}
```

| Field | |
|---|---|
| `updated` · `revision` | bookkeeping — see *Rules that apply to both* |
| `meta` | everything the `<head>` says about this page — see [The `meta` band](#the-meta-band-on-every-document) |
| `hero` | the page's heading and subheading. No `status`: a page with no title is not a page with a section switched off |
| `story` | the image-and-prose sections. `status` switches the whole run of them off |
| `specialties` | the slideshow. `interval` is milliseconds, clamped 2000–60000 |
| `whyus` | the grid of short reasons |
| `accreditations` | the wall of certification badges. **The only band that ships `hidden`** — it has no shipped copy behind it, and a heading over an empty grid is a gap rather than a section |
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
| `image` | `{ src, webp, width, height, srcset, webp_srcset }`. The picture, or the light half of a logo pair |
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

**This is the logo in that section and nowhere else.** The header's and the footer's are fields of
`content/chrome.json`, edited on the **Header & Footer** screen; the browser tab, the social share
card and `Organization.logo` in the structured data are build artefacts, not content, and still need
a developer and a deploy.

A picture record is kept rather than cleared on a row whose layout is not `logo`, so switching back
does not lose it — which is also why `about_images()` counts both halves when the unused-upload
sweep asks what is in use.

**The light and shaded backgrounds alternate by position, not by a stored field.** It is a rhythm
down the page, so a reordered or added section keeps the stripe instead of carrying a stale copy
of it.

### An accreditation

The same shape as a client logo on the company profile page, with one field more.

| Field | |
|---|---|
| `id` | minted from the name |
| `name` | the standard as it should read — `ISO/IEC 27001:2022`. **Never optional**, whichever way `caption` is set: it is either printed under the badge or it is the badge's `alt`, and a row without one is a picture nothing can describe |
| `image` | `{ src, webp, width, height, srcset, webp_srcset }`, stored at the `about.accreditations` slot |
| `caption` | `shown` or `hidden` — whether the name is **printed** under the badge |
| `status` | `shown` or `hidden` — whether the badge appears at all |

**`caption` and `status` are two switches because they answer different questions.** A badge whose
artwork already reads "ISO/IEC 27001" does not want the words repeated beneath it, and that is not
the same wish as wanting the badge gone.

**Hiding the caption never takes the name away.** It moves it: with the caption shown the `<img>`
takes `alt=""` and the printed name does the describing, so a screen reader does not hear it twice;
with the caption hidden the `<img>` carries the name as its `alt`, which is what the clients wall
does. `tools/test_publish.py` asserts both directions.

**The plate is square and the mark fills it.** `aspect-ratio: 1` rather than a minimum height, and
the `<img>` takes the whole square minus its padding instead of a fixed 56px. A certification mark
*is* the content of its tile — unlike a client logo, which is one of thirty on a wall — so it is
drawn 272px across on a desktop where the clients wall draws 126.

**The grid counts its columns rather than fitting them: two, then three at `48em`, then four at
`64em`.** `.clients` uses `auto-fit` with a 10rem minimum and lands six or seven across, which is
right for thirty company logos and wrong for a handful of certifications.

`auto-fit` cannot express "four across". The track *minimum* is the only thing that decides the
column count, so raising it to get four on a desktop also drags the mid-range down to one column —
measured, a 16rem minimum left a single tile with 233px of empty row beside it at 580px wide.

Counting the columns fixes a third thing for free. An explicit `repeat(N, 1fr)` creates all N tracks
whether or not there are badges to fill them, so **one badge is one track wide rather than the whole
row** — which is what `auto-fit`'s track collapsing could not do, and why no tile needs a
`max-width`. Without that, three badges drew at 318px and one at about 1200px, and a width that
depends on how many rows the editor has added is not a width.

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
`/pages/services/`.** All three now have a content source, so the taxonomy lives in **three**
documents — `content/about.json`, `content/home.json` and `content/services.json` — and still has
no owner. The home page's `Service` ItemList used to be a fourth and is generated from
`services.items`; each detail page's `Service` block used to be another and is now generated from
its own layers.

Bringing the services pages under management did not reconcile the three, and deliberately so: they
are three different summaries at three different lengths, and the known disagreement is real
content rather than drift — `content/home.json` calls one service *Human Resource Provision* where
the services index calls it *HRaaS*. Nothing enforces that they agree, and a rename in one is still
worth a look at the other two.

**About's why-us cards and the company profile's `principles` express overlapping ideas** — Robust
Security and Security First, Client-Centric Approach and Client Partnership — and are deliberately
separate: different wording, different icons, different markup, on different pages. Editing one is
worth a look at the other.

## `content/home.json`

```json
{
  "updated":  "…",
  "revision": 0,
  "meta":         { … },
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
| `meta` | everything the `<head>` says about this page — see [The `meta` band](#the-meta-band-on-every-document) |
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

## `content/services.json`

**One document, seven pages.** The services index *and* every service page under it. That is forced
rather than chosen: a seventh service has to be addable from the editor, and `CONTRACT_DOCUMENTS` is
a constant in code — so a service cannot be its own document and has to be a row in a list.

```json
{
  "updated":  "…",
  "revision": 0,
  "meta":     { … },
  "hero":     { "title": "…", "subtitle": "…" },
  "nav":      { "status": "shown", "eyebrow": "…", "title": "…", "lead": "…", "items": [] },
  "blocks":   { "status": "shown", "items": [] },
  "ossf":     { "status": "shown", "eyebrow": "…", "title": "…", "lead": "…", "items": [] },
  "cta":      { "status": "shown", "title": "…", "text": "…",
                "label": "…", "href": "…", "icon": "…" },
  "services": { "items": [] }
}
```

A `nav` row is `{ id, block, icon, title, text, status }` — `block` names the **block** it scrolls
to, which is not the service's slug: the HRaaS block is `id="hraas"` and its service is
`hr-solutions`.

A `blocks` row is `{ id, service, icon, title, intro, status, groups[], buttons[] }`, where a group
is `{ id, title, width, items[], status }` and a button is
`{ id, label, href, icon, style, status }`. `service` names the row in `services.items` the block
belongs to.

An `ossf` row is `{ id, icon, title, text, status }`. The number beside a stage is its position.

A `services` row is **one whole page**:

```json
{
  "id": "…", "slug": "…", "name": "…", "status": "shown",
  "schema_type": "…", "schema_description": "…",
  "meta":   { … },
  "hero":   { "title": "…", "subtitle": "…" },
  "core":   { "status": "shown", "eyebrow": "…", "title": "…", "lead": "…",
              "note": { "text": "…", "link_label": "…", "link_href": "…" },
              "items": [] },
  "layers": { "status": "shown", "eyebrow": "…", "title": "…", "lead": "…",
              "labels": { "purpose": "…", "features": "…", "tags": "…",
                          "count_one": "…", "count_many": "…" },
              "items": [] },
  "cta":    { "status": "shown", "title": "…", "text": "…",
              "label": "…", "href": "…", "icon": "…" }
}
```

A `layers` row is `{ id, icon, title, tab_text, text, hub_label, status, cards[] }` and a card is
`{ id, icon, name, category, desc, purpose, features[], tags[], status }`.

**Four things are drawn and never stored.** They are what keeps them from drifting out of step with
what they describe, which is what they are for:

| Drawn | From |
|---|---|
| the card beside each ring | that layer's **first** shown card |
| every node on the ring | a card — its id, its icon, and its name for the screen reader |
| *"12 Solutions"* under a layer heading | the shown card count, in `count_one` / `count_many` |
| the `Service` graph's offer catalogue | the layer list — empty when the band is hidden |

**A solution card's `id` is stored, never minted from its name.** Sixty-three of the 137 that
shipped carry an id that does not follow from the card's title —
`sol-cloud-design-private-cloud` on a card called *"Private Cloud Design & Implementation"*. They
were written by hand, and they are the fragment a saved deep link holds the card by.

**A layer's `id` is stored bare and rendered with a prefix** — `reactive` becomes
`id="layer-reactive"`, which is what the tab links to.

**`labels` are per page, not per card.** Every card on the cloud page is headed *"What it
includes"* and *"Technologies"*. An empty `features` label means the page's cards show no ticked
list at all; three of the six do not.

**`schema_type` is not the name.** It is schema.org's word for the practice, and on two of the six
it differs: HRaaS is `IT Staffing`, IT Consultancy & Training is `IT Consulting`.

**The index's group lists are authored, not derived from the detail pages.** The index says
*"Offensive Security & Penetration Testing (Metasploit, Burp Suite)"* where the detail page says
*"Offensive Security & Penetration Testing"*, and the HRaaS block lists four engagement models
against that page's thirty-three resource types. They are two summaries of one practice at two
lengths; flattening them into one would lose the shorter.

**Hiding a service hides the whole of it.** Its page answers 404, and its block and the nav card
that jumps to that block both leave the index — otherwise hiding would only produce a broken link.
The alternating tint follows the blocks a visitor can **see**, so the stripe stays correct when one
is switched off.

**There are no pictures on any of these pages, and no rich text anywhere in the document.**

## `content/certifications.json`

The resource certifications page: role groups, the roles each covers, and the certifications the
people in those roles hold. **A list inside a list** — the only document shaped that way.

```json
{
  "updated":  "…",
  "revision": 0,
  "meta":     { … },
  "hero":     { "title": "…", "subtitle": "…" },
  "certs":    { "status": "shown", "eyebrow": "…", "title": "…", "lead": "…", "items": [] },
  "cta":      { "status": "shown", "title": "…", "text": "…", "items": [] }
}
```

### A role group

```json
{
  "id":     "security-analyst",
  "slug":   "security-analyst",
  "icon":   "shield-halved",
  "blurb":  "…",
  "status": "shown",
  "open":   true,
  "roles":  [ { "id": "…", "name": "Security Analyst", "status": "shown" } ],
  "items":  [ { "id": "…", "name": "CompTIA Security+",  "status": "shown" } ]
}
```

`slug` is the anchor the group's `<details>` carries, so `#security-analyst` links to it. It is
minted from the **first role name** — a group has no title of its own, it *is* its roles — and then
frozen, because a link into the page is a promise. A group still carrying the placeholder id it was
created with is the one exception: that was never a real address.

`open` is the group that starts expanded. More than one may be, and none has to be.

### Three things are NOT in the file

- **the count on a group heading** — *"27 certifications"* is however many are **shown** in it;
- **a certification's icon** — all of them carry `certificate`, so it is a constant in the renderer;
- **the `/` between role names** — markup, emitted between them, and hidden from a screen reader so
  two roles are not read as a fraction.

### The totals in the prose are not in the file either

The lead and the search description may hold `{certifications}`, `{groups}` and `{roles}`, and the
renderer replaces each with the live figure as it draws. Every one has a `-word` form as well —
`{groups-word}` is *"four"* — because the page writes one of its numbers as a numeral and the other
as a word, and a token that could only produce digits would have reworded the page the first time
it rendered.

A typed number is wrong the moment somebody adds a certification, and nothing on the page or in any
check would notice: it is a true sentence that has quietly stopped being true. Counting and
substitution live in `lib/contract.php` rather than in either renderer, so the editor's preview and
the published page cannot disagree about what a token means.

## `content/branding.json`

The branding & advertisement page: the logo files people download, and the terms covering their
use. Edited at `/?s=branding`.

```
meta    { … }
hero    { title, subtitle }
assets  { status, eyebrow, title, lead, items[] }
legal   { status, title, items[] }
cta     { status, title, text, items[] }
```

One `assets.items[]` row is a logo variant:

| Field | Type | Notes |
|---|---|---|
| `id` | string | minted from the title |
| `title` `text` | string | the card's heading and its one line of description |
| `alt` | string | read instead of the preview picture |
| `plate` | string | `light`, `dark` or `neutral` — the background behind the preview |
| `status` | string | `shown` or hidden |
| `image` | picture | the preview drawn on the card |
| `files[]` | list | what a visitor can download |

And one `files[]` row:

| Field | Type | Notes |
|---|---|---|
| `id` | string | minted from the label, or the format when there is none |
| `label` | string | the adjective in the meta line — *"Transparent PNG"* |
| `filename` | string | the `download=` attribute: what the visitor's computer calls it. No separators; `branding_safe_filename()` empties anything with one |
| `status` | string | `shown` or hidden |
| `file` | picture | the file itself, which may be a vector |

`legal.items[]` rows are `{ id, text, status }` and `text` is **rich text** — the only rich text on
the page, because it is a legal notice and the sentence asking a rights holder to get in touch is a
link waiting to happen. It goes through `rt_sanitise_html()` on save and again on receipt.

### The preview and the download are two different pictures

`image` is the small thing on the card; `files[].file` is what somebody came for. On the page as it
ships those are an 800px preview and a 1600px download of the same mark. Collapsing them would
either serve the big file to everyone who merely looks at the page, or hand out the small one to
everyone who came for the logo.

A download may be an **SVG**; a preview may not. The page *links* to a vector file and never draws
one — see [0019](../90-decisions/0019-uploaded-images-travel-their-own-channel.md) and `lib/svg.php`.
A download is also allowed up to `UPLOAD_MAX_DOWNLOAD_DIMENSION` (3000px) rather than the 1600px
every displayed picture is reduced to, because it is the deliverable rather than decoration.

### Three things are not in the file

- **the size in a meta line** — *"1600 × 570"* is read off the file's own record, so it cannot claim
  a size the file no longer has. Only the adjective beside it is stored, because *"Transparent"* is
  editorial;
- **the words on a download button** — *"Download PNG"* states the file's own format;
- **the glyph on it** — every button carries `arrow-down`, so it is a constant in the renderer.

### The breadcrumb is its own field

The page is titled *"Branding Assets & Guidelines"* and called *"Branding & Advertisement"*
everywhere it is linked from. The about, company and certifications pages let their breadcrumb
follow `hero.title` because on those three the two strings are the same; here they differ, so a
breadcrumb that followed the hero would quietly rename the page in every search result that shows a
trail.

## `content/privacy.json`

The privacy policy: headed sections with Markdown bodies, a summary callout and a closing band.
Edited at `/?s=privacy`, listed on the legal hub at `?s=legal` with its shown/hidden switch.

```
meta    { … }
hero    { title, subtitle }
status  shown | hidden — the whole page; hidden answers 404
policy  { label, effective, callout{…}, sections[] }
cta     { status, title, text, items[] }
```

| Field | Type | Notes |
|---|---|---|
| `policy.label` | string | the visually-hidden `<h2>` that names the region for a screen reader, bound to it by `aria-labelledby` |
| `policy.effective` | string | the whole line at the top — *"Effective 21 August 2026"*. The wording is authored: *"Effective"* and *"Last updated"* do not mean the same thing |
| `status` | `shown` \| `hidden` | the whole page. Hidden answers 404, leaves the sitemap and the pills, and keeps every word |

One `policy.sections[]` row is a headed part of the policy:

| Field | Type | Notes |
|---|---|---|
| `id` | string | **the anchor**, minted from the heading and then frozen for good |
| `heading` | string | the `<h2>` |
| `status` | `shown` \| `hidden` | |
| `body` | string | Markdown source in the frozen dialect, rendered by `lib/markdown.php` |

`policy.callout` is `{ status, title, items[], note }` — the *"short version"* box, whose `items[]`
are `{ id, text, status }` with Markdown `text`, and whose `note` is a Markdown field.

### Bodies are Markdown, not blocks

Sections used to hold typed blocks (paragraph, list, table, address, note, subheading), because
`rt_sanitise_html()` allows no heading, no `<address>` and no `<table>` and structure therefore
could not live in a rich field. The Markdown renderer owns all markup instead, so a section
needs no kinds: `body` holds prose, lists, tables and notes in the frozen dialect
(`tech4time-website-frontend/plans/legal-markdown-syntax.md`), and raw HTML in it is escaped,
never passed through. Markdown source must never meet `rt_sanitise_*()` — it would
entity-mangle it — so `privacy_sanitise()` is an explicit pass-through, and safety lives in
the renderer, proven by `tools/test_markdown.py` in both repositories.

### An anchor is a promise

A section's `id` is the fragment somebody links to. Ids are assigned by `contract_identify_rows()`,
which claims every id already chosen **before** minting anything new — because the obvious one-pass
version lets a section added above an existing one with the same heading take that section's
fragment and silently rename the incumbent. Nine of the twelve shipped ids are hand-authored
(`who-we-are`, not `who-is-responsible-for-your-data`) and there is nothing to recover them from.

### The effective date is never stamped

`updated` records when the document was last published. `policy.effective` is a claim about when the
**policy** changed, and fixing a typo is not a new policy — so nothing writes it but a person.

### The policy band cannot be hidden, but the page can

`PRIVACY_BANDS` holds only `cta`: hiding the *band* would leave a page headed *"Privacy Policy"*
with no policy on it, still linked and listed — a compliance incident with a switch. Hiding the
*page* is the opposite: the `status` on the document, edited on the legal hub, answers 404,
leaves the sitemap and the pills, and keeps every word. One hides the words while keeping the
address; the other removes the address. The callout and any section can each be hidden.

### What it repeats from the contact page is compared, never enforced

The policy states the offices, the email and the telephone; so does `content/contact.json`. They are
kept separately on purpose — a controller's details are a legal statement, and one that changed
because somebody edited another page would be a statement nobody made. `privacy_shared_facts()`
asks by containment whether the policy still states the current values, on a form with `&nbsp;` and
whitespace collapsed, commas dropped and case folded, so it reports a different street and stays
quiet about a different comma. The editor draws it as a standing notice and **never refuses a
save**.

## Which pictures are stored at several widths, and which are not

Every uploaded picture record is `{ src, webp, width, height, srcset, webp_srcset }`.

`src` and `webp` are the single files they have always been, and stay the ones a browser without
`srcset` support is served — and the ones every scraper that reads an `<img>` without parsing a
candidate list will take. **A ladder is an addition to a working picture, never a replacement for
one.** `srcset` and `webp_srcset` are the same picture at several widths; empty means no ladder was
stored and the renderer emits `src` alone, exactly as it always did.

Which widths comes from `CONTRACT_IMAGE_SLOTS`, one row per upload slot, holding the width the
picture is **drawn** at and the `sizes=` attribute that describes it. One row, two consumers: the
uploader builds the ladder from `width`, the renderer builds `sizes=` from `sizes`. They are kept
together because a ladder the browser cannot choose from correctly is **worse than no ladder** —
with no `sizes=` a browser assumes the picture fills the viewport and takes the widest rung, so
every phone would download the 3× file.

| Slot | Drawn at | Ladders |
|---|---|---|
| `about.story` | 700 | yes — 1×, 2×, 3× |
| `company.journey` | 480 | yes |
| `home.destinations` | 400 | yes |
| `branding.asset` | 360 | yes |
| `about.accreditations` | 289 | yes |
| `company.clients` | 289 | yes |
| `company.technology` | 187 | yes |
| `contact.offices` | 56 | yes |
| `branding.file` | — | **no**: a deliverable somebody downloads, not something a page draws |
| `seo.share` | — | **no**: read by scrapers that do not implement `srcset` and want exactly 1200×630 |
| `seo.logo` | — | **no**: `Organization.logo` is one image, named once, to a consumer that picks nothing |

`contract_slot_widths()` never upscales and never stores a rung nothing can draw from. Each density
is capped at what actually arrived, which handles both directions with one rule: a 4000px
photograph in the 700 slot stores 700/1400/1600, a 500px one stores 500 alone, and a 1600px flag in
the 56 slot stores 56/112/168 rather than carrying a 1600px file to every phone that asks.

**The widths were measured in a browser, not estimated** — and three of them are not where anybody
would guess. `about.story` is widest at a 768px viewport, not on a desktop, because that is the last
width before the two-column breakpoint; `company.clients` is widest at 360. `about.accreditations`
is widest at 767, for `about.story`'s reason exactly — the last width before a breakpoint takes the
badge out of a half-width tile. It is **222 on a 1440 desktop, less than on a tablet**, which is why
its `sizes=` has three arms. See "If you are measuring geometry" in
[testing.md](../10-development/testing.md).

## Which pictures get a light/dark pair, and which do not

Asked and settled on 2026-08-31. Every managed picture on the site, and why it is or is not a pair:

| Page | What | n | Treatment | Pair? |
|---|---|---|---|---|
| Home | Get to Know Us cards | 3 | white plate, both modes | **yes** |
| About | photograph sections | 4 | white plate, both modes | **yes** |
| About | the logo section | 1 | themed surface | **yes** |
| About | accreditation badges | 0 | white plate, both modes | no |
| Company | client logos | 9 | white plate, both modes | no |
| Company | technology logos | 50 | white plate, both modes | no |
| Company | journey photographs | 3 | no plate, full-bleed | no |

**The three that are pairs are line art or a wordmark** — dark ink that needs a light ground, kept on
`--artwork-plate` in both modes. If the company ever has artwork drawn for a dark page, the slot is
there. **With nothing uploaded the markup is exactly what it was before the slot existed**: one
`<picture>`, no theme-swap classes, no second element. The page does not pay for an unused feature.

**The accreditation badges are not pairs either, and for the client logos' reason** — they are
somebody else's marks on a plate that is a legibility guarantee. The count is 0 because the band
ships empty; the treatment is settled whenever the first one is uploaded.

**And the plate is square by measurement now, not by request.** `aspect-ratio: 1` says what the
plate should be, and a **portrait** badge broke it: a grid item's automatic minimum size is its
min-content, which for a replaced element is its intrinsic size, so a 120×600 mark made the plate
**225×925** where every square and landscape one stayed 225×225 — one tile four times the height of
its neighbours, and the mark itself spilling 830px over the caption below it with `max-height: 100%`
sitting there doing nothing. Both minimums had to be released, the plate's and the mark's. Nothing
shipped was ever wrong, because the band has no rows yet — which is exactly why nothing found it:
`content/about.json` has no `accreditations` key, so all four browser crawlers walk this page and
never render the band. `tools/check_accreditations.py` supplies its own document and uploads a
portrait badge on purpose.

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

## The `meta` band, on every document

Every document has one, including `content/careers.json`, which never used to — its title and
description were literal strings in the page file and were the only two on the site nobody could
change.

```json
"meta": {
  "title":       "About Tech4TIME | Trusted IT & Cybersecurity Solutions",
  "description": "Founded in 2018, Tech4TIME delivers …",
  "share_title": "About Tech4TIME",
  "keywords":    "about Tech4TIME, IT company Bangladesh, cybersecurity company Dhaka",
  "breadcrumb":  "About Us",
  "robots":      "index",
  "changefreq":  "monthly",
  "priority":    "0.8",
  "share":       { "src": "", "webp": "", "width": 0, "height": 0 },
  "share_alt":   ""
}
```

| Field | |
|---|---|
| `title` | the browser tab and the search result's heading. At most `SEO_TITLE_MAX` (65) characters |
| `description` | the search result's paragraph. `SEO_DESC_MIN`–`SEO_DESC_MAX` (50–165); 150–160 is the ideal the editor hints at and nothing refuses |
| `keywords` | a comma-separated list, tidied on save: empties and case-insensitive repeats dropped, one space after each comma. **Omitted from the page entirely when empty.** Google has ignored the tag since 2009 and Bing treats a stuffed one as spam — a handful of true words is worth more than a long list |
| `share_title` | the heading on a shared link. Usually the title without the brand suffix |
| `breadcrumb` | the page's name in the BreadcrumbList. **Pure SEO** — there is no visible breadcrumb anywhere on the site |
| `robots` | `index` or `noindex`. **Also decides the sitemap**: one control, not two, so the two cannot contradict each other |
| `changefreq`, `priority` | the sitemap's hints for this page |
| `share` | a per-page share card, **uploaded at `?s=seo&page=<key>`** in the *When the link is shared* band. Empty means the site-wide one in `content/seo.json`, which is what every page still uses today. The 404 is the exception: its screen renders no share band and `seo_meta_from_post()` returns before the upload, so the field is there for one shape and stays empty |
| `share_alt` | its alt text — read aloud where the picture cannot be seen, and used **only** when this page has a card of its own |

**A service row carries the same band**, so a seventh service arrives with sensible defaults and a
sitemap entry without anyone opening a second screen.

**Only `title`, `description`, `share_title`, `breadcrumb` and `keywords` are in
`*_TEXT_FIELDS`** — the five of `CONTRACT_META_TEXT`. The rest are
enumerated or structured, and are validated against their allowed values rather than trimmed as
free text.

### The band is edited on one screen, and by nothing else

`?s=seo&page=<key>` writes it. The ten page editors do not: they render a link to that screen where
the fieldset used to be, and their `*_from_post()` loops iterate `contract_page_bands()`, which is
`*_TEXT_FIELDS` **minus** `meta`.

That subtraction is load-bearing. A form that stops *rendering* a field while its band is still
named in the loop reads `$_POST['meta']['title']` as absent, `?? ''` supplies an empty string, and
the page's title is blanked on every save — silently, because empty is a valid title.
`tech4time-website-backend/tools/test_seo_admin.py` saves each page editor untouched and requires
every meta value to survive. [ADR 0020](../90-decisions/0020-page-metadata-is-content.md)

---

## `content/seo.json`

The site-wide half: what is true of the whole site rather than of one page, plus the 404's own
record, because that page renders no content document and never will.

```json
{
  "updated":  "…",
  "revision": 0,
  "site":     { "name": "…", "description": "…", "locale": "en_US", "lang": "en",
                "og_type": "website", "twitter_card": "summary_large_image",
                "theme_light": "#…", "theme_dark": "#…", "share": {}, "share_alt": "…" },
  "identity": { "legal_name": "…", "alternate_name": "…", "slogan": "…",
                "description": "…", "founded": "…", "price_range": "…",
                "area_served": "…", "logo": {}, "service_types": [],
                "knows_about": [] },
  "sameas":   { "items": [] },
  "hours":    { "items": [] },
  "crawl":    { "verify_google": "", "verify_bing": "", "analytics_id": "",
                "robots_extra": [] },
  "manifest": { "short_name": "…", "background": "#…", "theme": "#…",
                "display": "standalone" },
  "notfound": { "title": "…", "description": "…", "share_title": "…",
                "breadcrumb": "…", "keywords": "…", "robots": "noindex",
                "changefreq": "…", "priority": "…", "share": {}, "share_alt": "…" }
}
```

| Band | | Edited at |
|---|---|---|
| `site` | the defaults every page inherits: `<html lang>`, `og:locale`, `og:type`, the card shape, the theme colours, the share card | `?s=seo&site=identity` |
| `identity` | the Organization node — legal name, slogan, founding year, the services it offers, what it knows about | `?s=seo&site=identity` |
| `sameas` | the profiles that are this company elsewhere. Rows, so they add, reorder and **hide** | `?s=seo&site=identity` |
| `hours` | opening hours as machine-readable rows — `days[]`, `opens`, `closes`. The office rows in `content/contact.json` carry hours as prose, which a search engine cannot read | `?s=seo&site=identity` |
| `crawl` | extra `Disallow` paths, the Search Console and Bing verification tokens, and **`analytics_id`** — a Google measurement id. Empty means no analytics and no external origin; anything that is not the shape Google issues is refused rather than escaped, because it lands inside a `<script src>`. [ADR 0021](../90-decisions/0021-analytics-is-off-until-somebody-turns-it-on.md) | `?s=seo&site=crawl` |
| `manifest` | what `manifest.php` renders at `/site.webmanifest`. It holds **only** `short_name`, the two colours and `display`: the manifest's `name` and `description` are `site.name` and `site.description`, so an installed icon cannot end up called something the site is not. The **icon list is not here** either — it names files that must exist | `?s=seo&site=crawl` |
| `notfound` | the 404's own meta band. It is the **same shape** as every page's, so one set of normalisers serves both, but only `title`, `share_title` and `description` are editable: `seo_meta_from_post()` returns before the rest and the screen renders none of them, because a page nobody may index has no keywords, no place in a breadcrumb trail and no sitemap line. `robots` is forced to `noindex` on every save. No canonical and no `og:url`, by design | `?s=seo&page=notfound` |

**The offices are not here.** The addresses and telephone numbers in the Organization graph come
from `content/contact.json`, through `contact_addresses()` and `contact_points()` — the same
functions the contact page renders from, so the graph and the visible page cannot disagree.

**`seo_defaults()` carries the real values, not placeholders.** A host with no `content/seo.json`
still emits the correct graph and share card, exactly as `contact_defaults()` already does for its
page.

---

## `content/chrome.json`

The furniture around every page: the header, the footer and the small-screen dock. Rendered by
`lib/body.php` on the request. It was literal markup in seventeen page files until 2026-09-10 —
about 6,800 lines of duplication kept in step by `propagate_shared.py` —
[ADR 0023](../90-decisions/0023-the-header-and-footer-are-emitted-once.md).

```json
{
  "updated":  "…",
  "revision": 0,
  "header": { "brand_label": "…",
              "logo": { "alt": "…", "sizes": "…", "width": 360, "height": 128,
                        "light": { "src": "…", "srcset": "…", "webp": "…" },
                        "dark":  { "src": "…", "srcset": "…", "webp": "…" } },
              "nav":  { "items": [] } },
  "footer": { "brand_label": "…", "logo": {}, "tagline": "…", "description": "…",
              "links":     { "heading": "…", "items": [] },
              "services":  { "heading": "…", "index_label": "…" },
              "contact":   { "heading": "…", "items": [] },
              "legal":     { "items": [] },
              "copyright": { "name": "…", "rights": "…" } },
  "dock":   { "panel": { "items": [] },
              "bar":   { "items": [] },
              "menu_label": "…" }
}
```

| Band | | Edited at |
|---|---|---|
| `header` | the logo lockup, the link's accessible name, and the six-row main nav | `?s=chrome&part=header` |
| `footer.links` | the "Quick Links" column: a heading and rows | `?s=chrome&part=footer` |
| `footer.services` | a heading and the label of the row above the list. **The rows are not here** | `?s=chrome&part=footer` |
| `footer.contact` | the footer's **own** contact rows — see below | `?s=chrome&part=footer` |
| `footer.legal` | the bottom bar's links | `?s=chrome&part=footer` |
| `footer.copyright` | the name and the rights sentence. **The year is not here** — it is stamped as the page renders, and `refreshCopyrightYear()` in `main.js` corrects it for a tab left open across midnight on 31 December | `?s=chrome&part=footer` |
| `dock.panel` | the card that rises above the bar: a row per section, each with a line of explanation | `?s=chrome&part=dock` |
| `dock.bar` | exactly four keys, each with a destination, a short label, an icon and an emphasis | `?s=chrome&part=dock` |

### A link row points at a route, never at a URL

```json
{ "id": "about", "target": "about", "label": "", "status": "shown" }
```

`target` is a key of `chrome_targets()` — one of the nine routes that resolve to an address, or
`service:<id>` for a row of `content/services.json`. There is no way to type an address into a nav
link, which is what makes it impossible for one to 404 in the single component that appears on
every page of the site. `SEO_ROUTES` already says routes are code; this follows from it.

**An empty label means "whatever that page calls itself".** Every link the site ships with has
one, because every one already agreed with its route's own name. So renaming a page in `?s=seo`
renames it in the header, the footer and the dock at once, and a label is typed only where the
chrome should disagree on purpose. The dock's bar is the exception: its labels are always typed,
because what fits under a 44px key is not what fits in a nav — "Profile", not "Company Profile".

### A footer contact row

```json
{ "id": "phone-bangladesh", "kind": "phone", "label": "Bangladesh",
  "lines": ["+880 1320571562", "+880 1881873463"],
  "note": "Sunday – Thursday", "status": "shown" }
```

`kind` is `phone`, `email`, `address` or `hours`. It decides both the mark drawn beside the row and
how the lines link — `tel:`, `mailto:`, or not at all, because a street and an opening time are
facts rather than destinations. **Consecutive rows sharing a kind render inside one
`.contact-item`, under one icon**, which is what the CSS's `.contact-item__label ~
.contact-item__label` rule is written against.

### Two columns store nothing, and are derived

**The services list** is read from `content/services.json` as the footer renders, so a seventh
service appears in the footer by itself and a hidden one disappears. Before this, the footer's copy
said "Human Resource Provision" where the services document said something else, and a service
added in the editor could never appear in the footer at all.

**The social links** are read from the SEO document's `sameas` rows, so a profile URL is changed in
one place and the footer cannot disagree with the Organization graph. The mark comes from the URL's
host — `CHROME_SOCIAL_ICONS` — with a globe behind anything the sprite has no brand mark for.

### The footer's contact rows deliberately are not

They are the footer's own: added, worded, ordered, shown and hidden on the footer screen, owing
nothing to `content/contact.json`. The contact page holds every detail in full; a footer holds the
part worth putting in a footer, in whatever wording suits it.

What keeps the two honest is a **notice, never a refusal** — for the reason the privacy policy's
duplicated facts are reported rather than forbidden: requiring the two to agree before either could
be saved means that after an office move, whichever page you edited first could not be saved, and
there is no order that avoids it.

### The four columns and the four keys are code

Their headings and contents are here; their number is not. A footer that can be given a fifth
column is a footer that can be broken at a width nobody tested, and a fifth key would not wrap —
it would shrink the other four below a thumb's width. `chrome_normalise()` pads and truncates the
bar to `CHROME_BAR_SLOTS` rather than trusting what arrived, because a document is a file as often
as it is a form.

### `chrome_defaults()` is the markup, extracted rather than typed

Every value in it was read out of the three templates by a script before they were deleted, so a
host with no `content/chrome.json` renders the site exactly as it rendered before any of this
existed — which is the whole safety property of the conversion, and the reason the header, footer
and dock cannot vanish because one file failed to arrive.

**A band that is absent and a band that is empty are not the same thing.** A missing document, or
one that predates a band, falls back to `chrome_defaults()` for that band — that is what makes the
paragraph above true. A band that arrived as an empty list is an operator who hid or removed every
row, and it stays empty; filling it back in would resurrect links somebody had deliberately taken
away, on every page at once. `chrome_normalise()` tells them apart by asking whether the key is
an array at all, not whether it is truthy, and `tools/test_chrome.py` holds both halves down: the
whole site renders with no document, and a nav emptied on purpose stays empty while the
other bands keep their rows.

---

## Rules that apply to both

**Written atomically.** `store_write()` writes a temp file and renames it over the target, keeping
one `.bak`. A visitor loading the page mid-save reads either the old file or the new one.

**Rich text is sanitised on save** by `rt_sanitise_html()`, which writes new tags from an allow-list
rather than passing anything through. There is no `style` attribute — the CSP blocks inline styles,
so alignment is a class from a fixed list.

**Everything is escaped on output** with `h()`, regardless of having been sanitised on the way in.

**Bookkeeping fields** — `updated`, `revision` — are exempt from the content-model check in both
directions. Nothing renders them and the form does not write them.

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
