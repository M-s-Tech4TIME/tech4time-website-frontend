# Routine deploys

**Applies to:** both

Pushing an update to a site that is already live, without destroying anything people have written.

---

## The one rule

> **The host's `content/` and `uploads/` are the real data. Yours is test data.**

Job posts and contact details are published from the admin host. The pictures behind them are
uploaded there too. Neither is in your working copy. A deploy that includes `content/` destroys the
words; one that deletes `uploads/` destroys every picture the editor has ever accepted. Both losses
are silent — the site keeps working, showing older content, until somebody notices their job post is
gone or a logo has turned into a broken image.

`uploads/` is the more dangerous of the two, and it is the one the excludes below existed without
for a while. It is named in `deploy.yml`'s protect list and in the independent gate that reads the
dry-run plan; this page is the path that has no gate at all, so it has to be right by itself.

---

## What to upload, and what never to

```
UPLOAD                            NEVER UPLOAD
  index.php    404.php              content/          ← live data
  pages/                            uploads/          ← live pictures
  assets/                           tools/            ← scripts, incl. password reset
  lib/          api/                docs/   plans/
  contact-handler.php               references/
  .htaccess                         deploy/   .claude/
  sitemap.php  robots.php           *.md   *.py
  manifest.php favicon.php          admin/            ← nothing, ever
```

`api/` and `favicon.php` are easy to forget and both are in `build_deploy_set.py`'s REQUIRED list:
`api/publish.php` is the only route content takes to the live site, and `/favicon.ico` answers from
`favicon.php` — a browser probes that address blindly, before it has read a line of the page.

### With rsync

```bash
rsync -avz --delete \
  --exclude='content/' \
  --exclude='uploads/' \
  --exclude='tools/' \
  --exclude='docs/' \
  --exclude='plans/' \
  --exclude='references/' \
  --exclude='deploy/' \
  --exclude='.claude/' \
  --exclude='.git*' \
  --exclude='*.md' \
  --exclude='*.py' \
  --exclude='admin/' \
  --exclude='.well-known/' \
  --exclude='cgi-bin/' \
  --exclude='error_log' \
  --exclude='.user.ini' \
  --exclude='php.ini' \
  --exclude='.htpasswd' \
  ./ user@tech4time.bd:~/public_html/
```

An `--exclude` does two jobs here: it keeps the path out of what is sent, **and** it protects the
receiver's copy from `--delete`. That second job is why the last seven lines are there — none of
those paths is in this repository, all of them are the panel's, and `--delete` would remove the lot.
They are the same set `deploy.yml` protects, kept in step by hand because this path has no CI behind
it.

**`--delete` without those excludes will remove live data.** Run it with `--dry-run` first, every
time. Read the output. Look specifically for `deleting content/` and `deleting uploads/` — and
treat *any* `deleting` line you cannot account for as a reason to stop.

### With SFTP or File Manager

Upload the changed directories only. Never drag the whole repository across.

---

## Before you upload

```bash
python3 tools/check_contrast.py
python3 tools/inject_icons.py --check
python3 tools/check_shared_markup.py
python3 tools/check_content_model.py
python3 tools/check_secrets.py
python3 tools/check_docs.py
python3 tools/audit_pages.py
```

And if the change touched anything server-side:

```bash
python3 tools/test_publish.py
python3 tools/test_contact_handler.py
```

---

## After you upload

- [ ] The changed pages look right
- [ ] `/pages/careers/` and `/pages/contact/` still render — **live content intact**
- [ ] `/api/publish.php` still answers 405 to a GET
- [ ] `lib/`, `content/` and `tools/` still return 403

That third check matters more than it looks: an `.htaccess` that failed to upload takes the blocking
rules with it, and nothing about the site's appearance will tell you.

---

## Cache busting

Asset filenames are not content-hashed — there is no build step to hash them — and `.htaccess`
caches CSS, JS and fonts for a year.

**A changed `base.css` will not reach returning visitors on its own.** Append a version query to
the tag, and bump it in the same breath as the file:

```html
<link rel="stylesheet" href="/assets/css/base.css?v=2">
```

`python3 tools/check_cache_bust.py` refuses a release where a changed stylesheet or script is still
served from the URL it had on `main`. Run it before every deploy — this rule was kept by memory
until it was missed twice, and both misses were invisible from a clean cache.

