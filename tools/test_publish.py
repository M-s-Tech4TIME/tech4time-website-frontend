#!/usr/bin/env python3
"""
Prove the backend can put content on the public site, and that nothing else can.

Development tool. NOT deployed to the web server (see tools/README.md).
Run from the repo root:  python3 tools/test_publish.py
Requires the PHP CLI:    sudo apt install php-cli

WHY THIS EXISTS
api/publish.php is the ONLY route by which content reaches this site, and the
only endpoint on it that writes anything at all. Everything the two
repositories do separately meets here.

So this drives the real endpoint over real HTTP with real signatures, and then
tries every way of getting past it that does not involve holding the key:

The signing below is written in PYTHON, deliberately. A test that asked
lib/publish.php to sign what api/publish.php then verifies would prove the two
agree with each other and nothing about whether either is right. This is a
second implementation of the format from its written description, so the
backend's PHP and this must both match the same third thing.

tech4time-website-backend has the mirror of this: its client posts to a stub endpoint
written in Python that verifies the signature. Neither side is ever checked
against its own counterpart.

    no signature            a stranger who found the URL
    a signature from
      another key           the two stores have parted
    a tampered body         the payload changed in flight
    an old timestamp        a request captured and kept
    a replayed request      a request captured and sent again inside the window
    a lower revision        a stale retry arriving after a newer save
    a different contract    the two repositories are out of step
    a script tag            the backend is compromised and sending markup

The last two are the ones a signature does not answer, which is why they are
checked separately: a compromised backend signs perfectly well.

Every test runs against a COPY of the real data files, which are restored
afterwards whether the run passes or fails.
"""

import copy
import hashlib
import hmac
import json
import os
import re
import shutil
import signal
import socket
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
ENDPOINT = "/api/publish.php"

CAREERS = ROOT / "content" / "careers.json"
CONTACT = ROOT / "content" / "contact.json"
COMPANY = ROOT / "content" / "company.json"
MILESTONES = ROOT / "content" / "milestones.json"
ABOUT = ROOT / "content" / "about.json"
HOME = ROOT / "content" / "home.json"
SERVICES = ROOT / "content" / "services.json"
CERTIFICATIONS = ROOT / "content" / "certifications.json"
BRANDING = ROOT / "content" / "branding.json"
PRIVACY = ROOT / "content" / "privacy.json"
SEO = ROOT / "content" / "seo.json"
CHROME = ROOT / "content" / "chrome.json"
SETTINGS = ROOT / "content" / "settings.json"

# Not a document: the stylesheet the two wall caps have to agree with. See
# css_columns().
COMPANY_CSS = ROOT / "assets" / "css" / "pages" / "company-profile.css"

MARK = "PUBLISHMARK"


# ------------------------------------------------------------------ results


class Results:
    def __init__(self) -> None:
        self.passed = 0
        self.failed: list[str] = []

    def check(self, case: str, ok: bool, detail: str = "") -> bool:
        if ok:
            self.passed += 1
            print(f"  ok    {case}")
        else:
            self.failed.append(case)
            print(f"  FAIL  {case}" + (f"\n          {detail}" if detail else ""))
        return ok

    def report(self) -> int:
        total = self.passed + len(self.failed)
        print(f"\n{self.passed}/{total} checks passed")
        if self.failed:
            print("\nfailed:")
            for case in self.failed:
                print(f"  - {case}")
        return 1 if self.failed else 0


# ------------------------------------------------------------------- wiring


def documents() -> list[Path]:
    """content/<name>.json for every document there is, asked of the contract.

    This test writes into the real content/ and puts it back afterwards, so the
    list it restores has to be the list that exists. A second copy kept here
    would go out of step in the way that does not announce itself — see the
    note where the restore happens.
    """
    out = subprocess.run(
        ["php", "-r", "require 'lib/contract.php'; echo implode(' ', CONTRACT_DOCUMENTS);"],
        cwd=ROOT, capture_output=True, text=True,
    )
    if out.returncode != 0 or not out.stdout.strip():
        raise SystemExit("could not read CONTRACT_DOCUMENTS from lib/contract.php:\n"
                         + (out.stderr or out.stdout)[:400])

    return [ROOT / "content" / f"{name}.json" for name in out.stdout.split()]


def free_port() -> int:
    with socket.socket() as s:
        s.bind(("127.0.0.1", 0))
        return int(s.getsockname()[1])


def fingerprint(key: bytes) -> str:
    return hmac.new(key, b"publish-key-fingerprint", hashlib.sha256).hexdigest()[:16]


def sign(key: bytes, body: bytes, timestamp: int) -> str:
    mac = hmac.new(key, f"{timestamp}.".encode() + body, hashlib.sha256).hexdigest()
    return f"{fingerprint(key)}:{mac}"


def envelope(document: str, data: dict, version: int = 2) -> dict:
    return {
        "contract_version": version,
        "document": document,
        "revision": int(data.get("revision", 0)),
        "published": "2026-08-26T00:00:00+00:00",
        "data": data,
    }


def jsonld(page: str) -> list[dict]:
    """Every JSON-LD block on a page, parsed.

    A block that will not parse is returned as {} rather than raised, so the
    check that wanted it fails by name instead of the whole suite dying on a
    stray comma somewhere else on the page.
    """
    out: list[dict] = []
    for raw in re.findall(
            r'<script type="application/ld\+json">(.*?)</script>', page, re.S):
        try:
            out.append(json.loads(raw))
        except ValueError:
            out.append({})
    return out


def breadcrumb_names(page: str) -> list[str]:
    """The trail, in order, as a crawler reads it.

    READ OUT OF THE BLOCK, not matched against the page's text. The check this
    serves used to ask whether the hero title appeared in ANY "name" field
    anywhere in the markup, which was a true proxy only while the breadcrumb
    was the sole thing on the page with a name. It is not any more: a
    CollectionPage names itself by its hero title, deliberately and in company
    with milestones_page_schema() and company_page_schema(), so that proxy
    began reporting a fault where there is none while still being unable to
    see a trail whose LAST item was wrong but whose words happened to appear.
    Parsing it asserts the thing the check is named after.
    """
    for block in jsonld(page):
        if block.get("@type") == "BreadcrumbList":
            return [str(i.get("name", ""))
                    for i in block.get("itemListElement", [])]
    return []


def post(base: str, body: bytes, headers: dict) -> tuple[int, dict]:
    req = urllib.request.Request(base + ENDPOINT, data=body, method="POST")
    req.add_header("Content-Type", "application/json")
    for name, value in headers.items():
        req.add_header(name, value)
    try:
        with urllib.request.urlopen(req, timeout=15) as r:
            return r.status, json.loads(r.read().decode())
    except urllib.error.HTTPError as e:
        raw = e.read().decode("utf-8", "replace")
        try:
            return e.code, json.loads(raw)
        except ValueError:
            return e.code, {"raw": raw[:300]}


def publish(base: str, key: bytes, document: str, data: dict,
            version: int = 2, at: int | None = None,
            tamper: bytes | None = None) -> tuple[int, dict]:
    body = json.dumps(envelope(document, data, version),
                      separators=(",", ":"), ensure_ascii=False).encode()
    stamp = int(time.time()) if at is None else at
    header = sign(key, body, stamp)
    return post(base, tamper if tamper is not None else body,
                {"X-T4T-Timestamp": str(stamp), "X-T4T-Signature": header})


class NoRedirect(urllib.request.HTTPRedirectHandler):
    """An opener that reports a redirect instead of following it.

    urllib follows 301s by default, which would turn "this address redirects
    to the clean one" into "this address answers 200" and prove nothing.
    """

    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def get_unfollowed(base: str, path: str) -> tuple[int, str]:
    """The status and Location of a request, with the redirect left alone."""
    opener = urllib.request.build_opener(NoRedirect)
    try:
        with opener.open(base + path, timeout=15) as response:
            return response.status, response.headers.get("Location", "")
    except urllib.error.HTTPError as e:
        return e.code, e.headers.get("Location", "")


def get(base: str, path: str) -> tuple[int, str]:
    try:
        with urllib.request.urlopen(base + path, timeout=15) as r:
            return r.status, r.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace")


# -------------------------------------------------------------------- cases


def job(revision: int, about: str = f"<p>{MARK}-about</p>") -> dict:
    return {
        "cv_form_url": "https://example.com/cv",
        "updated": "2026-08-26T00:00:00+00:00",
        "revision": revision,
        "jobs": [{
            "id": f"{MARK.lower()}-role",
            "title": f"{MARK} Engineer",
            "employment_type": "Full-Time",
            "work_arrangement": "On-site",
            "location": "Dhaka",
            "salary": "",
            "posted": "2026-08-01",
            "closes": "",
            "status": "open",
            "apply_url": "https://example.com/apply",
            "about": about,
            "responsibilities": "",
            "requirements": "",
            "must_have": "",
            "nice_to_have": "",
            "certifications": "",
            "offers": "",
        }],
    }


def run(base: str, key: bytes, r: Results) -> None:
    other = bytes.fromhex("11" * 32)

    print("\nthe happy path")

    status, answer = publish(base, key, "careers", job(1))
    r.check("a signed document is accepted", status == 200 and answer.get("ok") is True,
            f"{status} {answer}")
    r.check("and the answer says which revision now stands",
            answer.get("revision") == 1, str(answer))
    r.check("and names the document it landed",
            answer.get("document") == "careers", str(answer))

    stored = json.loads(CAREERS.read_text())
    r.check("the replica on disk is what was sent",
            stored["jobs"][0]["title"] == f"{MARK} Engineer", str(stored)[:200])
    r.check("and carries the revision it was sent with",
            stored.get("revision") == 1, str(stored.get("revision")))

    status, page = get(base, "/pages/careers/")
    r.check("and a visitor sees it", f"{MARK} Engineer" in page,
            f"status {status}")

    print("\nnothing else gets in")

    body = json.dumps(envelope("careers", job(2)), separators=(",", ":")).encode()
    status, answer = post(base, body, {})
    r.check("an unsigned request is refused",
            status == 401 and answer.get("code") == "no-signature", f"{status} {answer}")
    r.check("and is told nothing about what is here",
            "revision" not in answer, str(answer))

    status, answer = publish(base, other, "careers", job(2))
    r.check("a signature from another key is refused",
            status == 401 and answer.get("code") == "unknown-key", f"{status} {answer}")
    r.check("and says the KEY is wrong, not the signature",
            "publish key" in answer.get("error", ""), str(answer))

    good = json.dumps(envelope("careers", job(2)), separators=(",", ":")).encode()
    status, answer = publish(base, key, "careers", job(2),
                             tamper=good.replace(b"Engineer", b"Engineer2"))
    r.check("a body changed after signing is refused",
            status == 401 and answer.get("code") == "bad-signature", f"{status} {answer}")

    status, answer = publish(base, key, "careers", job(2), at=int(time.time()) - 3600)
    r.check("an hour-old request is refused",
            status == 401 and answer.get("code") == "stale-timestamp", f"{status} {answer}")

    status, answer = publish(base, key, "careers", job(2), at=int(time.time()) + 3600)
    r.check("and so is one from an hour in the future",
            status == 401 and answer.get("code") == "stale-timestamp", f"{status} {answer}")

    print("\nreplays and rollbacks")

    # Byte-identical to the one that succeeded, signed now so the window cannot
    # be what stops it. Only the revision can.
    status, answer = publish(base, key, "careers", job(1))
    r.check("a replay of an accepted request changes nothing",
            status == 409 and answer.get("code") == "not-newer", f"{status} {answer}")
    r.check("and the answer says what revision stands",
            answer.get("revision") == 1, str(answer))

    status, answer = publish(base, key, "careers", job(5, f"<p>{MARK}-five</p>"))
    r.check("a newer revision is accepted", status == 200, f"{status} {answer}")

    status, answer = publish(base, key, "careers", job(4, "<p>rolled back</p>"))
    r.check("an older revision arriving late is refused",
            status == 409 and answer.get("code") == "not-newer", f"{status} {answer}")

    stored = json.loads(CAREERS.read_text())
    r.check("and the site still holds the newer one",
            stored["revision"] == 5 and f"{MARK}-five" in stored["jobs"][0]["about"],
            str(stored.get("revision")))

    print("\nthe shape has to match")

    status, answer = publish(base, key, "careers", job(6), version=3)
    r.check("a document in a shape this side does not implement is refused",
            status == 422 and answer.get("code") == "contract-mismatch",
            f"{status} {answer}")
    r.check("and says the two halves are out of step",
            "out of step" in answer.get("error", ""), str(answer))

    status, answer = publish(base, key, "pricing", job(6))
    r.check("a document nobody publishes is refused",
            status == 400 and answer.get("code") == "unknown-document",
            f"{status} {answer}")

    mismatched = job(7)
    body = json.dumps(
        {"contract_version": 2, "document": "careers", "revision": 99,
         "published": "2026-08-26T00:00:00+00:00", "data": mismatched},
        separators=(",", ":")).encode()
    stamp = int(time.time())
    status, answer = post(base, body, {"X-T4T-Timestamp": str(stamp),
                                       "X-T4T-Signature": sign(key, body, stamp)})
    r.check("an envelope disagreeing with its own document is refused",
            status == 400 and answer.get("code") == "revision-mismatch",
            f"{status} {answer}")

    print("\na signature is not a promise that the content is safe")

    status, answer = publish(base, key, "careers",
                             job(8, '<p onclick="steal()">hi</p><script>steal()</script>'))
    r.check("markup from a signed sender is still accepted", status == 200,
            f"{status} {answer}")

    stored = json.loads(CAREERS.read_text())
    about = stored["jobs"][0]["about"]
    r.check("but the script is gone by the time it is written",
            "<script" not in about and "onclick" not in about, about)
    r.check("and the words survive", "hi" in about, about)

    _, page = get(base, "/pages/careers/")
    r.check("so the visitor is served no script either",
            "steal()" not in page)

    print("\nthe method and the size")

    status, page = get(base, ENDPOINT)
    r.check("GET is refused", status == 405, str(status))

    huge = job(9)
    huge["jobs"][0]["about"] = "<p>" + ("x" * 1_200_000) + "</p>"
    status, answer = publish(base, key, "careers", huge)
    r.check("a payload past the cap is refused",
            status == 413 and answer.get("code") == "too-large", f"{status} {answer}")

    print("\nthe contact document travels the same road")

    contact = json.loads(CONTACT.read_text())
    contact["revision"] = 3
    contact["hero"]["title"] = f"{MARK} Contact"
    status, answer = publish(base, key, "contact", contact)
    r.check("the contact page publishes", status == 200 and answer.get("ok") is True,
            f"{status} {answer}")

    _, page = get(base, "/pages/contact/")
    r.check("and a visitor sees the change", f"{MARK} Contact" in page)

    contact_switches(base, key, r)
    company_round_trip(base, key, r)
    milestones_round_trip(base, key, r)
    the_walls(base, key, r)
    about_round_trip(base, key, r)
    home_round_trip(base, key, r)
    services_round_trip(base, key, r)
    services_seventh(base, key, r)
    certifications_round_trip(base, key, r)
    branding_round_trip(base, key, r)
    privacy_round_trip(base, key, r)
    seo_round_trip(base, key, r)
    chrome_round_trip(base, key, r)
    settings_round_trip(base, key, r)


def php_const(name: str) -> int:
    """One integer constant, asked of PHP rather than written here twice.

    The caps below are a design, not a number: COMPANY_CLIENTS_WALL has to
    divide into every column count .clients uses and COMPANY_TECHNOLOGY_WALL
    into every count .tech-sphere__list uses, or the row above the expander
    comes out ragged at whichever width it does not divide into. A copy of the
    figure here would let the two part without anything noticing.
    """
    out = subprocess.run(
        ["php", "-r", f"require 'lib/company.php'; echo {name};"],
        cwd=ROOT, capture_output=True, text=True,
    )
    if out.returncode != 0 or not out.stdout.strip():
        raise SystemExit(f"could not read {name} from lib/company.php:\n"
                         + (out.stderr or out.stdout)[:400])
    return int(out.stdout.strip())


def css_columns(selector: str) -> list[int]:
    """Every column count one grid uses, read out of the stylesheet.

    THE OTHER HALF OF THE SAME DESIGN, AND IT USED TO BE A COPY. php_const()
    above goes to the trouble of asking PHP for the caps, and then the counts
    they have to divide into were written here as (2, 4, 6) and (3, 6, 9) --
    so a stylesheet changed to repeat(5, 1fr) kept passing against numbers the
    grid no longer used. An invariant checked against a transcript of the thing
    it is checking is not checking anything, and this one fails silently: the
    only symptom is a ragged half-row above the expander at exactly one width.
    CLAUDE.md said this check read the CSS before it did.

    Raises rather than returning [] when nothing matches. Every integer divides
    into an empty set, so an empty list here is a check that can only pass.
    """
    css = COMPANY_CSS.read_text()
    rule = re.compile(r"^[ \t]*" + re.escape(selector) + r"[ \t]*\{([^}]*)\}",
                      re.M)
    counts: list[int] = []
    for block in rule.finditer(css):
        counts += [int(m.group(1)) for m in re.finditer(
            r"grid-template-columns:\s*repeat\(\s*(\d+)", block.group(1))]

    if not counts:
        raise SystemExit(
            f"no `grid-template-columns: repeat(N, …)` found for {selector} in "
            f"{COMPANY_CSS.relative_to(ROOT)}. The cap-divides-columns check "
            f"has nothing to check against, which is worse than a failure — "
            f"either the selector was renamed or the grid was rewritten, and "
            f"this check has to be taught the new shape.")
    return sorted(set(counts))


