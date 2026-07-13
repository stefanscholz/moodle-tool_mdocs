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
 * Renders a Markdown document to sanitised HTML with anchors, TOC and rewritten links.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mdocs;

/**
 * Wraps Moodle's built-in Markdown renderer and post-processes the output.
 */
class markdown {
    /**
     * Render a document, using the MUC cache when possible.
     *
     * @param \stdClass $doc a tool_mdocs_index record (docid, component, relpath, timemodified)
     * @param \context $context context used for output cleaning
     * @return array{html: string, toc: array<int, array{level: int, text: string, id: string}>}
     */
    public static function render(\stdClass $doc, \context $context): array {
        $cache = \cache::make('tool_mdocs', 'rendered');
        $key = $doc->docid . '_' . $doc->timemodified;
        $cached = $cache->get($key);
        if ($cached !== false) {
            return $cached;
        }

        $raw = self::read_source($doc->component, $doc->relpath);
        $result = self::process($raw, $doc->component, $doc->relpath, $context);

        $cache->set($key, $result);
        return $result;
    }

    /**
     * Convert raw Markdown to sanitised HTML with anchors, TOC and rewritten links.
     *
     * This is the disk-independent core of {@see self::render()} and is exposed for testing.
     *
     * @param string $raw raw Markdown source
     * @param string $component frankenstyle component the document belongs to
     * @param string $relpath component-relative path of the document
     * @param \context $context context used for output cleaning
     * @return array{html: string, toc: array}
     */
    public static function process(string $raw, string $component, string $relpath, \context $context): array {
        $html = format_text($raw, FORMAT_MARKDOWN, ['noclean' => false, 'context' => $context]);
        return self::postprocess($html, $component, $relpath);
    }

    /**
     * Read a Markdown source file, validating it stays inside the component dir.
     *
     * @param string $component frankenstyle component
     * @param string $relpath component-relative path
     * @return string file contents
     * @throws \moodle_exception when the file cannot be safely resolved
     */
    protected static function read_source(string $component, string $relpath): string {
        $base = \core_component::get_component_directory($component);
        if ($base === null) {
            throw new \moodle_exception('invalidcomponent', 'tool_mdocs');
        }
        $base = realpath($base);
        $full = realpath($base . '/' . $relpath);
        if ($base === false || $full === false || strpos($full, $base . DIRECTORY_SEPARATOR) !== 0) {
            throw new \moodle_exception('documentnotfound', 'tool_mdocs');
        }
        return (string) file_get_contents($full);
    }

