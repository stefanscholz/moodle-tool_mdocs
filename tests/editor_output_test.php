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
 * Tests that what the editor toolbar writes actually renders.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs;

/**
 * Every toolbar button writes a snippet of Markdown. These assert that each of
 * those snippets renders to the element the button promises.
 *
 * The Markdown here is copied from what amd/src/editor.js produces. Two of these
 * caught real bugs: a list written straight after a paragraph is read as more of
 * that paragraph rather than a list, and a link whose target is not a valid URL
 * has its href stripped by the output sanitiser, leaving a dead anchor.
 *
 * @covers \tool_mdocs\markdown
 */
final class editor_output_test extends \advanced_testcase {
    /**
     * What each toolbar button writes, and what it has to render to.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function toolbar_provider(): array {
        return [
            'bold'                       => ["make me **bold**", '<strong>bold</strong>'],
            'italic'                     => ["make me *italic*", '<em>italic</em>'],
            'heading'                    => ["## My title", '<h2'],
            'quote'                      => ["> A quotation", '<blockquote>'],
            // Note: \x60 is a backtick, which is Markdown's code marker.
            'inline code'                => ["call \x60foo()\x60 here", '<code>foo()</code>'],
            'fenced code'                => ["\x60\x60\x60\none\ntwo\n\x60\x60\x60", '<pre>'],
            'link'                       => ["click [here](https://example.com)", '<a href="https://example.com"'],
            'image'                      => ["![alt](@@PLUGINFILE@@/image.png)", '<img'],
            'bullet list'                => ["- one\n- two", '<ul>'],
            'numbered list'              => ["1. one\n2. two", '<ol>'],
            'bullet list after a para'   => ["Intro text\n\n- item", '<ul>'],
            'numbered list after a para' => ["Intro text\n\n1. item", '<ol>'],
        ];
    }

    /**
     * Each toolbar snippet must render to the element it promises.
     *
     * @dataProvider toolbar_provider
     * @param string $markdown what the button writes
     * @param string $expected the markup it must produce
     * @return void
     */
    public function test_toolbar_output_renders(string $markdown, string $expected): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $html = markdown::preview($markdown, \context_system::instance())['html'];

        $this->assertStringContainsString($expected, $html);
    }

    /**
     * The image button writes an @@PLUGINFILE@@ placeholder. The preview has to
     * resolve it against the author's draft area, or the image renders broken and
     * format_text() complains that the content was never rewritten.
     *
     * @return void
     */
    public function test_image_placeholder_is_resolved_in_the_preview(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $html = markdown::preview("![alt](@@PLUGINFILE@@/image.png)", \context_system::instance())['html'];

        $this->assertStringContainsString('<img', $html);
        $this->assertStringNotContainsString('@@PLUGINFILE@@', $html);
    }

    /**
     * The bug this guards: without a blank line, a list marker on the line after a
     * paragraph is a lazy continuation of that paragraph, not a list. The toolbar
     * inserts the blank line for exactly this reason.
     *
     * @return void
     */
    public function test_a_list_glued_to_the_paragraph_above_is_not_a_list(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $html = markdown::preview("Intro text\n- item", \context_system::instance())['html'];

        $this->assertStringNotContainsString('<ul>', $html);
    }

    /**
     * The other bug this guards: the sanitiser drops the href of a link whose
     * target is not a valid URL, so the toolbar must not write a bare scheme.
     *
     * @return void
     */
    public function test_a_link_with_a_bare_scheme_loses_its_href(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $html = markdown::preview("click [here](https://)", \context_system::instance())['html'];

        $this->assertStringNotContainsString('href', $html);
    }
}
