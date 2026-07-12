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
 * Builds the component-grouped navigation structure shared by the pages.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs\output;

/**
 * Groups indexed documents by component for display.
 */
class nav_tree {

    /**
     * Build a Mustache-ready tree grouped by component.
     *
     * @param \stdClass[] $records rows from tool_mdocs_index
     * @param string|null $activedocid docid of the currently viewed document
     * @return array<int, array> component groups with their documents
     */
    public static function build(array $records, ?string $activedocid = null): array {
        $groups = [];
        foreach ($records as $record) {
            if (!isset($groups[$record->component])) {
                $groups[$record->component] = [
                    'component'   => $record->component,
                    'displayname' => self::component_name($record->component),
                    'plugintype'  => $record->plugintype,
                    'docs'        => [],
                ];
            }
            $url = new \moodle_url('/admin/tool/mdocs/view.php', [
                'component' => $record->component,
                'doc'       => $record->docid,
            ]);
            $groups[$record->component]['docs'][] = [
                'docid'  => $record->docid,
                'title'  => $record->title,
                'url'    => $url->out(false),
                'active' => ($activedocid !== null && $record->docid === $activedocid),
            ];
        }

        // Sort groups by display name for a stable, friendly order.
        $groups = array_values($groups);
        usort($groups, function ($a, $b) {
            return strnatcasecmp($a['displayname'], $b['displayname']);
        });
        foreach ($groups as &$group) {
            $group['doccount'] = count($group['docs']);
            $group['hasactive'] = false;
            foreach ($group['docs'] as $doc) {
                if (!empty($doc['active'])) {
                    $group['hasactive'] = true;
                    break;
                }
            }
        }
        unset($group);

        return $groups;
    }

    /**
     * Human-readable name for a component.
     *
     * @param string $component frankenstyle component
     * @return string
     */
    public static function component_name(string $component): string {
        if (strpos($component, 'core_') === 0) {
            $subsystem = substr($component, 5);
            return get_string('coresubsystem', 'tool_mdocs', ucfirst($subsystem));
        }

        $info = \core_plugin_manager::instance()->get_plugin_info($component);
        if ($info !== null) {
            return (string) $info->displayname;
        }

        return $component;
    }
}
