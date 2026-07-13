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
 * Database upgrade steps for tool_mdocs.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the tool_mdocs database.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool
 */
function xmldb_tool_mdocs_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026071300) {
        // Add the plain-text content column used for full-text search.
        $table = new xmldb_table('tool_mdocs_index');
        $field = new xmldb_field('content', XMLDB_TYPE_TEXT, null, null, null, null, null, 'title');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // The new column is populated on the next index rebuild; force one soon
        // by clearing the last-indexed marker so the catalogue is treated as stale.
        unset_config('lastversionshash', 'tool_mdocs');

        upgrade_plugin_savepoint(true, 2026071300, 'tool', 'mdocs');
    }

    return true;
}
