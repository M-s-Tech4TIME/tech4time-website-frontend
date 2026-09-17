#!/usr/bin/env python3
"""
Prove no page scrolls sideways, at the widths people actually hold.

Development tool. NOT deployed to the web server (see tools/README.md).
Run from the repo root:  python3 tools/check_responsive.py

Needs the PHP CLI, Firefox and geckodriver. Exits 0 with a notice if the
browser pieces are missing.

WHY THIS EXISTS
A page that scrolls sideways on a phone is the most visible layout failure
there is, and nothing in this repo could see it. audit_pages.py reads markup,
check_contrast.py reads colour, test_nav.py asks whether the navigation works
— none of them asks how wide the page turned out to be.

WHY AN IFRAME, AND NOT THE WINDOW
This is the whole reason the check is written the way it is.

**Firefox will not make a window narrower than about 500px**, which leaves a
viewport of about 488. Ask WebDriver for 320 and you measure 488, with no
error and no warning: the call succeeds, the
run goes green, and it reports that a width it never tested is fine. That is
worse than having no check, because it produces a record saying the narrowest
phones were covered.

So the page is loaded into an <iframe> of the width being tested, inside a
window that stays wide. An iframe establishes its own viewport: media queries,
100vw and clientWidth all resolve against the frame, so 320 means 320. The
frame is same-origin, so its document can be measured directly.

The check reports the viewport it actually measured, every time. If that
number ever stops matching the width asked for, the run is not to be believed.

WHAT IT ASSERTS
  - the document does not scroll horizontally: scrollWidth <= clientWidth
  - no link, button or form control is wider than the viewport
  - no tap target is under 24x24 CSS px — WCAG 2.2 SC 2.5.8, Level AA — with
    the criterion's own exceptions applied, so a label counts towards the
    control it names and an isolated small target with room around it passes

Two of the widths are criteria in their own right. 320px is SC 1.4.10 Reflow,
which is defined at exactly that width, so the no-sideways-scrolling assertion
is that criterion tested rather than merely argued. 640px is what a 1280px
desktop becomes at 200% zoom, which is SC 1.4.4 Resize Text.

The second is not implied by the first. .btn clips its own overflow for the
shine sweep and .cta-band clips again, so a button too wide for the screen is
cut off in silence — the page does not scroll and nothing looks broken except
the half a word that is missing. Both failures shipped; both are fixed; this
is what keeps them fixed.

AND THEN A SECOND PASS, WITH THE CHROME AT ITS WIDEST
The header, footer and dock became editable in ADR 0023. Every pass above
measures the navigation as it happens to be set today, which is six links whose
labels somebody chose to fit — so it can only find a layout that is already
broken, never one an operator is about to break. The second pass fills every
band with one row per destination the picker offers, leaving each label empty
so it draws that page's own name, and measures again at the narrow widths.

That is the worst case the EDITOR can reach without anybody typing something
absurd, and it is the only kind of worst case a check can honestly assert:
a label is a free text field, so there is no upper bound on its length, and a
check that failed on a 500-character nav link would be failing on a state
nobody has ever created.

AND A THIRD PASS, WITH A LOGO OF A SHAPE NOBODY HAS UPLOADED YET
The mark became an upload too, and the header sizes it by HEIGHT -- so the
width it occupies is height x aspect ratio, and .site-header__brand is
flex-shrink: 0, so nothing downstream can take that width back. While the logo
was three committed files at 2.81:1 there was nothing to check. Now there is.

This pass was written by measuring rather than arguing. Before the cap in
layout.css, at 320px: 8:1 held, 10:1 pushed the page 56px sideways and 16:1
pushed it 221px. The fix is a max-width on the mark with object-fit: contain,
which bounds the width without squashing the artwork -- and what is asserted
here is the thing that fix claims, which is that NO aspect ratio overflows.
That is a claim a check can make honestly, because the cap does not care what
the ratio is.

The shapes are real PNGs written into uploads/ and taken away again. They have
to be real: a broken <img> renders its alt text, which is not the geometry the
page would actually get.

FINDING THE CULPRIT
When the document overflows, the offending element is reported. Elements
inside something that clips them are skipped: a slider's off-screen slides
have a right edge far past the viewport and cannot extend the page, so
listing them buries the one element that can.
"""

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
CHROME = ROOT / "content" / "chrome.json"

