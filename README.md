# Documentation Center (tool_mdocs)

A Moodle admin tool that turns the Markdown documentation shipped **inside** your
installed plugins into a browsable, in-Moodle Documentation Center — conceptually
like [mkdocs](https://www.mkdocs.org/), but rendered natively by Moodle with no
separate build step.

Many plugins already ship a `readme.md` or a `docs/` folder. `tool_mdocs` discovers
those files, indexes them, and presents them with a sidebar navigation, an in-page
table of contents, and search — so administrators can read a plugin's documentation
without leaving the site or digging through the filesystem.

## Features

- **Automatic discovery** of `.md` files across every installed plugin (and,
  optionally, Moodle core subsystems) using the `core_component` API.
- **Configurable inclusion rules** — index whole folders (e.g. `docs`) *and* named
  files (e.g. `readme.md`, `changes.md`), with an exclusion list that overrides both
  (e.g. never `install.md`). Or simply index every `.md` file there is.
- **mkdocs-like reader** — a component-grouped sidebar tree, rendered Markdown,
  a scroll-spy table of contents, and a filter box.
- **Full-text search** across document titles, components, paths *and document
  content*, with a snippet showing the match in context.
- **Contextual help** — a book icon in the navbar offers the documentation that
  belongs to the page you are on, and what is written for *you* comes first. It
  works with no configuration: a document's audience comes from its file name
  (`teacher-guide.md`) and the pages it belongs to come from the plugin it lives
  in. See `docs/conventions.md`.
- **A window that gets out of the way** — the document opens in a panel you can
  drag, resize and remember, or pop out into a browser window of its own to read
  beside the page it is explaining.
- **User-authored pages** — managers can write their own Markdown documentation pages
  directly in Moodle, either standalone (under a "Site documentation" section) or attached
  to a specific plugin, with a draft/publish flag and embedded images.
- **Secure asset serving** — images referenced from a document are served through a
  hardened endpoint (path-traversal defence, extension allowlist, capability check).
- **Cached & indexed** — a database catalogue plus a rendered-output cache keep page
  loads fast; a scheduled task (and a "Rebuild index" button) keep it fresh.

## Requirements

- Moodle 4.5 (LTS) to 5.2. Continuously tested against 4.5, 5.1 and 5.2.
- PHP 8.1+.

## Installation

1. Copy this plugin to `admin/tool/mdocs` in your Moodle installation.
2. Visit **Site administration ▸ Notifications** to complete installation.
3. Go to **Site administration ▸ Plugins ▸ Admin tools ▸ Documentation Center**.

## Configuration

Under **Site administration ▸ Plugins ▸ Admin tools ▸ Documentation Center ▸ Settings**:

- **Included folders** / **Included file names** — the two lists **add up**: a file is
  indexed when it matches either. "Everything in `docs`, and also `readme.md` and
  `changes.md`" is both lists at once.
- **Never include** — names that are skipped whatever the lists say, matched against a
  file's own name (`install.md`) and against every folder in its path (`examples`).
- **Include every Markdown file** — index everything, ignoring the two lists above.
  *Never include* still applies.
- **Include core subsystems** — also scan Moodle core, not just plugins.
- **Maximum file size** — skip anything larger during indexing.

The index rebuilds itself whenever the inclusion rules or the set of installed
plugins change, so a settings change takes effect on the next visit. **Rebuild
index** on the Documentation Center page forces one on demand (for example after
editing a Markdown file in place).

## Capabilities

- `tool/mdocs:view` — read the Documentation Center (granted to Manager by default).
- `tool/mdocs:manage` — change settings and rebuild the index (Manager by default).
- `tool/mdocs:managepages` — create, edit and delete documentation pages (Manager by default).

## Privacy

Documentation read from inside plugins holds no personal data: it is files on
disk, and nothing is recorded about who reads them.

Pages authored in Moodle do, because each records who wrote it and who last
edited it, and uploaded images record who uploaded them. The privacy provider
declares this, exports a user's pages and images on request, and on erasure
unlinks the author while keeping the page: the pages are the site's
documentation, not the author's personal content, so the link to a person is
removed rather than the documentation destroyed.

## Roadmap

The Documentation Center is delivered in phases. Planned follow-ups include
contextual "docs for the current page" help for capable users, integration with
Moodle global search, and an optional richer Markdown parser.

## Licence

GNU GPL v3 or later.
