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
 * External function registration for tool_mdocs.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'tool_mdocs_get_contextual_doc' => [
        'classname'   => 'tool_mdocs\external\get_contextual_doc',
        'description' => 'Render one document for the help icon, without leaving the page.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'tool/mdocs:viewcontextual',
    ],
    'tool_mdocs_preview_page' => [
        'classname'   => 'tool_mdocs\external\preview_page',
        'description' => 'Render Markdown for the page editor preview.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'tool/mdocs:managepages',
    ],
    'tool_mdocs_search_docs' => [
        'classname'   => 'tool_mdocs\external\search_docs',
        'description' => 'Search the documentation index by title, component or path.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'tool/mdocs:view',
    ],
];