# 320 is the narrowest screen still in use and the one that found both bugs.
# It is also WCAG 2.2 SC 1.4.10 Reflow, which is defined as exactly this width
# — so the no-sideways-scrolling assertion below is that criterion, tested.
# 360 and 414 are the common Android and iPhone widths. 640 is what a 1280px
# desktop becomes at 200% zoom, which is SC 1.4.4 Resize Text. The rest are the
# breakpoints in layout.css, so a failure lands near a rule somebody wrote.
WIDTHS = [320, 360, 414, 640, 768, 1024, 1440]

# Every page, by the URL a visitor uses rather than the file behind it, so the
# two .php pages are exercised through PHP like everything else.
PAGES = [
    "/",
    "/404.php",
    "/pages/about/",
    "/pages/branding-and-advertisement/",
    "/pages/careers/",
    "/pages/company-profile/",
    "/pages/milestones/",
    "/pages/contact/",
    "/pages/privacy-policy/",
    "/pages/resource-certifications/",
    "/pages/services/",
    "/pages/services/cloud-infrastructure/",
    "/pages/services/cybersecurity/",
    "/pages/services/hr-solutions/",
    "/pages/services/it-consultancy-training/",
    "/pages/services/it-equipment-supply/",
    "/pages/services/software-development/",
]

# THE SECOND PASS. The chrome is identical on every page, so a handful is
# enough — and the widths are the narrow ones, because that is where a nav of
# fifteen links has anywhere to go wrong. The home page has no page-title band
# and the about page has one, which is the only difference above the fold.
STRESS_PAGES = ["/", "/pages/about/"]
STRESS_WIDTHS = [320, 360, 414, 640]

# THE THIRD PASS. Aspect ratios, not pixel sizes: the mark is stored at the top
# rung of its ladder either way, and what the header's geometry depends on is
# the SHAPE. A square emblem and a portrait mark are ordinary company logos; a
# 16:1 banner is a long wordmark with a tagline beside it, and 24:1 is past
# anything anybody would draw -- which is the point, because the cap that makes
# it safe does not care and a check that stopped at the plausible would not say
# so.
LOGO_SHAPES = [(0.5, "portrait"), (1, "square"), (2.81, "as it ships"),
               (8, "a long lockup"), (16, "a banner"), (24, "absurd")]
LOGO_PAGES = ["/", "/pages/about/"]
LOGO_WIDTHS = [320, 360, 414]
SETTINGS = ROOT / "content" / "settings.json"
UPLOADS = ROOT / "uploads"

# Builds the widest document the picker can produce, and prints it.
#
# EVERY LABEL IS LEFT EMPTY on purpose. An empty label means "whatever that
# page calls itself", so each link draws the longest name the picker
# guarantees — and those are the names the check can defend measuring, where a
# typed label has no length limit at all. The dock bar is the exception: its
# labels are typed rather than taken from the page, so the longest of those
# same names is written into all four, which is the widest thing four keys can
# be asked to hold without inventing a word.
STRESS_PHP = r'''
require 'lib/chrome.php';

$targets = chrome_target_list();
$rows    = [];
foreach (array_keys($targets) as $key) {
    $rows[] = ['id' => '', 'target' => $key, 'label' => '', 'status' => 'shown'];
}

$longest = '';
foreach ($targets as $target) {
    if (strlen($target['name']) > strlen($longest)) { $longest = $target['name']; }
}

$data = chrome_load();
$data['header']['nav']['items']   = $rows;
$data['footer']['links']['items'] = $rows;
$data['footer']['legal']['items'] = $rows;
$data['dock']['panel']['items']   = array_map(
    static fn(array $r): array => $r + ['description' => $longest], $rows);

foreach ($data['dock']['bar']['items'] as $i => $_key) {
    $data['dock']['bar']['items'][$i]['label'] = $longest;
}

echo json_encode(chrome_normalise($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                                          | JSON_UNESCAPED_UNICODE);
'''

