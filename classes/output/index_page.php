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

/**
 * Landing page: lists the components that ship documentation.
 */
class index_page implements \renderable, \templatable {
    /** @var index_manager */
    protected index_manager $index;

    /** @var bool Whether the current user can manage/rebuild. */
    protected bool $canmanage;

    /**
     * Constructor.
     *
     * @param index_manager $index catalogue accessor
     * @param bool $canmanage whether the current user may rebuild the index
     */
    public function __construct(index_manager $index, bool $canmanage) {
        $this->index = $index;
        $this->canmanage = $canmanage;
    }

    /**
     * Export data for the tool_mdocs/index_page template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $records = $this->index->get_all();
        $groups = nav_tree::build($records);
        $lastindexed = $this->index->get_last_indexed();

        return [
            'hasdocs'        => !empty($groups),
            'components'     => $groups,
            'countlabel'     => get_string('documentcount', 'tool_mdocs', count($records)),
            'nodocsmessage'  => get_string('nodocsfound_help', 'tool_mdocs'),
            'lastindexed'    => $lastindexed
                ? get_string('lastindexed', 'tool_mdocs', userdate($lastindexed))
                : get_string('neverindexed', 'tool_mdocs'),
            'canmanage'      => $this->canmanage,
            'rebuildurl'     => (new \moodle_url('/admin/tool/mdocs/index.php'))->out(false),
            'sesskey'        => sesskey(),
            'searchurl'      => (new \moodle_url('/admin/tool/mdocs/index.php'))->out(false),
        ];
    }
}