### A stylesheet is one edit; a script is still seventeen

**Stylesheets.** The five every page loads are named in `HEAD_STYLES`, in `lib/head.php`, and a
page's own is the third argument of its `seo_head()` call. Both are read on the request, so a bump
is one edit in one file — the page it appears on is wherever that file is used. This used to be a
template pasted into every page at birth and sixteen copies to keep in step; it is not any more.

**Scripts.** The tags live in `tools/templates/scripts.html`, and **`propagate_shared.py` does not
carry it out to the pages.** It handles the **hero circuit, and nothing else** — `BLOCKS` in that
file has exactly one entry. The header, footer and dock were the other three until they stopped
being copied markup at all and became `lib/body.php` reading `content/chrome.json`
([ADR 0023](../90-decisions/0023-the-header-and-footer-are-emitted-once.md)), which left this
sentence naming three blocks that no longer exist anywhere to propagate;
`scripts.html` is read by `assemble_page.py` when a page is *created*, and after that each page
holds its own copy. So a script bump still means editing the template **and** every page, and
`check_shared_markup.py` pins the expected `main.js` URL so a page left behind fails rather than
merely behaving oddly for people who have been here before.

`check_cache_bust.py` reads all three places — `HEAD_STYLES`, each `seo_head()` call, and the
pages' own script tags — so neither kind can change behind an unchanged URL without it saying so.

### What being wrong looks like

Nothing. Neither failure raises an error, and neither is visible in a browser opened on a clean
cache, which is every browser a developer tests in:

- **A stale stylesheet** meets markup written for rules it does not have. Undefined animation
  durations resolve to `0s`, undefined classes to nothing at all. The page renders; parts of it
  simply do not move.
- **A stale `main.js`** iterates the `MODULES` allow list it was cached with, so any module added
  since registers itself and is never initialised. No error, no console line — the feature is
  absent, and only for returning visitors.

Lowering `max-age` for that file type in `.htaccess` is the other way, and costs every visitor a
revalidation on every asset forever. Prefer the query.

---

## Changing the footer's contact details

**This is not a deploy any more.** It is a save, at
`https://admin.tech4time.bd/?s=chrome`, on the footer screen.

The footer used to be markup, so the sequence was: download the server's `content/contact.json`,
run a script that pushed the details into all sixteen pages, and upload the pages. Getting the
download wrong pushed your stale local details into every one of them, and the admin carried a
banner — fed by a fingerprint the frontend reported in every publish response — because the gap was
otherwise invisible.

The footer renders from `content/chrome.json` now. Its contact rows are the footer's **own**, not a
copy of the contact page's, so there is nothing to push and nothing to fall behind; what keeps the
two honest is a standing notice in the editor that never blocks a save.
[ADR 0023](../90-decisions/0023-the-header-and-footer-are-emitted-once.md) ·
[shared-markup.md](../10-development/frontend/shared-markup.md)

---

## Rolling back

There is no deploy history — the server holds one copy of the site.

- **Code** is in git. Check out the previous commit and re-upload.
- **Content** has one generation of backup on the host: `content/careers.json.bak`, written on every
  save. Restore by renaming it.
- **Anything older** comes from the cPanel backup. [backups.md](../30-operations/backups.md)

---

## Doing it automatically instead

The exclude list above is now also a program — `tools/build_deploy_set.py` — which builds the
upload set from an allow list and asserts what is in it. **Losing live content has stopped
depending on anyone remembering a flag.**

```bash
python3 tools/build_deploy_set.py --check     # what would go, and what must not
python3 tools/build_deploy_set.py --out _deploy
rsync -avz --delete _deploy/site/ user@tech4time.bd:~/public_html/
rsync -av --ignore-existing _deploy/seed/ user@tech4time.bd:~/public_html/content/
```

That second line is the whole of the content rule: `--ignore-existing` creates what is absent and
overwrites nothing, so a job post on the host always wins.

**This is now the fallback, not the procedure.** A push to `main` does all of the above through
`.github/workflows/deploy.yml`, with a protect list and a gate that reads the dry run before
anything is written — [ci-cd.md](ci-cd.md). Reach for the commands here when the pipeline is broken
or unavailable, and note that they carry none of its safeguards: no gate, and the protect list is
whatever you remember to type.
