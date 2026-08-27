# Tech4TIME — frontend

The public site at **`tech4time.bd`**: sixteen pages, a contact form, and one inbound endpoint that
receives content from the admin. No build step, no framework — the files here are the files that run
on the server.

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
2. **No CDN and no external origin.** Everything is self-hosted.
3. **No inline styles or scripts.** The CSP is `style-src 'self'; script-src 'self'` — a `style=`
   attribute, a `<style>` block or an `onclick` will be refused by the browser.
4. **Every page must work with JavaScript off.** Progressive enhancement is a hard rule. Motion may
   decorate; it may never be the only route to anything.
5. **Content renders on the server.** No runtime `fetch()` for content, ever — including for the
   header and footer, and including from the backend.
6. **Never commit anything from the private store** (`t4t-private/`, `*.key`).
7. **`content/` is a replica.** It is written by `api/publish.php` and by nothing else — not by
   hand, not on the server, not by a deploy. The next publish overwrites anything you put there.
8. **`lib/html.php`, `lib/contract.php` and `lib/publish.php` are byte-identical** with
   `tech4time-website-backend`. Change one and you change both, in the same breath.
9. **`tools/` is never deployed.**
10. **Never edit a header or footer in a page file.** Edit `tools/templates/`, then
    `python3 tools/propagate_shared.py`.

---

## Where things are

| | |
|---|---|
| `pages/` `index.html` | the sixteen pages — two are `.php` and render from `content/` |
| `assets/` | css, js, fonts, icons, images — all self-hosted |
| `lib/` | server-side PHP: rendering, the contract, the publish format |
| `api/publish.php` | where the backend's content arrives. The only thing here that writes |
| `content/` | the replica the two dynamic pages render from |
| `tools/` | build, audit and test scripts — never deployed |
| `docs/` | the documentation |
| `../t4t-private/` | **outside the repo** — `secret.key`, `throttle.json`, `publish.key`. Never committed |

There is no `admin/`, no `lib/auth.php` and no password hash on this host. The private store has no
*name* for one — `t4t_private_path()` throws on a key it does not know — and
`tools/check_secrets.py` asserts it on every run.

---

## Where to change what

Full table: [docs/10-development/where-to-change-things.md](docs/10-development/where-to-change-things.md)

| Change | Where |
|---|---|
| A colour | `assets/css/theme.css` — tokens only, never a hex elsewhere |
| Layout, components | `assets/css/layout.css`, `components.css` |
| Browser behaviour | `assets/js/` — modules register on `window.Tech4Time` |
| Header / footer | `tools/templates/` → `propagate_shared.py` |
| An icon | the markup, then `python3 tools/inject_icons.py` |
| A job post or contact detail | **`https://admin.tech4time.bd/`** — not a file, and not here |
| The shape of editable content | `lib/contract.php` — **and the same file in the backend** |
| How a document is signed | `lib/publish.php` — likewise byte-identical |
| Add a page | [adding-a-page.md](docs/10-development/frontend/adding-a-page.md) |
| Headers, caching, blocking | `.htaccess` — not read by the local dev server |

---

## Running it

```bash
python3 tools/serve.py          # http://localhost:8000  — NOT python3 -m http.server
```

Four things need PHP: the careers page, the contact page, the contact handler, and
`api/publish.php`.

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
python3 tools/inject_icons.py --check  python3 tools/check_secrets.py
python3 tools/check_shared_markup.py   python3 tools/check_docs.py
python3 tools/audit_pages.py           python3 tools/check_shared_lib.py
python3 tools/check_shared_repos.py
```

Touched `api/publish.php`, `lib/contract.php` or `lib/publish.php`? Also `test_publish.py` — **and
`check_shared_lib.py --update`, and copy the changed file to the backend.**

Touched the contact handler? Also `test_contact_handler.py`. Touched `lib/store.php`? Also
`test_store.py`.

Touched CSS, markup or motion? Also `test_motion.py`, `test_nav.py`, `test_theme.py`,
`check_hover.py`, `check_dark_mode.py`, `check_responsive.py`, `check_focus.py` — these need
Firefox and geckodriver, and leave processes behind if interrupted (`pkill firefox geckodriver`).

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

Work happens on `dev`; pull requests to `main` need explicit approval.

**Live** at `https://tech4time.bd` — cPanel, LiteSpeed, PHP 8.2.33. **A push to `main` deploys it.**
Checks run, rsync over SSH, and the site is asked afterwards whether `lib/`, `content/` and dotted
paths still answer 403 and `/api/publish.php` still answers 405 —
[ci-cd.md](docs/20-deployment/ci-cd.md). Never sync `content/`; it is seeded with `--ignore-existing`
and the host's copy always wins.

**Not done, and not missing:** field-measured LCP/CLS/INP against the live host is outstanding.
