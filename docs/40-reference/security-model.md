# Security model

**Applies to:** both

What is protected, by what, and what is deliberately not protected. Read this before changing
`.htaccess`, `lib/private.php` or anything in `tech4time-website-backend/lib/auth.php`.

---

## The classes of data, and why they get different protection

| | Protected by | If that protection fails |
|---|---|---|
| Public pages, assets | nothing — they are public | nothing |
| `content/*.json` | an `.htaccess` rule | a stranger reads the office addresses the contact page already shows them |
| `lib/*.php` | an `.htaccess` rule | source disclosure — bad, not catastrophic |
| **`t4t-private/`** | **not being inside the website** | — there is no request that reaches it |

The distinction is the design. **An `.htaccess` rule is a policy the server chooses to apply.** If
`mod_rewrite` is off, or an upload replaces the file, it silently stops applying. That is an
acceptable risk for site copy and not for a password hash.

A directory outside the document root has no URL at all, which is a stronger thing than a rule. So
`lib/private.php` refuses to start if it finds itself inside the web root, rather than trusting the
rule.

---

## What is in the private store, and how each part is protected

**Three files, and that is the security property.**

```
/home/USER/t4t-private/        0700, owned by the cPanel user
├── secret.key      0600   32 bytes; the throttle's keys derive from it
├── throttle.json          contact-form attempt counters
└── publish.key     0600   THE SAME BYTES as the backend's copy
```

There is no password hash here, no authenticator secret, no recovery code and no session — and no
*name* for any of them. `T4T_PRIVATE_FILES` in `lib/private.php` lists exactly three entries and
`t4t_private_path()` **throws** on a name it does not know, so this is not a convention that could
be broken by a careless addition. `tools/check_secrets.py` asserts it on every run.

The accounts, the sessions, the audit log and their own unrelated master key are in
`/home/USER/t4t-private-admin/`, which belongs to `tech4time-website-backend` and is described in that
repository's copy of this page. Neither host can read the other's store.
[0017](../90-decisions/0017-two-private-stores.md)

| | Stored as | Survives the file being stolen? |
|---|---|---|
| Throttle counters | keyed by `HMAC(scope:value, t4t_key('throttle'))` | the IP is pseudonymised; the counter is not a secret |
| `publish.key` | 32 raw bytes | **no** — whoever holds it can publish content to this site |

**`publish.key` is the one thing here worth stealing**, and what it buys is narrower than it looks:
the ability to write a *document* — which the endpoint then re-sanitises through `lib/html.php`
before storing, so it cannot be used to put script on the page. It cannot read anything, sign in
anywhere, or reach the admin host.

It is 0600 in a 0700 directory beside the document root, where no URL maps to it, and it is never
derived from `secret.key` — the two halves have separate master keys, so a derived value would
differ by construction and nothing would ever publish.

---

## The password

Not here. The sign-in, the argon2id pepper and everything around them are in `tech4time-website-backend`;
see that repository's copy of this page and its *authentication.md*.

**`secret.key` on this host peppers nothing but the throttle's keys.** That is the only thing it was
ever doing on the public site, and it is why losing it costs a cleared rate-limit counter rather
than every stored credential. Rotating this one has no effect on the backend's, and the reverse.

---

## Key derivation

Everything derived comes from `secret.key`, by purpose:

```
secret.key (32 random bytes)
    └── t4t_key($purpose) = hash_hmac('sha256', $purpose, master, true)
            └── 'throttle'          counter keys, so an IP never hits disk in the clear

publish.key (32 random bytes, THE SAME on both hosts)
    └── not derived from anything, and derives nothing
            └── HMAC-SHA256 over "<timestamp>.<body>", plus a 16-hex fingerprint
```

Separate purposes get separate keys so a weakness in how one is used cannot be carried into another.
On this host there is currently one purpose; the backend derives four from *its* master key, which
is a different 32 bytes.

**`publish.key` sits outside that tree on purpose.** Deriving it from `secret.key` is the obvious
idea and cannot work: the two halves hold different master keys, so the derived values would differ
and every publish would be refused. It would also mean rotating a master key silently broke
publishing. [0017](../90-decisions/0017-two-private-stores.md)

`secret.key` is created with `fopen(…, 'x')`, which fails if the file exists — making "create only
if absent" one atomic step. The creation path is written to **lose** a race, because regenerating
the key would invalidate every stored password at a stroke.

---

## HTTP-level protections

### Content-Security-Policy

```
default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; form-action 'self'
```

No inline styles, no inline scripts, no external origins. A `style="…"` attribute is exactly what an
injected payload looks like, and forbidding the whole category means the browser rejects it without
having to tell the two apart.

This is why the rich-text sanitiser has no `style` attribute and why alignment is a class from a
fixed list.

### Other headers

