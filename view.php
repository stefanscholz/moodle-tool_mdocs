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
 * Documentation Center single-document viewer.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$component = required_param('component', PARAM_COMPONENT);
$docid = required_param('doc', PARAM_ALPHANUM);

$pageurl = new moodle_url('/admin/tool/mdocs/view.php', ['component' => $component, 'doc' => $docid]);
admin_externalpage_setup('tool_mdocs_index');
$PAGE->set_url($pageurl);

$context = context_system::instance();
require_capability('tool/mdocs:view', $context);

$manager = new \tool_mdocs\index_manager();
$doc = $manager->get_document($docid);
if ($doc === null || $doc->component !== $component) {
    throw new moodle_exception('documentnotfound', 'tool_mdocs', new moodle_url('/admin/tool/mdocs/index.php'));
}

$PAGE->set_title($doc->title);
$PAGE->set_heading($doc->title);
$PAGE->navbar->add($doc->title, $pageurl);

/** @var \tool_mdocs\output\renderer $output */
$output = $PAGE->get_renderer('tool_mdocs');
$rendered = \tool_mdocs\markdown::render($doc, $context);

// Publish the "On this page" table of contents as a fake block, so it lives in
// the collapsible block drawer and can be hidden entirely by closing it.
\tool_mdocs\output\toc_block::add_to_page($PAGE, $output, $rendered['toc']);

echo $OUTPUT->header();
echo $output->render(new \tool_mdocs\output\viewer_page($manager, $doc, $rendered));
echo $OUTPUT->footer();
