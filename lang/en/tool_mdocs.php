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
 * English language strings for tool_mdocs.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Documentation Center';

// Capabilities.
$string['mdocs:view'] = 'View the Documentation Center';
$string['mdocs:manage'] = 'Configure the Documentation Center and rebuild the index';

// Navigation / pages.
$string['documentationcenter'] = 'Documentation Center';
$string['settings'] = 'Settings';
$string['generalsettings'] = 'General settings';

// Settings: inclusion rules.
$string['inclusionmode'] = 'Which Markdown files to include';
$string['inclusionmode_desc'] = 'Choose which Markdown (.md) files shipped inside plugins are picked up by the Documentation Center.';
$string['inclusionmode_all'] = 'All Markdown files';
$string['inclusionmode_folders'] = 'Only files inside specific folders';
$string['inclusionmode_filenames'] = 'Only files with specific names';
$string['includefolders'] = 'Included folders';
$string['includefolders_desc'] = 'One folder name per line (for example <em>docs</em>). Used when the inclusion mode is "Only files inside specific folders". A file is included when any segment of its path relative to the plugin matches one of these names.';
$string['includefilenames'] = 'Included file names';
$string['includefilenames_desc'] = 'One file name per line (for example <em>readme.md</em>). Used when the inclusion mode is "Only files with specific names". Matching is case-insensitive.';
$string['scancore'] = 'Include core subsystems';
$string['scancore_desc'] = 'Also scan Moodle core subsystems (not just installed plugins) for documentation.';
$string['maxfilesize'] = 'Maximum file size';
$string['maxfilesize_desc'] = 'Markdown files larger than this are skipped during indexing.';

// Index page.
$string['nodocsfound'] = 'No documentation was found.';
$string['nodocsfound_help'] = 'No Markdown files matched your current inclusion rules. Check the settings, make sure your plugins ship documentation, then rebuild the index.';
$string['rebuildindex'] = 'Rebuild index';
$string['rebuildqueued'] = 'A rebuild of the documentation index has been queued. It will run on the next cron cycle.';
$string['rebuiltnow'] = 'The documentation index was rebuilt. {$a} document(s) indexed.';
$string['lastindexed'] = 'Last indexed: {$a}';
$string['neverindexed'] = 'The index has not been built yet.';
$string['documentcount'] = '{$a} document(s)';
$string['browsedocs'] = 'Browse documentation';
$string['coresubsystem'] = 'Core: {$a}';

// Viewer.
$string['tableofcontents'] = 'On this page';
$string['searchdocs'] = 'Search documentation';
$string['searchplaceholder'] = 'Search documentation…';
$string['noresults'] = 'No matching documents.';
$string['backtoindex'] = 'All documentation';
$string['sourcepath'] = 'Source: {$a}';
$string['documentnotfound'] = 'The requested document could not be found. It may have been removed; try rebuilding the index.';
$string['filterplaceholder'] = 'Filter…';

// Tasks.
$string['task_rebuild_index'] = 'Rebuild the documentation index';

// Privacy.
$string['privacy:metadata'] = 'The Documentation Center plugin does not store any personal data. It only indexes documentation files shipped inside plugins.';

// Errors.
$string['invalidcomponent'] = 'Invalid or unknown component.';
