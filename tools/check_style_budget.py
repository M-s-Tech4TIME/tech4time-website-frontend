#!/usr/bin/env python3
"""
How much style recalculation the site does while simply sitting there.

Build/audit tool. NOT deployed to the web server (see tools/README.md).

    python3 tools/check_style_budget.py
    python3 tools/check_style_budget.py --seconds 8 --ceiling 100

WHY THIS EXISTS
Every other browser check here watches frames. None of them can see the fault
this one is for.

On 2026-09-03 a change to the page-title circuitry took the style
recalculation on fourteen pages from 46ms per second to 300ms per second —
roughly a third of a CPU core, spent forever, on every page with a title band.
The site was reported as struggling to scroll and to change theme. Throughout,
`tools/test_motion.py` reported 17ms median frames and every suite passed,
because an idle desktop has the headroom to absorb that and still hit 60fps.
The user found it before any check did.

Frame rate is the wrong instrument. A page can hold 60fps while burning a core,
and the person on a laptop or a phone is the one who pays. What went wrong was
measurable the whole time, just not by anything here: Chrome counts the time it
spends recalculating style, and that number went up sevenfold.

So this asks the browser directly. It loads a page, lets it settle, then reads
Performance.getMetrics twice and reports the style time between the two.
Nothing is clicked and nothing is scrolled: this is the cost of the page
existing, which is the cost that was missed.

WHAT THE NUMBERS MEAN
Measured on this machine, on /pages/about/, per second of wall clock:

    ~46ms   before the circuitry was replaced
    ~39ms   now
    ~83ms   the same drawing with 48 charges instead of 24
    ~300ms  the version that shipped and was reported

The ceiling below is deliberately loose. It is here to catch a change of kind,
not to police a change of ten milliseconds, and a busy machine moves this by
about ten per cent between runs.
"""

import argparse
import base64
import json
import os
import socket
import struct
import subprocess
import sys
import time
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PAGES = ["/pages/about/", "/pages/services/", "/"]
CEILING_MS_PER_SECOND = 100.0
SETTLE = 5
DEFAULT_SECONDS = 6


class Socket:
    """Just enough WebSocket to talk to Chrome. No dependencies by design."""

    def __init__(self, url):
        import urllib.parse
        u = urllib.parse.urlparse(url)
        self.sock = socket.create_connection((u.hostname, u.port), 10)
        path = u.path + (("?" + u.query) if u.query else "")
        self.sock.sendall((
            f"GET {path} HTTP/1.1\r\nHost: {u.hostname}:{u.port}\r\n"
            f"Upgrade: websocket\r\nConnection: Upgrade\r\n"
            f"Sec-WebSocket-Key: {base64.b64encode(os.urandom(16)).decode()}\r\n"
            f"Sec-WebSocket-Version: 13\r\n\r\n").encode())
        buf = b""
        while b"\r\n\r\n" not in buf:
            buf += self.sock.recv(4096)
        self.buf = buf.split(b"\r\n\r\n", 1)[1]
        self.id = 0

    def _read(self, n):
        while len(self.buf) < n:
            chunk = self.sock.recv(65536)
            if not chunk:
                raise ConnectionError("chrome closed the connection")
            self.buf += chunk
        out, self.buf = self.buf[:n], self.buf[n:]
        return out

    def call(self, method, params=None, timeout=60):
        self.id += 1
        payload = json.dumps({"id": self.id, "method": method,
                              "params": params or {}}).encode()
        mask = os.urandom(4)
        head = bytearray([0x81])
        n = len(payload)
        if n < 126:
            head.append(0x80 | n)
        elif n < 65536:
            head.append(0x80 | 126); head += struct.pack(">H", n)
        else:
            head.append(0x80 | 127); head += struct.pack(">Q", n)
        head += mask + bytes(b ^ mask[i % 4] for i, b in enumerate(payload))
        self.sock.sendall(bytes(head))

        end = time.time() + timeout
        while time.time() < end:
            h = self._read(2)
            op, ln = h[0] & 0x0F, h[1] & 0x7F
            if ln == 126:
                ln = struct.unpack(">H", self._read(2))[0]
            elif ln == 127:
                ln = struct.unpack(">Q", self._read(8))[0]
            data = self._read(ln)
            if op == 0x8:
                raise ConnectionError("chrome closed the connection")
            if op != 0x1:
                continue
            msg = json.loads(data.decode())
            if msg.get("id") == self.id:
                if "error" in msg:
                    raise RuntimeError(f"{method}: {msg['error']}")
                return msg.get("result", {})
        raise TimeoutError(method)


