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
 * Unit tests for the Markdown rendering pipeline.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for {@see \tool_mdocs\markdown}.
 */
#[CoversClass(markdown::class)]
final class markdown_test extends \advanced_testcase {
    /**
     * Headings should receive anchors and be collected into the table of contents.
     *
     * @return void
     */
    public function test_headings_get_anchors_and_toc(): void {
        $this->resetAfterTest();
        $context = \context_system::instance();

        $raw = "# Hello world\n\nBody.\n\n## Sub section\n\nMore.\n";
        $result = markdown::process($raw, 'mod_forum', 'readme.md', $context);

        $this->assertStringContainsString('id="hello-world"', $result['html']);
        $this->assertStringContainsString('id="sub-section"', $result['html']);
        $this->assertCount(2, $result['toc']);
        $this->assertSame('Hello world', $result['toc'][0]['text']);
        $this->assertSame('hello-world', $result['toc'][0]['id']);
        $this->assertSame(1, $result['toc'][0]['level']);
        $this->assertSame(2, $result['toc'][1]['level']);
    }

    /**
     * A relative link to another Markdown file should point at the viewer.
     *
     * @return void
     */
    public function test_relative_document_link_is_rewritten_to_viewer(): void {
        $this->resetAfterTest();
        set_config('inclusionmode', settings_helper::MODE_ALL, 'tool_mdocs');
        $context = \context_system::instance();

        $raw = "# Title\n\nSee the [guide](docs/guide.md).\n";
        $result = markdown::process($raw, 'mod_forum', 'readme.md', $context);

        $expecteddocid = document::make_docid('mod_forum', 'docs/guide.md');
        $this->assertStringContainsString('view.php', $result['html']);
        $this->assertStringContainsString($expecteddocid, $result['html']);
    }

    /**
     * A relative image should be routed through the secure file server.
     *
     * @return void
     */
    public function test_relative_image_is_rewritten_to_file_server(): void {
        $this->resetAfterTest();
        $context = \context_system::instance();

        $raw = "# Title\n\n![pic](images/p.png)\n";
        $result = markdown::process($raw, 'mod_forum', 'readme.md', $context);

        $this->assertStringContainsString('file.php', $result['html']);
        $this->assertStringContainsString('images', $result['html']);
    }

    /**
     * Absolute external links should be left unchanged.
     *
     * @return void
     */
    public function test_external_links_are_left_untouched(): void {
        $this->resetAfterTest();
        $context = \context_system::instance();

        $raw = "# Title\n\n[external](https://example.com/page).\n";
        $result = markdown::process($raw, 'mod_forum', 'readme.md', $context);

        $this->assertStringContainsString('https://example.com/page', $result['html']);
        $this->assertStringNotContainsString('view.php', $result['html']);
    }

    /**
     * References that escape the component directory must not become viewer links.
     *
     * @return void
     */
    public function test_links_escaping_the_component_root_are_not_rewritten(): void {
        $this->resetAfterTest();
        set_config('inclusionmode', settings_helper::MODE_ALL, 'tool_mdocs');
        $context = \context_system::instance();

        $raw = "# Title\n\n[up](../../secret.md)\n";
        $result = markdown::process($raw, 'mod_forum', 'readme.md', $context);

        // The reference escapes the component directory, so it must not become a viewer link.
        $this->assertStringNotContainsString('view.php', $result['html']);
    }

    /**
     * In-page anchor links (href="#...") should be left untouched, not rewritten.
     *
     * @return void
     */
    public function test_inpage_anchor_links_are_left_untouched(): void {
        $this->resetAfterTest();
        $context = \context_system::instance();

        $raw = "# Title\n\nJump to the [next section](#sub-section).\n\n## Sub section\n\nBody.\n";
        $result = markdown::process($raw, 'mod_forum', 'readme.md', $context);

        // The in-page anchor must survive and must not be turned into a viewer/file link.
        $this->assertStringContainsString('href="#sub-section"', $result['html']);
        $this->assertStringNotContainsString('view.php', $result['html']);
        $this->assertStringNotContainsString('file.php', $result['html']);
    }

    /**
     * Embedded scripts should be stripped by the output sanitiser.
     *
     * @return void
     */
    public function test_embedded_scripts_are_stripped(): void {
        $this->resetAfterTest();
        $context = \context_system::instance();

        $raw = "# Title\n\n<script>alert('xss');</script>\n\nSafe text.\n";
        $result = markdown::process($raw, 'mod_forum', 'readme.md', $context);

        $this->assertStringNotContainsString('<script', $result['html']);
        $this->assertStringContainsString('Safe text.', $result['html']);
    }
}
