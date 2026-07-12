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
 * Admin settings and navigation for tool_mdocs.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Build the navigation nodes on every admin tree build; only populate the
// settings controls when the full tree is requested (performance).
if ($hassiteconfig || has_capability('tool/mdocs:view', context_system::instance())) {
    // Group the Documentation Center and its settings under their own category.
    $ADMIN->add('tools', new admin_category('tool_mdocs', get_string('pluginname', 'tool_mdocs')));

    // The Documentation Center itself (viewable by anyone with the view capability).
    $ADMIN->add('tool_mdocs', new admin_externalpage(
        'tool_mdocs_index',
        get_string('browsedocs', 'tool_mdocs'),
        new moodle_url('/admin/tool/mdocs/index.php'),
        'tool/mdocs:view'
    ));

    // Settings page (requires the manage capability).
    $settingspage = new admin_settingpage(
        'tool_mdocs_settings',
        get_string('settings', 'tool_mdocs'),
        'tool/mdocs:manage'
    );

    if ($ADMIN->fulltree) {
        $settingspage->add(new admin_setting_heading(
            'tool_mdocs/generalheading',
            get_string('generalsettings', 'tool_mdocs'),
            ''
        ));

        // Which .md files to include.
        $settingspage->add(new admin_setting_configselect(
            'tool_mdocs/inclusionmode',
            get_string('inclusionmode', 'tool_mdocs'),
            get_string('inclusionmode_desc', 'tool_mdocs'),
            \tool_mdocs\settings_helper::MODE_FOLDERS,
            [
                \tool_mdocs\settings_helper::MODE_ALL       => get_string('inclusionmode_all', 'tool_mdocs'),
                \tool_mdocs\settings_helper::MODE_FOLDERS   => get_string('inclusionmode_folders', 'tool_mdocs'),
                \tool_mdocs\settings_helper::MODE_FILENAMES => get_string('inclusionmode_filenames', 'tool_mdocs'),
            ]
        ));

        $settingspage->add(new admin_setting_configtextarea(
            'tool_mdocs/includefolders',
            get_string('includefolders', 'tool_mdocs'),
            get_string('includefolders_desc', 'tool_mdocs'),
            "docs",
            PARAM_RAW,
            40,
            5
        ));

        $settingspage->add(new admin_setting_configtextarea(
            'tool_mdocs/includefilenames',
            get_string('includefilenames', 'tool_mdocs'),
            get_string('includefilenames_desc', 'tool_mdocs'),
            "readme.md\nindex.md",
            PARAM_RAW,
            40,
            5
        ));

        $settingspage->add(new admin_setting_configcheckbox(
            'tool_mdocs/scancore',
            get_string('scancore', 'tool_mdocs'),
            get_string('scancore_desc', 'tool_mdocs'),
            0
        ));

        $settingspage->add(new admin_setting_configselect(
            'tool_mdocs/maxfilesize',
            get_string('maxfilesize', 'tool_mdocs'),
            get_string('maxfilesize_desc', 'tool_mdocs'),
            1048576,
            [
                262144  => '256 KB',
                524288  => '512 KB',
                1048576 => '1 MB',
                2097152 => '2 MB',
                5242880 => '5 MB',
            ]
        ));
    }

    $ADMIN->add('tool_mdocs', $settingspage);
}

// Prevent the automatic settings link so we control placement above.
$settings = null;