`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, and HSTS
(staged, commented until the site is live).

> `X-Frame-Options` and `X-Content-Type-Options` are **ignored by browsers when set via `<meta>`**.
> The `.htaccess` copy is the one that counts; the `<meta>` equivalents are defence in depth for
> hosts that strip headers.

### Blocking

```apache
<FilesMatch "^\.">                          Require all denied
<FilesMatch "\.(md|py|sh|json|lock|yml|yaml)$">  Require all denied
<Files "site.webmanifest">                  Require all granted   # the one public .json
RewriteRule ^tools/       - [F,L]
RewriteRule ^lib/         - [F,L]
RewriteRule ^content/     - [F,L]
RewriteRule ^t4t-private/ - [F,L]
```

The last is for the one case the containment check cannot help with: somebody restoring a backup and
dropping the folder into the web root by hand.

### Keeping the admin and the API out of search results

```apache
Header always set X-Robots-Tag "noindex, nofollow, noarchive" "expr=%{REQUEST_URI} =~ m#^/admin(/|$)#"
Header always set X-Robots-Tag "noindex, nofollow, noarchive" "expr=%{REQUEST_URI} =~ m#^/api(/|$)#"
Header always set Cache-Control "no-store, max-age=0"          "expr=…"
```

`always` matters: without it the header attaches only to 2xx responses, and the login page answers a
crawler with 200 while every other admin URL answers with a redirect.

The `/admin` rule is kept although the editor has moved to `admin.tech4time.bd`: it costs nothing,
and it still covers the case of somebody putting something back at that path. **On the subdomain it
does not fire at all** — the URI there is `/` — which is why the backend carries its own blanket
rule. [0011](../90-decisions/0011-two-repositories.md) catalogued that before it happened.

`/api/` is the publish endpoint. It sets `X-Robots-Tag` and `Cache-Control` itself; the rules here
are belt and braces, the same as everything else in this section. An indexed POST endpoint is not a
vulnerability, but it is a URL in a result page inviting people to find out what it does.

> **`/admin` is deliberately not in `robots.txt`.** Listing it there advertises it to anyone who
> reads the file.

---

## What protects this site, and what each thing defeats

There is no session and no password on this half. What it has is one endpoint that writes, and the
protections are of a different kind:

| Protection | Defeats |
|---|---|
| HMAC signature over `"<timestamp>.<body>"` | anyone who does not hold `publish.key` |
| The key's fingerprint on every signature | *not knowing* the two stores have parted — it answers "wrong key", not "wrong signature" |
| ±5 minute timestamp window | a captured request kept and used later |
| **Strictly newer `revision`** | a replay **inside** the window, which is signed perfectly well — it becomes a no-op instead of a rollback |
| `contract_version` refused if unknown | a document written in a shape this side would mis-render |
| Re-sanitising through `lib/html.php` | **a compromised admin host putting script on the public page.** A signature proves origin, not safety |
| Body cap, POST-only | a large or malformed request costing anything |
| The private store outside the document root | `publish.key` being readable over HTTP whatever `.htaccess` says |
| Three entries in `T4T_PRIVATE_FILES` | a credential ever being written on this host at all |
| Contact-form throttle, keyed by HMAC | brute-forcing the form, without the IP hitting disk in the clear |

The admin's own protections — argon2id and the pepper, the TOTP second factor, the lockout, the CSRF
tokens, the session rules, the setup token and the audit log — are in `tech4time-website-backend`, and so is
the table describing them.

---

## What is deliberately not protected

**The contact form's rate limit fails open.** If the counter file is unreadable, the form still
works. The counter shares a directory with the passwords, and an unreachable store must not make the
company uncontactable. This is spam control, not a security boundary.

**A replay inside the five-minute window is not rejected as a forgery.** It cannot be — it is
correctly signed. It is rejected as *stale*, by the revision, and the answer to a replay is
therefore "nothing changed" rather than "go away". That is the intended behaviour, and
`tools/test_publish.py` asserts it.

**`content/` is only `.htaccess`-protected.** It holds addresses the contact page already displays.

**There is no intrusion detection**, no WAF, no fail2ban. The audit log records; nothing watches it.

---

## The enforced invariants

`tools/check_secrets.py` asserts the protections that would go on *working* after being removed —
the dangerous kind:

- no secret file is tracked in git
- the private store still refuses the document root
- nothing belonging to the admin has reappeared here, and no page verifies a password
- the private store has no *name* for an account file — `t4t_private_path()` throws on a key it does
  not know, so this is not a convention
- `api/publish.php` is the only writer outside `lib/`, and still verifies, checks the envelope,
  re-sanitises and refuses anything not strictly newer
- the session cookie flags are intact
- no password can reach the audit log
- the `.htaccess` blocking rules are all still there, `.well-known` included

**Every one of those checks was verified against a deliberate breakage before being trusted.** A
check nobody has watched fail is a check nobody should believe. Keep that going.

---

## Known weaknesses

| | Consequence | Mitigation |
|---|---|---|
| The containment check compares against the *requesting* document root | a store inside a sibling docroot passes and is web-reachable | set `T4T_PRIVATE` explicitly; the backend's document root is `backend/public/`, so nothing beside `public_html` is inside it — [0018](../90-decisions/0018-the-backend-serves-from-a-subdirectory.md) |
| `publish.key` is copied between hosts by hand | a mistake places it somewhere readable, or the two drift apart | the fingerprint on every signature makes a drift say so in one attempt; the file is 0600 in a 0700 directory outside both document roots |

The containment check is still worth fixing; [0018](../90-decisions/0018-the-backend-serves-from-a-subdirectory.md)
removes the layout that would exercise it rather than the gap itself.

> **Fixed 2026-08-23.** Recovery codes derived from `secret.key` died silently when it was lost —
> `admin-cli list` counted stored entries and reported ten. Each code now carries the fingerprint of
> the key that made it, so the CLI reports `10 DEAD` and says what to do.
>
> **Fixed 2026-08-23.** `store_read()` could not tell a missing file from a corrupt one, so a
> damaged `admins.json` presented as a fresh install and offered setup — whose first save copied the
> damage over the `.bak`. `store_state()` now tells them apart: the admin refuses to start, and
> `store_write()` never lets a damaged file become the backup.

---

## Reporting

Found something? Do not open a public issue. Contact whoever holds the cPanel account directly. If
`secret.key` may have been exposed, treat it as
*rung 8* (in tech4time-website-backend) and rotate rather than merely
removing the file.
