# Tech4TIME — frontend

The public Tech4TIME website at **`tech4time.bd`**: semantic HTML5, plain CSS3 and vanilla
JavaScript, with **no framework, bundler or build step**. It deploys by uploading the files as they
are.

Sixteen pages, a contact form, a job board, and one inbound endpoint — `api/publish.php` — where
content arrives from the admin.

**The editor lives in [`tech4time-backend`](https://github.com/M-s-Tech4TIME/tech4time-backend)**,
served at `admin.tech4time.bd`. It owns the content and pushes a signed copy here on every save;
this site renders from the replica it is sent and never calls the backend during a request.

Structure, layout and copy are ported from the internal NextJS site. The colour system is new: pure
monochrome with a metallic silver accent derived from the logo's clock face, in full light and dark
modes.

---

## Quick start

```bash
python3 tools/serve.py          # http://localhost:8000
```

Needs the PHP CLI (`sudo apt install php-cli`). **Not** `python3 -m http.server` — four things need
PHP: the careers page, the contact page, the contact form's handler, and `api/publish.php`.

Full setup, including the browser tests:
**[docs/10-development/setup.md](docs/10-development/setup.md)**

---

## Documentation

Everything is in **[docs/](docs/)**, organised by what you are trying to do.
Start at **[docs/README.md](docs/README.md)**, which routes by intent.

| I want to | |
|---|---|
| Understand this project | [docs/00-orientation/](docs/00-orientation/) |
| Set it up and change things | [docs/10-development/](docs/10-development/) |
| Deploy it | [docs/20-deployment/](docs/20-deployment/) |
| Fix something that broke | [docs/30-operations/](docs/30-operations/) |
| Look up a fact | [docs/40-reference/](docs/40-reference/) |
| Know why it is built this way | [docs/90-decisions/](docs/90-decisions/) |

**The two pages worth reading first:**

- [What this project is](docs/00-orientation/README.md) — ten minutes
- [Where to change things](docs/10-development/where-to-change-things.md) — "I want to change X,
  which file do I open?"

---

## The shape of it

```
index.html  404.html      the homepage and the error page
pages/                    the other fourteen — two are .php and render from content/
assets/                   css, js, fonts, icons, images — all self-hosted
lib/                      server-side PHP: rendering, the contract, the publish format
api/publish.php           where the backend's content arrives — the only thing here that writes
content/                  the replica the two dynamic pages render from
tools/                    build, audit and test scripts — never deployed
docs/                     the documentation
.htaccess                 security headers, caching, clean URLs, blocking
```

Not in this repository: **the private store** — `secret.key`, the contact form's throttle counters,
and `publish.key` — which lives *beside* the document root at `/home/USER/t4t-private/`, and
`../t4t-private` locally.

There are no password hashes here, and no name for a file that could hold one: the accounts, the
authenticator secrets and the sessions are the backend's, in its own store. See
[docs/40-reference/security-model.md](docs/40-reference/security-model.md).

Full map: [docs/00-orientation/repository-map.md](docs/00-orientation/repository-map.md)

---

## Before committing

```bash
python3 tools/check_contrast.py        python3 tools/check_content_model.py
python3 tools/inject_icons.py --check  python3 tools/check_secrets.py
python3 tools/check_shared_markup.py   python3 tools/check_docs.py
python3 tools/audit_pages.py           python3 tools/check_shared_lib.py
```

What each proves, and which browser suites to run when:
[docs/10-development/testing.md](docs/10-development/testing.md)

---

## Deploying

**A push to `main` deploys it.** `tools/build_deploy_set.py` builds the upload set from an explicit
allow list, CI rsyncs it over SSH, and the running site is then asked whether the rules that protect
it are still there. **`content/` is never synced** — it is seeded with `--ignore-existing`, and the
host's copy always wins, because it is what the admin published.

How it works: [docs/20-deployment/ci-cd.md](docs/20-deployment/ci-cd.md)
First time on a new host: [docs/20-deployment/first-deploy.md](docs/20-deployment/first-deploy.md)

---

## Status

**Live** at `https://tech4time.bd`, deployed from `main` by CI. Two of sixteen pages are editable,
from the backend. Field-measured LCP/CLS/INP against the live host is still outstanding.
