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
 * Who may read documentation, and where.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs;

/**
 * The access rules, in one place, because they are subtler than they look.
 *
 * There are two ways to be allowed to read documentation:
 *
 * - tool/mdocs:view at the system, which is what the Documentation Center needs;
 * - tool/mdocs:viewcontextual somewhere, which is what a teacher has, in their
 *   own course, and which is what the help icon needs.
 *
 * The awkward case is the pictures inside a document. They are served by requests
 * of their own, which arrive with no idea of the page the reader was on, so
 * neither of the checks above can be applied as-is: a teacher would read a
 * document whose every image was a broken link. Asking whether the reader holds
 * the contextual capability *anywhere* answers it, and that is what
 * {@see self::can_read_assets()} does.
 */
class access {
    /** @var bool|null Remembered answer for this request. */
    protected static ?bool $canreadassets = null;

    /**
     * Whether the user may open the Documentation Center itself.
     *
     * @return bool
     */
    public static function can_browse(): bool {
        return has_capability('tool/mdocs:view', \context_system::instance());
    }

    /**
     * Whether the user may read the documentation attached to a given page.
     *
     * @param \context $context the context of the page being viewed
     * @return bool
     */
    public static function can_view_contextual(\context $context): bool {
        return has_capability(contextual::CAPABILITY, $context);
    }

    /**
     * Whether the user may fetch the images and files a document refers to.
     *
     * True for anyone who can browse the Documentation Center, and for anyone who
     * can read contextual help anywhere at all — because if they are allowed to
     * read the document, they are allowed to see the pictures in it.
     *
     * The "anywhere" question costs a query, so it is asked once per request and
     * only after the cheap check has failed. Image requests are few and the
     * browser caches them.
     *
     * @return bool
     */
    public static function can_read_assets(): bool {
        global $USER;

        if (self::$canreadassets !== null) {
            return self::$canreadassets;
        }

        if (self::can_browse()) {
            return self::$canreadassets = true;
        }

        // One course is enough to answer the question, so stop at the first.
        $courses = get_user_capability_course(
            contextual::CAPABILITY,
            $USER->id,
            true,
            '',
            '',
            1
        );

        return self::$canreadassets = !empty($courses);
    }

    /**
     * Forget the remembered answer. For tests.
     *
     * @return void
     */
    public static function reset_caches(): void {
        self::$canreadassets = null;
    }
}
