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
 * Publishes the in-page table of contents as a fake block in the block drawer.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs\output;

/**
 * Builds the "On this page" fake block so it lives in the collapsible block
 * drawer rather than inside the document content.
 */
class toc_block {
    /**
     * Add the table of contents to the page as a fake block, if there is one.
     *
     * The block is placed in the page's default block region, which on an admin
     * page is the (collapsible) block drawer, so the whole table of contents can
     * be hidden by closing the drawer.
     *
     * @param \moodle_page $page the current page
     * @param \renderer_base $output renderer used to build the block content
     * @param array $toc table-of-contents entries (each with text, id and level)
     */
    public static function add_to_page(\moodle_page $page, \renderer_base $output, array $toc): void {
        if (empty($toc)) {
            return;
        }

        $items = [];
        foreach ($toc as $item) {
            $items[] = [
                'text'   => $item['text'],
                'id'     => $item['id'],
                'indent' => 'tool-mdocs-toc-l' . $item['level'],
            ];
        }

        $bc = new \block_contents();
        $bc->title = get_string('tableofcontents', 'tool_mdocs');
        $bc->attributes['class'] = 'block block_tool_mdocs_toc';
        $bc->content = $output->render_from_template('tool_mdocs/toc_block', ['toc' => $items]);

        $page->blocks->add_fake_block($bc, $page->blocks->get_default_region());
    }
}
