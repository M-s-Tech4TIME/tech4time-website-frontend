# The libraries — `lib/`

**Applies to:** both

Eleven PHP files. What each owns, and which one to open.

None of them is reachable over HTTP: `.htaccess` has `RewriteRule ^lib/ - [F,L]`, and the private
store they read from is outside the document root entirely.

---

## At a glance

| File | Owns | Depends on |
|---|---|---|
| [`html.php`](#htmlphp) **shared** | escaping, and the rich-text sanitiser | — |
| [`store.php`](#storephp) | reading and writing JSON atomically | — |
| [`contract.php`](#contractphp) **shared** | the shape of every editable document | `html` |
| [`careers.php`](#careersphp) | what this side does with a job post | `contract`, `store` |
| [`contact.php`](#contactphp) | what this side does with the contact page | `contract`, `store` |
| [`company.php`](#companyphp) | what this side does with the company profile | `contract`, `store` |
| [`milestones.php`](#milestonesphp) | what this side does with the timeline | `contract`, `store`, `company` |
| [`about.php`](#aboutphp) | what this side does with the about page | `contract`, `store` |
| [`home.php`](#homephp) | what this side does with the home page | `contract`, `store` |
| [`services.php`](#servicesphp) | the services index and its six detail pages | `contract`, `store`, `html` |
| [`certifications.php`](#certificationsphp) | the resource certifications page | `contract`, `store`, `html` |
| [`branding.php`](#brandingphp) | the branding & advertisement page | `contract`, `store`, `html` |
| [`privacy.php`](#privacyphp) | the privacy policy | `contract`, `store`, `html` |
| [`seo.php`](#seophp) | the site-wide SEO record, and what is derived from it | `contract`, `store`, `html`, `contact` |
| [`chrome.php`](#chromephp) | the header, footer and dock every page carries | `contract`, `store`, `html`, `services`, `seo`, `sprite` |
| [`settings.php`](#settingsphp) | the site's identity: the mark, the icons, the colours, the address | `contract`, `store` |
| [`sprite.php`](#spritephp) *(frontend)* | the icon block a renderer writes for itself | — |
| [`head.php`](#headphp) *(frontend)* | the `<head>` every page emits, and its structured data | `seo` |
| [`body.php`](#bodyphp) *(frontend)* | the header, footer and dock every page emits | `chrome` |
| [`svg.php`](#svgphp) **shared** | what a publishable vector file is |
| [`publish.php`](#publishphp) **shared** | how a document is signed and checked on the wire | `private`, `contract` |
| [`publish_client.php`](#publish_clientphp) *(backend)* | sending one | `publish` |
| [`private.php`](#privatephp) | where the secrets are, and key derivation | — |
| [`totp.php`](#totpphp) | RFC 6238 authenticator codes | — |
| [`throttle.php`](#throttlephp) | counting attempts | `private`, `store` |
| [`mailer.php`](#mailerphp) | the one place mail leaves this site | — |
| [`auth.php`](#authphp) | accounts, hashing, sessions, the audit log | `private`, `totp`, `store` |
| [`reset.php`](#resetphp) | the emailed one-time code | `auth`, `mailer`, `throttle` |
| [`admin.php`](#adminphp) | the section registry and page furniture | `auth`, `html` |

Roughly bottom-up: `html` and `store` know nothing about anything; `admin` sits on top of all of it.

---

## Content and rendering

### `html.php`

`h()` · `rt_sanitise_html()` · `rt_safe_href()` · `rt_plain()`

Escaping on output, and the sanitiser that decides what HTML an editor may write.

**Written by hand because there is no DOM extension on this host** — `DOMDocument` does not exist.
So it parses the markup itself, and the way it stays safe without a parser is worth understanding:

> It never passes anything through. It walks the input and, for each tag it recognises, **writes a
> new one** from an allow-list of names and attributes. Anything unrecognised — a tag, an attribute,
> a stray angle bracket — is discarded rather than copied.

So the output cannot contain a construct this file does not explicitly know how to emit. That is a
much smaller thing to get right than trying to spot every dangerous input.

**No `style` attribute, ever.** The CSP is `style-src 'self'`, so an editor that wrote
`style="text-align:center"` would look correct in the admin and do nothing on the public page.
Alignment is a class from a fixed list — which is why `class` is allow-listed *by value*, not merely
by name.

`h()` is what you call on every value you print. Always. Do not assume something was cleaned earlier.

### `store.php`

`store_read()` · `store_write()` · `store_edit()`

Reading and writing a JSON file.

`store_write()` is **atomic**: it writes a temp file in the same directory and renames it over the
target. A rename within a filesystem is atomic, so a visitor loading the page mid-save reads either
the old file or the new one, never half of one. It also keeps one generation of `.bak`.

`store_edit()` is read-modify-write **under a single exclusive lock**. Use it for anything that
counts.

> `store_read()` then `store_write()` is two steps with a gap. That is fine for a person saving a
> form and wrong for a counter: two failures landing together would each read 3, each write 4, and
> one would vanish. That is not a rounding error — it is the attacker's best move.

`store_read()` returns `null` for a missing file **and** for malformed JSON — the right shape for
site copy, where both mean "fall back to defaults" and the page still renders. Callers that must
tell them apart use **`store_state()`**, which answers `ok`, `missing`, `unreadable` or `corrupt`.

`auth_problem()` uses it to refuse rather than present a damaged account file as a fresh install,
and `store_write()` uses it to make sure a damaged file never becomes the `.bak` — the copy that
damage is recovered from. `tools/test_store.py` covers both.

### `contract.php`

**Shared — byte-identical in `tech4time-website-frontend` and `tech4time-website-backend`.**

`CONTRACT_VERSION` · `CONTRACT_DOCUMENTS` · `CONTRACT_BOOKKEEPING` · `contract_path()` ·
`careers_normalise()` · `contact_normalise()` · `contact_defaults()` · `chrome_defaults()` ·
`chrome_normalise()` · `chrome_targets()` · `contract_sanitise()` · `contract_next_revision()` · …

`contract_path()` gives a document's record path — `content/<name>.json`, the same rule on both
hosts. It exists for the things that have to work over *all* the documents without knowing their
names in advance: the deploy's seed, built by looping over `CONTRACT_DOCUMENTS`, and the editor's
warning when a host has no record for the page being edited. A list of documents kept anywhere but
here is a list that goes out of step, and it did — see
[ci-cd.md](../../20-deployment/ci-cd.md#it-was-a-list-and-the-list-went-out-of-step).

**The shape of a document, and nothing else.** Field lists, the defaults a missing key falls back
to, the normalising that turns whatever arrived into that shape, and the queries that read it. Both
halves must agree on all of it or they are not describing the same job post.

What is deliberately *not* here:

| | goes to | because |
|---|---|---|
| validation with readable messages, the form model, the flag picker | backend | the frontend has no form to validate |
| `JobPosting` / `ContactPage` schema, flag `<picture>`, `tel:` hrefs | frontend | the backend does not render the public page |

The line is: **if the two sides disagreeing about it would corrupt a document, it is here.** If
disagreeing would only make one side's own page look wrong, it is not.

`contact_defaults()` and `contact_office_defaults()` are **the definition of the shape** —
`check_content_model.py` reads the field list out of those functions rather than out of
`content/contact.json`, because the file is one instance of the shape and an optional field that
happens to be absent from it is still a field.

`CONTRACT_BOOKKEEPING` names the fields a document keeps about *itself* — `updated` and `revision`.
Nothing edits them and nothing renders them, so both directions of
`check_content_model.py` and the round trip in `test_careers_admin.py` exempt them, and all three
read the one list. They did not, once: `revision` was added, the careers test treated it as a
site-wide setting, posted it on its own, and blanked `cv_form_url` doing so.

`CONTRACT_IMAGE_SLOTS` is the one place that knows how wide an uploaded picture is **drawn**, and it
is here for the reason above: the uploader decides how many files to write from it and the renderer
decides its `sizes=` attribute from it, so the two sides disagreeing would mean a browser choosing
the wrong file for every picture on the site. `contract_slot_widths()` turns a row into a ladder —
never upscaling, and never storing a rung nothing can draw from — and `contract_slot_sizes()` gives
the attribute. `contract_srcset()` checks each candidate path the way `contract_safe_image_path()`
checks a single one; it was `chrome_srcset()` while the header lockup was the only picture stored at
more than one width.

`SETTINGS_MAIL_FROM` is what enquiry mail is sent **as**, and it is deliberately **not** editable.
A message has to come from an address at this site's own domain or it fails SPF — the DNS record
saying which servers may send as `tech4time.bd` — and is filed as spam. That record lives with the
domain and nothing in the editor can change it, so a field for it would let somebody make every
enquiry disappear into a spam folder with nothing on the screen to say why. It is here rather than
in the handler because **both halves need it**: the frontend sends with it, and the editor's screen
has to be able to *say* what messages are sent as, or the one field somebody might look for is
simply absent with no explanation.

**The contrast pairs and the sums that judge them are here, and that is a correction.**
`SETTINGS_CONTRAST_PAIRS` says which pairs have to be readable and at what ratio;
`contract_contrast_ratio()` and `settings_contrast_faults()` do the arithmetic. `tools/check_contrast.py`
held its own copy of both, under a note reading *"keep this in sync with assets/css/theme.css"* — fine
while a colour could only be changed by editing a stylesheet, and not fine the moment a person can
pick one from a screen and the editor has to judge it too.

So the data lives once and **the arithmetic deliberately does not**: `check_contrast.py` reads the
palette and the pairs from here and keeps its own Python sums, then compares all 38 answers against
this file's. That is `publish_stub.py`'s rule applied to colour — each side checked against an
independent implementation, never against its own counterpart. A shared list cannot drift; a shared
bug could.

`contract_ico_container()` writes a `.ico` by hand: a six-byte directory, a sixteen-byte entry per
image, then the PNG payloads. **It is in the contract because it is assembled where it is served,
not sent over the wire.** The asset channel carries what `getimagesizefromstring()` recognises — PNG,
JPEG, WebP — and an `.ico` is none of them; widening that list so one file could travel would also
widen what an editor can upload as page artwork. So the editor generates the PNGs and the public site
builds the container from the three it already holds, which is why both halves need the same writer.
Embedding PNG rather than the older BMP-with-mask has been valid since Windows Vista and is what the
committed `favicon.ico` already contained — all three of its entries, checked before this was written.

`contract_srcset_top()` answers the widest rung of a ladder, which is **not always the record's
`src`.** For a picture the uploader stored it is — `upload_store()` names the top rung as `src`. For
the logo the site *ships* with it is not: the header's `src` is the 360 px file and the ladder goes
on to 540, because those files were built before the settings document existed and the seed
reproduces them rather than tidying them. So the About page's big lockup, `Organization.logo` and
every job posting's hiring-organisation logo ask for the largest rendition instead of assuming, and
each still names the file it names today.

`contract_image_paths()` answers the other half of the same question: **every** file a picture record
names, srcset entries included. A ladder keeps most of its files inside `srcset` and nowhere else —
only the top rung is also the `src` — so a caller reading `src` and `webp` alone sees two of six.
The seven `*_images()` collectors all ask it rather than each carrying its own walk, which is what
makes adding a field to a picture record one edit instead of seven.

`about_picture()`, `home_picture()`, `company_picture()`, `branding_picture()` and
`contact_flag_picture()` each ask it for their own slot. Four of them name the slot as a literal
because they draw one picture; `company_picture()` takes it as an argument, because the company
profile draws a client's logo, a journey photograph and a technology mark at three different widths
through one function. Only the *uploaded* branch of `contact_flag_picture()` ladders — the slug
branch names files that ship with this repository, one width each.

`contract_picture_ladder()` answers what a renderer should put in `srcset` and `sizes` for one
picture in one slot — **and returns nothing at all when the slot declares no `sizes=`.** That is not
caution. A `srcset` of widths with no `sizes=` beside it does not mean "choose freely": the browser
is required to assume the picture fills the viewport, so it takes the *widest* rung on every screen
— a phone downloading the 3× file for a flag drawn at 56 px, which is worse than the single file it
would otherwise get. The rule has to be the same on five pages that each write their own markup, so
it lives here once and what comes back is three strings and no markup, which is what keeps this file
shareable with a repository that renders nothing.


`contract_sanitise()` runs every rich field back through `html.php`, driven off
`CAREERS_RICH_FIELDS` / `CONTACT_RICH_FIELDS` rather than a list of its own — so a rich field added
to the contract is sanitised on receipt *by having been added*.

**Bump `CONTRACT_VERSION`** when a change would make a document written by one version render
wrongly under the other: a field renamed, a field's meaning changed, a list that becomes a scalar.
Not for a new optional field older code simply ignores.

### `careers.php`

`careers_load()` · `careers_save()` · `careers_validate()` *(backend)* · `careers_job_posting()` *(frontend)* · …

What **this side** does with the shape `contract.php` defines. `careers_sanitise_html()` and
`careers_safe_href()` are one-line aliases kept from before that code moved to `html.php`, so the
move changed no caller.

`careers_save()` mints the next `revision` itself rather than trusting a caller to. On the backend it
also publishes — a save that wrote the record and forgot to send it is a save nobody would
investigate.

### `contact.php`

`contact_load()` · `contact_save()` · `contact_validate()` · `contact_flags()` *(backend)* ·
`contact_page_schema()` · `contact_flag_picture()` · `contact_reach_href()` *(frontend)* · …

The same division for the contact page.

It used to carry a footer-drift banner and a **second `store_write()`** after the publish, to record
the fingerprint the frontend reported for its own footers. The footer renders from
`content/chrome.json` now and holds no copy of these details to go stale, so both are gone —
[ADR 0023](../../90-decisions/0023-the-header-and-footer-are-emitted-once.md).

### `company.php`

`company_load()` · `company_save()` · `company_validate()` *(backend)* ·
`company_page_schema()` · `company_picture()` *(frontend)*

The same division again, for the company profile. The shape itself is the largest of the three and
lives entirely in `contract.php`: six repeatable lists — milestones, figures, clients,
photographs, technology, principles — plus the copy around them.

Two things here are worth knowing before changing either half:

**`company_picture()` decides between `<picture>` and a bare `<img>`,** on whether the row carries
a WebP sibling. An SVG or an AVIF has none, and a `<picture>` holding one `<img>` and no `<source>`
is markup claiming a choice is being made when none is. It is emitted on one line with no
whitespace between the tags, because those elements are inline and a newline between them is a
space the browser renders.

**`company_validate()` refuses a figure that does not start with a digit.** `animations.js` counts
it up by reading the number off the front, so `"Over 100"` silently never animates. That is the
kind of thing an editor should say out loud rather than let somebody discover.

**The milestones are no longer one of those lists.** They are `milestones.php`, below.
`COMPANY_MOVED_BANDS` names the band this side no longer edits or counts, and every loop over
`COMPANY_LISTS` or `COMPANY_BANDS` on the editing side goes through `company_here()` — because a
form that has stopped rendering a band while still naming it in its `*_from_post()` loop blanks
that band on every save, silently, and an empty string is a valid value.

**`company_page_schema()` takes the timeline as an argument** rather than reading it. The page
shows a window onto the history, and a graph built from the whole of it would describe entries the
markup does not carry. It is an argument and not a `require` because `milestones.php` already
requires this file, for its read-through.

### `milestones.php`

`milestones_load()` · `milestones_save()` · `milestones_validate()` *(backend)* ·
`milestones_page_schema()` · `milestones_event_list()` *(frontend)*

The company's timeline, and the one document **two pages read**: `/pages/milestones/` renders all of
it and `/pages/company-profile/` renders the most recent `MILESTONES_WINDOW` years and links there
for the rest. The window is `milestones_recent()` in `contract.php`, so both pages agree about what
"recent" means, and `assets/css/pages/milestones.css` is loaded by both, so they agree about what it
looks like.

**It was a band of the company document, and that band is still in the contract.** Deprecated, not
deleted: removing it would change the meaning of every `content/company.json` already written, which
is the one thing `CONTRACT_VERSION` exists to stop.

**`milestones_load()` reads through to it, and the condition is the revision.** Until the first save
on `?s=milestones` the entries — and the heading, the eyebrow and the introduction with them — still
live in the company document, so this reads them from there. Not "the file is missing": a fresh host
is seeded with `content/milestones.json` from the defaults, so the file exists from the first day.
Not "the list is empty" either: an operator who deliberately removed every entry would be handed
them all back on the next request, which is the editor refusing to do what it was told. Revision 0
means nobody has ever saved this, and the first save mints 1 and stops the fallback for good.

**`milestones_recent()` keeps a row whose year it cannot read.** `year` is free text — the editor
asks for `2024` or `2024–2025` and refuses anything else, but the contract sees hand-edited files
too. A row starting with four digits is placed in that year and the highest `MILESTONES_WINDOW`
years are the window; anything unreadable is always kept, because dropping a row nobody can sort is
worse than showing one extra. It filters and never sorts: the editor decides the order, and the
alternating left/right of the rail is `:nth-child`, so reordering here would move entries across the
page as well as down it.

### `about.php`

`about_load()` · `about_save()` · `about_validate()` *(backend)* ·
`about_page_schema()` · `about_certification_list()` · `about_picture()` ·
`about_photograph()` · `about_logo_lockup()` · `about_reveal_paragraphs()` *(frontend)*

The same division again, for the about page: three repeatable lists — the story sections, the
specialities and the why-us cards — plus the copy around them. The shape is in `contract.php`.

Three things here are worth knowing before changing either half:

**A story row's `layout` chooses between a photograph and a logo pair.** `about_logo_lockup()`
emits both colour variants, each falling back to the lockup that ships with the site — so the row
works with nothing uploaded, and a new mark can be put there without a deploy. With only the light
half given it is used in both modes, deliberately: the alternative is the old logo beside the new
one. Both variants carry the same `alt`, because exactly one is displayed at a time and two
different names for one logo is what a screen reader would otherwise announce.

**Every story row carries two picture records, whichever layout it uses.**
`about_logo_lockup()` draws the pair for a logo row and `about_photograph()` for a photograph one.
The dark half is optional and almost always empty: `.about-split__image` keeps the illustrations on
a white plate in both colour modes by design, so one picture is the normal case. With none uploaded
the markup is exactly what it was before the slot existed — one `<picture>`, no theme-swap classes,
no second element. Uploading one produces the pair and takes the dark half off the white plate. This
is `home_destination_art()` in `home.php` for the row shape this page uses; the two are separate
rather than shared because they take different classes.

**`about_reveal_paragraphs()` puts the scroll markers back on each paragraph.** The prose is one
rich-text field, so the markers cannot be typed into it, and how many paragraphs there are is a
property of the content rather than of the template. It runs on already-sanitised HTML and only
ever adds two valueless attributes to an opening `<p>`; if it matched nothing the paragraphs would
arrive un-animated rather than invisible.

**`tools/apply_reveals.py` no longer governs this page.** It reports and skips any page that builds
part of itself with a loop, which this one now does — see [motion.md](../frontend/motion.md).

**The accreditations band is the only part of this page's body that reaches the graph.**
`about_certification_list()` turns each shown badge into a `Certification` node and
`about_page_schema()` hangs them off the Organization as `hasCertification` — which is a claim about
the **company**, and deliberately not the `EducationalOccupationalCredential` that
`certifications.php` emits for the qualifications a **person** holds. Nothing is emitted while the
band is hidden, and a badge with no picture still counts: the name is the claim and the image is
decoration, so a row is skipped only when it has no name. The band ships hidden, so today this adds
nothing to the live page and starts working the moment somebody uploads a badge — with no code
change, which is the same property `milestones_page_schema()` has.

**`about_page_schema()` carries no `@id`.** It had one, hardcoded, identical to the one
`seo_page_node()` generates — so the page shipped two nodes claiming one id with different `@type`s,
different names and different descriptions, which is a graph contradicting itself. The company,
milestones and contact schemas never had one; this one now matches them.

### `home.php`

`home_load()` · `home_save()` · `home_validate()` *(backend)* ·
`home_hero_title()` · `home_terminal_lines()` · `home_cta_title()` ·
`home_picture()` · `home_destination_art()` · `home_service_schema()` *(frontend)*

The same division once more, for the home page — **six** repeatable lists, more than any other
document: the hero's badges and tags, the terminal's lines, the technical domains, the service
cards and the Get to Know Us cards. The shape is in `contract.php`.

**Every field on this page is plain text.** There is no rich-text field at all, because every lead
and card body is a single styled `<p>`; see `HOME_ROW_RICH_FIELDS` in `contract.php`. The three
functions that return markup build it from values they escape themselves.

**`home_hero_title()` wraps one phrase of the heading in the accent colour.** The title is stored as
plain text with the phrase to emphasise beside it, so nobody types a tag and the class name stays in
the stylesheet. The split is made on the raw strings and each part escaped afterwards — searching
escaped text for an escaped needle works until the phrase contains an `&`. A phrase that is not in
the title renders the heading plain; the editor refuses to save that, so it is a safety net rather
than the plan.

**`home_terminal_lines()` emits at column 0, and owns the caret.** `.terminal__line` is
`white-space: pre-wrap`, so source indentation would appear as leading spaces on the page. The
blinking cursor is emitted after the last line and is not a row in the document: an operator cannot
delete it, end up with two, or strand it in the middle.

**`home_destination_art()` emits one picture unless a dark half exists.** With none — which is every
card today — the markup is exactly what the page carried before it rendered from a document, with no
theme-swap classes and no second element. The illustrations are line art that the stylesheet keeps
on a light plate in both colour modes, so the dark slot is an option nobody has taken rather than a
gap. Uploading one produces the pair and takes the dark half off that plate.

**`home_service_schema()` generates the `Service` ItemList from the cards.** It was literal markup
maintained beside them, and the two had already drifted: the card read "SOC & CIRT" and the schema
read "SOC and CIRT". A seventh card is now a seventh entry by being a seventh card, and a hidden one
is absent from both.

**`tools/apply_reveals.py` does not govern this page either**, for the same reason.

### `services.php`

`services_load()` · `services_save()` · `services_validate()` *(backend)* ·
the renderers *(frontend)*

**This is the only library that draws more than one page.** One document,
`content/services.json`, holds the services index *and* all six detail pages beneath it. That is
forced rather than chosen: a seventh service has to be addable from the editor, and
`CONTRACT_DOCUMENTS` is a constant in code — so a service cannot be its own document and has to be
a row in a list. See the note over `services_defaults()` in `contract.php`.

Keeping the drawing in one file follows from the same fact. The six detail pages are one template
with six sets of words: they load the same stylesheet,
`assets/css/pages/service-detail.css`, and it contains no per-service rule. A seventh service
needs no new CSS.

**Every field on these pages is plain text.** There is no rich-text field at all — seven pages of
headings, one-line summaries and short list entries, and not one of the 137 solution cards holds a
paragraph anybody would want a link inside. See the `services` branch of `contract_sanitise()`.

**Three things are drawn and never stored**, which is what keeps them from drifting out of step
with the cards they describe:

- the detail card beside each ring is that layer's **first card**, redrawn;
- every node on the ring is a projection of a card — its id, its icon, and its name for the screen
  reader;
- *"12 Solutions"* under a layer heading is the **card count**.

All three were verified against the shipped markup at the migration: 24 layers, 24 rings, no
exceptions.

**So is every line of structured data these seven pages carry**, and the last of it only since
2026-09-18. `services_schema()` builds a detail page's `Service` node and its offer catalogue from
the layers; `services_catalog_schema()` builds the site-wide `OfferCatalog` on `/pages/services/`
from the service rows. That last one was **literal JSON typed into the page**, under a comment
promising it mirrored the document — and it had drifted **twice**: a service renamed in the editor
on 2026-09-10 kept its old name here, and by the sweep the cybersecurity entry described the
practice differently from the document its own detail page renders from, so a crawler was handed two
descriptions and no way to tell which the company meant. It was also blind to the list it claimed to
mirror: a service **added** in the editor never appeared in it, and a service **hidden** went on
being advertised after its card and its sitemap line had gone. It now walks
`services_rows_shown(services_all())` — the same list `lib/seo.php` builds the sitemap from, so a URL
is in the catalogue exactly when it is a URL the site offers.

**`alternateName` is the one thing still authored in code**, as `SERVICES_ALTERNATE_NAMES`, keyed by
slug and holding one entry: *HRaaS*. It is a marketing abbreviation rather than a fact about the
service and `contract.php` has no field for it in either half — and adding one is a change to a
byte-identical shared file, which is not a thing to do in passing. Holding twenty-four derived
values literal for the sake of one authored one is the trade that had already failed twice.

**It is dropped when it equals the name**, and that is not hypothetical: the editor had renamed the
service *to* its own abbreviation, so the first version of this shipped a graph saying the practice
is called HRaaS and is also known as HRaaS. It was invisible locally — the committed seed still
holds the longer name, so every local render looked right — and was caught by reading the **live**
page after the deploy. That is the standing hazard of authoring anything in code that the editor can
also change: the seed is not the content. The comparison is folded and trimmed, so `HRaaS` and
`hraas ` count as the same claim.

**`services_breadcrumbs()` is gone.** It was superseded when `meta.breadcrumb` joined the service
row — `seo_jsonld()` builds the trail from `SEO_ROUTES`, falling back to the service's name, which
is exactly what that function did — and it had had no caller since. It was not merely unused: it
returned a **complete, standalone `BreadcrumbList`**, so wiring it back up beside the working one
would have put two trails on one page. That is the fault that had to be taken off the About page as
an `@id` collision, sitting in the codebase waiting to be reintroduced.

**Nothing in these pages names the origin any more.** `services_schema()` and
`home_service_schema()` take it as an argument, and all eight call sites had the literal
`'https://tech4time.bd'` typed out — eight chances to disagree with `SEO_ORIGIN`, which is the
constant that decides the canonical, the sitemap and every other URL on the site. They pass
`SEO_ORIGIN` now. The argument stays, because it is the seam a test needs.

**The spokes and the ring are drawn too, as SVG, by `services_map_wires()`.** They used to be a
`repeating-conic-gradient` masked to a ring plus a dashed border, which is one idea with three
faults and they are all the same fault — a gradient is not a line:

- a conic wedge is an **angle**, so its width in pixels grows with the radius. Measured on the
  shipped page, `0.4deg` came out 1.20px wide at the nodes and **0.39px at the hub**. Under a pixel
  a line does not thin, it dissolves — so every spoke faded out before it reached the hub, worst on
  the four cardinal ones, where a sub-pixel horizontal or vertical line antialiases to almost
  nothing;
- a gradient is **rasterised**, so a `0.4deg` wedge is a staircase and the diagonals read as jagged;
- both pseudo-elements paint after the `<li>`s that are the nodes, so the dashed ring sat **on top
  of the icons**.

The function needs to know none of the ring's sizes, and that is deliberate: the stylesheet sizes
the `<svg>` to **the ring**, so inside it a node is always at radius 50 and the ring is always the
inscribed circle. `--size`, `--radius` and `--node` stay in one file. A spoke runs from the centre
to its node's own centre, so it cannot fall short whatever size the ring is — the inner half is
covered by the hub, which is opaque and painted after it.

**A solution card's id is stored, never minted from its name.** Sixty-three of the 137 cards carry
an id that does not follow from the card's title — `sol-cloud-design-private-cloud` on a card
called *"Private Cloud Design & Implementation"*. They were written by hand, and they are the
fragment a saved deep link holds the card by, so `services_identify()` leaves a real id alone and
mints only into empty ones.

**The index's group lists are authored, not derived from the detail pages.** The index says
*"Offensive Security & Penetration Testing (Metasploit, Burp Suite)"* where the detail page says
*"Offensive Security & Penetration Testing"*, and the HRaaS block lists four engagement models
against the detail page's thirty-three resource types. They are two different summaries of one
practice; flattening them into one would lose the shorter.

**`tools/apply_reveals.py` does not govern these pages**, for the reason it does not govern the
home page: it skips anything built with a PHP loop. The reveal markers are emitted by the renderer.

### `certifications.php`

`certifications_load()` · `certifications_save()` · `certifications_validate()` *(backend)* ·
the renderers *(frontend)*

One document, `content/certifications.json`, holding the page's four role groups, the ten role
names spread across them and the fifty-four certifications inside them. Groups, roles and
certifications are each a list: any of them can be added to, reordered, renamed or hidden, and a
role group added in the editor arrives hidden so a half-filled category is never live.

**Every field is plain text.** No rich text anywhere on the page — see the `certifications` branch
of `contract_sanitise()`.

**Three things are drawn and never stored:**

- *"27 certifications"* on a group heading is the count of the certifications **shown** inside it;
- every certification's glyph is one constant, `CERTIFICATIONS_CERT_GLYPH` — all 54 carry the same
  one and always did, so a per-certification icon field would be 54 chances to disagree;
- the `/` between two role names is emitted between them, as markup rather than text, because a
  screen reader should hear two roles and not a fraction.

**The totals in the prose are drawn too.** The lead and the meta description hold
`{certifications}` and `{groups-word}`, which `certifications_fill()` replaces as the page
renders. A typed number goes stale the moment somebody adds a certification, and nothing on the
page or in any check would notice — it is a true sentence that has quietly stopped being true.
Both digit and spelled forms exist because the page writes one of its numbers as a numeral and the
other as a word, and a token that could only produce digits would have reworded the page.
See `CERTIFICATIONS_TOKENS` in `contract.php`.

**The icons come from a second sprite.** A group's glyph is chosen in the editor, so
`inject_icons.py` cannot see it; `certifications_sprite()` emits what the document actually uses,
the same arrangement `services.php` has and for the same reason.

**`certifications_page_schema()`** hands the page to a crawler as a `CollectionPage` whose
`mainEntity` is an `ItemList` of `EducationalOccupationalCredential` — the type for a qualification
a **person** holds, which is what this page lists and what the About page's accreditations band is
not. That band is what the **company** holds and carries `Certification`; the two pages use the
word *certification* for two different things and these are the two types that keep them apart.
The list is flat rather than nested by group, because the grouping is how the page is *read* while
the credential is the thing being claimed; the roles survive as `occupationalCategory`, which is the
part of the grouping that is a fact about the credential rather than about the layout. It walks
`certifications_rows_shown()` at every level, so a hidden group and a hidden certification reach the
graph exactly as far as they reach the page — none. Its `description` is the **filled** one, through
`certifications_fill()`, so the graph cannot describe the page differently from the page's own
`<meta>`.

### `branding.php`

`branding_load()` · `branding_save()` · `branding_validate()` *(backend)* · the renderers *(frontend)*

One document, `content/branding.json`, holding the page's logo variants, the files each one offers
for download, and the disclaimer. Variants and their files are each a list: either can be added to,
reordered, renamed or hidden, and a variant added in the editor arrives hidden so a half-filled
card is never live.

**The preview and the download are not the same picture.** `image` is the small thing drawn on the
card; `files[]` is what a visitor came for. On the page as it ships those are an 800px preview and
a 1600px download of the same mark, so collapsing them would either serve the big file to everyone
who merely looks at the page or hand out the small one to everyone who came for the logo.

**Three things are drawn and never stored:**

- the dimensions in a meta line come off that file's own record, so they cannot claim
  *1600 × 570* about a file that is no longer that size — the adjective beside them
  (*"Transparent"*) stays authored, because that part is editorial;
- *"Download PNG"* states the file's own format, read from its extension;
- the glyph on every button is one constant, `BRANDING_DOWNLOAD_GLYPH`.

**The disclaimer is rich text**, and the only rich text on the page — see the `branding` branch of
`contract_sanitise()`. It is a legal notice, and the sentence asking a rights holder to get in
touch is a link waiting to happen.

**The breadcrumb carries its own name.** Unlike the about, company and certifications pages, whose
breadcrumb follows `hero.title`, this page is titled *"Branding Assets & Guidelines"* and named
*"Branding & Advertisement"* everywhere it is linked from. Both are authored; see `meta.breadcrumb`.

**No second sprite.** Nothing on this page picks an icon at run time, so `inject_icons.py` sees
every glyph it draws.

**`branding_page_schema()`** is the press kit as data: a `CollectionPage` whose `mainEntity` is an
`ItemList` of `ImageObject`, one per shown variant, each carrying its downloadable files as
`encoding` `MediaObject`s with the MIME type `branding_media_type()` reads off the extension. This
is the one page on the site whose *purpose* is to hand out files, and until now a crawler could see
that it had pictures on it but not that any of them were offered for download. Hidden variants and
hidden files are absent, by the same rule as everywhere else.


### `privacy.php`

`privacy_load()` · `privacy_save()` · `privacy_validate()` · `privacy_facts()` *(backend)* · the renderers *(frontend)*

One document, `content/privacy.json`, holding the whole privacy policy: twelve headed sections, a
summary callout, a retention table and an address block. It was the last hand-written page on the
site, and the one that most needed not to be — a privacy policy is the page most likely to need a
correction at short notice, and every correction used to need a developer and a deploy.

**Structure is a kind, not markup.** `rt_sanitise_html()` allows nine tags and no heading, no
`<address>` and no `<table>` among them, so structure cannot live in a rich field: somebody typing
`<h3>` into one would watch it disappear on save with no way to tell that from a bug. Each block
instead declares which of six kinds it is — `paragraph`, `list`, `subheading`, `note`, `address`,
`table` — and the renderer owns the markup. See `PRIVACY_BLOCK_KINDS` and `privacy_block()`.

**Every rich field is inline-only**, through `rt_sanitise_inline()`. All of them render *inside* an
element the renderer supplies — a `<p>`, a `<p class="legal__notice">`, an `<address>`, an `<li>` —
so a `<p>` arriving from the editor is not emphasis somebody added, it is a paragraph inside a
paragraph. Pressing Enter in a textarea is how it would arrive, which is not a corner case.

**Three lists deep**, one deeper than any editor before it: sections hold blocks, and a list, an
address or a table holds rows. The verbs carry the parents in the band name — `block-3-up:2`,
`row-3-2-remove:1` — because the index is cast to an int.

**A section's id is its anchor, and an anchor is a promise.** Ids are assigned by
`contract_identify_rows()`, which claims every id somebody already chose *before* it mints anything
new. The one-pass version has a bug that only bites a page whose ids are anchors: a section added
above an existing one with the same heading takes the existing one's fragment, and the incumbent is
silently renamed. Nine of the twelve shipped ids are hand-authored and are not what the slug
algorithm would produce, so there is nothing to recover them from.

**The effective date is never stamped.** `updated` records when the document was last published; an
effective date is a claim about when the *policy* changed. Fixing a typo is not a new policy, so
nothing writes that field but a person.

**The policy band cannot be hidden.** `PRIVACY_BANDS` holds only `cta`. Hiding the policy would
leave a page headed *"Privacy Policy"* with no policy on it, still linked from the footer of every
other page and still in the sitemap — not a configuration anybody wants. The callout and any
single section can be hidden.

**What it repeats from the contact page is compared, never enforced.** The policy states the
offices, the email and the telephone, and so does `content/contact.json`. `privacy_shared_facts()`
asks by containment whether the policy still states the current values, on a normalised form —
`&nbsp;` and whitespace collapsed, commas dropped, case folded — so it reports a different street
and stays quiet about a different comma. The editor draws it as a standing notice.
**It never refuses a save**: after an office move whichever page you edited first could not be
saved, and an unrelated typo fix would be blocked by an address that drifted months earlier.

**`privacy_policy_schema()` describes the policy, not the page.** schema.org has no type for a
privacy policy — none of `WebPage`'s subtypes is one — so a second `WebPage` node here would put two
records on one URL saying the same things, which is the exact fault that had to be taken off the
About page. `seo_page_node()` already describes the page; this describes the *document* it displays,
as a `CreativeWork` with its own `#policy` id, published by the organisation and in force from a
date. **The date is the reason it exists.** *"Effective 21 August 2026"* is the one fact on the page
a machine would want and could not read: `dateModified` is when the document was last **published**,
which is not when the policy took effect and can differ by months.

**`privacy_effective_date()` cuts the date out before parsing it.** Measured: `strtotime()` reads
*"21 August 2026"* and returns false for *"Effective 21 August 2026"* and for *"Last updated: 3
March 2024"* — so handing it the whole field would have meant the feature never fired on the wording
the policy actually uses, which is a feature that silently does nothing. Three patterns cover the
real phrasings, **each requiring a day**: *"In force since 2026"* names a year and no date, and
turning that into the first of January would be inventing one, so it returns `''` and the graph
carries no `datePublished` at all. The parsed year is then checked back against the text, which is
what stops a string `strtotime()` only half understood from quietly becoming **today's** date on
every render — a lie that refreshes itself. A guessed `datePublished` is worse than none.

### `svg.php`

**Shared — byte-identical in both repositories.**

`svg_sanitise()` · `svg_problem()` · `svg_looks_like()`

What a publishable vector file is. ADR 0019 refused SVG outright and its reasoning was right — an
SVG is a document, and re-encoding does not make it not one. This answers that rather than avoiding
it, twice over.

**It is read and replaced, not checked and kept.** The same rule the raster path follows: the file
is parsed into a DOM, walked against an allow-list, and re-serialised, and what is stored is *that*
— never the bytes that arrived. Anything outside the list makes the whole file refused, with a
sentence naming what was found, because silently dropping an element would hand somebody back a
different logo than the one they published.

**And it is never served as a document.** `/uploads/*.svg` goes out with `Content-Disposition:
attachment` and `default-src 'none'; sandbox` on both hosts, so it downloads and never renders in
this origin. The branding page links to it and no page ever draws one.

**It is idempotent, and that is load-bearing.** `svg_sanitise(svg_sanitise(x))` equals
`svg_sanitise(x)`. The receiving host relies on it: it sanitises what arrived and refuses anything
that is not already its own output — proving the bytes are clean *without changing them*, which it
could not do otherwise, because the file's name is a hash of its contents and both hosts compute it
independently.

**It needs `ext-dom`**, which the live hosts have and Ubuntu's `php-cli` does not. `svg_problem()`
says so plainly and the byte-level refusals still hold without it; CI installs `php-xml`.

### `publish.php`

**Shared — byte-identical in both repositories.**

`publish_problem()` · `publish_fingerprint()` · `publish_envelope()` · `publish_body()` ·
`publish_sign()` · `publish_verify()` · `publish_check_envelope()` · `publish_reason()`

The format content travels in, and only the format — sending is
[`publish_client.php`](#publish_clientphp), receiving is the frontend's `api/publish.php`. Full
description: [the publish API](publish-api.md).

The four checks are not interchangeable, and it is worth knowing which does what:

| check | answers |
|---|---|
| the signature | this came from something holding the key — **not** that it is safe |
| the timestamp | it was sent in the last five minutes |
| the revision | it is newer than what is here — this is what makes a replay a no-op |
| `contract_version` | this side implements the shape it is written in |

The key is `publish.key` in the private store: 32 random bytes, **the same bytes on both hosts**,
never derived from `secret.key` (the two stores have different master keys, so anything derived
would differ by construction). It is never created on demand — see
[`make_publish_key.py`](../../40-reference/tools.md).

### `seo.php`

`seo_load()` · `seo_site()` · `seo_identity()` · `seo_notfound()` · `seo_breadcrumb()` ·
`seo_sitemap_entries()` *(frontend)* · `seo_edit()` · `seo_meta_edit()` · `seo_validate()` ·
`seo_pages()` *(backend)*

One document, `content/seo.json`, and it is deliberately **not** where a page's own metadata
lives. Every page's title, search description, share title, breadcrumb, crawl setting and sitemap
row are in **that page's own document**, in the `meta` band every document has — the About page's
title is in `content/about.json`, beside the About page's content, and always was. What moved is
the editing: one screen, `?s=seo`, edits all of them. See
[ADR 0020](../../90-decisions/0020-page-metadata-is-content.md).

What `content/seo.json` holds is what belongs to the site rather than to any one page: the
Organization / WebSite / ProfessionalService graph, the default share card, the colours, the
`<html lang>`, `robots.txt`'s extra rules, the search-console verification tokens and the web
manifest — plus the 404's own record, because that page renders no content document and never
will.

**`SEO_ROUTES` is code, not content.** It maps a route key to an address, a name and the document
that holds that page's `meta`. The editor cannot add, rename, remove or reorder a row: adding a
page stays a code change, and its card then appears by itself, which is what makes it impossible
to orphan a record or point one at a URL that does not resolve. The service pages are not in it,
because a service is a row of `content/services.json` and a seventh can be added at any time — its
metadata is its row's own `meta` band, so there is nothing to keep in step and a slug rename
cannot orphan anything.

**Sitemap membership is derived from `robots`.** One control, not two, so a page cannot be listed
in the sitemap and asking not to be indexed at the same time — which is a warning raised against
the whole file.

The backend copy adds the two saves. `seo_edit()` writes `content/seo.json`; `seo_meta_edit()` and
`seo_service_meta_edit()` write **one band of another document** under `store_edit()`'s lock,
because the screen holds one band of a document whose other twenty were never in the form. A
whole-document rebuild there would empty the page.

### `settings.php`

`settings_load()` · `settings_icon()` · `head_styles()` *(in `head.php`)*

One document, `content/settings.json`, holding the four things every page depends on and no page
owns: the logo, the square mark the favicons are made from, the colour tokens the site is drawn
from, and the address the contact form sends to. Until it existed none of them could be changed
without a developer — the logo was twelve committed files and a Python script, the favicon another
eight and another script, the colours were literals in a stylesheet, and the address was a constant
in the handler.

**One mark, nine consumers, and now one document.** The logo is read from `content/settings.json` by
the header, the footer, the About page's lockup, `Organization.logo`, every job posting's hiring
organisation, the branding kit, the favicon set and the admin's own rail. It used to be typed into
`content/chrome.json` as eleven text fields per part — twice, for the header and the footer — while
the other seven named committed files nobody could reach from any editor at all. **The SEO screen
already had a working logo upload that was completely disconnected from the header**, so changing
one left the other showing the old mark with nothing comparing them.

What stays in the chrome is the **alt text**, which is genuinely the chrome's: the header's and the
footer's are different sentences about the same picture. `identity.logo` on the SEO screen stays as
an **override** — empty means the site's mark, filled wins — because Google renders
`Organization.logo` in a near-square slot and this lockup is nearly three to one.

**It is its own document because none of it belongs to a page.** The logo alone is drawn in the
header, the footer and the About page, and named in `Organization.logo`, in
`JobPosting.hiringOrganization.logo`, in the favicon set, in the branding kit and in the admin's own
rail. Putting it in any one page's document would make the other eight consumers read a document
about something else.

**Reading the identity is in [`contract.php`](#contractphp), not here, and that is a correction.**
`settings_logo()` (which mark for which theme, falling back to the light one), `settings_logo_largest()`,
`settings_logo_is_shared()`, `settings_logo_is_uploaded()`, `settings_logo_is_mismatched()`,
`settings_icon_is_stale()`, `settings_share_is_stale()` and `settings_colours()` are pure
functions of the document — none reads a file, emits markup or knows
which host it is on — and **both halves render the mark**: the public site draws it in the header,
the footer and the About row; the editor draws it in its own rail and on its sign-in page. Putting
them on the renderer's side got `settings_logo_is_shared()` written out twice within the hour, which
is the drift the shared file exists to prevent. What is left in each half is that half's own
business: the file path and the read here, plus the save, the validation and the screens over there.

**A missing file is not an error, and here that matters more than anywhere.** `settings_normalise()`
fills from `settings_defaults()`, which is the site's own mark, icons and colours exactly as they
ship — read off the files they replace rather than typed, so a host that has never received a
publish renders what it renders today, byte for byte.

`settings_logo_largest()` is for the three consumers that want **one** file rather than a ladder:
the About page's lockup, which draws the mark at up to 693 px, and the two structured-data graphs,
which are read by consumers that pick nothing from a candidate list. Its height is scaled from the
record's, which is exact rather than approximate — every rung is the same picture, so 128 × 540 ÷ 360
is 192 on the nose.

`settings_logo()` returns the light mark when the dark half is empty, and `settings_logo_is_shared()`
says when that is happening. **An empty dark half is an answer, not an omission**: plenty of marks
are one colour and read on both grounds. What must never happen is the other reading — an empty
half rendering as *nothing*, which would put a hole in the header of every page in dark mode. The
editor carries a standing notice saying which case it is in, because only the person who drew the
mark knows whether theirs reads on a dark ground.

**Three more predicates report the ways an identity can be half-changed**, and all three are
notices rather than refusals, because each is also what a correct half-finished edit looks like.

`settings_logo_is_mismatched()` is the quiet one and the worst. One half replaced and the other
still holding the *previous* mark renders two different logos, one per colour mode — and the person
who uploaded it is in one mode and will never see the other. It is symmetric: replacing only the
dark half is rarer and exactly as wrong. An **empty** half is excluded, because that is
`settings_logo_is_shared()`'s condition and two notices about one field is noise.

`settings_icon_is_stale()` says the logo was replaced and the tab icon was not. They are separate
uploads on purpose — a wordmark three times as wide as it is tall becomes a smear at sixteen
pixels — so changing one cannot change the other, and somebody who has just replaced their mark
will expect it to have.

`settings_share_is_stale()` says the same about the share card, and takes the **seo** document as
an argument rather than reading it: this file is shared with a repository whose copy of `seo.json`
is a replica and whose copy of this function is never called. Nothing generates that card —
drawing it would mean reimplementing typography against a font stack the server does not have — so
the only failure left is the logo moving and the card not, which nobody sees on the site itself.
It is visible only in somebody else's chat window.

### `chrome.php`

`chrome_load()` · `chrome_header()` · `chrome_footer()` · `chrome_dock()` ·
`chrome_target_list()` · `chrome_link()` · `chrome_services()` · `chrome_social()` ·
`chrome_contact_groups()` · `chrome_contact_href()` · `chrome_is_current()` ·
`chrome_icons_used()` · `chrome_sprite()`

One document, `content/chrome.json`, holding the furniture around every page: the header's logo
and nav, the footer's four columns, and the small-screen dock. It was literal markup in seventeen
page files — about 6,800 lines of duplication kept in step by a propagation script — and the
duplication had already produced three live defects: a footer service list that disagreed with
`content/services.json`, a service that could never appear in the footer at all, and phone numbers
that went stale because a script had to be run by hand before a deploy.

**A link points at a route, never at a URL.** Every destination is a key of `chrome_targets()` in
`contract.php` — `about`, `service:cybersecurity` — built from `SEO_ROUTES` and
`content/services.json`. There is no way to type an address into a nav link, so a nav link cannot
404, in the one component that appears on every page. An empty label means *whatever that page
calls itself*, which is how renaming a page in `?s=seo` renames it in the header, the footer and
the dock at once.

**Two columns of the footer store nothing.** The services list is read from
`content/services.json`, so a seventh service appears by itself and a hidden one goes; the social
links are read from the SEO document's `sameas` rows, so a profile URL is changed in one place and
the footer cannot disagree with the Organization graph.

**The footer's contact rows deliberately are not.** They are the footer's own — added, worded,
ordered, shown and hidden on the footer screen — and owe nothing to `content/contact.json`. The
contact page holds every detail in full; a footer holds the part worth putting in a footer. What
keeps the two honest is a notice the editor draws, never a refusal, for the reason the privacy
policy's duplicated facts are reported rather than forbidden: requiring the two to agree before
either could be saved means that after an office move, whichever page you edited first could not
be saved.

**A missing file is not an error.** `chrome_load()` fills from `chrome_defaults()`, which is the
site's own header, footer and dock as they shipped, extracted from the markup rather than typed.
A host that has never received a publish still renders a correct page — the failure that avoids is
the whole site losing its navigation because one file did not arrive.

### `head.php`

`seo_head()` · `seo_jsonld()` · `seo_graph()` · `seo_offices()` · `seo_lang()` — frontend only.

The `<head>` of every page, emitted once. It used to be pasted: seventeen copies of between 222
and 308 lines, about 4,250 lines in all, with no propagation tool and no drift check over any of
it — `check_shared_markup.py` covered the header, footer and dock, and the head was never in that
set. (Those three went the same way afterwards, into `body.php`.)

It drifted exactly as that guarantees. The Organization graph carried three office addresses and
four telephone numbers as literal JSON in sixteen of the seventeen heads; only the contact page
rendered them from `content/contact.json`. Editing an office in the admin left sixteen pages
advertising the old one, and a build script — `sync_site_contact.py`, since deleted — was written
to paste the new values back before a deploy. The graph is built here now, on the request, from the
document that owns the facts.

A page hands in **its own address** and **its own `meta` band**:

```php
seo_head('/pages/about/', $data['meta'], ['pages/about.css'], $data['updated']);
seo_jsonld('/pages/about/', $data['meta'], $data['updated']);
```

The address rather than a key, because a service page's address is a row's slug and is in no
constant — and because the canonical is then the argument itself, which `audit_pages.py` checks
against the directory the file actually sits in. A miscopied argument is the one mistake an
emitted head makes possible, and that is the check that catches it.

Nothing editable reaches the head unescaped, and the canonical, the CSP, the favicon list, the
font preload and the stylesheet order are code. An editor able to break the Content Security
Policy is a hazard, not a feature.

### `publish_client.php`

**Backend only.** `publish_push()` · `publish_endpoint()`

Sends one document and returns what the editor should show. Never throws for a network problem: an
unreachable site is a thing to report in the editor, not a stack trace over a form somebody has just
filled in.

The certificate is verified and there is no option to turn that off; redirects are not followed,
because a redirect on this route would post a signed document wherever it pointed.

`$T4T_PUBLISH_URL` overrides the endpoint — how `test_publish.py` points it at a local server.

### `sprite.php`

**Frontend only.** `sprite_block()`

The icon block a renderer writes for itself, in one place.

`tools/inject_icons.py` reads a page's **source** for `<use href="#name">` and inlines the matching
`<symbol>`s, because Chromium and WebKit do not resolve `<use>` into another document. That works
for every icon a page writes literally and cannot work for one chosen while the page renders — a
service's icon is a field of `content/services.json`, a dock key's is a field of
`content/chrome.json`, and neither appears in any page's source.

So `services.php`, `certifications.php` and `chrome.php` each work out **which** symbols they need
and hand the list here. It was written three times before it was written once: the first two copies
were byte-identical, which is where a third is one too many.

The markers are deliberately **not** `icon-sprite:start/end`. Those delimit the block
`inject_icons.py` rewrites, and a second pair would make its non-greedy match end in the wrong
place — it would swallow everything between the first start and this end, header included.

Two `<symbol>` elements may share an id and several do — the chrome's `#cogs` and a service card's
`#cogs` are the same markup from the same file. The first definition wins, the page renders the
same, and `audit_pages.py` exempts symbol ids from its duplicate-id check for exactly this reason.
[icons.md](../frontend/icons.md)

### `body.php`

**Frontend only.** `body_header()` · `body_footer()` · `body_dock()`

The header, the footer and the dock, emitted once instead of pasted seventeen times. Named as
`head.php` is named, and for the same reason: that file emits the shared part of `<head>`, this one
emits the shared parts of `<body>`.

A page calls three functions and passes the same address it hands `seo_head()`:

```php
<?php body_header('/pages/about/'); ?>
<main class="page__main" id="main"> … </main>
<?php body_footer(); ?>
<?php body_dock('/pages/about/'); ?>
```

The route decides one thing — which link carries `aria-current="page"`. It is applied to one nav
link and to at most one dock item and key, and **never to the brand**: `propagate_shared.py`
re-marked every `<a>` whose href a page already marked, which is why `index.php` used to send
`aria-current` on its logo link as well. A prefix counts, so on
`/pages/services/cybersecurity/` the marked link is Services; `/` is excluded from the prefix rule,
or Home would be current everywhere. The 404 passes `''` and marks nothing.

**Nothing here reads a document and nothing here decides what a link says.** That is `chrome.php`.
This file writes tags, escapes every value that goes into one, and holds the markup that is code
rather than content — the landmarks, the class names, the dock's circuit, the CSS hooks the scripts
bind to.

`body_header()` also emits the chrome's icon sprite, because it is the first of the three to run and
a `<symbol>` has to exist before the `<use>` that draws it.

[shared-markup.md](../frontend/shared-markup.md) ·
[ADR 0023](../../90-decisions/0023-the-header-and-footer-are-emitted-once.md)

---

## The sign-in

Full design: *authentication.md* (in tech4time-website-backend).

### `private.php`

`t4t_private_dir()` · `t4t_private_path()` · `t4t_master_key()` · `t4t_key()` · `t4t_assert_outside_document_root()`

Where the secrets are, and where every key comes from.

`t4t_private_dir()` resolves the store, **refuses if it is inside the document root**, creates it
0700, and caches the result. The containment check runs *before* `mkdir` — a safety check that
leaves a new folder in the web root on its way out is doing the opposite of its job — and again on
the resolved path, because `realpath()` follows symlinks.

`t4t_master_key()` creates `secret.key` with `fopen(…, 'x')`, which fails if the file exists. That
makes "create only if absent" one atomic step, and the creation path is written to **lose** a race
rather than win one: regenerating the key would invalidate every stored password at a stroke.

`t4t_key($purpose)` derives a per-purpose key by HMAC. The key that peppers passwords is not the key
that hashes reset codes, and neither is the key that will sign a publish request — so a weakness in
how one is used cannot be carried into another.

### `totp.php`

`totp_secret()` · `totp_code()` · `totp_verify()` · `totp_uri()` · `totp_format()` · base32 both ways

RFC 6238, about ninety lines: base32, HMAC-SHA1 dynamic truncation, a 30-second step, 6 digits, and
one step of drift either side for a phone clock that is slightly out.

Hand-written for the same reason `html.php` is — there is nothing to install on this host and no
build step to install it with. **It is checked against all six test vectors published in the RFC**,
including the one past 2^32 that catches a 32-bit counter. That is the only reason to trust an
implementation like this one.

### `auth.php`

The largest file here. Accounts, hashing, sessions, the audit log, and the setup token.

```
accounts    auth_accounts  auth_find  auth_put  auth_defaults  auth_has_accounts
passwords   auth_pepper  auth_password_hash/verify/needs_rehash/dummy/problem
recovery    auth_recovery_make/hash/use
sessions    auth_boot  auth_session_user  auth_login  auth_logout
            auth_invalidate_sessions  auth_sweep_sessions  auth_end_session
requests    auth_csrf  auth_check_csrf  auth_fingerprint
            auth_is_https  auth_is_local  auth_is_loopback
the log     auth_log  auth_recent
setup       auth_setup_token  auth_setup_token_check  auth_setup_done
gates       auth_problem  auth_attempt  auth_second_factor
```

> **`auth_second_factor()` takes the account by reference.** It spends a recovery code and advances
> the TOTP counter on the caller's copy. It took it by value once, and `auth_login()` wrote its own
> stale copy over the top one line later — silently restoring the spent code and the old counter.
> Recovery codes worked forever and a captured code could be replayed. If you refactor here, keep
> the reference.

### `throttle.php`

`throttle_ip()` · `throttle_key()` · `throttle_fail()` · `throttle_retry_after()` · `throttle_quota()` · `throttle_clear()`

Counting attempts, so guessing costs something. Five failures are free, then each waits longer than
the last, capped at `THROTTLE_MAX_BLOCK` (one hour).

`throttle_ip()` reads `REMOTE_ADDR` and **never** `X-Forwarded-For`, which a stranger sets.
`throttle_key()` HMACs the identifier, so usernames never land on disk in the counter file.

### `reset.php`

`reset_begin()` · `reset_verify()` · `reset_finish()` · `reset_forget()` · `reset_tries_left()`

The emailed one-time code: ten minutes, five guesses, single use, and bound to the browser that
asked for it. Rationed three times an hour per account, five per address, twenty overall — the last
because cPanel caps outbound mail per hour and somebody hammering the page could use the allowance
up, stopping the genuine reset from being delivered.

### `mailer.php`

`mail_send()` · `mail_problem()` · `mail_header_safe()`

The one place mail leaves this site, so the envelope sender is set in one place. It sends with
`-f no-reply@tech4time.bd` and retries once without it, because some hosts refuse the flag outright.

> The `-f` envelope sender is what SPF and DMARC are checked against. The `From:` header is not.

---

## The admin shell

### `admin.php`

`admin_start_session()` · `admin_require_auth()` · `admin_section()` · `admin_head()` / `admin_foot()` · `admin_shell_head()` / `admin_shell_foot()` · `admin_icons()` · `admin_csrf()`

The section registry, the icon rail, the page furniture, and the gate.

`ADMIN_SECTIONS` is the registry the rail draws itself from — adding an editable page is a row here
plus a file beside the others. `ADMIN_PAGE_SECTIONS` names the subset that edits a page of the
website, so anything counting "the pages you can edit" asks here rather than filtering the registry
by hand in three places.

`admin_shell_*` are the furniture for the pages that have **no** session yet — login, forgot, reset,
setup. They exist because `admin_head()` fatals on a section that is not in the registry, and those
pages are not sections.

*adding-an-editor.md* (in tech4time-website-backend)

---

## Adding a library

Rare. Most things belong in an existing file.

If you do: a header comment saying **what it owns and why it exists**, `declare(strict_types=1)`,
functions prefixed with the file's concern, no global state beyond a `static` cache, and no output.
Then add it to the table at the top of this page — `check_docs.py` fails until you do.
