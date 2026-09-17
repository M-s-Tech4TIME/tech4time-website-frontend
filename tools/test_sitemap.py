#!/usr/bin/env python3
"""
Prove the three files that are addresses rather than pages.

Development tool. NOT deployed to the web server (see tools/README.md).
Run from the repo root:  python3 tools/test_sitemap.py
Requires the PHP CLI:    sudo apt install php-cli

WHY THIS EXISTS
/sitemap.xml, /robots.txt and /site.webmanifest stopped being static files when
their contents came under the editor. Each is now a .php renderer reached by an
internal rewrite from an address that MUST NOT CHANGE: /sitemap.xml is what
robots.txt names and what was submitted to Search Console, /robots.txt is the
only place a crawler looks, and /site.webmanifest is named by a <link> in every
page a returning visitor has already cached.

None of them is a page, so tools/audit_pages.py does not audit them, and none
is a document, so tools/test_publish.py does not round-trip them. Without this
they are three files nothing checks — and each fails in a way nobody on this
end would see: a sitemap that is not well-formed is rejected silently, a
robots.txt served as HTML is ignored by some crawlers, and a manifest that does
not parse turns an installed copy of the site back into a browser tab.

WHAT IT PROVES
  - all three answer at their own addresses, with the right Content-Type;
  - the sitemap is well-formed XML and lists exactly the indexable routes;
  - a page set to noindex leaves it, and a hidden service leaves it;
  - robots.txt always allows the whole site and always names the sitemap,
    whatever is in the document;
  - the manifest is valid JSON with the icons the site actually ships.

Every test runs against a COPY of content/seo.json and content/branding.json,
restored afterwards whether the run passes or fails.
"""

import json
import os
import shutil
import signal
import socket
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.request
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SEO = ROOT / "content" / "seo.json"
BRANDING = ROOT / "content" / "branding.json"
SERVICES = ROOT / "content" / "services.json"
MILESTONES = ROOT / "content" / "milestones.json"

SITEMAP_NS = "{http://www.sitemaps.org/schemas/sitemap/0.9}"


class Results:
    def __init__(self):
        self.passed = 0
        self.failed = []

    def check(self, case, ok, detail=""):
        if ok:
            self.passed += 1
            print(f"  ok    {case}")
        else:
            self.failed.append(case)
            print(f"  FAIL  {case}" + (f"\n          {detail}" if detail else ""))


def free_port() -> int:
    with socket.socket() as s:
        s.bind(("127.0.0.1", 0))
        return int(s.getsockname()[1])


def fetch(base: str, path: str) -> tuple[int, dict, str]:
    try:
        with urllib.request.urlopen(base + path, timeout=20) as r:
            return r.status, dict(r.headers), r.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, dict(e.headers), e.read().decode("utf-8", "replace")


def locs(xml: str) -> list[str]:
    root = ET.fromstring(xml)
    return [u.findtext(SITEMAP_NS + "loc") for u in root.findall(SITEMAP_NS + "url")]


def edit(path: Path, change) -> None:
    data = json.loads(path.read_text())
    change(data)
    path.write_text(json.dumps(data, indent=2, ensure_ascii=False) + "\n")


# ------------------------------------------------------------------- tests

