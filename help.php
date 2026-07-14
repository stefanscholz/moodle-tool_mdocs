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
 * One document, on its own, for the reader who popped it out of the help window.
 *
 * The point of this page is to be a window you can put beside the one you are
 * working in. So it carries no navigation, no blocks and no navbar: it is the
 * document and nothing else.
 *
 * It cannot be view.php. That page requires tool/mdocs:view at the system, which
 * a teacher does not hold — their capability lives in their course. This page asks
 * the contextual question instead, in the context it is given, which is the same
 * question the help window itself asked before offering the document at all.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

$source = required_param('source', PARAM_ALPHA);
$sourceid = required_param('sourceid', PARAM_ALPHANUM);
$contextid = required_param('contextid', PARAM_INT);

$context = context::instance_by_id($contextid);

// Set the context before requiring login: require_login() would otherwise settle
// on the system context, and a page's context cannot be changed once it is set.
$PAGE->set_context($context);
require_login(null, false);
require_capability(\tool_mdocs\contextual::CAPABILITY, $context);

$url = new moodle_url('/admin/tool/mdocs/help.php', [
    'source' => $source,
    'sourceid' => $sourceid,
    'contextid' => $contextid,
]);

$doc = \tool_mdocs\contextual::read($source, $sourceid);

$PAGE->set_url($url);
$PAGE->set_pagelayout('popup');
$PAGE->set_title($doc['title']);
$PAGE->set_heading($doc['title']);

echo $OUTPUT->header();
echo html_writer::div($doc['html'], 'tool-mdocs-markdown tool-mdocs-help-popout');
echo $OUTPUT->footer();