def milestones_round_trip(base: str, key: bytes, r: Results) -> None:
    """Every field the milestones model declares, on both pages that draw it.

    THIS IS WHAT check_content_model.py POINTS AT for this document, for the
    reason it points at the company profile: the form and both renderers walk
    MILESTONES_LISTS with a loop, so reading the source finds the loop and not
    the fields.

    TWO PAGES, AND THEY DO NOT SHOW THE SAME THING. /pages/milestones/ shows
    the whole timeline; /pages/company-profile/ shows the most recent
    MILESTONES_WINDOW years of it and links here for the rest. So the marker
    walk runs against the milestones page, and the window is proved separately
    against the company profile — where the point is as much what is ABSENT as
    what is there.
    """
    print("\nthe milestones travel to two pages")

    window = php_const("MILESTONES_WINDOW")

    # Eight years, one entry each, oldest first — the order the timeline runs
    # in. The three oldest must fall outside a five-year window.
    years = [str(2018 + i) for i in range(8)]
    data = {
        "revision": 3,
        "meta": {"title": f"{MARK}-ms-tab", "description": f"{MARK}-ms-desc",
                 "share_title": f"{MARK}-ms-share", "breadcrumb": f"{MARK}-ms-crumb",
                 "keywords": f"{MARK}-ms-kw", "robots": "index",
                 "changefreq": "yearly", "priority": "0.5"},
        "hero": {"title": f"{MARK}-ms-hero", "subtitle": f"{MARK}-ms-sub"},
        "timeline": {
            "status": "shown",
            "eyebrow": f"{MARK}-ms-eyebrow",
            "title": f"{MARK}-ms-title",
            "lead": f"<p>{MARK}-ms-lead</p>",
            "items": [{"id": f"y{y}", "year": y, "title": f"{MARK}-ms-{y}",
                       "text": f"{MARK}-ms-text-{y}", "status": "shown"}
                      for y in years],
        },
    }

    status, answer = publish(base, key, "milestones", data)
    r.check("the milestones publish", status == 200 and answer.get("ok") is True,
            f"{status} {answer}")

    _, page = get(base, "/pages/milestones/")

    missing = [k for k in ("ms-tab", "ms-desc", "ms-share", "ms-crumb", "ms-kw",
                           "ms-hero", "ms-sub", "ms-eyebrow", "ms-title",
                           "ms-lead", "ms-2018", "ms-text-2018", "ms-2025")
               if f"{MARK}-{k}" not in page]
    r.check("every field the model declares reaches the page",
            not missing, "never rendered: " + ", ".join(missing))

    r.check("the whole history is on the milestones page",
            page.count('class="timeline__item"') == len(years),
            str(page.count('class="timeline__item"')))
    r.check("its graph lists every entry as an Event",
            len([g for g in json_ld(page) if g.get("@type") == "CollectionPage"]) == 1
            and len(main_entity_items(page)) == len(years),
            str(len(main_entity_items(page))))

    print("\nand the company profile shows a window onto them")
    _, page = get(base, "/pages/company-profile/")

    kept = years[-window:]
    dropped = years[:-window]
    r.check(f"the {window} most recent years are there",
            all(f"{MARK}-ms-{y}" in page for y in kept), str(kept))
    r.check("and the older ones are not",
            not any(f"{MARK}-ms-{y}" in page for y in dropped), str(dropped))
    r.check("the band's heading comes from the same document",
            f"{MARK}-ms-title" in page and f"{MARK}-ms-lead" in page,
            "the company document's own milestones band is deprecated — the "
            "words moved with the rows")
    r.check("a link out says how many there are in full",
            f"See all {len(years)} milestones" in page
            and 'href="/pages/milestones/"' in page)
    r.check("its graph describes the window and not the history",
            len(main_entity_items(page)) == window,
            "a graph listing entries the markup does not carry is a page "
            "saying two things about itself")

    print("\nand the link is only there when something is withheld")
    data["revision"] = 4
    data["timeline"]["items"] = data["timeline"]["items"][-window:]
    publish(base, key, "milestones", data)
    _, page = get(base, "/pages/company-profile/")
    r.check("a timeline inside the window gets no link",
            "See all" not in page and f"{MARK}-ms-{kept[0]}" in page,
            "a see-all under a list that is already all of it is a control "
            "that does nothing")

    print("\nan unreadable year is kept rather than dropped")
    data["revision"] = 5
    data["timeline"]["items"] = (
        [{"id": "founding", "year": "the beginning", "title": f"{MARK}-ms-nodate",
          "text": "", "status": "shown"}]
        + [{"id": f"y{y}", "year": y, "title": f"{MARK}-ms-{y}", "text": "",
            "status": "shown"} for y in years]
    )
    publish(base, key, "milestones", data)
    _, page = get(base, "/pages/company-profile/")
    r.check("a row whose year cannot be read survives the window",
            f"{MARK}-ms-nodate" in page,
            "dropping a row nobody can sort is worse than showing one extra")
    r.check("and the years that can be read are still windowed",
            not any(f"{MARK}-ms-{y}" in page for y in dropped), str(dropped))

    print("\nhiding, on a document two pages read")
    data["revision"] = 6
    data["timeline"]["items"][1]["status"] = "hidden"
    publish(base, key, "milestones", data)
    _, page = get(base, "/pages/milestones/")
    r.check("a hidden entry is on neither page", f"{MARK}-ms-{years[0]}" not in page)

    data["revision"] = 7
    data["timeline"]["status"] = "hidden"
    publish(base, key, "milestones", data)
    _, page = get(base, "/pages/milestones/")
    r.check("a hidden band takes the timeline off its own page",
            f"{MARK}-ms-title" not in page)
    _, page = get(base, "/pages/company-profile/")
    r.check("and off the company profile with it",
            f"{MARK}-ms-title" not in page,
            "one band, one switch, both pages — which is what the editor says")

    print("\na signature is not a promise about what is inside")
    data["revision"] = 8
    data["timeline"]["status"] = "shown"
    data["timeline"]["lead"] = '<p onclick="steal()">hi</p><script>steal()</script>'
    status, _ = publish(base, key, "milestones", data)
    r.check("a validly signed payload is accepted", status == 200, str(status))
    _, page = get(base, "/pages/milestones/")
    r.check("but the script is gone", "steal()" not in page and "onclick" not in page)
    r.check("and the text around it survives", ">hi<" in page)


def the_walls(base: str, key: bytes, r: Results) -> None:
    """The two capped walls of logos on the company profile.

    WHAT IS BEING PROVED IS THAT NOTHING IS LOST. The cap bounds how tall the
    page IS, not what it says: every logo past it is inside a closed <details>,
    which is in the DOM, found by Ctrl+F and read by a crawler. A cap that
    dropped rows would pass a page-height measurement and fail the site.

    And that the cap divides the columns. A cap that is not a multiple of every
    column count the grid uses leaves a ragged half-row above the button at
    whichever width it does not divide into, and two grids one after the other
    stop reading as one wall. That is a property of the numbers, so it is
    asserted about the numbers.
    """
    print("\nthe walls of logos are capped, and nothing is lost")

    clients_cap = php_const("COMPANY_CLIENTS_WALL")
    tech_cap = php_const("COMPANY_TECHNOLOGY_WALL")

    # Both sides of the invariant are read from the thing that defines them:
    # the caps from PHP, the tiers from the stylesheet. Neither is written here.
    clients_cols = css_columns(".clients")
    tech_cols = css_columns(".tech-sphere__list")

    r.check("the clients cap divides every column count .clients uses",
            all(clients_cap % n == 0 for n in clients_cols),
            f"{clients_cap} against "
            + ", ".join(str(n) for n in clients_cols)
            + " — read from assets/css/pages/company-profile.css")
    r.check("and the technology cap divides its own",
            all(tech_cap % n == 0 for n in tech_cols),
            f"{tech_cap} against " + ", ".join(str(n) for n in tech_cols))

    data = json.loads(COMPANY.read_text())
    data["revision"] = 40
    data["clients"]["status"] = "shown"
    data["technology"]["status"] = "shown"
    data["clients"]["items"] = [
        {"id": f"c{i}", "name": f"{MARK}-wall-c{i}", "status": "shown",
         "image": {"src": "/assets/images/clients/cca.jpg",
                   "webp": "/assets/images/clients/cca.webp",
                   "width": 320, "height": 167}}
        for i in range(clients_cap + 5)
    ]
    data["technology"]["items"] = [
        {"id": f"t{i}", "name": f"{MARK}-wall-t{i}", "status": "shown",
         "image": {"src": "/assets/images/tech/metasploit.svg", "webp": "",
                   "width": 1000, "height": 222}}
        for i in range(tech_cap + 5)
    ]

    status, _ = publish(base, key, "company", data)
    r.check("a long wall publishes", status == 200, str(status))

    _, page = get(base, "/pages/company-profile/")

    r.check("every client is in the markup",
            all(f"{MARK}-wall-c{i}" in page for i in range(clients_cap + 5)),
            "the tail is hidden by a closed <details>, not left out of the page")
    r.check("every technology is too",
            all(f"{MARK}-wall-t{i}" in page for i in range(tech_cap + 5)))

    shut = page.split('class="wall-more', 1)
    r.check("the wall above the expander is exactly the cap",
            shut[0].count('class="client-card"') == clients_cap,
            str(shut[0].count('class="client-card"')))

    r.check("the expander says how many there are in full",
            f"See all {clients_cap + 5} clients" in page
            and f"See all {tech_cap + 5} technologies" in page)
    r.check("it is a <details>, so it opens with no JavaScript",
            page.count('<details class="wall-more') == 2,
            str(page.count('<details class="wall-more')))
    r.check("nothing in the tail is marked for reveal",
            '<li data-reveal data-reveal-delay class="client-card">'
            not in page.split('wall-more__rest"', 1)[1],
            "a closed <details> has no layout box, so IntersectionObserver "
            "never fires and a card marked in there would still be invisible "
            "when somebody opened it")

    print("\nand a short wall gets no expander at all")
    data["revision"] = 41
    data["clients"]["items"] = data["clients"]["items"][:clients_cap]
    data["technology"]["items"] = data["technology"]["items"][:tech_cap]
    publish(base, key, "company", data)
    _, page = get(base, "/pages/company-profile/")
    r.check("exactly the cap needs no button",
            "wall-more" not in page and f"{MARK}-wall-c0" in page,
            "an expander over nothing is a control that does nothing")


def contact_switches(base: str, key: bytes, r: Results) -> None:
    """What the contact page does with the things that can be switched off.

    Both bands and every row in them can be hidden from the admin, and hidden
    has to mean hidden: not merely undrawn, but absent from the JSON-LD as
    well. A band that disappears visually and goes on telling a search engine
    where the offices are has not been hidden, it has been made invisible,
    which is a different and worse thing.
    """
    print("\nwhat the contact page does with hidden things")

    contact = json.loads(CONTACT.read_text())
    contact["revision"] = 10
    contact["reach"]["items"][0]["label"] = f"{MARK}-reach-one"
    contact["reach"]["items"][0]["status"] = "hidden"
    publish(base, key, "contact", contact)
    _, page = get(base, "/pages/contact/")

    r.check("a hidden reach row is not rendered", f"{MARK}-reach-one" not in page)
    r.check("but the band around it still is", 'id="reach-heading"' in page)

    contact["revision"] = 11
    contact["reach"]["items"][0]["status"] = "shown"
    contact["reach"]["status"] = "hidden"
    publish(base, key, "contact", contact)
    _, page = get(base, "/pages/contact/")

    r.check("a hidden reach band takes its rows with it",
            f"{MARK}-reach-one" not in page and 'id="reach-heading"' not in page)
    r.check("and the enquiry form is untouched", 'id="contact-heading"' in page,
            "the form is the page -- it has no switch, and must not vanish")

    contact["revision"] = 12
    contact["reach"]["status"] = "shown"
    contact["offices"]["status"] = "hidden"
    publish(base, key, "contact", contact)
    _, page = get(base, "/pages/contact/")

    r.check("a hidden offices band is gone entirely",
            'id="offices-heading"' not in page)
    r.check("and it is gone from the structured data too",
            "PostalAddress" not in page,
            "the addresses were still being advertised for a band nobody can see")

    # -------------------------------------------------- the uploaded flag
    contact["revision"] = 13
    contact["offices"]["status"] = "shown"
    office = contact["offices"]["items"][0]
    office["flag"] = "bangladesh"
    office["image"] = {"src": "/uploads/00112233445566aa.png",
                       "webp": "/uploads/00112233445566aa.webp",
                       "width": 120, "height": 80}
    publish(base, key, "contact", contact)
    _, page = get(base, "/pages/contact/")

    r.check("an uploaded flag is what gets drawn",
            '/uploads/00112233445566aa.png' in page)
    r.check("with its WebP sibling offered first",
            '<source srcset="/uploads/00112233445566aa.webp"' in page)
    r.check("and the size that keeps the card still",
            'width="120" height="80"' in page)
    r.check("the bundled flag it would otherwise have used is not drawn as well",
            '/assets/images/flags/bangladesh' not in page,
            "two flags for one office")

    contact["revision"] = 14
    office["image"] = {"src": "", "webp": "", "width": 0, "height": 0}
    publish(base, key, "contact", contact)
    _, page = get(base, "/pages/contact/")
    r.check("removing it falls back to the bundled flag",
            '/assets/images/flags/bangladesh' in page)


def company_round_trip(base: str, key: bytes, r: Results) -> None:
    """Every field the company model declares, set and then read off the page.

    THIS IS WHAT check_content_model.py POINTS AT. That check compares the
    model, the form and the renderer by reading their source, and it cannot do
    it for this page: the editor names its inputs with a loop variable and the
    renderer walks six lists with foreach, so a regex over either finds the
    loop and not the fields. So the agreement is proved the only other way
    there is — put a distinguishable value in every field, publish it, and
    look for it in the HTML a visitor would get.

    A field that stops being rendered fails here. A field renamed on one side
    fails here. Neither is visible to source-reading, and both are the whole
    reason the check exists.
    """
    print("\nthe company profile travels the same road")

    data = json.loads(COMPANY.read_text())
    data["revision"] = 3

    # One marker per scalar the page renders, so a missing one names itself.
    data["meta"]["title"] = f"{MARK}-tab"
    data["meta"]["description"] = f"{MARK}-desc"
    data["meta"]["share_title"] = f"{MARK}-share"
    data["meta"]["keywords"] = f"{MARK}-kw"
    data["hero"]["title"] = f"{MARK}-hero"
    data["hero"]["subtitle"] = f"{MARK}-sub"
    data["milestones"]["eyebrow"] = f"{MARK}-m-eyebrow"
    data["milestones"]["title"] = f"{MARK}-m-title"
    data["milestones"]["lead"] = f"<p>{MARK}-m-lead</p>"
    data["background"]["eyebrow"] = f"{MARK}-b-eyebrow"
    data["background"]["title"] = f"{MARK}-b-title"
    data["experience"]["title"] = f"{MARK}-x-title"
    data["clients"]["title"] = f"{MARK}-c-title"
    data["journey"]["title"] = f"{MARK}-j-title"
    data["journey"]["lead"] = f"<p>{MARK}-j-lead</p>"
    data["journey"]["interval"] = 9500
    data["excellence"]["eyebrow"] = f"{MARK}-e-eyebrow"
    data["excellence"]["title"] = f"{MARK}-e-title"
    data["excellence"]["lead"] = f"<p>{MARK}-e-lead</p>"
    data["technology"]["title"] = f"{MARK}-t-title"
    data["principles"]["title"] = f"{MARK}-p-title"
    data["cta"]["title"] = f"{MARK}-cta-title"
    data["cta"]["text"] = f"<p>{MARK}-cta-text</p>"
    data["cta"]["label"] = f"{MARK}-cta-label"

    # One row per list, every field of it marked.
    data["milestones"]["items"] = [{
        "id": "mark", "year": "2031", "title": f"{MARK}-m-row",
        "text": f"{MARK}-m-text", "status": "shown"}]
    data["experience"]["items"] = [{
        "id": "mark", "figure": "42+", "label": f"{MARK}-x-label", "status": "shown"}]
    data["clients"]["items"] = [{
        "id": "mark", "name": f"{MARK}-c-name", "status": "shown",
        "image": {"src": "/assets/images/clients/cca.jpg",
                  "webp": "/assets/images/clients/cca.webp",
                  "width": 320, "height": 167}}]
    data["journey"]["items"] = [{
        "id": "mark", "alt": f"{MARK}-j-alt", "status": "shown",
        "image": {"src": "/assets/images/photos/celebration-1.jpg",
                  "webp": "/assets/images/photos/celebration-1.webp",
                  "width": 1024, "height": 768}}]
    data["technology"]["items"] = [{
        "id": "mark", "name": f"{MARK}-t-name", "status": "shown",
        "image": {"src": "/assets/images/tech/metasploit.svg", "webp": "",
                  "width": 1000, "height": 222}}]
    data["principles"]["items"] = [{
        "id": "mark", "icon": "lightbulb", "title": f"{MARK}-p-title-row",
        "text": f"{MARK}-p-text", "status": "shown"}]

    status, answer = publish(base, key, "company", data)
    r.check("the company profile publishes", status == 200 and answer.get("ok") is True,
            f"{status} {answer}")

    _, page = get(base, "/pages/company-profile/")

    missing = [k for k in (
        "tab", "desc", "share", "kw", "hero", "sub",
        "m-eyebrow", "m-title", "m-lead", "m-row", "m-text",
        "b-eyebrow", "b-title", "x-title", "x-label",
        "c-title", "c-name", "j-title", "j-lead", "j-alt",
        "e-eyebrow", "e-title", "e-lead", "t-title", "t-name",
        "p-title", "p-title-row", "p-text",
        "cta-title", "cta-text", "cta-label",
    ) if f"{MARK}-{k}" not in page]
    r.check("every field the model declares reaches the page",
            not missing, "never rendered: " + ", ".join(missing))

    # Not just present -- present as the tag it is meant to be. A keyword list
    # that reached the page inside some other element would satisfy the marker
    # walk above and tell a search engine nothing.
    r.check("the keywords reach the page as a keywords tag",
            f'<meta name="keywords" content="{MARK}-kw">' in page,
            "the marker arrived, but not in the tag it is for")

    r.check("the figure keeps its count-up hook", 'data-count-up>42+<' in page)
    r.check("the slideshow carries the interval it was given",
            'data-slider-interval="9500"' in page, "9500")
    r.check("a logo with a WebP sibling gets a <picture>",
            '<source srcset="/assets/images/clients/cca.webp"' in page)
    r.check("and one without gets a bare <img> and no wrapper",
            '<picture><source srcset="/assets/images/tech/metasploit' not in page
            and 'src="/assets/images/tech/metasploit.svg"' in page,
            "an SVG has no WebP version, and a <picture> with one <img> and no "
            "<source> says a choice is being made when none is")
    r.check("every picture carries the size that keeps the page still",
            'width="320" height="167"' in page and 'width="1024" height="768"' in page)
    r.check("the principle's icon is drawn", '<use href="#lightbulb">' in page)
    r.check("the slideshow has one dot per photograph",
            page.count('data-slider-to=') == 1, str(page.count('data-slider-to=')))

    print("\nwhat the page does with hidden things")
    data["revision"] = 4
    data["clients"]["items"][0]["status"] = "hidden"
    data["cta"]["status"] = "hidden"
    publish(base, key, "company", data)
    _, page = get(base, "/pages/company-profile/")

    r.check("a hidden row is not rendered", f"{MARK}-c-name" not in page)
    r.check("but the band around it still is", f"{MARK}-c-title" in page)
    r.check("a hidden band is gone entirely", f"{MARK}-cta-title" not in page)
    r.check("and the rest of the page is untouched", f"{MARK}-t-name" in page)

    print("\na signature is not a promise about what is inside")
    data["revision"] = 5
    data["clients"]["items"][0]["status"] = "shown"
    data["cta"]["status"] = "shown"
    data["cta"]["text"] = '<p onclick="steal()">hi</p><script>steal()</script>'
    data["clients"]["items"][0]["image"]["src"] = "https://evil.example/logo.png"
    status, _ = publish(base, key, "company", data)
    r.check("a validly signed payload is accepted", status == 200, str(status))

    stored = json.loads(COMPANY.read_text())
    _, page = get(base, "/pages/company-profile/")
    r.check("but the script is gone", "steal()" not in page and "onclick" not in page)
    r.check("and the text around it survives", ">hi<" in page)
    r.check("a picture pointing at another origin is dropped on receipt",
            stored["clients"]["items"][0]["image"]["src"] == "",
            "a signature proves where a document came from, not what is in it — "
            "an <img src> elsewhere would put a third party in every page load")
    r.check("and nothing on the page points there", "evil.example" not in page)


