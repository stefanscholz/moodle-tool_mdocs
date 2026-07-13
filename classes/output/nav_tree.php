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
 * Groups indexed documents by component for display, folding subplugin
 * documentation underneath its parent plugin as labelled sub-sections.
 */
class nav_tree {
    /**
     * Build a Mustache-ready tree grouped by component.
     *
     * Documents belonging to a subplugin (for example videotimeplugin_analysis)
     * are nested under their parent plugin (mod_videotime) as a sub-section,
     * rather than appearing as a separate top-level group.
     *
     * @param \stdClass[] $records rows from tool_mdocs_index
     * @param string|null $activedocid docid of the currently viewed document
     * @return array<int, array> top-level component groups with docs and subgroups
     */
    public static function build(array $records, ?string $activedocid = null): array {
        $groups = [];

        foreach ($records as $record) {
            $parent = self::parent_component($record->component);
            $topkey = $parent ?? $record->component;

            if (!isset($groups[$topkey])) {
                $groups[$topkey] = [
                    'component'   => $topkey,
                    'displayname' => self::component_name($topkey),
                    'docs'        => [],
                    'subgroups'   => [],
                ];
            }

            $doc = self::doc_entry($record, $activedocid);

            if ($parent === null) {
                $groups[$topkey]['docs'][] = $doc;
            } else {
                if (!isset($groups[$topkey]['subgroups'][$record->component])) {
                    $groups[$topkey]['subgroups'][$record->component] = [
                        'component'   => $record->component,
                        'displayname' => self::component_name($record->component),
                        'docs'        => [],
                    ];
                }
                $groups[$topkey]['subgroups'][$record->component]['docs'][] = $doc;
            }
        }

        return self::finalise($groups);
    }

    /**
     * Turn a document record into a Mustache-ready link entry.
     *
     * @param \stdClass $record row from tool_mdocs_index
     * @param string|null $activedocid docid of the currently viewed document
     * @return array{docid: string, title: string, url: string, active: bool}
     */
    protected static function doc_entry(\stdClass $record, ?string $activedocid): array {
        $url = new \moodle_url('/admin/tool/mdocs/view.php', [
            'component' => $record->component,
            'doc'       => $record->docid,
        ]);
        return [
            'docid'  => $record->docid,
            'title'  => $record->title,
            'url'    => $url->out(false),
            'active' => ($activedocid !== null && $record->docid === $activedocid),
        ];
    }

    /**
     * Resolve the parent plugin of a subplugin component, if any.
     *
     * @param string $component frankenstyle component
     * @return string|null parent component, or null when this is not a subplugin
     */
    protected static function parent_component(string $component): ?string {
        $parts = explode('_', $component, 2);
        if (count($parts) !== 2) {
            return null;
        }
        return \core_component::get_subtype_parent($parts[0]);
    }

    /**
     * Sort, count and flag the assembled groups for display.
     *
     * @param array $groups groups keyed by top-level component
     * @return array<int, array> normalised, ordered list of groups
     */
    protected static function finalise(array $groups): array {
        $groups = array_values($groups);

        foreach ($groups as &$group) {
            $subgroups = array_values($group['subgroups']);
            usort($subgroups, fn($a, $b) => strnatcasecmp($a['displayname'], $b['displayname']));

            $doccount = count($group['docs']);
            $hasactive = self::any_active($group['docs']);

            foreach ($subgroups as &$subgroup) {
                $subactive = self::any_active($subgroup['docs']);
                $subgroup['doccount'] = count($subgroup['docs']);
                $subgroup['hasactive'] = $subactive;
                $doccount += $subgroup['doccount'];
                $hasactive = $hasactive || $subactive;
            }
            unset($subgroup);

            $group['subgroups']    = $subgroups;
            $group['hassubgroups'] = !empty($subgroups);
            $group['hasdocs']      = !empty($group['docs']);
            $group['doccount']     = $doccount;
            $group['hasactive']    = $hasactive;
        }
        unset($group);

        usort($groups, fn($a, $b) => strnatcasecmp($a['displayname'], $b['displayname']));

        return $groups;
    }

    /**
     * Whether any document in the list is the active one.
     *
     * @param array $docs document link entries
     * @return bool
     */
    protected static function any_active(array $docs): bool {
        foreach ($docs as $doc) {
            if (!empty($doc['active'])) {
                return true;
            }
        }
        return false;
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
