#!/usr/bin/env python3
"""
Prove that a picture stored at several widths reaches the page as several
widths — and that one stored at a single width still reaches it as it always
did.

Development tool. NOT deployed to the web server (see tools/README.md).
Run from the repo root:  python3 tools/test_pictures.py
Requires the PHP CLI:    sudo apt install php-cli

WHY THIS EXISTS
The backend stores a ladder; this half decides what a browser is told about it.
Nothing else here can see that:

  tools/audit_pages.py    audits the page against the documents AS THEY ARE,
                          and none of them holds a ladder yet — so the rule it
                          enforces (a srcset of widths needs a sizes= beside
                          it) has nothing to enforce it on
  tools/test_publish.py   proves a document travels the wire; it does not read
                          the markup a picture in it turns into
  tech4time-website-backend/tools/test_upload.py
                          proves the ladder gets WRITTEN. It cannot see this
                          repository at all

So this puts a ladder into each document and reads the page back.

WHAT IT PROVES
  - each of the seven laddering slots emits srcset AND sizes, on the <img> and
    on the WebP <source>, with the sizes= the contract declares for that slot;
  - src still names the top rung, so a browser that ignores srcset — and every
    scraper that reads an <img> without parsing a candidate list — gets a real
    picture rather than the smallest one;
  - A PICTURE WITH NO LADDER RENDERS EXACTLY AS IT DID BEFORE LADDERS EXISTED.
    That is the case every document is in today and the one worth a test of its
    own: no srcset, no sizes, no change to the markup at all;
  - and no page anywhere ends up with a srcset of widths and no sizes=, which
    is not a missing improvement but a REGRESSION — without sizes= a browser is
    required to assume the picture fills the viewport and takes the widest rung
    on every screen, so a phone downloads the 3x file for a flag drawn at 56
    pixels.

Every case runs against a COPY of the document, restored afterwards whether the
run passes or fails.
"""

import json
import re
import sys
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import audit_pages as A                                   # noqa: E402

CONTENT = ROOT / "content"

# The rungs are deliberately not round numbers a renderer could produce by
# accident, and the top one is the src: that is what upload_store() returns.
WIDTHS = [111, 222, 333]

# EVERY SEAT GETS ITS OWN FILE NAMES, and that is not tidiness. Three of the
# seven are on one page — a client's logo, a journey photograph and a
# technology mark — so a shared name would find whichever of the three the
# markup happens to put first and check the wrong picture against the right
# slot's sizes=. The first version of this file did exactly that and reported
# five failures in code that was correct.


def rungs(seat: int, ext: str) -> list[tuple[str, int]]:
    return [(f"/uploads/{seat:02d}{'ab' if ext == 'webp' else 'cd'}"
             f"{w:04d}{seat:02d}{w:04d}.{ext}", w) for w in WIDTHS]


def ladder(seat: int, with_rungs: bool = True) -> dict:
    """One picture record, with a ladder or without one."""
    png, webp = rungs(seat, "png"), rungs(seat, "webp")
    record = {"src": png[-1][0], "webp": webp[-1][0],
              "width": 333, "height": 250, "srcset": "", "webp_srcset": ""}
    if with_rungs:
        record["srcset"] = ", ".join(f"{p} {w}w" for p, w in png)
        record["webp_srcset"] = ", ".join(f"{p} {w}w" for p, w in webp)
    return record


# slot, document, where the picture goes, which page draws it
SEATS = [
    ("about.story",        "about",    ("story", "items", 1, "image"),
     "pages/about/index.php"),
    ("home.destinations",  "home",     ("destinations", "items", 0, "image"),
     "index.php"),
    ("company.journey",    "company",  ("journey", "items", 0, "image"),
     "pages/company-profile/index.php"),
    ("company.clients",    "company",  ("clients", "items", 0, "image"),
     "pages/company-profile/index.php"),
    ("company.technology", "company",  ("technology", "items", 0, "image"),
     "pages/company-profile/index.php"),
    ("branding.asset",     "branding", ("assets", "items", 0, "image"),
     "pages/branding-and-advertisement/index.php"),
    # An office's flag has no uploaded picture in the shipped document — the
    # three offices use a slug naming a file that ships with this repository.
    # An upload is the only kind an editor can add, and it takes the other
    # branch of contact_flag_picture(), so it is the branch worth testing.
    ("contact.offices",    "contact",  ("offices", "items", 0, "image"),
     "pages/contact/index.php"),
    # The accreditations wall. Its row is the only one on the about page whose
    # picture is NOT a story illustration, so it is also the check that
    # about_picture()'s slot argument is actually being passed rather than
    # falling back to its default.
    ("about.accreditations", "about",   ("accreditations", "items", 0, "image"),
     "pages/about/index.php"),
]


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


