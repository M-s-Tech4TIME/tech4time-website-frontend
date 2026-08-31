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
| [`about.php`](#aboutphp) | what this side does with the about page | `contract`, `store` |
| [`home.php`](#homephp) | what this side does with the home page | `contract`, `store` |
| [`publish.php`](#publishphp) **shared** | how a document is signed and checked on the wire | `private`, `contract` |
| [`publish_client.php`](#publish_clientphp) *(backend)* | sending one | `publish` |
| [`footer-fingerprint.php`](#footer-fingerprintphp) *(frontend, generated)* | what this site's footers currently say | — |
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
`careers_normalise()` · `contact_normalise()` · `contact_defaults()` · `contact_fingerprint()` ·
`contract_sanitise()` · `contract_next_revision()` · …

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

`CONTRACT_BOOKKEEPING` names the fields a document keeps about *itself* — `updated`, `revision`,
`footer_synced`. Nothing edits them and nothing renders them, so both directions of
`check_content_model.py` and the round trip in `test_careers_admin.py` exempt them, and all three
read the one list. They did not, once: `revision` was added, the careers test treated it as a
site-wide setting, posted it on its own, and blanked `cv_form_url` doing so.

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

The footer-drift banner is powered by `contact_footer_in_step()` in `contract.php`, comparing the
details now held against `footer_synced` — which after the split is **what the frontend reported in
the last publish response**, not something this side computed. See
[`footer-fingerprint.php`](#footer-fingerprintphp).

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

### `about.php`

`about_load()` · `about_save()` · `about_validate()` *(backend)* ·
`about_page_schema()` · `about_picture()` · `about_photograph()` ·
`about_logo_lockup()` · `about_reveal_paragraphs()` *(frontend)*

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

### `publish_client.php`

**Backend only.** `publish_push()` · `publish_endpoint()`

Sends one document and returns what the editor should show. Never throws for a network problem: an
unreachable site is a thing to report in the editor, not a stack trace over a form somebody has just
filled in.

The certificate is verified and there is no option to turn that off; redirects are not followed,
because a redirect on this route would post a signed document wherever it pointed.

`$T4T_PUBLISH_URL` overrides the endpoint — how `test_publish.py` points it at a local server.

### `footer-fingerprint.php`

**Frontend only, and generated** by `tools/sync_site_contact.py`. One constant,
`FOOTER_FINGERPRINT`.

The footer's contact details are literal markup in all sixteen pages, because the project forbids
runtime partials. So the moment somebody edits an address in the admin, the contact page is right
and the footers are behind — until the pages are rebuilt and deployed.

This records the fingerprint the footers were last rebuilt **for**. It used to be stamped into
`contact.json`, which stopped being possible when the backend took ownership of that file: the
frontend's copy is a replica, and the next publish overwrites anything written into it. So the
frontend keeps its own record, reports it in every publish response, and the backend compares. The
side that knows what its own footers say is the side that answers.

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
