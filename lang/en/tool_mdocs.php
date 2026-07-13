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

$string['addpage'] = 'Add page';
$string['backtoindex'] = 'All documentation';
$string['browsedocs'] = 'Browse documentation';
$string['coresubsystem'] = 'Core: {$a}';
$string['deletepage'] = 'Delete page';
$string['deletepageconfirm'] = 'Are you sure you want to delete the page "{$a}"? This cannot be undone.';
$string['documentationcenter'] = 'Documentation Center';
$string['documentcount'] = '{$a} document(s)';
$string['documentnotfound'] = 'The requested document could not be found. It may have been removed; try rebuilding the index.';
$string['draft'] = 'Draft (hidden)';
$string['draftbadge'] = 'Draft';
$string['editpage'] = 'Edit page';
$string['filterplaceholder'] = 'Filter…';
$string['generalsettings'] = 'General settings';
$string['includefilenames'] = 'Included file names';
$string['includefilenames_desc'] = 'One file name per line (for example <em>readme.md</em>). Used when the inclusion mode is "Only files with specific names". Matching is case-insensitive.';
$string['includefolders'] = 'Included folders';
$string['includefolders_desc'] = 'One folder name per line (for example <em>docs</em>). Used when the inclusion mode is "Only files inside specific folders". A file is included when any segment of its path relative to the plugin matches one of these names.';
$string['inclusionmode'] = 'Which Markdown files to include';
$string['inclusionmode_all'] = 'All Markdown files';
$string['inclusionmode_desc'] = 'Choose which Markdown (.md) files shipped inside plugins are picked up by the Documentation Center.';
$string['inclusionmode_filenames'] = 'Only files with specific names';
$string['inclusionmode_folders'] = 'Only files inside specific folders';
$string['invalidcomponent'] = 'Invalid or unknown component.';
$string['lastedited'] = 'Last edited by {$a->user} on {$a->date}';
$string['lastindexed'] = 'Last indexed: {$a}';
$string['maxfilesize'] = 'Maximum file size';
$string['maxfilesize_desc'] = 'Markdown files larger than this are skipped during indexing.';
$string['mdocs:manage'] = 'Configure the Documentation Center and rebuild the index';
$string['mdocs:managepages'] = 'Create, edit and delete documentation pages';
$string['mdocs:view'] = 'View the Documentation Center';
$string['neverindexed'] = 'The index has not been built yet.';
$string['newpage'] = 'New documentation page';
$string['nodocsfound'] = 'No documentation was found.';
$string['nodocsfound_help'] = 'No Markdown files matched your current inclusion rules. Check the settings, make sure your plugins ship documentation, then rebuild the index.';
$string['noresults'] = 'No matching documents.';
$string['pagecomponent'] = 'Associated plugin';
$string['pagecomponent_help'] = 'Optionally attach this page to a plugin so it appears alongside that plugin\'s shipped documentation. Leave as "Standalone" to place it under the Site documentation section.';
$string['pagecontent'] = 'Content (Markdown)';
$string['pagecontent_help'] = 'Write the page in Markdown. To embed an image, upload it under "Images" below and reference it as `![alt text](@@PLUGINFILE@@/yourimage.png)`.';
$string['pagedeleted'] = 'The documentation page was deleted.';
$string['pageimages'] = 'Images';
$string['pageimages_help'] = 'Upload images here, then reference them in the content with `![alt text](@@PLUGINFILE@@/filename)`.';
$string['pagenotfound'] = 'The requested documentation page could not be found.';
$string['pagesaved'] = 'The documentation page was saved.';
$string['pagetitle'] = 'Title';
$string['pagevisibility'] = 'Visibility';
$string['pluginname'] = 'Documentation Center';
$string['privacy:metadata'] = 'The Documentation Center plugin does not store any personal data. It only indexes documentation files shipped inside plugins.';
$string['published'] = 'Published';
$string['rebuildindex'] = 'Rebuild index';
$string['rebuildqueued'] = 'A rebuild of the documentation index has been queued. It will run on the next cron cycle.';
$string['rebuiltnow'] = 'The documentation index was rebuilt. {$a} document(s) indexed.';
$string['scancore'] = 'Include core subsystems';
$string['scancore_desc'] = 'Also scan Moodle core subsystems (not just installed plugins) for documentation.';
$string['searchdocs'] = 'Search documentation';
$string['searchplaceholder'] = 'Search documentation…';
$string['settings'] = 'Settings';
$string['sitedocumentation'] = 'Site documentation';
$string['sourcepath'] = 'Source: {$a}';
$string['standalone'] = 'Standalone (Site documentation)';
$string['tableofcontents'] = 'On this page';
$string['task_rebuild_index'] = 'Rebuild the documentation index';
