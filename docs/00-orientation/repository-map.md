# Repository map

**Applies to:** both

Every directory: what it holds, who owns it, and what must never happen to it.

---

## Top level

```
tech4time-website/
├── index.html              the homepage — stays at the root, not in pages/
├── 404.html                the custom error page
├── contact-handler.php     where the enquiry form posts
├── .htaccess               security headers, caching, clean URLs, blocking
├── robots.txt              crawl rules
├── sitemap.xml             submitted to Search Console
├── site.webmanifest        PWA manifest (the one .json that stays public)
├── .gitattributes          line endings and diff behaviour
├── .gitignore              includes the private store, as a backstop
│
├── pages/                  the other fifteen pages; three are .php
├── assets/                 css, js, fonts, icons, images — all self-hosted
├── lib/                    server-side PHP
├── api/publish.php         where the backend's content arrives
├── content/                the REPLICA the dynamic pages render from
├── tools/                  build, audit and test scripts — NEVER DEPLOYED
├── docs/                   this documentation
└── references/             notes kept from the original design work
```

---

## `pages/` — the website

Every page lives at `pages/<name>/index.*` and is served at `/pages/<name>/` with no extension,
because `.htaccess` resolves it. The homepage is the exception: it stays at the root.

| Page | File | |
|---|---|---|
| Home | `index.html` | at the repository root |
| About | `pages/about/index.php` | **dynamic** — renders `content/about.json` |
| Services hub | `pages/services/index.html` | |
| — Cybersecurity | `pages/services/cybersecurity/index.html` | |
| — Software development | `pages/services/software-development/index.html` | |
| — Cloud infrastructure | `pages/services/cloud-infrastructure/index.html` | |
| — HR solutions | `pages/services/hr-solutions/index.html` | |
| — IT consultancy & training | `pages/services/it-consultancy-training/index.html` | |
| — IT equipment supply | `pages/services/it-equipment-supply/index.html` | |
| Company profile | `pages/company-profile/index.php` | **dynamic** — renders `content/company.json` |
| Careers | `pages/careers/index.php` | **dynamic** — renders `content/careers.json` |
| Contact | `pages/contact/index.php` | **dynamic** — renders `content/contact.json` |
| Resource certifications | `pages/resource-certifications/index.html` | |
| Branding & advertisement | `pages/branding-and-advertisement/index.html` | |
| Privacy policy | `pages/privacy-policy/index.html` | |
| Not found | `404.html` | at the repository root |

Fourteen static, two dynamic. Adding one: [adding-a-page.md](../10-development/frontend/adding-a-page.md).

**Every page carries its own copy of the header and footer**, because runtime `fetch()` partials are
forbidden. `tools/templates/` holds the canonical copies and `check_shared_markup.py` proves no page
has drifted. Never hand-edit a header in one page — see
[shared-markup.md](../10-development/frontend/shared-markup.md).

---

## `assets/` — everything the browser loads

```
assets/
├── css/            17 files
│   ├── base.css            reset, type scale, the breakpoint ladder (documented at the top)
│   ├── theme.css           the colour tokens, and the light/dark switch
│   ├── layout.css          page scaffolding
│   ├── components.css      buttons, cards, forms, the shared furniture
│   ├── animations.css      keyframes and reveal states
│   └── pages/              one optional file per page
├── js/             13 files — see frontend/javascript.md
├── fonts/          Inter, self-hosted, two subsets (latin, latin-ext)
├── icons/          sprite.svg — the master icon set
└── images/         170 files: logo, favicon, og, tech, clients, photos, sections, flags, branding
```

**Cascade order matters** and is fixed: `base` → `theme` → `layout` → `components` → `animations` →
optional `pages/<name>.css`. See [css.md](../10-development/frontend/css.md).

**`assets/icons/sprite.svg` is a master, not a runtime asset.** Pages inline the symbols they use;
they do not link to this file. The reason is in [icons.md](../10-development/frontend/icons.md).

---

## `lib/` — server-side PHP

Never reachable over HTTP: `.htaccess` has `RewriteRule ^lib/ - [F,L]`.

