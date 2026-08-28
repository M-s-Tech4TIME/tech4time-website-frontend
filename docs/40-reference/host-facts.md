# Host facts

**Applies to:** both

The live state of the hosting account. **This file is a record, not a design** — update it whenever
something on the host changes, or it stops being useful.

Last confirmed: **2026-08-23**, from `tools/host-probe.php` run on the live host.
Last reviewed against the repository: **2026-08-28** — the PHP table and the uploads section were
re-checked directly on the host that day.

---

## The account

| | |
|---|---|
| Provider | cPanel shared hosting |
| Web server | **LiteSpeed** |
| Primary domain | `tech4time.bd` |
| Server IP | `103.138.189.25` |
| Account | `techtime` |
| Document root | `/home/techtime/public_html` |
| Private store | `/home/techtime/t4t-private` — 0700, writable, **outside the web root** |
| HTTPS | on, AutoSSL |
| SSH | **enabled** on port 22, with Terminal and Git Version Control also available |
| SSH host key | `SHA256:bSJs6qWqhlP3gLNzXrKClg5uP0zQoOYypucJH6fOF0U` (ED25519) |

## PHP

| | |
|---|---|
| Required | 8.1 or newer — the code uses the `never` return type |
| Developed against | 8.3 |
| On the host | **8.2.33** |
| argon2id | **available** — this is what is used; bcrypt is not needed |
| One hash costs | **80 ms** — the deliberate expense that makes an offline attack slow |
| `mbstring`, `dom` | both present |
| **`gd`** | **present** — bundled 2.1.0, with WebP, JPEG, PNG and AVIF. This is what re-encodes an uploaded picture, and the admin refuses uploads without it |
| `exif` | present |
| `upload_max_filesize` / `post_max_size` | 512M each; `memory_limit` 512M |
| `max_input_vars` | **10000** — the default is 1000, and the company profile form posts around 550. See `ADMIN_TAIL_FIELD` (in tech4time-website-backend) |
| `random_bytes`, sessions | both present |
| `mail()` | available; `sendmail_path` is `/usr/sbin/sendmail -t -i` |

Mail was proven end to end: the contact form delivers to `info@tech4time.bd` with JavaScript on
and off.

### If the deploy fails on the host key

`deploy.yml` pins that fingerprint in `known_hosts` rather than accepting whatever answers, so a
changed key **stops the deploy** instead of trusting it. Shared hosts do move accounts between
machines, so this can happen for an innocent reason — but it looks identical to the guilty one, and
the check exists because you cannot tell them apart by looking.

Confirm the new key out of band before updating the `SSH_HOST_KEY` secret: ask the host, or read
the fingerprint from cPanel's own Terminal, rather than from another `ssh-keyscan` — which would
ask the same question of the same answerer.

```bash
ssh-keyscan -p 22 -t ed25519 103.138.189.25 2>/dev/null | ssh-keygen -lf -
```

`SSH_HOST` must stay the bare IP. The pinned line names the host as `103.138.189.25`, and a
hostname in that secret would not match it.

> **The probe itself is gone, and must stay gone.** `tools/host-probe.php` is upload-run-delete.
> It was left on the host after the first run and was reachable at `/host-probe.php` — token-gated,
> but it sends mail on every request and reports the PHP build, the paths and the store location.
> `verify_live.py` now asserts `/tools/host-probe.php` answers 403 on every deploy.
>
> One trap worth knowing, because it cost an hour: **the probe's output caches in the browser.**
> Re-running the same URL after creating the admin account reported `Account set up: no` from cache
> while `admins.json` sat on disk. Re-request with a changed query string, or use another browser.

---

## Mail

### Mailboxes

| Address | Exists | For |
|---|---|---|
| `info@tech4time.bd` | **yes** | where the contact form sends enquiries |
| `no-reply@tech4time.bd` | **yes** | the envelope sender for outgoing mail |
| `admin@tech4time.bd` | **yes** — created 2026-08-23 | where a password reset code goes |

> **Created, not yet proven.** Existing and receiving are different facts, and only the second one
> matters on the day you cannot sign in. It is proven by
> *admin-activation.md* (in tech4time-website-backend) step 6 — a real reset code, sent by
> the live site, read in that mailbox — not by sending a test message to it from elsewhere, which
> exercises none of the path that matters.