    /**
     * Add heading anchors, extract a table of contents and rewrite relative links.
     *
     * @param string $html sanitised HTML from format_text
     * @param string $component frankenstyle component the document belongs to
     * @param string $relpath component-relative path of the document
     * @return array{html: string, toc: array}
     */
    protected static function postprocess(string $html, string $component, string $relpath): array {
        if (trim($html) === '') {
            return ['html' => '', 'toc' => []];
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $preverrors = libxml_use_internal_errors(true);
        $wrapped = '<?xml encoding="utf-8" ?><div id="tool-mdocs-root">' . $html . '</div>';
        $dom->loadHTML($wrapped, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($preverrors);

        $xpath = new \DOMXPath($dom);
        $root = $xpath->query('//div[@id="tool-mdocs-root"]')->item(0);
        if (!$root instanceof \DOMElement) {
            return ['html' => $html, 'toc' => []];
        }

        $toc = self::add_anchors_and_toc($xpath, $root);
        self::rewrite_links($xpath, $root, $component, $relpath);
        self::rewrite_images($xpath, $root, $component, $relpath);

        return ['html' => self::inner_html($root), 'toc' => $toc];
    }

    /**
     * Assign ids to headings and build the table of contents.
     *
     * @param \DOMXPath $xpath
     * @param \DOMElement $root
     * @return array<int, array{level: int, text: string, id: string}>
     */
    protected static function add_anchors_and_toc(\DOMXPath $xpath, \DOMElement $root): array {
        $toc = [];
        $used = [];
        $headings = $xpath->query('.//h1 | .//h2 | .//h3 | .//h4', $root);
        foreach ($headings as $heading) {
            if (!$heading instanceof \DOMElement) {
                continue;
            }
            $text = trim($heading->textContent);
            if ($text === '') {
                continue;
            }
            $id = $heading->getAttribute('id');
            if ($id === '') {
                $id = self::unique_slug($text, $used);
                $heading->setAttribute('id', $id);
            } else {
                $used[$id] = true;
            }
            $toc[] = [
                'level' => (int) substr($heading->nodeName, 1),
                'text'  => $text,
                'id'    => $id,
            ];
        }
        return $toc;
    }

    /**
     * Rewrite relative anchors to the viewer or the secure file server.
     *
     * @param \DOMXPath $xpath
     * @param \DOMElement $root
     * @param string $component
     * @param string $relpath
     */
    protected static function rewrite_links(\DOMXPath $xpath, \DOMElement $root, string $component, string $relpath): void {
        $basedir = self::dirname_rel($relpath);
        $discovery = new discovery();
        foreach ($xpath->query('.//a[@href]', $root) as $link) {
            if (!$link instanceof \DOMElement) {
                continue;
            }
            $href = $link->getAttribute('href');
            if ($href === '' || preg_match('~^(?:[a-z][a-z0-9+.-]*:|//|#)~i', $href)) {
                // Absolute URL, scheme, protocol-relative or in-page anchor: leave alone.
                continue;
            }

            [$path, $fragment] = self::split_fragment($href);
            $target = self::resolve_relative($basedir, rawurldecode($path));
            if ($target === null) {
                continue;
            }

            if (preg_match('/\.md$/i', $target)) {
                if (!$discovery->is_included($target)) {
                    continue;
                }
                $params = ['component' => $component, 'doc' => document::make_docid($component, $target)];
                $url = new \moodle_url('/admin/tool/mdocs/view.php', $params);
                $link->setAttribute('href', $url->out(false) . ($fragment !== '' ? '#' . $fragment : ''));
            } else {
                $url = new \moodle_url('/admin/tool/mdocs/file.php', ['component' => $component, 'path' => $target]);
                $link->setAttribute('href', $url->out(false));
            }
        }
    }

    /**
     * Rewrite relative image sources to the secure file server.
     *
     * @param \DOMXPath $xpath
     * @param \DOMElement $root
     * @param string $component
     * @param string $relpath
     */
    protected static function rewrite_images(\DOMXPath $xpath, \DOMElement $root, string $component, string $relpath): void {
        $basedir = self::dirname_rel($relpath);
        foreach ($xpath->query('.//img[@src]', $root) as $img) {
            if (!$img instanceof \DOMElement) {
                continue;
            }
            $src = $img->getAttribute('src');
            if ($src === '' || preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $src)) {
                continue;
            }
            [$path] = self::split_fragment($src);
            $target = self::resolve_relative($basedir, rawurldecode($path));
            if ($target === null) {
                continue;
            }
            $url = new \moodle_url('/admin/tool/mdocs/file.php', ['component' => $component, 'path' => $target]);
            $img->setAttribute('src', $url->out(false));
            $img->setAttribute('loading', 'lazy');
        }
    }

    /**
     * Component-relative directory of a document path ('' at the root).
     *
     * @param string $relpath
     * @return string
     */
    protected static function dirname_rel(string $relpath): string {
        $dir = str_replace('\\', '/', dirname($relpath));
        return ($dir === '.' || $dir === '/') ? '' : $dir;
    }

    /**
     * Split "path#fragment" into its two parts.
     *
     * @param string $href
     * @return array{0: string, 1: string}
     */
    protected static function split_fragment(string $href): array {
        $hash = strpos($href, '#');
        if ($hash === false) {
            return [$href, ''];
        }
        return [substr($href, 0, $hash), substr($href, $hash + 1)];
    }

    /**
     * Resolve a relative reference against a base directory, staying inside the root.
     *
     * @param string $basedir component-relative directory of the current document
     * @param string $ref the relative reference
     * @return string|null normalised component-relative path, or null if it escapes the root
     */
    protected static function resolve_relative(string $basedir, string $ref): ?string {
        $ref = str_replace('\\', '/', $ref);
        $combined = ($basedir === '' ? '' : $basedir . '/') . $ref;
        $segments = [];
        foreach (explode('/', $combined) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if (empty($segments)) {
                    return null;
                }
                array_pop($segments);
            } else {
                $segments[] = $segment;
            }
        }
        return empty($segments) ? null : implode('/', $segments);
    }

    /**
     * Produce a unique, URL-safe heading slug.
     *
     * @param string $text heading text
     * @param array $used slugs already assigned as keys (updated by reference)
     * @return string
     */
    protected static function unique_slug(string $text, array &$used): string {
        $slug = \core_text::specialtoascii($text);
        $slug = \core_text::strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim((string) $slug, '-');
        if ($slug === '') {
            $slug = 'section';
        }
        $candidate = $slug;
        $i = 2;
        while (isset($used[$candidate])) {
            $candidate = $slug . '-' . $i++;
        }
        $used[$candidate] = true;
        return $candidate;
    }

    /**
     * Serialise the inner HTML of an element.
     *
     * @param \DOMElement $element
     * @return string
     */
    protected static function inner_html(\DOMElement $element): string {
        $html = '';
        foreach ($element->childNodes as $child) {
            $html .= $element->ownerDocument->saveHTML($child);
        }
        return $html;
    }
}
