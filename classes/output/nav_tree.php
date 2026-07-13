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
    /** @var string Internal group key for standalone user pages. */
    const SITE_GROUP = '__site__';

    /**
     * Build a Mustache-ready tree grouped by component, merging file docs and user pages.
     *
     * Documents belonging to a subplugin (for example videotimeplugin_analysis)
     * are nested under their parent plugin (mod_videotime) as a sub-section,
     * rather than appearing as a separate top-level group. User pages attached to a
     * component fold the same way; standalone pages form a pinned "Site documentation"
     * group.
     *
     * @param \stdClass[] $records rows from tool_mdocs_index
     * @param string|null $activedocid docid of the currently viewed document
     * @param \stdClass[] $pagerecords rows from tool_mdocs_page
     * @param int|null $activepageid id of the currently viewed user page
     * @param bool $canmanage whether the viewer may edit/delete pages
     * @return array<int, array> top-level component groups with docs and subgroups
     */
    public static function build(array $records, ?string $activedocid = null, array $pagerecords = [],
            ?int $activepageid = null, bool $canmanage = false): array {
        $groups = [];

        foreach ($records as $record) {
            $parent = self::parent_component($record->component);
            $topkey = $parent ?? $record->component;

            if (!isset($groups[$topkey])) {
                $groups[$topkey] = self::new_group($topkey, self::component_name($topkey));
            }

            $doc = self::doc_entry($record, $activedocid);
            self::place($groups, $topkey, $record->component, $parent, $doc);
        }

        foreach ($pagerecords as $record) {
            $entry = self::page_entry($record, $activepageid, $canmanage);

            if (empty($record->component)) {
                if (!isset($groups[self::SITE_GROUP])) {
                    $groups[self::SITE_GROUP] = self::new_group(self::SITE_GROUP,
                        get_string('sitedocumentation', 'tool_mdocs'));
                }
                $groups[self::SITE_GROUP]['docs'][] = $entry;
                continue;
            }

            $parent = self::parent_component($record->component);
            $topkey = $parent ?? $record->component;
            if (!isset($groups[$topkey])) {
                $groups[$topkey] = self::new_group($topkey, self::component_name($topkey));
            }
            self::place($groups, $topkey, $record->component, $parent, $entry);
        }

        return self::finalise($groups);
    }

    /**
     * Place an entry into a top-level group's docs or a subgroup, folding subplugins.
     *
     * @param array $groups groups keyed by top-level component (by reference)
     * @param string $topkey the top-level group key
     * @param string $component the entry's own component
     * @param string|null $parent parent component when $component is a subplugin
     * @param array $entry the doc or page link entry
     */
    protected static function place(array &$groups, string $topkey, string $component, ?string $parent,
            array $entry): void {
        if ($parent === null) {
            $groups[$topkey]['docs'][] = $entry;
            return;
        }
        if (!isset($groups[$topkey]['subgroups'][$component])) {
            $groups[$topkey]['subgroups'][$component] = [
                'component'   => $component,
                'displayname' => self::component_name($component),
                'docs'        => [],
            ];
        }
        $groups[$topkey]['subgroups'][$component]['docs'][] = $entry;
    }

    /**
     * Create an empty top-level group structure.
     *
     * @param string $topkey group key ('__site__' for the standalone-pages group)
     * @param string $displayname human-readable group name
     * @return array
     */
    protected static function new_group(string $topkey, string $displayname): array {
        return [
            'component'   => ($topkey === self::SITE_GROUP) ? '' : $topkey,
            'displayname' => $displayname,
            'issite'      => ($topkey === self::SITE_GROUP),
            'docs'        => [],
            'subgroups'   => [],
        ];
    }

    /**
     * Turn a user-page record into a Mustache-ready link entry.
     *
     * @param \stdClass $record row from tool_mdocs_page
     * @param int|null $activepageid id of the currently viewed page
     * @param bool $canmanage whether to include edit/delete links
     * @return array
     */
    protected static function page_entry(\stdClass $record, ?int $activepageid, bool $canmanage): array {
        $url = new \moodle_url('/admin/tool/mdocs/view.php', ['pageid' => $record->id]);
        $entry = [
            'title'     => $record->title,
            'url'       => $url->out(false),
            'active'    => ($activepageid !== null && (int) $record->id === $activepageid),
            'isuser'    => true,
            'isdraft'   => empty($record->visible),
            'editurl'   => null,
            'deleteurl' => null,
        ];
        if ($canmanage) {
            $entry['editurl'] = (new \moodle_url('/admin/tool/mdocs/edit.php',
                ['id' => $record->id]))->out(false);
            $entry['deleteurl'] = (new \moodle_url('/admin/tool/mdocs/delete.php',
                ['id' => $record->id]))->out(false);
        }
        return $entry;
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

        // Pin the "Site documentation" group first, then order the rest by name.
        usort($groups, function ($a, $b) {
            $asite = !empty($a['issite']);
            $bsite = !empty($b['issite']);
            if ($asite !== $bsite) {
                return $asite ? -1 : 1;
            }
            return strnatcasecmp($a['displayname'], $b['displayname']);
        });

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