### DNS — confirmed

| Record | Value | |
|---|---|---|
| **MX** | `0 tech4time.bd` | mail for the domain is handled by **the web server itself** and never leaves the box |
| **SPF** | `v=spf1 +a +mx +ip4:103.138.189.25 include:spf.mysecurecloudhost.com ~all` | authorises this server to send as the domain |
| **DKIM** | cPanel `default` selector | outbound mail is signed |
| **DMARC** | `v=DMARC1; p=none;` | **monitoring only — and reporting nothing** |

**DMARC is deliberately at `p=none`.** Worth tightening to `p=quarantine` once reset delivery is
proven — not before. At `p=none` a failure is visible; at `p=quarantine` it is silently binned,
which is the worst way to discover a mail problem.

> **The record carries no `rua=`.** Checked against live DNS on 2026-08-27: the whole value is
> `v=DMARC1; p=none;`. `p=none` means "do not enforce, tell me what you saw" — and with no `rua=`
> address there is nowhere to tell. So this is not monitoring; it is the absence of a policy
> wearing a policy's clothes, and a week of it produces no evidence that tightening would be safe.
>
> **Adding `rua=mailto:admin@tech4time.bd` costs nothing and changes no delivery**, because the
> policy stays `p=none`. It is the step that has to come before `p=quarantine`, and it is the one
> that was missing.

### Performance — lab, measured 2026-08-27

Lighthouse 12.8.2 against `https://tech4time.bd/`, mobile form factor, simulated Slow 4G. INP is
not a load metric and Lighthouse does not report it, so it was measured separately: a real Chrome
at 390×844 under Slow 4G **and 4× CPU throttling**, with the navigation toggle and the theme toggle
actually clicked and the page scrolled.

| | Measured | Google's "good" bar | |
|---|---|---|---|
| **LCP** | **1.5 s** | ≤ 2.5 s | |
| **CLS** | **0** | ≤ 0.1 | not 0.02 — zero |
| **INP** | **24 ms** | ≤ 200 ms | worst event of any kind was 32 ms |
| FCP | 1.4 s | ≤ 1.8 s | |
| TBT | 40 ms | ≤ 200 ms | the lab stand-in for INP |
| Performance score | **99 / 100** | | |

**These are lab numbers and must not be quoted as field numbers.** Simulated throttling is a model
of a slow phone, not a slow phone; and one run from one place is not a distribution. What they do
establish is that the site has no structural performance problem to find — Lighthouse's entire list
of opportunities came to 13 KiB of offscreen images and 2 KiB of unminified JavaScript.

Why the numbers are what they are, so a future change can be judged against it: HTTP/2, **brotli on
both HTML and CSS**, assets cached for a year, HTML at `max-age=0, must-revalidate`, every script
`defer` except `theme-init.js` — which blocks deliberately, because that is what stops the theme
flashing. The home page is 74.8 KB of HTML compressing to 17.0 KB, of which 21.9 KB uncompressed is
the inlined icon sprite. That inlining is [0004](../90-decisions/0004-self-hosted-strict-csp.md)
and the CSP, paid for in bytes that brotli mostly gives back.

### Quotas

cPanel enforces an **hourly outbound mail limit**. The reset throttle is sized to stay under it:
three per hour per account, five per address, **twenty overall**.

That global cap is not about this site. Somebody hammering the admin's forgot-password page could use the
allowance up, which would stop the genuine reset from being delivered at the moment it was wanted.

---

## Uploaded pictures

`~/public_html/uploads/` on this host, `~/admin.tech4time.bd/public/uploads/` on the other. Neither
is in a repository, both are on the deploy **protect list**, and both are served by an `.htaccess`
allow-list of sixteen hex characters and three raster extensions —
[0019](../90-decisions/0019-uploaded-images-travel-their-own-channel.md).

Proven end to end on **2026-08-28**: a picture re-encoded on the admin host, signed, posted to
`/api/publish-asset.php`, and served from `https://tech4time.bd/uploads/` with the right
`Content-Type` — while the same basename with `.php` and `.svg` answered 403, and the directory
itself answered 403. The test files were removed from both hosts afterwards and both now answer 404.

**Neither directory ships.** They are created on first use by `upload_write()` and by the endpoint.
That was found the hard way: the first attempt answered "The picture could not be saved on this
server", because only `upload_accept()` had been creating it.

