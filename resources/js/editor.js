import EditorJS from '@editorjs/editorjs';
import CodeTool from '@editorjs/code';
import Delimiter from '@editorjs/delimiter';
import Embed from '@editorjs/embed';
import Header from '@editorjs/header';
import List from '@editorjs/list';
import Quote from '@editorjs/quote';
import Table from '@editorjs/table';

const editors = [];

const sanitizeInitialData = (value) => {
    if (typeof value !== 'string' || value.trim() === '') {
        return undefined;
    }

    try {
        const parsed = JSON.parse(value);
        return parsed && typeof parsed === 'object' ? parsed : undefined;
    } catch (error) {
        console.warn('Invalid Editor.js JSON detected, falling back to empty content.', error);

        return undefined;
    }
};

const extractPlainText = (data) => {
    if (!data || typeof data !== 'object' || !Array.isArray(data.blocks)) {
        return '';
    }

    const segments = [];

    for (const block of data.blocks) {
        if (!block || typeof block !== 'object') {
            continue;
        }

        const type = block.type;
        const payload = block.data ?? {};

        switch (type) {
            case 'paragraph':
            case 'header':
            case 'quote':
                if (typeof payload.text === 'string') {
                    segments.push(payload.text);
                }
                break;
            case 'code':
                if (typeof payload.code === 'string') {
                    segments.push(payload.code);
                }
                break;
            case 'list':
                if (Array.isArray(payload.items)) {
                    segments.push(...payload.items.map((item) => (typeof item === 'string' ? item : '')));
                }
                break;
            case 'table':
                if (Array.isArray(payload.content)) {
                    for (const row of payload.content) {
                        if (Array.isArray(row)) {
                            segments.push(...row.map((cell) => (typeof cell === 'string' ? cell : '')));
                        }
                    }
                }
                break;
            default:
                if (typeof payload.text === 'string') {
                    segments.push(payload.text);
                }
        }
    }

    return segments
        .map((segment) => segment.replace(/<[^>]+>/g, '').replace(/&nbsp;/g, ' ').trim())
        .filter(Boolean)
        .join(' ');
};

const dispatchEvent = (element, name, detail) => {
    element.dispatchEvent(
        new CustomEvent(name, {
            bubbles: true,
            detail,
        }),
    );
};

const initEditor = (element) => {
    const holderId = element.dataset.holder;
    const inputSelector = element.dataset.input;
    const placeholder = element.dataset.placeholder ?? '';
    const minHeight = Number(element.dataset.minHeight ?? 300);
    const input = inputSelector ? document.querySelector(inputSelector) : null;

    if (!holderId || !input) {
        return;
    }

    const initialData = sanitizeInitialData(input.value);

    const editor = new EditorJS({
        holder: holderId,
        minHeight,
        placeholder,
        data: initialData,
        tools: {
            paragraph: {
                inlineToolbar: true,
            },
            header: {
                class: Header,
                inlineToolbar: true,
                config: {
                    levels: [2, 3, 4],
                    defaultLevel: 2,
                },
            },
            list: {
                class: List,
                inlineToolbar: true,
            },
            quote: {
                class: Quote,
                inlineToolbar: true,
            },
            delimiter: Delimiter,
            table: {
                class: Table,
                inlineToolbar: true,
            },
            youtube: {
                class: Embed,
                inlineToolbar: false,
                config: {
                    services: {
                        youtube: true,
                    },
                },
                toolbox: {
                    title: 'YouTube',
                },
            },
            code: CodeTool,
        },
        onReady: () => {
            if (!initialData) {
                dispatchEvent(element, 'editorjs:ready', { output: undefined, plainText: '' });

                return;
            }

            const plainText = extractPlainText(initialData);
            dispatchEvent(element, 'editorjs:ready', { output: initialData, plainText });
        },
        onChange: async () => {
            try {
                const output = await editor.save();
                input.value = JSON.stringify(output);
                const plainText = extractPlainText(output);
                dispatchEvent(element, 'editorjs:change', { output, plainText });
            } catch (error) {
                console.error('Failed to save Editor.js content', error);
            }
        },
    });

    editors.push(editor);
    element.dataset.initialized = 'true';
};

const bootEditors = () => {
    document.querySelectorAll('[data-editorjs]').forEach((element) => {
        if (element.dataset.initialized === 'true') {
            return;
        }

        initEditor(element);
    });
};

document.addEventListener('DOMContentLoaded', bootEditors);

export { editors };
