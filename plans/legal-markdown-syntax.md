# Legal Markdown — the frozen syntax

> ## Status: ACCEPTED — building.
>
> The dialect below is frozen by owner decision. Phase 1 (`lib/markdown.php` + vector suites)
> implements exactly this and nothing else; a ribbon for syntax not named here is out of scope,
> and syntax added later gets its own ADR amendment, vectors, and toolbar work first.
>
> When the legal hub ships, this file is marked **BUILT** and kept for the reasoning, exactly
> like `seo-management.md`. `docs/` describes what is true; this describes what was decided.

**Applies to:** both — the renderer is `lib/markdown.php`, byte-identical in both repositories;
the ribbons are `editor.js`, backend only; the pages are frontend only.

This is a design, not documentation. Nothing below is true of the site today.

---

## Why this exists

The privacy editor's section→block→row cards are precise and, at twelve sections and
forty-four blocks, punishing: every paragraph is a card, every correction archaeology. The
owner — developer-adjacent, ribbon-driven, never typing raw syntax by choice — asked for
Word-like ribbons over a Markdown document: toolbar buttons inserting syntax, server-rendered
preview. The dialect follows what ribbons can honestly insert, not the other way round.

## Non-goals, stated so nobody re-litigates them

- **No raw HTML.** Ever. Source HTML is escaped to literal text. The sanitizer allow-list does
  not grow; the renderer can only emit tags `rt_sanitise_html()` already approves, plus the
  heading and table structures the renderer itself owns (see below).
- **H1 stays a field.** The page title is the H1; bodies mint H2–H6. A lone `#` renders
  literally rather than minting a second H1, which would break the landmark hierarchy and
  dilute the page title in search.
- **No second renderer.** Preview posts through `data-async` and returns HTML from the same
  `lib/markdown.php`. A JavaScript copy is forbidden by ADR 0025.
- **No per-document flavors.** One dialect for privacy, terms, and cookies.

## Core: strict CommonMark subset

Paragraphs (blank-line separated), `*emphasis*`, `**strong**`, `[label](url)`, `- ` / `1. ` lists
(one level; a nested marker renders literally — legal prose does not nest lists, and a guessy
nest would mis-number obligations). Single newlines join; two close the paragraph. Trailing
double-space hard breaks are NOT supported (invisible syntax on a legal page is a trap).

Links admit exactly the addresses the toolbar admits today: `https://`, `mailto:`, `/`.
Anything else renders as literal text, including the brackets. This matches `editor.js`'s
`link()` guard and `careers_safe_href()`-class policy: the ribbon cannot write what the save
would strip.

## Headings with pinned anchors: `##` through `######`

One body, one heading tree. H2 opens (the hero owns H1), levels never skip downward, and any
heading may pin its anchor with a `{#custom-id}` suffix:

```markdown
## Who is responsible for your data {#who-we-are}

### When you use the contact form
```

- Suffix validated (`[A-Za-z]` start, alphanumerics plus `_-:.`); invalid or empty
  suffixes render literally. Missing suffix slugs from display text (markers
  stripped first, so `[c](/d)` anchors as `c`, not `c-d`).
- Explicit duplicates are refused at save (two stated addresses); slugged twins
  get `-2` silently. First H2-or-deeper rule and the no-skip chain are validated
  per body at save, naming the heading — the renderer is line-blind by design.
- The table of contents reads the same resolver (`md_headings()`), so a rail
  link pointing at a missing anchor is structurally impossible. Renaming a
  heading keeps its anchor when the suffix is kept; dropping the suffix moves
  it, visibly, in preview.

## Extension 1: `++underline++`

Markdown has none; the toolbar keeps its U button. Paired `++` around inline text emits `<u>`;
unpaired or empty (`++++`, `++ ++`) renders literally. Never spans a line break — an underline
that starts in one paragraph and ends in another is a typo, and rendering it would join the
paragraphs. Single `+` is untouched (prices, phone numbers with `+880`).

