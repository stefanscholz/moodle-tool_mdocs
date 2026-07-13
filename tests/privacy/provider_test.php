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
 * Unit tests for the privacy provider.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use tool_mdocs\page_manager;

/**
 * Tests for {@see \tool_mdocs\privacy\provider}.
 *
 * @covers \tool_mdocs\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Insert a page authored by a user.
     *
     * @param int $userid author and last editor
     * @param string $title page title
     * @return int the page id
     */
    protected function create_page(int $userid, string $title): int {
        global $DB;
        return (int) $DB->insert_record(page_manager::TABLE, (object) [
            'title'         => $title,
            'content'       => 'Some documentation written by a person.',
            'contentformat' => FORMAT_MARKDOWN,
            'component'     => null,
            'visible'       => 1,
            'sortorder'     => 0,
            'usercreated'   => $userid,
            'usermodified'  => $userid,
            'timecreated'   => time(),
            'timemodified'  => time(),
        ]);
    }

    /**
     * An author should be found in the system context, and a bystander should not.
     *
     * @return void
     */
    public function test_contexts_and_users_are_found_for_an_author(): void {
        $this->resetAfterTest();
        $author = $this->getDataGenerator()->create_user();
        $bystander = $this->getDataGenerator()->create_user();
        $this->create_page((int) $author->id, 'Authored page');

        $contexts = provider::get_contexts_for_userid((int) $author->id)->get_contextids();
        $this->assertEquals([\context_system::instance()->id], $contexts);

        $this->assertEmpty(provider::get_contexts_for_userid((int) $bystander->id)->get_contextids());

        $userlist = new userlist(\context_system::instance(), 'tool_mdocs');
        provider::get_users_in_context($userlist);
        $this->assertContains((int) $author->id, $userlist->get_userids());
        $this->assertNotContains((int) $bystander->id, $userlist->get_userids());
    }

    /**
     * The author's pages should be exported.
     *
     * @return void
     */
    public function test_export_includes_the_users_pages(): void {
        $this->resetAfterTest();
        $author = $this->getDataGenerator()->create_user();
        $this->create_page((int) $author->id, 'Authored page');

        $context = \context_system::instance();
        $this->export_context_data_for_user((int) $author->id, $context, 'tool_mdocs');

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
    }

    /**
     * Images embedded in a page are declared in the metadata, so they have to be
     * exported too, not just the text.
     *
     * @return void
     */
    public function test_export_includes_images_embedded_in_a_page(): void {
        $this->resetAfterTest();
        $author = $this->getDataGenerator()->create_user();
        $pageid = $this->create_page((int) $author->id, 'Page with a picture');

        $context = \context_system::instance();
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'tool_mdocs',
            'filearea'  => page_manager::FILEAREA,
            'itemid'    => $pageid,
            'filepath'  => '/',
            'filename'  => 'diagram.png',
            'userid'    => $author->id,
        ], 'not really a png');

        $this->export_context_data_for_user((int) $author->id, $context, 'tool_mdocs');

        $subcontext = [get_string('privacy:path:pages', 'tool_mdocs'), $pageid . ' Page with a picture'];
        $files = writer::with_context($context)->get_files($subcontext);

        $this->assertArrayHasKey('diagram.png', $files);
    }

    /**
     * Once an author has been erased their id is replaced with 0, which is not a
     * user and must not come back as one.
     *
     * @return void
     */
    public function test_an_erased_author_is_not_reported_as_a_user(): void {
        $this->resetAfterTest();
        $author = $this->getDataGenerator()->create_user();
        $this->create_page((int) $author->id, 'Authored page');

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $userlist = new userlist(\context_system::instance(), 'tool_mdocs');
        provider::get_users_in_context($userlist);

        $this->assertNotContains(0, $userlist->get_userids());
        $this->assertEmpty($userlist->get_userids());
    }

    /**
     * Erasure must unlink the author but keep the page: it is site documentation,
     * not personal content.
     *
     * @return void
     */
    public function test_deleting_a_user_unlinks_them_but_keeps_the_page(): void {
        global $DB;
        $this->resetAfterTest();
        $author = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $pageid = $this->create_page((int) $author->id, 'Authored page');
        $otherid = $this->create_page((int) $other->id, 'Someone else page');

        $contextlist = new approved_contextlist($author, 'tool_mdocs', [\context_system::instance()->id]);
        provider::delete_data_for_user($contextlist);

        // The page survives, but no longer points at a person.
        $page = $DB->get_record(page_manager::TABLE, ['id' => $pageid]);
        $this->assertNotEmpty($page);
        $this->assertSame('Authored page', $page->title);
        $this->assertEquals(0, $page->usercreated);
        $this->assertEquals(0, $page->usermodified);

        // Another user's page is untouched.
        $untouched = $DB->get_record(page_manager::TABLE, ['id' => $otherid]);
        $this->assertEquals((int) $other->id, (int) $untouched->usercreated);
    }

    /**
     * Deleting a set of users unlinks exactly those users.
     *
     * @return void
     */
    public function test_deleting_a_userlist_unlinks_only_those_users(): void {
        global $DB;
        $this->resetAfterTest();
        $one = $this->getDataGenerator()->create_user();
        $two = $this->getDataGenerator()->create_user();
        $this->create_page((int) $one->id, 'Page one');
        $keptid = $this->create_page((int) $two->id, 'Page two');

        $userlist = new approved_userlist(\context_system::instance(), 'tool_mdocs', [(int) $one->id]);
        provider::delete_data_for_users($userlist);

        $this->assertEquals(0, $DB->get_field(page_manager::TABLE, 'usercreated', ['title' => 'Page one']));
        $this->assertEquals((int) $two->id, (int) $DB->get_field(page_manager::TABLE, 'usercreated', ['id' => $keptid]));
        // Both pages still exist.
        $this->assertEquals(2, $DB->count_records(page_manager::TABLE));
    }

    /**
     * Purging the context unlinks every author but keeps every page.
     *
     * @return void
     */
    public function test_deleting_the_context_unlinks_all_authors(): void {
        global $DB;
        $this->resetAfterTest();
        $author = $this->getDataGenerator()->create_user();
        $this->create_page((int) $author->id, 'Authored page');

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assertEquals(1, $DB->count_records(page_manager::TABLE));
        $this->assertEquals(0, $DB->get_field(page_manager::TABLE, 'usercreated', ['title' => 'Authored page']));
    }
}
