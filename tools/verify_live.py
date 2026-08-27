#!/usr/bin/env python3
"""
Ask a live site whether the deploy actually landed.

Deploy tool. NOT deployed to the web server (see tools/README.md).

    python3 tools/verify_live.py https://tech4time.bd

WHY THIS EXISTS
A deploy can succeed at the transport and still leave a broken site, and the
two failures look nothing alike.

A page that did not arrive is loud: somebody clicks it and gets a 404. A
.htaccess that did not arrive is silent — every page still renders, every
image still loads, and the only difference is that lib/, content/ and the
private store have stopped returning 403. Nothing about the site's appearance
will tell you, and the first person to find out will not be you.

So the 403s below matter more than the 200s. The 200s prove the site is up,
which anyone would notice; the 403s prove it is still closed, which nobody
would.

IT LOOKS TWICE BEFORE IT FAILS
A deploy that has just finished rsyncing is a deploy the web server has not
finished reading. LiteSpeed re-reads .htaccess and rebuilds the vhost after
files under it change, and for a few seconds in that window the host answers
every path with 200 and none of the headers — a cPanel default page, not this
site. This check runs within a second of the last file landing, so it sees that
window sometimes.

That happened on the admin host on 2026-08-26: 6 of 22, with /no-such-page-here
answering 200 and every header absent. The deploy was fine; the site was fine a
minute later; the red X was on a good release. The same window exists here —
this host runs the same LiteSpeed and this check runs at the same moment — so
the same second look applies.

So a failing check is looked at again after RETRY_AFTER seconds, and only a
failure that survives the second look is a failure. The distinction is real: a
server mid-reload recovers in seconds and a broken .htaccess never does.

A check that only passed the second time is reported as such rather than
quietly counted as a pass — "the site needed N seconds to settle" is worth
knowing, and a check that started needing two looks every time would be telling
you something.

WHAT IT DOES NOT DO
It does not sign in, and it does not check content. It answers one question —
did the files and the rules that protect them reach this host — and gives it
back as an exit code CI can act on.
"""

import argparse
import ssl
import sys
import time
import urllib.error
import urllib.request

TIMEOUT = 20

# How long to give the server to finish reloading before believing a failure.
RETRY_AFTER = 20

# (path, what it must answer, why it matters)
#
# A tuple of acceptable statuses, because the honest answer for a blocked path
# is "not 200" — 403 and 404 are both correct and which one a server picks is
# its business. Pinning one would make this fail on a host that chose the other.
EXPECT = [
    ("/",                         (200,),      "the home page"),
    ("/pages/about/",             (200,),      "a static page"),
    ("/pages/careers/",           (200,),      "PHP renders, and content/ is readable from disk"),
    ("/pages/contact/",           (200,),      "the other PHP page"),
    ("/assets/css/base.css",      (200,),      "assets are served"),
    ("/robots.txt",               (200,),      "crawlers are told what to do"),
    ("/sitemap.xml",              (200,),      "and where to go"),
    ("/no-such-page-here",        (404,),      "a miss is a miss"),

    ("/lib/private.php",          (403, 404),  "the store locator is not source-readable"),
    ("/lib/auth.php",             (403, 404),  "nor is the sign-in"),
    ("/content/careers.json",     (403, 404),  "live content is not fetchable"),
    ("/content/contact.json",     (403, 404),  "nor are the contact details"),
    ("/tools/host-probe.php",     (403, 404),  "tools/ is blocked even if one is left there"),
    ("/t4t-private/secret.key",   (403, 404),  "a store dropped in the web root is refused"),
    ("/.git/HEAD",                (403, 404),  "a directory whose name starts with a dot"),
    ("/.git/config",              (403, 404),  "and every file inside it"),
    ("/references/",              (403, 404),  "working material is not the website"),
    ("/README.md",                (403, 404),  "documentation is not the website"),
    ("/tech4time-website.zip",    (403, 404),  "an archive left in the web root"),
    ("/error_log",                (403, 404),  "the server's own log names paths"),

    # THERE IS NO EDITOR HERE ANY MORE. The split moved it to
    # admin.tech4time.bd, and the deploy removes admin/ along with
    # lib/auth.php and everything else that could sign somebody in.
    #
    # 403 is what the live host answers, because cPanel's Directory Privacy
    # owns admin/.htaccess and the deploy protects it (ADR 0016) — so the
    # directory survives holding that one file, and LiteSpeed refuses a
    # request with no password. 404 is what a host without Directory Privacy
    # would answer. Either is right.
    #
    # **200 would be the finding.** It would mean a working editor had
    # reappeared on the public site, which is the one thing this half is
    # supposed to no longer be able to do.
    ("/admin/",                   (403, 404),  "no editor on the public site — it is at admin.tech4time.bd"),
    ("/admin/login.php",          (403, 404),  "and nothing inside it either"),

    # The one route content takes to this site. A GET must be refused with
    # 405, which is the endpoint answering — a 404 here means it did not
    # deploy, and the first anyone would know is a save in the admin that
    # never appears on the site.
    ("/api/publish.php",          (405,),      "the publish endpoint is deployed and refusing GET"),
]

# (path, header, what must be in its value)
HEADERS = [
    ("/",        "content-security-policy",   "script-src"),
    ("/",        "x-content-type-options",    "nosniff"),
    ("/",        "referrer-policy",           ""),
    ("/",        "strict-transport-security", "max-age="),
    ("/api/publish.php", "x-robots-tag",      "noindex"),
]