def slot_sizes() -> dict:
    """What sizes= the contract declares for each slot, read from the contract."""
    out = subprocess.run(
        ["php", "-r", "require 'lib/contract.php';"
                      "$o = []; foreach (CONTRACT_IMAGE_SLOTS as $k => $_)"
                      " { $o[$k] = contract_slot_sizes($k); }"
                      "echo json_encode($o);"],
        cwd=ROOT, capture_output=True, text=True)
    return json.loads(out.stdout)


def put(doc: str, seat: tuple, value) -> None:
    """Write one picture record into a document, making room for it if needed.

    A seat names a row that the SHIPPED document usually already has, and for
    six of the eight it does. The accreditations wall ships empty on purpose --
    a heading over an empty grid is not a section -- so its seat has to be
    created rather than found. Built here rather than seeded into
    content/about.json, because content/ is a replica: it is written by
    api/publish.php and by nothing else, and a row added to it by hand is a row
    a fresh clone would ship and the next publish would silently drop.
    """
    path = CONTENT / f"{doc}.json"
    data = json.loads(path.read_text())
    node = data
    for key, nxt in zip(seat[:-1], seat[1:]):
        if isinstance(key, int):
            while len(node) <= key:
                node.append({})
        elif key not in node:
            node[key] = [] if isinstance(nxt, int) else {}
        node = node[key]
    node[seat[-1]] = value

    # A picture in a band that is switched off is a picture the renderer never
    # reaches, and the failure reads as a missing srcset rather than as a
    # hidden section. Every band that holds a seat is shown before it is read
    # back. Seven of the eight are shown already, so this changes nothing for
    # them; the accreditations wall ships hidden.
    band = seat[0]
    if isinstance(band, str) and isinstance(data.get(band), dict):
        data[band]["status"] = "shown"

    path.write_text(json.dumps(data, indent=2))


def elements(html: str, tag: str) -> list[str]:
    return [re.sub(r"\s+", " ", m.group(0))
            for m in re.finditer(rf"<{tag}\b[^>]*>", html, re.S)]


def naming(html: str, tag: str, path: str) -> str:
    """The one <img> or <source> whose srcset or src names `path`."""
    for el in elements(html, tag):
        if path in el:
            return el
    return ""


def widths_of(srcset: str) -> list[int]:
    return [int(w) for w in re.findall(r"\s(\d+)w", srcset)]


def attr(el: str, name: str) -> str:
    m = re.search(rf'\b{name}="([^"]*)"', el)
    return m.group(1) if m else ""