def run(base: str, r: Results) -> None:
    print("the three addresses answer, and say what they are")

    status, headers, sitemap = fetch(base, "/sitemap.xml")
    r.check("/sitemap.xml answers", status == 200, f"status {status}")
    r.check("as XML, which a crawler may refuse it as anything else",
            "xml" in headers.get("Content-Type", ""), headers.get("Content-Type", ""))

    status, headers, robots = fetch(base, "/robots.txt")
    r.check("/robots.txt answers", status == 200, f"status {status}")
    r.check("as plain text", "text/plain" in headers.get("Content-Type", ""),
            headers.get("Content-Type", ""))

    status, headers, manifest = fetch(base, "/site.webmanifest")
    r.check("/site.webmanifest answers", status == 200, f"status {status}")
    r.check("as a manifest", "manifest+json" in headers.get("Content-Type", ""),
            headers.get("Content-Type", ""))

    print("\nthe sitemap is a sitemap")

    try:
        urls = locs(sitemap)
        parsed = True
    except ET.ParseError as e:
        urls, parsed = [], False
        r.check("it is well-formed XML", False, str(e))
    if parsed:
        r.check("it is well-formed XML", True)

    r.check("it keeps the stylesheet that renders it readably in a browser",
            "assets/xsl/sitemap.xsl" in sitemap)
    r.check("it names no editor address, because it is world-readable",
            "admin" not in sitemap.lower(),
            "the sitemap should not advertise the editor's path")

    routes = json.loads(subprocess.run(
        ["php", "-r", "require 'lib/contract.php'; echo json_encode(SEO_ROUTES);"],
        cwd=ROOT, capture_output=True, text=True).stdout)

    for key, (route, name, _doc) in routes.items():
        if route == "":
            r.check(f"{name} is absent, having no address of its own",
                    not any(u.endswith("/404.php") or u.endswith("/404") for u in urls))
            continue
        r.check(f"{name} is listed", f"https://tech4time.bd{route}" in urls, route)

    services = json.loads(SERVICES.read_text())["services"]["items"]
    for service in services:
        want = f"https://tech4time.bd/pages/services/{service['slug']}/"
        r.check(f"and the {service['name']} service page", want in urls, want)

    r.check("and nothing else at all",
            len(urls) == len([r_ for r_, *_ in routes.values() if r_]) + len(services),
            f"{len(urls)} urls: {urls}")

    print("\nrobots.txt cannot be made to block the site")

    r.check("it allows the whole site", "Allow: /" in robots)
    r.check("it names the sitemap at its real address",
            "Sitemap: https://tech4time.bd/sitemap.xml" in robots)
    r.check("it says nothing about the editor",
            not any("admin" in line.lower() and ":" in line and not line.startswith("#")
                    for line in robots.splitlines()))
    r.check("and it carries the rule the site ships with",
            "Disallow: /contact-handler.php" in robots)

    print("\nthe manifest is a manifest")

    try:
        app = json.loads(manifest)
        r.check("it is valid JSON", True)
    except json.JSONDecodeError as e:
        app = {}
        r.check("it is valid JSON", False, str(e))

    for field in ("name", "short_name", "start_url", "display", "icons"):
        r.check(f"it has {field}", field in app)
    r.check("its icons are files that exist",
            all((ROOT / icon["src"].lstrip("/")).is_file() for icon in app.get("icons", [])),
            str([i["src"] for i in app.get("icons", [])]))

    print("\na page set to Not indexed leaves the sitemap")

    edit(BRANDING, lambda d: d["meta"].__setitem__("robots", "noindex"))
    _s, _h, after = fetch(base, "/sitemap.xml")
    r.check("the branding page is gone",
            "https://tech4time.bd/pages/branding-and-advertisement/" not in locs(after))
    r.check("and every other page is still there", len(locs(after)) == len(urls) - 1,
            f"{len(locs(after))} vs {len(urls) - 1}")

    edit(BRANDING, lambda d: d["meta"].__setitem__("robots", "index"))
    _s, _h, back = fetch(base, "/sitemap.xml")
    r.check("and it comes back when it is indexed again", len(locs(back)) == len(urls))

    print("\nand so does a hidden service")

    edit(SERVICES, lambda d: d["services"]["items"][0].__setitem__("status", "hidden"))
    _s, _h, after = fetch(base, "/sitemap.xml")
    r.check("a hidden service is absent",
            f"https://tech4time.bd/pages/services/{services[0]['slug']}/" not in locs(after))

    print("\nwhat it claims about dates")

    edit(SERVICES, lambda d: d["services"]["items"][0].__setitem__("status", "shown"))
    edit(SEO, lambda d: None)
    _s, _h, current = fetch(base, "/sitemap.xml")
    root = ET.fromstring(current)
    dated = [u for u in root.findall(SITEMAP_NS + "url")
             if u.find(SITEMAP_NS + "lastmod") is not None]
    published = [u for u in root.findall(SITEMAP_NS + "url")
                 if u.findtext(SITEMAP_NS + "loc", "").endswith("/pages/careers/")]
    r.check("a page that has never been published claims no date",
            len(dated) < len(root.findall(SITEMAP_NS + "url")),
            "every page reported a lastmod, which means one was invented")
    r.check("and one that has, reports the day it was",
            published and published[0].find(SITEMAP_NS + "lastmod") is not None,
            "the careers document carries an updated stamp and should report it")

    # WHY THIS PAGE IN PARTICULAR. /pages/milestones/ was given a document of
    # its own rather than a metadata-only stub precisely so that this date is
    # honest. A stub would have shared the company profile's file, and its
    # lastmod would then have moved when somebody edited the page's SEO fields
    # and never when a milestone was added -- a date telling a crawler the
    # truth about the wrong thing. The whole shape of that decision rests on
    # this assertion, so it is made rather than described.
    print("\nand a milestone moving the milestones page's date")

    def entry(xml: str, route: str, field: str):
        """One field of ONE url, found by its loc.

        By the loc and not by searching the whole document: nine pages carry a
        changefreq, so `"yearly" in xml` is satisfied by the privacy policy and
        says nothing at all about this page.
        """
        for url in ET.fromstring(xml).findall(SITEMAP_NS + "url"):
            if (url.findtext(SITEMAP_NS + "loc") or "").endswith(route):
                return url.findtext(SITEMAP_NS + field)
        return None

    def lastmod(xml: str, route: str):
        return entry(xml, route, "lastmod")

    _s, _h, page = fetch(base, "/sitemap.xml")
    r.check("the milestones page is listed",
            "https://tech4time.bd/pages/milestones/" in locs(page))
    r.check("with the changefreq its own document declares",
            entry(page, "/pages/milestones/", "changefreq") == "yearly",
            str(entry(page, "/pages/milestones/", "changefreq")))

    # A DOCUMENT WITH NO FILE IS AN EMPTY DOCUMENT, NOT AN ABSENT ONE, and
    # seo_route_meta() used to treat it as the second: it returned [] and the
    # sitemap fell through to its own 'monthly' and '0.5' -- while the page
    # rendered the right title all along, out of its own *_load(). Two answers
    # to what a page is called, disagreeing for exactly as long as a new
    # document went unpublished, which is the state every new document starts
    # in. Moved aside rather than emptied, because an empty FILE is a different
    # case and already normalises correctly.
    MILESTONES.rename(MILESTONES.with_suffix(".json.moved"))
    try:
        _s, _h, none = fetch(base, "/sitemap.xml")
        r.check("and it still declares it with no file on disk at all",
                entry(none, "/pages/milestones/", "changefreq") == "yearly",
                str(entry(none, "/pages/milestones/", "changefreq")))
        r.check("and claims no date, because nothing has been published",
                lastmod(none, "/pages/milestones/") is None,
                str(lastmod(none, "/pages/milestones/")))
    finally:
        MILESTONES.with_suffix(".json.moved").rename(MILESTONES)

    before = lastmod(page, "/pages/milestones/")
    edit(MILESTONES, lambda d: (d.__setitem__("updated", "2031-07-04T09:00:00+00:00"),
                                d.__setitem__("revision", 1),
                                d["timeline"]["items"].append(
                                    {"id": "x", "year": "2031", "title": "A thing",
                                     "text": "", "status": "shown"})))
    _s, _h, page = fetch(base, "/sitemap.xml")
    after = lastmod(page, "/pages/milestones/")
    r.check("adding one moves its lastmod",
            after == "2031-07-04" and after != before,
            f"{before!r} -> {after!r}")
    r.check("and moves nobody else's",
            lastmod(page, "/pages/company-profile/")
            != "2031-07-04",
            "the timeline is its own document; editing it must not restamp "
            "the company profile")


