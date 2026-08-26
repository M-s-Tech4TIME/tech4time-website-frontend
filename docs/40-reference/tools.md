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
| `check_shared_markup.py` | the header, footer and script blocks have not drifted between pages |
| `check_content_model.py` | the editor, the data and the page still describe the same thing, and no editor is unchecked |
| `check_secrets.py` | nothing protecting the admin has quietly stopped protecting it |
| `check_docs.py` | the documentation still describes the code |
| `audit_pages.py` | every page: SEO, accessibility and structural correctness |
| `inject_icons.py --check` | every page's inlined icon block is current |
| `build_deploy_set.py --check` | the set of files bound for the server holds nothing it must not, and nothing is missing |
| `verify_live.py <url>` | a deployed site still returns 403 for `lib/`, `content/` and dotted paths, still carries its headers, and still answers on `/api/publish.php` |
| `check_shared_lib.py` | the three files both repositories hold identically have not been edited here |

## Checks that need a browser

| Script | Proves |
|---|---|
| `check_dark_mode.py` | every page as the browser actually paints it, in both themes |
| `check_hover.py` | every kind of interactive element visibly responds to a real pointer |
| `check_responsive.py` | no page scrolls sideways, no control is wider than the screen, and no tap target is under 24px, at seven widths from 320px up |
| `check_focus.py` | tabbing every page: the focus ring can be seen, and nothing covers it |

Both skip with a notice and exit 0 when Firefox or geckodriver is missing.

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
| `test_publish.py` | `api/publish.php` and `publish_push()` over real HTTP: the happy path, and every way past it that does not involve holding the key |

### In a real browser

| Script | Proves |
|---|---|
| `test_motion.py` | the scroll reveal never leaves anything unread |
| `test_nav.py` | the navigation is usable at both widths |
| `test_theme.py` | the theme switch behaves, with a real OS preference |

---

## Markup

| Script | Does |
|---|---|
| `assemble_page.py` | Assemble a page from the shared templates plus a per-page `<main>` block. **For creating a page, not maintaining one** |
| `propagate_shared.py` | Push a change in `tools/templates/` out to every page |
| `inject_icons.py` | Inline each page's icon subset from the master sprite |
| `apply_reveals.py` | Mark up the scroll-reveal targets on every page, from one structural rule |
| `sync_site_contact.py` | Push the contact details out of `content/contact.json` into every page's footer, and record the fingerprint in `lib/footer-fingerprint.php` |
| `htmltree.py` | *(a library)* a minimal HTML tree with source offsets, for tools that edit markup structurally |

---

## Asset generation

Run rarely — usually only when the source artwork changes. **These need Pillow.**

| Script | Does |
|---|---|
| `build_icon_sprite.py` | Build the self-hosted SVG icon sprite from Font Awesome Free metadata |
| `build_images.py` | Copy, rename and optimise the site's content images |
| `build_logos.py` | Normalise the master logo artwork into the web asset set |
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
| `check_shared_lib.py` | Assert the three shared files against a committed digest. `--update` re-records after a deliberate change |

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
| `tools/templates/` | the canonical header, footer, head and script markup |
| `tools/masters/` | source artwork for the asset builders |
| `tools/shots/` | screenshot output, gitignored |

---

## Adding a tool

A docstring saying **what it proves and how to run it**, standard library only (or Pillow), exits
non-zero on failure, prints what failed rather than that something did, and cleans up after itself.

Then add it to this page — `check_docs.py` fails until you do.
