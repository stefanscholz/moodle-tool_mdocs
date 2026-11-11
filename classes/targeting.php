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
 * The convention that decides which page a document belongs to, and who it is for.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs;

/**
 * Works out where a document belongs and who it is written for.
 *
 * Both answers are derived from things a plugin already has — its directory and
 * the name of the file — so a plugin that does nothing at all still gets
 * contextual help. Front matter only has to be written where the guess is wrong
 * or not precise enough. That matters: the {role}-guide.md habit exists in only a
 * couple of plugins today, and a convention nobody has adopted yet cannot be a
 * precondition for the feature working.
 */
class targeting {
    /** @var string Shown to anyone who can see contextual help. */
    const AUDIENCE_ANY = 'any';

    /** @var string[] The audiences a document may be written for. */
    const AUDIENCES = ['admin', 'manager', 'teacher', 'student', 'developer', self::AUDIENCE_ANY];

    /**
     * How we decide whether the current user belongs to an audience, strongest first.
     *
     * Capabilities, not roles: a capability check is served from the access data
     * already loaded on the request, whereas get_user_roles() is a live database
     * query and this runs on every page.
     *
     * The order is the point. Capabilities nest — a teacher can do everything a
     * student can, and an administrator can do everything at all — so asking
     * "which of these can you do?" answers "all of them" for anyone senior, and a
     * site administrator would be told that the student guide is written for them.
     * The first match decides how senior the reader is. They then get the guides
     * for that audience and for every staff audience below it: an administrator
     * configuring a course is doing a teacher's job at that moment, and a teacher
     * article is the answer. The student guide is never included this way (see
     * audiences_for_user()), so it stays one click away for staff.
     *
     * @var array<string, string>
     */
    const AUDIENCE_CAPABILITIES = [
        // Site administrators pass every capability check, which is what makes
        // this one work: nobody holds it by role.
        'admin'   => 'moodle/site:config',
        // Managers hold this and teachers do not. The obvious choice,
        // moodle/course:update, is held by editing teachers as well, and would
        // have handed every teacher the manager's guide.
        'manager' => 'moodle/category:manage',
        // Held by both editing and non-editing teachers, and by no student.
        'teacher' => 'moodle/course:viewhiddenactivities',
    ];

    /** @var string What somebody is when they are none of the above. */
    const AUDIENCE_FALLBACK = 'student';

    /**
     * The audience a file declares through its name or its folder.
     *
     * "docs/teacher-guide.md" and "docs/teacher/setup.md" both say teacher. A name
     * that says nothing — README.md, timetable-integration.md — means the document
     * is for everyone, which is the right answer for a topic document.
     *
     * @param string $relpath the path of the file inside its component
     * @return string one of self::AUDIENCES
     */
    public static function audience_from_path(string $relpath): string {
        $segments = explode('/', \core_text::strtolower($relpath));
        $basename = array_pop($segments);

        // A folder named after an audience wins: docs/teacher/setup.md.
        foreach ($segments as $segment) {
            if (in_array($segment, self::AUDIENCES, true) && $segment !== self::AUDIENCE_ANY) {
                return $segment;
            }
        }

        // Otherwise the {audience}-guide.md convention.
        if (preg_match('/^([a-z]+)-guide\.md$/', (string) $basename, $matches)) {
            $audience = $matches[1];
            if (in_array($audience, self::AUDIENCES, true) && $audience !== self::AUDIENCE_ANY) {
                return $audience;
            }
        }

        return self::AUDIENCE_ANY;
    }

    /**
     * The pagetype pattern a component's pages carry.
     *
     * Moodle builds a pagetype from the script path: the path below the code root
     * with the slashes turned into hyphens (moodle_page::initialise_default_pagetype).
     * So a component's own directory tells us the prefix of every pagetype it can
     * ever produce, and mod/videotime becomes mod-videotime-*.
     *
     * A subplugin borrows its parent's prefix, because a subplugin's interface
     * almost always appears on the parent's pages rather than on pages of its own.
     *
     * @param string $component frankenstyle component
     * @return string[] the pattern(s) the component's documents default to, possibly empty
     */
    public static function pagetypes_for_component(string $component): array {
        // A subplugin's UI lives on its parent's pages: videotimeplugin_analysis
        // reports appear inside Video Time, not at a URL of their own.
        $parts = explode('_', $component, 2);
        $parent = count($parts) === 2 ? \core_component::get_subtype_parent($parts[0]) : null;
        if ($parent !== null && $parent !== $component) {
            return self::pagetypes_for_component($parent);
        }

        $prefix = self::pagetype_prefix($component);
        if ($prefix === null) {
            return [];
        }

        return [$prefix . '-*'];
    }

    /**
     * The pagetype prefix for a component, or null when it has no directory.
     *
     * @param string $component frankenstyle component
     * @return string|null e.g. "mod-videotime", "admin-tool-timings"
     */
    public static function pagetype_prefix(string $component): ?string {
        global $CFG;

        $dir = \core_component::get_component_directory($component);
        if ($dir === null) {
            return null;
        }

        $dir = str_replace('\\', '/', $dir);
        $root = str_replace('\\', '/', $CFG->dirroot);
        if (!str_starts_with($dir, $root . '/')) {
            return null;
        }

        $relative = trim(substr($dir, strlen($root)), '/');
        if ($relative === '') {
            return null;
        }

        // Moodle rewrites the admin directory back to "admin" when it builds a
        // pagetype, so a site that renamed it still produces admin-tool-x pages.
        $admin = trim((string) ($CFG->admin ?? 'admin'), '/');
        if ($admin !== '' && $admin !== 'admin') {
            if ($relative === $admin || str_starts_with($relative, $admin . '/')) {
                $relative = 'admin' . substr($relative, strlen($admin));
            }
        }

        return str_replace('/', '-', trim($relative, '/'));
    }

    /**
     * Which audiences the current user is read as, on this page.
     *
     * The context matters: a teacher holds moodle/course:manageactivities inside
     * their own course and nowhere else, so the same person is a "teacher" on a
     * course page and nobody in particular on an admin page.
     *
     * The most senior matching audience is returned together with every staff
     * audience below it, plus everything written for everyone: an administrator
     * also gets the manager and teacher guides, a manager the teacher guides.
     * The learner's audience is only returned for someone who matches none of
     * the staff audiences, so a teacher is not told the student guide is theirs.
     *
     * @param \context $context the context of the page being viewed
     * @return string[] the audiences that apply, always including 'any'
     */
    public static function audiences_for_user(\context $context): array {
        $audiences = [self::AUDIENCE_ANY];

        $matched = false;
        foreach (self::AUDIENCE_CAPABILITIES as $audience => $capability) {
            // Once the reader matched, every audience below is theirs too. The
            // order of AUDIENCE_CAPABILITIES is what makes "below" meaningful.
            if ($matched || has_capability($capability, $context)) {
                $matched = true;
                $audiences[] = $audience;
            }
        }

        // Nobody in particular here, so read as a learner would.
        if (!$matched) {
            $audiences[] = self::AUDIENCE_FALLBACK;
        }

        // Developer documentation is for people reading the code, and on a Moodle
        // site the people who do that are its administrators.
        if (is_siteadmin()) {
            $audiences[] = 'developer';
        }

        return array_values(array_unique($audiences));
    }
}
