# Where to change things

**Applies to:** both

"I want to change X — which file do I open?" This page answers that and nothing else. If you read
one page in this documentation, make it this one.

**The general rule:** if it is *content*, it is edited at **admin.tech4time.bd** and you should not
touch a file — not here, and not on this server either. If it is *design or behaviour*, it is in
`assets/`. If it is *structure*, it is in `pages/` or `tools/templates/`. If it is *rules*, it is in
`lib/` or `.htaccess`.

**This is the frontend.** The editor, the sign-in and everything they need live in
**tech4time-website-backend**; this repository is the public site and its one inbound endpoint. Rows below
that name the admin name the other repository too.

---

## Content — do not edit files for these

| I want to change | Where |
|---|---|
| A job post — add, edit, remove, reorder | `https://admin.tech4time.bd/?s=careers` |
| The CV / application form link | `https://admin.tech4time.bd/?s=careers` |
| An office address, phone number, email | `https://admin.tech4time.bd/?s=contact` |
| The contact page's headings and copy | `https://admin.tech4time.bd/?s=contact` |
| What the enquiry form says | `https://admin.tech4time.bd/?s=contact` |

Saving there writes the backend's own record, then pushes a signed copy to `api/publish.php` here,
which verifies it, re-sanitises it and writes `content/careers.json` or `content/contact.json`. This
site's copy is a **replica** — never edit it by hand, on the server or anywhere else: the next
publish overwrites it. [publish-api.md](server-side/publish-api.md)
*content-runbook.md* (in tech4time-website-backend) ·
[content-schemas.md](../40-reference/content-schemas.md)

> **The footer is the exception.** The contact details repeated in every page's footer are *markup*,
> not content, so the editor cannot reach them. After changing them in the admin, run
> `python3 tools/sync_site_contact.py` to push them out to all sixteen pages, then redeploy.
> The admin shows a banner when the two have drifted.

---

## Look and feel

| I want to change | Where |
|---|---|
| **A colour** | `assets/css/theme.css` — the tokens. Never a hex value in a component file. |
| Light/dark behaviour | `assets/css/theme.css` (`data-theme`) and `assets/js/theme-init.js` |
| Typography, the type scale | `assets/css/base.css` |
| The font itself | `assets/fonts/` + the `@font-face` in `base.css`; refetch with `tools/fetch_fonts.py` |
| Spacing, breakpoints | `assets/css/base.css` — the ladder is documented at the top (480 / 768 / 1024 / 1280 / 1440 / 1920) |
| Page scaffolding | `assets/css/layout.css` |
| Buttons, cards, forms, shared furniture | `assets/css/components.css` |
| One page only | `assets/css/pages/<name>.css` |

**Cascade order is fixed:** `base` → `theme` → `layout` → `components` → `animations` → `pages/*`.
[css.md](frontend/css.md)

---

## Behaviour in the browser

| I want to change | Where |
|---|---|
| Navigation, the mobile menu | `assets/js/nav.js` |
| The theme toggle | `assets/js/theme-toggle.js` |
| Scroll reveal | `assets/js/animations.js` + `assets/css/animations.css` |
| Which elements reveal | `tools/apply_reveals.py` — a structural rule, not hand-marked |
| Sliders on About / Company Profile | `assets/js/slider.js` |
| The homepage terminal | `assets/js/terminal.js` |
| The technology sphere | `assets/js/tech-sphere.js` |
| Counting figures, client logos | `assets/js/animations.js` |
| Contact form validation (convenience only) | `assets/js/forms.js` |
| Module wiring | `assets/js/main.js` |

Every module registers on `window.Tech4Time` and must degrade — the page has to work with scripting
off. [javascript.md](frontend/javascript.md) · [motion.md](frontend/motion.md)

---

## Structure and markup

| I want to change | Where |
|---|---|
| **The header or footer** | `tools/templates/`, then `python3 tools/propagate_shared.py` — **never one page** |
| A page's content | `pages/<name>/index.html` |
| The homepage | `index.html`, at the repository root |
| The 404 page | `404.html` |
| Add a whole new page | [adding-a-page.md](frontend/adding-a-page.md) |
| An icon on a page | edit the markup, then `python3 tools/inject_icons.py` |
| Add a new icon to the set | `assets/icons/sprite.svg` via `tools/build_icon_sprite.py`, then inject |
| Images | `tools/masters/`, then `python3 tools/build_images.py` |
| The favicon | `tools/build_favicons.py` |
| The social share card | `tools/build_og_image.py` |