## Extension 2: `:::note` / alignment containers

One fenced mechanism for the two things Markdown lacks — highlight boxes and alignment:

```
:::note
Anything the dialect knows, paragraphs and lists included.
:::

:::center
The same, centred.
:::
```

- Fence lines are exactly `:::name` / `:::` (trailing spaces tolerated, nothing else on the
  line). Name is `[a-z]+`; `note`, `left`, `center`, `right` and `justify` are known,
  anything else renders the whole block — fences included — as literal paragraphs. Unknown
  degrades visibly, never silently.
- Unclosed fences render literally, fences and all. Nesting is refused: a second opener line
  inside an open container is literal text; containers never nest.
- `note` renders the tinted box (`.legal__notice`, same class and CSS as before); the
  alignment names wrap in the existing `ta-*` classes. Renderer owns the markup, as with every
  privacy block kind before it.

## Extension 3: strict GFM tables

```
| What        | How long      |
| ----------- | ------------- |
| Enquiry mail | 24 months    |
```

- Header row, delimiter row (`---`, `:---`, `---:`, `:--:` for default/left/right/center),
  body rows. Column count is the header's; short rows pad empty, long rows truncate — no,
  stricter: a row whose cell count differs from the header renders the WHOLE table literally.
  A legal schedule must never silently gain or drop a cell. (Decided: strict. See ADR 0025.)
- Delimiter row absent or malformed → literal text, pipes and all.
- Cells hold inline dialect only (emphasis, links, `++`). Block syntax inside a cell is
  literal. Pipes cannot be escaped — a cell needing one is a table the author should not be
  building in prose; the dedicated table UI this replaces did not allow it either.
- Renders today's `.legal__table` in `.legal__table-wrap` with the header row in `<thead>` —
  no `<caption>` variant exists in the dialect; a table needing a spoken caption keeps a
  lead-in paragraph above it, which is how the current policy does it.

## What the dialect renders to (the whole output vocabulary)

`p` `br` `strong` `em` `u` `a[href]` `ul` `ol` `li` `h2`–`h6` `table` `thead` `tbody` `tr`
`th` `td`
`div[class: legal__notice | ta-left | ta-center | ta-right | ta-justify]` — every one of them
already inside `RT_ALLOWED_TAGS` + `RT_ALLOWED_CLASSES` or the legal table/note markup the
renderer owns. If a construct would need another tag, the construct is cut, not the allow-list
extended.

## Structure around the dialect (not in it)

A legal document is one Markdown `body` plus hero, callout box, CTA band, SEO `meta` band,
per-document `status` (shown/hidden whole page), facts-comparison notices, and the effective
date (calendar-picked ISO) with the auto `updated` stamp beside it. Anchors resolve at render
from `{#id}` suffixes and slugs through one shared function, so the table of contents and the
page cannot disagree; renaming a heading keeps its anchor when the suffix is kept.

## Migration note (privacy first)

The live `privacy.json` bodies are HTML-subset; a one-way throwaway script converts them
(paragraphs, lists, emphasis, links, tables→GFM, notes→`:::note`) for diff review. Vectors
include the converted corpus: every migrated section must render byte-comparable to today's
page before cutover. The script is deleted after, with the old editor.

## Verification (Phase 1 gates)

- `tools/test_markdown.py` (both halves): every construct above, every edge named here,
  the full migrated privacy corpus, 10MB input, pathological nesting — all degrading to
  literal text, never markup; byte-identical output asserted across both repositories.
- Adversarial subset: `<script>`/`<img onerror>`/ `javascript:`/`data:` URLs in links and
  autolinks (there are no autolinks — `<http://…>` renders literally), unclosed fences,
  tables, emphasis spanning blocks.
- `test_legal_admin.py`: every ribbon pressed, rendered markup read back; malformed input
  refused or literal-never-markup.
