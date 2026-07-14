# Documentation Center — user guide

The Documentation Center gives your site one place to read documentation. It
holds two kinds:

- **Documentation shipped inside your plugins.** Most plugins carry a `readme.md`
  or a `docs/` folder that nobody ever reads, because it is buried in the
  filesystem. This tool finds those files and renders them.
- **Pages you write yourself**, for the things no plugin can document: how *your*
  site is set up, who to call, the enrolment process you actually follow.

Both appear side by side, are searched together, and read the same way.

You are reading this page *through* the Documentation Center, which is the
shortest possible demonstration of the first half.

## Where to find it

**Site administration ▸ Plugins ▸ Admin tools ▸ Documentation Center**

- **Browse documentation** — the Documentation Center itself.
- **Settings** — which files get picked up.

Three capabilities control access, all granted to Manager by default:

| Capability | Allows |
| --- | --- |
| `tool/mdocs:view` | Read the Documentation Center |
| `tool/mdocs:manage` | Change the settings and rebuild the index |
| `tool/mdocs:managepages` | Create, edit and delete pages |
| `tool/mdocs:viewcontextual` | See the help icon and read the documentation for the page you are on. Granted to managers and teachers, and checked **where you are**: a teacher holds it inside their own course. |

## Help where you need it

You do not have to come here to read documentation. On any page that has some,
there is a **book icon in the navbar**. It lists what belongs to that page, opens
a document in place, and does not make you go and look for it.

What is written for *you* comes first. A teacher opening a Video Time activity is
offered the **Analysis — Teacher guide**; an administrator on the same page is
offered the administrator's guide. The rest is under **Written for other people**,
because "what is my student being told?" is a fair question and its answer is the
student guide.

If a page has no documentation, **the icon is not there**. An icon that opens an
empty box teaches people to stop pressing it.

This works with no configuration at all: a document's audience comes from its file
name (`teacher-guide.md`), and the pages it belongs to come from the plugin it
lives in. Plugin authors can be more precise if they need to — see
*conventions.md*, which is in this same folder.

Teachers can read the help without being able to open the Documentation Center
itself, which is why the document opens inside the popover rather than sending
them here.

## Writing your own pages

Press **Add page** on the Documentation Center. You need `tool/mdocs:managepages`.

A page has:

- **Title.**
- **Content**, written in Markdown — see the editor below.
- **Component** *(optional)*. Leave it as *Standalone* and the page is filed under
  **Site documentation**. Pick a plugin and the page appears **alongside that
  plugin's own documentation**, which is how you annotate a plugin with the
  specifics of your installation. A page about your Video Time conventions sits
  with the Video Time docs, where somebody looking for it will actually be.
- **Visibility.** *Published* is visible to every reader. *Draft* is visible only
  to people who can manage pages, and is flagged **Draft** in the list, so you can
  work on a page without publishing it half-finished.
- **Written for.** Who the page is aimed at. It is offered first to those people
  when they open the help icon.
- **Show on these pages.** One Moodle page type per line, for example
  `mod-videotime-view`. A `*` matches anything, so `mod-videotime-*` is every
  Video Time page. Leave it empty and the page appears wherever the plugin you
  filed it under appears — so a page filed against Video Time turns up on Video
  Time's pages by itself.
- **Images.** Upload them on the form, then reference them in the text.

**Edit** and **Delete** links sit next to each page you have written.

Pages you write are searched exactly like the plugin documentation, so a page
about your enrolment process is found by searching for a word inside it.

### The editor

The content field has a formatting toolbar and two tabs.

**Write** gives you the toolbar: **bold**, *italic*, heading, quote, code, link,
image, bulleted list and numbered list. The buttons work on whatever you have
selected, and they toggle — pressing **bold** on text that is already bold takes
the markers off again. The list, heading and quote buttons apply to every line
your selection touches, so you can select five lines and make them all bullets in
one press.

**Preview** shows the page exactly as it will be published. It is rendered by the
same code that renders a saved page, so what you see is genuinely what you get —
not an approximation from a different renderer. Images you have uploaded but not
yet saved appear in the preview too.

To place an image, upload it under **Images**, then use the image button, or write
it by hand:

```
![Description of the picture](@@PLUGINFILE@@/yourimage.png)
```

If you are writing Markdown by hand, one thing is worth knowing: **a list must
have a blank line before it.** This is not a list —

```
Here are the steps:
- first
- second
```

— it is one paragraph reading "Here are the steps: - first - second". This is:

```
Here are the steps:

- first
- second
```

The toolbar buttons insert that blank line for you.

## Choosing which plugin files are indexed

Under **Settings**, three lists decide which Markdown files inside plugins are
indexed. Pages you write yourself are always included, whatever the lists say.

| Setting | Behaviour |
| --- | --- |
| **Included folders** | Everything inside a matching folder, at any depth. Default: `docs`. |
| **Included file names** | A file whose own name matches, wherever it is found. Default: `readme.md`, `index.md`. |
| **Never include** | A file whose own name matches, or that sits inside a matching folder. Empty by default. |

