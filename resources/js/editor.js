import EditorJS from '@editorjs/editorjs';
import Header from '@editorjs/header';
import List from '@editorjs/list';
import Quote from '@editorjs/quote';
import Delimiter from '@editorjs/delimiter';
import Table from '@editorjs/table';
import Code from '@editorjs/code';
import Embed from '@editorjs/embed';
import Paragraph from '@editorjs/paragraph';

const extractPlainText = (data) => {
    if (!data || !Array.isArray(data.blocks)) {
        return '';
    }

    const lines = [];

    data.blocks.forEach((block) => {
        if (!block || typeof block !== 'object') {
            return;
        }

        const { type, data: blockData } = block;

        switch (type) {
            case 'paragraph':
            case 'header':
            case 'quote':
            case 'code':
                if (blockData?.text || blockData?.code) {
                    lines.push((blockData.text || blockData.code || '').replace(/<[^>]+>/g, ''));
                }
                break;
            case 'list':
                if (Array.isArray(blockData?.items)) {
                    blockData.items.forEach((item) => {
                        lines.push(`• ${String(item).replace(/<[^>]+>/g, '')}`);
                    });
                }
                break;
            case 'table':
                if (Array.isArray(blockData?.content)) {
                    blockData.content.forEach((row) => {
                        if (Array.isArray(row)) {
                            lines.push(row.map((cell) => String(cell).replace(/<[^>]+>/g, '')).join(' | '));
                        }
                    });
                }
                break;
            case 'embed':
                if (blockData?.service) {
                    lines.push(`${blockData.service} embed`);
                }
                break;
            default:
                break;
        }
    });

    return lines.join('\n').trim();
};

const toolConfig = {
    paragraph: {
        class: Paragraph,
        inlineToolbar: ['bold', 'italic', 'link'],
        config: {
            placeholder: 'Start writing...'
        }
    },
    header: {
        class: Header,
        inlineToolbar: ['link'],
        config: {
            levels: [2, 3, 4],
            defaultLevel: 2,
        }
    },
    list: {
        class: List,
        inlineToolbar: true,
    },
    quote: {
        class: Quote,
        inlineToolbar: true,
        config: {
            captionPlaceholder: 'Quote source',
        }
    },
    delimiter: {
        class: Delimiter,
    },
    table: {
        class: Table,
        inlineToolbar: true,
    },
    code: {
        class: Code,
    },
    embed: {
        class: Embed,
        inlineToolbar: false,
        config: {
            services: {
                youtube: true,
            },
        },
    },
};

const initialiseEditors = () => {
    const inputs = document.querySelectorAll('input[data-editorjs="true"]');

    inputs.forEach((input) => {
        const holderId = input.dataset.holder;
        const holder = holderId ? document.getElementById(holderId) : null;

        if (!holder) {
            return;
        }

        let initialData = null;
        const initialValue = input.value?.trim();

        if (initialValue) {
            try {
                initialData = JSON.parse(initialValue);
            } catch (error) {
                initialData = null;
            }
        }

        const editor = new EditorJS({
            holder,
            minHeight: 240,
            data: initialData ?? undefined,
            placeholder: input.dataset.placeholder || 'Write something awesome…',
            tools: toolConfig,
            inlineToolbar: true,
            onChange: async () => {
                try {
                    const output = await editor.save();
                    input.value = JSON.stringify(output);
                    const detail = {
                        input,
                        data: output,
                        plainText: extractPlainText(output),
                    };
                    input.dispatchEvent(new CustomEvent('editorjs:change', { detail }));
                    const errorElement = document.querySelector(`[data-editorjs-error="${input.id}"]`);
                    if (errorElement) {
                        errorElement.textContent = '';
                        errorElement.hidden = true;
                    }
                } catch (error) {
                    console.error(error);
                }
            },
            onReady: async () => {
                if (!initialData) {
                    try {
                        const output = await editor.save();
                        input.value = JSON.stringify(output);
                    } catch (error) {
                        // Ignore initial serialisation errors.
                    }
                }

                const initialPlain = input.dataset.initialPlain;
                const detail = {
                    input,
                    data: initialData,
                    plainText: initialPlain || extractPlainText(initialData),
                };
                input.dispatchEvent(new CustomEvent('editorjs:ready', { detail }));
            },
        });

        input.__editor = editor;

        const form = input.closest('form');

        if (form) {
            form.addEventListener('submit', async (event) => {
                if (!editor) {
                    return;
                }

                try {
                    const output = await editor.save();
                    input.value = JSON.stringify(output);
                } catch (error) {
                    event.preventDefault();
                    const errorElement = document.querySelector(`[data-editorjs-error="${input.id}"]`);
                    if (errorElement) {
                        errorElement.textContent = 'The editor content could not be saved. Please try again.';
                        errorElement.hidden = false;
                    }
                }
            });
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialiseEditors);
} else {
    initialiseEditors();
}
