<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Renderable for the single-document viewer.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs\output;

use tool_mdocs\index_manager;
use tool_mdocs\page_manager;

/**
 * Viewer page: sidebar tree and the rendered document.
 *
 * Handles both file-based documents and user-authored pages. The table of
 * contents is published separately as a fake block by the calling script.
 */
class viewer_page implements \renderable, \templatable {
    /** @var index_manager */
    protected index_manager $index;

    /** @var page_manager */
    protected page_manager $pages;

    /** @var array{html: string, toc: array} Pre-rendered document HTML and table of contents. */
    protected array $rendered;

    /** @var bool Whether the viewer can edit/delete pages. */
    protected bool $canmanagepages;

    /** @var \stdClass|null The active file document record. */
    protected ?\stdClass $filedoc;

    /** @var \stdClass|null The active user page record. */
    protected ?\stdClass $userpage;

    /**
     * Constructor.
     *
     * @param index_manager $index catalogue accessor
     * @param page_manager $pages page accessor
     * @param array $rendered pre-rendered document (html + toc)
     * @param bool $canmanagepages whether the viewer may edit/delete pages
     * @param \stdClass|null $filedoc the active file document, if viewing one
     * @param \stdClass|null $userpage the active user page, if viewing one
     */
    public function __construct(
        index_manager $index,
        page_manager $pages,
        array $rendered,
        bool $canmanagepages,
        ?\stdClass $filedoc = null,
        ?\stdClass $userpage = null
    ) {
        $this->index = $index;
        $this->pages = $pages;
        $this->rendered = $rendered;
        $this->canmanagepages = $canmanagepages;
        $this->filedoc = $filedoc;
        $this->userpage = $userpage;
    }

    /**
     * Build a viewer for a file-based document.
     *
     * @param index_manager $index
     * @param page_manager $pages
     * @param \stdClass $doc
     * @param array $rendered
     * @param bool $canmanagepages
     * @return self
     */
    public static function for_file(
        index_manager $index,
        page_manager $pages,
        \stdClass $doc,
        array $rendered,
        bool $canmanagepages
    ): self {
        return new self($index, $pages, $rendered, $canmanagepages, $doc, null);
    }

    /**
     * Build a viewer for a user-authored page.
     *
     * @param index_manager $index
     * @param page_manager $pages
     * @param \stdClass $page
     * @param array $rendered
     * @param bool $canmanagepages
     * @return self
     */
    public static function for_page(
        index_manager $index,
        page_manager $pages,
        \stdClass $page,
        array $rendered,
        bool $canmanagepages
    ): self {
        return new self($index, $pages, $rendered, $canmanagepages, null, $page);
    }

    /**
     * Export data for the tool_mdocs/viewer_page template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $data = $this->userpage !== null ? $this->page_meta() : $this->file_meta();

        $tree = nav_tree::build(
            $this->index->get_all(),
            $this->filedoc?->docid,
            $this->pages->get_pages($this->canmanagepages),
            $this->userpage?->id,
            $this->canmanagepages
        );

        return $data + [
            'contenthtml' => $this->rendered['html'],
            'tree'        => $tree,
            'indexurl'    => (new \moodle_url('/admin/tool/mdocs/index.php'))->out(false),
        ];
    }

    /**
     * Template fields specific to a file document.
     *
     * @return array
     */
    protected function file_meta(): array {
        $doc = $this->filedoc;
        return [
            'title'         => $doc->title,
            'componentname' => nav_tree::component_name($doc->component),
            'component'     => $doc->component,
            'ispage'        => false,
            'sourcelabel'   => get_string('sourcepath', 'tool_mdocs', $doc->component . '/' . $doc->relpath),
        ];
    }

    /**
     * Template fields specific to a user page.
     *
     * @return array
     */
    protected function page_meta(): array {
        $page = $this->userpage;
        $componentname = empty($page->component)
            ? get_string('sitedocumentation', 'tool_mdocs')
            : nav_tree::component_name($page->component);

        $editor = \core_user::get_user($page->usermodified);
        $lastedited = get_string('lastedited', 'tool_mdocs', (object) [
            'user' => $editor ? fullname($editor) : '-',
            'date' => userdate($page->timemodified),
        ]);

        $data = [
            'title'          => $page->title,
            'componentname'  => $componentname,
            'ispage'         => true,
            'isdraft'        => empty($page->visible),
            'draftbadge'     => get_string('draftbadge', 'tool_mdocs'),
            'lastedited'     => $lastedited,
            'canmanagepages' => $this->canmanagepages,
        ];
        if ($this->canmanagepages) {
            $data['editurl'] = (new \moodle_url('/admin/tool/mdocs/edit.php', ['id' => $page->id]))->out(false);
            $data['deleteurl'] = (new \moodle_url('/admin/tool/mdocs/delete.php', ['id' => $page->id]))->out(false);
        }
        return $data;
    }
}
