#!/usr/bin/env python3
"""
Prove the public site still carries nothing it should not.

Build/audit tool. NOT deployed to the web server (see tools/README.md).
Run from the repo root:  python3 tools/check_secrets.py

WHY THIS EXISTS
The failures this looks for share one property: everything goes on working.
A key committed by accident, a private store that turns out to be web-reachable,
an .htaccess that quietly stopped blocking lib/ — none of them break a page,
fail a save or raise an error. The site is exactly as usable the day after as
the day before.

So these are asserted mechanically, on every run, rather than remembered.

WHAT THIS HALF CHECKS
This is the FRONTEND. It has no accounts, no password hashes, no second factor
and no sessions — that is the whole point of the split, and the third check
below is that promise made mechanical rather than merely stated.

The admin's own protections — the setup window, the bypass flags, what reaches
the audit log — are checked by the same-named tool in tech4time-website-backend, which
is where that code now lives.

WHAT IS CHECKED BY BEHAVIOUR RATHER THAN BY READING
Where a check can run the real code, it does. Pointing lib/private.php at a
directory inside the web root and insisting it refuses is worth more than
grepping for the function that refuses, because the grep goes on passing when
the call to it is deleted.
"""

import os
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent

# Names that must never be committed, wherever they turn up.
SECRET_NAMES = [
    "secret.key", "publish.key", "admins.json", "audit.log", "audit.log.1",
    "throttle.json", "resets.json", "setup-token.txt",
]
SECRET_DIRS = ["t4t-private", "t4t-private-admin", ".dev-private"]

# What belongs to the backend and must not reappear here. Each was deleted in
# the split; each would work perfectly well if somebody copied it back, which
# is exactly why this is a check and not a comment.
BACKEND_ONLY = [
    "admin",
    "lib/auth.php",
    "lib/admin.php",
    "lib/totp.php",
    "lib/reset.php",
    "lib/mailer.php",
    "lib/publish_client.php",
]

problems: list[str] = []
notes: list[str] = []


def ok(label: str) -> None:
    print(f"  ok    {label}")


def bad(label: str, detail: str = "") -> None:
    print(f"  FAIL  {label}" + (f"\n          {detail}" if detail else ""))
    problems.append(label)


def tracked() -> list[str]:
    out = subprocess.run(["git", "ls-files"], cwd=ROOT,
                         capture_output=True, text=True)
    return out.stdout.splitlines() if out.returncode == 0 else []


# ------------------------------------------------------- nothing is committed


def check_nothing_committed() -> None:
    print("\nnothing secret is in git")

    files = tracked()
    if not files:
        notes.append("not a git checkout — the commit checks were skipped")
        print("  --    skipped: git said nothing")
        return

    found = [f for f in files
             if Path(f).name in SECRET_NAMES
             or any(part in SECRET_DIRS for part in Path(f).parts)
             or f.endswith(".key")]

    if found:
        bad("no secret file is tracked", ", ".join(found))
    else:
        ok("no secret file is tracked")

    # .gitignore has to catch them where they would actually land.
    samples = [f"{d}/{n}" for d in SECRET_DIRS for n in SECRET_NAMES[:2]]
    missed = []

    for sample in samples:
        done = subprocess.run(["git", "check-ignore", "-q", sample], cwd=ROOT)
        if done.returncode != 0:
            missed.append(sample)

    if missed:
        bad("a stray private store would be ignored by git", ", ".join(missed))
    else:
        ok("a stray private store would be ignored by git")


# ------------------------------------------------- the store stays out of reach


