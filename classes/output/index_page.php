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
 * Renderable for the Documentation Center landing page.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs\output;

use tool_mdocs\index_manager;
use tool_mdocs\page_manager;

/**
 * Landing page: lists the components that ship documentation.
 */
class index_page implements \renderable, \templatable {
    /** @var index_manager */
    protected index_manager $index;

    /** @var page_manager */
    protected page_manager $pages;

    /** @var bool Whether the current user can manage/rebuild. */
    protected bool $canmanage;

    /** @var bool Whether the current user can create/edit pages. */
    protected bool $canmanagepages;

    /**
     * Constructor.
     *
     * @param index_manager $index catalogue accessor
     * @param page_manager $pages page accessor
     * @param bool $canmanage whether the current user may rebuild the index
     * @param bool $canmanagepages whether the current user may create/edit pages
     */
    public function __construct(index_manager $index, page_manager $pages, bool $canmanage, bool $canmanagepages) {
        $this->index = $index;
        $this->pages = $pages;
        $this->canmanage = $canmanage;
        $this->canmanagepages = $canmanagepages;
    }

    /**
     * Export data for the tool_mdocs/index_page template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $records = $this->index->get_all();
        $pagerecords = $this->pages->get_pages($this->canmanagepages);
        $groups = nav_tree::build($records, null, $pagerecords, null, $this->canmanagepages);
        $lastindexed = $this->index->get_last_indexed();
        $total = count($records) + count($pagerecords);

        return [
            'hasdocs'        => !empty($groups),
            'components'     => $groups,
            'countlabel'     => get_string('documentcount', 'tool_mdocs', $total),
            'nodocsmessage'  => get_string('nodocsfound_help', 'tool_mdocs'),
            'lastindexed'    => $lastindexed
                ? get_string('lastindexed', 'tool_mdocs', userdate($lastindexed))
                : get_string('neverindexed', 'tool_mdocs'),
            'canmanage'      => $this->canmanage,
            'canmanagepages' => $this->canmanagepages,
            'addpageurl'     => (new \moodle_url('/admin/tool/mdocs/edit.php'))->out(false),
            'draftbadge'     => get_string('draftbadge', 'tool_mdocs'),
            'rebuildurl'     => (new \moodle_url('/admin/tool/mdocs/index.php'))->out(false),
            'sesskey'        => sesskey(),
            'searchurl'      => (new \moodle_url('/admin/tool/mdocs/index.php'))->out(false),
        ];
    }
}
