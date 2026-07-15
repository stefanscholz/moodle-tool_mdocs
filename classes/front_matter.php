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
 * Reads the optional metadata block at the top of a Markdown document.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs;

/**
 * Splits a document into its front matter and its body.
 *
 * A document may open with a YAML-style block fenced by "---" lines, saying who
 * it is for and which pages it belongs to:
 *
 *     ---
 *     audience: teacher, manager
 *     pagetypes:
 *       - mod-videotime-view
 *       - mod-videotime-*
 *     exclude: false
 *     ---
 *
 *     # Watching a video
 *
 * Only the small subset above is understood — scalars and simple lists. This is
 * deliberate: a full YAML parser is a dependency and an attack surface, and the
 * block only ever has to carry a handful of hints. Anything unrecognised is
 * ignored rather than treated as an error, so a document is never lost to a typo
 * in its metadata.
 *
 * The body must be split off wherever the document is used. Left in, the block
 * would be indexed as searchable text and rendered on the page as "--- audience:
 * teacher ---".
 */
class front_matter {
    /** @var string The line that opens and closes the block. */
    const FENCE = '---';

    /**
     * Split a raw document into its metadata and its body.
     *
     * @param string $raw the whole document as read from disk
     * @return array{0: array<string, string|string[]>, 1: string} the metadata, and the body without it
     */
    public static function split(string $raw): array {
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        if ($lines === false || trim((string) ($lines[0] ?? '')) !== self::FENCE) {
            return [[], $raw];
        }

        // Find the closing fence. Without one there is no front matter, only a
        // document that happens to start with a horizontal rule.
        $close = null;
        for ($i = 1; $i < count($lines); $i++) {
            if (trim($lines[$i]) === self::FENCE) {
                $close = $i;
                break;
            }
        }
        if ($close === null) {
            return [[], $raw];
        }

        $meta = self::parse(array_slice($lines, 1, $close - 1));
        $body = implode("\n", array_slice($lines, $close + 1));

        return [$meta, ltrim($body, "\n")];
    }

    /**
     * Parse the lines between the fences.
     *
     * @param string[] $lines the lines of the block
     * @return array<string, string|string[]> keys lower-cased; list values as arrays
     */
    protected static function parse(array $lines): array {
        $meta = [];
        $listkey = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            // A "- item" line continues the list opened by the key above it.
            if (str_starts_with($trimmed, '- ')) {
                if ($listkey !== null) {
                    $meta[$listkey][] = self::clean(substr($trimmed, 2));
                }
                continue;
            }

            $colon = strpos($trimmed, ':');
            if ($colon === false) {
                continue;
            }

            $key = \core_text::strtolower(trim(substr($trimmed, 0, $colon)));
            $value = self::clean(substr($trimmed, $colon + 1));
            if ($key === '') {
                continue;
            }

            if ($value === '') {
                // A key with nothing after it opens a list on the lines below.
                $meta[$key] = [];
                $listkey = $key;
                continue;
            }

            $meta[$key] = $value;
            $listkey = null;
        }

        return $meta;
    }

    /**
     * Trim a scalar and drop any quotes around it.
     *
     * @param string $value
     * @return string
     */
    protected static function clean(string $value): string {
        $value = trim($value);
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }
        return trim($value);
    }

    /**
     * Read a value that may be written either as a list or as a comma-separated line.
     *
     * "audience: teacher, manager" and a "- teacher" list mean the same thing, and
     * document authors will write both.
     *
     * @param array $meta the parsed metadata
     * @param string $key the key to read
     * @return string[] the values, lower-cased and de-duplicated, or [] when absent
     */
    public static function values(array $meta, string $key): array {
        if (!isset($meta[$key])) {
            return [];
        }

        $raw = $meta[$key];
        $items = is_array($raw) ? $raw : explode(',', (string) $raw);

        $values = [];
        foreach ($items as $item) {
            $item = \core_text::strtolower(trim((string) $item));
            if ($item !== '') {
                $values[$item] = true;
            }
        }

        return array_keys($values);
    }

    /**
     * Whether the document asked to be kept out of contextual help.
     *
     * @param array $meta the parsed metadata
     * @return bool
     */
    public static function is_excluded(array $meta): bool {
        $value = $meta['exclude'] ?? '';
        if (is_array($value)) {
            return false;
        }
        return in_array(\core_text::strtolower(trim($value)), ['1', 'true', 'yes'], true);
    }
}
