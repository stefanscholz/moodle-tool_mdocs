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

/**
 * Tests for {@see \tool_mdocs\discovery}.
 *
 * @covers \tool_mdocs\discovery
 */
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
        // "documentation" contains "docs" as a substring but is not a matching segment.
        $this->assertFalse($discovery->is_included('documentation/guide.md'));
        $this->assertFalse($discovery->is_included('readme.md'));
    }
}