# --------------------------------------------------------------------- main



def about_round_trip(base: str, key: bytes, r: Results) -> None:
    """Every field the about model declares, set and then read off the page.

    THIS IS WHAT check_content_model.py POINTS AT, for the same reason
    company_round_trip() is: the editor names its inputs with a loop variable
    and the renderer walks three lists with foreach, so a regex over either
    finds the loop and not the fields. Put a distinguishable value in every
    field, publish it, and look for it in the HTML a visitor would get.
    """
    print("\nthe about page travels the same road")

    data = json.loads(ABOUT.read_text())
    data["revision"] = 3

    data["meta"]["title"] = f"{MARK}-tab"
    data["meta"]["description"] = f"{MARK}-desc"
    data["meta"]["share_title"] = f"{MARK}-share"
    data["hero"]["title"] = f"{MARK}-hero"
    data["hero"]["subtitle"] = f"{MARK}-sub"
    data["specialties"]["title"] = f"{MARK}-s-title"
    data["specialties"]["interval"] = 7500
    data["whyus"]["title"] = f"{MARK}-w-title"
    data["cta"]["title"] = f"{MARK}-cta-title"
    data["cta"]["label"] = f"{MARK}-cta-label"
    data["cta"]["href"] = "/pages/services/"
    data["cta"]["icon"] = "arrow-right"

    # Two story rows: one photograph, one logo lockup, so both branches render.
    data["story"]["items"] = [
        {"id": "mark-photo", "heading": f"{MARK}-st-heading",
         "body": f"<p>{MARK}-st-body-one</p><p>{MARK}-st-body-two</p>",
         "layout": "photograph", "side": "right", "alt": f"{MARK}-st-alt",
         "status": "shown",
         "image": {"src": "/assets/images/sections/our-goal.jpg",
                   "webp": "/assets/images/sections/our-goal.webp",
                   "width": 818, "height": 810}},
        {"id": "mark-logo", "heading": f"{MARK}-st-logo-heading",
         "body": f"<p>{MARK}-st-logo-body</p>",
         "layout": "logo", "side": "left", "alt": f"{MARK}-st-logo-alt",
         "status": "shown",
         "image": {"src": "", "webp": "", "width": 0, "height": 0}},
    ]
    data["specialties"]["items"] = [{
        "id": "mark", "icon": "cloud", "title": f"{MARK}-sp-title",
        "text": f"{MARK}-sp-text", "status": "shown"}]
    data["whyus"]["items"] = [{
        "id": "mark", "icon": "trophy", "title": f"{MARK}-w-row",
        "text": f"{MARK}-w-text", "status": "shown"}]

    # The accreditations wall, shown for this run -- it ships hidden. Two rows,
    # because the caption is per row and one of each is the only way to see
    # that the switch is read rather than assumed.
    # Set whole rather than keyed into: this band is NOT in the shipped
    # content/about.json and is not supposed to be. It reaches a document by
    # about_normalise() merging it in, which is the behaviour a publish has to
    # survive, so the test writes the band the way the editor would send it.
    data["accreditations"] = {
        "status": "shown",
        "title": f"{MARK}-a-title",
        "items": [
            {"id": "mark-named", "name": f"{MARK}-a-named", "caption": "shown",
             "status": "shown",
             "image": {"src": "/uploads/7777777777777777.png", "webp": "",
                       "width": 240, "height": 174}},
            {"id": "mark-bare", "name": f"{MARK}-a-bare", "caption": "hidden",
             "status": "shown",
             "image": {"src": "/uploads/8888888888888888.png", "webp": "",
                       "width": 240, "height": 174}},
        ],
    }

    status, answer = publish(base, key, "about", data)
    r.check("the about page publishes", status == 200 and answer.get("ok") is True,
            f"{status} {answer}")

    _, page = get(base, "/pages/about/")

    missing = [k for k in (
        "tab", "desc", "share", "hero", "sub",
        "st-heading", "st-body-one", "st-body-two", "st-alt",
        "st-logo-heading", "st-logo-body", "st-logo-alt",
        "s-title", "sp-title", "sp-text",
        "w-title", "w-row", "w-text",
        "cta-title", "cta-label",
        "a-title", "a-named", "a-bare",
    ) if f"{MARK}-{k}" not in page]
    r.check("every field the model declares reaches the page",
            not missing, "never rendered: " + ", ".join(missing))

    r.check("each paragraph of a section gets its own reveal",
            page.count(f'<p data-reveal data-reveal-delay>{MARK}-st-body') == 2,
            "about_reveal_paragraphs() puts back what the editor cannot type")
    r.check("the slideshow carries the interval it was given",
            'data-slider-interval="7500"' in page)
    r.check("the slideshow has one dot per speciality",
            page.count('data-slider-to=') == 1, str(page.count('data-slider-to=')))
    r.check("a photograph row gets a <picture> and its size",
            '<source srcset="/assets/images/sections/our-goal.webp"' in page
            and 'width="818" height="810"' in page)
    r.check("a logo row draws the lockup instead",
            'class="theme-swap--light"' in page and 'class="theme-swap--dark"' in page,
            "layout=logo ignores the row's picture and uses the brand asset")
    r.check("the side a picture sits on travels",
            'class="about-split about-split--reverse"' in page)
    r.check("the icons chosen at run time are drawn",
            '<use href="#cloud">' in page and '<use href="#trophy">' in page)
    r.check("each section's heading id matches what labels it",
            'aria-labelledby="mark-photo-heading"' in page
            and 'id="mark-photo-heading"' in page,
            "a generated id must match the aria-labelledby generated beside it")

    print("\nan accreditation's name can be printed or only announced")
    r.check("a captioned badge prints its name and lets the picture be decorative",
            f'<span class="accreditation__name">{MARK}-a-named</span>' in page
            and '/uploads/7777777777777777.png" alt=""' in page,
            "with the name beside it, alt= would make a reader hear it twice")
    r.check("an uncaptioned badge prints nothing and carries the name as alt",
            f'<span class="accreditation__name">{MARK}-a-bare</span>' not in page
            and f'/uploads/8888888888888888.png" alt="{MARK}-a-bare"' in page,
            "hiding the caption must not take the name away from a screen reader")
    r.check("the name is still on the page when the caption is off",
            f'<span class="visually-hidden">{MARK}-a-bare</span>' in page)
    r.check("both badges are drawn",
            page.count('class="accreditation__plate"') == 2,
            str(page.count('class="accreditation__plate"')))

    print("\na photograph section may carry artwork for each colour mode")
    r.check("with no dark half it draws ONE picture and no theme-swap",
            page.count('class="about-split__image"') == 1
            and "about-split__image--dark" not in page,
            "the page must not grow a second picture per row for an unused feature")

    data["revision"] = 4
    data["story"]["items"][0]["image_dark"] = {
        "src": "/uploads/4444444444444444.webp", "webp": "", "width": 818, "height": 810}
    publish(base, key, "about", data)
    _, page = get(base, "/pages/about/")
    r.check("the dark half appears only once a dark image is given",
            "/uploads/4444444444444444.webp" in page
            and 'class="about-split__image about-split__image--dark theme-swap--dark"' in page)
    r.check("and the light half is marked as the light one",
            'class="about-split__image theme-swap--light"' in page)

    data["revision"] = 5
    data["story"]["items"][0]["image_dark"] = {
        "src": "", "webp": "", "width": 0, "height": 0}
    publish(base, key, "about", data)
    _, page = get(base, "/pages/about/")
    # Scoped to the photograph's own class pair. The LOGO row wraps its two
    # halves in <picture class="theme-swap--light"> whatever happens, so a bare
    # "theme-swap--light" is always on this page and asserting on it tests the
    # wrong row.
    r.check("clearing it goes back to one picture and no swap",
            page.count('class="about-split__image"') == 1
            and 'class="about-split__image theme-swap--light"' not in page)

    print("\na logo section may carry a logo of its own")
    data["revision"] = 6
    data["story"]["items"][1]["image"] = {
        "src": "/uploads/1111111111111111.webp", "webp": "",
        "width": 540, "height": 192}
    publish(base, key, "about", data)
    _, page = get(base, "/pages/about/")
    # Scoped to the story section: the header and the footer carry the logo
    # too, so "the shipped file is absent from the page" is never true.
    SHIPPED = 'about-split__image--contain" src="/assets/images/logo/'
    r.check("an uploaded logo replaces the one that ships with the site",
            "/uploads/1111111111111111.webp" in page and SHIPPED not in page,
            "a company that changes its mark should not need a deploy")
    r.check("and with only one uploaded it is used in both colour modes",
            page.count("/uploads/1111111111111111.webp") == 2,
            f"drawn {page.count('/uploads/1111111111111111.webp')} times — falling "
            "back to the shipped dark logo would show the old mark beside the new one")

    data["revision"] = 7
    data["story"]["items"][1]["image_dark"] = {
        "src": "/uploads/2222222222222222.webp", "webp": "",
        "width": 540, "height": 192}
    publish(base, key, "about", data)
    _, page = get(base, "/pages/about/")
    r.check("a dark half is used when it is given",
            'class="theme-swap--dark"><img class="about-split__image about-split__image--contain" '
            'src="/uploads/2222222222222222.webp"' in page,
            "each colour mode gets its own")
    r.check("and the light half is untouched",
            'class="theme-swap--light"><img class="about-split__image about-split__image--contain" '
            'src="/uploads/1111111111111111.webp"' in page)

    data["revision"] = 8
    data["story"]["items"][1]["image"] = {"src": "", "webp": "", "width": 0, "height": 0}
    data["story"]["items"][1]["image_dark"] = {"src": "", "webp": "", "width": 0, "height": 0}
    publish(base, key, "about", data)
    _, page = get(base, "/pages/about/")
    r.check("clearing both brings the shipped lockup back",
            page.count(SHIPPED) == 2
            and "/uploads/1111111111111111.webp" not in page)

    print("\nwhat the about page does with hidden things")
    data["revision"] = 9
    data["whyus"]["items"][0]["status"] = "hidden"
    data["cta"]["status"] = "hidden"
    data["accreditations"]["items"][0]["status"] = "hidden"
    publish(base, key, "about", data)
    _, page = get(base, "/pages/about/")

    r.check("a hidden row is not rendered", f"{MARK}-w-row" not in page)
    r.check("but the band around it still is", f"{MARK}-w-title" in page)
    r.check("a hidden band is gone entirely", f"{MARK}-cta-title" not in page)
    r.check("and the rest of the page is untouched", f"{MARK}-sp-title" in page)
    r.check("a hidden badge takes its picture with it",
            "/uploads/7777777777777777.png" not in page
            and f"{MARK}-a-named" not in page)
    r.check("and the badge beside it is still there",
            "/uploads/8888888888888888.png" in page)

    # The band ships hidden, so this is the state every visitor is in until
    # somebody switches it on -- the one that has to leave no trace at all.
    data["revision"] = 10
    data["accreditations"]["status"] = "hidden"
    publish(base, key, "about", data)
    _, page = get(base, "/pages/about/")
    r.check("the whole wall switched off leaves nothing behind",
            "accreditation__plate" not in page
            and "accreditations__grid" not in page
            and f"{MARK}-a-title" not in page)
    r.check("and the page around it is unharmed",
            f"{MARK}-sp-title" in page and f"{MARK}-hero" in page)
    data["accreditations"]["status"] = "shown"

    print("\na signature is not a promise about what is inside")
    data["revision"] = 11
    data["whyus"]["items"][0]["status"] = "shown"
    data["cta"]["status"] = "shown"
    data["story"]["items"][0]["body"] = ('<p onclick="steal()">hi</p>'
                                         '<script>steal()</script>')
    data["story"]["items"][0]["image"]["src"] = "https://evil.example/photo.jpg"
    status, _ = publish(base, key, "about", data)
    r.check("a validly signed payload is accepted", status == 200, str(status))

    stored = json.loads(ABOUT.read_text())
    _, page = get(base, "/pages/about/")
    r.check("but the script is gone", "steal()" not in page and "onclick" not in page)
    r.check("and the text around it survives", ">hi<" in page)
    r.check("a picture pointing at another origin is dropped on receipt",
            stored["story"]["items"][0]["image"]["src"] == "",
            str(stored["story"]["items"][0]["image"]))


