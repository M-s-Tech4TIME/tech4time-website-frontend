# Backups

**Applies to:** both

What to back up, what the defaults miss, and how to prove a restore works.

---

## What exists in three different places

| | Where | Backed up by |
|---|---|---|
| **Code** | git, and `public_html/` | git — already safe |
| **Content** | `public_html/content/*.json` on the host | the host only — **not in git after launch** |
| **Secrets** | `/home/USER/t4t-private/` | the host only — **never in git** |

Only the first is safe by default. The other two exist in exactly one place.

---

## The one that catches people out

> **`t4t-private/` lives at `/home/USER/`, not inside `public_html/`.**

A backup scoped to `public_html` therefore restores your entire website **except the ability to log
into it**. Everything looks fine until the day you need it.

**cPanel → Backup**, and confirm you are taking one of:

- **Full Account Backup** — includes the whole home directory ✅
- **Home Directory** partial backup — includes it ✅
- A `public_html`-only backup — **misses it** ❌

Check this now rather than discovering it during a restore.

---

## Back up `secret.key` separately

65 bytes, and it is the difference between two outcomes when the store is lost:

| Without it | With it |
|---|---|
| Every password must be reset | restore one file |
| Every recovery code reissued | nothing else changes |
| Every authenticator re-paired | — |

```bash
cat ~/t4t-private/secret.key
```

Put the line in a password manager. It is a secret — anyone holding it plus the backend's `admins.json` can attack
your password offline — so treat it like one. Not in email, not in a note file, not in the
repository.

---

## What to back up, and how often

| | How often | Why |
|---|---|---|
| `content/*.json` | weekly, and before any deploy | live job posts and contact details exist nowhere else |
| `t4t-private/` whole | weekly | accounts, authenticator secrets, the audit log |
| `secret.key` alone | once, and again if it ever changes | the cheapest insurance here |
| Full cPanel account | monthly | everything, including mail and DNS |

Before any deploy that could touch content:

```bash
scp user@tech4time.bd:~/public_html/content/*.json ./backup-$(date +%F)/
```

---

## Proving a restore works

An unverified backup is a belief, not a backup. Do this once, deliberately, on a day when nothing is
wrong.

### Content

```bash
# on the host
cd ~/public_html/content
cp careers.json careers.json.proof
# edit a job title in the admin, confirm this page changes
cp careers.json.proof careers.json
# confirm the page changes back
rm careers.json.proof
```

There is also one generation of automatic backup — `careers.json.bak`, written on every save. Good
for "I just deleted the wrong post", not for anything older.

### The private store

```bash
# from a backup, into a scratch location
mkdir -p /tmp/restore-test
tar xzf backup.tar.gz -C /tmp/restore-test --strip-components=1 t4t-private

python3 tools/verify_live.py https://tech4time.bd
```

If that prints your account, the backup is real. Then `rm -rf /tmp/restore-test` — it contains
password hashes and authenticator secrets.

---

## What must never be backed up into git

`.gitignore` covers these, and `check_secrets.py` fails the build if one appears:

```
t4t-private/    .dev-private/    *.key    setup-token.txt
content/*.json.bak
```

A `secret.key` in a commit is in the history forever, and rotating it means resetting every
password. If it happens, treat it as *rung 8* (in tech4time-website-backend) — rotate
rather than just removing the file.

---

## Now that the repository is split

`admin.tech4time.bd` holds the content of record; this site holds a replica. That changes the
priority in two ways.

**The backend's store is the one whose loss matters.** This side's holds three files, and two of
them are reconstructible: `secret.key` peppers nothing but the throttle's keys, and losing it costs
a cleared rate-limit counter. `throttle.json` is a counter.

**`publish.key` is the exception, and it is shared.** Losing this side's copy is recoverable by
copying the backend's; losing *both* means minting a new one and placing it on both hosts, during
which every publish is refused as `unknown-key`. Back it up with `secret.key`, in the same place.

**`content/` here is reconstructible by re-publishing** — `tech4time-website-backend/tools/reconcile.py`
sends anything this site is behind on. It is still worth backing up, because "reconstructible" means
"if the backend still has it".

The two stores are separate and neither can read the other's:
[0017](../90-decisions/0017-two-private-stores.md).

---

## The checklist

```
[ ] cPanel backup covers the HOME DIRECTORY, not just public_html
    — which is also the only way BOTH private stores are covered
[ ] secret.key saved in a password manager
[ ] publish.key saved with it — losing both halves' copies stops publishing
[ ] content/*.json downloaded before any deploy
[ ] A restore has actually been tested at least once
[ ] Recovery codes stored somewhere that is not this server
```
