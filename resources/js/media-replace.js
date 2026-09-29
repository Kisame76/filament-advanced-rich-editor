/*
 * A file replaced or deleted in the library, on the editor's side.
 *
 * Filament loads this verbatim through a dynamic `import()`, so there are no `import`
 * statements here and TipTap is read from the global the editor publishes. See
 * `task-list.js` for the whole reasoning.
 *
 * The browser replaces a file under the id every document points at, so every document shows
 * the new file the next time it is drawn - except the one open underneath the browser, whose
 * nodes still hold the address they were loaded with. In a media library that address is
 * gone the moment the file is renamed, which would leave a broken picture on screen right
 * after a replacement that worked. So the browser says what changed (`media-picker.js`
 * dispatches `arte-media-replaced` on the window) and every open editor rewrites its nodes.
 *
 * Deleting is the same story the other way round: the stored entries are cleaned on the
 * server, and the one open underneath the dialog would otherwise go on showing a file that is
 * gone - and save it back. `arte-media-deleted` takes its nodes out.
 *
 * Every editor rather than only the one the browser belongs to: two fields on one page may
 * share a library, and both are showing the same file.
 */

/** The nodes that point at an attachment. Mirrors `Media/FileAttachments::TYPES`. */
export const CARRIERS = ['image', 'media', 'file']

/**
 * An address that shows the new file where it did not change - a disk keeps the path, and
 * the browser would draw the old picture out of its cache. Only where there is no query
 * already: a signed address is new every time, and adding to it would break its signature.
 * What is written here is replaced on the next render anyway, since the id is the truth.
 */
const fresh = (src, previous) =>
    src === previous && !src.includes('?') ? `${src}?v=${Date.now()}` : src

/**
 * The height a sized picture needs to keep the new picture's shape at the width it was
 * given. Null where either shape is unknown, and the size is then left as it was.
 */
const heightFor = (attrs, detail) => {
    const width = Number.parseFloat(attrs.width)
    const height = Number.parseFloat(attrs.height)

    if (!(width > 0) || !(height > 0) || !(detail.width > 0) || !(detail.height > 0)) {
        return null
    }

    return Math.round((width * detail.height) / detail.width)
}

/**
 * Points every node carrying the replaced id at the new file, in one transaction, and
 * answers whether there was anything to point.
 *
 * Kept out of the undo history: the old address may not exist any more, and undoing back to
 * it would be undoing to a broken picture.
 */
export const replaceAttachment = (editor, detail) => {
    const id = detail?.id
    const src = detail?.src

    if (!editor?.state || !id || typeof src !== 'string' || src === '') {
        return false
    }

    const { tr } = editor.state
    let changed = false

    editor.state.doc.descendants((node, pos) => {
        if (!CARRIERS.includes(node.type.name) || node.attrs.id !== id) {
            return
        }

        const attrs = { ...node.attrs, src: fresh(src, node.attrs.src) }

        // A card carries its name and size as the text it was inserted with.
        if (node.type.name === 'file') {
            attrs.name = detail.name || attrs.name
            attrs.size = detail.size || attrs.size
        }

        if (node.type.name === 'image') {
            attrs.height = heightFor(attrs, detail) ?? attrs.height
        }

        tr.setNodeMarkup(pos, undefined, attrs)
        changed = true
    })

    if (changed) {
        editor.view.dispatch(tr.setMeta('addToHistory', false))
    }

    return changed
}

/**
 * Takes every node carrying the deleted id out of the document, in one transaction, and
 * answers whether there was anything to take.
 *
 * From the back, so taking one out does not move the ones still to go. Kept out of the undo
 * history for the reason a replacement is: undoing would bring back a picture of nothing.
 */
export const removeAttachment = (editor, detail) => {
    const id = detail?.id

    if (!editor?.state || !id) {
        return false
    }

    const found = []

    editor.state.doc.descendants((node, pos) => {
        if (CARRIERS.includes(node.type.name) && node.attrs.id === id) {
            found.push([pos, pos + node.nodeSize])
        }
    })

    if (found.length === 0) {
        return false
    }

    const { tr } = editor.state

    for (const [from, to] of found.reverse()) {
        tr.delete(from, to)
    }

    editor.view.dispatch(tr.setMeta('addToHistory', false))

    return true
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor media extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    return tiptap.Extension.create({
        name: 'arteMediaReplace',

        addStorage() {
            return { replaced: null, deleted: null }
        },

        // On the window, because that is where the browser says it: the dialog is a modal,
        // and a modal is not inside the field it was opened from.
        onCreate() {
            this.storage.replaced = (event) => replaceAttachment(this.editor, event.detail)
            this.storage.deleted = (event) => removeAttachment(this.editor, event.detail)

            window.addEventListener('arte-media-replaced', this.storage.replaced)
            window.addEventListener('arte-media-deleted', this.storage.deleted)
        },

        onDestroy() {
            if (this.storage.replaced) {
                window.removeEventListener('arte-media-replaced', this.storage.replaced)
            }

            if (this.storage.deleted) {
                window.removeEventListener('arte-media-deleted', this.storage.deleted)
            }
        },
    })
}
