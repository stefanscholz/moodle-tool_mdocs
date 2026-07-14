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
 * The document opens in a window that can be moved and resized, because help is
 * read *while* working: a panel that cannot be moved is a panel that covers the
 * very thing it is explaining. Where it was put and how big it was made are
 * remembered, and it can be popped out into a browser window of its own, to sit
 * beside Moodle rather than on top of it.
 *
 * @module     tool_mdocs/help_popover
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import Ajax from 'core/ajax';
import PopoverRegionController from 'core/popover_region_controller';
import Storage from 'core/localstorage';
import {get_string as getString} from 'core/str';

/** @type {String} Where the window's size and position are remembered. */
const STORE = 'tool_mdocs/help_window';

/** @type {Number} Narrowest the window may be made, in pixels. */
const MIN_WIDTH = 260;

/** @type {Number} Shortest the window may be made, in pixels. */
const MIN_HEIGHT = 140;

/** @type {Number} How much of the window must stay on screen, in pixels. */
const KEEP_VISIBLE = 48;

/** @type {Number} How far an arrow key moves or resizes the window, in pixels. */
const NUDGE = 24;

/**
 * Read back the size and position the window was last left in.
 *
 * @return {Object|null} the remembered geometry, or null when there is none
 */
const recall = () => {
    const stored = Storage.get(STORE);
    if (!stored) {
        return null;
    }

    try {
        return JSON.parse(stored);
    } catch (e) {
        return null;
    }
};

/**
 * Remember where the window is and how big it is.
 *
 * @param {HTMLElement} win the window
 */
const remember = (win) => {
    const rect = win.getBoundingClientRect();

    Storage.set(STORE, JSON.stringify({
        left: Math.round(rect.left),
        top: Math.round(rect.top),
        width: Math.round(rect.width),
        height: Math.round(rect.height),
    }));
};

/**
 * Put the window somewhere, keeping enough of it on screen to take hold of again.
 *
 * A remembered position is not necessarily still a possible one: the browser may
 * have been made smaller since, or moved to another screen.
 *
 * @param {HTMLElement} win the window
 * @param {Number} left distance from the left of the viewport
 * @param {Number} top distance from the top of the viewport
 */
const place = (win, left, top) => {
    const leftmost = KEEP_VISIBLE - win.offsetWidth;

    win.style.left = Math.max(leftmost, Math.min(left, window.innerWidth - KEEP_VISIBLE)) + 'px';
    win.style.top = Math.max(0, Math.min(top, window.innerHeight - KEEP_VISIBLE)) + 'px';
};

/**
 * Size the window, within what the viewport can hold.
 *
 * @param {HTMLElement} win the window
 * @param {Number} width
 * @param {Number} height
 */
const resize = (win, width, height) => {
    win.style.width = Math.max(MIN_WIDTH, Math.min(width, window.innerWidth)) + 'px';
    win.style.height = Math.max(MIN_HEIGHT, Math.min(height, window.innerHeight)) + 'px';
};

/**
 * Restore the window to where it was left, or to a sensible first position.
 *
 * @param {HTMLElement} win the window
 */
const restore = (win) => {
    const saved = recall();

    if (saved) {
        resize(win, saved.width, saved.height);
        place(win, saved.left, saved.top);
        return;
    }

    // The first time, put it under the icon it came from and out of the way.
    resize(win, 420, Math.round(window.innerHeight * 0.6));
    place(win, window.innerWidth - win.offsetWidth - 24, 72);
};

/**
 * Drag the window by its header.
 *
 * @param {HTMLElement} win the window
 * @param {PointerEvent} e the press that started the drag
 */
const drag = (win, e) => {
    if (e.button !== 0) {
        return;
    }

    const rect = win.getBoundingClientRect();
    const grabx = e.clientX - rect.left;
    const graby = e.clientY - rect.top;

    const move = (ev) => place(win, ev.clientX - grabx, ev.clientY - graby);
    const drop = () => {
        window.removeEventListener('pointermove', move);
        window.removeEventListener('pointerup', drop);
        win.classList.remove('tool-mdocs-dragging');
        remember(win);
    };

    win.classList.add('tool-mdocs-dragging');
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', drop);

    // Without this the browser selects the title text instead of moving anything.
    e.preventDefault();
};

/**
 * Move and resize the window from the keyboard, so that a mouse is not the only
 * way to get it out from in front of what it is explaining.
 *
 * @param {HTMLElement} win the window
 * @param {KeyboardEvent} e a key pressed while the header had focus
 * @return {Boolean} whether the key was one of ours
 */
