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
 * Unit tests for contextual help.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs;

/**
 * Tests for {@see \tool_mdocs\contextual} and the capability that guards it.
 *
 * @covers \tool_mdocs\contextual
 */
final class contextual_test extends \advanced_testcase {
    /**
     * Index a document against a page, without touching the filesystem.
     *
     * @param string $docid the document's id
     * @param string $component the component it belongs to
     * @param string $title its title
     * @param string $audience who it is written for
     * @param string $pagetype the page it belongs to
     * @return void
     */
    protected function index(
        string $docid,
        string $component,
        string $title,
        string $audience,
        string $pagetype
    ): void {
        global $DB;

        $DB->insert_record(index_manager::TABLE, (object) [
            'docid'        => $docid,
            'component'    => $component,
            'plugintype'   => 'mod',
            'relpath'      => 'docs/' . $audience . '-guide.md',
            'basename'     => $audience . '-guide.md',
            'title'        => $title,
            'content'      => '',
            'audience'     => $audience,
            'pagetypes'    => $pagetype,
            'sortorder'    => 0,
            'filesize'     => 0,
            'timemodified' => 0,
            'timecreated'  => 0,
        ]);

        $DB->insert_record(index_manager::TARGET_TABLE, (object) [
            'source'   => index_manager::SOURCE_FILE,
            'sourceid' => $docid,
            'pagetype' => $pagetype,
        ]);
    }

    /**
     * The trap this whole capability exists for.
     *
     * A teacher's role is assigned in a course, so they hold no system capability.
     * Guarding the help icon with tool/mdocs:view — the obvious choice — would have
     * hidden it from exactly the people it is for, and no test of the icon itself
     * would have noticed, because an administrator sees it either way.
     *
     * @return void
     */
    public function test_a_teacher_holds_no_system_capability_but_does_hold_it_in_their_course(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $system = \context_system::instance();

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $this->assertFalse(
            has_capability('tool/mdocs:view', $system),
            'a teacher never holds a system capability, which is why the help icon cannot be guarded by one'
        );
        $this->assertTrue(has_capability(contextual::CAPABILITY, $coursecontext));

        // And on an administration page, which is the system context, they get
        // nothing — which is correct.
        $this->assertFalse(has_capability(contextual::CAPABILITY, $system));
    }

    /**
     * A document written for the reader comes first; the rest stay reachable.
     *
     * @return void
     */
    public function test_documents_are_split_by_who_they_are_written_for(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $this->index('a1', 'mod_assign', 'Teacher guide', 'teacher', 'mod-assign-*');
        $this->index('a2', 'mod_assign', 'Admin guide', 'admin', 'mod-assign-*');
        $this->index('a3', 'mod_assign', 'Overview', targeting::AUDIENCE_ANY, 'mod-assign-*');

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $matches = contextual::for_page('mod-assign-view', $context);

        $titles = array_column($matches['foryou'], 'title');
        $this->assertContains('Teacher guide', $titles);
        $this->assertContains('Overview', $titles, 'a document for everyone is for the teacher too');
        $this->assertNotContains('Admin guide', $titles);

        // Not thrown away: a teacher asking what the administrator is told is a
        // real question.
        $this->assertSame(['Admin guide'], array_column($matches['others'], 'title'));
    }

    /**
     * A page with no documentation must produce nothing, so the icon can disappear.
     *
     * @return void
     */
    public function test_a_page_with_no_documentation_matches_nothing(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $this->index('a1', 'mod_assign', 'Teacher guide', 'teacher', 'mod-assign-*');

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $this->assertFalse(contextual::has_any('mod-forum-view', $context));
        $this->assertTrue(contextual::has_any('mod-assign-view', $context));
    }

    /**
     * The most specific document wins.
     *
     * @return void
     */
    public function test_the_most_specific_page_match_comes_first(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $this->index('broad', 'mod_assign', 'Anywhere in Assignment', targeting::AUDIENCE_ANY, 'mod-assign-*');
        $this->index('exact', 'mod_assign', 'This very page', targeting::AUDIENCE_ANY, 'mod-assign-view');

        $this->setAdminUser();

        $matches = contextual::for_page('mod-assign-view', $context);

        $this->assertSame('This very page', $matches['foryou'][0]['title']);
    }

    /**
     * A draft page is nobody's answer to anything.
     *
     * @return void
     */
    public function test_draft_pages_are_not_offered(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $pageid = $DB->insert_record(page_manager::TABLE, (object) [
            'title' => 'Half written', 'content' => 'x', 'contentformat' => FORMAT_MARKDOWN,
            'component' => 'mod_assign', 'visible' => 0, 'sortorder' => 0,
            'usercreated' => 0, 'usermodified' => 0, 'timecreated' => 0, 'timemodified' => 0,
        ]);
        $DB->insert_record(index_manager::TARGET_TABLE, (object) [
            'source' => index_manager::SOURCE_PAGE,
            'sourceid' => (string) $pageid,
            'pagetype' => 'mod-assign-*',
        ]);

        $this->assertFalse(contextual::has_any('mod-assign-view', $context));
    }
}
