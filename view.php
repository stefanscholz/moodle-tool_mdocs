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

$pageid = optional_param('pageid', 0, PARAM_INT);
$component = optional_param('component', '', PARAM_COMPONENT);
$docid = optional_param('doc', '', PARAM_ALPHANUM);

admin_externalpage_setup('tool_mdocs_index');

$context = context_system::instance();
require_capability('tool/mdocs:view', $context);

$indexurl = new moodle_url('/admin/tool/mdocs/index.php');
$manager = new \tool_mdocs\index_manager();
$pagemanager = new \tool_mdocs\page_manager();
$canmanagepages = has_capability('tool/mdocs:managepages', $context);

if ($pageid) {
    // User-authored page.
    $userpage = $pagemanager->get_page($pageid);
    if ($userpage === null) {
        throw new moodle_exception('pagenotfound', 'tool_mdocs', $indexurl);
    }
    if (empty($userpage->visible)) {
        require_capability('tool/mdocs:managepages', $context);
    }
    $pageurl = new moodle_url('/admin/tool/mdocs/view.php', ['pageid' => $pageid]);
    $title = $userpage->title;
    $rendered = \tool_mdocs\markdown::render_page($userpage, $context);
    $renderable = \tool_mdocs\output\viewer_page::for_page(
        $manager, $pagemanager, $userpage, $rendered, $canmanagepages);
} else {
    // File-based document.
    if ($component === '' || $docid === '') {
        throw new moodle_exception('documentnotfound', 'tool_mdocs', $indexurl);
    }
    $doc = $manager->get_document($docid);
    if ($doc === null || $doc->component !== $component) {
        throw new moodle_exception('documentnotfound', 'tool_mdocs', $indexurl);
    }
    $pageurl = new moodle_url('/admin/tool/mdocs/view.php', ['component' => $component, 'doc' => $docid]);
    $title = $doc->title;
    $rendered = \tool_mdocs\markdown::render($doc, $context);
    $renderable = \tool_mdocs\output\viewer_page::for_file(
        $manager, $pagemanager, $doc, $rendered, $canmanagepages);
}

$PAGE->set_url($pageurl);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->navbar->add($title, $pageurl);

/** @var \tool_mdocs\output\renderer $output */
$output = $PAGE->get_renderer('tool_mdocs');

// Publish the "On this page" table of contents as a fake block, so it lives in
// the collapsible block drawer and can be hidden entirely by closing it.
\tool_mdocs\output\toc_block::add_to_page($PAGE, $output, $rendered['toc']);

echo $OUTPUT->header();
echo $output->render($renderable);
echo $OUTPUT->footer();
