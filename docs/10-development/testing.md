# Testing

**Applies to:** both

What each check proves, when to run it, and how to read a failure.

There is no test runner and no config file. Every check is a Python script you run directly, exits
non-zero on failure, and prints what failed rather than that something did.

---

## Before every commit

Fast, no browser needed. Run all seven.

```bash
python3 tools/check_contrast.py        # WCAG AA in both colour modes
python3 tools/inject_icons.py --check  # every page's inlined icon block is current
python3 tools/check_shared_markup.py   # no page's copied markup has drifted
python3 tools/check_content_model.py   # model, form and renderer still agree
python3 tools/check_secrets.py         # nothing secret committed; no protection removed
python3 tools/check_docs.py            # the docs still describe the code
python3 tools/audit_pages.py           # SEO, accessibility, structure, internal links
python3 tools/build_deploy_set.py --check   # nothing secret or local is bound for the server
python3 tools/check_shared_lib.py
python3 tools/check_shared_facts.py    # the offices, email and phone the policy repeats from the contact page
python3 tools/check_shared_repos.py      # the three files both halves hold identically
python3 tools/check_form_dom.py        # no script reads a property a form's own control hides
```

> **Half the suite is in the other repository.** The editor's round trips, the sign-in and the
> browser run over the admin went with the admin. Neither list is the whole suite any more, and
> neither pretends to be — `check_content_model.py` and `check_docs.py` each say which half they
> ran and name the repository that does the other.

## When you touched the publish endpoint, the contract or the contact handler

```bash
python3 tools/test_publish.py          # the one endpoint that writes, over HTTP
python3 tools/test_publish_asset.py    # the other one — pictures arrive there
python3 tools/test_sitemap.py          # the three files that are generated, not stored
python3 tools/test_svg.py              # the SVG sanitiser, which is a security boundary
python3 tools/test_contact_handler.py  # the enquiry form's endpoint
python3 tools/test_store.py            # the JSON store itself
```

Touched `lib/contract.php`, `lib/publish.php`, `lib/html.php` or `lib/svg.php`? Then also:

```bash
python3 tools/check_shared_lib.py --update    # re-record the digests
# then copy the changed file AND tools/shared-lib.sha256 into tech4time-website-backend,
# and bump CONTRACT_VERSION if the SHAPE of a document changed
```

## When you touched the logo, or anything that draws it

```bash
python3 tools/test_settings.py         # one mark, every place it is drawn
python3 tools/audit_pages.py           # and the pages still audit
```

The mark is in nine places across two repositories, and the whole point of
`content/settings.json` is that they are one. `test_settings.py` changes it once and reads it back
out of the header, the footer, the About lockup, `Organization.logo` and the job postings — plus the
case a fresh clone is in, where the document is **missing** and the site still has a logo.

## When you touched a picture, or how wide one is drawn

```bash
python3 tools/test_pictures.py         # what a ladder turns into on the page
python3 tools/audit_pages.py           # and that no page ends up with one it cannot use
```

`test_pictures.py` puts a ladder into each document and reads the markup back, because
`audit_pages.py` audits the documents *as they are* and none of them holds one yet — so the rule it
enforces has nothing to enforce it on until somebody uploads a picture. Both halves matter and the
second is the one that is easy to lose: **a `srcset` of widths with no `sizes=` beside it is a
regression, not a missing improvement.** The browser is required to assume the picture fills the
viewport, so it takes the widest rung on every screen.

Changed a number in `CONTRACT_IMAGE_SLOTS`? **Measure it, do not estimate it** — "If you are
measuring geometry" below has the technique, and three of the eight are not where anybody would
guess.

## When you touched the header, the footer or the dock

```bash
python3 tools/test_chrome.py           # what every page carries, from content/chrome.json
python3 tools/audit_pages.py           # the same markup, audited as a visitor receives it
python3 tools/check_shared_markup.py   # the emitter still carries the scripts' data- hooks
```

`test_chrome.py` is the one that reads the **words**; `audit_pages.py` is the one that reads the
**structure**, and neither is a substitute for the other. Run `check_responsive.py` too if you
changed the markup rather than only the document — it measures the nav at the widest the picker
can make it, and the logo at every shape somebody could upload, and neither is a width a shipped
document produces.

## When you touched CSS, markup or motion

Needs Firefox and geckodriver. Slower.

```bash
python3 tools/test_motion.py           # every page, scrolled end to end
python3 tools/test_nav.py              # navigation at both widths
python3 tools/test_theme.py            # the theme switch, with a real OS preference
python3 tools/check_hover.py           # a real pointer over every kind of control
python3 tools/check_dark_mode.py       # every page as the browser actually paints it
python3 tools/check_responsive.py      # sideways scroll and tap targets, 320px and up
python3 tools/check_focus.py           # tab every page: the ring is visible and uncovered
```

