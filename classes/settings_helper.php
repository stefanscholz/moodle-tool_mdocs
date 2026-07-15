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
 * Reads and normalises the admin inclusion-rule configuration.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs;

/**
 * Helper for reading the rules that decide which files are indexed.
 *
 * The rules add up rather than compete. A folder list and a file-name list are
 * both included, so "everything in docs, and also readme.md and changes.md" is
 * one setting each and not a choice between them. An exclusion list then takes
 * things back out again, and it always wins — there was previously no way at all
 * to say "never this one", which meant a single unwanted file forced you to
 * narrow the rules for everything else.
 */
class settings_helper {
    /** @var string[] Folders indexed when the setting has never been touched. */
    const DEFAULT_FOLDERS = ['docs'];

    /** @var string[] File names indexed when the setting has never been touched. */
    const DEFAULT_FILENAMES = ['readme.md', 'index.md'];

    /**
     * Whether every Markdown file is indexed, whatever the lists say.
     *
     * Exclusions still apply: "everything except" is the whole point of having
     * them.
     *
     * @return bool
     */
    public static function include_all(): bool {
        return (bool) get_config('tool_mdocs', 'includeall');
    }

    /**
     * The folders whose contents are indexed.
     *
     * @return string[] folder name segments, lower-cased
     */
    public static function get_folders(): array {
        return self::get_list('includefolders', self::DEFAULT_FOLDERS);
    }

    /**
     * The file names that are indexed wherever they are found.
     *
     * @return string[] basenames, lower-cased
     */
    public static function get_filenames(): array {
        return self::get_list('includefilenames', self::DEFAULT_FILENAMES);
    }

    /**
     * The names that are never indexed, whatever else says otherwise.
     *
     * A line is matched against the file's own name and against every folder in
     * its path, so "install.md" and "examples" both do what they look like they do.
     *
     * @return string[] names, lower-cased
     */
    public static function get_excluded(): array {
        return self::get_list('excludenames', []);
    }

    /**
     * Whether core subsystems should be scanned in addition to plugins.
     *
     * @return bool
     */
    public static function scan_core(): bool {
        return (bool) get_config('tool_mdocs', 'scancore');
    }

    /**
     * Maximum indexable file size in bytes.
     *
     * @return int
     */
    public static function get_max_filesize(): int {
        $size = (int) get_config('tool_mdocs', 'maxfilesize');
        return $size > 0 ? $size : 1048576;
    }

    /**
     * A fingerprint of every rule that decides which files get indexed.
     *
     * The catalogue is only valid for the rules it was built with, so this lets
     * {@see \tool_mdocs\index_manager::is_stale()} notice a settings change and
     * rebuild, instead of quietly serving the previous set of documents.
     *
     * @return string
     */
    public static function signature(): string {
        return sha1(json_encode([
            self::include_all(),
            self::get_folders(),
            self::get_filenames(),
            self::get_excluded(),
            self::scan_core(),
            self::get_max_filesize(),
        ]));
    }

    /**
     * Read one of the textarea settings.
     *
     * An empty list and an unset setting are different things: a setting that has
     * been deliberately emptied means "none of these", and must not quietly fall
     * back to the default it was cleared of.
     *
     * @param string $name the setting name
     * @param string[] $default used only when the setting has never been saved
     * @return string[] lower-cased, de-duplicated, in the order they were written
     */
    protected static function get_list(string $name, array $default): array {
        $raw = get_config('tool_mdocs', $name);
        if ($raw === false) {
            return $default;
        }

        return self::parse_lines((string) $raw);
    }

    /**
     * Split a textarea value into a clean list.
     *
     * @param string $raw the raw textarea value
     * @return string[]
     */
    protected static function parse_lines(string $raw): array {
        $lines = preg_split('/[\r\n,]+/', $raw) ?: [];

        $out = [];
        foreach ($lines as $line) {
            $line = trim(\core_text::strtolower($line));
            if ($line !== '') {
                $out[$line] = true;
            }
        }

        return array_keys($out);
    }
}
