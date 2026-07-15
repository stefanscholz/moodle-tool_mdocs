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
 * External function: read a document from the help icon, without leaving the page.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use tool_mdocs\contextual;

/**
 * Renders one document for the help popover.
 *
 * The reader may well not be able to open the Documentation Center: a teacher's
 * capability lives in their course, not at the system. So the document is rendered
 * here and shown in place, and the capability is checked against the context the
 * reader is actually in.
 */
class get_contextual_doc extends external_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'source' => new external_value(PARAM_ALPHA, 'file for a plugin document, page for an authored one'),
            'sourceid' => new external_value(PARAM_ALPHANUM, 'The document id, or the authored page id'),
            'contextid' => new external_value(PARAM_INT, 'The context of the page the reader is on'),
        ]);
    }

    /**
     * Render the document.
     *
     * @param string $source file|page
     * @param string $sourceid the docid or page id
     * @param int $contextid the context of the page the reader is on
     * @return array
     */
    public static function execute(string $source, string $sourceid, int $contextid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'source' => $source,
            'sourceid' => $sourceid,
            'contextid' => $contextid,
        ]);

        // The capability is checked where the reader is: that is the whole point,
        // because a teacher holds it in their course and nowhere else.
        $context = \context::instance_by_id($params['contextid']);
        self::validate_context($context);
        require_capability(contextual::CAPABILITY, $context);

        return contextual::read((string) $params['source'], (string) $params['sourceid']);
    }

    /**
     * Describe the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'title' => new external_value(PARAM_TEXT, 'Document title'),
            'html' => new external_value(PARAM_RAW, 'Rendered, sanitised HTML'),
        ]);
    }
}
