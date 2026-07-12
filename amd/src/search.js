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
 * Live search for the Documentation Center landing page.
 *
 * @module     tool_mdocs/search
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Debounce a function.
 *
 * @param {Function} fn
 * @param {Number} wait milliseconds
 * @return {Function}
 */
const debounce = (fn, wait) => {
    let timer = null;
    return (...args) => {
        if (timer) {
            window.clearTimeout(timer);
        }
        timer = window.setTimeout(() => fn(...args), wait);
    };
};

/**
 * Render the result list.
 *
 * @param {HTMLElement} container
 * @param {Array} items
 */
const render = (container, items) => {
    container.innerHTML = '';
    if (!items.length) {
        container.hidden = true;
        return;
    }
    items.forEach((item) => {
        const link = document.createElement('a');
        link.href = item.url;
        link.className = 'list-group-item list-group-item-action';
        link.textContent = item.title + ' — ' + item.componentname;
        container.appendChild(link);
    });
    container.hidden = false;
};

/**
 * Initialise the search box.
 *
 * @param {String} inputId
 * @param {String} resultsId
 */
export const init = (inputId, resultsId) => {
    const input = document.getElementById(inputId);
    const results = document.getElementById(resultsId);
    if (!input || !results) {
        return;
    }

    const run = () => {
        const query = input.value.trim();
        if (query.length < 2) {
            results.hidden = true;
            results.innerHTML = '';
            return;
        }
        Ajax.call([{
            methodname: 'tool_mdocs_search_docs',
            args: {query: query},
        }])[0].then((data) => {
            render(results, data.results);
            return data;
        }).catch(() => {
            results.hidden = true;
        });
    };

    input.addEventListener('input', debounce(run, 250));
    document.addEventListener('click', (e) => {
        if (e.target !== input && !results.contains(e.target)) {
            results.hidden = true;
        }
    });
};
