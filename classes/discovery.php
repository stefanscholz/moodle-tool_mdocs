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

    /** @var int Maximum number of bytes read from a file when indexing its content. */
    const CONTENT_READ_LIMIT = 262144;

    /** @var int Maximum number of characters of plain-text content stored per document. */
    const CONTENT_STORE_LIMIT = 65535;

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
        $components = $this->get_components();

        // The canonical directory of every component. A plugin scan must not
        // claim Markdown files that physically live inside one of its
        // subplugins (or any other nested component) — those belong to that
        // component and would otherwise be indexed twice.
        $roots = [];
        foreach ($components as $info) {
            $root = realpath($info['dir']);
            if ($root !== false) {
                $roots[$root] = true;
            }
        }

        $docs = [];
        foreach ($components as $component => $info) {
            foreach ($this->scan_component($component, $info['type'], $info['dir'], $roots) as $doc) {
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
            foreach (\core_component::get_plugin_list($type) as $name => $dir) {
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
     * @param array $componentroots realpath => true for every component root, so nested components are not descended into
     * @return document[]
     */
    public function scan_component(
        string $component,
        string $plugintype,
        string $dir,
        array $componentroots = []
    ): array {
        $realdir = realpath($dir);
        if ($realdir === false || !is_dir($realdir)) {
            return [];
        }

        $prune = self::PRUNE_DIRS;
        $dirit = new \RecursiveDirectoryIterator($realdir, \FilesystemIterator::SKIP_DOTS);
        $filter = new \RecursiveCallbackFilterIterator(
            $dirit,
            function ($current) use ($prune, $componentroots, $realdir) {
                if (!$current->isDir()) {
                    return true;
                }
                if (in_array($current->getFilename(), $prune, true)) {
                    return false;
                }
                // Never descend into another component's directory: those files
                // belong to that (sub)plugin, not to the one being scanned.
                $real = $current->getRealPath();
                return !($real !== false && $real !== $realdir && isset($componentroots[$real]));
            }
        );
        $iterator = new \RecursiveIteratorIterator($filter, \RecursiveIteratorIterator::LEAVES_ONLY);

        $documents = [];
        foreach ($iterator as $fileinfo) {
            $document = $this->file_to_document($fileinfo, $realdir, $component, $plugintype);
            if ($document !== null) {
                $documents[] = $document;
            }
        }

        return $documents;
    }

    /**
     * Turn a single filesystem entry into a document, or null if it is excluded.
     *
     * @param \SplFileInfo $fileinfo the iterated filesystem entry
     * @param string $realdir canonical component directory
     * @param string $component frankenstyle component
     * @param string $plugintype plugin type
     * @return document|null the document, or null when the file is not an included Markdown file
     */
    protected function file_to_document(
        \SplFileInfo $fileinfo,
        string $realdir,
        string $component,
        string $plugintype
    ): ?document {
        if (!$fileinfo->isFile()) {
            return null;
        }
        $filename = $fileinfo->getFilename();
        if (\core_text::strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'md') {
            return null;
        }

        $fullpath = $fileinfo->getPathname();
        $relpath = ltrim(str_replace('\\', '/', substr($fullpath, strlen($realdir))), '/');
        if ($relpath === '' || !$this->is_included($relpath)) {
            return null;
        }

        $filesize = (int) $fileinfo->getSize();
        if ($filesize > $this->maxfilesize) {
            return null;
        }

        $raw = $this->read_file($fullpath, self::CONTENT_READ_LIMIT);

        return new document(
            $component,
            $plugintype,
            $relpath,
            $this->extract_title($raw, $filename),
            (int) $fileinfo->getMTime(),
            $filesize,
            self::to_plain_text($raw)
        );
    }

    /**
     * Read up to a bounded number of bytes from a file.
     *
     * @param string $fullpath absolute path to the file
     * @param int $maxbytes maximum number of bytes to read
     * @return string the bytes read, or an empty string on failure
     */
    protected function read_file(string $fullpath, int $maxbytes): string {
        $handle = @fopen($fullpath, 'rb');
        if ($handle === false) {
            return '';
        }
        $data = (string) fread($handle, $maxbytes);
        fclose($handle);
        return $data;
    }

    /**
     * Reduce Markdown source to searchable plain text.
     *
     * @param string $raw raw Markdown source
     * @return string normalised plain text, truncated to the storage limit
     */
    public static function to_plain_text(string $raw): string {
        // Keep the visible text of links and images, drop their URLs and syntax.
        $text = preg_replace('/!?\[([^\]]*)\]\([^)]*\)/', '$1', $raw);
        // Strip common Markdown punctuation, keeping words (including code) intact.
        // \x60 is the backtick used for code spans and fences. Underscores are
        // kept because they are part of identifiers such as tool_mdocs_index.
        $text = preg_replace('/[\x60*~#>|]+/', ' ', (string) $text);
        // Collapse all whitespace runs to single spaces.
        $text = preg_replace('/\s+/', ' ', (string) $text);
        return \core_text::substr(trim((string) $text), 0, self::CONTENT_STORE_LIMIT);
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
     * @param string $raw raw Markdown source
     * @param string $filename basename
     * @return string
     */
    protected function extract_title(string $raw, string $filename): string {
        // Titles live near the top, so only inspect the head of the file.
        $head = \core_text::substr($raw, 0, 8192);
        foreach (preg_split('/\r\n|\r|\n/', $head) as $line) {
            if (preg_match('/^#\s+(.+?)\s*#*\s*$/', $line, $m)) {
                $title = trim($m[1]);
                if ($title !== '') {
                    return \core_text::substr($title, 0, 255);
                }
            }
        }

        // Fall back to a humanised filename.
        $base = preg_replace('/\.md$/i', '', $filename);
        $base = trim(str_replace(['-', '_'], ' ', $base));
        return $base === '' ? $filename : \core_text::strtotitle($base);
    }
}