def main() -> None:
    if not shutil.which("php"):
        print("php not found — skipping. sudo apt install php-cli")
        return

    port = free_port()
    base = f"http://127.0.0.1:{port}"
    # None for a file that is not there, so "restore" can mean delete it again.
    backup = {p: (p.read_bytes() if p.is_file() else None)
              for p in (SEO, BRANDING, SERVICES, MILESTONES)}

    with tempfile.TemporaryDirectory() as tmp:
        private = Path(tmp) / "t4t-private"
        private.mkdir(mode=0o700)
        (private / "publish.key").write_text("9f" * 32 + "\n")

        server = subprocess.Popen(
            ["php", "-S", f"127.0.0.1:{port}", "-t", str(ROOT),
             str(ROOT / "tools" / "dev-router.php")],
            cwd=str(ROOT), stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
            env=dict(os.environ, T4T_PRIVATE=str(private)), preexec_fn=os.setsid,
        )

        r = Results()
        try:
            for _ in range(80):
                try:
                    urllib.request.urlopen(base + "/robots.txt", timeout=1).read()
                    break
                except Exception:
                    time.sleep(0.15)
            run(base, r)
        finally:
            try:
                os.killpg(os.getpgid(server.pid), signal.SIGTERM)
                server.wait(timeout=5)
            except Exception:
                pass
            for path, bytes_ in backup.items():
                if bytes_ is None:
                    path.unlink(missing_ok=True)
                else:
                    path.write_bytes(bytes_)
            print("\ncontent/ restored")

    total = r.passed + len(r.failed)
    if r.failed:
        print(f"\n{len(r.failed)} of {total} checks FAILED:")
        for case in r.failed:
            print(f"  - {case}")
        sys.exit(1)
    print(f"\n{r.passed}/{total} checks passed")


if __name__ == "__main__":
    main()
