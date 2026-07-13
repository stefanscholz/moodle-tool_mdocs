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
 * Markdown editor: a formatting toolbar and a preview for the page form.
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
 * What each toolbar button does to the text.
 *
 * 'wrap' surrounds the selection; 'prefix' marks every line the selection
 * touches. Symmetric markers toggle, so pressing Bold on bold text unwraps it.
 *
 * blankline matters: in Markdown a list marker on the line straight after a
 * paragraph is read as more of that paragraph, not as a list, so a list has to
 * be separated from the text above it by a blank line or it silently renders as
 * literal "- text". Headings and quotes may interrupt a paragraph, and do not
 * need this.
 */
const ACTIONS = {
    bold: {kind: 'wrap', before: '**', after: '**', toggle: true},
    italic: {kind: 'wrap', before: '*', after: '*', toggle: true},
    // The placeholder has to be a real URL: Moodle's output sanitiser drops the
    // href of a link whose target is not one, leaving a dead <a> with no href.
    // Leave the URL selected, so it is typed straight over.
    link: {kind: 'wrap', before: '[', after: '](https://example.com)', selecturl: true},
    image: {kind: 'wrap', before: '![', after: '](@@PLUGINFILE@@/image.png)', selecturl: true},
    code: {kind: 'code'},
    heading: {kind: 'prefix', prefix: () => '## '},
    quote: {kind: 'prefix', prefix: () => '> '},
    bullet: {kind: 'prefix', prefix: () => '- ', blankline: true},
    number: {kind: 'prefix', prefix: (i) => (i + 1) + '. ', blankline: true},
};

/**
 * Surround the selection with markers, or strip them if they are already there.
 *
 * @param {Object} spec the action
 * @param {String} value the whole text
 * @param {Number} start selection start
 * @param {Number} end selection end
 * @param {String} placeholder inserted when nothing is selected
 * @return {Object} the edit to make: {from, to, text, selectFrom, selectTo}
 */
const wrapEdit = (spec, value, start, end, placeholder) => {
    const {before, after} = spec;
    const selected = value.slice(start, end);

    if (spec.toggle
            && value.slice(start - before.length, start) === before
            && value.slice(end, end + after.length) === after) {
        // Already wrapped: take the markers off again.
        return {
            from: start - before.length,
            to: end + after.length,
            text: selected,
            selectFrom: start - before.length,
            selectTo: start - before.length + selected.length,
        };
    }

    const body = selected || placeholder;
    const text = before + body + after;

    if (spec.selecturl) {
        // Land the cursor on the URL inside the trailing "](...)", not on the label.
        const open = after.indexOf('(');
        const close = after.lastIndexOf(')');
        const urlAt = start + before.length + body.length;
        return {
            from: start,
            to: end,
            text,
            selectFrom: urlAt + open + 1,
            selectTo: urlAt + close,
        };
    }

    return {
        from: start,
        to: end,
        text,
        selectFrom: start + before.length,
        selectTo: start + before.length + body.length,
    };
};

/**
 * Add or remove a marker on every line the selection touches.
 *
 * @param {Object} spec the action
 * @param {String} value the whole text
 * @param {Number} start selection start
 * @param {Number} end selection end
 * @return {Object} the edit to make: {from, to, text, selectFrom, selectTo}
 */
const prefixEdit = (spec, value, start, end) => {
    const from = start === 0 ? 0 : value.lastIndexOf('\n', start - 1) + 1;
    let to = value.indexOf('\n', end);
    if (to === -1) {
        to = value.length;
    }

    const lines = value.slice(from, to).split('\n');
    const marked = lines.every((line, i) => line.startsWith(spec.prefix(i)));

    let text = lines.map((line, i) => {
        const prefix = spec.prefix(i);
        return marked ? line.slice(prefix.length) : prefix + line;
    }).join('\n');

    if (!marked && spec.blankline && from > 0) {
        const above = value.slice(0, from - 1);
        const previousLine = above.slice(above.lastIndexOf('\n') + 1);
        if (previousLine.trim() !== '') {
            // Detach the list from the paragraph above, or it is not a list.
            text = '\n' + text;
        }
    }

    return {from, to, text, selectFrom: from, selectTo: from + text.length};
};

