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

    if ($oldversion < 2026071400) {
        // Add the table for user-authored documentation pages.
        $table = new xmldb_table('tool_mdocs_page');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('content', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('contentformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '4');
        $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('visible', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('component', XMLDB_INDEX_NOTUNIQUE, ['component']);
        $table->add_index('visible', XMLDB_INDEX_NOTUNIQUE, ['visible']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026071400, 'tool', 'mdocs');
    }

    if ($oldversion < 2026071500) {
        // Contextual help: who each document is for, and which pages it belongs to.
        $index = new xmldb_table('tool_mdocs_index');
        $field = new xmldb_field('audience', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'pagetypes');
        if (!$dbman->field_exists($index, $field)) {
            $dbman->add_field($index, $field);
        }

        $page = new xmldb_table('tool_mdocs_page');
        $field = new xmldb_field('pagetypes', XMLDB_TYPE_TEXT, null, null, null, null, null, 'component');
        if (!$dbman->field_exists($page, $field)) {
            $dbman->add_field($page, $field);
        }
        $field = new xmldb_field('audience', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'pagetypes');
        if (!$dbman->field_exists($page, $field)) {
            $dbman->add_field($page, $field);
        }

        // The pattern lookup runs on every page render, so the patterns get an
        // indexed column of their own rather than a LIKE over a comma separated list.
        $target = new xmldb_table('tool_mdocs_target');
        $target->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $target->add_field('source', XMLDB_TYPE_CHAR, '4', null, XMLDB_NOTNULL, null, null);
        $target->add_field('sourceid', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        $target->add_field('pagetype', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $target->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $target->add_index('pagetype', XMLDB_INDEX_NOTUNIQUE, ['pagetype']);
        $target->add_index('source-sourceid', XMLDB_INDEX_NOTUNIQUE, ['source', 'sourceid']);

        if (!$dbman->table_exists($target)) {
            $dbman->create_table($target);
        }

        // Targets are worked out during a scan, so the catalogue has to be rebuilt
        // before contextual help knows about anything.
        unset_config('lastsettingshash', 'tool_mdocs');

        upgrade_plugin_savepoint(true, 2026071500, 'tool', 'mdocs');
    }

    if ($oldversion < 2026071600) {
        // The inclusion rules used to be a choice: folders, or file names, or
        // everything. They now add up, and an exclusion list overrides them.
        //
        // Preserve what each site was actually getting, rather than what its
        // settings now happen to say. Under the old rules a site in "folders" mode
        // still had readme.md sitting in the file-name setting, ignored; leaving it
        // there would silently start indexing every readme on the site the moment
        // the lists began to add up.
        $mode = get_config('tool_mdocs', 'inclusionmode');

        if ($mode === 'all') {
            set_config('includeall', 1, 'tool_mdocs');
        } else if ($mode === 'folders') {
            // Folders only: the file names were being ignored, so empty them.
            set_config('includefilenames', '', 'tool_mdocs');
        } else if ($mode === 'filenames') {
            // File names only: the folders were being ignored, so empty them.
            set_config('includefolders', '', 'tool_mdocs');
        }

        unset_config('inclusionmode', 'tool_mdocs');

        // The rules have changed shape, so the catalogue has to be built again.
        unset_config('lastsettingshash', 'tool_mdocs');

        upgrade_plugin_savepoint(true, 2026071600, 'tool', 'mdocs');
    }

    return true;
}