> Interrupted browser runs leave processes behind. `pkill firefox geckodriver` clears them.

---

## What each one actually proves

### The static checks

| Script | Proves |
|---|---|
| `check_contrast.py` | every text/background pair in `theme.css` meets WCAG AA, in both modes, including the 3:1 bar for component boundaries |
| `inject_icons.py --check` | each page inlines exactly the icon symbols it references — no missing symbol, no dead weight |
| `check_shared_markup.py` | every page's hero circuit and script block is byte-identical to `tools/templates/`, and `lib/head.php` and `lib/body.php` still emit the script hooks whose absence nothing else would notice |
| `check_content_model.py` | the model, the editor form and the page renderer describe the same fields — **in both directions**, so a field dropped from the page but left in the form is caught; and that every editor in `ADMIN_PAGE_SECTIONS` is checked either here or by a named test that exists |
| `check_secrets.py` | no secret is committed; the private store still refuses the web root; no auth bypass constant has returned; cookie flags intact; no password reachable by the audit log; every admin page shape noindexed |
| `check_docs.py` | every tool, library and admin section is documented; no doc cites a path that does not exist; no internal link is broken; no doc quotes a constant that has changed |
| `audit_pages.py` | per page: title and meta description — **unique site-wide, and within the model's own `SEO_TITLE_MAX` / `SEO_DESC_MIN` / `SEO_DESC_MAX`, read from `lib/contract.php` rather than retyped here** — heading order, `alt` text, landmark roles, no repeated `id`, a label on every form control and an accessible name on every link and button, structured data, internal links resolve, the markup nests, and **nothing carries an inline `style=` or `on…=` attribute** — the CSP refuses those silently, so the page looks right and the behaviour is simply gone. Also that **each page's canonical is its own directory URL**: the head is emitted from one function now, and a copied `seo_head()` call with the wrong route is the single mistake that makes possible |
| `build_deploy_set.py --check` | the upload set holds no `content/`, `tools/`, `docs/` or key, keeps the `.htaccess` that blocks them, and carries a seed for **every** document the contract defines, with no job posts in the careers one |
| `verify_live.py <url>` | run **after** a deploy, against the real host: the pages answer 200, `lib/`, `content/`, `tools/` and `/.git/` answer 403, and the security headers are present. It also reads three **bodies** and one **redirect**, because a status alone cannot tell a generated sitemap from a stale file — and because `.htaccess` is the half of the services route that no local test can reach |

### The HTTP tests

These start a real PHP server on a spare port and drive it over HTTP.

| Script | Proves |
|---|---|
| `test_publish.py` | `api/publish.php` driven over real HTTP with real signatures: the happy path, then **every way past it that does not involve holding the key** — no signature, another key's signature, a tampered body, an old timestamp, a replay, a lower revision, a different `contract_version`, and a `<script>` from a sender that signed correctly. Also that **every field the model declares reaches the visitor**, by sending a marker through each one and reading it back off the public page. And that **a chrome document published from the editor reaches every page** — a marker in the nav, the footer's headings, its contact rows, the copyright and the dock comes back out of an ordinary page, a hidden nav row does not, the services column is read from `content/services.json`, and the brand link on the home page carries no `aria-current`. And that **a service added in the editor becomes a page**: an address with no file behind it, its own head, a line in the sitemap, a link from the index, and a 404 when it is hidden or removed. For the privacy policy it also checks that each of the **six block kinds** is drawn as the kind it is — a subheading as an `<h3>`, a table with scoped headers, a note as the tinted paragraph — that the blocks stay **flat** children of `.legal__body` so `:first-of-type` still matches, and that a fragment link into the page keeps its `href` |
| `test_sitemap.py` | `sitemap.php`, `robots.php` and `manifest.php` — the three files served at an address that looks static and is not. That each answers with the right `Content-Type`; that the sitemap is well-formed XML listing **exactly** the indexable routes, so a page set to `noindex` and a hidden service both leave it; that `lastmod` is a real publish stamp or absent rather than today's date; that `robots.txt` cannot lose its `Allow: /` or its `Sitemap:` line, and never names the editor's address; and that the manifest is valid JSON |
| `test_chrome.py` | the header, footer and dock, rendered from `content/chrome.json` by `lib/body.php` and read back off five real pages. A hidden row is **absent**, not faint; `aria-current` lands on exactly one nav link and never on the brand; the header and the dock agree about where you are, including on a service page, where it is the Services link that is marked; the footer's services column follows `content/services.json`, losing a service hidden after the fact; a contact row draws the `tel:` or `mailto:` form its `kind` calls for. And the group that matters most on a fresh clone: **every page renders correctly with `content/chrome.json` deleted**, because `chrome_normalise()` falls back to `chrome_defaults()` band by band — and its opposite, that a band emptied on purpose is left empty, because the two failures are in opposite directions and a fix for one is how you write the other |
| `test_contact_handler.py` | method check, honeypot, every validation rule, CR/LF injection into each field, the assembled message, non-ASCII round trips, the rate limit, and the no-JavaScript HTML response |
| `test_store.py` | `lib/store.php`: telling apart missing, unreadable and corrupt; the atomic write; and the rule that a damaged file is never copied over a good `.bak`, because the backup is what damage is recovered from |