/**
 * Work out the edit a toolbar button should make. Pure: no DOM involved.
 *
 * @param {String} action one of the keys of ACTIONS
 * @param {String} value the whole text
 * @param {Number} start selection start
 * @param {Number} end selection end
 * @param {String} placeholder inserted when nothing is selected
 * @return {Object|null} {from, to, text, selectFrom, selectTo}, or null if unknown
 */
export const edit = (action, value, start, end, placeholder) => {
    const spec = ACTIONS[action];
    if (!spec) {
        return null;
    }

    if (spec.kind === 'prefix') {
        return prefixEdit(spec, value, start, end);
    }

    if (spec.kind === 'code') {
        const selected = value.slice(start, end);
        const fence = selected.indexOf('\n') !== -1;
        const codespec = fence
            ? {kind: 'wrap', before: '```\n', after: '\n```'}
            : {kind: 'wrap', before: '`', after: '`', toggle: true};
        return wrapEdit(codespec, value, start, end, placeholder);
    }

    return wrapEdit(spec, value, start, end, placeholder);
};

/**
 * Apply an action to the textarea, keeping the browser's undo history intact.
 *
 * @param {HTMLTextAreaElement} textarea
 * @param {String} action
 * @param {String} placeholder
 */
const apply = (textarea, action, placeholder) => {
    const change = edit(action, textarea.value, textarea.selectionStart, textarea.selectionEnd, placeholder);
    if (!change) {
        return;
    }

    // setRangeText rather than assigning value, so ctrl-z still works.
    textarea.setRangeText(change.text, change.from, change.to, 'end');
    textarea.setSelectionRange(change.selectFrom, change.selectTo);
    textarea.focus();
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

    // Send the form's draft file area, so images that have been uploaded but not
    // yet saved resolve in the preview instead of showing as broken.
    const attachments = document.getElementById('id_attachments');
    const draftitemid = attachments ? parseInt(attachments.value, 10) || 0 : 0;

    return Ajax.call([{
        methodname: 'tool_mdocs_preview_page',
        args: {content: textarea.value, draftitemid: draftitemid},
    }])[0].then((data) => {
        // Already sanitised server-side by Moodle's output cleaner.
        target.innerHTML = data.html;
        return data;
    }).catch(() => {
        target.textContent = strings.editorpreviewfailed;
    });
};

/**
 * The toolbar buttons, in order.
 *
 * @param {Object} strings
 * @return {Array}
 */
const buttons = (strings) => [
    {action: 'bold', label: 'B', title: strings.editorbold, bold: true},
    {action: 'italic', label: 'I', title: strings.editoritalic, italic: true},
    {action: 'heading', label: 'H', title: strings.editorheading},
    {action: 'quote', label: '❝', title: strings.editorquote},
    {action: 'code', label: '</>', title: strings.editorcode},
    {action: 'link', label: '\u{1F517}', title: strings.editorlink},
    {action: 'image', label: '\u{1F5BC}', title: strings.editorimage},
    {action: 'bullet', label: '•', title: strings.editorbulletlist},
    {action: 'number', label: '1.', title: strings.editornumberedlist},
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
        button.title = spec.title;
        button.setAttribute('aria-label', spec.title);
        if (spec.bold) {
            button.style.fontWeight = 'bold';
        }
        if (spec.italic) {
            button.style.fontStyle = 'italic';
        }
        // Keep the textarea's selection: a plain click would blur it first.
        button.addEventListener('mousedown', (e) => e.preventDefault());
        button.addEventListener('click', () => apply(textarea, spec.action, spec.title));
        toolbar.appendChild(button);
    });

    return toolbar;
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
        const toolbar = buildToolbar(textarea, strings);
        header.appendChild(tabs.nav);
        header.appendChild(toolbar);

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
            toolbar.hidden = false;
            tabs.write.className = 'btn btn-secondary';
            tabs.preview.className = 'btn btn-outline-secondary';
            tabs.write.setAttribute('aria-pressed', 'true');
            tabs.preview.setAttribute('aria-pressed', 'false');
        };

        const showPreview = () => {
            // Match the editing area's height, so switching tabs does not jump the page.
            preview.style.minHeight = textarea.offsetHeight + 'px';
            textarea.hidden = true;
            preview.hidden = false;
            toolbar.hidden = true;
            tabs.write.className = 'btn btn-outline-secondary';
            tabs.preview.className = 'btn btn-secondary';
            tabs.write.setAttribute('aria-pressed', 'false');
            tabs.preview.setAttribute('aria-pressed', 'true');

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
