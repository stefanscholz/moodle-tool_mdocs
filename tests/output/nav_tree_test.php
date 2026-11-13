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
 * Unit tests for the navigation tree builder.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs\output;

/**
 * Tests for {@see \tool_mdocs\output\nav_tree}.
 *
 * These rely on the assignsubmission_* subplugins of mod_assign, which always
 * ship with Moodle core, to exercise subplugin folding.
 *
 * @covers \tool_mdocs\output\nav_tree
 */
final class nav_tree_test extends \advanced_testcase {
    /**
     * Build a minimal index record.
     *
     * @param string $component frankenstyle component
     * @param string $title document title
     * @param string $docid stable document id
     * @return \stdClass
     */
    protected function record(string $component, string $title, string $docid): \stdClass {
        $record = new \stdClass();
        $record->component = $component;
        $record->title = $title;
        $record->docid = $docid;
        $record->plugintype = explode('_', $component)[0];
        return $record;
    }

    /**
     * Index the returned top-level groups by their component key.
     *
     * @param array $tree the built tree
     * @return array<string, array>
     */
    protected function by_component(array $tree): array {
        $bycomponent = [];
        foreach ($tree as $group) {
            $bycomponent[$group['component']] = $group;
        }
        return $bycomponent;
    }

    /**
     * Subplugin documents should be folded under their parent plugin.
     *
     * @return void
     */
    public function test_subplugin_docs_are_folded_under_parent(): void {
        $this->resetAfterTest();

        $tree = nav_tree::build([
            $this->record('mod_assign', 'Assign overview', 'a1'),
            $this->record('assignsubmission_file', 'File submissions', 'b1'),
            $this->record('assignsubmission_comments', 'Comments', 'c1'),
            $this->record('mod_forum', 'Forum overview', 'd1'),
        ]);

        $bycomponent = $this->by_component($tree);

        // Two top-level groups; the subplugins are folded away, not standalone.
        $this->assertCount(2, $tree);
        $this->assertArrayHasKey('mod_assign', $bycomponent);
        $this->assertArrayHasKey('mod_forum', $bycomponent);
        $this->assertArrayNotHasKey('assignsubmission_file', $bycomponent);

        $assign = $bycomponent['mod_assign'];
        $this->assertTrue($assign['hasdocs']);
        $this->assertCount(1, $assign['docs']);
        $this->assertTrue($assign['hassubgroups']);
        $this->assertCount(2, $assign['subgroups']);
        $this->assertSame(3, $assign['doccount']);

        $subcomponents = array_map(fn($sub) => $sub['component'], $assign['subgroups']);
        $this->assertContains('assignsubmission_file', $subcomponents);
        $this->assertContains('assignsubmission_comments', $subcomponents);

        // A plugin with no subplugin docs keeps its docs and has no subgroups.
        $forum = $bycomponent['mod_forum'];
        $this->assertFalse($forum['hassubgroups']);
        $this->assertCount(1, $forum['docs']);
    }

    /**
     * An active subplugin document should flag its parent group as active.
     *
     * @return void
     */
    public function test_active_subplugin_document_flags_parent_group(): void {
        $this->resetAfterTest();

        $tree = nav_tree::build([
            $this->record('mod_assign', 'Assign overview', 'a1'),
            $this->record('assignsubmission_file', 'File submissions', 'b1'),
        ], 'b1');

        $this->assertCount(1, $tree);
        $this->assertTrue($tree[0]['hasactive']);
        $this->assertTrue($tree[0]['subgroups'][0]['hasactive']);
    }

    /**
     * A parent plugin with no documentation of its own should still appear as a
     * group when one of its subplugins ships documentation.
     *
     * @return void
     */
    public function test_parent_without_own_docs_still_groups_subplugins(): void {
        $this->resetAfterTest();

        $tree = nav_tree::build([
            $this->record('assignsubmission_file', 'File submissions', 'b1'),
        ]);

        $this->assertCount(1, $tree);
        $this->assertSame('mod_assign', $tree[0]['component']);
        $this->assertFalse($tree[0]['hasdocs']);
        $this->assertTrue($tree[0]['hassubgroups']);
        $this->assertSame(1, $tree[0]['doccount']);
    }

    /**
     * Documents in a folder below docs/ are listed under that folder as a section,
     * in the order their documents come in; documents directly in docs/ come first.
     *
     * @return void
     */
    public function test_documents_are_grouped_by_their_folder(): void {
        $this->resetAfterTest();

        $files = [
            ['About', 'docs/README.md'],
            ['Getting started', 'docs/introduction/getting-started.md'],
            ['Installation', 'docs/installation-and-setup/installation.md'],
            ['Settings', 'docs/installation-and-setup/settings.md'],
            ['Capabilities', 'docs/reference_pages/capabilities.md'],
        ];
        $records = [];
        foreach ($files as $i => [$title, $relpath]) {
            $record = $this->record('mod_forum', $title, 'f' . $i);
            $record->relpath = $relpath;
            $records[] = $record;
        }

        $group = $this->by_component(nav_tree::build($records, 'f3'))['mod_forum'];

        $this->assertSame(['About'], array_column($group['docs'], 'title'));
        $this->assertTrue($group['hassections']);
        $this->assertSame(
            ['Introduction', 'Installation and setup', 'Reference pages'],
            array_column($group['sections'], 'displayname')
        );
        $this->assertSame(['Installation', 'Settings'], array_column($group['sections'][1]['docs'], 'title'));
        $this->assertTrue($group['sections'][1]['hasactive']);
        $this->assertFalse($group['sections'][0]['hasactive']);
        $this->assertTrue($group['hasactive']);
        $this->assertSame(5, $group['doccount']);
    }

    /**
     * The section is the folder the file sits in; files in the top folder have none.
     *
     * @return void
     */
    public function test_section_of(): void {
        $this->assertNull(nav_tree::section_of('README.md'));
        $this->assertNull(nav_tree::section_of('docs/README.md'));
        $this->assertSame('reports', nav_tree::section_of('docs/reports/overview.md'));
        $this->assertSame('deep', nav_tree::section_of('docs/reports/deep/a.md'));
        $this->assertSame('Skill decay', nav_tree::section_name('skill-decay'));
    }
}
