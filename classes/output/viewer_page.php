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
use tool_mdocs\markdown;

/**
 * Viewer page: sidebar tree, rendered document and in-page table of contents.
 */
class viewer_page implements \renderable, \templatable {

    /** @var index_manager */
    protected index_manager $index;

    /** @var \stdClass The active document record. */
    protected \stdClass $doc;

    /** @var \context Context used for output cleaning. */
    protected \context $context;

    /**
     * Constructor.
     *
     * @param index_manager $index catalogue accessor
     * @param \stdClass $doc the active tool_mdocs_index record
     * @param \context $context context for output cleaning
     */
    public function __construct(index_manager $index, \stdClass $doc, \context $context) {
        $this->index = $index;
        $this->doc = $doc;
        $this->context = $context;
    }

    /**
     * Export data for the tool_mdocs/viewer_page template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $rendered = markdown::render($this->doc, $this->context);

        $toc = [];
        foreach ($rendered['toc'] as $item) {
            $toc[] = [
                'text'   => $item['text'],
                'id'     => $item['id'],
                'level'  => $item['level'],
                'indent' => 'tool-mdocs-toc-l' . $item['level'],
            ];
        }

        $tree = nav_tree::build($this->index->get_all(), $this->doc->docid);

        return [
            'title'         => $this->doc->title,
            'componentname' => nav_tree::component_name($this->doc->component),
            'component'     => $this->doc->component,
            'contenthtml'   => $rendered['html'],
            'toc'           => $toc,
            'hastoc'        => !empty($toc),
            'toclabel'      => get_string('tableofcontents', 'tool_mdocs'),
            'tree'          => $tree,
            'indexurl'      => (new \moodle_url('/admin/tool/mdocs/index.php'))->out(false),
            'sourcelabel'   => get_string('sourcepath', 'tool_mdocs', $this->doc->component . '/' . $this->doc->relpath),
        ];
    }
}