[shared-markup.md](frontend/shared-markup.md) · [icons.md](frontend/icons.md)

---

## Server-side rules

| I want to change | Where |
|---|---|
| Who the contact form emails | `MAIL_TO` in `contact-handler.php` |
| Who enquiries are sent to, and who they come from | `MAIL_TO` / `MAIL_FROM` in `contact-handler.php` |
| Contact form validation | `contact-handler.php` — the server side is the real one |
| The contact form's rate limit | `contact-handler.php`, using `lib/throttle.php` |
| The shape of a job post | `lib/careers.php` — **and the form and the renderer with it** |
| The shape of the contact page | `lib/contact.php` — **and the form and the renderer with it** |
| What HTML is allowed in rich text | `lib/html.php` — the sanitiser |
| How JSON is read and written | `lib/store.php` |

> Changing a content shape means changing three files together — the model, the form and the
> renderer. Something fails the build if one is left behind: `check_content_model.py` for contact,
> `test_publish.py` for careers, which sends a marker through every declared field and reads
> it back off the page. [content-model.md](server-side/content-model.md)

---

## The admin and the sign-in

**All of it is in tech4time-website-backend.** The section registry, session lifetimes, the lockout,
recovery codes, password rules, hashing cost, reset codes and authenticator drift are constants in
that repository's `tech4time-website-backend/lib/auth.php`, `tech4time-website-backend/lib/reset.php` and `tech4time-website-backend/lib/totp.php`, and its own copy of this page
lists them.

What is still here, because the public site uses it for the contact form:

| I want to change | Where |
|---|---|
| How many enquiries one address may send | the `throttle_quota()` call in `contact-handler.php` |
| The longest lockout | `THROTTLE_MAX_BLOCK` in `lib/throttle.php` — one hour |
| Where the private store lives | `T4T_PRIVATE`, or the default in `lib/private.php` |
| The key both halves sign content with | `publish.key` — `tools/make_publish_key.py`, never edited by hand |

> This side's private store holds three things: `secret.key`, `throttle.json` and `publish.key`.
> There is no name in `T4T_PRIVATE_FILES` for a password hash, which is not a convention —
> `t4t_private_path()` throws on a name it does not know. `tools/check_secrets.py` asserts it.

---

## Server configuration

| I want to change | Where |
|---|---|
| Security headers (CSP, X-Frame-Options…) | `.htaccess` section 1 |
| Caching | `.htaccess` section 6 |
| Clean URLs | `.htaccess` section 3 |
| What is blocked over HTTP | `.htaccess` section 8 |
| Keeping `/api/` out of search results | `.htaccess` section 9 |
| Enabling HSTS | `.htaccess` — uncomment, **after** the site is live on HTTPS |
| Crawl rules | `robots.txt` |
| The sitemap | `sitemap.xml` |

> `.htaccess` is not read by the local dev server. Changes there can only be verified on the host.
> [security-model.md](../40-reference/security-model.md)

---

## Deploying and operating

| I want to | Where |
|---|---|
| Deploy for the first time | [first-deploy.md](../20-deployment/first-deploy.md) |
| Push an update | [routine-deploys.md](../20-deployment/routine-deploys.md) |
| Recover a lost password or secret | *secrets-recovery.md* (in tech4time-website-backend) |
| Diagnose something broken | [troubleshooting.md](../30-operations/troubleshooting.md) |

---

## Things you should not change without reading first

| | Read this first |
|---|---|
| Anything in `lib/private.php` | [security-model.md](../40-reference/security-model.md) |
| `api/publish.php`, or anything it calls | [publish-api.md](server-side/publish-api.md) |
| The `.htaccess` blocking rules | [security-model.md](../40-reference/security-model.md) |
| A page's header or footer, directly | [shared-markup.md](frontend/shared-markup.md) |
| `content/*.json` on a live server | [routine-deploys.md](../20-deployment/routine-deploys.md) |
| `lib/html.php`, `lib/contract.php`, `lib/publish.php` | They are **byte-identical** in both repositories. [publish-api.md](server-side/publish-api.md) |
