# Development

**Applies to:** both

You have just been handed this project. This page is the first hour.

---

## The first hour

1. **Ten minutes** — [00-orientation/README.md](../00-orientation/README.md). What this is and why
   it has no build step.
2. **Twenty minutes** — [setup.md](setup.md). Install four things, clone, serve. You will have the
   site running.
3. **Ten minutes** — [00-orientation/architecture.md](../00-orientation/architecture.md). How a
   request is served and where the data lives.
4. **Twenty minutes** — [where-to-change-things.md](where-to-change-things.md). Skim it. You do not
   need to remember it; you need to know it exists, because it is the page that answers "which file
   do I open?"

Then make a change and run [the checks](testing.md).

---

## The five things that will surprise you

**There is no build step.** Edit a file, refresh the browser. No watcher, no compile, no install.

**`python3 -m http.server` is not enough.** Four things need PHP: the careers page, the contact page,
the contact form's handler, and `api/publish.php`. Use `python3 tools/serve.py`.

**The editor is not in this repository.** It is `tech4time-backend`, serving `admin.tech4time.bd`.
Clone it beside this one and run both servers to watch content actually travel —
[running-locally.md](running-locally.md).

**Your secrets live outside the repository**, at `../t4t-private`, beside your clone — the same
shape as `/home/USER/t4t-private` on the host, so nothing about the layout differs in development.

**The header and footer are copied into every page**, not included at runtime. Edit
`tools/templates/`, then propagate. Never hand-edit one page's header.
[shared-markup.md](frontend/shared-markup.md)

---

## In this section

| | |
|---|---|
| [setup.md](setup.md) | from nothing to a running site |
| [running-locally.md](running-locally.md) | the dev server, signing in, what cannot work locally |
| [where-to-change-things.md](where-to-change-things.md) | **"I want to change X" → the file that owns it** |
| [testing.md](testing.md) | every check, what it proves, how to read a failure |
| [frontend/](frontend/) | CSS, JavaScript, motion, icons, shared markup, adding a page |
| [backend/](server-side/) | the libraries, the content model, editors, authentication |

---

## Before you commit

```bash
python3 tools/check_contrast.py        # WCAG AA, both modes
python3 tools/inject_icons.py --check  # every page's icon block is current
python3 tools/check_shared_markup.py   # no header/footer has drifted
python3 tools/check_content_model.py   # model, form and renderer still agree
python3 tools/check_secrets.py         # nothing secret committed, no protection removed
python3 tools/check_docs.py            # the docs still describe the code
python3 tools/audit_pages.py           # SEO, accessibility, structure, links
```

What each proves, and which of the browser suites to run when: [testing.md](testing.md).
