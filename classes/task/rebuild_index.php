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
 * Scheduled task that rebuilds the documentation index.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs\task;

use tool_mdocs\index_manager;

/**
 * Rebuilds the tool_mdocs_index catalogue from a filesystem scan.
 */
class rebuild_index extends \core\task\scheduled_task {

    /**
     * Human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_rebuild_index', 'tool_mdocs');
    }

    /**
     * Execute the rebuild.
     */
    public function execute(): void {
        $manager = new index_manager();
        $count = $manager->rebuild();
        mtrace("tool_mdocs: indexed {$count} document(s).");
    }
}
