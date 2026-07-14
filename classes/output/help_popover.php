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
 * The help icon in the navbar, and the documents behind it.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs\output;

use tool_mdocs\index_manager;

/**
 * The documentation that belongs to the page currently being looked at.
 */
class help_popover implements \renderable, \templatable {
    /** @var \moodle_page The page the reader is on. */
    protected \moodle_page $page;

    /** @var array{foryou: array[], others: array[]} The documents that belong to it. */
    protected array $matches;

    /**
     * Constructor.
     *
     * @param \moodle_page $page the page being viewed
     * @param array $matches the documents that belong to it, already split by audience
     */
    public function __construct(\moodle_page $page, array $matches) {
        $this->page = $page;
        $this->matches = $matches;
    }

    /**
     * Export data for the tool_mdocs/navbar_popover template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $foryou = $this->entries($this->matches['foryou'] ?? []);
        $others = $this->entries($this->matches['others'] ?? []);

        // Only people who can reach the Documentation Center are offered a way in;
        // a teacher reading help in a course cannot open it, and a dead link is
        // worse than no link.
        $canbrowse = has_capability('tool/mdocs:view', \context_system::instance());

        return [
            'uniqid'        => \html_writer::random_id(),
            'pagetype'      => $this->page->pagetype,
            'pagecontextid' => $this->page->context->id,
            'foryou'        => $foryou,
            'hasforyou'     => !empty($foryou),
            'others'        => $others,
            'hasothers'     => !empty($others),
            // With nothing written for this reader, the only thing in the box is
            // the other people's documentation; leaving it folded away would make
            // the help look empty.
            'othersopen'    => empty($foryou),
            'count'         => count($foryou) + count($others),
            'canbrowse'     => $canbrowse,
            'browseurl'     => $canbrowse
                ? (new \moodle_url('/admin/tool/mdocs/index.php'))->out(false)
                : '',
        ];
    }

    /**
     * Shape the matches for the template.
     *
     * @param array[] $matches documents from {@see \tool_mdocs\contextual::for_page()}
     * @return array[]
     */
    protected function entries(array $matches): array {
        $entries = [];

        foreach ($matches as $match) {
            $audiences = $match['audiences'] ?: [];
            $label = '';
            if (!empty($audiences) && $audiences[0] !== \tool_mdocs\targeting::AUDIENCE_ANY) {
                $label = get_string('audience_' . $audiences[0], 'tool_mdocs');
            }

            $entries[] = [
                'source'       => $match['source'],
                'sourceid'     => $match['sourceid'],
                'title'        => $match['title'],
                'component'    => $match['component'],
                'componentname' => $match['component'] !== ''
                    ? nav_tree::component_name($match['component'])
                    : get_string('sitedocumentation', 'tool_mdocs'),
                'audiencelabel' => $label,
                'hasaudience'  => $label !== '',
                'isfile'       => $match['source'] === index_manager::SOURCE_FILE,
            ];
        }

        return $entries;
    }
}