## SSL

| | |
|---|---|
| AutoSSL | for `tech4time.bd` and `www.tech4time.bd` |
| HTTPS redirect | **active** in `.htaccess` |
| HSTS | **active** — `max-age=31536000`, set in `.htaccess` and asserted by `tools/check_secrets.py`. No `preload`, deliberately |
| `includeSubDomains` | **off**, and must stay off until `admin.tech4time.bd` has its own certificate |

---

## Directory Privacy

Not in use on this host. It belongs to the backend's document root, as a temporary measure while
its own sign-in is being proven — *admin-activation.md* (in tech4time-website-backend).

> **Never add an `.htaccess` where cPanel writes one.** cPanel writes its own for this
> feature, and uploading over it silently removes the password.

---

## Outstanding on the host

1. **Delete the pre-split leftovers from `~/t4t-private/`.** Found on the live host 2026-08-27,
   and it is the one item here that is a *finding* rather than a task:

   ```
   ~/t4t-private/  admins.json  admins.json.bak  audit.log  resets.json  sessions/ (19 files)
   ```

   None of those belong to this half. This side's store has three files —
   [0017](../90-decisions/0017-two-private-stores.md) — and `T4T_PRIVATE_FILES` in `lib/private.php`
   names only those three, so `t4t_private_path()` throws on any of the above. **Nothing here can
   read them, and nothing writes them:** this side calls `session_start()` nowhere, and PHP's own
   `session.save_path` on the host is `/var/cpanel/php/sessions/ea-php82`.

   They are the monolith's, from before the split. `admins.json` holds account `tech4time-admin`
   with a real **password hash, a real authenticator secret and ten recovery-code hashes**, last
   touched 2026-08-24; the audit log runs 2026-08-23 to 2026-08-24. It is *not* a copy of the live
   backend's account — the two files have different digests, so this is a second, forgotten set of
   admin credentials.

   Unreachable is not the same as gone, and this documentation said the public host holds no
   password hash — which was false while they sat there. Removed on 2026-08-27 by name, not by
   glob; the three ADR 0017 files were digest-checked before and after and are unchanged, and
   `verify_live.py` passed 28/28 afterwards with the contact and careers pages still rendering.

2. ~~**Publish a `rua=` address on the DMARC record.**~~ **Done 2026-08-27** — see the DNS section
   above.

3. **Consider `p=quarantine`**, once a week or two of those reports show every legitimate
   sender passing — not before, because at `p=none` a failure is visible and at `p=quarantine` it
   is silently binned
4. **FIELD-measured** LCP, CLS and INP. Lab figures against the live host were taken on
   2026-08-27 and are below; field figures come from CrUX, which needs 28 days of real traffic and
   therefore cannot exist yet. Re-check `pagespeed.web.dev` once the site has been visited for a
   month
5. If `mail()` proves unreliable, the fix is authenticated SMTP against the host's own mail server —
   not more `mail()` retries

Items that stood here and are done, recorded above rather than pending: `tools/host-probe.php` ran
on 2026-08-23 (its figures are the PHP and signing rows at the top of this file, and the probe
itself was deleted); HSTS is active; **`admin@tech4time.bd` was proven to receive on 2026-08-27**,
by a password reset run end to end with the emailed code read out of that mailbox; and **pressing
Reply on a contact-form mail was confirmed on 2026-08-27** to address the visitor rather than
`no-reply@`, which is what `Reply-To` is there for.

---

## Built since this file first named them as planned

| | |
|---|---|
| `admin.tech4time.bd` | the backend, serving from its own document root — [environments.md](../20-deployment/environments.md) · *0018* (in tech4time-website-backend) |
| Deploy key | rsync over SSH from GitHub Actions, in place for both halves — [ci-cd.md](../20-deployment/ci-cd.md) |
| Pinned `known_hosts` | the host key comes from a secret rather than `ssh-keyscan` on each run |

Nothing on the host is planned-but-absent today. What is left is the list above this one — things
to *prove*, not things to build.

---

## Keeping this current

Update it whenever you change something on the host — a mailbox, a DNS record, the PHP version, an
SSL certificate. Put the date at the top.

Nobody will trust it if it is stale, and the whole value of the file is that somebody arriving cold
can read what is true rather than log in and work it out.