def free_port() -> int:
    s = socket.socket(); s.bind(("127.0.0.1", 0)); p = s.getsockname()[1]; s.close()
    return p


def chrome_binary() -> str | None:
    for name in ("google-chrome", "google-chrome-stable", "chromium", "chromium-browser"):
        found = subprocess.run(["which", name], capture_output=True, text=True)
        if found.returncode == 0:
            return found.stdout.strip()
    return None


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--seconds", type=int, default=DEFAULT_SECONDS)
    ap.add_argument("--ceiling", type=float, default=CEILING_MS_PER_SECOND)
    ap.add_argument("--page", action="append", dest="pages")
    args = ap.parse_args()
    pages = args.pages or PAGES

    binary = chrome_binary()
    if binary is None:
        print("check_style_budget: no Chrome on PATH — nothing was measured.")
        print("Install Google Chrome or Chromium to run this. It is the only")
        print("check here that can see a style-recalculation regression.")
        return 0

    web = free_port()
    php = subprocess.Popen(
        ["php", "-S", f"127.0.0.1:{web}", "-t", str(ROOT),
         str(ROOT / "tools" / "dev-router.php")],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True)
    port = free_port()
    profile = f"/tmp/t4t-style-budget-{port}"
    chrome = subprocess.Popen(
        [binary, "--headless=new", f"--remote-debugging-port={port}",
         f"--user-data-dir={profile}", "--no-first-run", "--no-default-browser-check",
         "--disable-extensions", "--window-size=1440,900", "--force-device-scale-factor=1"],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True)

    failures = []
    try:
        for _ in range(80):
            try:
                socket.create_connection(("127.0.0.1", web), 0.4).close()
                break
            except OSError:
                time.sleep(0.25)

        ws = None
        for _ in range(120):
            try:
                with urllib.request.urlopen(
                        f"http://127.0.0.1:{port}/json/list", timeout=1) as f:
                    for t in json.loads(f.read()):
                        if t.get("type") == "page":
                            ws = t["webSocketDebuggerUrl"]
                            break
                if ws:
                    break
            except Exception:
                pass
            time.sleep(0.25)
        if not ws:
            print("check_style_budget: Chrome did not start. Nothing was measured.")
            return 2

        c = Socket(ws)
        c.call("Page.enable")
        c.call("Performance.enable")

        print(f"check_style_budget: {args.seconds}s per page, "
              f"ceiling {args.ceiling:.0f}ms of style per second\n")
        for path in pages:
            c.call("Page.navigate", {"url": f"http://127.0.0.1:{web}{path}"})
            time.sleep(SETTLE)          # past load, and past any one-shot reveal

            def snap():
                return {m["name"]: m["value"]
                        for m in c.call("Performance.getMetrics")["metrics"]}

            a = snap()
            time.sleep(args.seconds)
            b = snap()
            style = (b["RecalcStyleDuration"] - a["RecalcStyleDuration"]) * 1000
            layout = (b["LayoutDuration"] - a["LayoutDuration"]) * 1000
            rate = (style + layout) / args.seconds
            ok = rate <= args.ceiling
            print(f"  {'ok  ' if ok else 'FAIL'}  {path:<22} "
                  f"{rate:>6.0f}ms/s  (style {style:.0f}ms, layout {layout:.0f}ms "
                  f"over {args.seconds}s)")
            if not ok:
                failures.append((path, rate))
    finally:
        for proc in (chrome, php):
            try:
                proc.terminate(); proc.wait(5)
            except Exception:
                pass

    if failures:
        print(f"\n{len(failures)} page(s) over the budget.\n")
        print("This is the cost of the page merely being open — nothing was")
        print("clicked or scrolled. Something is being recalculated every frame.")
        print("The usual cause is a CSS animation on an inherited property over")
        print("a <use>, which pushes work through the whole shadow tree; see")
        print("docs/10-development/frontend/motion.md.")
        return 1

    print("\nEvery page is inside its style budget.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
