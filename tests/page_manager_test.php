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
 * Unit tests for the user-page manager.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs;

/**
 * Tests for {@see \tool_mdocs\page_manager}.
 *
 * @covers \tool_mdocs\page_manager
 */
final class page_manager_test extends \advanced_testcase {

    /**
     * Build a form-shaped data object for a page.
     *
     * @param string $title
     * @param string $content
     * @param string $component
     * @param int $visible
     * @return \stdClass
     */
    protected function page_data(string $title, string $content = '# Body', string $component = '', int $visible = 1): \stdClass {
        return (object) [
            'id' => 0,
            'title' => $title,
            'content' => $content,
            'contentformat' => FORMAT_MARKDOWN,
            'component' => $component,
            'visible' => $visible,
        ];
    }

    /**
     * Saving then loading a page round-trips its fields.
     *
     * @return void
     */
    public function test_save_and_get(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $manager = new page_manager();

        $id = $manager->save_page($this->page_data('Onboarding', '# Onboarding', 'mod_forum', 1));
        $page = $manager->get_page($id);

        $this->assertNotNull($page);
        $this->assertSame('Onboarding', $page->title);
        $this->assertSame('mod_forum', $page->component);
        $this->assertEquals(1, $page->visible);
        $this->assertEquals(FORMAT_MARKDOWN, $page->contentformat);
    }

    /**
     * Standalone pages store a NULL component.
     *
     * @return void
     */
    public function test_standalone_component_is_null(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $manager = new page_manager();

        $id = $manager->save_page($this->page_data('Site note', '# Note', '', 1));
        $this->assertNull($manager->get_page($id)->component);
    }

    /**
     * Drafts are hidden unless explicitly requested.
     *
     * @return void
     */
    public function test_get_pages_hides_drafts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $manager = new page_manager();

        $manager->save_page($this->page_data('Published one', '# A', '', 1));
        $manager->save_page($this->page_data('Draft one', '# B', '', 0));

        $this->assertCount(1, $manager->get_pages(false));
        $this->assertCount(2, $manager->get_pages(true));
    }

    /**
     * Pages can be filtered by their associated component.
     *
     * @return void
     */
    public function test_get_pages_component_filter(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $manager = new page_manager();

        $manager->save_page($this->page_data('Standalone', '# A', '', 1));
        $manager->save_page($this->page_data('Attached', '# B', 'mod_forum', 1));

        $standalone = $manager->get_pages(true, '');
        $this->assertCount(1, $standalone);
        $this->assertSame('Standalone', reset($standalone)->title);

        $attached = $manager->get_pages(true, 'mod_forum');
        $this->assertCount(1, $attached);
        $this->assertSame('Attached', reset($attached)->title);
    }

    /**
     * Search matches titles and respects draft visibility.
     *
     * @return void
     */
    public function test_search_pages(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $manager = new page_manager();

        $manager->save_page($this->page_data('Alpha guide', '# Alpha', '', 1));
        $manager->save_page($this->page_data('Alpha draft', '# Alpha', '', 0));

        $this->assertCount(1, $manager->search_pages('Alpha', false));
        $this->assertCount(2, $manager->search_pages('Alpha', true));
        $this->assertCount(0, $manager->search_pages('nomatch', true));
    }

    /**
     * Deleting a page removes its record and any attached files.
     *
     * @return void
     */
    public function test_delete_removes_record_and_files(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $manager = new page_manager();
        $context = \context_system::instance();

        $id = $manager->save_page($this->page_data('With image', '# Img', '', 1));

        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'tool_mdocs',
            'filearea'  => page_manager::FILEAREA,
            'itemid'    => $id,
            'filepath'  => '/',
            'filename'  => 'pic.png',
        ], 'PNGDATA');

        $this->assertCount(1, $fs->get_area_files($context->id, 'tool_mdocs', page_manager::FILEAREA, $id, 'id', false));

        $manager->delete_page($id);

        $this->assertNull($manager->get_page($id));
        $this->assertCount(0, $fs->get_area_files($context->id, 'tool_mdocs', page_manager::FILEAREA, $id, 'id', false));
    }
}
