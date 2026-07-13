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
 * External function: search the documentation index.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use tool_mdocs\discovery;
use tool_mdocs\index_manager;
use tool_mdocs\page_manager;
use tool_mdocs\output\nav_tree;

/**
 * Returns documents matching a search query for the viewer's search box.
 */
class search_docs extends external_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(PARAM_RAW, 'Search text'),
        ]);
    }

    /**
     * Run the search.
     *
     * @param string $query
     * @return array
     */
    public static function execute(string $query): array {
        $params = self::validate_parameters(self::execute_parameters(), ['query' => $query]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('tool/mdocs:view', $context);

        $canmanagepages = has_capability('tool/mdocs:managepages', $context);
        $results = [];

        // File-based documents.
        $manager = new index_manager();
        foreach ($manager->search($params['query'], 30) as $record) {
            $url = new \moodle_url('/admin/tool/mdocs/view.php', [
                'component' => $record->component,
                'doc'       => $record->docid,
            ]);
            $results[] = [
                'docid'         => $record->docid,
                'title'         => $record->title,
                'component'     => $record->component,
                'componentname' => nav_tree::component_name($record->component),
                'url'           => $url->out(false),
                'excerpt'       => $record->excerpt ?? '',
                'source'        => 'file',
            ];
        }

        // User-authored pages (drafts included only for managers).
        $pagemanager = new page_manager();
        foreach ($pagemanager->search_pages($params['query'], $canmanagepages, 30) as $record) {
            $url = new \moodle_url('/admin/tool/mdocs/view.php', ['pageid' => $record->id]);
            $componentname = empty($record->component)
                ? get_string('sitedocumentation', 'tool_mdocs')
                : nav_tree::component_name($record->component);
            // Use the same normalisation and match-centred excerpt as file documents,
            // so a hit deep in a page shows the matching text rather than its opening line.
            $plain = discovery::to_plain_text((string) $record->content);
            $results[] = [
                'title'         => $record->title,
                'componentname' => $componentname,
                'url'           => $url->out(false),
                'excerpt'       => index_manager::make_excerpt($plain, $params['query']),
                'source'        => 'page',
            ];
        }

        return ['results' => $results];
    }

    /**
     * Describe the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'results' => new external_multiple_structure(
                new external_single_structure([
                    'docid'         => new external_value(PARAM_ALPHANUM, 'Document id', VALUE_OPTIONAL),
                    'title'         => new external_value(PARAM_TEXT, 'Document title'),
                    'component'     => new external_value(PARAM_COMPONENT, 'Component', VALUE_OPTIONAL),
                    'componentname' => new external_value(PARAM_TEXT, 'Human-readable component name'),
                    'url'           => new external_value(PARAM_URL, 'Viewer URL'),
                    'excerpt'       => new external_value(PARAM_TEXT, 'Plain-text content snippet', VALUE_OPTIONAL),
                    'source'        => new external_value(PARAM_ALPHA, 'Result source: file or page'),
                ])
            ),
        ]);
    }
}