# Runs in the outer window. Puts the page in a frame of the requested width,
# waits for it, then measures inside it. Returns {"loading": true} until the
# frame is ready, so the caller polls rather than sleeping a guessed amount.
PROBE = r"""
var width = %WIDTH%, url = %URL%;

var frame = document.getElementById('probe');
if (!frame) {
  frame = document.createElement('iframe');
  frame.id = 'probe';
  frame.style.border = '0';
  frame.style.height = '900px';
  document.body.appendChild(frame);
}
frame.style.width = width + 'px';

var want = url + '#' + width;
if (frame.getAttribute('data-showing') !== want) {
  frame.setAttribute('data-showing', want);
  frame.src = url;
  return {loading: true};
}

var doc = frame.contentDocument;
if (!doc || doc.readyState !== 'complete' || !doc.body) return {loading: true};

var view = frame.contentWindow;
var root = doc.documentElement;
var vw = root.clientWidth;
var sw = root.scrollWidth;

function describe(el) {
  var parts = [];
  for (var e = el; e && e.tagName && parts.length < 4; e = e.parentElement) {
    var cls = (e.className && e.className.baseVal !== undefined
                 ? e.className.baseVal : e.className || '').toString().trim();
    parts.unshift(e.tagName.toLowerCase() + (cls ? '.' + cls.split(/\s+/)[0] : ''));
  }
  return parts.join(' > ');
}

/* An element cannot extend the page if something between it and the root
   clips it and sits inside the viewport itself. Without this the report is
   filled with a carousel's off-screen slides. */
function isClipped(el) {
  for (var p = el.parentElement; p; p = p.parentElement) {
    var cs = view.getComputedStyle(p);
    if (cs.overflowX !== 'visible') {
      if (p.getBoundingClientRect().right <= vw + 1) return true;
    }
  }
  return false;
}

var culprits = [];
if (sw > vw + 1) {
  var all = doc.querySelectorAll('body *');
  for (var i = 0; i < all.length; i++) {
    var el = all[i], cs = view.getComputedStyle(el);
    if (cs.display === 'none' || cs.visibility === 'hidden') continue;
    if (cs.position === 'fixed') continue;
    var r = el.getBoundingClientRect();
    if (r.width < 1 || r.height < 1) continue;
    if (r.right <= vw + 1 || r.right > sw + 2) continue;
    if (isClipped(el)) continue;
    culprits.push(describe(el) + '  (right edge ' + Math.round(r.right) + 'px)');
  }
}

/* Clipped or not, a control wider than the screen has part of itself off it. */
var toowide = [];
var controls = doc.querySelectorAll('a[href], button, input, select, textarea');
for (var i = 0; i < controls.length; i++) {
  var el = controls[i], cs = view.getComputedStyle(el);
  if (cs.display === 'none' || cs.visibility === 'hidden') continue;
  var r = el.getBoundingClientRect();
  if (r.width > vw + 1) {
    var label = (el.textContent || el.value || '').trim().replace(/\s+/g, ' ');
    toowide.push(Math.round(r.width) + 'px  ' + describe(el)
                 + (label ? '  "' + label.slice(0, 40) + '"' : ''));
  }
}

/* --- tap targets: WCAG 2.2 SC 2.5.8, Level AA, 24x24 CSS px -------------
   Measured rather than read off the stylesheet, because a control can declare
   2.75rem and still be squeezed by the flex or grid it sits in. That is the
   only failure worth catching here, and a stylesheet cannot show it.

   Every exception in the criterion is applied, because without them the check
   reports things that are not wrong and gets switched off:

     - a control with an associated <label> is as large as the two together,
       since clicking the label operates the control
     - an inline link inside a run of text is exempt; its height is the line's
     - a small target with clear space around it is exempt however small
     - aria-hidden or tabindex="-1" is not a target at all — the contact
       form's honeypot is exactly that, and 26px wide on purpose
*/
var targets = [];
var candidates = doc.querySelectorAll(
  'a[href], button, input, select, textarea, summary, [role="button"]');
for (var i = 0; i < candidates.length; i++) {
  var el = candidates[i], cs = view.getComputedStyle(el);
  if (cs.display === 'none' || cs.visibility === 'hidden') continue;
  if (el.disabled) continue;
  if (el.getAttribute('tabindex') === '-1') continue;
  if (el.closest('[aria-hidden="true"]')) continue;

  var r = el.getBoundingClientRect();
  if (r.width < 1 || r.height < 1) continue;

  var box = {left: r.left, top: r.top, right: r.right, bottom: r.bottom};
  if (el.id) {
    var lab = doc.querySelector('label[for="' + CSS.escape(el.id) + '"]');
    if (lab) {
      var lr = lab.getBoundingClientRect();
      if (lr.width > 0 && lr.height > 0) {
        box.left = Math.min(box.left, lr.left);
        box.top = Math.min(box.top, lr.top);
        box.right = Math.max(box.right, lr.right);
        box.bottom = Math.max(box.bottom, lr.bottom);
      }
    }
  }
  box.w = box.right - box.left;
  box.h = box.bottom - box.top;
  box.cx = box.left + box.w / 2;
  box.cy = box.top + box.h / 2;
  targets.push({box: box, inline: cs.display === 'inline', el: el});
}

var small = [];
for (var i = 0; i < targets.length; i++) {
  var t = targets[i], box = t.box;
  if (Math.min(box.w, box.h) >= 24) continue;
  if (t.inline) continue;

  /* The spacing exception: undersized is allowed when nothing else is close
     enough to mis-tap. Centre to centre, against the same 24px. */
  var crowded = false;
  for (var j = 0; j < targets.length && !crowded; j++) {
    if (j === i) continue;
    var o = targets[j].box;
    var dx = box.cx - o.cx, dy = box.cy - o.cy;
    if (Math.sqrt(dx * dx + dy * dy) < 24) crowded = true;
  }
  if (!crowded) continue;

  var name = (t.el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 30);
  small.push(Math.round(box.w) + 'x' + Math.round(box.h) + '  ' + describe(t.el)
             + (name ? '  "' + name + '"' : ''));
}

return {loading: false, vw: vw, over: sw - vw,
        culprits: culprits.slice(0, 3), toowide: toowide.slice(0, 3),
        small: small.slice(0, 4)};
"""


