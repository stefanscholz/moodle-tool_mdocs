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
 * Unit tests for the targeting convention.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs;

/**
 * Tests for {@see \tool_mdocs\targeting} and {@see \tool_mdocs\front_matter}.
 *
 * @covers \tool_mdocs\targeting
 */
final class targeting_test extends \advanced_testcase {
    /**
     * A plugin's own directory says which pages its documentation belongs to.
     *
     * @return void
     */
    public function test_pagetypes_come_from_the_component_directory(): void {
        $this->resetAfterTest();

        $this->assertSame(['mod-assign-*'], targeting::pagetypes_for_component('mod_assign'));
        $this->assertSame(['admin-tool-mdocs-*'], targeting::pagetypes_for_component('tool_mdocs'));
    }

    /**
     * A subplugin's interface lives on its parent's pages, so its documentation
     * has to appear there too. This is what makes videotimeplugin_analysis show up
     * on a Video Time page rather than on a page of its own that nobody visits.
     *
     * @return void
     */
    public function test_a_subplugin_borrows_its_parents_pages(): void {
        $this->resetAfterTest();

        $this->assertSame(
            targeting::pagetypes_for_component('mod_assign'),
            targeting::pagetypes_for_component('assignsubmission_file')
        );
    }

    /**
     * The audience is read from the file name, because that is the only signal
     * that is actually reliable: the titles of the guides that exist today do not
     * agree with each other, but their names do.
     *
     * @return void
     */
    public function test_audience_is_read_from_the_file_name(): void {
        $this->resetAfterTest();

        $this->assertSame('teacher', targeting::audience_from_path('docs/teacher-guide.md'));
        $this->assertSame('admin', targeting::audience_from_path('docs/admin-guide.md'));
        $this->assertSame('developer', targeting::audience_from_path('docs/developer-guide.md'));
        $this->assertSame('teacher', targeting::audience_from_path('docs/teacher/setup.md'));
    }

    /**
     * A document that names no audience is for everybody. Topic documents and plain
     * readmes must not vanish just because they do not follow the guide convention.
     *
     * @return void
     */
    public function test_a_document_that_names_no_audience_is_for_everyone(): void {
        $this->resetAfterTest();

        $this->assertSame(targeting::AUDIENCE_ANY, targeting::audience_from_path('README.md'));
        $this->assertSame(targeting::AUDIENCE_ANY, targeting::audience_from_path('docs/timetable-integration.md'));
        $this->assertSame(targeting::AUDIENCE_ANY, targeting::audience_from_path('docs/user-guide.md'));
    }

    /**
     * Only the most senior audience counts.
     *
     * Capabilities nest, so asking "which of these can you do?" answers "all of
     * them" for anyone senior, and a site administrator would be told the student
     * guide was written for them.
     *
     * @return void
     */
    public function test_a_reader_is_read_as_the_most_senior_thing_they_are(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);
        $audiences = targeting::audiences_for_user($context);
        $this->assertContains('teacher', $audiences);
        $this->assertNotContains('student', $audiences);
        $this->assertNotContains('manager', $audiences, 'an editing teacher is not a manager');
        $this->assertNotContains('admin', $audiences);

        $this->setUser($student);
        $this->assertContains('student', targeting::audiences_for_user($context));

        $this->setAdminUser();
        $audiences = targeting::audiences_for_user($context);
        $this->assertContains('admin', $audiences);
        $this->assertContains('developer', $audiences);
        $this->assertNotContains('student', $audiences);
    }

    /**
     * Front matter is optional, and a document that has none must be untouched.
     *
     * @return void
     */
    public function test_front_matter_is_optional(): void {
        $this->resetAfterTest();

        [$meta, $body] = front_matter::split("# Title\n\nBody.\n");

        $this->assertSame([], $meta);
        $this->assertSame("# Title\n\nBody.\n", $body);
    }

    /**
     * A document that opens with a horizontal rule is not a document with front
     * matter, and must not lose its first paragraph to one.
     *
     * @return void
     */
    public function test_a_leading_rule_is_not_front_matter(): void {
        $this->resetAfterTest();

        [$meta, $body] = front_matter::split("---\n\nJust a rule.\n");

        $this->assertSame([], $meta);
        $this->assertStringStartsWith('---', $body);
    }

    /**
     * The metadata block is understood, and taken off the body.
     *
     * @return void
     */
    public function test_front_matter_is_read_and_removed(): void {
        $this->resetAfterTest();

        $raw = "---\naudience: teacher, manager\npagetypes:\n  - mod-videotime-view\n  - mod-videotime-*\n---\n\n# Title\n";
        [$meta, $body] = front_matter::split($raw);

        $this->assertSame(['teacher', 'manager'], front_matter::values($meta, 'audience'));
        $this->assertSame(['mod-videotime-view', 'mod-videotime-*'], front_matter::values($meta, 'pagetypes'));
        $this->assertSame("# Title\n", $body);
        $this->assertStringNotContainsString('audience', $body);
    }

    /**
     * A document may ask to be left out of contextual help.
     *
     * @return void
     */
    public function test_a_document_can_opt_out(): void {
        $this->resetAfterTest();

        [$meta] = front_matter::split("---\nexclude: true\n---\n\n# Title\n");

        $this->assertTrue(front_matter::is_excluded($meta));
    }
}
