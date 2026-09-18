#!/usr/bin/env python3
"""Tech4TIME — measure the About page's accreditations band in a browser.

Development tool. NOT deployed to the web server (see tools/README.md).

WHY THIS EXISTS, AND WHY NO OTHER CHECK COULD DO IT
content/about.json ships with **no `accreditations` key at all**. The band is
the one part of the site that is built, styled, published, protected from the
upload sweep, given a measured 289px picture slot and a seat in
test_pictures.py -- and then never rendered, because the document that would
fill it does not exist yet. So check_responsive.py, check_focus.py,
check_dark_mode.py and check_hover.py all walk the About page as it ships and
every one of them walks straight past the band. Its behaviour at 320px, the
colours it actually paints, and whether its plates are square have never been
measured by anything.

This supplies its own document, measures the band, and puts content/ back.
Nothing shipped changes and the live About page is untouched -- the band stays
hidden until somebody uploads a badge.

WHAT IS MEASURED, AND WHY EACH ONE
  the grid          2 columns, then 3 at 48em, then 4 at 64em. Read as the
                    painted x-positions of the tiles, not as the stylesheet's
                    own words: a `repeat(3, 1fr)` that a long caption has
                    overflowed is still `repeat(3, 1fr)` in the CSSOM.
  the plate         aspect-ratio: 1 is a REQUEST. A tall mark, a two-line
                    caption or a grid row that stretches can all defeat it,
                    and a plate that is not square is the one thing a
                    certification wall cannot get away with.
  320px             no horizontal scroll, which is the width the site's own
                    responsive rule is written against.
  the artwork plate about.css says --artwork-plate is "the same value in both
                    themes deliberately", because a certification mark is
                    somebody else's dark ink on transparency and a themed
                    surface would swallow it. Read as the colour the browser
                    RESOLVES for the element under each theme -- the same
                    method check_contrast.py uses, and the one that catches a
                    token quietly resolving to two different values. Sampling
                    the screenshot instead would measure the reveal animation's
                    opacity as well, which is check_dark_mode.py's subject.
  the caption       WCAG AA against the ground it actually sits on, in both
                    themes -- it is --text-secondary on the section surface,
                    which is the combination most likely to be borderline.
  the mark          contained by its plate. The mark fills the square minus
                    its padding "whatever shape it arrived in", so a very wide
                    or very tall badge is exactly the case that would spill.

The band is NOT interactive -- <li> and <span>, no link, no control -- so
there is no focus ring and no tap target to measure, and this does not invent
either. The hover lift is check_hover.py's subject and stays there.
"""

from __future__ import annotations

import json
import os
import shutil
import signal
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
ABOUT = ROOT / "content" / "about.json"

# 320 is the floor the site is written against; 768 and 1024 are 48em and 64em,
# the two breakpoints the grid declares. One width either side of each proves
# the breakpoint is where it says it is rather than near it.
WIDTHS_COLUMNS = [(320, 2), (767, 2), (768, 3), (1023, 3), (1024, 4), (1440, 4)]

