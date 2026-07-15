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
 * Capability definitions for tool_mdocs.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // View the Documentation Center and read documents.
    'tool/mdocs:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Configure inclusion rules and rebuild the index.
    'tool/mdocs:manage' => [
        'riskbitmask'  => RISK_CONFIG,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Create, edit and delete user-authored documentation pages.
    'tool/mdocs:managepages' => [
        'riskbitmask'  => RISK_XSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // See the help icon, and read the documentation that belongs to the page you
    // are on.
    //
    // This is deliberately not tool/mdocs:view. That one is a system capability,
    // and a teacher's role is assigned in a course, so a teacher never holds it —
    // widening its archetypes would have changed nothing. This capability is
    // checked against the context of the page being viewed, which walks up the
    // tree: a manager assigned at the system holds it everywhere, and a teacher
    // holds it inside their own course and nowhere else. It is the same approach
    // core takes for its own documentation links (moodle/site:doclinks).
    'tool/mdocs:viewcontextual' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'manager'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
        ],
    ],
];