def services_round_trip(base: str, key: bytes, r: Results) -> None:
    """Every field the services model declares, set and read off SEVEN pages.

    THIS IS WHAT check_content_model.py POINTS AT, for the reason
    about_round_trip() is and more so: this document draws seven pages and
    every part of all seven is a loop, so a regex over the renderer finds
    $card and $group rather than a field name. Put a distinguishable value in
    every field, publish it, and look for it in the HTML a visitor would get.

    It also checks the four things the renderer DRAWS rather than stores — the
    card beside the ring, the ring's nodes, the count, and the Service graph.
    Those cannot be proved by a marker, because nothing types them; they are
    proved by being consistent with the cards they come from.
    """
    print("\nthe services pages travel the same road")

    data = json.loads(SERVICES.read_text())
    data["revision"] = 30

    data["meta"]["title"] = f"{MARK}-tab"
    data["meta"]["description"] = f"{MARK}-desc"
    data["meta"]["share_title"] = f"{MARK}-share"
    data["hero"]["title"] = f"{MARK}-hero"
    data["hero"]["subtitle"] = f"{MARK}-sub"

    data["nav"]["eyebrow"] = f"{MARK}-nav-eyebrow"
    data["nav"]["title"] = f"{MARK}-nav-title"
    data["nav"]["lead"] = f"{MARK}-nav-lead"
    data["nav"]["items"] = [{
        "id": "mark-nav", "block": "mark-block", "icon": "cloud",
        "title": f"{MARK}-nav-card", "text": f"{MARK}-nav-text", "status": "shown"}]

    data["blocks"]["items"] = [{
        "id": "mark-block", "service": "cybersecurity", "icon": "server",
        "title": f"{MARK}-block-title", "intro": f"{MARK}-block-intro",
        "status": "shown",
        "groups": [{"id": "mark-group", "title": f"{MARK}-group-title",
                    "width": "wide", "items": [f"{MARK}-group-item"],
                    "status": "shown"}],
        "buttons": [{"id": "mark-button", "label": f"{MARK}-button-label",
                     "href": "/pages/services/cybersecurity/", "icon": "arrow-right",
                     "style": "secondary", "status": "shown"}]}]

    data["ossf"]["eyebrow"] = f"{MARK}-ossf-eyebrow"
    data["ossf"]["title"] = f"{MARK}-ossf-title"
    data["ossf"]["lead"] = f"{MARK}-ossf-lead"
    data["ossf"]["items"] = [{
        "id": "mark-stage", "icon": "crosshairs", "title": f"{MARK}-stage-title",
        "text": f"{MARK}-stage-text", "status": "shown"}]

    data["cta"]["title"] = f"{MARK}-cta-title"
    data["cta"]["text"] = f"{MARK}-cta-text"
    data["cta"]["label"] = f"{MARK}-cta-label"
    data["cta"]["href"] = "/pages/contact/"
    data["cta"]["icon"] = "calendar-check"

    # Two solutions, so the ring has more than one node and the card beside it
    # can be seen to be the FIRST rather than "the only one there was".
    cards = [
        {"id": "sol-mark-one", "icon": "shield-alt", "name": f"{MARK}-card-one",
         "category": f"{MARK}-card-cat", "desc": f"{MARK}-card-desc",
         "purpose": f"{MARK}-card-purpose", "features": [f"{MARK}-card-feature"],
         "tags": [f"{MARK}-card-tag"], "status": "shown"},
        {"id": "sol-mark-two", "icon": "lock", "name": f"{MARK}-card-two",
         "category": "", "desc": "", "purpose": "",
         "features": [], "tags": [], "status": "shown"},
    ]

    data["services"]["items"] = [{
        # The slug is one of the six that HAVE a directory. A service at a
        # slug of its own needs a route, which is the next piece of work and
        # not this one -- see plans/ and the note in adding-a-page.md.
        "id": "cybersecurity", "slug": "cybersecurity", "name": f"{MARK}-svc-name",
        "status": "shown",
        "schema_type": f"{MARK}-svc-type",
        "schema_description": f"{MARK}-svc-schema-desc",
        "meta": {"title": f"{MARK}-svc-tab", "description": f"{MARK}-svc-desc",
                 "share_title": f"{MARK}-svc-share"},
        "hero": {"title": f"{MARK}-svc-hero", "subtitle": f"{MARK}-svc-sub"},
        "core": {"status": "shown", "eyebrow": f"{MARK}-core-eyebrow",
                 "title": f"{MARK}-core-title", "lead": f"{MARK}-core-lead",
                 "note": {"text": f"{MARK}-note-text",
                          "link_label": f"{MARK}-note-label",
                          "link_href": "/pages/services/"},
                 "items": [{"id": "mark-core", "icon": "code",
                            "title": f"{MARK}-core-card", "text": f"{MARK}-core-text",
                            "status": "shown"}]},
        "layers": {"status": "shown", "eyebrow": f"{MARK}-lay-eyebrow",
                   "title": f"{MARK}-lay-title", "lead": f"{MARK}-lay-lead",
                   "labels": {"purpose": f"{MARK}-lab-purpose",
                              "features": f"{MARK}-lab-features",
                              "tags": f"{MARK}-lab-tags",
                              "count_one": f"{MARK}-one",
                              "count_many": f"{MARK}-many"},
                   "items": [{"id": "mark-layer", "icon": "eye",
                              "title": f"{MARK}-layer-title",
                              "tab_text": f"{MARK}-layer-tab",
                              "text": f"{MARK}-layer-text",
                              "hub_label": f"{MARK}-hub", "status": "shown",
                              "cards": cards}]},
        "cta": {"status": "shown", "title": f"{MARK}-svc-cta-title",
                "text": f"{MARK}-svc-cta-text", "label": f"{MARK}-svc-cta-label",
                "href": "/pages/contact/", "icon": "calendar-check"},
    }]

    status, answer = publish(base, key, "services", data)
    r.check("the services document publishes",
            status == 200 and answer.get("ok") is True, f"{status} {answer}")

    _, index = get(base, "/pages/services/")
    missing = [k for k in (
        "tab", "desc", "share", "hero", "sub",
        "nav-eyebrow", "nav-title", "nav-lead", "nav-card", "nav-text",
        "block-title", "block-intro", "group-title", "group-item", "button-label",
        "ossf-eyebrow", "ossf-title", "ossf-lead", "stage-title", "stage-text",
        "cta-title", "cta-text", "cta-label",
    ) if f"{MARK}-{k}" not in index]
    r.check("every field the index declares reaches the page",
            not missing, "never rendered: " + ", ".join(missing))

    _, page = get(base, "/pages/services/cybersecurity/")
    missing = [k for k in (
        "svc-tab", "svc-desc", "svc-share", "svc-hero", "svc-sub",
        "svc-type", "svc-schema-desc", "svc-name",
        "core-eyebrow", "core-title", "core-lead", "core-card", "core-text",
        "note-text", "note-label",
        "lay-eyebrow", "lay-title", "lay-lead",
        "lab-purpose", "lab-features", "lab-tags",
        "layer-title", "layer-tab", "layer-text", "hub",
        "card-one", "card-two", "card-cat", "card-desc", "card-purpose",
        "card-feature", "card-tag",
        "svc-cta-title", "svc-cta-text", "svc-cta-label",
    ) if f"{MARK}-{k}" not in page]
    r.check("every field a service page declares reaches it",
            not missing, "never rendered: " + ", ".join(missing))

    print("\nwhat the renderer draws rather than stores")
    r.check("the card beside the ring is the layer's FIRST card",
            page.count(f"{MARK}-card-one") > page.count(f"{MARK}-card-two"),
            "it is drawn twice — in the grid and beside the ring — where the "
            "second card is drawn once")
    r.check("it carries no id of its own",
            'class="tool-card tool-card--detail" id=' not in page,
            "two elements with one id would make the fragment link ambiguous")
    r.check("the ring has one node per card",
            page.count('class="soc-map__node"') == 2,
            str(page.count('class="soc-map__node"')))
    r.check("each node names the card it opens",
            'data-solution="sol-mark-one"' in page
            and 'data-solution="sol-mark-two"' in page)
    r.check("the ring is sized by the count",
            'class="soc-map soc-map--n2 soc-map--sm"' in page)
    r.check("the count is the number of cards, in the words given",
            f'class="layer__count">2 {MARK}-many<' in page,
            "it is derived, so it cannot disagree with the cards below it")

    graph = [b for b in json_ld(page) if b.get("@type") == "Service"]
    r.check("the Service graph is generated from the page", len(graph) == 1,
            f"{len(graph)} Service blocks")
    if graph:
        svc = graph[0]
        r.check("it takes the name and the type it was given",
                svc.get("name") == f"{MARK}-svc-name"
                and svc.get("serviceType") == f"{MARK}-svc-type",
                f"{svc.get('name')} / {svc.get('serviceType')}")
        catalog = svc.get("hasOfferCatalog", {}).get("itemListElement", [])
        r.check("its offer catalogue IS the layers",
                [c.get("name") for c in catalog] == [f"{MARK}-layer-title"],
                str(catalog))
        r.check("and each entry links to the layer's anchor",
                catalog and catalog[0].get("url", "").endswith("#layer-mark-layer"),
                str(catalog))

    print("\nhiding removes a thing from every place it appears")
    data["revision"] = 31
    data["services"]["items"][0]["layers"]["items"][0]["cards"][1]["status"] = "hidden"
    publish(base, key, "services", data)
    _, page = get(base, "/pages/services/cybersecurity/")
    r.check("a hidden solution leaves the grid", f"{MARK}-card-two" not in page)
    r.check("and the ring", page.count('class="soc-map__node"') == 1)
    r.check("and the count follows it",
            f'class="layer__count">1 {MARK}-one<' in page,
            "and takes the singular, because there is one of it now")

    data["revision"] = 32
    data["services"]["items"][0]["layers"]["status"] = "hidden"
    publish(base, key, "services", data)
    _, page = get(base, "/pages/services/cybersecurity/")
    r.check("a hidden band leaves the page", f"{MARK}-layer-title" not in page)
    r.check("and the rest of the page stays", f"{MARK}-core-card" in page)

    print("\nhiding a service hides the whole of it")
    data["revision"] = 33
    data["services"]["items"][0]["layers"]["status"] = "shown"
    data["services"]["items"][0]["status"] = "hidden"
    publish(base, key, "services", data)
    status, page = get(base, "/pages/services/cybersecurity/")
    r.check("its page answers 404", status == 404, f"status {status}")
    _, index = get(base, "/pages/services/")
    r.check("its block leaves the index", f"{MARK}-block-title" not in index,
            "otherwise the index links to a page that 404s")
    r.check("and so does the card that jumped to that block",
            f"{MARK}-nav-card" not in index)

    data["revision"] = 34
    data["services"]["items"][0]["status"] = "shown"
    publish(base, key, "services", data)
    status, _ = get(base, "/pages/services/cybersecurity/")
    r.check("showing it again brings the page back", status == 200, f"status {status}")

    print("\nthe sprite carries what the document asked for")
    _, page = get(base, "/pages/services/cybersecurity/")
    used = set(re.findall(r'<use href="#([a-z0-9-]+)"', page))
    defined = set(re.findall(r'<symbol id="([^"]+)"', page))
    # The hero circuit and the dock both point <use> at their OWN <defs>.
    # Those are local references and have no business in the sprite.
    defined |= set(re.findall(r'\bid="((?:hc|dock)-[^"]+)"', page))
    r.check("every symbol the page points at is inlined in it",
            not (used - defined), sorted(used - defined))
    r.check("an icon chosen in the document is among them",
            "eye" in defined and "shield-alt" in defined,
            "the sprite is worked out at render time, so a newly chosen icon "
            "arrives with the card that chose it")


def services_seventh(base: str, key: bytes, r: Results) -> None:
    """A service that exists only in the document, and the page it gets.

    WHAT THIS IS FOR
    The six services shipped as directories, and every tool here finds a page
    by walking the filesystem. A service added in the editor has no directory
    and never will — nothing in this repository runs when content is published
    — so everything that makes a page a page has to work for a row instead: an
    address that resolves, a head that names itself, a place in the sitemap, a
    404 when it is hidden, and a link from the index.

    None of that is proved by the round trip above, which only ever edits
    services that already have somewhere to live.

    The .htaccess rewrite is NOT exercised here: the local server does not read
    .htaccess, and tools/dev-router.php carries the same route so this can be
    tested at all. tools/verify_live.py is what asks the host whether its half
    of the route works.
    """
    print("\na seventh service, which has no directory")

    slug = "quantum-readiness"
    r.check("the slug being added really has no directory here",
            not (ROOT / "pages" / "services" / slug).is_dir(),
            "the point of the test is a service with nowhere to live")

    def sitemap_services(xml: str) -> list[str]:
        return re.findall(
            r"<loc>https://tech4time\.bd/pages/services/([a-z0-9-]+)/</loc>", xml)

    _, before = get(base, "/sitemap.xml")
    listed_before = sitemap_services(before)

    data = json.loads(SERVICES.read_text())
    data["revision"] = 40

    seventh = copy.deepcopy(data["services"]["items"][0])
    seventh["id"] = "seventh-service"
    seventh["slug"] = slug
    seventh["name"] = f"{MARK} Quantum"
    seventh["status"] = "shown"
    seventh["meta"]["title"] = f"{MARK}-7th-tab"
    seventh["meta"]["description"] = f"{MARK}-7th-desc"
    seventh["meta"]["share_title"] = f"{MARK}-7th-share"
    seventh["hero"]["title"] = f"{MARK}-7th-hero"
    seventh["hero"]["subtitle"] = f"{MARK}-7th-sub"
    seventh["cta"]["title"] = f"{MARK}-7th-cta"
    data["services"]["items"].append(seventh)

    # On the index the way the editor would put it there: a block that names
    # the service, and a nav card that jumps to the block.
    data["blocks"]["items"].append({
        "id": "seventh-block", "service": slug, "icon": "server",
        "title": f"{MARK}-7th-block", "intro": f"{MARK}-7th-intro",
        "status": "shown", "groups": [],
        # A block links to its service page through a button, exactly as the
        # six do. Adding a service and forgetting this leaves a page that is
        # reachable and a home page that never mentions it, which is why
        # content-runbook.md lists the button as part of adding a service.
        "buttons": [{"id": "seventh-button", "label": f"{MARK}-7th-button",
                     "href": f"/pages/services/{slug}/", "icon": "arrow-right",
                     "style": "secondary", "status": "shown"}]})
    data["nav"]["items"].append({
        "id": "seventh-nav", "block": "seventh-block", "icon": "cloud",
        "title": f"{MARK}-7th-nav", "text": f"{MARK}-7th-navtext",
        "status": "shown"})

    status, answer = publish(base, key, "services", data)
    r.check("a service can be added to the document",
            status == 200 and answer.get("ok") is True, f"{status} {answer}")

    clean = f"/pages/services/{slug}/"
    status, page = get(base, clean)
    r.check("and its page answers at an address with no file behind it",
            status == 200, f"status {status}")
    r.check("with the hero it was given", f"{MARK}-7th-hero" in page)
    r.check("and its own <title>", f"<title>{MARK}-7th-tab</title>" in page)
    r.check("and its own description",
            f'name="description" content="{MARK}-7th-desc"' in page)

    r.check("the canonical is the clean address, not the .php one",
            f'<link rel="canonical" href="https://tech4time.bd{clean}">' in page,
            "a page reachable at two addresses is a duplicate-content problem")
    r.check("and so is og:url",
            f'<meta property="og:url" content="https://tech4time.bd{clean}">' in page)

    r.check("it is a whole page, not a fragment",
            page.count("<main") == 1 and "</html>" in page)
    r.check("with the bands the other six have",
            f"{MARK}-7th-cta" in page and "page-hero__title" in page)

    # The sprite is worked out from the document at render time, so a page
    # nobody wrote a comment for still gets its icons.
    used = set(re.findall(r'<use href="#([a-z0-9-]+)"', page))
    defined = set(re.findall(r'<symbol id="([^"]+)"', page))
    defined |= set(re.findall(r'\bid="((?:hc|dock)-[^"]+)"', page))
    r.check("every icon it points at is inlined in it",
            not (used - defined), sorted(used - defined))

    _, index = get(base, "/pages/services/")
    r.check("the index links to it", clean in index,
            "a service nobody can navigate to is not added")
    r.check("and shows the block that was added with it",
            f"{MARK}-7th-block" in index)

    status, sitemap = get(base, "/sitemap.xml")
    r.check("the sitemap answers at the address robots.txt names",
            status == 200 and "<urlset" in sitemap, f"status {status}")
    r.check("and lists the new service",
            f"<loc>https://tech4time.bd{clean}</loc>" in sitemap,
            "a page no sitemap names is a page a crawler is not told about")
    # Relative, not absolute: the round trip above leaves the document holding
    # whichever services it was testing with, and a count typed in here would
    # be a fact about that test rather than about this one.
    listed_after = sitemap_services(sitemap)
    r.check("adding one service adds exactly one line to the sitemap",
            listed_after == listed_before + [slug],
            f"{listed_before} -> {listed_after}")

    status, location = get_unfollowed(base, f"/pages/services/detail.php?service={slug}")
    r.check("asked for by its .php name, the page redirects to the clean one",
            status == 301 and location == clean, f"{status} {location}")

    print("\nand it can be taken away again")

    data["revision"] = 41
    data["services"]["items"][-1]["status"] = "hidden"
    publish(base, key, "services", data)

    status, _ = get(base, clean)
    r.check("hidden, its page answers 404", status == 404, f"status {status}")
    _, index = get(base, "/pages/services/")
    r.check("and the index stops linking to it", clean not in index)
    r.check("and the block that named it goes too", f"{MARK}-7th-block" not in index)
    _, sitemap = get(base, "/sitemap.xml")
    r.check("and the sitemap stops naming it", clean not in sitemap,
            "a sitemap naming a 404 is a crawl error against the whole site")

    data["revision"] = 42
    data["services"]["items"].pop()
    publish(base, key, "services", data)
    status, _ = get(base, clean)
    r.check("removed outright, it is still a 404", status == 404, f"status {status}")

    status, _ = get(base, "/pages/services/no-such-thing/")
    r.check("a slug the document never had is a 404 too", status == 404,
            f"status {status}")
    status, _ = get(base, "/pages/services/cybersecurity/")
    r.check("and the six that have directories are untouched", status == 200,
            f"status {status}")


def certifications_round_trip(base: str, key: bytes, r: Results) -> None:
    """Every field the certifications model declares, set and read off the page.

    THIS IS WHAT check_content_model.py POINTS AT, for the reason
    about_round_trip() is and one level deeper: a role group walks its roles
    and its certifications, so a regex over the renderer finds $group and
    $cert rather than a field name. Put a distinguishable value in every
    field, publish it, and look for it in the HTML a visitor would get.

    It also checks the things the renderer DRAWS rather than stores -- the
    count on each group heading, the glyph beside every certification, and the
    totals filled into the prose. Those cannot be proved by a marker, because
    nothing types them: they are proved by being consistent with the rows they
    come from, and by MOVING when those rows do.
    """
    print("\nthe certifications page travels the same road")

    page_url = "/pages/resource-certifications/"

    data = json.loads(CERTIFICATIONS.read_text())
    data["revision"] = 50

    data["meta"]["title"] = f"{MARK}-tab"
    data["meta"]["share_title"] = f"{MARK}-share"
    data["meta"]["description"] = f"{MARK}-desc {{certifications}} held"
    data["hero"]["title"] = f"{MARK}-hero"
    data["hero"]["subtitle"] = f"{MARK}-sub"
    data["certs"]["eyebrow"] = f"{MARK}-eyebrow"
    data["certs"]["title"] = f"{MARK}-bandtitle"
    data["certs"]["lead"] = (f"{MARK}-lead {{certifications}} across {{groups}} "
                             f"({{groups-word}}), {{roles}} roles")
    data["cta"]["title"] = f"{MARK}-ctatitle"
    data["cta"]["text"] = f"{MARK}-ctatext"

    data["certs"]["items"] = [
        {"id": "alpha", "slug": "alpha", "icon": "cogs", "blurb": f"{MARK}-blurb",
         "status": "shown", "open": True,
         "roles": [{"id": "r1", "name": f"{MARK}-role1", "status": "shown"},
                   {"id": "r2", "name": f"{MARK}-role2", "status": "shown"},
                   {"id": "r3", "name": f"{MARK}-rolehidden", "status": "hidden"}],
         "items": [{"id": "c1", "name": f"{MARK}-cert1", "status": "shown"},
                   {"id": "c2", "name": f"{MARK}-cert2", "status": "shown"},
                   {"id": "c3", "name": f"{MARK}-certhidden", "status": "hidden"}]},
        {"id": "beta", "slug": "beta", "icon": "first-aid", "blurb": "beta blurb",
         "status": "shown", "open": False,
         "roles": [{"id": "r4", "name": "Beta Role", "status": "shown"}],
         "items": [{"id": "c4", "name": "Beta Cert", "status": "shown"}]},
        {"id": "gamma", "slug": "gamma", "icon": "cogs", "blurb": f"{MARK}-neverseen",
         "status": "hidden", "open": False,
         "roles": [{"id": "r5", "name": f"{MARK}-hiddenrole", "status": "shown"}],
         "items": [{"id": "c5", "name": f"{MARK}-hiddencert", "status": "shown"}]},
    ]
    data["cta"]["items"] = [
        {"id": "b1", "label": f"{MARK}-btn", "href": "/pages/contact/", "icon": "",
         "style": "primary", "status": "shown"},
        {"id": "b2", "label": f"{MARK}-btnhidden", "href": "/x/", "icon": "",
         "style": "ghost", "status": "hidden"},
    ]

    status, answer = publish(base, key, "certifications", data)
    r.check("a validly signed payload is accepted",
            status == 200 and answer.get("ok") is True, f"{status} {answer}")

    status, page = get(base, page_url)
    r.check("the page is served", status == 200, f"status {status}")

    for field in ("tab", "share", "hero", "sub", "eyebrow", "bandtitle",
                  "blurb", "role1", "role2", "cert1", "cert2",
                  "ctatitle", "ctatext", "btn"):
        r.check(f"{field} reaches the visitor", f"{MARK}-{field}" in page)

    r.check("the tab title is the tab title",
            f"<title>{MARK}-tab</title>" in page)
    r.check("and the share title is separate",
            f'property="og:title" content="{MARK}-share"' in page)

    print("\nwhat is hidden is not there at all")

    for gone in ("rolehidden", "certhidden", "neverseen", "hiddenrole",
                 "hiddencert", "btnhidden"):
        r.check(f"{gone} is absent", f"{MARK}-{gone}" not in page)

    r.check("a hidden group takes its whole panel with it",
            'id="gamma"' not in page)

    print("\nthe counts are drawn, not stored")

    # Two shown certifications in the first group, one in the second.
    r.check("each group heading counts the certifications shown inside it",
            '<span class="cert-group__count">2 certifications</span>' in page
            and '<span class="cert-group__count">1 certification</span>' in page,
            "counts: " + str(re.findall(r'cert-group__count">([^<]*)<', page)))

    r.check("one certification is not 'certifications'",
            "1 certifications</span>" not in page)

    r.check("nothing anywhere claims the hidden one",
            "3 certifications" not in page)

    print("\nthe totals in the prose are drawn too")

    # Three shown certifications, two shown groups, three shown role names.
    r.check("the lead resolves its digits",
            f"{MARK}-lead 3 across 2" in page,
            [line for line in page.splitlines() if f"{MARK}-lead" in line][:1])
    r.check("and its spelled form", "(two), 3 roles" in page)
    r.check("the meta description resolves too",
            f'content="{MARK}-desc 3 held"' in page)
    r.check("no token is left showing to a visitor",
            "{certifications}" not in page and "{groups}" not in page
            and "{roles}" not in page)

    print("\nthe rest of what the renderer draws")

    r.check("every certification carries the same glyph, and it is not stored",
            page.count('<use href="#certificate"></use>') == 3,
            str(page.count('<use href="#certificate"></use>')))
    r.check("the group that says it is open is the one that is",
            re.search(r'<details[^>]*id="alpha"[^>]*\sopen>', page) is not None
            and re.search(r'<details[^>]*id="beta"[^>]*\sopen>', page) is None)
    r.check("two role names are slashed apart",
            page.count('class="cert-group__role-sep"') == 1,
            "one separator between two roles, and none after the last")
    r.check("a group's anchor is its slug",
            'id="alpha"' in page and 'id="beta"' in page)

    print("\nand a band can be switched off whole")

    data["revision"] = 51
    data["cta"]["status"] = "hidden"
    status, answer = publish(base, key, "certifications", data)
    r.check("hiding the closing band publishes", status == 200, f"{status} {answer}")

    status, page = get(base, page_url)
    r.check("the whole band is gone", f"{MARK}-ctatitle" not in page)
    r.check("but the page is still a page", f"{MARK}-hero" in page)


