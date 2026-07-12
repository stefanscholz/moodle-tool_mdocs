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
 * Table of contents scroll-spy for the document viewer.
 *
 * @module     tool_mdocs/toc
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Initialise the scroll-spy that highlights the current heading.
 */
export const init = () => {
    const links = Array.prototype.slice.call(document.querySelectorAll('[data-mdocs-toc]'));
    if (!links.length || typeof window.IntersectionObserver === 'undefined') {
        return;
    }

    const linksById = {};
    links.forEach((link) => {
        linksById[link.getAttribute('data-mdocs-toc')] = link;
    });

    const observer = new window.IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }
            links.forEach((link) => link.classList.remove('tool-mdocs-toc-active'));
            const active = linksById[entry.target.id];
            if (active) {
                active.classList.add('tool-mdocs-toc-active');
            }
        });
    }, {rootMargin: '0px 0px -70% 0px', threshold: 0});

    Object.keys(linksById).forEach((id) => {
        const heading = document.getElementById(id);
        if (heading) {
            observer.observe(heading);
        }
    });
};