class Results:
    def __init__(self):
        self.passed = 0
        self.failed = []

    def check(self, case, ok, detail=""):
        if ok:
            self.passed += 1
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

    def size(self, w, h):
        rq("POST", self.s + "/window/rect", {"width": w, "height": h, "x": 0, "y": 0})
        time.sleep(0.3)

    def go(self, url):
        rq("POST", self.s + "/url", {"url": url})
        time.sleep(0.5)

    def js(self, script):
        return rq("POST", self.s + "/execute/sync",
                  {"script": script, "args": []})["value"]

    def measure(self, width, url, tries=40):
        script = PROBE.replace("%WIDTH%", str(width)).replace("%URL%", json.dumps(url))
        for _ in range(tries):
            out = self.js(script)
            if not out.get("loading"):
                return out
            time.sleep(0.25)
        return None

    def quit(self):
        try:
            rq("DELETE", self.s)
        except Exception:
            pass


def run(b: Browser, origin: str, r: Results) -> None:
    # Held here as well as in main(): the passes below put the documents back
    # BETWEEN themselves, so the stress logo is not measured against the widest
    # navigation as well. Two worst cases at once is a third worst case, and a
    # failure in it would not say which half caused it.
    chrome_held = CHROME.read_bytes() if CHROME.is_file() else None

    # The frame needs a page around it, and the window has to stay wide enough
    # that the widest frame is not itself clamped.
    b.size(max(WIDTHS) + 120, 1000)
    b.go(origin + "/404.php")

    measure_pages(b, origin, r, PAGES, WIDTHS)

    # ------------------------------------------------ and with the widest nav
    #
    # content/ is a replica written by api/publish.php and by nothing else, so
    # this puts it back from bytes taken before anything ran -- including if
    # the run fails, which is what the finally in main() is for.
    print("\n\nand again with every band as full as the picker can make it")

    CHROME.write_text(widest_chrome())
    measure_pages(b, origin, r, STRESS_PAGES, STRESS_WIDTHS, note="  (widest nav)")
    if chrome_held is not None:
        CHROME.write_bytes(chrome_held)

    # ------------------------------------------------- and with a stress logo
    print("\n\nand again with a mark of every shape, at the top of its ladder")

    # The top rung is read from the contract, not written down here. A number
    # in two places is a number that will disagree with itself.
    top = max(contract_rungs())

    for ratio, what in LOGO_SHAPES:
        height = max(1, round(top / ratio))
        stem = stress_logo(ratio)
        SETTINGS.write_text(logo_document(stem, top, height))
        measure_pages(b, origin, r, LOGO_PAGES, LOGO_WIDTHS,
                      note=f"  ({top}x{height}, {ratio}:1 — {what})")


