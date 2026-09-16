# Where to change things

**Applies to:** both

"I want to change X — which file do I open?" This page answers that and nothing else. If you read
one page in this documentation, make it this one.

**The general rule:** if it is *content*, it is edited at **admin.tech4time.bd** and you should not
touch a file — not here, and not on this server either. If it is *design or behaviour*, it is in
`assets/`. If it is *structure*, it is in `pages/` or `lib/`. If it is *rules*, it is in `lib/` or
`.htaccess`.

**This is the frontend.** The editor, the sign-in and everything they need live in
**tech4time-website-backend**; this repository is the public site and its one inbound endpoint. Rows below
that name the admin name the other repository too.

---

## Content — do not edit files for these

| I want to change | Where |
|---|---|
| The home page's hero, badges, tags and terminal | `https://admin.tech4time.bd/?s=home` |
| The service cards and the Get to Know Us cards | `https://admin.tech4time.bd/?s=home` |
| A job post — add, edit, remove, reorder | `https://admin.tech4time.bd/?s=careers` |
| The CV / application form link | `https://admin.tech4time.bd/?s=careers` |
| An office address, phone number, email | `https://admin.tech4time.bd/?s=contact` |
| The contact page's headings and copy | `https://admin.tech4time.bd/?s=contact` |
| What the enquiry form says | `https://admin.tech4time.bd/?s=contact` |
| A milestone, a figure, a client logo, a photograph | `https://admin.tech4time.bd/?s=company` |
| The technology list, or the principles | `https://admin.tech4time.bd/?s=company` |
| The company profile's headings and copy | `https://admin.tech4time.bd/?s=company` |
| The about page's sections, specialities and why-us cards | `https://admin.tech4time.bd/?s=about` |
| An accreditation badge — ISO 27001, SOC 2 and the like | `https://admin.tech4time.bd/?s=about`, the Accreditations band. Ships hidden; switch it on once a badge is on it. **Not** `?s=certifications`, which is the Resource Certifications page and lists people's qualifications |
| **Any page's browser-tab title, search description or share card** | `https://admin.tech4time.bd/?s=seo` |
| Whether a page appears in search at all, and where it sits in the sitemap | `https://admin.tech4time.bd/?s=seo` |
| The Organization details a search engine reads — legal name, slogan, opening hours, social profiles | `https://admin.tech4time.bd/?s=seo&site=identity` |
| Crawl rules, the web manifest, Search Console and Bing verification | `https://admin.tech4time.bd/?s=seo&site=crawl` |
| The 404 page's title and description | `https://admin.tech4time.bd/?s=seo&page=notfound` |

Saving there writes the backend's own record, then pushes a signed copy to `api/publish.php` here,
which verifies it, re-sanitises it and writes `content/home.json`, `content/careers.json`,
`content/contact.json`, `content/company.json` or `content/about.json`. Pictures travel a second endpoint of their own, `api/publish-asset.php`,
and land in `uploads/` — [0019](../90-decisions/0019-uploaded-images-travel-their-own-channel.md). This
site's copy is a **replica** — never edit it by hand, on the server or anywhere else: the next
publish overwrites it. [publish-api.md](server-side/publish-api.md)
*content-runbook.md* (in tech4time-website-backend) ·
[content-schemas.md](../40-reference/content-schemas.md)

> **The footer's contact details are separate, deliberately.** They are the footer's own rows, on
> the **Header & Footer** screen — `?s=chrome` — and not a copy of the contact page's. The contact
> page holds everything in full; the footer holds the part worth putting in a footer, in whatever
> order and wording suits it. A standing notice in the editor reports when the two differ, and never
> blocks a save.
> [ADR 0023](../90-decisions/0023-the-header-and-footer-are-emitted-once.md)

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
| The homepage hero's neural mesh | `assets/js/neural.js`; its colours in `assets/css/pages/home.css`. No markup, and no fallback |
| The technology sphere | `assets/js/tech-sphere.js` |
| Counting figures, client logos | `assets/js/animations.js` |
| Contact form validation (convenience only) | `assets/js/forms.js` |
| **Where** the contact form posts, and how it clears | its `action` attribute. Never `form.action`, `form.method` or `form.reset()` in script — a control of that name replaces each. [ADR 0022](../90-decisions/0022-form-properties-are-read-off-the-prototype.md) |
| Module wiring | `assets/js/main.js` |