def branding_round_trip(base: str, key: bytes, r: Results) -> None:
    """Every field the branding model declares, set and read off the page.

    THIS IS WHAT check_content_model.py POINTS AT, for the reason
    certifications_round_trip() is: a logo card walks the files inside it, so a
    regex over the renderer finds $asset and $file rather than a field name.
    Put a distinguishable value in every field, publish it, and look for it in
    the HTML a visitor would get.

    It also checks the three things the renderer DRAWS rather than stores -- the
    size in a meta line, the format on a download button, and the glyph beside
    it. Those cannot be proved by a marker, because nothing types them: they are
    proved by being consistent with the record they come from and by MOVING
    when it does.

    AND IT CHECKS THE TWO PICTURES STAY APART. A card holds a preview and a
    download, and on the real page they are an 800px file and a 1600px one. A
    renderer that confused them would look completely fine and hand out the
    wrong file, so the sizes here are deliberately different and both are
    asserted where they belong.
    """
    print("\nthe branding page travels the same road")

    page_url = "/pages/branding-and-advertisement/"

    data = json.loads(BRANDING.read_text())
    data["revision"] = 60

    data["meta"]["title"] = f"{MARK}-tab"
    data["meta"]["share_title"] = f"{MARK}-share"
    data["meta"]["description"] = f"{MARK}-desc"
    data["meta"]["breadcrumb"] = f"{MARK}-crumb"
    data["hero"]["title"] = f"{MARK}-hero"
    data["hero"]["subtitle"] = f"{MARK}-sub"
    data["assets"]["eyebrow"] = f"{MARK}-eyebrow"
    data["assets"]["title"] = f"{MARK}-bandtitle"
    data["assets"]["lead"] = f"{MARK}-lead"
    data["legal"]["title"] = f"{MARK}-legaltitle"
    data["cta"]["title"] = f"{MARK}-ctatitle"
    data["cta"]["text"] = f"{MARK}-ctatext"

    png = "/assets/images/branding/logo-light-transparent-full.png"
    preview = "/assets/images/branding/logo-light-transparent.png"

    data["assets"]["items"] = [
        {"id": "alpha", "title": f"{MARK}-title1", "text": f"{MARK}-text1",
         "alt": f"{MARK}-alt1", "plate": "light", "status": "shown",
         "image": {"src": preview, "webp": preview.replace(".png", ".webp"),
                   "width": 800, "height": 285},
         "files": [
             {"id": "f1", "label": f"{MARK}-label1",
              "filename": f"{MARK}-saved1.png", "status": "shown",
              "file": {"src": png, "webp": "", "width": 1600, "height": 570}},
             # A second file on the same card: a vector, which the page LINKS
             # to and must never draw.
             {"id": "f2", "label": f"{MARK}-label2",
              "filename": f"{MARK}-saved2.svg", "status": "shown",
              "file": {"src": "/uploads/" + "b" * 16 + ".svg", "webp": "",
                       "width": 512, "height": 182}},
             {"id": "f3", "label": f"{MARK}-filehidden",
              "filename": f"{MARK}-nope.png", "status": "hidden",
              "file": {"src": png, "webp": "", "width": 10, "height": 10}},
         ]},
        {"id": "beta", "title": "Beta Logo", "text": "beta text",
         "alt": "beta alt", "plate": "neutral", "status": "shown",
         "image": {"src": preview, "webp": "", "width": 800, "height": 450},
         "files": [{"id": "f4", "label": "Plated PNG", "filename": "beta.png",
                    "status": "shown",
                    "file": {"src": png, "webp": "", "width": 1600, "height": 900}}]},
        {"id": "gamma", "title": f"{MARK}-neverseen", "text": f"{MARK}-hiddentext",
         "alt": "x", "plate": "dark", "status": "hidden",
         "image": {"src": preview, "webp": "", "width": 800, "height": 285},
         "files": [{"id": "f5", "label": f"{MARK}-hiddenfile", "filename": "x.png",
                    "status": "shown",
                    "file": {"src": png, "webp": "", "width": 1, "height": 1}}]},
    ]

    data["legal"]["items"] = [
        {"id": "n1", "text": f"<p>{MARK}-note1 <strong>{MARK}-bold</strong></p>",
         "status": "shown"},
        {"id": "n2", "text": f"<p>{MARK}-notehidden</p>", "status": "hidden"},
    ]
    data["cta"]["items"] = [
        {"id": "b1", "label": f"{MARK}-btn", "href": "/pages/contact/",
         "style": "primary", "status": "shown"},
        {"id": "b2", "label": f"{MARK}-btnhidden", "href": "/x/", "style": "ghost",
         "status": "hidden"},
    ]

    status, answer = publish(base, key, "branding", data)
    r.check("a validly signed payload is accepted",
            status == 200 and answer.get("ok") is True, f"{status} {answer}")

    status, page = get(base, page_url)
    r.check("the page is served", status == 200, f"status {status}")

    for field in ("tab", "share", "hero", "sub", "eyebrow", "bandtitle", "lead",
                  "title1", "text1", "alt1", "legaltitle", "note1", "bold",
                  "ctatitle", "ctatext", "btn"):
        r.check(f"{field} reaches the visitor", f"{MARK}-{field}" in page)

    r.check("the tab title is the tab title",
            f"<title>{MARK}-tab</title>" in page)
    r.check("and the share title is separate",
            f'property="og:title" content="{MARK}-share"' in page)
    trail = breadcrumb_names(page)
    r.check("the breadcrumb carries its OWN name, not the hero's",
            bool(trail) and trail[-1] == f"{MARK}-crumb",
            f"the trail ends {trail[-1:] or ['(no BreadcrumbList)']}, "
            f"not [{MARK}-crumb]")

    print("\nwhat is hidden is not there at all")

    for gone in ("filehidden", "neverseen", "hiddentext", "hiddenfile",
                 "notehidden", "btnhidden"):
        r.check(f"{gone} is absent", f"{MARK}-{gone}" not in page)

    print("\nthe preview and the download are different files")

    r.check("the preview is drawn at the preview's size",
            'width="800" height="285"' in page,
            "the card is not using the preview's dimensions")
    r.check("the download link points at the download",
            f'href="{png}" download="{MARK}-saved1.png"' in page,
            "the button does not point at the file it should")
    r.check("the saved-as name is the authored one, not the file's own",
            f'download="{MARK}-saved1.png"' in page
            and 'download="logo-light-transparent-full.png"' not in page)
    # Counted over the CARDS, not the page: the header and the footer carry
    # <picture> elements of their own for the site logo, so a count of the
    # whole document would be measuring the chrome.
    wrapped = len(re.findall(
        r'<picture><source srcset="[^"]*" type="image/webp"><img class="asset__image"', page))
    total = page.count('<img class="asset__image"')
    r.check("a preview with a WebP sibling gets a <picture>", wrapped == 1,
            f"{wrapped} wrapped previews, wanted 1")
    r.check("and one without gets a bare <img>", total == 2 and wrapped == 1,
            f"{total} previews, {wrapped} of them wrapped")

    print("\nthe meta line and the button are drawn, not stored")

    r.check("the meta line quotes the DOWNLOAD's size",
            f"{MARK}-label1 · 1600 × 570" in page,
            [l.strip() for l in page.splitlines() if f"{MARK}-label1" in l][:1])
    r.check("and not the preview's",
            f"{MARK}-label1 · 800 × 285" not in page,
            "the meta line is quoting the preview")
    r.check("the vector's own size is its own",
            f"{MARK}-label2 · 512 × 182" in page)
    r.check("the button says what format the file is",
            "Download PNG" in page and "Download SVG" in page,
            "the derived button label is missing")
    r.check("nothing published a button label",
            "Download PNG" not in json.dumps(data))
    r.check("every button carries the same glyph, and it is not stored",
            page.count('<use href="#arrow-down"></use>') == 3,
            str(page.count('<use href="#arrow-down"></use>')))

    print("\na vector is linked and never drawn")

    r.check("the SVG is a download target",
            f'download="{MARK}-saved2.svg"' in page)
    r.check("and nothing renders it",
            '<img class="asset__image" src="/uploads/' not in page
            and '<source srcset="/uploads/' not in page,
            "an uploaded vector reached an <img> or a <source>")

    print("\nthe plate is per card")

    r.check("each card sits on the background it was given",
            'asset__preview--light' in page and 'asset__preview--neutral' in page)
    r.check("and the hidden card's plate went with it",
            'asset__preview--dark' not in page)

    print("\nthe disclaimer is the one place markup survives")

    r.check("emphasis is printed rather than escaped",
            f"<strong>{MARK}-bold</strong>" in page)

    data["revision"] = 61
    data["legal"]["items"][0]["text"] = (
        f'<p>{MARK}-note1 <script>alert(1)</script></p>')
    status, answer = publish(base, key, "branding", data)
    r.check("a document with script in it still publishes",
            status == 200, f"{status} {answer}")

    status, page = get(base, page_url)
    r.check("but the script does not reach the visitor",
            "<script>alert(1)</script>" not in page and "alert(1)" not in page,
            "the receiving side did not re-sanitise")
    r.check("and the rest of the paragraph did", f"{MARK}-note1" in page)

    print("\nand a band can be switched off whole")

    data["revision"] = 62
    data["cta"]["status"] = "hidden"
    data["legal"]["status"] = "hidden"
    status, answer = publish(base, key, "branding", data)
    r.check("hiding two bands publishes", status == 200, f"{status} {answer}")

    status, page = get(base, page_url)
    r.check("both are gone",
            f"{MARK}-ctatitle" not in page and f"{MARK}-legaltitle" not in page)
    r.check("but the page is still a page", f"{MARK}-hero" in page)



def privacy_round_trip(base: str, key: bytes, r: Results) -> None:
    """Every field the privacy model declares, set and read off the page.

    THIS IS WHAT check_content_model.py POINTS AT, for the reason the branding
    one is: the editor names its inputs "sections[<?= $s ?>][body]" and the
    page renders them by calling md_render() in a loop, so a regex over the
    renderer finds $section rather than a field name. Put a distinguishable
    value in every field, publish it, and look for it in the HTML a visitor
    would get.

    THE DIALECT IS THE POINT. A section body is Markdown source and the shared
    renderer owns all markup, so what is checked is not that the words arrived
    -- it is that emphasis became <em>, a table became a <table> with scoped
    headers, a note became the tinted box and not an ordinary paragraph, and
    hostile source arrived escaped. A renderer that drew everything the same
    would pass a check that only looked for the text.

    AND THE STRUCTURE IS CHECKED FOR STAYING FLAT. assets/css/pages/legal.css
    zeroes the top margin of the first heading with a child combinator, so a
    per-section wrapper would silently stop it matching. Nothing here may nest
    the sections inside anything but the body.
    """
    print("\nthe privacy policy travels the same road")

    page_url = "/pages/privacy-policy/"

    data = json.loads(PRIVACY.read_text())
    data["revision"] = 70
    data["status"] = "shown"

    data["meta"]["title"] = f"{MARK}-tab"
    data["meta"]["share_title"] = f"{MARK}-share"
    data["meta"]["description"] = f"{MARK}-desc"
    data["meta"]["breadcrumb"] = f"{MARK}-crumb"
    data["hero"]["title"] = f"{MARK}-hero"
    data["hero"]["subtitle"] = f"{MARK}-sub"
    data["policy"]["label"] = f"{MARK}-label"
    data["policy"]["effective"] = "2026-08-21"
    data["cta"]["title"] = f"{MARK}-ctatitle"
    data["cta"]["text"] = f"{MARK}-ctatext"

    data["policy"]["callout"] = {
        "status": "shown",
        "title": f"{MARK}-callouttitle",
        "note": f"{MARK}-calloutnote",
    }

    data["policy"]["body"] = (
        "## First head {#first-one}\n"
        "\n"
        f"{MARK}-para1 *{MARK}-em* ++{MARK}-ul++ "
        f"[{MARK}-fraglink](#second-one)\n"
        "\n"
        f"- {MARK}-bullet1\n"
        f"- {MARK}-bullet2\n"
        "\n"
        ":::note\n"
        f"{MARK}-notetext\n"
        ":::\n"
        "\n"
        ":::center\n"
        f"{MARK}-centered\n"
        ":::\n"
        "\n"
        "## Second head\n"
        "\n"
        f"**{MARK}-captionline**\n"
        "\n"
        f"| {MARK}-col1 | {MARK}-col2 |\n"
        "| --- | --- |\n"
        f"| {MARK}-rowlabel | {MARK}-rowvalue |\n"
        "\n"
        "### Sub head {#sub-head}\n"
        "\n"
        f"{MARK}-subbody\n"
    )

    data["cta"]["items"] = [
        {"id": "b1", "label": f"{MARK}-btn", "href": "/pages/contact/",
         "style": "primary", "status": "shown"},
        {"id": "b2", "label": f"{MARK}-btnhidden", "href": "/x/", "style": "ghost",
         "status": "hidden"},
    ]

    status, answer = publish(base, key, "privacy", data)
    r.check("a validly signed payload is accepted",
            status == 200 and answer.get("ok") is True, f"{status} {answer}")

    status, page = get(base, page_url)
    r.check("the page is served", status == 200, f"status {status}")

    for field in ("tab", "share", "hero", "sub", "label",
                  "callouttitle", "calloutnote",
                  "para1", "em", "ul", "fraglink", "bullet1",
                  "bullet2", "notetext", "centered",
                  "captionline",
                  "col1", "col2", "rowlabel", "rowvalue", "ctatitle",
                  "ctatext", "btn"):
        r.check(f"{field} reaches the visitor", f"{MARK}-{field}" in page)

    for head in ("First head", "Second head", "Sub head"):
        r.check(f"{head} renders as a heading", head in page)

    r.check("the tab title is the tab title", f"<title>{MARK}-tab</title>" in page)
    r.check("and the share title is separate",
            f'property="og:title" content="{MARK}-share"' in page)
    trail = breadcrumb_names(page)
    r.check("the breadcrumb carries its OWN name, not the hero's",
            bool(trail) and trail[-1] == f"{MARK}-crumb",
            f"the trail ends {trail[-1:] or ['(no BreadcrumbList)']}, "
            f"not [{MARK}-crumb]")

    print("\nthe hub furniture is there")

    r.check("the eyebrow names the segment",
            '<p class="legal__eyebrow">Legal</p>' in page)
    r.check("the effective line is printed as a date, not the stored ISO",
            "Effective 21 August 2026" in page)
    r.check("one document means no pills",
            "legal__tabs" not in page,
            "a switch with a single position")
    r.check("the rail lists the headings, numbered",
            '<nav class="legal__toc"' in page
            and 'href="#first-one"' in page
            and 'href="#second-one"' in page
            and 'class="legal__toc-num">01<' in page
            and 'class="legal__toc-num">02<' in page,
            "numbered anchors missing")

    print("\nwhat is hidden is not there at all")

    r.check("a hidden button is absent", f"{MARK}-btnhidden" not in page)

    print("\neach dialect construct is drawn as itself")

    r.check("emphasis is emphasis", f"<em>{MARK}-em</em>" in page)
    r.check("underline is underline", f"<u>{MARK}-ul</u>" in page)
    r.check("a fragment link KEEPS ITS HREF",
            'href="#second-one"' in page,
            "the link would still look like a link and do nothing")
    r.check("a list is a <ul> of <li>",
            "<ul>" in page and f"<li>{MARK}-bullet1</li>" in page)
    r.check("a note is the tinted box and not an ordinary paragraph",
            f'<div class="legal__notice">\n<p>{MARK}-notetext</p>' in page,
            "the note lost its box")
    r.check("a centred block carries the class",
            f'<div class="ta-center">\n<p>{MARK}-centered</p>' in page)
    r.check("a table is a table, in its scroller",
            '<div class="legal__table-wrap"><table class="legal__table">' in page)
    r.check("its column headings are scoped to their column",
            f'<th scope="col">{MARK}-col1</th>' in page)
    r.check("and its cells are cells",
            f"<td>{MARK}-rowlabel</td><td>{MARK}-rowvalue</td>" in page)
    r.check("the caption line is a bold lead-in, not a table caption",
            f"<strong>{MARK}-captionline</strong>" in page
            and "<caption" not in page)

    print("\nthe things that make the stylesheet work")

    r.check("the sections are FLAT children of the body, with no wrapper",
            re.search(r'<div class="legal__body">\s*<div class="legal__callout">', page)
            is not None
            or re.search(r'<div class="legal__body">\s*<h2 class="legal__heading"', page)
            is not None,
            "something was inserted between the body and its content")
    r.check("the first heading is a direct child, so :first-of-type still matches",
            re.search(r'</div>\s*<h2 class="legal__heading" id="first-one">', page)
            is not None,
            "the callout no longer closes immediately before the first heading")
    r.check("the callout's own heading stays INSIDE the callout, not beside it",
            re.search(r'<div class="legal__callout">\s*'
                      r'<h2 class="legal__callout-title">', page) is not None,
            "a flattened callout would steal :first-of-type from the first section")

    print("\nan anchor is a promise")

    r.check("a stated id survives rendering",
            '<h2 class="legal__heading" id="first-one">First head</h2>' in page)
    r.check("and the rail points at the same address",
            '<a href="#first-one">' in page)

    print("\nMarkdown source is stored, and script in it is text")

    data["policy"]["body"] += (
        f'\n\n{MARK}-clean<script>alert(1)</script>\n'
        f'\n'
        f'# {MARK}-octothorpe\n'
        f'\n'
        f'[x](javascript:alert(1))')
    data["revision"] = 71
    publish(base, key, "privacy", data)
    _status, page = get(base, page_url)

    r.check("the words survive", f"{MARK}-clean" in page)
    r.check("the script does not -- escaped text is not markup",
            "<script>alert" not in page and f"{MARK}-clean&lt;script&gt;" in page)
    r.check("a hash is a hash, because headings are fields",
            f"# {MARK}-octothorpe" in page)
    r.check("and a bad-scheme link stays literal",
            "[x](javascript:alert(1))" in page)

    print("\na hidden page is gone, not merely unindexed")

    data["status"] = "hidden"
    data["revision"] = 72
    publish(base, key, "privacy", data)
    status, _page = get(base, page_url)
    r.check("it answers 404", status == 404, f"status {status}")

    data["status"] = "shown"
    data["revision"] = 73
    status, answer = publish(base, key, "privacy", data)
    r.check("and showing it again restores the page",
            status == 200 and answer.get("ok") is True, f"{status} {answer}")
    status, _page = get(base, page_url)
    r.check("back to 200", status == 200, f"status {status}")