def measure_pages(b: Browser, origin: str, r: Results, pages: list[str],
                  widths: list[int], note: str = "") -> None:
    """One pass: every page in `pages`, at every width in `widths`."""
    for width in widths:
        print(f"\n{width}px{note}")
        measured = None

        for page in pages:
            out = b.measure(width, origin + page)
            if out is None:
                r.check(f"{page} loads at {width}px{note}", False,
                        "the frame never finished loading")
                continue

            measured = out["vw"]
            r.check(
                f"{page} does not scroll sideways at {width}px{note}",
                out["over"] <= 1,
                f"the page is {out['over']}px wider than its {out['vw']}px viewport\n          "
                + "\n          ".join(out["culprits"] or ["(no unclipped element found)"]),
            )
            r.check(
                f"{page} has no control wider than the screen at {width}px{note}",
                not out["toowide"],
                "\n          ".join(out["toowide"]),
            )
            r.check(
                f"{page} has no tap target under 24px at {width}px{note}",
                not out["small"],
                "\n          ".join(out["small"]),
            )

        # Said out loud every time, because the day this stops matching the
        # width asked for is the day every pass above becomes meaningless.
        # Firefox clamps a *window* at about 500px; a frame is why it does not
        # clamp here, and this line is the evidence that it did not.
        if measured is not None:
            slack = width - measured
            print(f"  measured viewport {measured}px"
                  f"  ({slack}px of scrollbar)  — {len(pages)} pages")
            if not 0 <= slack <= 40:
                r.check(f"the {width}px frame really is {width}px wide{note}", False,
                        f"asked for {width}, measured {measured} — the frame is being "
                        f"clamped, so nothing checked at this width can be believed")


def stress_logo(ratio: float) -> str:
    """Three real PNGs — one per rung — in the shape asked for. Returns the stem.

    REAL BYTES, NOT A PATH. An <img> whose source 404s renders its alt text,
    which is a run of words about 12px tall and tells you nothing about what a
    540px picture would have done to the header.
    """
    stem = "check-responsive-" + str(ratio).replace(".", "_")
    for rung in contract_rungs():
        height = max(1, round(rung / ratio))
        out = subprocess.run(
            ["php", "-r", f"""
            $im = imagecreatetruecolor({rung}, {height});
            $g  = imagecolorallocate($im, 110, 112, 117);
            imagefilledrectangle($im, 0, 0, {rung} - 1, {height} - 1, $g);
            imagepng($im, '{UPLOADS}/{stem}-{rung}.png');
            """], cwd=str(ROOT), capture_output=True, text=True)
        if out.returncode != 0:
            raise SystemExit("could not draw the stress logo:\n"
                             + out.stderr.strip()[:400])
    return stem


