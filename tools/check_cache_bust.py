#!/usr/bin/env python3
"""
Refuse to ship a changed stylesheet or script that nobody will re-download.

Build/audit tool. NOT deployed to the web server (see tools/README.md).

    python3 tools/check_cache_bust.py                 # against origin/main
    python3 tools/check_cache_bust.py --base HEAD~1
    python3 tools/check_cache_bust.py --base v1.2.0

WHY THIS EXISTS
Filenames here are not content-hashed, because ADR 0001 forbids the build step
that would hash them, and `.htaccess` caches CSS, JS and fonts for a year. So a
released stylesheet reaches a returning visitor only if the URL in the markup
changed too -- a version query, bumped by hand, in the same breath as the file.

That is a rule kept by remembering, and remembering is not a mechanism. It has
already been missed twice in this repository, and both misses were silent:

  * `assets/css/layout.css` was rewritten with a new set of animation classes
    while the markup still asked for the unversioned URL. A returning visitor
    would have got the new markup against a year-old stylesheet, which does not
    error -- it just leaves every rule the new classes need undefined.

  * `assets/js/main.js` gained an entry in its hardcoded MODULES allow list. A
    stale copy iterates the old array, so the module registers itself and is
    never initialised. No error. No console line. The feature is simply absent,
    and only for the visitors who have been here before -- which is to say, not
    for whoever is checking.

Neither shows up in a browser opened on a clean cache, which is every browser
a developer tests in. This is the check that does not have to remember.

WHAT IT COMPARES
The reference *as written in the markup*, at the base revision and now. A
version query is the usual way to change it, but the check does not care how:
renaming the file works, and so would a hash if this project ever grew one.
The rule is only that a changed asset is not still served from an unchanged
URL.

A file no page references (a module loaded by another module, say) is reported
and skipped: there is no markup URL to bump, and its freshness is its
importer's problem.
"""

import argparse
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
WATCHED = re.compile(r"^assets/.*\.(css|js)$")

# Every place a page can name an asset. The query string is part of the capture
# on purpose -- it is the thing being checked.
REFERENCE = re.compile(r'(?:href|src)="(/assets/[^"]+)"')


def git(*args: str) -> str:
    out = subprocess.run(["git", *args], cwd=ROOT, capture_output=True, text=True)
    if out.returncode != 0:
        raise SystemExit(f"git {' '.join(args)} failed:\n{out.stderr.strip()}")
    return out.stdout


def pages() -> list[str]:
    """Every file that can carry a reference, as repository-relative paths."""
    found = ["index.php", "404.html"]
    found += [str(p.relative_to(ROOT)) for p in (ROOT / "pages").rglob("index.*")]
    return sorted(p for p in found if (ROOT / p).exists())


def references(revision: str | None, paths: list[str]) -> dict[str, set[str]]:
    """asset path -> the set of URLs the markup uses for it, at one revision."""
    urls: dict[str, set[str]] = {}
    for rel in paths:
        if revision is None:
            text = (ROOT / rel).read_text(encoding="utf-8", errors="replace")
        else:
            out = subprocess.run(["git", "show", f"{revision}:{rel}"],
                                 cwd=ROOT, capture_output=True, text=True)
            if out.returncode != 0:      # the page did not exist yet
                continue
            text = out.stdout
        for url in REFERENCE.findall(text):
            urls.setdefault(url.split("?", 1)[0].lstrip("/"), set()).add(url)
    return urls


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--base", default="origin/main",
                    help="revision to compare against (default: origin/main)")
    args = ap.parse_args()

    base = args.base
    if subprocess.run(["git", "rev-parse", "--verify", "--quiet", base],
                      cwd=ROOT, capture_output=True).returncode != 0:
        print(f"check_cache_bust: no such revision '{base}'.")
        print("Fetch it first, or pass --base. Nothing was checked.")
        return 2

    changed = [line for line in git("diff", "--name-only", f"{base}...HEAD").split("\n")
               if WATCHED.match(line)]
    if not changed:
        print(f"check_cache_bust: no stylesheet or script changed since {base}.")
        return 0

    now = references(None, pages())
    then = references(base, pages())

    problems, ok, unreferenced = [], [], []
    for asset in changed:
        here, before = now.get(asset, set()), then.get(asset, set())
        if not here:
            unreferenced.append(asset)
        elif here == before:
            problems.append((asset, sorted(here)))
        else:
            ok.append((asset, sorted(before) or ["(new)"], sorted(here)))

    print(f"check_cache_bust: {len(changed)} asset(s) changed since {base}\n")
    for asset, was, is_ in ok:
        print(f"  ok    {asset}")
        print(f"          {', '.join(was)}  ->  {', '.join(is_)}")
    for asset in unreferenced:
        print(f"  --    {asset} — no page references it directly; skipped")
    for asset, urls in problems:
        print(f"  FAIL  {asset}")
        print(f"          still served from {', '.join(urls)}")

    if problems:
        print(f"\n{len(problems)} changed asset(s) keep an unchanged URL.")
        print("Anyone who has visited before keeps the copy they already have,")
        print("for up to a year, and sees the new markup against the old file.")
        print("\nBump the version query in tools/templates/ AND in every page —")
        print("neither head.html nor scripts.html is propagated, so both have to")
        print("be edited. docs/20-deployment/routine-deploys.md, 'Cache busting'.")
        return 1

    print("\nEvery changed asset is served from a URL that changed with it.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