def seo_round_trip(base: str, key: bytes, r: Results) -> None:
    """The site-wide record, and the page fields that have never been tested.

    THIS DOCUMENT IS NOT A PAGE, which is what makes it worth a round trip of
    its own. Nothing renders content/seo.json on its own; it is read by every
    page's <head>, by the Organization graph, by /robots.txt and by
    /site.webmanifest. A field that stopped arriving would show up as a missing
    line in seventeen heads at once and in nothing a person looks at.

    THE PAGE FIELDS ARE HERE TOO. meta.breadcrumb, meta.robots, meta.changefreq
    and meta.priority are new on every document, and check_content_model.py
    cannot see them: they are read by lib/head.php in a loop over an array the
    page hands it, not named in any page file. So they are proved by round trip,
    which is what COVERED_ELSEWHERE points at.
    """
    print("\nthe site-wide SEO record travels the same road")

    data = json.loads(SEO.read_text())
    data["revision"] = 80

    data["site"]["name"] = f"{MARK}-sitename"
    data["site"]["lang"] = "en-GB"
    data["site"]["locale"] = f"{MARK}_LOCALE"
    data["site"]["twitter_card"] = "summary"
    data["site"]["theme_light"] = "#fedcba"
    data["site"]["theme_dark"] = "#123456"
    data["site"]["share_alt"] = f"{MARK}-sharealt"
    data["site"]["description"] = f"{MARK}-sitedescription"

    data["identity"]["legal_name"] = f"{MARK}-legalname"
    data["identity"]["alternate_name"] = f"{MARK}-alsoknown"
    data["identity"]["slogan"] = f"{MARK}-slogan"
    data["identity"]["description"] = f"{MARK}-orgdescription"
    data["identity"]["founded"] = "2001-02-03"
    data["identity"]["price_range"] = f"{MARK}-price"
    data["identity"]["area_served"] = f"{MARK}-area"
    data["identity"]["service_types"] = [f"{MARK}-servicetype"]
    data["identity"]["knows_about"] = [f"{MARK}-knowsabout"]

    data["sameas"]["items"] = [
        {"id": "one", "label": "One", "url": f"https://example.com/{MARK}-profile",
         "status": "shown"},
        {"id": "two", "label": "Two", "url": "https://example.com/hidden",
         "status": "hidden"},
    ]
    data["hours"]["items"] = [
        {"id": "bd", "label": "Bangladesh office", "days": ["Monday"],
         "opens": "07:00", "closes": "19:00", "status": "shown"},
    ]

    data["crawl"]["verify_google"] = f"{MARK}-googletoken"
    data["crawl"]["verify_bing"] = f"{MARK}-bingtoken"
    data["crawl"]["analytics_id"] = "G-PUBLISHED1"
    data["crawl"]["robots_extra"] = ["/contact-handler.php", f"/{MARK}-disallowed"]

    data["manifest"]["short_name"] = f"{MARK}-shortname"
    data["manifest"]["display"] = "minimal-ui"
    data["manifest"]["background"] = "#abcdef"
    data["manifest"]["theme"] = "#fedcba"

    data["notfound"]["title"] = f"{MARK}-notfoundtitle"
    data["notfound"]["description"] = f"{MARK}-notfounddescription"

    status, _ = publish(base, key, "seo", data)
    r.check("the site-wide record is accepted", status == 200, f"status {status}")

    _s, page = get(base, "/pages/about/")

    print("  what every page's head now says")
    for what, needle in [
        ("the site name", f'property="og:site_name" content="{MARK}-sitename"'),
        ("the language", 'lang="en-GB"'),
        ("the sharing locale", f'property="og:locale" content="{MARK}_LOCALE"'),
        ("the card shape", 'name="twitter:card" content="summary"'),
        ("the light theme colour", 'content="#fedcba"'),
        ("the dark theme colour", 'content="#123456"'),
        ("the share picture's description", f"{MARK}-sharealt"),
        ("the Google verification tag",
         f'name="google-site-verification" content="{MARK}-googletoken"'),
        ("the Bing verification tag",
         f'name="msvalidate.01" content="{MARK}-bingtoken"'),

        # THE ONE FIELD THAT REACHES ANOTHER COMPANY'S SERVERS. Both halves are
        # named: the loader Google serves, and this site's own configuration
        # file, which exists because their second <script> is inline and
        # script-src 'self' refuses those without a word.
        ("the analytics loader",
         '<script async src="https://www.googletagmanager.com/gtag/js?id=G-PUBLISHED1">'),
        ("and the configuration script, which is this site's own file",
         '<script src="/assets/js/analytics.js?v=1" data-ga="G-PUBLISHED1" defer>'),
        ("and the policy widened to let exactly that through",
         "script-src 'self' https://www.googletagmanager.com"),
    ]:
        r.check(f"  {what}", needle in page, needle)

    print("  and the Organization graph, on a page that is not the contact page")
    graph = next((g for g in json_ld(page) if isinstance(g, dict) and "@graph" in g), None)
    r.check("  the graph is there", graph is not None)
    nodes = {n.get("@type"): n for n in (graph or {}).get("@graph", [])} if graph else {}

    org = nodes.get("Organization", {})
    r.check("  the legal name", org.get("name") == f"{MARK}-sitename", str(org.get("name")))
    r.check("  the also-known-as", org.get("alternateName") == f"{MARK}-alsoknown")
    r.check("  the slogan", org.get("slogan") == f"{MARK}-slogan")
    r.check("  the description", org.get("description") == f"{MARK}-orgdescription")
    r.check("  the founding date", org.get("foundingDate") == "2001-02-03")
    r.check("  a shown profile is listed",
            f"https://example.com/{MARK}-profile" in org.get("sameAs", []))
    r.check("  A HIDDEN PROFILE IS NOT",
            "https://example.com/hidden" not in org.get("sameAs", []),
            "hiding a row has to mean it is not published")

    r.check("  THE ADDRESSES STILL COME FROM THE CONTACT DOCUMENT",
            len(org.get("address", [])) > 0 and MARK not in json.dumps(org.get("address")),
            "the offices belong to content/contact.json and must not be in this one")

    svc = nodes.get("ProfessionalService", {})
    r.check("  the price range", svc.get("priceRange") == f"{MARK}-price")
    r.check("  the services offered", svc.get("serviceType") == [f"{MARK}-servicetype"])
    r.check("  the subjects known", svc.get("knowsAbout") == [f"{MARK}-knowsabout"])
    hours = svc.get("openingHoursSpecification", [])
    r.check("  the opening hours",
            hours and hours[0].get("opens") == "07:00" and hours[0].get("dayOfWeek") == ["Monday"],
            str(hours))

    print("  one LocalBusiness per office, which the site has never had")
    offices = [n for n in (graph or {}).get("@graph", [])
               if isinstance(n, dict) and n.get("@type") == "LocalBusiness"]
    r.check("  there is one per shown office", len(offices) == 3, str(len(offices)))
    r.check("  each has an address of its own",
            all(isinstance(o.get("address"), dict) for o in offices))
    r.check("  each says which organisation it belongs to",
            all(o.get("parentOrganization", {}).get("@id", "").endswith("#organization")
                for o in offices))
    bd = next((o for o in offices if "bangladesh" in o.get("@id", "")), {})
    r.check("  and the hours row named after an office reaches that office",
            bd.get("openingHoursSpecification", [{}])[0].get("opens") == "07:00",
            str(bd.get("openingHoursSpecification")))

    # THE SHARE CARD, NOT THE OFFICE'S OWN PICTURE. That field is a flag,
    # stored at 56px because 56px is where it is drawn; offering it to a search
    # engine as the photograph of a place of business would be worse than
    # offering nothing. Organization uses the card too, so the two agree.
    r.check("  each carries an image, and it is the site's share card",
            all(o.get("image", "").endswith("/assets/images/og/tech4time-og.png")
                for o in offices),
            str([o.get("image") for o in offices]))

    offices_geo(base, key, r)

    print("  the three generated files")
    _s, robots = get(base, "/robots.txt")
    r.check(f"  robots.txt carries the extra rule", f"/{MARK}-disallowed" in robots)
    r.check("  and still allows the whole site", "Allow: /" in robots)

    _s, manifest = get(base, "/site.webmanifest")
    app = json.loads(manifest)
    r.check("  the manifest takes its name from the site band",
            app["name"] == f"{MARK}-sitename")
    r.check("  its short name from its own", app["short_name"] == f"{MARK}-shortname")
    r.check("  and its display mode", app["display"] == "minimal-ui")

    # FOUR PAGE-LEVEL GRAPHS THAT USED TO CARRY THEIR OWN COPY OF THE NAME.
    # The site name has been editable since the SEO screen shipped, and these
    # four said 'Tech4TIME' in PHP -- so renaming the company left four schema
    # blocks contradicting the Organization graph on the same pages. The
    # founding date was the same fault: lib/company.php held a constant while
    # lib/head.php read identity.founded, so one page carried two graphs that
    # disagreed the moment anybody touched the field.
    print("  and the page-level graphs, which used to hard-code the name")
    for what, path, needle in (
            ("the contact page's ContactPage", "/pages/contact/", "ContactPage"),
            ("the company profile's AboutPage", "/pages/company-profile/", "AboutPage"),
            ("a service page's Service", "/pages/services/cybersecurity/", "Service"),
            ("the careers page's JobPosting", "/pages/careers/", "JobPosting")):
        _s, page = get(base, path)
        node = next((g for g in json_ld(page)
                     if isinstance(g, dict) and g.get("@type") == needle), None)
        r.check(f"  {what} is there", node is not None, f"no {needle} node")
        if node is None:
            continue
        blob = json.dumps(node)
        r.check(f"  {what} names the company the site band names",
                f"{MARK}-sitename" in blob and "Tech4TIME" not in blob,
                blob[:220])

    _s, company = get(base, "/pages/company-profile/")
    about_node = next((g for g in json_ld(company)
                       if isinstance(g, dict) and g.get("@type") == "AboutPage"), None)
    r.check("  and the founding date it publishes is the editable one",
            (about_node or {}).get("about", {}).get("foundingDate")
            == json.loads(SEO.read_text())["identity"]["founded"],
            str((about_node or {}).get("about"))[:200])

    print("  the error page, whose record is in this document")
    _s, notfound = get(base, "/404.php")
    r.check(f"  its title arrives", f"<title>{MARK}-notfoundtitle</title>" in notfound)
    r.check("  it is noindex", 'name="robots" content="noindex, follow"' in notfound)
    r.check("  AND IT HAS NO CANONICAL", "rel=\"canonical\"" not in notfound,
            "a page served at every address that does not exist has no address "
            "of its own to claim")

    print("\nthe meta fields every document gained")

    about = json.loads(ABOUT.read_text())
    about["revision"] = 81
    about["meta"]["breadcrumb"] = f"{MARK}-crumb"
    about["meta"]["changefreq"] = "hourly"
    about["meta"]["priority"] = "0.2"
    about["updated"] = "2031-07-09T10:11:12+00:00"
    status, _ = publish(base, key, "about", about)
    r.check("a page document with the new fields is accepted", status == 200)

    _s, page = get(base, "/pages/about/")
    crumbs = next((b for b in json_ld(page)
                   if isinstance(b, dict) and b.get("@type") == "BreadcrumbList"), None)
    r.check("the breadcrumb reaches the trail",
            crumbs and crumbs["itemListElement"][-1]["name"] == f"{MARK}-crumb",
            str(crumbs))

    webpage = next((b for b in json_ld(page)
                    if isinstance(b, dict) and b.get("@type") == "WebPage"), None)
    r.check("the page has a WebPage node, which it never had", webpage is not None)
    r.check("it names this address", webpage and webpage["url"].endswith("/pages/about/"))
    r.check("it says which site it is part of",
            webpage and webpage["isPartOf"]["@id"].endswith("#website"))
    r.check("AND IT CARRIES THE DAY THE PAGE CHANGED",
            webpage and webpage.get("dateModified") == "2031-07-09",
            "every document has carried an updated stamp and no page emitted it")
    r.check("which is also an Open Graph tag",
            'property="og:updated_time" content="2031-07-09"' in page)

    _s, sitemap = get(base, "/sitemap.xml")
    r.check("the sitemap takes its change frequency from the page",
            "<changefreq>hourly</changefreq>" in sitemap)
    r.check("and its priority", "<priority>0.2</priority>" in sitemap)
    r.check("and its date", "<lastmod>2031-07-09</lastmod>" in sitemap)

    about["revision"] = 82
    about["meta"]["robots"] = "noindex"
    publish(base, key, "about", about)
    _s, sitemap = get(base, "/sitemap.xml")
    r.check("A PAGE SET TO NOINDEX LEAVES THE SITEMAP",
            "https://tech4time.bd/pages/about/" not in sitemap,
            "membership is derived from robots, so the two cannot disagree")
    _s, page = get(base, "/pages/about/")
    r.check("and says so in its own head",
            'name="robots" content="noindex, follow"' in page)

