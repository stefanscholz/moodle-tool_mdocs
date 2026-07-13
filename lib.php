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
 * Library functions for tool_mdocs.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serve images embedded in a user-authored documentation page.
 *
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param context $context the context
 * @param string $filearea the file area
 * @param array $args the remaining bits of the file path
 * @param bool $forcedownload whether to force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file was not found, does not return otherwise
 */
function tool_mdocs_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel !== CONTEXT_SYSTEM) {
        return false;
    }
    if ($filearea !== \tool_mdocs\page_manager::FILEAREA) {
        return false;
    }

    require_login();
    require_capability('tool/mdocs:view', $context);

    $itemid = (int) array_shift($args);

    $page = (new \tool_mdocs\page_manager())->get_page($itemid);
    if ($page === null) {
        send_file_not_found();
    }
    if (empty($page->visible)) {
        require_capability('tool/mdocs:managepages', $context);
    }

    $filename = array_pop($args);
    $filepath = empty($args) ? '/' : '/' . implode('/', $args) . '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'tool_mdocs', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
}