Every module registers on `window.Tech4Time` and must degrade — the page has to work with scripting
off. [javascript.md](frontend/javascript.md) · [motion.md](frontend/motion.md)

---

## Structure and markup

| I want to change | Where |
|---|---|
| **The header, footer or dock** | **`https://admin.tech4time.bd/?s=chrome`** — every link, label, heading and contact row. The markup is `lib/body.php`; **never a page file** |
| The hero circuit around a page title | `references/t4t_circuitry_6000_2031_300.svg` is the drawing; `python3 tools/build_hero_circuit.py` redraws the template from it, then `python3 tools/propagate_shared.py` — **never one page, and never the template by hand** |
| A page's content | `pages/<name>/index.php` — its **words** are in the admin, above |
| The homepage | `index.php`, at the repository root — likewise |
| The 404 page | `404.php` — its markup here, its title and description in the admin |
| Anything inside a page's `<head>` | `lib/head.php` if it is code; the admin if it is words. Never a page file |
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
| The shape of ANY editable document | `lib/contract.php` — **and the form and the renderer with it**, and the same file in the backend |
| A service, its solutions, or the services page | **`https://admin.tech4time.bd/?s=services`** — not a file, and not here |
| How a services page is drawn | `lib/services.php` — one renderer behind all seven pages |
| Adding a whole new service | **the editor** — it needs no file here. `pages/services/detail.php` serves any slug the document has |
| A certification, a role, or a whole role group | **`https://admin.tech4time.bd/?s=certifications`** — not a file, and not here |
| How the certifications page is drawn | `lib/certifications.php` |
| The number in "54 certifications across the four specialist roles" | nowhere: the text holds `{certifications}` and `{groups-word}`, and the renderer fills them in |
| A logo file people download, or the terms covering its use | **`https://admin.tech4time.bd/?s=branding`** — not a file, and not here |
| How the branding page is drawn | `lib/branding.php` |
| Anything the privacy policy says | **`https://admin.tech4time.bd/?s=privacy`** — not a file, and not here |
| How the privacy policy is drawn | `lib/privacy.php` and `PRIVACY_BLOCK_KINDS` in `lib/contract.php` — a block's kind decides its markup |
| The web address of a section of the policy | nowhere: it is minted from the heading once and then frozen, because somebody may have linked to it |
| The size beside a download, or the words on its button | nowhere: both are read off the file itself by `branding_meta_line()` and `branding_download_label()` |
| What a vector file is allowed to contain | `lib/svg.php` — **and the same file in the backend** |
| What HTML is allowed in rich text | `lib/html.php` — the sanitiser |
| How JSON is read and written | `lib/store.php` |

> Changing a content shape means changing three files together — the model, the form and the
> renderer. Something fails the build if one is left behind: `check_content_model.py` for contact,
> `test_publish.py` for everything else, which sends a marker through every declared field and
> reads it back off the page. `lib/contract.php` is byte-identical across both repositories, so
> after editing it run `check_shared_lib.py --update` and copy the file *and* the manifest across.
> [content-model.md](server-side/content-model.md)

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
| Crawl rules | `https://admin.tech4time.bd/?s=seo&site=crawl` — `robots.php` renders them at `/robots.txt` |
| The sitemap's pages | nowhere: `sitemap.php` walks `SEO_ROUTES` and omits anything set to noindex |
| The sitemap's service pages | nowhere: they are read from `content/services.json` |
| The address a page is a page **at** | `SEO_ROUTES` in `lib/contract.php`, and `.htaccess` — a route is code |
| The web manifest | `https://admin.tech4time.bd/?s=seo&site=crawl` — `manifest.php` renders it |

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