def check_store_refuses_web_root() -> None:
    print("\nthe private store refuses to be reachable")

    inside = ROOT / "content" / "would-be-web-readable"

    done = subprocess.run(
        ["php", "-r",
         "require 'lib/private.php';"
         "try { t4t_private_dir(); echo 'ACCEPTED'; }"
         "catch (RuntimeException $e) { echo 'REFUSED'; }"],
        cwd=str(ROOT), capture_output=True, text=True,
        env=dict(os.environ, T4T_PRIVATE=str(inside)),
    )

    if done.stdout.strip() == "REFUSED":
        ok("a store inside the document root is refused")
    else:
        bad("a store inside the document root is refused",
            f"php said {done.stdout.strip()!r} {done.stderr.strip()[:200]}")

    if inside.exists():
        bad("and refusing it creates nothing",
            f"{inside.relative_to(ROOT)} was created anyway")
        try:
            inside.rmdir()
        except OSError:
            pass
    else:
        ok("and refusing it creates nothing")


# ------------------------------------------- this half holds no credentials


def check_ships_no_authentication() -> None:
    """ADR 0011's promise, made mechanical.

    "The public site stops shipping authentication code entirely." Nothing
    enforces that by itself: every one of these files would work if it were
    copied back, and the site would look identical with a login page on it.
    """
    print("\nthe public site ships no way to sign in")

    back = [p for p in BACKEND_ONLY if (ROOT / p).exists()]
    if back:
        bad("nothing belonging to the admin is here", ", ".join(back))
    else:
        ok("nothing belonging to the admin is here")

    # The names, not just the files — a hand-copied fragment would not bring
    # the filename with it.
    php = [p for p in ROOT.rglob("*.php")
           if "tools" not in p.parts and p.name != "check_secrets.py"]
    leaked = []
    for path in php:
        text = path.read_text()
        for needle in ("password_hash(", "password_verify(", "auth_attempt(",
                       "totp_verify(", "admins.json"):
            if needle in text:
                leaked.append(f"{path.relative_to(ROOT)}: {needle}")

    if leaked:
        bad("no page here verifies a password", "; ".join(leaked))
    else:
        ok("no page here verifies a password")

    # The store cannot even NAME an account file. t4t_private_path() throws on
    # a key it does not know, so this is not a convention — there is no path
    # for a password hash to be written to.
    done = subprocess.run(
        ["php", "-r",
         "require 'lib/private.php';"
         "echo implode(',', array_keys(T4T_PRIVATE_FILES));"],
        cwd=str(ROOT), capture_output=True, text=True,
    )
    held = set(done.stdout.strip().split(",")) if done.stdout.strip() else set()

    if held & {"admins", "setup", "sessions", "resets"}:
        bad("the private store has no name for an account file",
            f"T4T_PRIVATE_FILES still lists {sorted(held & {'admins', 'setup', 'sessions', 'resets'})}")
    else:
        ok(f"the private store has no name for an account file ({', '.join(sorted(held))})")


# --------------------------------------------- only one thing writes content


