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
- **Configurable inclusion rules** — include *all* Markdown files, only files inside
  named folders (e.g. `docs`), or only files with named basenames (e.g. `readme.md`).
- **mkdocs-like reader** — a component-grouped sidebar tree, rendered Markdown,
  a scroll-spy table of contents, and a filter box.
- **Search** across document titles, components and paths.
- **Secure asset serving** — images referenced from a document are served through a
  hardened endpoint (path-traversal defence, extension allowlist, capability check).
- **Cached & indexed** — a database catalogue plus a rendered-output cache keep page
  loads fast; a scheduled task (and a "Rebuild index" button) keep it fresh.

## Requirements

- Moodle 4.5 (LTS) or later, up to Moodle 5.x.
- PHP 8.1+.

## Installation

1. Copy this plugin to `admin/tool/mdocs` in your Moodle installation.
2. Visit **Site administration ▸ Notifications** to complete installation.
3. Go to **Site administration ▸ Plugins ▸ Admin tools ▸ Documentation Center**.

## Configuration

Under **Site administration ▸ Plugins ▸ Admin tools ▸ Documentation Center ▸ Settings**:

- **Which Markdown files to include** — `all`, `folders`, or `filenames`.
- **Included folders** / **Included file names** — the lists used by the folder and
  filename modes.
- **Include core subsystems** — also scan Moodle core, not just plugins.
- **Maximum file size** — skip anything larger during indexing.

After changing settings, use **Rebuild index** on the Documentation Center page.

## Capabilities

- `tool/mdocs:view` — read the Documentation Center (granted to Manager by default).
- `tool/mdocs:manage` — change settings and rebuild the index (Manager by default).

## Privacy

The plugin stores no personal data; it only indexes files shipped inside plugins.

## Roadmap

The Documentation Center is delivered in phases. Planned follow-ups include
contextual "docs for the current page" help for capable users, integration with
Moodle global search, and an optional richer Markdown parser.

## Licence

GNU GPL v3 or later.