| File | Owns |
|---|---|
| `html.php` | escaping, and the rich-text sanitiser |
| `store.php` | reading and writing a JSON file atomically, with a lock |
| `contract.php` | **shared** — the shape of every editable document, and `CONTRACT_VERSION` |
| `publish.php` | **shared** — how a document is signed, and how a signature is checked |
| `careers.php` | what this side does with a job post: the JobPosting schema |
| `contact.php` | what this side does with the contact page: the ContactPage schema |
| `private.php` | where the secrets are, and the keys derived from them |
| `throttle.php` | counting attempts, so guessing costs something |
| `footer-fingerprint.php` | **generated** — what this site's footers currently say |

**Shared** means byte-identical with `tech4time-website-backend`. `tools/check_shared_lib.py` compares all
three against a committed digest; the real guarantee is `CONTRACT_VERSION`, checked at run time by
`api/publish.php` against what it was actually sent.

There is no `auth.php`, `admin.php`, `totp.php`, `reset.php` or `mailer.php` here. They went with
the editor. [0017](../90-decisions/0017-two-private-stores.md)

Detail on each: [libraries.md](../10-development/server-side/libraries.md).

---

## `content/` — the data

```
content/
├── careers.json     job posts and the CV form link
└── contact.json     offices, phone numbers, the enquiry form's copy
```

**This is a replica, and `api/publish.php` is the only thing that writes it.** The system of record
is the backend's copy of these two files; this one is what it was last sent.

So a deploy that overwrote it would destroy live job posts, and so would editing it by hand — the
difference being that the hand edit survives until the next save in the admin and then vanishes,
which is worse, because it looks like it worked.

> **Rule:** never upload `content/` to a server that already has one, and never edit it in place.
> See [routine-deploys.md](../20-deployment/routine-deploys.md) and
> [publish-api.md](../10-development/server-side/publish-api.md).

Field-by-field: [content-schemas.md](../40-reference/content-schemas.md).

---

## `api/` — the one way in

```
api/
└── publish.php     POST only. The backend's content arrives here, signed
```

The only endpoint on this site that writes anything, and the only route content takes to it. It
verifies the signature, checks the timestamp, refuses a `contract_version` it does not implement,
refuses anything not **strictly newer** than what it holds, re-sanitises every rich field through
this side's own `lib/html.php`, and only then writes.

A GET answers **405** — which is what `tools/verify_live.py` asserts after every deploy, because a
404 there means the endpoint did not arrive and the first anyone would know is a save in the admin
that never appears.

Full description: [publish-api.md](../10-development/server-side/publish-api.md).

> **The editor itself is in `tech4time-website-backend`**, served at `admin.tech4time.bd`, with its own
> document root, its own private store and its own pipeline. Nothing in this repository can reach an
> account, and `tools/check_secrets.py` asserts that on every run.

---

## `tools/` — never deployed

Asset builders, markup propagators, auditors, and this half of the test suite. Blocked over HTTP by
`RewriteRule ^tools/ - [F,L]` as a backstop, but the real rule is that they are not uploaded at all.

Two files there are exceptions, uploaded by hand and then deleted:

- `tools/host-probe.php` — answers what can only be answered on the host
- `tools/make_publish_key.py` — creates the key both halves sign content with. Run once, by a
  person, and the same value placed on both hosts

Full list: [tools.md](../40-reference/tools.md).

---

## Not in the repository at all

### The private store

`/home/USER/t4t-private/` on the host, `../t4t-private` beside your clone locally. Password hashes,
the master key, authenticator secrets, sessions, counters and the audit log.

Never committed, never deployed, never inside the document root. `.gitignore` lists it as a
backstop; `lib/private.php` refuses to start if it finds itself in the web root.

[security-model.md](../40-reference/security-model.md) ·
*secrets-recovery.md* (in tech4time-website-backend)

### Generated and ignored

| | |
|---|---|
| `tools/shots/` | screenshots from `shoot_pages.py`, regenerated on demand |
| `content/*.json.bak` | one generation of backup, written on every save |
| `__pycache__/`, `*.pyc` | Python bytecode |
| `Tech4TIME-Static-Website-Plan_v3.md` | the original working brief, kept locally |