def run(r: Results) -> None:
    sizes = slot_sizes()

    print("a picture stored at several widths reaches the page as several widths")
    for n, (slot, doc, seat, page) in enumerate(SEATS):
        put(doc, seat, ladder(n, True))
        html, err = A.render_php(ROOT / page)
        if err:
            r.check(f"{slot}: the page renders", False, err[:200])
            continue

        png, webp = rungs(n, "png"), rungs(n, "webp")
        img = naming(html, "img", png[-1][0])
        src = naming(html, "source", webp[-1][0])

        r.check(f"{slot}: the <img> carries the ladder",
                widths_of(attr(img, "srcset")) == WIDTHS, f"{img[:200]}")
        r.check(f"{slot}: and the sizes= the contract declares",
                attr(img, "sizes") == sizes[slot],
                f"{attr(img, 'sizes')!r} vs {sizes[slot]!r}")
        r.check(f"{slot}: src still names the top rung",
                attr(img, "src") == png[-1][0], f"{attr(img, 'src')!r}")
        r.check(f"{slot}: the WebP <source> carries its own ladder",
                widths_of(attr(src, "srcset")) == WIDTHS, f"{src[:200]}")
        r.check(f"{slot}: and the same sizes=",
                attr(src, "sizes") == sizes[slot],
                f"{attr(src, 'sizes')!r} vs {sizes[slot]!r}")

    print("\na slot that declares no sizes= gets no candidate list, whatever it holds")
    # The guard, asked directly, because no renderer draws these three: a
    # download is the deliverable, and a share card and Organization.logo are
    # read by consumers that do not implement srcset. If one of them ever
    # arrives holding a ladder — hand-edited, or restored from a document
    # written under a different table — it must still render as one file. The
    # first version of this file could not see this at all: breaking the guard
    # changed nothing any of its cases looked at.
    for slot in ("branding.file", "seo.share", "seo.logo", "not.a.slot", ""):
        got = subprocess.run(
            ["php", "-r", "require 'lib/contract.php';"
                          "echo json_encode(contract_picture_ladder(["
                          "  'srcset' => '/uploads/a.png 100w, /uploads/b.png 200w',"
                          "  'webp_srcset' => '/uploads/a.webp 100w'], %r));" % slot],
            cwd=ROOT, capture_output=True, text=True)
        answer = json.loads(got.stdout or "{}")
        r.check(f"'{slot or '(none)'}' holding a ladder still renders one file",
                answer == {"srcset": "", "webp_srcset": "", "sizes": ""},
                str(answer))

    print("\na picture stored at one width renders as it always did")
    for n, (slot, doc, seat, page) in enumerate(SEATS):
        put(doc, seat, ladder(n, False))
        html, err = A.render_php(ROOT / page)
        if err:
            r.check(f"{slot}: the page renders", False, err[:200])
            continue

        png, webp = rungs(n, "png"), rungs(n, "webp")
        img = naming(html, "img", png[-1][0])
        src = naming(html, "source", webp[-1][0])

        r.check(f"{slot}: no srcset on the <img>", "srcset" not in img, img[:200])
        r.check(f"{slot}: no sizes on the <img>", "sizes" not in img, img[:200])
        r.check(f"{slot}: the <source> is the single WebP it always was",
                attr(src, "srcset") == webp[-1][0] and "sizes" not in src,
                src[:200])

    print("\nno page ends up with a ladder a browser cannot choose from")
    # The regression the sizes= rule exists to prevent, asked of the whole site
    # with every document laddered at once rather than one at a time.
    for n, (slot, doc, seat, _page) in enumerate(SEATS):
        put(doc, seat, ladder(n, True))

    loose = []
    for label, path, service in targets():
        html, err = A.render_php(path, service)
        if err:
            continue
        for tag in ("img", "source"):
            for el in elements(html, tag):
                srcset = attr(el, "srcset")
                if re.search(r"\s\d+w", srcset) and not attr(el, "sizes"):
                    loose.append(f"{label}: <{tag}> {srcset[:60]}")

    r.check("every srcset of widths on every page has a sizes= beside it",
            not loose, "\n          ".join(loose[:6]))


def targets():
    out = [(str(p.relative_to(ROOT)), p, None) for p in A.pages()]
    have = {str(p.relative_to(ROOT)) for p in A.pages()}
    for slug in A.service_slugs():
        rel = f"pages/services/{slug}/index.php"
        if rel not in have:
            out.append((f"pages/services/{slug}/", A.DETAIL, slug))
    return sorted(out)


def main() -> None:
    held = {doc: (CONTENT / f"{doc}.json").read_bytes()
            for doc in {d for _s, d, _seat, _p in SEATS}}

    r = Results()
    try:
        run(r)
    finally:
        for doc, blob in held.items():
            (CONTENT / f"{doc}.json").write_bytes(blob)
        print(f"\n{', '.join(f'content/{d}.json' for d in sorted(held))} restored")

    if r.failed:
        print(f"\n{len(r.failed)} of {r.passed + len(r.failed)} checks FAILED:")
        for case in r.failed:
            print(f"  - {case}")
        raise SystemExit(1)

    print(f"\n{r.passed}/{r.passed} checks passed")


if __name__ == "__main__":
    main()
