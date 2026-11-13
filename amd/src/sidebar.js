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
 * Sidebar navigation: collapsible groups and a client-side filter.
 *
 * @module     tool_mdocs/sidebar
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Initialise the sidebar behaviour.
 */
export const init = () => {
    document.querySelectorAll('[data-action="toggle"]').forEach((button) => {
        button.addEventListener('click', () => {
            const group = button.closest('[data-region="mdocs-group"]');
            if (group) {
                group.classList.toggle('tool-mdocs-open');
            }
        });
    });

    const filter = document.querySelector('[data-region="mdocs-filter"]');
    if (!filter) {
        return;
    }
    filter.addEventListener('input', () => {
        const term = filter.value.trim().toLowerCase();
        document.querySelectorAll('[data-region="mdocs-group"]').forEach((group) => {
            let visible = false;
            group.querySelectorAll('[data-region="mdocs-doc"]').forEach((doc) => {
                const match = term === '' || doc.textContent.toLowerCase().indexOf(term) !== -1;
                doc.hidden = !match;
                if (match) {
                    visible = true;
                }
            });
            // A section heading without a matching document would be left standing alone.
            group.querySelectorAll('[data-region="mdocs-section"]').forEach((section) => {
                section.hidden = section.querySelector('[data-region="mdocs-doc"]:not([hidden])') === null;
            });
            group.hidden = !visible;
            if (term !== '' && visible) {
                group.classList.add('tool-mdocs-open');
            }
        });
    });
};