`test_contact_handler.py` captures outgoing mail by pointing PHP's `sendmail_path` at a script that
writes the message to a file, then reads back the exact bytes `mail()` was asked to send. That is
what lets it assert the header-injection defences work rather than merely look right. **It does not
test delivery**, and cannot — that needs a real mail server, and is proven on the host with
`tools/host-probe.php`.

**`test_publish.py` signs in Python, deliberately.** A test that asked `lib/publish.php` to sign what
`api/publish.php` then verifies would prove the two agree with each other and nothing about whether
either is right. It is a second implementation of the format from its written description, so the
endpoint and the backend's client must both match the same third thing. The backend has the mirror:
its client posts to a stub endpoint written in Python. Neither side is ever checked against its own
counterpart.

Every test runs against a **copy** of the real data files, restored afterwards whether the run
passes or fails, and against a private store in a throwaway directory under `/tmp`.

### The browser tests

| Script | Proves |
|---|---|
| `test_motion.py` | every page scrolled end to end, with every reveal-marked element required to finish opaque — the reveal never leaves anything unread |
| `test_nav.py` | navigation is usable at desktop and mobile widths, with a keyboard as well as a pointer. Its link counts are **read from `content/chrome.json`** rather than typed, so editing the nav does not silently retire the assertion — `CHROME_BAR_SLOTS` stays a literal, because four slots is code and not content |
| `test_theme.py` | the theme switch honours an explicit choice, falls back to the OS preference, and survives a reload without a flash |
| `check_hover.py` | every interactive element visibly responds to a real pointer |
| `check_dark_mode.py` | every page in both themes, as painted — catching what a CSS reader cannot, like a token that resolves to the same colour as its background |
| `check_responsive.py` | every page at 320, 360, 414, 640, 768, 1024 and 1440px: the document does not scroll sideways, no link, button or field is wider than the screen, and no tap target is under 24px. Each width is a frame, not a window — see [0015](../90-decisions/0015-narrow-widths-need-a-frame.md), because Firefox silently clamps a window at about 500px and a check written the obvious way reports widths it never tested. Then a **second pass**: two pages at the four narrow widths with `content/chrome.json` replaced by the widest document the picker can produce — every route in the nav, the links, the legal row and the dock panel, each drawing the page's own name, and the longest of those names in all four dock keys. An editable nav is the one thing here that can newly overflow, and no shipped document is as wide as what somebody is allowed to save. Then a **third pass**: the same two pages with an uploaded logo at the top of its ladder, in six shapes from portrait through square to 24:1. The header sizes the mark by its HEIGHT and `.site-header__brand` is `flex-shrink: 0`, so the width it takes is height x aspect ratio and nothing downstream can take it back — measured before the cap in `layout.css`, 8:1 held at 320px and 10:1 pushed the page 56px sideways. The cap does not care what the ratio is, so what is asserted is that none of them overflows |
| `check_focus.py` | every page tabbed one stop at a time, at desktop and mobile widths: each focused element has a visible ring (SC 2.4.7) and is not entirely covered by the sticky header or the fixed dock (SC 2.4.11). Runs with reduced motion so scrolling is instant, and **refuses to run** if that preference did not take effect — otherwise every position it reads is mid-scroll |

They skip with a notice and exit 0 when Firefox or geckodriver is missing, rather than failing —
so a machine without a browser can still run everything else.

---

## Reading a failure

**`check_shared_markup.py` fails** — someone edited the hero circuit in a page instead of in
`tools/templates/`. Fix the template, then `python3 tools/propagate_shared.py`. If it names
`lib/body.php` instead, a `data-` hook has been dropped from the emitter and a browser behaviour has
silently gone with it; the message says which one.

**`check_content_model.py` fails** — you changed a content shape in one of the three places it
lives. The message names the field and which layer is missing it.
[content-model.md](server-side/content-model.md)

**`check_docs.py` fails** — the code and the documentation disagree. Usually you added a file and
have not documented it, or changed a constant the prose quotes. The message names both sides.

