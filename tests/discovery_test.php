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
     * With only a folder list, nothing outside those folders is indexed.
     *
     * @return void
     */
    public function test_folder_list_includes_only_configured_folders(): void {
        $this->resetAfterTest();
        set_config('includefilenames', '', 'tool_mdocs');
        set_config('includefolders', 'docs', 'tool_mdocs');

        $this->assertEquals(['docs/guide.md', 'docs/teacher-guide.md'], $this->scan());
    }

    /**
     * With only a file-name list, nothing but those names is indexed.
     *
     * @return void
     */
    public function test_filename_list_includes_only_configured_basenames(): void {
        $this->resetAfterTest();
        set_config('includefolders', '', 'tool_mdocs');
        set_config('includefilenames', 'readme.md', 'tool_mdocs');

        $this->assertEquals(['readme.md'], $this->scan());
    }

    /**
     * The two lists add up: a file is indexed when it matches either of them.
     *
     * This is the rule an administrator actually wants to express — "everything
     * in docs, and also readme.md and changes.md" — which used to be impossible
     * because the two lists were a choice rather than a sum.
     *
     * @return void
     */
    public function test_folder_and_filename_lists_add_up(): void {
        $this->resetAfterTest();
        set_config('includefolders', 'docs', 'tool_mdocs');
        set_config('includefilenames', "readme.md\nchanges.md", 'tool_mdocs');

        $paths = $this->scan();

        $this->assertContains('docs/guide.md', $paths);
        $this->assertContains('readme.md', $paths);
        $this->assertContains('CHANGES.md', $paths);
        // Matched by neither list.
        $this->assertNotContains('examples/tips.md', $paths);
    }

    /**
     * Exclusion wins over both lists, and over "include everything".
     *
     * A name is matched against the file itself and against every folder in its
     * path, so one unwanted file no longer forces the rules to be narrowed for
     * all the others.
     *
     * @return void
     */
    public function test_exclusion_beats_every_inclusion_rule(): void {
        $this->resetAfterTest();
        set_config('includefolders', 'docs', 'tool_mdocs');
        set_config('includefilenames', "readme.md\ninstall.md", 'tool_mdocs');
        set_config('excludenames', "install.md\nexamples", 'tool_mdocs');

        $paths = $this->scan();

        $this->assertContains('readme.md', $paths);
        $this->assertContains('docs/guide.md', $paths);
        // Excluded by its own name, even though the file-name list asks for it.
        $this->assertNotContains('INSTALL.md', $paths);
        // Excluded by the folder it sits in.
        $this->assertNotContains('examples/tips.md', $paths);

        // Even "index everything" does not overrule an exclusion.
        set_config('includeall', 1, 'tool_mdocs');
        $paths = $this->scan();
        $this->assertContains('CHANGES.md', $paths);
        $this->assertNotContains('INSTALL.md', $paths);
        $this->assertNotContains('examples/tips.md', $paths);
    }

    /**
     * Including everything indexes every Markdown file but never descends into vendor/.
     *
     * @return void
     */
    public function test_include_all_includes_every_markdown_but_prunes_vendor(): void {
        $this->resetAfterTest();
        set_config('includeall', 1, 'tool_mdocs');

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
        set_config('includeall', 1, 'tool_mdocs');

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
        set_config('includeall', 1, 'tool_mdocs');

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
        set_config('includeall', 1, 'tool_mdocs');

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
        set_config('includeall', 1, 'tool_mdocs');

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
        set_config('includefilenames', '', 'tool_mdocs');
        set_config('includefolders', 'docs', 'tool_mdocs');

        $discovery = new discovery();
        $this->assertTrue($discovery->is_included('docs/guide.md'));
        $this->assertTrue($discovery->is_included('sub/docs/deep.md'));
        // Note: "documentation" contains "docs" as a substring but is not a matching segment.
        $this->assertFalse($discovery->is_included('documentation/guide.md'));
        $this->assertFalse($discovery->is_included('readme.md'));
    }

    /**
     * Front matter is metadata, not prose. If it survives into the title, the
     * searchable text or the rendered page, the reader sees "--- audience: teacher"
     * as if it were the first paragraph.
     *
     * @return void
     */
    public function test_front_matter_never_leaks_into_the_document(): void {
        $this->resetAfterTest();
        set_config('includeall', 1, 'tool_mdocs');

        $discovery = new discovery();
        $bypath = [];
        foreach ($discovery->scan_component('fixture_sample', 'fixture', $this->fixture_dir()) as $doc) {
            $bypath[$doc->relpath] = $doc;
        }

        $doc = $bypath['docs/teacher-guide.md'];

        $this->assertSame('Fixture teacher guide', $doc->title);
        $this->assertStringContainsString('searchable', $doc->content);
        $this->assertStringNotContainsString('audience', $doc->content);
        $this->assertStringNotContainsString('pagetypes', $doc->content);
    }

    /**
     * What the front matter declares beats what the file name and directory guess.
     *
     * @return void
     */
    public function test_front_matter_overrides_the_guess(): void {
        $this->resetAfterTest();
        set_config('includeall', 1, 'tool_mdocs');

        $discovery = new discovery();
        $bypath = [];
        foreach ($discovery->scan_component('fixture_sample', 'fixture', $this->fixture_dir()) as $doc) {
            $bypath[$doc->relpath] = $doc;
        }

        $doc = $bypath['docs/teacher-guide.md'];

        $this->assertSame(['teacher'], $doc->audiences);
        // Declared explicitly, so the mod-videotime-* the component would have
        // guessed does not apply.
        $this->assertSame(['mod-videotime-view'], $doc->pagetypes);
        $this->assertSame(20, $doc->sortorder);
        $this->assertSame(20, $doc->to_record()->sortorder);
    }

    /**
     * A document that requires a missing component is left out of the index.
     *
     * @return void
     */
    public function test_documents_requiring_missing_components_are_skipped(): void {
        $this->resetAfterTest();
        set_config('includeall', 1, 'tool_mdocs');

        $dir = make_request_directory();
        mkdir($dir . '/docs');
        file_put_contents($dir . '/docs/always.md', "# Always\n\nNo requirements.\n");
        file_put_contents($dir . '/docs/installed.md', "---\nrequires: tool_mdocs\n---\n\n# Installed\n");
        file_put_contents($dir . '/docs/missing.md', "---\nrequires:\n  - tool_mdocs\n  - local_doesnotexist\n---\n\n# Missing\n");

        $discovery = new discovery();
        $paths = array_map(fn($doc) => $doc->relpath, $discovery->scan_component('fixture_sample', 'fixture', $dir));
        sort($paths);

        $this->assertSame(['docs/always.md', 'docs/installed.md'], $paths);
    }

    /**
     * Installed means installed: code alone, or an unknown name, does not count.
     *
     * @return void
     */
    public function test_requirements_met(): void {
        $this->resetAfterTest();

        $this->assertTrue(discovery::requirements_met([]));
        $this->assertTrue(discovery::requirements_met(['tool_mdocs', 'mod_forum', 'core_course']));
        $this->assertFalse(discovery::requirements_met(['tool_mdocs', 'local_doesnotexist']));
        $this->assertFalse(discovery::requirements_met(['core_doesnotexist']));

        // Code that is on disk but not installed yet: the upgrade has not run.
        unset_config('version', 'mod_forum');
        $this->assertFalse(discovery::requirements_met(['mod_forum']));
    }
}
