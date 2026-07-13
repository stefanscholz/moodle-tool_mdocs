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
 * Delete a user-authored documentation page.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$id = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

admin_externalpage_setup('tool_mdocs_index');

$context = context_system::instance();
require_capability('tool/mdocs:managepages', $context);

$indexurl = new moodle_url('/admin/tool/mdocs/index.php');
$PAGE->set_url(new moodle_url('/admin/tool/mdocs/delete.php', ['id' => $id]));

$manager = new \tool_mdocs\page_manager();
$page = $manager->get_page($id);
if ($page === null) {
    redirect($indexurl, get_string('pagenotfound', 'tool_mdocs'), null, \core\output\notification::NOTIFY_ERROR);
}

if ($confirm && confirm_sesskey()) {
    $manager->delete_page($id);
    redirect($indexurl, get_string('pagedeleted', 'tool_mdocs'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$confirmurl = new moodle_url('/admin/tool/mdocs/delete.php', ['id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]);

echo $OUTPUT->header();
echo $OUTPUT->confirm(get_string('deletepageconfirm', 'tool_mdocs', $page->title), $confirmurl, $indexurl);
echo $OUTPUT->footer();
