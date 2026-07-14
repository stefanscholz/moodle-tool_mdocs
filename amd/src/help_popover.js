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
 * The help icon in the navbar: pick a document, read it without leaving the page.
 *
 * The list is already in the page, because the server had to look it up anyway to
 * decide whether to show the icon at all. Only the document itself is fetched, and
 * only when somebody asks for it.
 *
 * @module     tool_mdocs/help_popover
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import Ajax from 'core/ajax';
import PopoverRegionController from 'core/popover_region_controller';
import {get_string as getString} from 'core/str';

/**
 * Show the list of documents, and hide whichever one was open.
 *
 * @param {HTMLElement} root the popover
 */
const showList = (root) => {
    root.querySelector('[data-region="mdocs-help-list"]').hidden = false;
    root.querySelector('[data-region="mdocs-help-doc"]').hidden = true;
};

/**
 * Say that the document could not be fetched.
 *
 * @param {HTMLElement} body where the document would have gone
 * @return {Promise}
 */
const showError = (body) => {
    return getString('helpdocfailed', 'tool_mdocs').then((message) => {
        body.textContent = message;
        return message;
    }).catch(() => {
        return null;
    });
};

/**
 * Fetch a document and show it in place.
 *
 * @param {HTMLElement} root the popover
 * @param {String} source file or page
 * @param {String} sourceid the document id, or the authored page id
 * @return {Promise}
 */
const openDoc = (root, source, sourceid) => {
    const list = root.querySelector('[data-region="mdocs-help-list"]');
    const doc = root.querySelector('[data-region="mdocs-help-doc"]');
    const body = root.querySelector('[data-region="mdocs-help-body"]');

    list.hidden = true;
    doc.hidden = false;
    body.textContent = '';

    return Ajax.call([{
        methodname: 'tool_mdocs_get_contextual_doc',
        args: {
            source: source,
            sourceid: sourceid,
            contextid: parseInt(root.dataset.pagecontextid, 10) || 0,
        },
    }])[0].then((data) => {
        // Sanitised server-side by Moodle's output cleaner, as everywhere else.
        body.innerHTML = data.html;
        return data;
    }).catch(() => {
        return showError(body);
    });
};

/**
 * Initialise the help popover.
 *
 * @param {String} id the popover's element id
 */
export const init = (id) => {
    const root = document.getElementById(id);
    if (!root || root.dataset.mdocsReady === '1') {
        return;
    }
    root.dataset.mdocsReady = '1';

    // Extending core/popover_region gives us the markup; it does not give us the
    // behaviour. Without the controller the icon renders and does nothing at all
    // when you press it, because opening and closing live in here.
    new PopoverRegionController($(root));

    root.addEventListener('click', (e) => {
        const open = e.target.closest('[data-action="open-doc"]');
        if (open) {
            e.preventDefault();
            openDoc(root, open.dataset.source, open.dataset.sourceid);
            return;
        }

        const back = e.target.closest('[data-action="back"]');
        if (back) {
            e.preventDefault();
            showList(root);
            return;
        }

        const others = e.target.closest('[data-action="toggle-others"]');
        if (others) {
            e.preventDefault();
            const list = root.querySelector('[data-region="mdocs-others"]');
            const expanded = !list.hidden;
            list.hidden = expanded;
            others.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        }
    });
};
