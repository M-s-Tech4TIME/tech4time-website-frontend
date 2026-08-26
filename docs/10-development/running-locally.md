# Running locally

**Applies to:** both

The dev server, running both halves side by side, editing content safely, and the one thing that
genuinely cannot work on a development machine.

---

## The server

```bash
python3 tools/serve.py            # http://localhost:8000
python3 tools/serve.py 8080       # a different port
```

It prints a menu of the pages worth visiting and reminds you how to undo content edits. `Ctrl-C`
stops it.

Under the hood it is `php -S localhost:8000 -t . tools/dev-router.php`. The router exists to make
one machine behave like the other: it resolves `/pages/about/` to `pages/about/index.html` and
`/pages/careers/` to `index.php` the way `.htaccess` does on Apache, so a URL that works here works
there.

It binds to localhost only.

This side's private store lives in `../t4t-private/`, beside the clone:

```
CodeSpace/
├── tech4time-frontend/    ← this repository
├── tech4time-backend/     ← the other half
├── t4t-private/           ← this side: three files
│   ├── secret.key         the throttle's keys derive from it
│   ├── throttle.json      contact-form attempt counters
│   └── publish.key        THE SAME BYTES as the backend's copy
└── t4t-private-admin/     the backend's: accounts, sessions, audit log
```

Deliberately the same shape as `/home/USER/t4t-private` on the host, so nothing about the layout is
different in development.

---

## Running both halves at once

The editor is in `tech4time-backend`. To watch content actually travel, run both servers and point
the backend at this one:

```bash
# terminal 1 — tech4time-frontend
python3 tools/serve.py                       # http://localhost:8000

# terminal 2 — tech4time-backend
T4T_PUBLISH_URL=http://localhost:8000/api/publish.php python3 tools/serve.py 8001
```

Then sign in at `http://localhost:8001/`, save a job post, and reload
`http://localhost:8000/pages/careers/`.

**Both stores need the same `publish.key`.** Without it every publish is refused as
`not-configured`; with two *different* keys, as `unknown-key` — which is the intended failure, and
is exactly what it says.

```bash
# in either repository, once
python3 tools/make_publish_key.py
# then put the printed value in the other store's publish.key
```

Signing in, the lockout and the rescue CLI are the backend's — see the same page in
**tech4time-backend**.

---

## Editing content

**Saving in the admin writes this side's `content/careers.json` and `content/contact.json` for
real**, through `api/publish.php`, exactly as it does on the host. They are tracked files, so the
edits show up in `git status`.

```bash
git checkout content/careers.json content/contact.json   # undo them
```

Keep the development data rich — several job posts, every contact field populated — because it is
what exercises the renderers. An empty JSON file tests nothing.

> **This is the opposite of the rule on the host**, where `content/` is the live replica and must
> never be overwritten by a deploy. Locally it is test data; there, it is what the client published.
> [environments.md](../20-deployment/environments.md)
>
> Either way it is a **replica**: `api/publish.php` is the only thing that writes it, and a hand
> edit survives only until the next save in the admin.

---

## What cannot work locally

### `mail()`

There is no mail server on your machine, so:

- **The contact form** validates, sanitises and answers correctly, then reports that it could not
  send. Every part except delivery is exercised.
- **Password recovery by email** has nowhere to send the code. Use a recovery code instead.

Both are proven on the host with `tools/host-probe.php`, which tests `mail()` on its own so that a
mail problem shows up as one failed probe rather than as a contact form that quietly swallows
enquiries.

`test_contact_handler.py` does test the outgoing message — it points PHP's `sendmail_path` at a
script that captures the bytes `mail()` was asked to send, then reads them back. That is how the
header-injection defences are proven to work rather than merely to look right.

### Apache

The dev server is PHP's built-in one. `.htaccess` is not read, so locally you do not get the
security headers, the caching rules, the compression, or the blocking of `lib/`, `content/` and
`tools/`. The dev router reproduces the URL shapes and nothing else.

**What this means in practice:** a `.htaccess` change cannot be verified locally. Verify it on the
host, and read [security-model.md](../40-reference/security-model.md) before changing it.

---

## Looking at pages

```bash
python3 tools/shoot_pages.py            # screenshots into tools/shots/
python3 tools/check_dark_mode.py        # every page, both themes
python3 tools/check_hover.py            # a real pointer over every control
```

`tools/shots/` is gitignored — regenerate rather than commit.

---

## Housekeeping

The browser suites can leave processes behind if a run is interrupted:

```bash
pkill firefox geckodriver
```

Worth doing after an interrupted test run. They are harmless but they accumulate.
