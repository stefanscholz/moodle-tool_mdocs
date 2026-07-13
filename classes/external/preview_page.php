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
 * External function: render Markdown for the page editor preview.
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
use tool_mdocs\markdown;

/**
 * Renders the Markdown currently in the editor, so the author can see it before saving.
 */
class preview_page extends external_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'content' => new external_value(PARAM_RAW, 'Raw Markdown to render'),
        ]);
    }

    /**
     * Render the Markdown.
     *
     * @param string $content raw Markdown
     * @return array
     */
    public static function execute(string $content): array {
        $params = self::validate_parameters(self::execute_parameters(), ['content' => $content]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('tool/mdocs:managepages', $context);

        $rendered = markdown::preview($params['content'], $context);

        return ['html' => $rendered['html']];
    }

    /**
     * Describe the return structure.
     *
     * The HTML has already been through Moodle's output sanitiser, which is what
     * makes it safe to inject; it is returned raw so the preview keeps its markup.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'Rendered, sanitised HTML'),
        ]);
    }
}
