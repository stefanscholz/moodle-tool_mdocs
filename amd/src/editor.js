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
 * Markdown editor: a formatting toolbar and a live preview for the page form.
 *
 * The preview is rendered on the server by the same pipeline that renders a
 * saved page, so what it shows is what will be published.
 *
 * @module     tool_mdocs/editor
 * @copyright  2026 bdecent GmbH <info@bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {get_strings as getStrings} from 'core/str';

/** @var {String[]} The strings the editor needs, in one round trip. */
const STRING_KEYS = [
    'editorbold', 'editorbulletlist', 'editorcode', 'editorheading', 'editorimage',
    'editoritalic', 'editorlink', 'editornumberedlist', 'editorpreview',
    'editorpreviewempty', 'editorpreviewfailed', 'editorquote', 'editorwrite',
];

/**
 * Wrap the selection (or a placeholder) in the given markers.
 *
 * @param {HTMLTextAreaElement} textarea
 * @param {String} before
 * @param {String} after
 * @param {String} placeholder used when nothing is selected
 */
const wrapSelection = (textarea, before, after, placeholder) => {
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selected = textarea.value.slice(start, end) || placeholder;

    textarea.setRangeText(before + selected + after, start, end, 'end');
    // Leave the inner text selected, so typing replaces the placeholder.
    textarea.setSelectionRange(start + before.length, start + before.length + selected.length);
    textarea.focus();
};

/**
 * Add or remove a prefix on every line the selection touches.
 *
 * @param {HTMLTextAreaElement} textarea
 * @param {Function} prefixFor receives the line index, returns the prefix
 */
const prefixLines = (textarea, prefixFor) => {
    const value = textarea.value;
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;

    const from = value.lastIndexOf('\n', start - 1) + 1;
    let to = value.indexOf('\n', end);
    if (to === -1) {
        to = value.length;
    }

    const lines = value.slice(from, to).split('\n');
    const allPrefixed = lines.every((line, i) => line.startsWith(prefixFor(i)));
    const out = lines.map((line, i) => {
        const prefix = prefixFor(i);
        return allPrefixed ? line.slice(prefix.length) : prefix + line;
    }).join('\n');

    textarea.setRangeText(out, from, to, 'select');
    textarea.focus();
};

/**
 * Insert a fenced code block, or wrap an inline span in backticks.
 *
 * @param {HTMLTextAreaElement} textarea
 * @param {Object} strings
 */
const applyCode = (textarea, strings) => {
    const selected = textarea.value.slice(textarea.selectionStart, textarea.selectionEnd);
    if (selected.indexOf('\n') !== -1) {
        wrapSelection(textarea, '```\n', '\n```', selected);
    } else {
        wrapSelection(textarea, '`', '`', strings.editorcode.toLowerCase());
    }
};

/**
 * The toolbar buttons, in order.
 *
 * @param {Object} strings
 * @return {Array}
 */
const buttons = (strings) => [
    {key: 'editorbold', label: 'B', bold: true, apply: (t) => wrapSelection(t, '**', '**', strings.editorbold)},
    {key: 'editoritalic', label: 'I', italic: true, apply: (t) => wrapSelection(t, '*', '*', strings.editoritalic)},
    {key: 'editorheading', label: 'H', apply: (t) => prefixLines(t, () => '## ')},
    {key: 'editorquote', label: '❝', apply: (t) => prefixLines(t, () => '> ')},
    {key: 'editorcode', label: '</>', apply: (t) => applyCode(t, strings)},
    {key: 'editorlink', label: '🔗', apply: (t) => wrapSelection(t, '[', '](https://)', strings.editorlink)},
    {key: 'editorimage', label: '🖼', apply: (t) => wrapSelection(t, '![', '](@@PLUGINFILE@@/image.png)', 'alt')},
    {key: 'editorbulletlist', label: '•', apply: (t) => prefixLines(t, () => '- ')},
    {key: 'editornumberedlist', label: '1.', apply: (t) => prefixLines(t, (i) => (i + 1) + '. ')},
];

/**
 * Build the toolbar element.
 *
 * @param {HTMLTextAreaElement} textarea
 * @param {Object} strings
 * @return {HTMLElement}
 */