**`check_secrets.py` fails** — read the message carefully before "fixing" it. It only fails for
things that would silently weaken the admin, and every one of its checks was verified against a
deliberate breakage before being trusted.

**A test hangs or fails oddly** — most often a leaked PHP server from a previous run
holding the port, or a stale `/tmp` private directory. It uses a fresh one each run; if in doubt,
`pkill -f 'php -S'`.

**A browser test fails only sometimes** — check for leaked `geckodriver` processes first. That is
the usual cause of a flake here.

---

## Adding a test

Match the existing shape. Each script is standalone, starts what it needs, cleans up after itself,
prints one line per check, and exits non-zero if any failed. Do not add a framework — there is no
build step, and a dependency is a thing that has to be resurrected before a typo can be fixed.

If your change makes a protection that could be removed without anything failing, add the check to
`check_secrets.py` — and **prove the check works by deliberately breaking the thing it guards**
before you trust it.

### If you are measuring geometry

`CONTRACT_IMAGE_SLOTS` holds the width every uploaded picture is actually drawn at. Those numbers
decide how many files each upload writes and what goes in its `sizes=` attribute, so they are
measured against a real browser rather than read off a stylesheet — a stylesheet says `width: 100%`
and tells you nothing about what that resolves to.

**Measure in an iframe, not a window.** Firefox will not make a window narrower than about 500px:
ask for 320 and you measure 488, with no error. `check_responsive.py` explains this at length and is
the model to copy. The frame's own scrollbar takes about 12px, so a frame asked for 1280 reports a
`clientWidth` of 1268 — report the width you measured, never the one you asked for.

**Force lazy images eager before measuring, and wait for them.** Two hours went into this twice
over. `loading="lazy"` means an image below the fold has not loaded, and an unloaded `<img>` whose
CSS width is a percentage of an indefinite box measures **zero** — the branding page's four previews
looked like a live layout bug and were not. Scrolling one image into view does not help either: the
ones between it and the fold stay unloaded, so "all complete" never arrives and every page times
out. Set `loading = 'eager'` on all of them, return, then poll until `complete && naturalWidth`.

**The widest point is often not the widest screen.** Two of the eight slots peak somewhere in the
middle, because a breakpoint takes the picture out of a full-width column:

| Slot | Widest at | And at 1440 |
|---|---|---|
| `about.story` | **693px @ 768** — the last single-column width | 534px |
| `company.clients` | **249px @ 360** — the grid drops to one column | 126px |
| `about.accreditations` | **289px @ 767** — the last width before the 3-column breakpoint | 222px |

Sampling only 320 and 1440 would have missed the first two and shipped a picture too small on
exactly the width that needed it most. Sample across the breakpoints, in both themes — a lockup
hidden by `display: none` in one theme measures zero in it.

**And count the rows, not just the widths.** `about.accreditations` was measured at three row
counts before a number was written down, and it needed to be: with the `1fr` maximum `.clients`
uses, the same badge came back 318px on a desktop holding three badges and about 1200px holding
one — `auto-fit` collapses the tracks nothing sits in and divides the row between what is left, so
the width depended on how many rows the editor had added. Measure with one row, a few, and many.
If the answer moves, that is a finding about the CSS rather than a number to write down: here it
sent the grid from `auto-fit` to a counted `repeat(N, 1fr)`, which creates every track whether or
not a badge sits in it.

### If you are measuring time

The performance checks in `test_motion.py` cost two failed CI runs and one failed merge before they
were written correctly. The rules that came out of it:

**A threshold needs more margin than the machine has noise.** The frame budget was `baseline + 3ms`
and passed locally by 1ms. On a slower CPU the same code measured 7ms over, and failed. A threshold
that passes by 1ms is not a threshold; it is a coincidence waiting to be reported as a regression.

**Never assert on a maximum.** The stall check compared the single worst frame of about a hundred
against 50ms. Two runs of identical code on the same runner measured 44ms and 55ms — one passed,
one failed. A maximum is the most outlier-sensitive number available, and on a shared runner one
stretched frame is as likely to be the hypervisor as the page. Use a percentile for "is it steady",
and keep the maximum only for a ceiling so high that nothing but a real freeze reaches it.

**Say what drew the page.** Headless Firefox software-rasterises everywhere, including on a
workstation with a good graphics card — it reports `llvmpipe` on a developer laptop and a CI runner
alike. A whole afternoon went into a fix premised on one having a GPU and the other not. These
numbers are CPU-bound on every machine that will ever run them, and say nothing about what a visitor
with hardware compositing sees.

**Two claims, not one.** "Janky" and "frozen" are different failures. They deserve different
statistics and different numbers.