const nudge = (win, e) => {
    const steps = {
        ArrowLeft: [-NUDGE, 0],
        ArrowRight: [NUDGE, 0],
        ArrowUp: [0, -NUDGE],
        ArrowDown: [0, NUDGE],
    };

    const step = steps[e.key];
    if (!step) {
        return false;
    }

    const rect = win.getBoundingClientRect();
    if (e.ctrlKey) {
        resize(win, rect.width + step[0], rect.height + step[1]);
    } else {
        place(win, rect.left + step[0], rect.top + step[1]);
    }
    remember(win);

    return true;
};

/**
 * Close the window, handing the focus back to the icon that opened it.
 *
 * @param {HTMLElement} win the window
 * @param {HTMLElement} root the popover
 */
const close = (win, root) => {
    win.hidden = true;

    const toggle = root.querySelector('.popover-region-toggle');
    if (toggle) {
        toggle.focus();
    }
};

/**
 * Fetch a document and show it in the window.
 *
 * @param {HTMLElement} win the window
 * @param {HTMLElement} root the popover
 * @param {String} source file or page
 * @param {String} sourceid the document id, or the authored page id
 * @return {Promise}
 */
const openDoc = (win, root, source, sourceid) => {
    const title = win.querySelector('[data-region="mdocs-window-title"]');
    const body = win.querySelector('[data-region="mdocs-help-body"]');
    const contextid = parseInt(root.dataset.pagecontextid, 10) || 0;

    // The pop-out is a real link to a page that asks the same question this
    // request does, so a blocked pop-up still lands the reader on the document.
    const popout = win.querySelector('[data-action="popout"]');
    popout.href = win.dataset.popouturl +
        '?source=' + encodeURIComponent(source) +
        '&sourceid=' + encodeURIComponent(sourceid) +
        '&contextid=' + contextid;

    body.textContent = '';
    win.hidden = false;
    restore(win);
    win.focus();

    return Ajax.call([{
        methodname: 'tool_mdocs_get_contextual_doc',
        args: {source: source, sourceid: sourceid, contextid: contextid},
    }])[0].then((data) => {
        title.textContent = data.title;
        // Sanitised server-side by Moodle's output cleaner, as everywhere else.
        body.innerHTML = data.html;
        return data;
    }).catch(() => {
        return getString('helpdocfailed', 'tool_mdocs').then((message) => {
            body.textContent = message;
            return message;
        }).catch(() => {
            return null;
        });
    });
};

/**
 * Wire the window up: dragging, keyboard moving, resizing, closing, popping out.
 *
 * @param {HTMLElement} win the window
 * @param {HTMLElement} root the popover
 */
const initWindow = (win, root) => {
    // It is rendered inside the navbar, which clips it and stacks it beneath the
    // drawers. It has to live at the end of the body to float above the page.
    document.body.appendChild(win);

    const header = win.querySelector('[data-region="mdocs-window-drag"]');

    header.addEventListener('pointerdown', (e) => {
        // Not when the press was meant for the buttons sitting in the header.
        if (!e.target.closest('button, a')) {
            drag(win, e);
        }
    });

    header.addEventListener('keydown', (e) => {
        if (nudge(win, e)) {
            e.preventDefault();
        }
    });

    // Resizing is the browser's own corner handle, so all we do is notice the
    // result and remember it.
    if (window.ResizeObserver) {
        let attaching = true;
        const observer = new window.ResizeObserver(() => {
            // It fires once on being attached, which would overwrite a remembered
            // size with whatever the window happens to be at the time.
            if (attaching) {
                attaching = false;
                return;
            }
            if (!win.hidden) {
                remember(win);
            }
        });
        observer.observe(win);
    }

    win.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            close(win, root);
        }
    });

    win.addEventListener('click', (e) => {
        if (e.target.closest('[data-action="close-window"]')) {
            close(win, root);
            return;
        }

        const popout = e.target.closest('[data-action="popout"]');
        if (!popout) {
            return;
        }

        // A window of a given size, not a tab: the point is to put it beside the
        // page you are working in, not behind it.
        const opened = window.open(
            popout.href,
            'tool_mdocs_help',
            'width=560,height=800,scrollbars=yes,resizable=yes'
        );

        if (opened) {
            e.preventDefault();
            opened.focus();
            win.hidden = true;
        }

        // Blocked by the browser: leave the link alone, and it opens in a new tab.
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
    const controller = new PopoverRegionController($(root));

    const win = document.querySelector('[data-region="mdocs-help-doc"]');
    if (!win) {
        return;
    }
    initWindow(win, root);

    root.addEventListener('click', (e) => {
        const open = e.target.closest('[data-action="open-doc"]');
        if (open) {
            e.preventDefault();
            openDoc(win, root, open.dataset.source, open.dataset.sourceid);
            // The list has done its job now: what the reader wanted is the document.
            controller.closeMenu();
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
