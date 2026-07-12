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
 * Discovers Markdown documentation shipped inside installed components.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs;

/**
 * Walks the component tree and collects the Markdown files that match the
 * admin inclusion rules.
 */
class discovery {

    /** @var string[] Directory names never descended into. */
    const PRUNE_DIRS = ['.git', 'node_modules', 'vendor', 'build', 'fixtures', 'yui', 'phpunit'];

    /** @var string Configured inclusion mode. */
    protected string $mode;

    /** @var string[] Configured folder segments (lower-case). */
    protected array $folders;

    /** @var string[] Configured basenames (lower-case). */
    protected array $filenames;

    /** @var int Maximum indexable file size in bytes. */
    protected int $maxfilesize;

    /**
     * Load the inclusion rules from plugin configuration.
     */
    public function __construct() {
        $this->mode = settings_helper::get_mode();
        $this->folders = settings_helper::get_folders();
        $this->filenames = settings_helper::get_filenames();
        $this->maxfilesize = settings_helper::get_max_filesize();
    }

    /**
     * Discover every included document across all scanned components.
     *
     * @return document[] keyed numerically, de-duplicated by docid
     */
    public function discover(): array {
        $docs = [];
        foreach ($this->get_components() as $component => $info) {
            foreach ($this->scan_component($component, $info['type'], $info['dir']) as $doc) {
                $docs[$doc->docid] = $doc;
            }
        }
        return array_values($docs);
    }

    /**
     * Build the map of components to scan.
     *
     * @return array<string, array{type: string, dir: string}>
     */
    public function get_components(): array {
        $components = [];

        foreach (array_keys(\core_component::get_plugin_types()) as $type) {
            foreach (\core_component::get_plugins_of_type($type) as $name => $dir) {
                if (empty($dir) || !is_dir($dir)) {
                    continue;
                }
                $components[$type . '_' . $name] = ['type' => $type, 'dir' => $dir];
            }
        }

        if (settings_helper::scan_core()) {
            foreach (\core_component::get_core_subsystems() as $name => $dir) {
                if (empty($dir) || !is_dir($dir)) {
                    continue;
                }
                $components['core_' . $name] = ['type' => 'core', 'dir' => $dir];
            }
        }

        return $components;
    }

    /**
     * Scan a single component directory for included Markdown files.
     *
     * @param string $component frankenstyle component
     * @param string $plugintype plugin type
     * @param string $dir absolute component directory
     * @return document[]
     */
    public function scan_component(string $component, string $plugintype, string $dir): array {
        $realdir = realpath($dir);
        if ($realdir === false || !is_dir($realdir)) {
            return [];
        }

        $prune = self::PRUNE_DIRS;
        $dirit = new \RecursiveDirectoryIterator($realdir, \FilesystemIterator::SKIP_DOTS);
        $filter = new \RecursiveCallbackFilterIterator($dirit, function ($current) use ($prune) {
            if ($current->isDir()) {
                return !in_array($current->getFilename(), $prune, true);
            }
            return true;
        });
        $iterator = new \RecursiveIteratorIterator($filter, \RecursiveIteratorIterator::LEAVES_ONLY);

        $documents = [];
        foreach ($iterator as $fileinfo) {
            if (!$fileinfo->isFile()) {
                continue;
            }
            $filename = $fileinfo->getFilename();
            if (\core_text::strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'md') {
                continue;
            }

            $fullpath = $fileinfo->getPathname();
            $relpath = ltrim(str_replace('\\', '/', substr($fullpath, strlen($realdir))), '/');
            if ($relpath === '') {
                continue;
            }

            if (!$this->is_included($relpath)) {
                continue;
            }

            $filesize = (int) $fileinfo->getSize();
            if ($filesize > $this->maxfilesize) {
                continue;
            }

            $title = $this->extract_title($fullpath, $filename);
            $documents[] = new document(
                $component,
                $plugintype,
                $relpath,
                $title,
                (int) $fileinfo->getMTime(),
                $filesize
            );
        }

        return $documents;
    }

    /**
     * Decide whether a relative path is included by the current rules.
     *
     * @param string $relpath forward-slash path relative to the component dir
     * @return bool
     */
    public function is_included(string $relpath): bool {
        $relpath = \core_text::strtolower($relpath);
        $basename = basename($relpath);

        switch ($this->mode) {
            case settings_helper::MODE_ALL:
                return true;

            case settings_helper::MODE_FILENAMES:
                return in_array($basename, $this->filenames, true);

            case settings_helper::MODE_FOLDERS:
            default:
                $segments = explode('/', $relpath);
                array_pop($segments); // Drop the file basename.
                foreach ($segments as $segment) {
                    if (in_array($segment, $this->folders, true)) {
                        return true;
                    }
                }
                return false;
        }
    }

    /**
     * Derive a title: the first level-1 heading, else a humanised filename.
     *
     * @param string $fullpath absolute path to the file
     * @param string $filename basename
     * @return string
     */
    protected function extract_title(string $fullpath, string $filename): string {
        // Read only the head of the file: titles live near the top.
        $handle = @fopen($fullpath, 'rb');
        if ($handle !== false) {
            $head = (string) fread($handle, 8192);
            fclose($handle);
            foreach (preg_split('/\r\n|\r|\n/', $head) as $line) {
                if (preg_match('/^#\s+(.+?)\s*#*\s*$/', $line, $m)) {
                    $title = trim($m[1]);
                    if ($title !== '') {
                        return \core_text::substr($title, 0, 255);
                    }
                }
            }
        }

        // Fall back to a humanised filename.
        $base = preg_replace('/\.md$/i', '', $filename);
        $base = trim(str_replace(['-', '_'], ' ', $base));
        return $base === '' ? $filename : \core_text::strtotitle($base);
    }
}