The two inclusion lists **add up**. A file is indexed when it matches *either* of
them, so the rule most sites want is simply both lists at once:

```
Included folders:     docs
Included file names:  readme.md
                      changes.md
Never include:        install.md
```

That indexes everything in `docs`, plus every `readme.md` and `changes.md`, and
never an `install.md` — because **Never include** wins over everything else. That
is what it is for: keeping one unwanted file out without having to narrow the
rules for all the others.

All three lists take **one entry per line** and are case-insensitive, so
`README.md` matches a `readme.md` rule.

**Include every Markdown file** is the sledgehammer: it indexes every `.md` file
in every plugin and ignores both inclusion lists. **Never include** still applies,
so "everything except our `install.md`" is a single checkbox and a single line.

Two more settings:

- **Include core subsystems** — also scan Moodle core, not just plugins. Off by
  default, because core ships a lot of Markdown aimed at developers.
- **Maximum file size** — anything larger is skipped during indexing.

Some directories are never scanned, because they hold other people's
documentation rather than the plugin's own: `.git`, `node_modules`, `vendor`,
`build`, `fixtures`, `yui` and `phpunit`.

### Settings changes apply immediately

Changing any of the rules above marks the index stale, and it rebuilds on your
next visit. You do **not** need to press *Rebuild index* afterwards.

That is the easiest change to see for yourself:

1. Go to **Settings** and tick *Include every Markdown file*. Save.
2. Go to **Browse documentation** — without pressing anything else.
3. The document count jumps, because the index rebuilt itself on arrival.
4. Untick it and the count shrinks again.

## Searching

The search box searches the **text of every document and every page**, not just
titles. Start typing (two characters minimum) and results appear as you type.

Each hit shows the title, where it came from, and a **snippet of the matching
text** in context, so you can tell which result you want before opening it.

This means you can search for things that appear in no title at all — a setting
name, a database table, a capability, a phone number in one of your own pages.
Code identifiers survive indexing intact, so searching `tool_mdocs_index` finds
the documents mentioning that table, underscores and all.

How it matches:

- It is **substring matching**, not a full search engine. There is no stemming and
  no ranking, so `attendance` will not find `attend`.
- Results are capped at 30.
- Indexing reads up to 256 KB of each plugin file and stores up to 64 KB of text
  for searching. Real documentation is nowhere near that, but a very large
  generated file is only searchable up to that point.
- Draft pages are only searchable by people who can manage pages.

## Reading a document

**The sidebar** lists everything, grouped by the plugin it belongs to, with a
filter box. What you are reading is highlighted.

**The content** is the rendered Markdown. Relative links between plugin documents
work: a link to another `.md` file opens it in the viewer, and images are served
through a hardened endpoint that only serves files from inside that plugin's own
directory, to holders of `tool/mdocs:view`.

**"On this page"** is the table of contents, built from the headings. It lives in
the **block drawer** on the right, so you can collapse it away entirely by closing
the drawer when you want the width back. The current heading highlights as you
scroll.

## Subplugins are folded into their parent

Some plugins ship subplugins, and a subplugin lives *inside* its parent's
directory. Video Time is the clearest example: `videotimeplugin_analysis` sits at
`mod/videotime/plugin/analysis`.

Their documents are shown as sub-sections of the plugin they belong to, rather
than as separate top-level entries:

```
Video Time
  Analysis
    Administrator guide
    Teacher guide
  Video Time Pro
    Overview
```

Each file is attributed to exactly one component — the subplugin that owns it — so
nothing is listed twice.

## Keeping the index fresh

The catalogue of plugin documentation rebuilds itself when it needs to:

- **On a settings change**, as described above.
- **When plugins are installed, upgraded or removed.**
- **Nightly**, via the scheduled task *Rebuild the documentation index*
  (Site administration ▸ Server ▸ Scheduled tasks).

The **Rebuild index** button forces one on demand. You need it in one case the
checks above cannot detect: when a Markdown file inside a plugin has been **edited
in place** without the plugin's version changing.

Pages you write are never affected by any of this. They are saved directly and
appear immediately.

## Privacy

Documentation read from inside plugins holds no personal data: it is files on
disk, and nothing is recorded about who reads them.

**Pages written in Moodle do hold personal data**, because each one records who
wrote it and who last edited it, and any image uploaded to a page records who
uploaded it.

That data is covered by Moodle's privacy tools:

- A **data export** for a user includes every page they wrote or edited, with its
  text, its timestamps and its images.
- A **data deletion** request removes the person from the page, but **keeps the
  page**. The pages are your site's documentation rather than anybody's personal
  content, so deleting them because the person who wrote them has left would
  destroy something that belongs to the site. The link to a person is removed
  either way, which is the part that is personal.