def offices_geo(base: str, key: bytes, r: Results) -> None:
    """A pin on an office, and every way one is not a pin.

    Coordinates are the one pair in a contact document that is checked rather
    than trimmed. Every other field there is a line of an address: whatever
    somebody types is what that place is called. A latitude is a number with a
    range that no person ever reads, printed into a graph a search engine acts
    on -- so "near the airport" in one does not degrade to a vaguer pin, it is
    a broken property in a graph that was otherwise fine.
    """
    print("  and an office's coordinates, which are checked rather than trimmed")

    def publish_offices(schema_extra: dict) -> list:
        data = json.loads(CONTACT.read_text())
        data["revision"] = data.get("revision", 0) + 1
        data["offices"]["items"][0]["schema"].update(schema_extra)
        status, _ = publish(base, key, "contact", data)
        if status != 200:
            return []
        _s, page = get(base, "/pages/contact/")
        graph = next((g for g in json_ld(page)
                      if isinstance(g, dict) and "@graph" in g), None)
        return [n for n in (graph or {}).get("@graph", [])
                if isinstance(n, dict) and n.get("@type") == "LocalBusiness"]

    got = publish_offices({"latitude": "23.8103", "longitude": "90.4125"})
    geo = got[0].get("geo", {}) if got else {}
    r.check("  a real pair becomes a GeoCoordinates node",
            geo.get("@type") == "GeoCoordinates"
            and geo.get("latitude") == "23.8103"
            and geo.get("longitude") == "90.4125", str(geo))

    # BOTH OR NEITHER. One number is not half a pin; it is a pin somewhere on a
    # line through the middle of the planet.
    got = publish_offices({"latitude": "23.8103", "longitude": ""})
    r.check("  half a pair is dropped entirely rather than published as far as it goes",
            "geo" not in (got[0] if got else {}), str(got[0].get("geo") if got else None))

    for what, lat, lon in (("out of range", "90.1", "90.4125"),
                           ("not a number", "near the airport", "90.4125"),
                           ("scientific notation", "1e5", "90.4125")):
        got = publish_offices({"latitude": lat, "longitude": lon})
        r.check(f"  a latitude that is {what} never reaches the graph",
                "geo" not in (got[0] if got else {}),
                str(got[0].get("geo") if got else None))

    got = publish_offices({"latitude": "", "longitude": ""})
    r.check("  and an office with no pin simply has no geo",
            got and "geo" not in got[0], str(got[0].get("geo") if got else None))

    # THE IMAGE IS SET ONLY WHEN THERE IS ONE, and this is the case that proves
    # it: the shipped document always has a share card, so a node that emitted
    # the key unconditionally looked correct in every other check here. An
    # operator can clear that card, and "image": "" is a property claiming a
    # picture exists and naming none -- worse than the property being absent.
    seo = json.loads(SEO.read_text())
    held = json.loads(json.dumps(seo["site"]["share"]))

    seo["revision"] = seo.get("revision", 0) + 1
    seo["site"]["share"] = {"src": "", "webp": "", "width": 0, "height": 0}
    status, _ = publish(base, key, "seo", seo)
    r.check("  a seo document with no share card is accepted", status == 200,
            f"status {status}")

    _s, page = get(base, "/pages/contact/")
    graph = next((g for g in json_ld(page)
                  if isinstance(g, dict) and "@graph" in g), None)
    without = [n for n in (graph or {}).get("@graph", [])
               if isinstance(n, dict) and n.get("@type") == "LocalBusiness"]
    r.check("  and then an office has NO image key at all, not an empty one",
            without and all("image" not in o for o in without),
            str([o.get("image", "(absent)") for o in without]))

    seo["revision"] += 1
    seo["site"]["share"] = held
    publish(base, key, "seo", seo)


def settings_round_trip(base: str, key: bytes, r: Results) -> None:
    """The site's identity travels the same road.

    NOT A PAGE EITHER, and further from one than the chrome: nothing here is
    words on a screen. It is a mark, a set of icons, twenty-eight colours and
    the address the contact form posts to — every one of which is read by
    several pages and owned by none.

    THIS DOCUMENT IS THE ONE WHERE RE-NORMALISING ON RECEIPT EARNS ITS KEEP.
    Its values do not become text on a page; they become an <img src>, a
    <link rel="icon">, a CSS declaration and the To: line of an email. A
    signature proves who sent a document and says nothing about what is inside
    it, so each of those is given something hostile below and has to come back
    safe — from THIS side, which is the side that would be serving it.

    The road is proved first -- the name has a home, the bands survive it, and
    the poison does not -- and then the walk: a legitimate document is published
    and every band is looked for where a visitor would meet it. That second half
    exists because every renderer now reads this document, and the failure it
    guards against is the one this whole document was built to end: a mark
    changing in one of the nine places it appears and not in the other eight.
    """
    print("\nthe site's identity travels the same road")

    data = json.loads(SETTINGS.read_text()) if SETTINGS.is_file() else {}
    data["revision"] = 90

    # A mark on somebody else's server, and a ladder with one good rung and one
    # that is not a path this site will ever serve.
    data["logo"] = {
        "light": {
            "src": "https://evil.example/logo.png",
            "webp": "/assets/images/logo/logo-light-360.webp",
            "width": 360, "height": 128,
            "srcset": "/assets/images/logo/logo-light-180.png 180w, "
                      "https://evil.example/logo.png 540w",
            "webp_srcset": "",
        },
        # Cleared on purpose. It must STAY cleared: filling it back in from the
        # defaults would put the shipped lockup underneath somebody else's mark.
        "dark": {"src": "", "webp": "", "width": 0, "height": 0,
                 "srcset": "", "webp_srcset": ""},
    }

    data["icon"] = {
        "master": {"src": "/uploads/00112233aabbccdd.png", "webp": "",
                   "width": 512, "height": 512, "srcset": "", "webp_srcset": ""},
        "generated": {"png96": "/uploads/44556677eeff0011.png",
                      # Not under a root this site serves from.
                      "png16": "../../etc/passwd",
                      "png32": "/uploads/8899aabbccddeeff.png"},
    }

    # Six hex digits, and two things that are not: one that would close the
    # declaration and start a new rule, and one that is simply not a colour.
    data["colours"] = {
        "light": {"bg-base": "#AABBCC",
                  "text-primary": "red; } body { display: none"},
        "dark": {"accent-text": "#123456", "focus-ring": "chartreuse"},
    }

    data["contact"] = {"mail_to": "attacker@evil.example\nBcc: everyone@evil.example",
                       "mail_subject": f"{MARK}-subject"}

    status, _ = publish(base, key, "settings", data)
    r.check("the settings document is accepted", status == 200, f"status {status}")

    stored = json.loads(SETTINGS.read_text())

    print("  what arrived is what this host will serve")
    r.check("a logo on another origin is dropped",
            stored["logo"]["light"]["src"] == "", str(stored["logo"]["light"])[:200])
    r.check("and the one good rung of its ladder is kept, the other dropped",
            stored["logo"]["light"]["srcset"]
            == "/assets/images/logo/logo-light-180.png 180w",
            stored["logo"]["light"]["srcset"])
    r.check("a dark half cleared on purpose stays cleared",
            stored["logo"]["dark"]["src"] == "", str(stored["logo"]["dark"])[:200])

    r.check("the icon master arrives", stored["icon"]["master"]["src"]
            == "/uploads/00112233aabbccdd.png", str(stored["icon"]["master"])[:160])
    r.check("a generated icon outside the served roots is dropped",
            stored["icon"]["generated"]["png16"] == "",
            str(stored["icon"]["generated"])[:200])
    r.check("and the ones inside them are kept",
            stored["icon"]["generated"]["png96"] == "/uploads/44556677eeff0011.png"
            and stored["icon"]["generated"]["png32"] == "/uploads/8899aabbccddeeff.png",
            str(stored["icon"]["generated"])[:200])
    # No 'ico' among them: the asset channel carries what
    # getimagesizefromstring() recognises and an .ico is not among them, so the
    # public site assembles that one itself at /favicon.ico.
    r.check("every icon slot the shape declares is present, and no .ico",
            set(stored["icon"]["generated"]) == {"png16", "png32", "png48",
                                                 "png96", "png192", "png512", "apple"},
            str(sorted(stored["icon"]["generated"])))

    r.check("a real colour arrives, lower-cased",
            stored["colours"]["light"]["bg-base"] == "#aabbcc",
            str(stored["colours"]["light"])[:160])
    # This one ends up inside a generated stylesheet. "red; } body { display:
    # none" is a valid CSS value right up until the moment it is not.
    r.check("one that would close the declaration is refused for the shipped one",
            stored["colours"]["light"]["text-primary"] == "#111113",
            str(stored["colours"]["light"])[:200])
    r.check("a colour name rather than a hex value is refused too",
            stored["colours"]["dark"]["focus-ring"] == "#b8babe",
            str(stored["colours"]["dark"])[:200])
    r.check("and every token is present whether it was sent or not",
            len(stored["colours"]["light"]) == len(stored["colours"]["dark"]) == 14,
            f'{len(stored["colours"]["light"])} light, {len(stored["colours"]["dark"])} dark')

    # An address with a newline in it is header injection into the mail the
    # contact form sends, which is the one field here that becomes SMTP.
    r.check("a mail address with a header break in it is refused",
            stored["contact"]["mail_to"] == "info@tech4time.bd",
            repr(stored["contact"]["mail_to"]))
    r.check("and the subject line arrives", stored["contact"]["mail_subject"]
            == f"{MARK}-subject", stored["contact"]["mail_subject"])

    settings_walk(base, key, r)


def settings_walk(base: str, key: bytes, r: Results) -> None:
    """A marker in every band, looked for where a visitor meets it.

    ONE MARK, NINE PLACES. The header, the footer, the About page's logo row,
    Organization.logo, JobPosting.hiringOrganization.logo, the browser tab, the
    web manifest, the stylesheet and the address the enquiry form posts to were
    nine independent copies of the company's identity before this document
    existed, and the SEO screen's logo upload had already drifted from the
    header's with nothing comparing them. So the check is not "the logo
    renders"; it is that ONE publish moves ALL of them, which is only provable
    by publishing one and reading all of them.

    Each band gets a marker no other band could have produced, so a failure
    names itself rather than saying a page changed.
    """
    print("  and a visitor meets every band of it")

    logo = f"/uploads/{MARK}-logo"
    icon = f"/uploads/{MARK}-icon"

    data = json.loads(SETTINGS.read_text())
    data["revision"] = 91
    data["logo"] = {
        "light": {
            "src": f"{logo}-180.png", "webp": "",
            "width": 180, "height": 64,
            "srcset": f"{logo}-180.png 180w, {logo}-540.png 540w",
            "webp_srcset": "",
        },
        "dark": {"src": "", "webp": "", "width": 0, "height": 0,
                 "srcset": "", "webp_srcset": ""},
    }
    data["icon"] = {
        "master": {"src": f"{icon}-512.png", "webp": "", "width": 512,
                   "height": 512, "srcset": "", "webp_srcset": ""},
        "generated": {name: f"{icon}-{name}.png" for name in
                      ("png16", "png32", "png48", "png96",
                       "png192", "png512", "apple")},
    }
    # A token nothing else on the site could produce, so finding it in the
    # stylesheet cannot be a coincidence.
    data["colours"]["light"]["accent-text"] = "#a1b2c3"
    data["contact"]["mail_to"] = f"{MARK}-walk@tech4time.bd"

    status, _ = publish(base, key, "settings", data)
    r.check("a legitimate settings document is accepted", status == 200,
            f"status {status}")

    _, home = get(base, "/")
    _, about = get(base, "/pages/about/")
    _, careers = get(base, "/pages/careers/")

    # THREE LOCKUPS, LOOKED FOR ONE AT A TIME AND BY CLASS. Counting the
    # marker in the whole page proves nothing: the header alone names it twice
    # (a <source srcset> and an <img src>), so a footer that had stopped
    # reading the document would still leave the count satisfied -- and the
    # About page carries the header and the footer too, so "the marker is on
    # the About page" is true even when the row itself is stale. Each of the
    # three is read out of its own element.
    head_marks = re.findall(r'<img\s+class="site-header__logo".*?>', home, re.S)
    foot_marks = re.findall(r'<img class="site-footer__logo".*?>', home, re.S)
    about_marks = re.findall(r'<img class="about-split__image about-split__image--contain".*?>',
                             about, re.S)

    r.check("the HEADER's lockup is drawn from the document",
            len(head_marks) == 2 and all(f"{logo}-" in tag for tag in head_marks),
            f"{len(head_marks)} header marks: " + str(head_marks)[:200])
    r.check("the FOOTER's is too, and separately",
            len(foot_marks) == 2 and all(f"{logo}-" in tag for tag in foot_marks),
            f"{len(foot_marks)} footer marks: " + str(foot_marks)[:200])
    r.check("and the About page's logo ROW, which is not the chrome",
            len(about_marks) == 2 and all(f"{logo}-" in tag for tag in about_marks),
            f"{len(about_marks)} About marks: " + str(about_marks)[:200])
    r.check("at the widths the ladder declares, not one file",
            f"{logo}-540.png 540w" in home, "the ladder did not arrive")

    # Two graphs, one company. These disagreed before this document existed:
    # Organization.logo named the 540 and JobPosting named the 360.
    graph = next((g for g in json_ld(home)
                  if isinstance(g, dict) and "@graph" in g), None)
    nodes = {n.get("@type"): n for n in (graph or {}).get("@graph", [])}
    org = nodes.get("Organization", {})
    r.check("Organization.logo is the published mark",
            f"{logo}-" in str(org.get("logo", "")), str(org.get("logo"))[:200])

    hiring = re.findall(r'"hiringOrganization"\s*:\s*\{.*?\}', careers, re.S)
    r.check("and the job post names the same file, not a second one",
            hiring and all(f"{logo}-" in node for node in hiring),
            str(hiring)[:200])

    # The tab, and the thing an installed web app uses.
    r.check("the browser tab's icons are the published ones",
            f"{icon}-png32.png" in home and f"{icon}-apple.png" in home,
            "the head kept the shipped icons")
    _, manifest = get(base, "/site.webmanifest")
    r.check("and so are the manifest's",
            f"{icon}-png192.png" in manifest and f"{icon}-png512.png" in manifest,
            manifest[:200])

    # The colour reaches a stylesheet, which is the only route a colour has:
    # the CSP forbids a style attribute, so there is no other way for it to be
    # on the page at all.
    _, brand = get(base, "/assets/css/brand.css")
    r.check("the published colour reaches the generated stylesheet",
            "#a1b2c3" in brand, brand[:200])
    r.check("and the page asks for that stylesheet",
            "/assets/css/brand.css" in home, "the link is not in the head")

    # THE ONE BAND THAT MUST NOT APPEAR. Where the enquiry form sends is read
    # by the handler and belongs in no page; an address in the markup is an
    # address a harvester has.
    r.check("where enquiries go is NOWHERE in the markup",
            all(f"{MARK}-walk@tech4time.bd" not in page
                for page in (home, about, careers)),
            "the destination address was rendered into a page")


def chrome_round_trip(base: str, key: bytes, r: Results) -> None:
    """The header, footer and dock travel the same road.

    THIS DOCUMENT IS NOT A PAGE. It is on EVERY page, which makes the last
    group below the important one: a marker published into this document has
    to come back out of an ordinary page, or the whole conversion in ADR 0023
    is unproved.

    Before that, the contract half — that every band survives the endpoint, and
    that normalising happens on the RECEIVING side and not only on the sending
    one. That is the reason to test it at this end at all: api/publish.php
    re-normalises what it was sent, so a document written by a compromised or
    simply older backend still arrives in the shape lib/body.php assumes. A bar
    with six keys, a contact row of an invented kind and a logo pointing at
    another origin are all things a signature would happily carry.
    """
    print("\nthe chrome travels the same road")

    data = json.loads(CHROME.read_text())
    data["revision"] = 90

    data["header"]["brand_label"] = f"{MARK}-brandlabel"
    data["header"]["logo"]["alt"] = f"{MARK}-logoalt"
    data["header"]["nav"]["items"] = [
        {"id": "home", "target": "home", "label": f"{MARK}-navhome", "status": "shown"},
        {"id": "about", "target": "about", "label": f"{MARK}-navhidden", "status": "hidden"},
    ]

    data["footer"]["tagline"] = f"{MARK}-tagline"
    data["footer"]["description"] = f"{MARK}-description"
    data["footer"]["links"]["heading"] = f"{MARK}-linksheading"
    data["footer"]["services"]["heading"] = f"{MARK}-servicesheading"
    data["footer"]["services"]["index_label"] = f"{MARK}-indexlabel"
    data["footer"]["contact"]["heading"] = f"{MARK}-contactheading"
    data["footer"]["copyright"]["name"] = f"{MARK}-copyname"
    data["footer"]["copyright"]["rights"] = f"{MARK}-rights"
    data["footer"]["legal"]["items"] = [
        {"id": "privacy", "target": "privacy", "label": f"{MARK}-legal", "status": "shown"},
    ]
    data["footer"]["contact"]["items"] = [
        {"id": "phone-bangladesh", "kind": "phone", "label": f"{MARK}-office",
         "lines": [f"{MARK}-number", "", "  "], "note": f"{MARK}-note", "status": "shown"},
        # A kind nothing offers. It must come back as the safe default rather
        # than reaching a renderer that would look up an icon for it.
        {"id": "odd", "kind": "carrier-pigeon", "label": "Odd",
         "lines": ["nowhere"], "note": "", "status": "shown"},
    ]

    data["dock"]["menu_label"] = f"{MARK}-menu"
    data["dock"]["panel"]["items"] = [
        {"id": "home", "target": "home", "label": "", "description": f"{MARK}-panel",
         "status": "shown"},
    ]
    # Six keys for a four-key grid, and an icon the picker does not offer.
    data["dock"]["bar"]["items"] = [
        {"id": f"k{i}", "target": "home", "label": f"{MARK}-bar{i}",
         "icon": "home" if i < 5 else "not-an-icon", "emphasis": "plain"}
        for i in range(6)
    ]

    status, _ = publish(base, key, "chrome", data)
    r.check("the chrome document is accepted", status == 200, f"status {status}")

    stored = json.loads(CHROME.read_text())

    print("  every band arrived")
    for what, got in [
        ("the header's brand label", stored["header"]["brand_label"]),
        ("the logo's alt text", stored["header"]["logo"]["alt"]),
        ("a nav row's label", stored["header"]["nav"]["items"][0]["label"]),
        ("the footer's tagline", stored["footer"]["tagline"]),
        ("the footer's description", stored["footer"]["description"]),
        ("the quick-links heading", stored["footer"]["links"]["heading"]),
        ("the services heading", stored["footer"]["services"]["heading"]),
        ("the services index label", stored["footer"]["services"]["index_label"]),
        ("the contact heading", stored["footer"]["contact"]["heading"]),
        ("a contact row's label", stored["footer"]["contact"]["items"][0]["label"]),
        ("a contact row's note", stored["footer"]["contact"]["items"][0]["note"]),
        ("a contact line", stored["footer"]["contact"]["items"][0]["lines"][0]),
        ("the copyright name", stored["footer"]["copyright"]["name"]),
        ("the rights sentence", stored["footer"]["copyright"]["rights"]),
        ("a legal row's label", stored["footer"]["legal"]["items"][0]["label"]),
        ("a dock panel description", stored["dock"]["panel"]["items"][0]["description"]),
        ("the dock's menu label", stored["dock"]["menu_label"]),
        ("a dock bar label", stored["dock"]["bar"]["items"][0]["label"]),
    ]:
        r.check(f"  {what}", MARK in str(got), repr(got))

    print("  and the receiving side normalised it, rather than trusting it")
    r.check("  a hidden nav row is still hidden",
            stored["header"]["nav"]["items"][1]["status"] == "hidden")
    r.check("  the bar is cut back to four keys",
            len(stored["dock"]["bar"]["items"]) == 4,
            f'{len(stored["dock"]["bar"]["items"])} keys')
    r.check("  an icon the picker does not offer is dropped",
            all(row["icon"] in ("", "home") for row in stored["dock"]["bar"]["items"]))
    r.check("  a contact kind nothing offers falls back to a known one",
            stored["footer"]["contact"]["items"][1]["kind"] == "phone",
            stored["footer"]["contact"]["items"][1]["kind"])
    r.check("  empty contact lines are dropped",
            stored["footer"]["contact"]["items"][0]["lines"] == [f"{MARK}-number"],
            repr(stored["footer"]["contact"]["items"][0]["lines"]))
    # THE PICTURE IS NOT IN THIS DOCUMENT ANY MORE. It used to be, and the two
    # checks here refused a logo on another origin and kept only the good half
    # of a srcset. The mark moved to content/settings.json, read by the nine
    # places that draw it, so both refusals moved with it — settings_round_trip()
    # above sends exactly the same poison and asserts exactly the same outcome.
    # What the chrome still says about the logo is the words:
    r.check("  the chrome keeps the alt text and nothing else about the mark",
            set(stored["header"]["logo"]) == {"alt"},
            str(sorted(stored["header"]["logo"])))
    r.check("  the services column still stores no rows of its own",
            "items" not in stored["footer"]["services"])

    print("  a row keeps the id it was given")
    r.check("  the nav row is still 'home'",
            stored["header"]["nav"]["items"][0]["id"] == "home",
            stored["header"]["nav"]["items"][0]["id"])
    r.check("  the contact row is still 'phone-bangladesh'",
            stored["footer"]["contact"]["items"][0]["id"] == "phone-bangladesh",
            stored["footer"]["contact"]["items"][0]["id"])

    # AND IT REACHES A PAGE. Everything above proves the document landed in the
    # right shape; this proves lib/body.php renders THAT document rather than
    # anything of its own. The about page is picked because it is an ordinary
    # page with no relationship to the chrome — if the marker is in its header,
    # its footer and its dock, it is in all seventeen.
    print("  and a visitor sees it, on a page that knows nothing about it")
    _, page = get(base, "/pages/about/")

    for what, mark in [
        ("the header's nav link", f"{MARK}-navhome"),
        ("the footer's tagline", f"{MARK}-tagline"),
        ("the footer's quick-links heading", f"{MARK}-linksheading"),
        ("the footer's contact note", f"{MARK}-note"),
        ("the copyright name", f"{MARK}-copyname"),
        ("the dock panel's description", f"{MARK}-panel"),
        ("a dock bar label", f"{MARK}-bar0"),
        ("the menu button's label", f"{MARK}-menu"),
    ]:
        r.check(f"  {what}", mark in page, "not in the rendered page")

    r.check("  a hidden nav row is absent from the page",
            f"{MARK}-navhidden" not in page,
            "a row marked hidden was rendered anyway")

    # The services column stores nothing, so it must be reading the services
    # document -- not the chrome one, and not a copy.
    r.check("  the services column is read from content/services.json",
            "/pages/services/cybersecurity/" in page and f"{MARK}-indexlabel" in page)

    # The one per-page difference, and the defect this conversion fixed: the
    # brand link must NOT be marked, on the one page where it used to be.
    _, home = get(base, "/")
    brand = re.search(r'<a class="site-header__brand"[^>]*>', home)
    r.check("  and the brand link on the home page carries no aria-current",
            brand is not None and "aria-current" not in brand.group(0),
            brand.group(0) if brand else "no brand link found")


