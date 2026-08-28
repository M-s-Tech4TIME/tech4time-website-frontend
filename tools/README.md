# tools/

Build, audit and test scripts. **None of it is deployed** — `.htaccess` blocks `/tools/` as a
backstop, but the real rule is that this directory never gets uploaded.

One file is an exception, uploaded by hand and then deleted: `host-probe.php`. There is no
`admin-cli.php` here and there must never be one — this host holds no accounts and no password
hash. See `CLAUDE.md`.

---

## The reference has moved

**Every script is documented in [../docs/40-reference/tools.md](../docs/40-reference/tools.md)** —
what it does, when to run it, and what it proves.

This file used to carry that plus the host's mail configuration and the content guidance. Those are
now in `docs/`, so each fact lives in exactly one place:

| Was here | Now |
|---|---|
| What each script does | [40-reference/tools.md](../docs/40-reference/tools.md) |
| The private store, and the key both halves sign with | [10-development/server-side/publish-api.md](../docs/10-development/server-side/publish-api.md) |
| Host state — mail, DNS, DMARC, quotas | [40-reference/host-facts.md](../docs/40-reference/host-facts.md) |
| Deploying without destroying live posts | [20-deployment/routine-deploys.md](../docs/20-deployment/routine-deploys.md) |
| The checks to run before committing | [10-development/testing.md](../docs/10-development/testing.md) |

Recovering a lost admin password, and the day-to-day content guidance, are the editor's business
and live with it: `tech4time-website-backend/docs/30-operations/secrets-recovery.md` and
`tech4time-website-backend/docs/30-operations/content-runbook.md`. They are named with the
repository in front because they are not in this one.

---

## The short version

```bash
python3 tools/serve.py                      # run the site locally — NOT python3 -m http.server

python3 tools/check_contrast.py             # before committing
python3 tools/check_css.py
python3 tools/check_content_model.py
python3 tools/check_secrets.py
python3 tools/check_docs.py
python3 tools/inject_icons.py --check
python3 tools/check_shared_markup.py
python3 tools/audit_pages.py
python3 tools/build_deploy_set.py --check
python3 tools/check_shared_lib.py
python3 tools/check_shared_repos.py
```

`CLAUDE.md` carries the conditional tests on top of that list — the publish endpoint, the contact
handler, the store, and the browser suite.

Adding a script? Give it a docstring saying what it proves and how to run it, keep to the standard
library (Pillow is the one exception, for the asset builders), and add it to
[40-reference/tools.md](../docs/40-reference/tools.md) — `check_docs.py` fails until you do.

---

## Subdirectories

| | |
|---|---|
| `templates/` | the canonical header, footer, head and script markup — see its own README |
| `masters/` | source artwork for the asset builders |
| `shots/` | screenshot output, gitignored |
