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
 * Secure server for assets (images, etc.) referenced from documentation files.
 *
 * Only files inside a valid component directory, with a whitelisted extension,
 * are served, and only to users holding tool/mdocs:view.
 *
 * @package    tool_mdocs
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/filelib.php');

require_login(null, false);

// Anyone allowed to read a document is allowed to see the pictures in it. A
// teacher reading contextual help in their course holds no system capability, so
// checking only tool/mdocs:view here would serve them a document full of broken
// images.
if (!\tool_mdocs\access::can_read_assets()) {
    throw new required_capability_exception(
        context_system::instance(),
        'tool/mdocs:view',
        'nopermissions',
        ''
    );
}

$component = required_param('component', PARAM_COMPONENT);
$relpath = required_param('path', PARAM_PATH);

// Whitelisted, non-executable extensions only.
$allowed = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'bmp', 'ico', 'md', 'txt', 'csv', 'json'];

$base = \core_component::get_component_directory($component);
if ($base === null) {
    send_file_not_found();
}
$base = realpath($base);
$full = realpath($base . '/' . ltrim($relpath, '/'));

// Path-traversal defence: the resolved path must live inside the component dir.
if (
    $base === false || $full === false || !is_file($full)
        || strpos($full, $base . DIRECTORY_SEPARATOR) !== 0
) {
    send_file_not_found();
}

$ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
if (!in_array($ext, $allowed, true)) {
    send_file_not_found();
}

// SVGs can carry embedded scripts: never serve them inline.
$forcedownload = ($ext === 'svg');

send_file($full, basename($full), null, 0, false, $forcedownload, '', false);
