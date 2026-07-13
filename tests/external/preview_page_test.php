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
 * Unit tests for the editor preview external function.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs\external;

/**
 * Tests for {@see \tool_mdocs\external\preview_page}.
 *
 * @covers \tool_mdocs\external\preview_page
 */
final class preview_page_test extends \advanced_testcase {
    /**
     * Render Markdown through the external function as an admin.
     *
     * @param string $content raw Markdown
     * @return string the returned HTML
     */
    protected function preview(string $content): string {
        $result = preview_page::execute($content);
        $result = \core_external\external_api::clean_returnvalue(preview_page::execute_returns(), $result);
        return $result['html'];
    }

    /**
     * Markdown should come back rendered, with heading anchors, just like a saved page.
     *
     * @return void
     */
    public function test_markdown_is_rendered_with_anchors(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $html = $this->preview("# Hello world\n\nSome **bold** text.\n");

        $this->assertStringContainsString('id="hello-world"', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
    }

    /**
     * The preview must not become an XSS hole: it runs through the same output
     * sanitiser as a published page.
     *
     * @return void
     */
    public function test_embedded_scripts_are_stripped(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $html = $this->preview("# Title\n\n<script>alert('xss');</script>\n\nSafe text.\n");

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('Safe text.', $html);
    }

    /**
     * Empty content should render to nothing, not blow up.
     *
     * @return void
     */
    public function test_empty_content_renders_empty(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame('', $this->preview(''));
    }

    /**
     * A user without the page-management capability must not be able to preview.
     *
     * @return void
     */
    public function test_preview_requires_the_managepages_capability(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\required_capability_exception::class);
        preview_page::execute('# Nope');
    }
}
