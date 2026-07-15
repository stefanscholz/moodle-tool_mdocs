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
 * Finds the documents that belong to the page being looked at.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs;

/**
 * Answers "what should this person read, here?".
 *
 * This runs on every page render, so it is deliberately mean: one cache lookup
 * keyed by pagetype, and on a miss one indexed query that returns titles and
 * nothing else. Document bodies are never touched here — they are fetched only
 * when somebody actually opens the help.
 */
class contextual {
    /** @var string The capability that decides whether contextual help appears at all. */
    const CAPABILITY = 'tool/mdocs:viewcontextual';

    /**
     * The documents that belong to a page, ordered and split by whether they are
     * for this reader.
     *
     * @param string $pagetype the page's type, e.g. mod-videotime-view
     * @param \context $context the page's context
     * @return array{foryou: array[], others: array[]} matching documents
     */
    public static function for_page(string $pagetype, \context $context): array {
        $candidates = self::candidates($pagetype);
        if (empty($candidates)) {
            return ['foryou' => [], 'others' => []];
        }

        $audiences = targeting::audiences_for_user($context);

        $foryou = [];
        $others = [];
        foreach ($candidates as $candidate) {
            // A document with no audience of its own is for whoever can see it.
            $wanted = $candidate['audiences'] ?: [targeting::AUDIENCE_ANY];

            if (array_intersect($wanted, $audiences)) {
                $foryou[] = $candidate;
            } else {
                // Not thrown away: a teacher wanting to know what a student is told
                // is a real question, and the student guide is the answer.
                $others[] = $candidate;
            }
        }

        return ['foryou' => $foryou, 'others' => $others];
    }

    /**
     * Whether there is anything at all to show, without paying to build the list.
     *
     * @param string $pagetype the page's type
     * @param \context $context the page's context
     * @return bool
     */
    public static function has_any(string $pagetype, \context $context): bool {
        $matches = self::for_page($pagetype, $context);
        return !empty($matches['foryou']) || !empty($matches['others']);
    }

    /**
     * Every document that targets this page, most specific pattern first.
     *
     * Cached by pagetype rather than by user: what a page has is the same for
     * everybody, and only the audience filter differs, which is cheap to redo.
     *
     * @param string $pagetype the page's type
     * @return array[] the candidates, each with source, sourceid, title, component, audiences
     */
    protected static function candidates(string $pagetype): array {
        $cache = \cache::make('tool_mdocs', 'contextual');
        $cached = $cache->get($pagetype);
        if ($cached !== false) {
            return $cached;
        }

        $candidates = self::query($pagetype);
        $cache->set($pagetype, $candidates);

        return $candidates;
    }

    /**
     * Look the page's documents up in the database.
     *
     * @param string $pagetype the page's type
     * @return array[] the candidates
     */
    protected static function query(string $pagetype): array {
        global $DB, $CFG;

        require_once($CFG->libdir . '/blocklib.php');

        // Core already knows how to enumerate the patterns a page answers to, and
        // hands them back most-specific-first — which is exactly the order we want
        // to show them in. mod-videotime-view, then mod-videotime-*, then mod-*, *.
        $patterns = matching_page_type_patterns($pagetype);
        if (empty($patterns)) {
            return [];
        }

        $order = array_flip($patterns);
        [$insql, $params] = $DB->get_in_or_equal($patterns, SQL_PARAMS_NAMED, 'pt');

        // Documents inside plugins.
        $sql = "SELECT t.pagetype, i.docid, i.title, i.component, i.audience
                  FROM {" . index_manager::TARGET_TABLE . "} t
                  JOIN {" . index_manager::TABLE . "} i ON i.docid = t.sourceid
                 WHERE t.source = :source AND t.pagetype $insql";
        $params['source'] = index_manager::SOURCE_FILE;

        $candidates = [];
        foreach ($DB->get_recordset_sql($sql, $params) as $row) {
            $candidates[] = [
                'source'    => index_manager::SOURCE_FILE,
                'sourceid'  => $row->docid,
                'title'     => $row->title,
                'component' => $row->component,
                'audiences' => self::split($row->audience),
                'rank'      => $order[$row->pagetype] ?? PHP_INT_MAX,
            ];
        }

        // Pages authored on this site. Drafts stay out of contextual help: an
        // unfinished page should not be offered as the answer to anything.
        $sql = "SELECT t.pagetype, p.id, p.title, p.component, p.audience
                  FROM {" . index_manager::TARGET_TABLE . "} t
                  JOIN {" . page_manager::TABLE . "} p ON " . $DB->sql_cast_to_char('p.id') . " = t.sourceid
                 WHERE t.source = :source AND p.visible = 1 AND t.pagetype $insql";
        $params['source'] = index_manager::SOURCE_PAGE;

        foreach ($DB->get_recordset_sql($sql, $params) as $row) {
            $candidates[] = [
                'source'    => index_manager::SOURCE_PAGE,
                'sourceid'  => (string) $row->id,
                'title'     => $row->title,
                'component' => (string) ($row->component ?? ''),
                'audiences' => self::split($row->audience),
                'rank'      => $order[$row->pagetype] ?? PHP_INT_MAX,
            ];
        }

        // Most specific pattern first; a stable title order within a tie.
        usort($candidates, function ($a, $b) {
            return [$a['rank'], $a['title']] <=> [$b['rank'], $b['title']];
        });

        foreach ($candidates as &$candidate) {
            unset($candidate['rank']);
        }
        unset($candidate);

        return $candidates;
    }

    /**
     * Split a stored comma-separated list.
     *
     * @param string|null $value the stored value
     * @return string[]
     */
    protected static function split(?string $value): array {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $items = array_map('trim', explode(',', $value));

        return array_values(array_filter($items, fn($item) => $item !== ''));
    }
}
