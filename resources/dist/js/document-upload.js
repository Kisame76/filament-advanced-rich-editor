/*
 * A document dropped or pasted into a field without a media browser, as a card.
 *
 * Filament loads this verbatim through a dynamic `import()`, so there are no `import`
 * statements here and TipTap is read from the global the editor publishes. See
 * `task-list.js` for the whole reasoning.
 *
 * Filament's own handler (`localFiles`) inserts an `image` node for every file it accepts. A
 * field that accepts a pdf therefore stored `<img src="….pdf">` - or, on a local disk, where
 * Livewire hands out no preview address for a pdf, nothing at all. This handler runs ahead of
 * it and takes a drop or a paste only where that would go wrong: some file in it is not a
 * picture, and every file in it is one the field accepts. Everything else - pictures alone,
 * or a file the field refuses, whose message is Filament's to show - is left to Filament.
 *
 * The upload is Filament's in every way that matters: the same temporary storage under the
 * same path, measured against the same accepted types and size limit, saved by the same
 * save. Which node an upload becomes is asked of the field (`getUploadedFileAttachmentNodeForJs`),
 * so the dialog and this handler give the same answer. The PHP half is
 * `Concerns/UploadsDocuments.php`.
 *
 * Inert where the field hands it no settings - a field with a browser, or one that takes
 * nothing but pictures.
 */

export const NAME = 'documentUpload'

/**
 * The settings the field handed over, or null where there are none or they cannot be read.
 */
export function settingsOf(element) {
    try {
        const settings = JSON.parse(element?.dataset?.arteDocumentUpload ?? 'null')

        if (typeof settings?.key !== 'string' || typeof settings?.statePath !== 'string') {
            return null
        }

        const maxSize = Number(settings.maxSize)

        return {
            key: settings.key,
            statePath: settings.statePath,
            acceptedTypes: Array.isArray(settings.acceptedTypes) ? settings.acceptedTypes.map(String) : null,
            maxSize: settings.maxSize !== null && Number.isFinite(maxSize) && maxSize > 0 ? maxSize : null,
            uploadingMessage: typeof settings.uploadingMessage === 'string' ? settings.uploadingMessage : null,
        }
    } catch {
        return null
    }
}

/**
 * Whether the field takes a file, measured the way Filament's handler measures it: by its
 * exact type, and by its size in kilobytes. No list means any type.
 */
export function accepts(file, settings) {
    if (settings.acceptedTypes && !settings.acceptedTypes.includes(file.type)) {
        return false
    }

    return !(settings.maxSize && file.size > settings.maxSize * 1024)
}

/**
 * Whether this handler takes a drop or a paste of these files.
 */
export function takes(files, settings) {
    if (!settings || files.length === 0) {
        return false
    }

    if (files.every((file) => String(file.type).startsWith('image/'))) {
        return false
    }

    return files.every((file) => accepts(file, settings))
}

/**
 * A name for one upload in Livewire's temporary storage. Not the media browser's `arte-`
 * prefix: that prefix sends an upload to the browser's own list of accepted types.
 */
export function uploadKey() {
    if (typeof crypto?.randomUUID === 'function') {
        return crypto.randomUUID()
    }

    // `randomUUID` is only there in a secure context, and a field on a plain-http intranet
    // page is not in one.
    return '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, (c) =>
        (c ^ (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (c / 4)))).toString(16),
    )
}

/**
 * Uploads the files one after the other and inserts what each became, in the order they
 * came, at the position the drop or the caret named.
 *
 * Says it is busy the way Filament's handler does - the editor is read-only, the field shows
 * its uploading message and the form will not submit - and says it is done however the
 * uploads went.
 */
export async function uploadAll({ wire, editor, view, files, settings, position }) {
    const form = view.dom.closest('form')
    const detail = { key: settings.key, livewireId: wire.id }
    const wasEditable = editor.isEditable

    editor.setEditable(false)
    form?.dispatchEvent(
        new CustomEvent('form-processing-started', {
            composed: true,
            cancelable: true,
            detail: { message: settings.uploadingMessage },
        }),
    )
    view.dom.dispatchEvent(new CustomEvent('rich-editor-uploading-file', { bubbles: true, detail }))

    try {
        const nodes = []

        for (const file of files) {
            const key = uploadKey()
            const uploaded = await new Promise((resolve) => {
                wire.upload(
                    `componentFileAttachments.${settings.statePath}.${key}`,
                    file,
                    () => resolve(true),
                    () => resolve(false),
                    () => {},
                    () => resolve(false),
                )
            })

            if (!uploaded) {
                continue
            }

            const node = await wire.callSchemaComponentMethod(settings.key, 'getUploadedFileAttachmentNodeForJs', {
                attachment: key,
            })

            if (typeof node?.type === 'string') {
                nodes.push(node)
            }
        }

        if (nodes.length > 0 && !editor.isDestroyed) {
            editor.chain().insertContentAt(position(), nodes).run()
        }
    } catch (error) {
        console.error('The advanced rich editor could not upload a file:', error)
    } finally {
        if (!editor.isDestroyed) {
            editor.setEditable(wasEditable)
            view.dom.dispatchEvent(new CustomEvent('rich-editor-uploaded-file', { bubbles: true, detail }))
        }

        form?.dispatchEvent(new CustomEvent('form-processing-finished', { composed: true, cancelable: true }))
    }
}

/**
 * The Livewire component the field lives in, reached through Alpine - see `mention.js`.
 */
function wireFor(editor) {
    const element = editor.options.element

    if (!element || !window.Alpine) {
        return null
    }

    try {
        return window.Alpine.evaluate(element, '$wire') ?? null
    } catch {
        return null
    }
}

export default () => {
    const { Extension } = window.FilamentRichEditor.tiptap.core
    const { Plugin, PluginKey } = window.FilamentRichEditor.tiptap.pmState

    return Extension.create({
        name: NAME,

        // Ahead of Filament's `localFiles` (at the default 100), whose handlers would take
        // the same drop and insert the document as a picture.
        priority: 1000,

        addProseMirrorPlugins() {
            const editor = this.editor
            const settings = settingsOf(editor.options.element)

            if (!settings) {
                return []
            }

            const start = (view, files, position) => {
                const wire = wireFor(editor)

                if (!wire) {
                    return false
                }

                uploadAll({ wire, editor, view, files, settings, position })

                return true
            }

            return [
                new Plugin({
                    key: new PluginKey(NAME),
                    props: {
                        handleDrop(view, event, _slice, moved) {
                            // A move inside the editor is ProseMirror's, as it is for Filament.
                            if (moved) {
                                return false
                            }

                            const files = Array.from(event.dataTransfer?.files ?? [])

                            if (!takes(files, settings)) {
                                return false
                            }

                            const at = view.posAtCoords({ left: event.clientX, top: event.clientY })

                            if (!start(view, files, () => at?.pos ?? editor.state.selection.anchor)) {
                                return false
                            }

                            event.preventDefault()
                            event.stopPropagation()

                            return true
                        },

                        handlePaste(view, event) {
                            const files = Array.from(event.clipboardData?.files ?? [])

                            // Text beside the file means a copy from somewhere that also put
                            // a picture of it on the clipboard - a spreadsheet, a word
                            // processor. The text is what was meant.
                            if ((event.clipboardData?.getData('text')?.length ?? 0) > 0) {
                                return false
                            }

                            if (!takes(files, settings)) {
                                return false
                            }

                            if (!start(view, files, () => editor.state.selection.anchor)) {
                                return false
                            }

                            event.preventDefault()
                            event.stopPropagation()

                            return true
                        },
                    },
                }),
            ]
        },
    })
}