# Deliberately awkward: a very wide mark, a very tall one, a long caption that
# must wrap, and one with no badge at all. Four rows is not a round number on
# any of the three column counts, so the last row is always short -- which is
# the state a wall of certifications is usually in.
ROWS = [
    ("ISO/IEC 27001:2022 Information Security Management", 600, 120),
    ("SOC 2 Type II", 120, 600),
    ("CMMI Level 5", 300, 300),
    ("PCI DSS", 0, 0),
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


def free_port() -> int:
    with socket.socket() as s:
        s.bind(("127.0.0.1", 0))
        return s.getsockname()[1]


def rq(method, url, body=None):
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(url, data=data, method=method,
                                 headers={"Content-Type": "application/json"})
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
            return json.loads(r.read().decode() or "{}")
    except urllib.error.HTTPError as e:
        raise SystemExit("WebDriver error:\n" + e.read().decode()[:600])


def wait_for(port, tries=120) -> bool:
    for _ in range(tries):
        try:
            with socket.create_connection(("127.0.0.1", port), 0.2):
                return True
        except OSError:
            time.sleep(0.15)
    return False


def stop(proc: subprocess.Popen) -> None:
    for attempt in (proc.terminate, proc.kill):
        try:
            attempt()
            proc.wait(timeout=5)
            return
        except Exception:
            continue
    try:
        os.killpg(os.getpgid(proc.pid), signal.SIGKILL)
    except Exception:
        pass


class Browser:
    def __init__(self, drv_port):
        base = f"http://127.0.0.1:{drv_port}"
        r = rq("POST", base + "/session", {"capabilities": {"alwaysMatch": {
            "browserName": "firefox",
            "moz:firefoxOptions": {"args": ["-headless"]}}}})
        self.s = f"{base}/session/{r['value']['sessionId']}"

    def size(self, w, h=900):
        rq("POST", self.s + "/window/rect",
           {"width": w, "height": h, "x": 0, "y": 0})
        time.sleep(0.3)

    def go(self, url):
        rq("POST", self.s + "/url", {"url": url})
        time.sleep(0.5)

    def js(self, script):
        return rq("POST", self.s + "/execute/sync",
                  {"script": script, "args": []})["value"]

    def theme(self, name):
        """The site's own switch, not a devtools override.

        data-theme is what theme-toggle.js writes and what every themed token
        keys off, so setting it here paints exactly what a visitor who pressed
        the button would see.
        """
        self.js(f"document.documentElement.setAttribute('data-theme', {name!r});")
        time.sleep(0.35)

    def quit(self):
        try:
            rq("DELETE", self.s)
        except Exception:
            pass


# --------------------------------------------------------------- the document

def badge(width: int, height: int) -> str:
    """A real PNG of the given size, as a data: URI is not allowed here.

    Written into uploads/ the way a published badge would be, so the renderer
    takes exactly the path it takes in production rather than a special case.
    """
    import base64
    import struct
    import zlib

    def chunk(tag: bytes, data: bytes) -> bytes:
        return (struct.pack(">I", len(data)) + tag + data
                + struct.pack(">I", zlib.crc32(tag + data) & 0xFFFFFFFF))

    raw = b"".join(b"\x00" + b"\x20\x20\x20\xff" * width for _ in range(height))
    png = (b"\x89PNG\r\n\x1a\n"
           + chunk(b"IHDR", struct.pack(">IIBBBBB", width, height, 8, 6, 0, 0, 0))
           + chunk(b"IDAT", zlib.compress(raw))
           + chunk(b"IEND", b""))
    return base64.b64encode(png).decode()


def document(uploads: Path) -> dict:
    """The shipped about document, plus an accreditations band."""
    data = json.loads(ABOUT.read_text())
    items = []
    for i, (name, w, h) in enumerate(ROWS, start=1):
        row = {"id": f"acc{i}", "name": name, "status": "shown", "caption": "shown"}
        if w and h:
            import base64
            rel = f"check-accreditations-{i}.png"
            (uploads / rel).write_bytes(base64.b64decode(badge(w, h)))
            row["image"] = {"src": f"/uploads/{rel}", "webp": "",
                            "width": w, "height": h,
                            "srcset": "", "webp_srcset": ""}
        items.append(row)
    data["accreditations"] = {
        "status": "shown",
        "title": "Accreditations",
        "items": items,
    }
    return data


# ------------------------------------------------------------------- measuring

PROBE = """
const grid = document.querySelector('.accreditations__grid');
if (!grid) { return {missing: true}; }
const tiles = Array.from(grid.querySelectorAll('.accreditation'));
const plates = tiles.map(t => t.querySelector('.accreditation__plate'));
const names  = tiles.map(t => t.querySelector('.accreditation__name'));
const marks  = tiles.map(t => t.querySelector('.accreditation__logo'));
const rect = el => { const r = el.getBoundingClientRect();
                     return {x: r.x, y: r.y, w: r.width, h: r.height}; };
const paint = el => getComputedStyle(el);
return {
  tiles:  tiles.map(rect),
  plates: plates.map(p => p ? rect(p) : null),
  plateBg: plates.map(p => p ? paint(p).backgroundColor : null),
  nameColor: names.map(n => n ? paint(n).color : null),
  nameGround: (function () {
    let el = grid.closest('.section') || grid.parentElement;
    while (el) {
      const bg = paint(el).backgroundColor;
      if (bg && bg !== 'transparent' && !bg.startsWith('rgba(0, 0, 0, 0)')) return bg;
      el = el.parentElement;
    }
    return paint(document.body).backgroundColor;
  })(),
  marks: marks.map((m, i) => (m && plates[i]) ? {
    mark: rect(m), plate: rect(plates[i]),
    pad: parseFloat(paint(plates[i]).paddingLeft) || 0
  } : null),
  docWidth:  document.documentElement.scrollWidth,
  viewWidth: document.documentElement.clientWidth,
};
"""


def columns(tiles: list[dict]) -> int:
    """How many tiles share the topmost row, by painted x-position."""
    if not tiles:
        return 0
    top = min(t["y"] for t in tiles)
    return sum(1 for t in tiles if abs(t["y"] - top) < 2)


def srgb(component: float) -> float:
    c = component / 255
    return c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4


def luminance(rgb: tuple[float, float, float]) -> float:
    r, g, b = (srgb(v) for v in rgb)
    return 0.2126 * r + 0.7152 * g + 0.0722 * b


def parse_rgb(value: str) -> tuple[float, float, float]:
    nums = [float(n) for n in
            value.replace("rgba(", "").replace("rgb(", "").rstrip(")").split(",")]
    return nums[0], nums[1], nums[2]


def contrast(a: str, b: str) -> float:
    la, lb = luminance(parse_rgb(a)), luminance(parse_rgb(b))
    hi, lo = max(la, lb), min(la, lb)
    return (hi + 0.05) / (lo + 0.05)


def run(b: Browser, origin: str, r: Results) -> None:
    url = origin + "/pages/about/"

    print("\nthe grid is the column count the stylesheet declares, measured as"
          " painted positions")
    for width, expected in WIDTHS_COLUMNS:
        b.size(width)
        b.go(url)
        m = b.js(PROBE)
        if m.get("missing"):
            r.check(f"the band is on the page at {width}px", False,
                    "no .accreditations__grid — the document did not take")
            return
        got = columns(m["tiles"])
        r.check(f"{width}px lays the tiles out {expected} to a row",
                got == expected, f"{got} tiles share the top row, not {expected}")

    print("\nevery plate is square, at every width")
    for width, _ in WIDTHS_COLUMNS:
        b.size(width)
        b.go(url)
        m = b.js(PROBE)
        worst = 0.0
        for p in m["plates"]:
            if not p or p["w"] == 0:
                continue
            worst = max(worst, abs(p["w"] - p["h"]) / p["w"])
        r.check(f"{width}px keeps the plates square",
                worst <= 0.02, f"worst plate is off square by {worst*100:.1f}%")

    print("\nthe mark stays inside its plate")
    b.size(320)
    b.go(url)
    m = b.js(PROBE)
    for i, entry in enumerate(m["marks"]):
        if not entry:
            continue
        mark, plate, pad = entry["mark"], entry["plate"], entry["pad"]
        inside = (mark["x"] >= plate["x"] + pad - 1
                  and mark["y"] >= plate["y"] + pad - 1
                  and mark["x"] + mark["w"] <= plate["x"] + plate["w"] - pad + 1
                  and mark["y"] + mark["h"] <= plate["y"] + plate["h"] - pad + 1)
        r.check(f"badge {i+1} ({ROWS[i][1]}x{ROWS[i][2]}) is contained by its plate",
                inside,
                f"mark {mark['w']:.0f}x{mark['h']:.0f} at ({mark['x']:.0f},"
                f"{mark['y']:.0f}) in plate {plate['w']:.0f}x{plate['h']:.0f}"
                f" at ({plate['x']:.0f},{plate['y']:.0f}) with {pad:.0f}px padding")

    print("\n320px does not scroll sideways")
    b.size(320)
    b.go(url)
    m = b.js(PROBE)
    r.check("the document is no wider than the window at 320px",
            m["docWidth"] <= m["viewWidth"] + 1,
            f"scrollWidth {m['docWidth']} against clientWidth {m['viewWidth']}")

    print("\nthe artwork plate is the same colour in both themes, which is the"
          " whole reason it is not a themed surface")
    b.size(1024)
    b.go(url)
    b.theme("light")
    light = b.js(PROBE)
    b.theme("dark")
    dark = b.js(PROBE)
    r.check("the plate paints the same colour light and dark",
            light["plateBg"][0] == dark["plateBg"][0],
            f"light {light['plateBg'][0]} against dark {dark['plateBg'][0]}")

    print("\nthe caption reads, in both themes, against the ground it sits on")
    for label, m in (("light", light), ("dark", dark)):
        ratio = contrast(m["nameColor"][0], m["nameGround"])
        r.check(f"the caption clears WCAG AA in {label} mode ({ratio:.2f}:1)",
                ratio >= 4.5,
                f"{m['nameColor'][0]} on {m['nameGround']} is {ratio:.2f}:1,"
                f" and AA for this size is 4.5:1")


def main() -> None:
    missing = [n for n in ("php", "geckodriver", "firefox") if not shutil.which(n)]
    if missing:
        print(f"Skipping: {', '.join(missing)} not installed.")
        print("This check needs Firefox and geckodriver as well as the PHP CLI.")
        return

    uploads = ROOT / "uploads"
    uploads.mkdir(parents=True, exist_ok=True)
    before = {f.name for f in uploads.iterdir() if f.is_file()}
    backup = ABOUT.read_bytes()

    web_port, drv_port = free_port(), free_port()
    php = subprocess.Popen(
        ["php", "-S", f"127.0.0.1:{web_port}", "-t", str(ROOT),
         str(ROOT / "tools" / "dev-router.php")],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True)
    drv = subprocess.Popen(
        ["geckodriver", "--port", str(drv_port)],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True)

    if not (wait_for(web_port) and wait_for(drv_port)):
        stop(drv); stop(php)
        raise SystemExit("php or geckodriver did not start")

    print(f"firefox (headless) against 127.0.0.1:{web_port}")
    print(f"{len(ROWS)} accreditations, {len(WIDTHS_COLUMNS)} widths, both themes")

    results = Results()
    browser = None
    try:
        ABOUT.write_text(json.dumps(document(uploads), indent=2))
        browser = Browser(drv_port)
        run(browser, f"http://127.0.0.1:{web_port}", results)
    finally:
        # ALWAYS, and before anything else that can fail. content/ is a replica
        # and a dirty one is a commit waiting to happen -- git add -A would take
        # a test fixture live.
        ABOUT.write_bytes(backup)
        for stray in uploads.iterdir():
            if stray.is_file() and stray.name not in before:
                stray.unlink()
        print("\ncontent/ and uploads/ restored")
        if browser:
            browser.quit()
        for proc in (drv, php):
            stop(proc)

    total = results.passed + len(results.failed)
    print(f"\n{results.passed}/{total} checks passed")

    # A RUN THAT MEASURED NOTHING IS A FAILURE, NOT A PASS. If the document did
    # not take, or the band did not render, every loop above simply does not
    # execute and the suite would otherwise print 0/0 and exit 0 -- the exact
    # silent-pass this sweep went looking for elsewhere.
    if total == 0:
        print("\nFAIL  this check proved nothing: the band never rendered.")
        sys.exit(1)

    if results.failed:
        print("\nfailed:")
        for name in results.failed:
            print(f"  - {name}")
        sys.exit(1)


if __name__ == "__main__":
    main()
