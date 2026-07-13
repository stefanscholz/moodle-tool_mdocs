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
 * Privacy provider for tool_mdocs.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider.
 *
 * Documentation discovered inside plugins holds no personal data: it is files on
 * disk. User-authored pages do, because each one records who wrote it and who
 * last edited it.
 *
 * Those pages are site documentation rather than personal content, so an erasure
 * request unlinks the author instead of deleting the page. The personal data —
 * the link to a person — is removed either way, but the site does not lose its
 * documentation because the admin who wrote it left.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data held by this plugin.
     *
     * @param collection $collection the collection to add to
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('tool_mdocs_page', [
            'title'        => 'privacy:metadata:tool_mdocs_page:title',
            'content'      => 'privacy:metadata:tool_mdocs_page:content',
            'usercreated'  => 'privacy:metadata:tool_mdocs_page:usercreated',
            'usermodified' => 'privacy:metadata:tool_mdocs_page:usermodified',
            'timecreated'  => 'privacy:metadata:tool_mdocs_page:timecreated',
            'timemodified' => 'privacy:metadata:tool_mdocs_page:timemodified',
        ], 'privacy:metadata:tool_mdocs_page');

        // Images embedded in a page go through the files subsystem, which records
        // who uploaded them.
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:core_files');

        return $collection;
    }

    /**
     * Get the contexts holding data for a user. Pages are site-wide, so this is
     * the system context or nothing at all.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                 WHERE ctx.contextlevel = :contextlevel
                   AND EXISTS (
                       SELECT 1
                         FROM {tool_mdocs_page} p
                        WHERE p.usercreated = :usercreated
                           OR p.usermodified = :usermodified
                   )";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_SYSTEM,
            'usercreated'  => $userid,
            'usermodified' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the users holding data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }

        $userlist->add_from_sql('usercreated', "SELECT usercreated FROM {tool_mdocs_page}", []);
        $userlist->add_from_sql('usermodified', "SELECT usermodified FROM {tool_mdocs_page}", []);
    }

    /**
     * Export the pages a user wrote or last edited.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (!self::has_system_context($contextlist->get_contexts())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $pages = $DB->get_records_select(
            'tool_mdocs_page',
            'usercreated = :usercreated OR usermodified = :usermodified',
            ['usercreated' => $userid, 'usermodified' => $userid],
            'title ASC'
        );
        if (empty($pages)) {
            return;
        }

        $data = [];
        foreach ($pages as $page) {
            $data[] = (object) [
                'title'        => $page->title,
                'content'      => $page->content,
                'authored'     => transform::yesno((int) $page->usercreated === $userid),
                'lastedited'   => transform::yesno((int) $page->usermodified === $userid),
                'timecreated'  => transform::datetime($page->timecreated),
                'timemodified' => transform::datetime($page->timemodified),
            ];
        }

        writer::with_context(\context_system::instance())->export_data(
            [get_string('privacy:path:pages', 'tool_mdocs')],
            (object) ['pages' => $data]
        );
    }

    /**
     * Unlink every author in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_system) {
            return;
        }

        $DB->set_field('tool_mdocs_page', 'usercreated', 0, []);
        $DB->set_field('tool_mdocs_page', 'usermodified', 0, []);
    }

    /**
     * Unlink one user from any page they wrote or edited.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        if (!self::has_system_context($contextlist->get_contexts())) {
            return;
        }

        self::unlink_users([(int) $contextlist->get_user()->id]);
    }

    /**
     * Unlink a set of users from any page they wrote or edited.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }

        self::unlink_users($userlist->get_userids());
    }

    /**
     * Replace the given users' ids with 0 wherever a page records them.
     *
     * The page itself is site documentation and is kept; only the link to a
     * person is removed.
     *
     * @param int[] $userids
     */
    protected static function unlink_users(array $userids): void {
        global $DB;

        if (empty($userids)) {
            return;
        }

        foreach (['usercreated', 'usermodified'] as $field) {
            [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
            $DB->set_field_select('tool_mdocs_page', $field, 0, "$field $insql", $params);
        }
    }

    /**
     * Whether the system context is among the approved contexts.
     *
     * @param \context[] $contexts
     * @return bool
     */
    protected static function has_system_context(array $contexts): bool {
        foreach ($contexts as $context) {
            if ($context instanceof \context_system) {
                return true;
            }
        }
        return false;
    }
}
