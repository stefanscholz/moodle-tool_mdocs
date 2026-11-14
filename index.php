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
 * Documentation Center landing page.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('tool_mdocs_index');

$context = context_system::instance();
require_capability('tool/mdocs:view', $context);
// Without this the heading is the site name, which says nothing about the page.
$PAGE->set_heading(get_string('pluginname', 'tool_mdocs'));

$manager = new \tool_mdocs\index_manager();
$pagemanager = new \tool_mdocs\page_manager();
$canmanage = has_capability('tool/mdocs:manage', $context);
$canmanagepages = has_capability('tool/mdocs:managepages', $context);

// Manual rebuild request.
if ($canmanage && optional_param('rebuild', 0, PARAM_BOOL) && confirm_sesskey()) {
    $count = $manager->rebuild();
    redirect(
        new moodle_url('/admin/tool/mdocs/index.php'),
        get_string('rebuiltnow', 'tool_mdocs', $count),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Build the index automatically the first time, or after a plugin set change.
if ($manager->is_stale()) {
    $manager->rebuild();
}

/** @var \tool_mdocs\output\renderer $output */
$output = $PAGE->get_renderer('tool_mdocs');

echo $OUTPUT->header();
echo $output->render(new \tool_mdocs\output\index_page($manager, $pagemanager, $canmanage, $canmanagepages));
echo $OUTPUT->footer();
