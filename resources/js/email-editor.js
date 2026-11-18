// Word-style editor for the Email members page. Loaded only on that page.
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import TextAlign from '@tiptap/extension-text-align';
import TextStyle from '@tiptap/extension-text-style';
import Color from '@tiptap/extension-color';
import Placeholder from '@tiptap/extension-placeholder';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/** Uploads a picture and returns its public URL. */
async function upload(file, url) {
    const body = new FormData();
    body.append('image', file);
    const res = await fetch(url, { method: 'POST', body, headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.errors?.image?.[0] ?? data.message ?? 'Upload failed.');
    return data.url;
}

window.createEmailEditor = function (element, { content, uploadUrl, onChange, onState, onUploading, onError }) {
    let editor;

    const addFiles = async (files) => {
        const images = [...files].filter((f) => f.type.startsWith('image/'));
        if (!images.length) return false;
        onUploading(true);
        try {
            for (const file of images) {
                const src = await upload(file, uploadUrl);
                editor.chain().focus().setImage({ src, alt: '' }).run();
            }
        } catch (e) {
            onError(e.message);
        } finally {
            onUploading(false);
        }
        return true;
    };

    const state = () => ({
        bold: editor.isActive('bold'),
        italic: editor.isActive('italic'),
        underline: editor.isActive('underline'),
        strike: editor.isActive('strike'),
        bulletList: editor.isActive('bulletList'),
        orderedList: editor.isActive('orderedList'),
        blockquote: editor.isActive('blockquote'),
        link: editor.isActive('link'),
        block: editor.isActive('heading', { level: 2 }) ? 'h2' : editor.isActive('heading', { level: 3 }) ? 'h3' : 'p',
        align: ['center', 'right'].find((a) => editor.isActive({ textAlign: a })) ?? 'left',
        color: editor.getAttributes('textStyle').color ?? '',
        canUndo: editor.can().undo(),
        canRedo: editor.can().redo(),
    });

    editor = new Editor({
        element,
        content,
        extensions: [
            StarterKit.configure({ heading: { levels: [2, 3] }, code: false, codeBlock: false }),
            Underline,
            TextStyle,
            Color,
            Link.configure({ openOnClick: false, autolink: true, protocols: ['mailto'], HTMLAttributes: { rel: 'noopener', target: '_blank' } }),
            Image.configure({ inline: false, allowBase64: false }),
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
            Placeholder.configure({ placeholder: 'Write your message here…' }),
        ],
        editorProps: {
            attributes: { class: 'email-editor-content', 'aria-label': 'Message', role: 'textbox', 'aria-multiline': 'true' },
            handlePaste: (view, event) => {
                const files = event.clipboardData?.files;
                if (files?.length) {
                    addFiles(files);
                    return true;
                }
                return false;
            },
            handleDrop: (view, event) => {
                const files = event.dataTransfer?.files;
                if (files?.length) {
                    event.preventDefault();
                    addFiles(files);
                    return true;
                }
                return false;
            },
        },
        onUpdate: () => onChange(editor.isEmpty ? '' : editor.getHTML()),
        onTransaction: () => onState(state()),
    });
    onState(state());

    const run = () => editor.chain().focus();

    return {
        toggle(mark) {
            const cmd = { bold: 'toggleBold', italic: 'toggleItalic', underline: 'toggleUnderline', strike: 'toggleStrike',
                bulletList: 'toggleBulletList', orderedList: 'toggleOrderedList', blockquote: 'toggleBlockquote' }[mark];
            run()[cmd]().run();
        },
        block(type) {
            if (type === 'p') run().setParagraph().run();
            else run().setHeading({ level: type === 'h2' ? 2 : 3 }).run();
        },
        align(where) { run().setTextAlign(where).run(); },
        color(value) { value ? run().setColor(value).run() : run().unsetColor().run(); },
        divider() { run().setHorizontalRule().run(); },
        link() {
            const current = editor.getAttributes('link').href ?? '';
            const url = window.prompt('Link address (https://…). Leave empty to remove the link.', current || 'https://');
            if (url === null) return;
            const clean = url.trim();
            if (!clean || clean === 'https://') {
                run().extendMarkRange('link').unsetLink().run();
                return;
            }
            const href = /^(https?:\/\/|mailto:)/i.test(clean) ? clean : `https://${clean}`;
            if (editor.state.selection.empty && !editor.isActive('link')) {
                run().insertContent(`<a href="${href.replace(/"/g, '&quot;')}">${clean.replace(/</g, '&lt;')}</a> `).run();
            } else {
                run().extendMarkRange('link').setLink({ href }).run();
            }
        },
        insertName() { run().insertContent('{name}').run(); },
        clear() { run().unsetAllMarks().clearNodes().run(); },
        undo() { run().undo().run(); },
        redo() { run().redo().run(); },
        addFiles,
        setContent(html) {
            editor.commands.setContent(html, true);
            onChange(editor.isEmpty ? '' : editor.getHTML());
        },
        text() { return editor.getText(); },
        focus() { editor.commands.focus(); },
    };
};

window.dispatchEvent(new Event('email-editor-ready'));