def contract_rungs() -> list[int]:
    """The ladder the contract declares for the logo, from the contract."""
    out = subprocess.run(
        # Source and ceiling are the ladder's own top rung rather than the
        # uploader's UPLOAD_MAX_DIMENSION, which lives in the backend and is
        # not on this host at all. Unclamped either way: the point is the
        # widths the slot declares, not what a particular upload allowed.
        ["php", "-r", "require 'lib/contract.php';"
                      "$w = CONTRACT_IMAGE_SLOTS['settings.logo']['width'];"
                      "$top = $w * max(CONTRACT_IMAGE_DPR);"
                      "echo json_encode(contract_slot_widths('settings.logo',"
                      " $top, $top));"],
        cwd=str(ROOT), capture_output=True, text=True)
    try:
        return [int(w) for w in json.loads(out.stdout.strip())]
    except ValueError:
        raise SystemExit("could not read the logo's ladder from the contract:\n"
                         + (out.stderr or out.stdout).strip()[:400])


def logo_document(stem: str, width: int, height: int) -> str:
    """content/settings.json with that mark on both halves."""
    rungs = contract_rungs()
    srcset = ", ".join(f"/uploads/{stem}-{rung}.png {rung}w" for rung in rungs)
    half = {"src": f"/uploads/{stem}-{max(rungs)}.png", "webp": "",
            "width": width, "height": height,
            "srcset": srcset, "webp_srcset": ""}

    data = json.loads(SETTINGS.read_text()) if SETTINGS.is_file() else {}
    data["logo"] = {"light": half, "dark": dict(half)}
    return json.dumps(data, indent=4)


def widest_chrome() -> str:
    """content/chrome.json with every band as full as the picker can make it."""
    out = subprocess.run(["php", "-r", STRESS_PHP],
                         cwd=str(ROOT), capture_output=True, text=True)
    if out.returncode != 0 or not out.stdout.strip():
        raise SystemExit("could not build the stress document:\n"
                         + out.stderr.strip()[:400])

    return out.stdout


def main() -> None:
    missing = [n for n in ("php", "geckodriver", "firefox") if not shutil.which(n)]
    if missing:
        print(f"Skipping: {', '.join(missing)} not installed.")
        print("This check needs Firefox and geckodriver as well as the PHP CLI.")
        return

    web_port, drv_port = free_port(), free_port()
    php = subprocess.Popen(
        ["php", "-S", f"127.0.0.1:{web_port}", "-t", str(ROOT),
         str(ROOT / "tools" / "dev-router.php")],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True)
    drv = subprocess.Popen(
        ["geckodriver", "--port", str(drv_port)],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True)

    if not (wait_for(web_port) and wait_for(drv_port)):
        raise SystemExit("php or geckodriver did not start")

    print(f"firefox (headless) against 127.0.0.1:{web_port}")
    print(f"{len(PAGES)} pages x {len(WIDTHS)} widths, each in a frame of its own,")
    print(f"then {len(STRESS_PAGES)} x {len(STRESS_WIDTHS)} with the navigation at its widest")
    results = Results()
    browser = None
    chrome_backup = CHROME.read_bytes() if CHROME.is_file() else None
    settings_backup = SETTINGS.read_bytes() if SETTINGS.is_file() else None
    # uploads/ is ignored by git, so a stray file left here is invisible rather
    # than caught by a dirty tree. Named before the run, removed after it.
    UPLOADS.mkdir(parents=True, exist_ok=True)
    uploads_before = {f.name for f in UPLOADS.iterdir() if f.is_file()}
    try:
        browser = Browser(drv_port)
        run(browser, f"http://127.0.0.1:{web_port}", results)
    finally:
        if chrome_backup is not None:
            CHROME.write_bytes(chrome_backup)
        if settings_backup is not None:
            SETTINGS.write_bytes(settings_backup)
        for stray in UPLOADS.iterdir():
            if stray.is_file() and stray.name not in uploads_before:
                stray.unlink()
        print("\ncontent/ and uploads/ restored")
        if browser:
            browser.quit()
        for proc in (drv, php):
            stop(proc)

    total = results.passed + len(results.failed)
    print(f"\n{results.passed}/{total} checks passed")
    if results.failed:
        print("\nfailed:")
        for name in results.failed:
            print(f"  - {name}")
        sys.exit(1)


if __name__ == "__main__":
    main()
