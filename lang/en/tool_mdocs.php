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
$string['audience_admin'] = 'Administrator';
$string['audience_any'] = 'Everyone';
$string['audience_developer'] = 'Developer';
$string['audience_manager'] = 'Manager';
$string['audience_student'] = 'Student';
$string['audience_teacher'] = 'Teacher';
$string['backtoindex'] = 'All documentation';
$string['backtolist'] = 'All documents for this page';
$string['browsedocs'] = 'Browse documentation';
$string['coresubsystem'] = 'Core: {$a}';
$string['deletepage'] = 'Delete page';
$string['deletepageconfirm'] = 'Are you sure you want to delete the page "{$a}"? This cannot be undone.';
$string['documentationcenter'] = 'Documentation Center';
$string['documentcount'] = '{$a} document(s)';
$string['documentnotfound'] = 'The requested document could not be found. It may have been removed; try rebuilding the index.';
$string['draft'] = 'Draft (hidden)';
$string['draftbadge'] = 'Draft';
$string['editorbold'] = 'Bold';
$string['editorbulletlist'] = 'Bulleted list';
$string['editorcode'] = 'Code';
$string['editorheading'] = 'Heading';
$string['editorimage'] = 'Image';
$string['editoritalic'] = 'Italic';
$string['editorlink'] = 'Link';
$string['editornumberedlist'] = 'Numbered list';
$string['editorpreview'] = 'Preview';
$string['editorpreviewempty'] = 'Nothing to preview yet.';
$string['editorpreviewfailed'] = 'The preview could not be loaded.';
$string['editorquote'] = 'Quote';
$string['editorwrite'] = 'Write';
$string['editpage'] = 'Edit page';
$string['filterplaceholder'] = 'Filter…';
$string['generalsettings'] = 'General settings';
$string['helpdocfailed'] = 'That document could not be loaded.';
$string['helpforthispage'] = 'Documentation for this page';
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
$string['mdocs:viewcontextual'] = 'See the documentation that belongs to the page being viewed';
$string['neverindexed'] = 'The index has not been built yet.';
$string['newpage'] = 'New documentation page';
$string['nodocsfound'] = 'No documentation was found.';
$string['nodocsfound_help'] = 'No Markdown files matched your current inclusion rules. Check the settings, make sure your plugins ship documentation, then rebuild the index.';
$string['noresults'] = 'No matching documents.';
$string['otheraudiences'] = 'Written for other people';
$string['pageaudience'] = 'Written for';
$string['pageaudience_help'] = 'Who this page is aimed at. It is offered first to those people when they open the help icon; everybody else can still find it under "Written for other people".';
$string['pagecomponent'] = 'Associated plugin';
$string['pagecomponent_help'] = 'Optionally attach this page to a plugin so it appears alongside that plugin\'s shipped documentation. Leave as "Standalone" to place it under the Site documentation section.';
$string['pagecontent'] = 'Content (Markdown)';
$string['pagecontent_help'] = 'Write the page in Markdown. To embed an image, upload it under "Images" below and reference it as `![alt text](@@PLUGINFILE@@/yourimage.png)`.';
$string['pagedeleted'] = 'The documentation page was deleted.';
$string['pageimages'] = 'Images';
$string['pageimages_help'] = 'Upload images here, then reference them in the content with `![alt text](@@PLUGINFILE@@/filename)`.';
$string['pagenotfound'] = 'The requested documentation page could not be found.';
$string['pagepagetypes'] = 'Show on these pages';
$string['pagepagetypes_help'] = 'One Moodle page type per line, for example <em>mod-videotime-view</em>. A <em>*</em> matches anything, so <em>mod-videotime-*</em> covers every Video Time page. Leave this empty and the page is only shown on the pages of the plugin it belongs to.';
$string['pagesaved'] = 'The documentation page was saved.';
$string['pagetitle'] = 'Title';
$string['pagevisibility'] = 'Visibility';
$string['pluginname'] = 'Documentation Center';
$string['privacy:metadata:core_files'] = 'Images uploaded for use in a documentation page.';
$string['privacy:metadata:tool_mdocs_page'] = 'Documentation pages written in Moodle. Documentation discovered inside plugins is not recorded here: it is read from files on disk and holds no personal data.';
$string['privacy:metadata:tool_mdocs_page:content'] = 'The Markdown content of the page.';
$string['privacy:metadata:tool_mdocs_page:timecreated'] = 'When the page was created.';
$string['privacy:metadata:tool_mdocs_page:timemodified'] = 'When the page was last edited.';
$string['privacy:metadata:tool_mdocs_page:title'] = 'The title of the page.';
$string['privacy:metadata:tool_mdocs_page:usercreated'] = 'The user who created the page.';
$string['privacy:metadata:tool_mdocs_page:usermodified'] = 'The user who last edited the page.';
$string['privacy:path:pages'] = 'Documentation pages';
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
