# Documentation Center — user guide

The Documentation Center collects the Markdown documentation that is already
shipped **inside** your installed plugins and presents it as a browsable site,
without a build step and without leaving Moodle.

Most plugins ship a `readme.md` or a `docs/` folder that nobody ever reads
because it is buried in the filesystem. This tool finds those files, indexes
them, and renders them with navigation, a table of contents and search.

You are reading this page *through* the Documentation Center, which is the
shortest possible demonstration of what it does.

## Where to find it

**Site administration ▸ Plugins ▸ Admin tools ▸ Documentation Center**

Two pages live there:

- **Browse documentation** — the Documentation Center itself.
- **Settings** — which files get picked up.

Access is controlled by two capabilities, both granted to Manager by default:

| Capability | Allows |
| --- | --- |
| `tool/mdocs:view` | Read the Documentation Center |
| `tool/mdocs:manage` | Change the settings and rebuild the index |

## Choosing which files are indexed

Under **Settings**, **Which Markdown files to include** offers three rules.

| Mode | Behaviour |
| --- | --- |
| Only files inside specific folders | *(default)* A file is included when any folder in its path matches. Default: `docs`. |
| Only files with specific names | A file is included when its name matches. Default: `readme.md`, `index.md`. |
| All Markdown files | Every `.md` file in every plugin. |

Folder and file-name lists take **one entry per line** and are case-insensitive,
so `README.md` matches a `readme.md` rule.

Two more settings:

- **Include core subsystems** — also scan Moodle core, not just plugins. Off by
  default, because core ships a lot of Markdown that is aimed at developers.
- **Maximum file size** — anything larger is skipped during indexing.

Some directories are never scanned, because they contain other people's
documentation rather than the plugin's own: `.git`, `node_modules`, `vendor`,
`build`, `fixtures`, `yui` and `phpunit`.

### Settings changes now apply immediately

Changing any of the rules above marks the index stale, and it rebuilds on your
next visit to the Documentation Center. You do **not** need to press *Rebuild
index* afterwards.

That is worth trying, because it is the easiest change to see:

1. Go to **Settings** and switch the mode to *All Markdown files*. Save.
2. Go to **Browse documentation** — without pressing anything else.
3. The document count jumps, because the index rebuilt itself on arrival.
4. Switch back to *Only files inside specific folders* and it shrinks again.

## Searching inside documents

The search box on the Documentation Center searches the **text of every
document**, not just titles. Start typing (two characters minimum) and results
appear as you type.

Each hit shows the document title, its component, and a **snippet of the
matching text** with the surrounding context, so you can tell which result you
actually want before opening it.

This means you can search for things that appear nowhere in a title — a setting
name, a database table, a capability, a task name. Code identifiers survive
indexing intact, so searching for `tool_mdocs_index` finds the documents that
mention that table, underscores and all.

A few things to know about how it matches:

- It is **substring matching**, not a full search engine. There is no stemming
  and no relevance ranking, so `attendance` will not find `attend`.
- Results are capped at 30.
- Indexing reads up to 256 KB of each file and stores up to 64 KB of text for
  searching. Documentation files are nowhere near that, but a very large
  generated file will only be searchable up to that point.

## Reading a document

Open any document and you get three things.

**The sidebar** lists every indexed document, grouped by the plugin it belongs
to, with a filter box at the top. The document you are reading is highlighted.

**The content** is the rendered Markdown. Relative links between documents work:
a link to another `.md` file opens it in the viewer, and images are served
through a hardened endpoint that only serves files from inside the plugin's own
directory, to holders of `tool/mdocs:view`.

**"On this page"** is the table of contents for the current document, built from
its headings. It lives in the **block drawer** on the right — so you can collapse
the whole thing away by closing the drawer when you want the width back. As you
scroll, the current heading is highlighted.

## Subplugins are folded into their parent

Some plugins ship subplugins, and subplugins live *inside* the parent's
directory. Video Time is the clearest example: `videotimeplugin_analysis` sits at
`mod/videotime/plugin/analysis`.

Those documents are shown as sub-sections of the plugin they belong to, rather
than as separate top-level entries:

```
Video Time
  Analysis
    Administrator guide
    Teacher guide
  Video Time Pro
    Overview
```

Each file is attributed to exactly one component — the subplugin that owns it —
so nothing is listed twice.

## Keeping the index fresh

The catalogue rebuilds itself when it needs to:

- **On a settings change** — as described above.
- **When plugins are installed, upgraded or removed.**
- **Nightly**, via the scheduled task *Rebuild the documentation index*
  (Site administration ▸ Server ▸ Scheduled tasks).

The **Rebuild index** button on the Documentation Center forces a rebuild on
demand. You need it in one situation the checks above cannot detect: when you
have **edited a Markdown file in place** without changing the plugin's version.

## Privacy

The plugin stores no personal data. It indexes files that ship inside plugins,
nothing about the people reading them.
