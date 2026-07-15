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
 * Put a help icon in the navbar when the page being viewed has documentation.
 *
 * This runs on every page render, so the order of the checks is deliberate: the
 * cheap ones that reject most requests come first, and the lookup that can touch
 * the database comes last and is cached. Nothing here reads a document body.
 *
 * The capability is checked against the page's own context, not the system
 * context. A teacher's role is assigned in a course, so a teacher holds no system
 * capability at all — checking there would silently hide the help from exactly
 * the people it is for. Core takes the same approach for its own documentation
 * links (see page_get_doc_link_path()).
 *
 * @param renderer_base $renderer the page renderer
 * @return string the icon, or '' when this page has nothing to say
 */
function tool_mdocs_render_navbar_output(renderer_base $renderer): string {
    if (!isloggedin() || isguestuser() || \core_user::awaiting_action()) {
        return '';
    }

    $page = $renderer->get_page();
    $context = $page->context;
    if (!$context instanceof context) {
        return '';
    }

    if (!has_capability(\tool_mdocs\contextual::CAPABILITY, $context)) {
        return '';
    }

    $matches = \tool_mdocs\contextual::for_page((string) $page->pagetype, $context);
    if (empty($matches['foryou']) && empty($matches['others'])) {
        // Nothing to say about this page. An icon that opens an empty box teaches
        // people to stop pressing it.
        return '';
    }

    $popover = new \tool_mdocs\output\help_popover($page, $matches);

    // The window the documents are read in comes with the icon, empty, rather than
    // being built when one is opened: it is a handful of static elements, and
    // rendering it here keeps opening a document down to the one request that
    // actually fetches it.
    return $renderer->render_from_template('tool_mdocs/navbar_popover', $popover->export_for_template($renderer))
        . $renderer->render_from_template('tool_mdocs/help_window', [
            'popouturl' => (new moodle_url('/admin/tool/mdocs/help.php'))->out(false),
        ]);
}

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

    // As in file.php: whoever may read the page may see the pictures in it, and a
    // teacher reading contextual help holds no system capability.
    if (!\tool_mdocs\access::can_read_assets()) {
        send_file_not_found();
    }

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
