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
 * Reads and writes the tool_mdocs_index catalogue.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs;

/**
 * The catalogue of discovered documents, backed by a database table.
 */
class index_manager {
    /** @var string Database table name. */
    const TABLE = 'tool_mdocs_index';

    /**
     * Rebuild the whole catalogue from a fresh filesystem scan.
     *
     * @return int number of documents indexed
     */
    public function rebuild(): int {
        global $DB, $CFG;

        $discovery = new discovery();
        $documents = $discovery->discover();

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records(self::TABLE);
        $now = time();
        foreach ($documents as $doc) {
            $record = $doc->to_record();
            $record->timecreated = $now;
            $DB->insert_record(self::TABLE, $record);
        }
        $transaction->allow_commit();

        set_config('lastindexed', $now, 'tool_mdocs');
        set_config('lastversionshash', $CFG->allversionshash ?? '', 'tool_mdocs');

        // Drop any stale rendered output.
        \cache_helper::purge_by_definition('tool_mdocs', 'rendered');

        return count($documents);
    }

    /**
     * Whether the index is stale relative to the current plugin set.
     *
     * @return bool true when a rebuild is warranted
     */
    public function is_stale(): bool {
        global $CFG;
        if ($this->get_last_indexed() === 0) {
            return true;
        }
        $stored = (string) get_config('tool_mdocs', 'lastversionshash');
        return $stored !== (string) ($CFG->allversionshash ?? '');
    }

    /**
     * Timestamp of the last successful index build (0 if never).
     *
     * @return int
     */
    public function get_last_indexed(): int {
        return (int) get_config('tool_mdocs', 'lastindexed');
    }

    /**
     * Total number of indexed documents.
     *
     * @return int
     */
    public function count(): int {
        global $DB;
        return $DB->count_records(self::TABLE);
    }

    /**
     * Fetch a single document record by its stable id.
     *
     * @param string $docid
     * @return \stdClass|null
     */
    public function get_document(string $docid): ?\stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['docid' => $docid]);
        return $record ?: null;
    }

    /**
     * Fetch all documents, ordered for tree display.
     *
     * @return \stdClass[]
     */
    public function get_all(): array {
        global $DB;
        return array_values($DB->get_records(self::TABLE, null, 'component ASC, sortorder ASC, title ASC'));
    }

    /**
     * Search documents by title, component, path or content.
     *
     * Each returned record carries an {@see self::make_excerpt() excerpt}: a short
     * plain-text snippet around the first content match (empty for title/path-only
     * matches). The full content column is not returned.
     *
     * @param string $query user query
     * @param int $limit maximum rows
     * @return \stdClass[]
     */
    public function search(string $query, int $limit = 30): array {
        global $DB;

        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $like = '%' . $DB->sql_like_escape($query) . '%';
        $titlelike = $DB->sql_like('title', ':title', false);
        $complike = $DB->sql_like('component', ':component', false);
        $pathlike = $DB->sql_like('relpath', ':relpath', false);
        $contentlike = $DB->sql_like('content', ':content', false);
        $sql = "SELECT * FROM {" . self::TABLE . "}
                 WHERE $titlelike OR $complike OR $pathlike OR $contentlike
              ORDER BY title ASC";
        $params = ['title' => $like, 'component' => $like, 'relpath' => $like, 'content' => $like];

        $records = array_values($DB->get_records_sql($sql, $params, 0, $limit));
        foreach ($records as $record) {
            $record->excerpt = self::make_excerpt((string) ($record->content ?? ''), $query);
            unset($record->content);
        }
        return $records;
    }

    /**
     * Build a short plain-text excerpt centred on the first match of the query.
     *
     * @param string $content the document's plain-text content
     * @param string $query the search query
     * @param int $radius characters of context to include on each side of the match
     * @return string the excerpt (with ellipses where trimmed), or '' if there is no content
     */
    public static function make_excerpt(string $content, string $query, int $radius = 80): string {
        if ($content === '') {
            return '';
        }

        $pos = \core_text::strpos(\core_text::strtolower($content), \core_text::strtolower(trim($query)));
        if ($pos === false) {
            // Matched on title/component/path only: show the start of the document.
            $snippet = \core_text::substr($content, 0, $radius * 2);
            return \core_text::strlen($content) > \core_text::strlen($snippet) ? $snippet . '…' : $snippet;
        }

        $start = max(0, $pos - $radius);
        $length = $radius * 2 + \core_text::strlen(trim($query));
        $snippet = \core_text::substr($content, $start, $length);
        $prefix = $start > 0 ? '…' : '';
        $suffix = ($start + \core_text::strlen($snippet)) < \core_text::strlen($content) ? '…' : '';
        return $prefix . trim($snippet) . $suffix;
    }
}