def home_round_trip(base: str, key: bytes, r: Results) -> None:
    """Every field the home model declares, set and then read off the page.

    THIS IS WHAT check_content_model.py POINTS AT, for the same reason
    about_round_trip() is, only more so: the home page has SIX lists and the
    renderer walks every one of them with foreach, so a regex over the page
    finds loops rather than fields. Put a distinguishable value in every field,
    publish it, and look for it in the HTML a visitor would get.
    """
    print("\nthe home page travels the same road")

    data = json.loads(HOME.read_text())
    data["revision"] = 3

    data["meta"]["title"] = f"{MARK}-tab"
    data["meta"]["description"] = f"{MARK}-desc"
    data["meta"]["share_title"] = f"{MARK}-share"
    data["hero"]["title"] = f"{MARK}-hero with {MARK}-accent inside"
    data["hero"]["accent"] = f"{MARK}-accent"
    data["hero"]["cta_label"] = f"{MARK}-hero-btn"
    data["hero"]["cta_href"] = "/pages/services/"
    data["terminal"]["title"] = f"{MARK}-term-title"
    data["terminal"]["summary"] = f"{MARK}-term-summary"
    data["capabilities"]["title"] = f"{MARK}-cap-title"
    data["capabilities"]["lead"] = f"{MARK}-cap-lead"
    data["services"]["eyebrow"] = f"{MARK}-svc-eyebrow"
    data["services"]["title"] = f"{MARK}-svc-title"
    data["services"]["lead"] = f"{MARK}-svc-lead"
    data["services"]["schema_name"] = f"{MARK}-svc-schema"
    data["services"]["schema_description"] = f"{MARK}-svc-schema-desc"
    data["destinations"]["eyebrow"] = f"{MARK}-dst-eyebrow"
    data["destinations"]["title"] = f"{MARK}-dst-title"
    data["destinations"]["lead"] = f"{MARK}-dst-lead"
    data["cta"]["icon"] = "rocket"
    data["cta"]["title"] = f"{MARK}-cta-one\n{MARK}-cta-two"
    data["cta"]["text"] = f"{MARK}-cta-text"
    data["cta"]["label"] = f"{MARK}-cta-label"
    data["cta"]["href"] = "/pages/contact/"

    data["badges"]["items"] = [{
        "id": "mark-badge", "icon": "shield-alt", "label": f"{MARK}-badge",
        "status": "shown"}]
    data["tags"]["items"] = [{
        "id": "mark-tag", "icon": "bug", "label": f"{MARK}-tag", "status": "shown"}]
    # One of each kind and tone, so every branch of home_terminal_lines() runs.
    data["terminal"]["items"] = [
        {"id": "mark-cmd", "kind": "command", "tone": "plain",
         "prompt": f"{MARK}-prompt$", "text": f"{MARK}-command", "status": "shown"},
        {"id": "mark-ok", "kind": "output", "tone": "success",
         "prompt": "", "text": f"{MARK}-success", "status": "shown"},
        {"id": "mark-bad", "kind": "output", "tone": "alert",
         "prompt": "", "text": f"{MARK}-alert", "status": "shown"},
    ]
    data["capabilities"]["items"] = [{
        "id": "mark-cap", "icon": "sitemap", "title": f"{MARK}-cap-row",
        "status": "shown"}]
    data["services"]["items"] = [{
        "id": "mark-svc", "icon": "laptop-code", "title": f"{MARK}-svc-row",
        "text": f"{MARK}-svc-text", "href": "/pages/services/cybersecurity/",
        "label": f"{MARK}-svc-label", "link_hint": f"{MARK}-svc-hint",
        "status": "shown"}]
    data["destinations"]["items"] = [{
        "id": "mark-dst", "title": f"{MARK}-dst-row", "text": f"{MARK}-dst-text",
        "href": "/pages/about/", "label": f"{MARK}-dst-label",
        "link_hint": f"{MARK}-dst-hint", "alt": f"{MARK}-dst-alt",
        "status": "shown",
        "image": {"src": "/assets/images/pages/about-us.jpg",
                  "webp": "/assets/images/pages/about-us.webp",
                  "width": 800, "height": 658},
        "image_dark": {"src": "", "webp": "", "width": 0, "height": 0}}]

    status, answer = publish(base, key, "home", data)
    r.check("the home page publishes", status == 200 and answer.get("ok") is True,
            f"{status} {answer}")

    _, page = get(base, "/")

    missing = [k for k in (
        "tab", "desc", "share", "hero", "accent", "hero-btn",
        "term-title", "term-summary", "command", "success", "alert", "prompt",
        "cap-title", "cap-lead", "cap-row",
        "svc-eyebrow", "svc-title", "svc-lead", "svc-row", "svc-text",
        "svc-label", "svc-hint", "svc-schema", "svc-schema-desc",
        "dst-eyebrow", "dst-title", "dst-lead", "dst-row", "dst-text",
        "dst-label", "dst-hint", "dst-alt",
        "badge", "tag",
        "cta-one", "cta-two", "cta-text", "cta-label",
    ) if f"{MARK}-{k}" not in page]
    r.check("every field the model declares reaches the page",
            not missing, "never rendered: " + ", ".join(missing))

    r.check("the highlighted phrase is wrapped where it appears",
            f'<span class="hero__accent">{MARK}-accent</span>' in page,
            "home_hero_title() marks the first occurrence and nothing else")
    r.check("and the rest of the heading is not",
            page.count(f'{MARK}-accent') == 1,
            "the accent must be wrapped once, not repeated")
    r.check("the closing heading keeps its line break as a <br>",
            f"{MARK}-cta-one<br>{MARK}-cta-two" in page,
            "a newline in the field is the break on the page")
    r.check("a command line carries its prompt and is typed",
            f'<span class="terminal__prompt">{MARK}-prompt$</span>'
            f'<span class="terminal__command">{MARK}-command</span>' in page)
    r.check("an output line carries its tone",
            f'class="terminal__line terminal__output terminal__output--success">'
            f'{MARK}-success' in page
            and f'terminal__output--alert">{MARK}-alert' in page)
    r.check("the caret is emitted after the last line, not stored as one",
            page.count('class="terminal__cursor"') == 1,
            "an operator must not be able to delete it or end up with two")
    r.check("and it follows the last command's prompt",
            f'<span class="terminal__prompt">{MARK}-prompt$</span>'
            '<span class="terminal__cursor"></span>' in page)
    r.check("terminal lines are emitted at column 0",
            f'\n<div class="terminal__line' in page,
            "pre-wrap renders source indentation as leading spaces on the page")
    r.check("a destination card gets a <picture> and its size",
            '<source srcset="/assets/images/pages/about-us.webp"' in page
            and 'width="800" height="658"' in page)
    r.check("with no dark half it draws ONE picture and no theme-swap",
            page.count('class="destination-card__media"') == 1
            and "destination-card__media--dark" not in page,
            "the page must not grow a second picture per card for an unused feature")
    r.check("the icons chosen at run time are drawn",
            all(f'<use href="#{i}">' in page
                for i in ("shield-alt", "bug", "sitemap", "laptop-code", "rocket")))
    r.check("the screen-reader tail follows the link label",
            f'<span class="visually-hidden">{MARK}-svc-hint</span>' in page)

    print("\nthe services a search engine is told about")
    schema = itemlist_of(page)
    r.check("the page carries a Service ItemList", schema is not None)
    if schema is not None:
        r.check("generated from the cards, so it cannot disagree with them",
                len(schema["itemListElement"]) == 1
                and schema["itemListElement"][0]["item"]["name"] == f"{MARK}-svc-row",
                str(schema.get("itemListElement")))
        r.check("with the card's own text as the description",
                schema["itemListElement"][0]["item"]["description"] == f"{MARK}-svc-text")
        r.check("and an absolute url built from the card's link",
                schema["itemListElement"][0]["item"]["url"]
                == "https://tech4time.bd/pages/services/cybersecurity/",
                str(schema["itemListElement"][0]["item"].get("url")))

    print("\na destination card may carry artwork for each colour mode")
    data["revision"] = 4
    data["destinations"]["items"][0]["image_dark"] = {
        "src": "/uploads/3333333333333333.webp", "webp": "", "width": 800, "height": 658}
    publish(base, key, "home", data)
    _, page = get(base, "/")
    r.check("the dark half appears only once a dark image is given",
            "/uploads/3333333333333333.webp" in page
            and 'class="destination-card__media destination-card__media--dark '
                'theme-swap--dark"' in page)
    r.check("and the light half is marked as the light one",
            'class="destination-card__media theme-swap--light"' in page)

    data["revision"] = 5
    data["destinations"]["items"][0]["image_dark"] = {
        "src": "", "webp": "", "width": 0, "height": 0}
    publish(base, key, "home", data)
    _, page = get(base, "/")
    r.check("clearing it goes back to one picture and no swap",
            page.count('class="destination-card__media"') == 1
            and "theme-swap--light" not in page)

    print("\nwhat the home page does with hidden things")
    data["revision"] = 6
    data["tags"]["items"][0]["status"] = "hidden"
    data["capabilities"]["status"] = "hidden"
    data["terminal"]["status"] = "hidden"
    publish(base, key, "home", data)
    _, page = get(base, "/")

    r.check("a hidden row is not rendered", f"{MARK}-tag" not in page)
    r.check("a hidden band is gone entirely", f"{MARK}-cap-title" not in page)
    r.check("including the terminal, and its description with it",
            f"{MARK}-term-summary" not in page and "terminal__cursor" not in page)
    r.check("and the rest of the page is untouched", f"{MARK}-svc-row" in page)
    r.check("a hidden service is out of the structured data too",
            itemlist_of(page)["itemListElement"][0]["item"]["name"] == f"{MARK}-svc-row")

    print("\nan accent phrase that is not in the title")
    data["revision"] = 7
    data["tags"]["items"][0]["status"] = "shown"
    data["capabilities"]["status"] = "shown"
    data["terminal"]["status"] = "shown"
    data["hero"]["accent"] = "not-in-the-title"
    publish(base, key, "home", data)
    _, page = get(base, "/")
    r.check("the heading still renders, plain",
            f"{MARK}-hero" in page and 'class="hero__accent"' not in page,
            "a heading losing its colour, not a page losing its heading")

    print("\na signature is not a promise about what is inside")
    data["revision"] = 8
    data["hero"]["accent"] = f"{MARK}-accent"
    data["capabilities"]["items"][0]["title"] = '<script>steal()</script>'
    data["destinations"]["items"][0]["image"]["src"] = "https://evil.example/photo.jpg"
    status, _ = publish(base, key, "home", data)
    r.check("a validly signed payload is accepted", status == 200, str(status))

    stored = json.loads(HOME.read_text())
    _, page = get(base, "/")
    r.check("but the script is not markup on the page",
            "<script>steal()" not in page and "steal()" not in page.replace(
                "&lt;script&gt;steal()&lt;/script&gt;", ""),
            "every field on this page is plain text and goes through h()")
    r.check("a picture pointing at another origin is dropped on receipt",
            stored["destinations"]["items"][0]["image"]["src"] == "",
            str(stored["destinations"]["items"][0]["image"]))


def json_ld(page: str) -> list:
    """Every ld+json block on the page, parsed. A services page carries three:
    the shared Organization graph, the breadcrumb trail, and the Service."""
    out = []
    for raw in re.findall(r'<script type="application/ld\+json">(.*?)</script>',
                          page, re.S):
        try:
            out.append(json.loads(raw))
        except ValueError:
            continue
    return out


def main_entity_items(page: str) -> list:
    """The entries of a page graph's mainEntity ItemList.

    NOT itemlist_of(), which looks for a TOP-LEVEL @type ItemList — that is the
    shape the services index uses. The company profile's AboutPage and the
    milestones page's CollectionPage each carry their timeline as a nested
    mainEntity, so a top-level search finds nothing and an assertion made
    against None is a check that cannot fail.
    """
    for node in json_ld(page):
        entity = node.get("mainEntity") if isinstance(node, dict) else None
        if isinstance(entity, dict) and entity.get("@type") == "ItemList":
            return entity.get("itemListElement", [])
    return []


def itemlist_of(page: str):
    """The Service ItemList block, parsed. There are two ld+json blocks."""
    for raw in re.findall(r'<script type="application/ld\+json">(.*?)</script>',
                          page, re.S):
        try:
            node = json.loads(raw)
        except ValueError:
            continue
        if node.get("@type") == "ItemList":
            return node
    return None


def main() -> None:
    if not shutil.which("php"):
        print("php not found — skipping. sudo apt install php-cli")
        return

    port = free_port()
    base = f"http://127.0.0.1:{port}"

    with tempfile.TemporaryDirectory() as tmp:
        private = Path(tmp) / "t4t-private"
        private.mkdir(mode=0o700)

        key_hex = "9f" * 32
        (private / "publish.key").write_text(key_hex + "\n")
        key = bytes.fromhex(key_hex)

        backups = {path: (path.read_text() if path.is_file() else None)
                   for path in documents()}

        server = subprocess.Popen(
            ["php", "-S", f"127.0.0.1:{port}", "-t", str(ROOT),
             str(ROOT / "tools" / "dev-router.php")],
            cwd=str(ROOT),
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
            env=dict(os.environ, T4T_PRIVATE=str(private)),
            preexec_fn=os.setsid,
        )

        r = Results()
        try:
            for _ in range(80):
                try:
                    urllib.request.urlopen(base + "/", timeout=1).read()
                    break
                except urllib.error.HTTPError:
                    break
                except OSError:
                    time.sleep(0.05)
            else:
                raise SystemExit("the test server never came up")

            run(base, key, r)
        finally:
            os.killpg(os.getpgid(server.pid), signal.SIGTERM)
            server.wait(timeout=10)

            # EVERY document, and no longer the ones somebody remembered.
            # Leaving one out does not fail the run that left it out — it fails
            # the NEXT run, which starts from a document the previous run raised
            # the revision of, and is refused as not-newer. home.json was that
            # one, and the comment that replaced it asked the reader to
            # remember, which is not a mechanism. The list is CONTRACT_DOCUMENTS
            # now, so a document added to the contract is restored by having
            # been added.
            for path, backup in sorted(backups.items()):
                if backup is not None:
                    path.write_text(backup)
                elif path.is_file():
                    # A document with no committed file — one whose editor
                    # exists before anybody has saved it. Restoring "what was
                    # there" means removing it, not leaving it: content/ is
                    # committed in this repository, and a file this test wrote
                    # is one a stray "git add -A" would publish as content.
                    path.unlink()
                bak = path.with_suffix(".json.bak")
                if bak.is_file():
                    bak.unlink()
            print("\ncontent/ restored")

        sys.exit(r.report())


if __name__ == "__main__":
    main()