def fetch(url: str):
    """(status, headers) — an HTTP error is an answer, not an exception."""
    req = urllib.request.Request(url, method="GET", headers={
        "User-Agent": "tech4time-verify-live/1",
        # Asking for a fresh copy: a cached 'Account set up: no' from a probe
        # is how an hour went missing once. Verification that reads a cache is
        # not verification.
        "Cache-Control": "no-cache",
        "Pragma": "no-cache",
    })
    try:
        with urllib.request.urlopen(req, timeout=TIMEOUT) as r:
            return r.status, {k.lower(): v for k, v in r.headers.items()}
    except urllib.error.HTTPError as e:
        return e.code, {k.lower(): v for k, v in e.headers.items()}
    except (urllib.error.URLError, ssl.SSLError, TimeoutError, OSError) as e:
        return None, {"error": str(e)}


def check_path(origin, path, allowed):
    """(passed, description of what was got)."""
    status, info = fetch(origin + path)
    if status in allowed:
        return True, str(status)
    got = status if status is not None else info.get("error", "no answer")
    return False, f"wanted {' or '.join(str(a) for a in allowed)}, got {got}"


def check_header(origin, path, header, needle):
    status, info = fetch(origin + path)
    value = info.get(header, "")
    if value and needle.lower() in value.lower():
        return True, value[:60]
    return False, f"got {value[:80]!r}" if value else "(absent)"


def main() -> None:
    ap = argparse.ArgumentParser(description="Check a deployed site over HTTP.")
    ap.add_argument("origin", help="e.g. https://tech4time.bd")
    ap.add_argument("--no-retry", action="store_true",
                    help="fail on the first look; do not give the server time to settle")
    args = ap.parse_args()

    origin = args.origin.rstrip("/")
    print(f"{origin}\n{len(EXPECT)} paths, {len(HEADERS)} headers\n")

    # (kind, key, run-it) — one list so the retry does not have to know which
    # sort of check it is looking at again.
    checks = ([("path", (p, a, why), (lambda p=p, a=a: check_path(origin, p, a)))
               for p, a, why in EXPECT]
              + [("header", (p, h, n), (lambda p=p, h=h, n=n: check_header(origin, p, h, n)))
                 for p, h, n in HEADERS])

    results = {}
    for kind, key, run in checks:
        ok, detail = run()
        results[key] = (ok, detail)
        label = key[0] if kind == "path" else f"{key[1]}: {detail}"
        print(f"  {'ok  ' if ok else 'FAIL'}  {detail + '  ' + key[0] if kind == 'path' else label}")

    failed = [(kind, key, run) for kind, key, run in checks if not results[key][0]]

    # A server that has just been rsynced over may still be reloading. Look
    # again before believing it — see the note at the top of this file.
    settled = []
    if failed and not args.no_retry:
        print(f"\n  {len(failed)} did not pass. The server may still be reloading after the")
        print(f"  deploy; looking again in {RETRY_AFTER} seconds before calling it a failure.\n")
        time.sleep(RETRY_AFTER)

        still = []
        for kind, key, run in failed:
            ok, detail = run()
            results[key] = (ok, detail)
            if ok:
                settled.append(key[0])
                print(f"  ok    {detail}  {key[0]}   (only on the second look)")
            else:
                still.append((kind, key))
                print(f"  FAIL  {key[0]}  {detail}")
        failed = still

    passed = sum(1 for ok, _ in results.values() if ok)
    print(f"\n{passed}/{len(results)} checks passed")

    if settled:
        print(f"\n{len(settled)} needed a second look, {RETRY_AFTER}s apart: "
              + ", ".join(settled))
        print("The deploy is fine. If this becomes every run rather than an "
              "occasional one,\nthe server is taking longer to settle than it "
              "used to and that is worth knowing.")

    if failed:
        print("\nfailed:")
        for kind, key in failed:
            print(f"  - {key[0]}" + ("" if kind == "path" else f" {key[1]}"))
        # Before blaming the deploy: if a URL that should not exist at all came
        # back 200, and the headers we always set are missing, then whatever
        # answered was not this application. A missing .htaccess cannot do that
        # -- a nonsense path would still 404. A host security layer challenging
        # the caller can, and it returns the same 200 page for every request
        # with none of our headers on it.
        #
        # This happened to the backend's deploy on 2026-08-27, and the message
        # below would have sent somebody to check a file that had arrived
        # perfectly well.
        decoys = [key for kind, key in failed
                  if kind == "path" and "no-such-page" in key[0]]
        headers_gone = sum(1 for kind, key in failed if kind == "header")
        if decoys and headers_gone:
            print("\nREAD THIS FIRST: a path that should not exist answered anyway,\n"
                  "and the headers this site always sets are absent. That is not a\n"
                  "missing .htaccess -- without one, a nonsense URL would still 404.\n"
                  "Something other than the application replied: a host security\n"
                  "layer or WAF challenging this caller, a parking page, or a proxy.\n"
                  "\n"
                  "Check by hand from somewhere else before changing anything:\n"
                  f"    python3 tools/verify_live.py {origin}\n"
                  "\n"
                  "If it passes from your machine and fails only from CI, the deploy\n"
                  "is fine and the host is filtering the runner.")
            sys.exit(1)

        print("\nA 403 that became a 200 is the dangerous direction: the site\n"
              "looks completely normal and lib/, content/ and the private store\n"
              "have stopped being protected. Check that .htaccess arrived.")
        sys.exit(1)

    print("\nEverything that should answer answers, and everything that should not does not.")


if __name__ == "__main__":
    main()
