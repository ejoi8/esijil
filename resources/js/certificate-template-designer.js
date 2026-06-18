import { Designer } from '@pdfme/ui';
import { image, text } from '@pdfme/schemas';

const initializedRoots = new WeakMap();

const clone = (value) => JSON.parse(JSON.stringify(value));

const parseTemplate = (root, selector) => {
    const script = root.querySelector(selector);

    if (!script?.textContent) {
        throw new Error(`Missing designer payload for ${selector}.`);
    }

    return JSON.parse(script.textContent);
};

const setStatus = (statusElement, message, tone = 'neutral') => {
    if (!statusElement) {
        return;
    }

    const toneClasses = {
        neutral: 'text-gray-600 dark:text-gray-400',
        dirty: 'text-amber-700 dark:text-amber-300',
        saving: 'text-primary-700 dark:text-primary-300',
        success: 'text-green-700 dark:text-green-300',
        danger: 'text-red-700 dark:text-red-300',
    };

    statusElement.textContent = message;
    statusElement.className = toneClasses[tone] ?? toneClasses.neutral;
};

const persistTemplate = async ({ root, saveButton, statusElement, template }) => {
    const livewireId = root.dataset.livewireId || root.closest('[wire\\:id]')?.getAttribute('wire:id');

    if (!livewireId) {
        throw new Error('Unable to resolve the Livewire component for this designer.');
    }

    const component = window.Livewire?.find(livewireId);

    if (!component) {
        throw new Error('Unable to find the Livewire component for this designer.');
    }

    saveButton.disabled = true;
    setStatus(statusElement, 'Saving layout...', 'saving');

    try {
        await component.call('saveDesigner', JSON.stringify(template));
        setStatus(statusElement, 'Layout saved.', 'success');
    } finally {
        saveButton.disabled = false;
    }
};

const loadFonts = async (root) => {
    const manifest = parseTemplate(root, '[data-pdfme-fonts]');
    const entries = await Promise.all(
        Object.entries(manifest).map(async ([name, definition]) => {
            const response = await fetch(definition.url);

            if (!response.ok) {
                throw new Error(`Unable to load font asset: ${name}`);
            }

            return [name, {
                data: await response.arrayBuffer(),
                fallback: Boolean(definition.fallback),
                subset: definition.subset !== false,
            }];
        }),
    );

    return Object.fromEntries(entries);
};

const initializeRoot = async (root) => {
    if (initializedRoots.has(root)) {
        return;
    }

    const canvas = root.querySelector('[data-pdfme-canvas]');
    const saveButton = root.querySelector('[data-pdfme-save]');
    const resetButton = root.querySelector('[data-pdfme-reset]');
    const statusElement = root.querySelector('[data-pdfme-status]');

    if (!canvas || !saveButton || !resetButton) {
        return;
    }

    initializedRoots.set(root, true);
    setStatus(statusElement, 'Loading fonts...', 'saving');

    const initialTemplate = parseTemplate(root, '[data-pdfme-template]');
    const defaultTemplate = parseTemplate(root, '[data-pdfme-default-template]');
    const fonts = await loadFonts(root);

    let currentTemplate = clone(initialTemplate);

    const designer = new Designer({
        domContainer: canvas,
        template: currentTemplate,
        plugins: {
            image,
            text,
        },
        options: {
            font: fonts,
            sidebarOpen: true,
        },
    });

    designer.onChangeTemplate((template) => {
        currentTemplate = clone(template);
        setStatus(statusElement, 'Unsaved changes.', 'dirty');
    });

    designer.onSaveTemplate(async (template) => {
        currentTemplate = clone(template);

        try {
            await persistTemplate({
                root,
                saveButton,
                statusElement,
                template: currentTemplate,
            });
        } catch (error) {
            console.error(error);
            setStatus(statusElement, 'Unable to save layout.', 'danger');
        }
    });

    saveButton.addEventListener('click', () => {
        designer.saveTemplate();
    });

    resetButton.addEventListener('click', () => {
        currentTemplate = clone(defaultTemplate);
        designer.updateTemplate(currentTemplate);
        setStatus(statusElement, 'Reset to the default layout. Save to keep it.', 'dirty');
    });

    const fieldNamesOf = (template) =>
        (Array.isArray(template?.schemas?.[0]) ? template.schemas[0] : []).map((field) => field?.name);

    // pdfme requires unique field names. Text content carries the {{token}}, so
    // the name is just an id and can be suffixed freely.
    const uniqueName = (template, base) => {
        const names = new Set(fieldNamesOf(template));

        if (!names.has(base)) {
            return base;
        }

        let suffix = 2;

        while (names.has(`${base}_${suffix}`)) {
            suffix += 1;
        }

        return `${base}_${suffix}`;
    };

    root.querySelectorAll('[data-pdfme-insert]').forEach((button) => {
        button.addEventListener('click', () => {
            const next = clone(currentTemplate);

            if (!Array.isArray(next.schemas) || !Array.isArray(next.schemas[0])) {
                next.schemas = [[]];
            }

            let field;

            if (button.dataset.pdfmeInsert === 'image') {
                const name = button.dataset.pdfmeName;

                // Image names must match the renderer variable exactly, so they
                // cannot be suffixed — skip if one is already present.
                if (fieldNamesOf(next).includes(name)) {
                    setStatus(statusElement, `"${name}" is already on the canvas.`, 'dirty');

                    return;
                }

                field = {
                    name,
                    type: 'image',
                    content: '',
                    position: { x: 20, y: 20 },
                    width: 40,
                    height: 40,
                    rotate: 0,
                    opacity: 1,
                };
            } else {
                const token = button.dataset.pdfmeToken;

                field = {
                    name: uniqueName(next, token),
                    type: 'text',
                    content: `{{${token}}}`,
                    position: { x: 20, y: 20 },
                    width: 90,
                    height: 10,
                    rotate: 0,
                    alignment: 'left',
                    verticalAlignment: 'top',
                    fontSize: 13,
                    lineHeight: 1.2,
                    characterSpacing: 0,
                    fontColor: '#000000',
                };
            }

            next.schemas[0].push(field);
            currentTemplate = next;
            designer.updateTemplate(currentTemplate);
            setStatus(statusElement, `Added "${field.name}". Drag it into place, then Save.`, 'dirty');
        });
    });

    setStatus(statusElement, 'Ready.', 'neutral');
};

const initializeAllDesigners = () => {
    document.querySelectorAll('[data-pdfme-root]').forEach((root) => {
        initializeRoot(root).catch((error) => {
            initializedRoots.delete(root);
            console.error(error);

            const statusElement = root.querySelector('[data-pdfme-status]');

            setStatus(statusElement, 'Unable to load the certificate fonts.', 'danger');
        });
    });
};

document.addEventListener('DOMContentLoaded', initializeAllDesigners);
document.addEventListener('livewire:navigated', initializeAllDesigners);