const buildToolbar = (textarea, strings) => {
    const toolbar = document.createElement('div');
    toolbar.className = 'tool-mdocs-editor-toolbar btn-group btn-group-sm';
    toolbar.setAttribute('role', 'toolbar');

    buttons(strings).forEach((spec) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline-secondary tool-mdocs-editor-btn';
        button.textContent = spec.label;
        button.title = strings[spec.key];
        button.setAttribute('aria-label', strings[spec.key]);
        if (spec.bold) {
            button.style.fontWeight = 'bold';
        }
        if (spec.italic) {
            button.style.fontStyle = 'italic';
        }
        button.addEventListener('click', () => spec.apply(textarea));
        toolbar.appendChild(button);
    });

    return toolbar;
};

/**
 * Fill the preview pane with the server-rendered Markdown.
 *
 * @param {HTMLTextAreaElement} textarea
 * @param {HTMLElement} target
 * @param {Object} strings
 * @return {Promise}
 */
const renderPreview = (textarea, target, strings) => {
    if (textarea.value.trim() === '') {
        target.textContent = strings.editorpreviewempty;
        return Promise.resolve();
    }

    return Ajax.call([{
        methodname: 'tool_mdocs_preview_page',
        args: {content: textarea.value},
    }])[0].then((data) => {
        // Already sanitised server-side by Moodle's output cleaner.
        target.innerHTML = data.html;
        return data;
    }).catch(() => {
        target.textContent = strings.editorpreviewfailed;
    });
};

/**
 * Build the Write/Preview tabs.
 *
 * @param {Object} strings
 * @return {Object} the nav element and the two tab buttons
 */
const buildTabs = (strings) => {
    const nav = document.createElement('div');
    nav.className = 'tool-mdocs-editor-tabs btn-group btn-group-sm mr-2';

    const write = document.createElement('button');
    write.type = 'button';
    write.className = 'btn btn-secondary';
    write.textContent = strings.editorwrite;
    write.setAttribute('aria-pressed', 'true');

    const preview = document.createElement('button');
    preview.type = 'button';
    preview.className = 'btn btn-outline-secondary';
    preview.textContent = strings.editorpreview;
    preview.setAttribute('aria-pressed', 'false');

    nav.appendChild(write);
    nav.appendChild(preview);

    return {nav, write, preview};
};

/**
 * Initialise the editor on a textarea.
 *
 * @param {String} textareaId id of the Markdown textarea
 */
export const init = (textareaId) => {
    const textarea = document.getElementById(textareaId);
    if (!textarea || textarea.dataset.mdocsEditor === '1') {
        return;
    }
    textarea.dataset.mdocsEditor = '1';

    getStrings(STRING_KEYS.map((key) => ({key, component: 'tool_mdocs'}))).then((loaded) => {
        const strings = {};
        STRING_KEYS.forEach((key, i) => {
            strings[key] = loaded[i];
        });

        const wrapper = document.createElement('div');
        wrapper.className = 'tool-mdocs-editor';

        const header = document.createElement('div');
        header.className = 'tool-mdocs-editor-header d-flex flex-wrap align-items-center mb-2';

        const tabs = buildTabs(strings);
        header.appendChild(tabs.nav);
        header.appendChild(buildToolbar(textarea, strings));

        const preview = document.createElement('div');
        preview.className = 'tool-mdocs-editor-preview tool-mdocs-markdown';
        preview.hidden = true;

        textarea.parentNode.insertBefore(wrapper, textarea);
        wrapper.appendChild(header);
        wrapper.appendChild(textarea);
        wrapper.appendChild(preview);

        const showWrite = () => {
            preview.hidden = true;
            textarea.hidden = false;
            tabs.write.className = 'btn btn-secondary';
            tabs.preview.className = 'btn btn-outline-secondary';
            tabs.write.setAttribute('aria-pressed', 'true');
            tabs.preview.setAttribute('aria-pressed', 'false');
            tabs.nav.parentNode.querySelector('.tool-mdocs-editor-toolbar').hidden = false;
        };

        const showPreview = () => {
            // Match the editing area's height, so switching tabs does not jump the page.
            preview.style.minHeight = textarea.offsetHeight + 'px';
            textarea.hidden = true;
            preview.hidden = false;
            tabs.write.className = 'btn btn-outline-secondary';
            tabs.preview.className = 'btn btn-secondary';
            tabs.write.setAttribute('aria-pressed', 'false');
            tabs.preview.setAttribute('aria-pressed', 'true');
            tabs.nav.parentNode.querySelector('.tool-mdocs-editor-toolbar').hidden = true;

            renderPreview(textarea, preview, strings);
        };

        tabs.write.addEventListener('click', showWrite);
        tabs.preview.addEventListener('click', showPreview);

        return strings;
    }).catch(() => {
        // Without strings there is no toolbar; the plain textarea still works.
        return;
    });
};
