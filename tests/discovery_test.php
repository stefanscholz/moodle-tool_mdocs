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
 * Unit tests for the discovery engine.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for {@see \tool_mdocs\discovery}.
 */
#[CoversClass(discovery::class)]
final class discovery_test extends \advanced_testcase {
    /**
     * Absolute path to the sample plugin fixture.
     *
     * @return string
     */
    protected function fixture_dir(): string {
        return __DIR__ . '/fixtures/sampleplugin';
    }

    /**
     * Collect the relative paths returned when scanning the fixture.
     *
     * @return string[]
     */
    protected function scan(): array {
        $discovery = new discovery();
        $docs = $discovery->scan_component('fixture_sample', 'fixture', $this->fixture_dir());
        $paths = array_map(fn($doc) => $doc->relpath, $docs);
        sort($paths);
        return $paths;
    }

    /**
     * Folders mode should include only files inside the configured folders.
     *
     * @return void
     */
    public function test_folders_mode_includes_only_configured_folders(): void {
        $this->resetAfterTest();
        set_config('inclusionmode', settings_helper::MODE_FOLDERS, 'tool_mdocs');
        set_config('includefolders', 'docs', 'tool_mdocs');

        $this->assertEquals(['docs/guide.md'], $this->scan());
    }

    /**
     * Filenames mode should include only files with a configured basename.
     *
     * @return void
     */
    public function test_filenames_mode_includes_only_configured_basenames(): void {
        $this->resetAfterTest();
        set_config('inclusionmode', settings_helper::MODE_FILENAMES, 'tool_mdocs');
        set_config('includefilenames', 'readme.md', 'tool_mdocs');

        $this->assertEquals(['readme.md'], $this->scan());
    }

    /**
     * All mode should index every Markdown file but never descend into vendor/.
     *
     * @return void
     */
    public function test_all_mode_includes_every_markdown_but_prunes_vendor(): void {
        $this->resetAfterTest();
        set_config('inclusionmode', settings_helper::MODE_ALL, 'tool_mdocs');

        $paths = $this->scan();
        $this->assertContains('readme.md', $paths);
        $this->assertContains('CHANGES.md', $paths);
        $this->assertContains('docs/guide.md', $paths);
        // Files under vendor/ must never be indexed.
        $this->assertNotContains('vendor/library.md', $paths);
    }

    /**
     * The title should be taken from the first level-one heading.
     *
     * @return void
     */
    public function test_title_is_extracted_from_first_heading(): void {
        $this->resetAfterTest();
        set_config('inclusionmode', settings_helper::MODE_ALL, 'tool_mdocs');

        $discovery = new discovery();
        $docs = $discovery->scan_component('fixture_sample', 'fixture', $this->fixture_dir());
        $bypath = [];
        foreach ($docs as $doc) {
            $bypath[$doc->relpath] = $doc;
        }

        $this->assertSame('Guide title', $bypath['docs/guide.md']->title);
        $this->assertSame('Sample plugin', $bypath['readme.md']->title);
    }

    /**
     * The docid should be a stable, component-relative hash.
     *
     * @return void
     */
    public function test_docid_is_stable_and_component_relative(): void {
        $this->resetAfterTest();
        set_config('inclusionmode', settings_helper::MODE_ALL, 'tool_mdocs');

        $discovery = new discovery();
        $docs = $discovery->scan_component('fixture_sample', 'fixture', $this->fixture_dir());
        foreach ($docs as $doc) {
            $this->assertSame(document::make_docid('fixture_sample', $doc->relpath), $doc->docid);
        }
    }

    /**
     * The plain-text body of each document should be captured for searching,
     * with Markdown punctuation stripped.
     *
     * @return void
     */
    public function test_document_content_is_captured_as_plain_text(): void {
        $this->resetAfterTest();
        set_config('inclusionmode', settings_helper::MODE_ALL, 'tool_mdocs');

        $discovery = new discovery();
        $bypath = [];
        foreach ($discovery->scan_component('fixture_sample', 'fixture', $this->fixture_dir()) as $doc) {
            $bypath[$doc->relpath] = $doc;
        }

        $this->assertArrayHasKey('readme.md', $bypath);
        $this->assertStringContainsString('root readme for the discovery test fixture', $bypath['readme.md']->content);
        // Heading markers and other Markdown punctuation are stripped.
        $this->assertStringNotContainsString('#', $bypath['readme.md']->content);
        // Underscores survive, so code identifiers stay searchable as written.
        $this->assertStringContainsString('tool_mdocs_index', $bypath['readme.md']->content);
    }

    /**
     * A directory that is itself another component's root must not be descended
     * into, so its files are attributed to that (sub)plugin and not indexed twice.
     *
     * @return void
     */
    public function test_nested_component_directories_are_excluded(): void {
        $this->resetAfterTest();
        set_config('inclusionmode', settings_helper::MODE_ALL, 'tool_mdocs');

        $discovery = new discovery();
        $nesteddir = realpath($this->fixture_dir() . '/plugin/sub');

        // Without any exclusion, the nested file is discovered as part of the parent.
        $unscoped = array_map(
            fn($doc) => $doc->relpath,
            $discovery->scan_component('fixture_sample', 'fixture', $this->fixture_dir())
        );
        $this->assertContains('plugin/sub/nested.md', $unscoped);

        // When the nested directory is a known component root, its files are skipped.
        $scoped = array_map(
            fn($doc) => $doc->relpath,
            $discovery->scan_component('fixture_sample', 'fixture', $this->fixture_dir(), [$nesteddir => true])
        );
        $this->assertContains('readme.md', $scoped);
        $this->assertContains('docs/guide.md', $scoped);
        $this->assertNotContains('plugin/sub/nested.md', $scoped);
    }

    /**
     * Folder matching should compare whole path segments, not substrings.
     *
     * @return void
     */
    public function test_is_included_matches_path_segments_not_substrings(): void {
        $this->resetAfterTest();
        set_config('inclusionmode', settings_helper::MODE_FOLDERS, 'tool_mdocs');
        set_config('includefolders', 'docs', 'tool_mdocs');

        $discovery = new discovery();
        $this->assertTrue($discovery->is_included('docs/guide.md'));
        $this->assertTrue($discovery->is_included('sub/docs/deep.md'));
        // Note: "documentation" contains "docs" as a substring but is not a matching segment.
        $this->assertFalse($discovery->is_included('documentation/guide.md'));
        $this->assertFalse($discovery->is_included('readme.md'));
    }
}
