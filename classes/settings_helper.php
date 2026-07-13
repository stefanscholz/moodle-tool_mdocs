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
 * Helper for reading the plugin's inclusion-rule settings.
 */
class settings_helper {
    /** @var string Include every Markdown file. */
    const MODE_ALL = 'all';

    /** @var string Include only files inside configured folders. */
    const MODE_FOLDERS = 'folders';

    /** @var string Include only files with configured basenames. */
    const MODE_FILENAMES = 'filenames';

    /**
     * Get the configured inclusion mode.
     *
     * @return string one of the MODE_* constants
     */
    public static function get_mode(): string {
        $mode = get_config('tool_mdocs', 'inclusionmode');
        $valid = [self::MODE_ALL, self::MODE_FOLDERS, self::MODE_FILENAMES];
        return in_array($mode, $valid, true) ? $mode : self::MODE_FOLDERS;
    }

    /**
     * Get the configured folder names (lower-cased), used in MODE_FOLDERS.
     *
     * @return string[] folder name segments
     */
    public static function get_folders(): array {
        return self::parse_lines((string) get_config('tool_mdocs', 'includefolders'), ['docs']);
    }

    /**
     * Get the configured file names (lower-cased), used in MODE_FILENAMES.
     *
     * @return string[] basenames
     */
    public static function get_filenames(): array {
        return self::parse_lines((string) get_config('tool_mdocs', 'includefilenames'), ['readme.md', 'index.md']);
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
            self::get_mode(),
            self::get_folders(),
            self::get_filenames(),
            self::scan_core(),
            self::get_max_filesize(),
        ]));
    }

    /**
     * Split a textarea setting into a clean, lower-cased, de-duplicated list.
     *
     * @param string $raw the raw textarea value
     * @param string[] $default returned when the setting is empty
     * @return string[]
     */
    protected static function parse_lines(string $raw, array $default): array {
        $lines = preg_split('/[\r\n]+/', $raw) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = trim(\core_text::strtolower($line));
            if ($line !== '') {
                $out[$line] = true;
            }
        }
        $out = array_keys($out);
        return empty($out) ? $default : $out;
    }
}
