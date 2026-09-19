#!/usr/bin/env python3
"""
Verify the markup still pasted into every page has not drifted between them.

Build/audit tool. NOT deployed to the web server (see tools/README.md).
Run from the repo root:  python3 tools/check_shared_markup.py

MOST OF WHAT THIS FILE USED TO CHECK NO LONGER EXISTS TO CHECK. The header,
footer and dock were literal markup in seventeen pages -- about 6,800 lines of
duplication -- and this compared every copy against tools/templates/. They are
lib/body.php now, rendering from content/chrome.json, and there is one copy.
Nothing can drift from itself, so those three blocks and their templates are
gone from here and from the repository. ADR 0023.

WHAT IS LEFT IS WHAT IS STILL PASTED:

  the hero circuit   decoration around a page title, with no content in it and
                     nothing editable about it, so it stayed literal
  the script tags    a page loads the modules it needs, which differ page to
                     page -- but the shared ones must be the same set, in the
                     same order, at the same version
  lib/body.php       the one copy, asserted to still carry the hooks the
                     scripts bind to. See the end of this file for why that is
                     worth a check of its own

The single permitted per-page difference in a pasted block is the
aria-current="page" marker, which is normalised away before comparison.
"""

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
TEMPLATES = ROOT / "tools" / "templates"

# name -> (template file, regex capturing that block in a page)
BLOCKS = {
    # Only on pages with a title band, which the home page and the 404 do not
    # have. Absence is not drift; a copy that differs is.
    "hero-circuit": (
        "hero-circuit.html",
        re.compile(r"<!--hero-circuit:start-->.*?<!--hero-circuit:end-->", re.S),
    ),
}

OPTIONAL_BLOCKS = {"hero-circuit"}

# Feature modules a page may legitimately omit: forms.js when it carries no
# form, dashboard.js when it has no tabbed panels, tech-sphere.js when it has no
# logo sphere. Their absence is not drift.
#
# PATHS, WITHOUT THE VERSION QUERY. circuit.js was listed here as
# "/assets/js/circuit.js?v=2" and had to be re-edited every time the file
# changed -- a tool that has to be maintained in step with a cache bust is a
# tool that will one day be forgotten, and its only symptom would be sixteen
# pages accused of carrying a shared script they do not have. The comparison
# strips the query instead, and the agreement BETWEEN pages is asserted
# separately, further down, where it belongs.
OPTIONAL_SCRIPTS = {
    "/assets/js/forms.js",
    "/assets/js/dashboard.js",
    "/assets/js/tech-sphere.js",
    # Two pages have a slideshow, one has the terminal.
    "/assets/js/slider.js",
    "/assets/js/terminal.js",
    # The hero mesh is the home page's alone.
    "/assets/js/neural.js",
    # The charges in the title band; the two pages without a band omit it.
    "/assets/js/circuit.js",
}

# The hooks the scripts bind to in the header, footer and dock. Attribute
# names, not whole tags: what matters is that the hook is still emitted, not
# where in the line it sits.
BODY_HOOKS = [
    "data-theme-toggle",    # theme-toggle.js — the sun/moon button
    "data-dock",            # nav.js — the dock itself
    "data-nav-drawer",      # nav.js — the panel it opens
    "data-nav-toggle",      # nav.js — the button that opens it
    "data-back-to-top",     # main.js — the footer key
    "data-current-year",    # main.js — refreshCopyrightYear()
]

ARIA_CURRENT = re.compile(r'\s*aria-current="page"')
WHITESPACE = re.compile(r"\s+")


def normalise(markup: str) -> str:
    """Collapse whitespace and drop the per-page active-nav marker."""
    return WHITESPACE.sub(" ", ARIA_CURRENT.sub("", markup)).strip()


def pages() -> list[Path]:
    found = (
        list(ROOT.glob("*.html"))
        # The home page is index.php and the error page is 404.php. Without
        # them the page every visitor sees and the page every lost visitor
        # sees would be the two whose header and footer nothing checked.
        # Named, not globbed as "*.php": contact-handler.php and sitemap.php
        # are endpoints, not pages, and have none of these blocks by design.
        + list(ROOT.glob("index.php"))
        + list(ROOT.glob("404.php"))
        + list(ROOT.glob("pages/**/*.html"))
        # The careers page is PHP because its content changes without a
        # redeploy. Its header and footer are still literal markup pasted in
        # like everywhere else, so they drift the same way and are checked the
        # same way.
        + list(ROOT.glob("pages/**/*.php"))
    )
    return sorted(found)


def first_difference(a: str, b: str) -> str:
    for i, (x, y) in enumerate(zip(a, b)):
        if x != y:
            start = max(0, i - 60)
            return (
                f"\n      expected: …{b[start:i + 60]}…"
                f"\n      found:    …{a[start:i + 60]}…"
            )
    longer = "page" if len(a) > len(b) else "template"
    extra = (a if len(a) > len(b) else b)[min(len(a), len(b)):][:120]
    return f"\n      {longer} has extra content: …{extra}…"


