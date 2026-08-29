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
ABOUT = ROOT / "content" / "about.json"

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


def free_port() -> int:
    with socket.socket() as s:
        s.bind(("127.0.0.1", 0))
        return int(s.getsockname()[1])


def fingerprint(key: bytes) -> str:
    return hmac.new(key, b"publish-key-fingerprint", hashlib.sha256).hexdigest()[:16]


def sign(key: bytes, body: bytes, timestamp: int) -> str:
    mac = hmac.new(key, f"{timestamp}.".encode() + body, hashlib.sha256).hexdigest()
    return f"{fingerprint(key)}:{mac}"


def envelope(document: str, data: dict, version: int = 1) -> dict:
    return {
        "contract_version": version,
        "document": document,
        "revision": int(data.get("revision", 0)),
        "published": "2026-08-26T00:00:00+00:00",
        "data": data,
    }


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
            version: int = 1, at: int | None = None,
            tamper: bytes | None = None) -> tuple[int, dict]:
    body = json.dumps(envelope(document, data, version),
                      separators=(",", ":"), ensure_ascii=False).encode()
    stamp = int(time.time()) if at is None else at
    header = sign(key, body, stamp)
    return post(base, tamper if tamper is not None else body,
                {"X-T4T-Timestamp": str(stamp), "X-T4T-Signature": header})


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
    r.check("and reports what the site's footers say",
            isinstance(answer.get("footer_synced"), str) and answer["footer_synced"] != "",
            "footer_synced should carry lib/footer-fingerprint.php")

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

    status, answer = publish(base, key, "careers", job(6), version=2)
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
        {"contract_version": 1, "document": "careers", "revision": 99,
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
    about_round_trip(base, key, r)


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
        "tab", "desc", "share", "hero", "sub",
        "m-eyebrow", "m-title", "m-lead", "m-row", "m-text",
        "b-eyebrow", "b-title", "x-title", "x-label",
        "c-title", "c-name", "j-title", "j-lead", "j-alt",
        "e-eyebrow", "e-title", "e-lead", "t-title", "t-name",
        "p-title", "p-title-row", "p-text",
        "cta-title", "cta-text", "cta-label",
    ) if f"{MARK}-{k}" not in page]
    r.check("every field the model declares reaches the page",
            not missing, "never rendered: " + ", ".join(missing))

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

    print("\nwhat the about page does with hidden things")
    data["revision"] = 4
    data["whyus"]["items"][0]["status"] = "hidden"
    data["cta"]["status"] = "hidden"
    publish(base, key, "about", data)
    _, page = get(base, "/pages/about/")

    r.check("a hidden row is not rendered", f"{MARK}-w-row" not in page)
    r.check("but the band around it still is", f"{MARK}-w-title" in page)
    r.check("a hidden band is gone entirely", f"{MARK}-cta-title" not in page)
    r.check("and the rest of the page is untouched", f"{MARK}-sp-title" in page)

    print("\na signature is not a promise about what is inside")
    data["revision"] = 5
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

        careers_backup = CAREERS.read_text() if CAREERS.is_file() else None
        contact_backup = CONTACT.read_text() if CONTACT.is_file() else None
        company_backup = COMPANY.read_text() if COMPANY.is_file() else None
        about_backup = ABOUT.read_text() if ABOUT.is_file() else None

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

            for path, backup in ((CAREERS, careers_backup), (CONTACT, contact_backup),
                                 (COMPANY, company_backup), (ABOUT, about_backup)):
                if backup is not None:
                    path.write_text(backup)
                bak = path.with_suffix(".json.bak")
                if bak.is_file():
                    bak.unlink()
            print("\ncontent/ restored")

        sys.exit(r.report())


if __name__ == "__main__":
    main()
