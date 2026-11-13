# Writing documentation a plugin can surface

This is for people writing documentation **inside a plugin** — ours or anybody
else's. It describes how the Documentation Center decides two things:

- **which page** a document belongs to, so it can be offered there;
- **who** it is written for, so it is offered to them first.

The short version: **you probably do not have to do anything.** Both answers are
guessed from things your plugin already has. Read the last section only if the
guess is wrong.

## The help icon

On any page, somebody who can see contextual help gets a book icon in the navbar.
It lists the documentation that belongs to that page, with what is written for
them at the top and everything else one click away, and it opens a document in
place without leaving the page.

If a page has no documentation, **the icon is not there at all**. An icon that
opens an empty box teaches people to stop pressing it.

## What is guessed, and from what

### Which page: your plugin's directory

Moodle builds a page's type from the path of the script that drew it, with the
slashes turned into hyphens. `mod/videotime/view.php` is `mod-videotime-view`. So
your plugin's own directory already says what its pages are called, and every
document in `mod/videotime` is offered on every Video Time page.

| Your plugin | Its documents appear on |
| --- | --- |
| `mod/videotime` | `mod-videotime-*` — every Video Time page |
| `admin/tool/timings` | `admin-tool-timings-*` |
| `local/dash` | `local-dash-*` |

**A subplugin borrows its parent's pages.** `videotimeplugin_analysis` lives in
`mod/videotime/plugin/analysis`, but nobody ever visits a page there — its report
appears *inside* Video Time. So its documents are offered on `mod-videotime-*`,
where the reader actually is.

### Who for: the file name

| File | Written for |
| --- | --- |
| `docs/admin-guide.md` | administrators |
| `docs/manager-guide.md` | managers |
| `docs/teacher-guide.md` | teachers |
| `docs/student-guide.md` | students |
| `docs/developer-guide.md` | developers |
| `docs/teacher/anything.md` | teachers — a folder works too |
| `README.md`, `docs/timetable-integration.md` | **everyone** |

A name that says nothing means the document is for everyone, which is the right
answer for a readme or a topic document. Nothing is hidden for want of a
convention.

The audience is taken from the **file name**, never from the title. Titles vary —
`# Admin guide — installation` and `# Analysis — Administrator guide` are both in
use today — and a rule that depended on them would be a rule that broke.

## Being precise: front matter

When the guess is wrong, say so. Open the file with a block fenced by `---`:

```markdown
---
audience: teacher, manager
pagetypes:
  - mod-videotime-view
exclude: false
order: 20
edition: Pro
requires: videotimeplugin_repository
---

# Watching a video
```

| Key | Means |
| --- | --- |
| `audience` | Who it is for: `admin`, `manager`, `teacher`, `student`, `developer`, `any`. Several are allowed. Overrides the file name. |
| `pagetypes` | Which pages it belongs to. `*` matches anything, so `mod-videotime-*` is every Video Time page and `mod-videotime-view` is only the one. Overrides the directory. |
| `exclude` | `true` keeps the document out of contextual help entirely. It is still in the Documentation Center. |
| `order` | A whole number that sets the document's place among the documents of its plugin, lowest first, in the sidebar and in contextual help. Without it, documents are listed by title. |
| `edition` | The edition the whole document needs, such as `Pro`. Shown as a pill next to the title, and announced with the title by screen readers. Write it as it is sold. |
| `requires` | The components the document is about, such as `skilladdon_decay`. Several are allowed. If one of them is not installed, the document is left out: it is not listed, not found by search, not offered as contextual help, and `mdocs:` links to it become plain text. It appears as soon as the component is installed. Use it for documents about a part of a higher edition, together with `edition`; leave it out of documents that only mention such a part. |

Anything else in the block is ignored, and a mistake in it costs you the hint, not
the document.

The block never reaches the reader: it is taken off before the document is
indexed, searched or rendered.

## Linking to another plugin's documentation

A relative link such as `[Capabilities](capabilities.md)` stays inside its own plugin. To link
to a document of another plugin, name that plugin's component and the path inside it:

```markdown
[Decay rules](mdocs:skilladdon_decay/docs/decay-rules.md#rule-types)
```

When that document is on the site, the link opens it. When it is not, because the plugin is not
installed, only the link text is shown, so readers never meet a dead link. Write the text so it
still makes sense without the link.

## Editions, callouts and tables

- **A single setting that needs a higher edition:** write the edition in parentheses right after
  the bold label, `**Mode** (Pro)`. It is shown as a small pill. This works for `Plus`, `Pro` and
  `Premium`, and only directly after bold text.
- **Callouts:** a quote that starts with a bold `Note:`, `Important:` or `Tip:` is shown as a
  coloured box, for example `> **Note:** Changes apply to new attempts only.` The label stays in the
  text, so the meaning does not depend on the colour.
- **Tables:** put a heading above each table. A wide table scrolls in a box that is named after that
  heading and can be scrolled with the keyboard.

## Two things worth knowing when you write the body

**A list needs a blank line before it.** This is not a list —

```
Here are the steps:
- first
```

— it is one paragraph reading "Here are the steps: - first". Moodle's Markdown
does not let a list interrupt a paragraph, even though GitHub's does. Leave a
blank line.

**Start with a level-one heading.** It becomes the document's title everywhere it
is listed. Without one you get a title made out of the file name.

## Where documents are looked for

By default, anything in a `docs/` folder. A site can widen that to every `.md`
file, or narrow it to particular names, under *Site administration ▸ Plugins ▸
Admin tools ▸ Documentation Center ▸ Settings*.

These directories are never scanned, because what is in them is somebody else's
documentation: `.git`, `node_modules`, `vendor`, `build`, `fixtures`, `yui`,
`phpunit`.

## A worked example

`videotimeplugin_analysis` ships exactly this, and has never been changed for the
Documentation Center:

```
mod/videotime/plugin/analysis/docs/admin-guide.md
mod/videotime/plugin/analysis/docs/teacher-guide.md
```

No front matter, no configuration. A teacher opening a Video Time activity gets
the book icon, and behind it the **Analysis — Teacher guide**, with the
administrator's guide underneath in *Written for other people*. An administrator
on the same page gets both: staff guides add up downwards, so an administrator also
gets the manager and teacher guides, and a manager the teacher guides. Only the
student guide stays under *Written for other people* for staff.

That is the whole convention: put the documents in `docs/`, name them after the
person who needs them, and they turn up where that person is.
