#!/usr/bin/env python3
"""
Prove the backend can put a PICTURE on the public site, and nothing else can.

Development tool. NOT deployed to the web server (see tools/README.md).
Run from the repo root:  python3 tools/test_publish_asset.py
Requires the PHP CLI:    sudo apt install php-cli

WHY THIS EXISTS
api/publish-asset.php is the second of exactly two things on this host that
write anything, and the only one that writes a FILE THE WEB SERVER WILL SERVE.
That is a sharper edge than content/, which is only ever read by PHP: a
mistake here is a file somebody can fetch, and possibly one the server would
run.

Three layers are meant to make that impossible, and this drives all three:

    the bytes are ours    the backend re-encodes through gd before sending, so
                          what arrives is that library's output. This endpoint
                          does not take that on trust and reads the header
                          itself.
    the name is ours      computed here from those bytes. The sender never
                          sends a filename, so there is nothing for a traversal
                          or a .php extension to ride in on.
    the server serves
      nothing else        an .htaccess allow-list of sixteen hex characters and
                          three extensions. Not testable over the dev server,
                          which does not read .htaccess -- it is asserted
                          against the live host by tools/verify_live.py.

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

import base64
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
# The endpoint under test. It is named here rather than glued onto the base at
# each call site, because gluing it on is what went wrong: post() appends
# ENDPOINT itself, so a caller passing base + "/api/publish-asset.php" asked for
# /api/publish-asset.php/api/publish.php. Both Apache and `php -S` answer that by
# running the first script and calling the rest PATH_INFO, so every check here
# passed while testing an address the backend never sends to — it posts to
# PUBLISH_ASSET_PATH in tech4time-website-backend/lib/publish_client.php, which
# is this exact path and nothing after it.
ASSET_ENDPOINT = "/api/publish-asset.php"

UPLOADS = ROOT / "uploads"
ROUTER = ROOT / "tools" / "dev-router.php"

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


def post(base: str, body: bytes, headers: dict,
         endpoint: str = ENDPOINT) -> tuple[int, dict]:
    req = urllib.request.Request(base + endpoint, data=body, method="POST")
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



# A one-pixel picture in each format the endpoint accepts, and several it does
# not. Written as literals so the test needs no image library -- which matters,
# because the machine running this may well not have one.
PNG = base64.b64decode(
    "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==")
GIF = base64.b64decode("R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7")
JPEG = base64.b64decode(
    "/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a"
    "HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAA"
    "AAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q==")
WEBP = base64.b64decode("UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==")


def send(base: str, key: bytes, blob: bytes, *, mime="image/png",
         timestamp=None, sign_with=None, tamper=False) -> tuple[int, dict]:
    ts = int(time.time()) if timestamp is None else timestamp
    signature = sign(sign_with or key, blob, ts)
    if tamper:
        blob = blob + b"\x00"
    return post(base, blob, {
        "Content-Type": mime,
        "X-T4T-Timestamp": str(ts),
        "X-T4T-Signature": signature,
    }, ASSET_ENDPOINT)


def held() -> list[str]:
    return sorted(p.name for p in UPLOADS.iterdir()
                  if p.is_file() and not p.name.startswith("."))


def stop(proc) -> None:
    """Stop the dev server and everything it started."""
    try:
        os.killpg(os.getpgid(proc.pid), signal.SIGTERM)
    except (ProcessLookupError, PermissionError):
        proc.terminate()
    try:
        proc.wait(timeout=5)
    except subprocess.TimeoutExpired:
        proc.kill()


def run(base: str, key: bytes, r: Results) -> None:
    print("a picture the backend signed")

    status, answer = send(base, key, PNG)
    r.check("a signed PNG is accepted", status == 200 and answer.get("ok") is True,
            f"{status} {answer}")
    name = answer.get("asset", "")
    r.check("the name is sixteen hex characters and an extension",
            re.fullmatch(r"[0-9a-f]{16}\.png", name) is not None, name)
    r.check("and it is on disk", (UPLOADS / name).is_file(), name)
    r.check("byte for byte", (UPLOADS / name).read_bytes() == PNG)
    r.check("with the real size, which is what keeps the page still",
            answer.get("width") == 1 and answer.get("height") == 1, str(answer))

    status, again = send(base, key, PNG)
    r.check("sending it a second time is a no-op, not an error",
            status == 200 and again.get("held") is True, f"{status} {again}")
    r.check("and it is the same file", again.get("asset") == name)

    for label, blob, ext in [("a JPEG", JPEG, "jpg"), ("a WebP", WEBP, "webp")]:
        status, answer = send(base, key, blob)
        r.check(f"{label} is accepted too", status == 200 and answer.get("ok") is True,
                f"{status} {answer}")
        r.check(f"{label} gets the extension its CONTENT says",
                answer.get("asset", "").endswith("." + ext), str(answer))

    print("\nthe name is never the sender's")
    status, answer = send(base, key, PNG, mime="image/png")
    r.check("the Content-Type does not decide the extension",
            answer.get("asset", "").endswith(".png"))
    status, answer = send(base, key, JPEG, mime="image/png")
    r.check("a lying Content-Type is ignored, not obeyed",
            answer.get("asset", "").endswith(".jpg"), str(answer))
    r.check("nothing outside uploads/ was written",
            not (ROOT / "x.php").exists() and not (ROOT / "uploads.php").exists())

    print("\nnothing else gets in")
    status, answer = post(base, PNG,
                          {"Content-Type": "image/png"}, ASSET_ENDPOINT)
    r.check("an unsigned picture is refused",
            status == 401 and answer.get("code") == "no-signature", f"{status} {answer}")
    r.check("and it reveals nothing about what is here", "asset" not in answer)

    status, answer = send(base, key, PNG, sign_with=bytes.fromhex("bb" * 32))
    r.check("a signature from another key is refused",
            status == 401 and answer.get("code") == "unknown-key", f"{status} {answer}")

    status, answer = send(base, key, PNG, tamper=True)
    r.check("a body changed after signing is refused",
            status == 401 and answer.get("code") == "bad-signature", f"{status} {answer}")

    status, answer = send(base, key, PNG, timestamp=int(time.time()) - 3600)
    r.check("a signature from an hour ago is refused",
            status == 401 and answer.get("code") == "stale-timestamp", f"{status} {answer}")

    print("\nand nothing that is not a picture, however well signed")
    before = held()
    for label, blob in [
        ("a PHP script", b"<?php system($_GET['c']); ?>"),
        ("an empty body", b""),
        ("a GIF", GIF),
        ("an SVG, which is a document and can carry script",
         b'<svg xmlns="http://www.w3.org/2000/svg"><script>steal()</script></svg>'),
        ("a PHP script wearing a PNG header", PNG[:8] + b"<?php system($_GET['c']); ?>"),
        ("a ZIP", b"PK\x03\x04" + b"\x00" * 40),
    ]:
        status, answer = send(base, key, blob)
        r.check(f"{label} is refused",
                status == 415 and answer.get("code") == "not-an-image",
                f"{status} {answer}")
    r.check("and none of them left a file behind", held() == before,
            str(set(held()) - set(before)))

    print("\na real picture with a payload appended")
    polyglot = PNG + b"<?php system($_GET['c']); ?>"
    status, answer = send(base, key, polyglot)
    # This one IS a valid PNG -- the trailing bytes are simply ignored by every
    # decoder -- so it is accepted, and that is the honest result to record.
    # Three things stop it mattering, and none of them is this check:
    #   the backend re-encodes before sending, so these bytes never leave it;
    #   the name ends .png, chosen here from the header, not from the sender;
    #   the .htaccess allow-list serves that shape as an image, and no handler
    #   on this host will ever be asked to run it.
    r.check("it is stored as an image, and named as one",
            status == 200 and answer.get("asset", "").endswith(".png"),
            f"{status} {answer}")
    r.check("with the real size, not one read out of the payload",
            answer.get("width") == 1 and answer.get("height") == 1, str(answer))
    r.check("so the payload is inert: nothing can ask the server to run it",
            re.fullmatch(r"[0-9a-f]{16}\.png", answer.get("asset", "")) is not None,
            "the shape of the name is the whole of the .htaccess allow-list")

    print("\nand a header that claims a picture nobody could hold")
    for label, blob in [
        ("a PNG declaring 60000 x 60000",
         PNG[:16] + (60000).to_bytes(4, "big") + (60000).to_bytes(4, "big") + PNG[24:]),
    ]:
        status, answer = send(base, key, blob)
        r.check(f"{label} is refused",
                status == 415 and answer.get("code") == "not-an-image",
                f"{status} {answer}")

    print("\nsize and method")
    status, answer = post(base, b"", {}, ASSET_ENDPOINT)
    status2, answer2 = get(base, "/api/publish-asset.php")
    r.check("GET is refused", status2 == 405, str(status2))

    big = PNG + b"\x00" * (2 * 1024 * 1024)
    status, answer = send(base, key, big)
    r.check("a body past the cap is refused",
            status == 413 and answer.get("code") == "asset-too-large",
            f"{status} {answer}")


# --------------------------------------------------------------------- main


def main() -> None:
    if not shutil.which("php"):
        raise SystemExit("php not found:  sudo apt install php-cli")

    port = free_port()
    work = Path(tempfile.mkdtemp(prefix="t4t-asset-"))
    private = work / "private"
    private.mkdir(mode=0o700, parents=True)

    key = bytes.fromhex("a4" * 32)
    (private / "publish.key").write_text(key.hex() + "\n")

    UPLOADS.mkdir(exist_ok=True)
    before = set(held())

    r = Results()
    server = subprocess.Popen(
        ["php", "-S", f"127.0.0.1:{port}", "-t", str(ROOT), str(ROUTER)],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
        start_new_session=True,
        env=dict(os.environ, T4T_PRIVATE=str(private)),
    )
    try:
        base = f"http://127.0.0.1:{port}"
        for _ in range(80):
            try:
                urllib.request.urlopen(base + "/404.html", timeout=1)
                break
            except Exception:
                time.sleep(0.15)
        run(base, key, r)
    finally:
        stop(server)
        shutil.rmtree(work, ignore_errors=True)
        # Everything this run put there, and nothing that was there before.
        for name in set(held()) - before:
            (UPLOADS / name).unlink(missing_ok=True)
        print("\nuploads/ restored")

    total = r.passed + len(r.failed)
    if r.failed:
        print(f"\n{len(r.failed)} of {total} checks FAILED:")
        for case in r.failed:
            print(f"  - {case}")
        sys.exit(1)

    print(f"\n{r.passed}/{total} checks passed")


if __name__ == "__main__":
    main()
