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
 * Editing form for user-authored documentation pages.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs\form;

use tool_mdocs\output\nav_tree;

/**
 * Create/edit form for a documentation page.
 */
class page_form extends \moodleform {

    /**
     * Define the form.
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'title', get_string('pagetitle', 'tool_mdocs'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', get_string('required'), 'required', null, 'client');

        $mform->addElement('autocomplete', 'component', get_string('pagecomponent', 'tool_mdocs'),
            self::component_options());
        $mform->addHelpButton('component', 'pagecomponent', 'tool_mdocs');

        $mform->addElement('select', 'visible', get_string('pagevisibility', 'tool_mdocs'), [
            1 => get_string('published', 'tool_mdocs'),
            0 => get_string('draft', 'tool_mdocs'),
        ]);
        $mform->setDefault('visible', 1);

        $mform->addElement('textarea', 'content', get_string('pagecontent', 'tool_mdocs'),
            'wrap="virtual" rows="20" cols="80"');
        $mform->setType('content', PARAM_RAW);
        $mform->addRule('content', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('content', 'pagecontent', 'tool_mdocs');

        $mform->addElement('filemanager', 'attachments', get_string('pageimages', 'tool_mdocs'), null,
            self::file_options());
        $mform->addHelpButton('attachments', 'pageimages', 'tool_mdocs');

        $this->add_action_buttons();
    }

    /**
     * File manager options for the image area.
     *
     * @return array
     */
    public static function file_options(): array {
        return [
            'subdirs' => 0,
            'maxbytes' => 0,
            'maxfiles' => 50,
            'accepted_types' => ['web_image'],
        ];
    }

    /**
     * Build the component picker options: standalone plus every installed plugin.
     *
     * @return array<string, string>
     */
    protected static function component_options(): array {
        $options = ['' => get_string('standalone', 'tool_mdocs')];

        foreach (array_keys(\core_component::get_plugin_types()) as $type) {
            foreach (array_keys(\core_component::get_plugin_list($type)) as $name) {
                $component = $type . '_' . $name;
                $options[$component] = nav_tree::component_name($component) . ' (' . $component . ')';
            }
        }

        // Order the picker by what the reader sees, keeping "standalone" pinned first.
        $standalone = array_slice($options, 0, 1, true);
        $plugins = array_slice($options, 1, null, true);
        natcasesort($plugins);

        return $standalone + $plugins;
    }
}