def main() -> None:
    files = pages()
    if not files:
        print("No pages built yet — nothing to compare.")
        return

    canonical = {}
    for name, (filename, _) in BLOCKS.items():
        path = TEMPLATES / filename
        if not path.exists():
            raise SystemExit(f"Missing template: {path}")
        canonical[name] = normalise(path.read_text())

    problems = []
    # Which URL each optional script is served from, and on which pages.
    optional_versions: dict[str, dict[str, list[str]]] = {}
    print(f"Checking shared markup across {len(files)} page(s)\n")

    for path in files:
        rel = path.relative_to(ROOT)
        html = path.read_text()
        issues = []

        for name, (_, pattern) in BLOCKS.items():
            match = pattern.search(html)
            if not match:
                if name not in OPTIONAL_BLOCKS:
                    issues.append(f"no {name} block found")
                continue
            found = normalise(match.group(0))
            if found != canonical[name]:
                issues.append(f"{name} differs from template" + first_difference(found, canonical[name]))

        # Scripts: check the set and order of the non-optional ones.
        #
        # An optional script is recognised by its PATH, not by its whole URL. It
        # carries a version query the moment its file changes -- forms.js became
        # forms.js?v=2 the day the contact form stopped reading form.action -- and
        # matching the literal would then treat it as a missing shared script on
        # every page that has one. The bump is the correct action; a check that
        # punishes it is the one that is wrong. What the version has to agree
        # with is the OTHER pages, and that is asserted below.
        srcs = re.findall(r'<script src="(/assets/js/[^"]+)"[^>]*></script>', html)
        required = [s for s in srcs if s.split("?")[0] not in OPTIONAL_SCRIPTS]

        for src in srcs:
            stem = src.split("?")[0]
            if stem in OPTIONAL_SCRIPTS:
                optional_versions.setdefault(stem, {}).setdefault(src, []).append(str(rel))

        expected = [
            # theme-init.js is NOT here any more. It moved into lib/head.php
            # with the rest of the <head>, so it is no longer a literal in any
            # page and looking for one would fail on all seventeen. It is
            # checked once, below, where it now lives -- losing it would bring
            # back the flash of the wrong theme on first paint, which is the
            # whole reason it is not deferred.
            "/assets/js/theme-toggle.js",
            "/assets/js/nav.js",
            # Versioned for the same reason main.js is, below. It gained one
            # when the reveal machinery stopped assuming every staggered grid
            # was auto-fit — two of them count their columns per breakpoint
            # now — and a required script is pinned here rather than left to
            # the cross-page agreement that covers the optional ones.
            "/assets/js/animations.js?v=2",
            # The version query is part of the contract, not noise: .htaccess
            # caches JS for a year, and MODULES in main.js is a hardcoded allow
            # list. A page still pointing at the unversioned URL keeps whatever
            # copy that visitor cached, and silently loses every module added
            # since. So the string is pinned here and a page that drops it
            # fails, rather than merely behaving oddly for returning visitors.
            "/assets/js/main.js?v=3",
        ]
        if required != expected:
            issues.append(f"script tags differ:\n      expected: {expected}\n      found:    {required}")

        print(f"  {rel}  — {'OK' if not issues else str(len(issues)) + ' issue(s)'}")
        for issue in issues:
            problems.append(f"{rel}: {issue}")

    # AND THEY AGREE WITH EACH OTHER. An optional script is edited in one file
    # and pointed at from up to sixteen, so a sed that missed one leaves those
    # visitors on last year's copy with this year's markup -- the same failure
    # check_cache_bust.py catches for a script nobody bumped at all, in the one
    # shape it cannot see, because the URL did change on the pages it looked at.
    for stem, seen in sorted(optional_versions.items()):
        if len(seen) > 1:
            detail = "; ".join(
                f"{url} on {len(pages)} page(s), e.g. {sorted(pages)[0]}"
                for url, pages in sorted(seen.items())
            )
            problems.append(
                f"{stem}: served from {len(seen)} different URLs — {detail}. "
                f"One file, one version query, every page that carries it."
            )

    # The head is emitted rather than pasted now, so the one script that lives
    # in it is checked in the file that emits it. This is the whole of what
    # replaced seventeen copies, and it is one line -- but it is a line whose
    # absence nothing else on this site would notice.
    head = ROOT / "lib" / "head.php"
    if not head.is_file():
        problems.append("lib/head.php: missing, so no page has a <head> at all")
    elif '<script src="/assets/js/theme-init.js"></script>' not in head.read_text():
        problems.append("lib/head.php: does not emit theme-init.js, so every page "
                        "flashes the wrong theme before first paint")
    else:
        print("  lib/head.php  — emits theme-init.js for all 17")

    # And the same question asked of the file that replaced the other 6,800
    # lines. lib/body.php emits the header, footer and dock, and every browser
    # behaviour in them is bound by a data- attribute: the theme toggle, the
    # dock panel and its button, back-to-top, and the copyright year a tab left
    # open across midnight corrects itself with.
    #
    # NOTHING ELSE HERE WOULD NOTICE ONE GOING MISSING. audit_pages.py reads
    # rendered output and would still find one <header>, one <footer> and every
    # link resolving; the page would be valid, and the feature would be gone.
    # The suites that WOULD notice -- test_nav.py, test_theme.py -- need
    # Firefox and geckodriver, so they are not in the run before a commit.
    body = ROOT / "lib" / "body.php"
    if not body.is_file():
        problems.append("lib/body.php: missing, so no page has a header, "
                        "footer or dock at all")
    else:
        text = body.read_text()
        absent = [hook for hook in BODY_HOOKS if hook not in text]
        if absent:
            problems.append(
                f"lib/body.php: does not emit {', '.join(absent)} — the markup "
                f"still renders and the behaviour behind it silently does not")
        else:
            print(f"  lib/body.php  — emits all {len(BODY_HOOKS)} script hooks "
                  f"the chrome carries")

    if problems:
        print(f"\n{len(problems)} drift issue(s):\n")
        for p in problems:
            print(f"  - {p}")
        print("\nFix the page to match tools/templates/, or update the template "
              "and propagate to every page.")
        sys.exit(1)

    print("\nShared markup is identical across all pages.")


if __name__ == "__main__":
    main()