def check_one_writer() -> None:
    """The public site writes exactly one kind of file, from exactly one place.

    Everything a visitor can reach is a read. The only write is api/publish.php
    landing a document the backend signed, and lib/throttle.php counting
    attempts. A second writer appearing outside lib/ is worth noticing before
    it is a way in rather than after.
    """
    print("\nonly the publish endpoint writes content")

    writers = []
    for path in ROOT.rglob("*.php"):
        if "tools" in path.parts or "lib" in path.parts:
            continue
        text = path.read_text()
        if "store_write(" in text or "file_put_contents(" in text:
            writers.append(path.relative_to(ROOT).as_posix())

    # EXACTLY TWO, and the equality is the point. A third writer appearing on
    # this host is the single change most worth noticing, whatever it is for:
    # everything else here only reads.
    ALLOWED_WRITERS = ["api/publish-asset.php", "api/publish.php"]

    if sorted(writers) == ALLOWED_WRITERS:
        ok("the two publish endpoints are the only writers outside lib/")
    else:
        bad("the two publish endpoints are the only writers outside lib/",
            f"also writing: {sorted(set(writers) - set(ALLOWED_WRITERS))}"
            if set(writers) - set(ALLOWED_WRITERS)
            else f"one is missing: {sorted(set(ALLOWED_WRITERS) - set(writers))}")

    api = (ROOT / "api" / "publish.php").read_text()

    for needle, label in (
        ("publish_verify(", "it verifies the signature"),
        ("publish_check_envelope(", "it checks the envelope"),
        ("contract_sanitise(", "it re-sanitises what it was sent"),
        ("<= $held", "it refuses anything not strictly newer"),
    ):
        if needle in api:
            ok(label)
        else:
            bad(label, f"api/publish.php no longer calls {needle}")

    # The asset endpoint writes a file the web server SERVES, which is a
    # sharper edge than content/ -- so what it must do is asserted separately
    # rather than assumed to be the same.
    asset = (ROOT / "api" / "publish-asset.php").read_text()

    for needle, label in (
        ("publish_verify(", "the asset endpoint verifies the signature too"),
        ("publish_asset_type(", "and decides what a file is from its own header"),
        ("publish_asset_name(", "and names it from the bytes, never from the sender"),
    ):
        if needle in asset:
            ok(label)
        else:
            bad(label, f"api/publish-asset.php no longer calls {needle}")

    # The name it writes must be computed, never taken from the request. This
    # looks for the shape of the mistake rather than its absence: any $_SERVER,
    # $_GET, $_POST or $_FILES value reaching the path it opens.
    import re as _re
    tainted = _re.findall(r"PUBLISH_ASSET_DIR\s*\.\s*'/'\s*\.\s*(\$\w+)", asset)
    if tainted and all(v == "$name" for v in tainted):
        ok("and the path it opens is built from that name and nothing else")
    else:
        bad("and the path it opens is built from that name and nothing else",
            f"built from: {tainted}")


# ------------------------------------------------------ the rules still exist


def check_htaccess_blocks() -> None:
    print("\n.htaccess still closes what it closed")

    htaccess = (ROOT / ".htaccess").read_text()

    for pattern, label in (
        (r"RewriteRule \^lib/",       "lib/ is blocked"),
        (r"RewriteRule \^content/",   "content/ is blocked"),
        (r"RewriteRule \^tools/",     "tools/ is blocked"),
        (r"RewriteRule \^references/", "references/ is blocked"),
        (r"t4t-private",              "a stray private store is blocked"),
        (r'RewriteRule "\(\^\|/\)\\\."', "any dotted path segment is blocked"),
        (r"!\^/\\\.well-known/",      "and .well-known is exempt, so AutoSSL still renews"),
        (r"Strict-Transport-Security", "HSTS is set"),
        (r"Content-Security-Policy",  "the CSP is set"),
        # An ALLOW-list, not a block: uploads/ has to be served. It is the one
        # directory that is both written over the network and fetched by the
        # public, and this rule is the third of ADR 0019's three layers -- the
        # only one that still holds if the bytes were not re-encoded and the
        # name was not computed. Removing it is silent until somebody notices
        # /uploads/x.php answering 200.
        (r"\^/uploads/\[0-9a-f\]\{16\}",
         "uploads/ serves the shape it mints, and nothing else"),
    ):
        if re.search(pattern, htaccess):
            ok(label)
        else:
            bad(label, f"nothing in .htaccess matches {pattern!r}")

    if re.search(r'X-Robots-Tag[^\n]*\n[^\n]*api|api[^\n]*X-Robots-Tag', htaccess) \
            or ("X-Robots-Tag" in htaccess and "/api" in htaccess):
        ok("/api/ is kept out of search results")
    else:
        bad("/api/ is kept out of search results")


def main() -> None:
    check_nothing_committed()
    check_store_refuses_web_root()
    check_ships_no_authentication()
    check_one_writer()
    check_htaccess_blocks()

    for note in notes:
        print(f"\nnote: {note}")

    if problems:
        print(f"\n{len(problems)} problem(s):\n")
        for p in problems:
            print(f"  - {p}")
        print("\nEach of these fails silently in production: the site goes on\n"
              "working and the only difference is what a stranger can reach.")
        sys.exit(1)

    print("\nThe public site's protections are all still in place.")


if __name__ == "__main__":
    main()
