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
 * Create or edit a user-authored documentation page.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');

$id = optional_param('id', 0, PARAM_INT);

admin_externalpage_setup('tool_mdocs_index');

$context = context_system::instance();
require_capability('tool/mdocs:managepages', $context);

$indexurl = new moodle_url('/admin/tool/mdocs/index.php');
$editurl = new moodle_url('/admin/tool/mdocs/edit.php', ['id' => $id]);
$PAGE->set_url($editurl);

$manager = new \tool_mdocs\page_manager();
$page = null;
if ($id) {
    $page = $manager->get_page($id);
    if ($page === null) {
        throw new moodle_exception('pagenotfound', 'tool_mdocs', $indexurl);
    }
}

$fileoptions = \tool_mdocs\form\page_form::file_options();
$draftitemid = file_get_submitted_draft_itemid('attachments');
file_prepare_draft_area($draftitemid, $context->id, 'tool_mdocs', 'content', $id ?: null, $fileoptions);

$toform = new stdClass();
$toform->id = $id;
$toform->attachments = $draftitemid;
if ($page !== null) {
    $toform->title = $page->title;
    $toform->component = (string) ($page->component ?? '');
    $toform->visible = $page->visible;
    $toform->content = $page->content;
}

$mform = new \tool_mdocs\form\page_form($editurl->out(false));
$mform->set_data($toform);

if ($mform->is_cancelled()) {
    redirect($indexurl);
} else if ($data = $mform->get_data()) {
    $pageid = $manager->save_page($data);
    file_save_draft_area_files($data->attachments, $context->id, 'tool_mdocs', 'content', $pageid, $fileoptions);

    $viewurl = new moodle_url('/admin/tool/mdocs/view.php', ['pageid' => $pageid]);
    redirect($viewurl, get_string('pagesaved', 'tool_mdocs'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$PAGE->set_title($id ? get_string('editpage', 'tool_mdocs') : get_string('newpage', 'tool_mdocs'));
$PAGE->set_heading($id ? get_string('editpage', 'tool_mdocs') : get_string('newpage', 'tool_mdocs'));

// Progressive enhancement: the content field is a plain textarea, and stays usable
// as one. With JavaScript it gains a formatting toolbar and a preview tab.
$PAGE->requires->js_call_amd('tool_mdocs/editor', 'init', ['id_content']);

echo $OUTPUT->header();
echo $OUTPUT->heading($id ? get_string('editpage', 'tool_mdocs') : get_string('newpage', 'tool_mdocs'));
$mform->display();
echo $OUTPUT->footer();
