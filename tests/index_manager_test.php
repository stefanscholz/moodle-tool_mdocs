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
 * Unit tests for the index catalogue, focused on content search.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs;

/**
 * Tests for {@see \tool_mdocs\index_manager}.
 *
 * @covers \tool_mdocs\index_manager
 */
final class index_manager_test extends \advanced_testcase {
    /**
     * Insert a document row directly into the catalogue.
     *
     * @param string $docid stable document id
     * @param string $component frankenstyle component
     * @param string $title document title
     * @param string $content plain-text body
     * @return void
     */
    protected function insert(string $docid, string $component, string $title, string $content): void {
        global $DB;
        $DB->insert_record(index_manager::TABLE, (object) [
            'docid'        => $docid,
            'component'    => $component,
            'plugintype'   => 'mod',
            'relpath'      => 'readme.md',
            'basename'     => 'readme.md',
            'title'        => $title,
            'content'      => $content,
            'sortorder'    => 0,
            'filesize'     => 0,
            'timemodified' => 0,
            'timecreated'  => 0,
        ]);
    }

    /**
     * A word that appears only in the body should still match.
     *
     * @return void
     */
    public function test_search_matches_document_content(): void {
        $this->resetAfterTest();
        $this->insert('a1', 'mod_forum', 'Forum overview', 'Discussion forums let participants exchange messages.');
        $this->insert('b1', 'mod_quiz', 'Quiz guide', 'A quiz is made up of questions.');

        $results = (new index_manager())->search('participants');

        $this->assertCount(1, $results);
        $this->assertSame('a1', reset($results)->docid);
    }

    /**
     * A content match should return a trimmed excerpt and never leak the full body.
     *
     * @return void
     */
    public function test_search_returns_excerpt_around_match(): void {
        $this->resetAfterTest();
        $content = str_repeat('padding ', 40) . 'the secrettoken appears here ' . str_repeat('tail ', 40);
        $this->insert('a1', 'mod_forum', 'Forum overview', $content);

        $results = (new index_manager())->search('secrettoken');

        $this->assertCount(1, $results);
        $row = reset($results);
        $this->assertStringContainsString('secrettoken', $row->excerpt);
        $this->assertStringContainsString('…', $row->excerpt);
        $this->assertLessThan(strlen($content), strlen($row->excerpt));
        // The bulky content column must not be returned to callers.
        $this->assertFalse(property_exists($row, 'content'));
    }

    /**
     * Listings must not drag every document's body out of the database. They run
     * on every page render and only need the metadata to build the navigation.
     *
     * @return void
     */
    public function test_listings_do_not_load_document_content(): void {
        $this->resetAfterTest();
        $this->insert('a1', 'mod_forum', 'Forum overview', 'A very long body that has no business being loaded.');

        $manager = new index_manager();

        $all = $manager->get_all();
        $this->assertCount(1, $all);
        $this->assertSame('Forum overview', reset($all)->title);
        $this->assertFalse(property_exists(reset($all), 'content'));

        $doc = $manager->get_document('a1');
        $this->assertNotNull($doc);
        $this->assertSame('Forum overview', $doc->title);
        $this->assertFalse(property_exists($doc, 'content'));
    }

    /**
     * Changing the inclusion rules must invalidate the catalogue, otherwise the
     * Documentation Center keeps serving documents from the previous rules.
     *
     * @return void
     */
    public function test_index_is_stale_when_inclusion_settings_change(): void {
        global $CFG;
        $this->resetAfterTest();

        $manager = new index_manager();

        // Never built.
        $this->assertTrue($manager->is_stale());

        // Simulate a completed build under the current rules.
        set_config('lastindexed', time(), 'tool_mdocs');
        set_config('lastversionshash', (string) ($CFG->allversionshash ?? ''), 'tool_mdocs');
        set_config('lastsettingshash', settings_helper::signature(), 'tool_mdocs');
        $this->assertFalse($manager->is_stale());

        // Any change to which files are included makes it stale again.
        set_config('includefolders', '', 'tool_mdocs');
        $this->assertTrue($manager->is_stale());
    }

    /**
     * A rebuild should record the rules it ran with, so it is not immediately
     * considered stale again.
     *
     * @return void
     */
    public function test_rebuild_records_the_settings_it_used(): void {
        $this->resetAfterTest();
        set_config('includefolders', '', 'tool_mdocs');
        set_config('includefilenames', 'zzznotarealfile.md', 'tool_mdocs');

        $manager = new index_manager();
        $manager->rebuild();

        $this->assertFalse($manager->is_stale());
        $this->assertSame(settings_helper::signature(), get_config('tool_mdocs', 'lastsettingshash'));
    }

    /**
     * A title-only match should still return a (leading) excerpt without error.
     *
     * @return void
     */
    public function test_search_title_match_falls_back_to_leading_excerpt(): void {
        $this->resetAfterTest();
        $this->insert('a1', 'mod_forum', 'Uniquetitleword here', 'Body text without the query term.');

        $results = (new index_manager())->search('uniquetitleword');

        $this->assertCount(1, $results);
        $this->assertStringContainsString('Body text without', reset($results)->excerpt);
    }
}
