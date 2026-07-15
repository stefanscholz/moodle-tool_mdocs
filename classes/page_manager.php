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
 * CRUD and queries for user-authored documentation pages.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs;

/**
 * Reads and writes the tool_mdocs_page table and its attached files.
 */
class page_manager {
    /** @var string Database table name. */
    const TABLE = 'tool_mdocs_page';

    /** @var string File area for images embedded in a page. */
    const FILEAREA = 'content';

    /** @var string Every column except the page body, which listings never display. */
    const METADATA_FIELDS = 'id, title, contentformat, component, visible, sortorder, ' .
        'usercreated, usermodified, timecreated, timemodified';

    /**
     * Fetch a single page by id, body included: this is what the viewer renders.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public function get_page(int $id): ?\stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['id' => $id]);
        return $record ?: null;
    }

    /**
     * Fetch pages, optionally including drafts and/or limited to one component.
     *
     * The body is deliberately excluded. This runs on every page render to build
     * the navigation, which only shows titles, so loading the Markdown of every
     * page would pull the whole site's documentation out of the database to draw
     * a list of links.
     *
     * @param bool $includehidden include draft (non-visible) pages
     * @param string|null $component restrict to this component (use '' for standalone)
     * @return \stdClass[]
     */
    public function get_pages(bool $includehidden = false, ?string $component = null): array {
        global $DB;

        $conditions = [];
        if (!$includehidden) {
            $conditions['visible'] = 1;
        }
        if ($component !== null) {
            $conditions['component'] = ($component === '') ? null : $component;
        }

        return array_values(
            $DB->get_records(self::TABLE, $conditions, 'sortorder ASC, title ASC', self::METADATA_FIELDS)
        );
    }

    /**
     * Create or update a page.
     *
     * @param \stdClass $data form-shaped data (id, title, content, contentformat, component, visible)
     * @return int the page id
     */
    public function save_page(\stdClass $data): int {
        global $DB, $USER;

        $pagetypes = self::clean_list($data->pagetypes ?? '');
        $audiences = array_values(array_intersect(self::clean_list($data->audience ?? ''), targeting::AUDIENCES));

        $now = time();
        $record = new \stdClass();
        $record->title = $data->title;
        $record->content = $data->content;
        $record->contentformat = isset($data->contentformat) ? (int) $data->contentformat : FORMAT_MARKDOWN;
        $record->component = (empty($data->component)) ? null : $data->component;
        $record->pagetypes = $pagetypes ? implode(',', $pagetypes) : null;
        $record->audience = $audiences ? implode(',', $audiences) : null;
        $record->visible = empty($data->visible) ? 0 : 1;
        $record->sortorder = isset($data->sortorder) ? (int) $data->sortorder : 0;
        $record->usermodified = $USER->id;
        $record->timemodified = $now;

        if (empty($data->id)) {
            $record->usercreated = $USER->id;
            $record->timecreated = $now;
            $id = (int) $DB->insert_record(self::TABLE, $record);
        } else {
            $record->id = (int) $data->id;
            $DB->update_record(self::TABLE, $record);
            $id = $record->id;
        }

        // A page with no patterns of its own belongs wherever its plugin's own
        // documentation belongs, so filing a page against Video Time is enough to
        // have it turn up on Video Time's pages.
        if (empty($pagetypes) && !empty($record->component)) {
            $pagetypes = targeting::pagetypes_for_component($record->component);
        }
        $this->write_targets($id, $pagetypes);

        $this->purge_caches();

        return $id;
    }

    /**
     * Record which pages a page belongs to.
     *
     * @param int $id the page's id
     * @param string[] $pagetypes the pagetype patterns
     * @return void
     */
    protected function write_targets(int $id, array $pagetypes): void {
        global $DB;

        $conditions = ['source' => index_manager::SOURCE_PAGE, 'sourceid' => (string) $id];

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records(index_manager::TARGET_TABLE, $conditions);

        $targets = [];
        foreach ($pagetypes as $pagetype) {
            $targets[] = (object) [
                'source'   => index_manager::SOURCE_PAGE,
                'sourceid' => (string) $id,
                'pagetype' => $pagetype,
            ];
        }
        if (!empty($targets)) {
            $DB->insert_records(index_manager::TARGET_TABLE, $targets);
        }
        $transaction->allow_commit();
    }

    /**
     * Split a textarea or comma-separated value into a clean list.
     *
     * @param string $raw the submitted value
     * @return string[] lower-cased, de-duplicated, in order
     */
    protected static function clean_list(string $raw): array {
        $items = preg_split('/[\r\n,]+/', $raw) ?: [];

        $clean = [];
        foreach ($items as $item) {
            $item = \core_text::strtolower(trim($item));
            if ($item !== '') {
                $clean[$item] = true;
            }
        }

        return array_keys($clean);
    }

    /**
     * Delete a page and any files attached to it.
     *
     * @param int $id
     * @return void
     */
    public function delete_page(int $id): void {
        global $DB;

        $fs = get_file_storage();
        $context = \context_system::instance();
        $fs->delete_area_files($context->id, 'tool_mdocs', self::FILEAREA, $id);

        $DB->delete_records(self::TABLE, ['id' => $id]);
        $DB->delete_records(index_manager::TARGET_TABLE, [
            'source'   => index_manager::SOURCE_PAGE,
            'sourceid' => (string) $id,
        ]);

        $this->purge_caches();
    }

    /**
     * Drop what the site remembers about pages, after one has changed.
     *
     * @return void
     */
    protected function purge_caches(): void {
        \cache_helper::purge_by_definition('tool_mdocs', 'rendered');
        \cache_helper::purge_by_definition('tool_mdocs', 'contextual');
    }

    /**
     * Search pages by title or content.
     *
     * @param string $query user query
     * @param bool $includehidden include draft pages
     * @param int $limit maximum rows
     * @return \stdClass[]
     */
    public function search_pages(string $query, bool $includehidden = false, int $limit = 30): array {
        global $DB;

        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $like = '%' . $DB->sql_like_escape($query) . '%';
        $titlelike = $DB->sql_like('title', ':title', false);
        $contentlike = $DB->sql_like('content', ':content', false);
        $params = ['title' => $like, 'content' => $like];

        $where = "($titlelike OR $contentlike)";
        if (!$includehidden) {
            $where .= " AND visible = 1";
        }

        $sql = "SELECT * FROM {" . self::TABLE . "} WHERE $where ORDER BY title ASC";

        return array_values($DB->get_records_sql($sql, $params, 0, $limit));
    }
}
