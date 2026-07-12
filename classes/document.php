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
 * Value object describing a single indexed Markdown document.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs;

/**
 * Immutable description of one Markdown file that has been discovered.
 */
class document {

    /** @var string Stable identifier: sha1(component . '/' . relpath). */
    public string $docid;

    /** @var string Frankenstyle component, e.g. mod_forum or core_course. */
    public string $component;

    /** @var string Plugin type, e.g. mod, tool, core. */
    public string $plugintype;

    /** @var string Path of the file relative to the component directory. */
    public string $relpath;

    /** @var string File basename, e.g. readme.md. */
    public string $basename;

    /** @var string Human-readable title (first H1, or humanised filename). */
    public string $title;

    /** @var int File modification time. */
    public int $timemodified;

    /** @var int File size in bytes. */
    public int $filesize;

    /**
     * Build a document from its component and relative path.
     *
     * @param string $component frankenstyle component
     * @param string $plugintype plugin type
     * @param string $relpath path relative to the component directory
     * @param string $title document title
     * @param int $timemodified file mtime
     * @param int $filesize file size in bytes
     */
    public function __construct(string $component, string $plugintype, string $relpath,
            string $title, int $timemodified, int $filesize) {
        $this->component = $component;
        $this->plugintype = $plugintype;
        $this->relpath = $relpath;
        $this->basename = basename($relpath);
        $this->title = $title;
        $this->timemodified = $timemodified;
        $this->filesize = $filesize;
        $this->docid = self::make_docid($component, $relpath);
    }

    /**
     * Compute the stable document identifier.
     *
     * @param string $component frankenstyle component
     * @param string $relpath path relative to the component directory
     * @return string
     */
    public static function make_docid(string $component, string $relpath): string {
        return sha1($component . '/' . $relpath);
    }

    /**
     * Convert to a database record for the tool_mdocs_index table.
     *
     * @return \stdClass
     */
    public function to_record(): \stdClass {
        $record = new \stdClass();
        $record->docid = $this->docid;
        $record->component = $this->component;
        $record->plugintype = $this->plugintype;
        $record->relpath = $this->relpath;
        $record->basename = $this->basename;
        $record->title = $this->title;
        $record->pagetypes = null;
        $record->sortorder = 0;
        $record->filesize = $this->filesize;
        $record->timemodified = $this->timemodified;
        return $record;
    }
}
