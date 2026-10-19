/*
 * The settings a page's editors share, put back on every editor that only names them.
 *
 * The slash menu, the strings of both pickers and the find bar's are the same for every
 * field set up the same way, so the server writes each one in full on the first editor of a
 * render and only its key on the rest (`SharedSettings` in PHP). This runs before any
 * extension reads them - TipTap calls `onBeforeCreate` before it builds the view, and the
 * readers start with the view or later - and copies each one onto the element under the
 * attribute it always had. Not one of the readers knows the difference.
 *
 * Where a setting comes from: the page's store first, which every editor fills as it starts
 * with what it carries; then the editor that carries it, which is in the page with this one
 * whenever the store cannot have it yet - they arrived in the same response, and editors do
 * not start in the order they stand in. The store is what keeps working after that editor
 * has gone: a repeater item deleted, a modal closed. Filament loads this verbatim through a
 * dynamic `import()`, so there are no `import` statements.
 */

export const STORE = 'arteSharedSettings'

/**
 * Fills in every setting the element names and does not carry, and keeps what it carries.
 */
export function share(element, store = (window[STORE] ??= {}), root = document) {
    let shared = null

    try {
        shared = JSON.parse(element?.dataset?.arteShared ?? 'null')
    } catch (error) {
        console.error('The advanced rich editor could not read which settings it shares:', error)

        return
    }

    if (!shared) {
        return
    }

    for (const [name, key] of Object.entries(shared)) {
        const carried = element.dataset[name]

        if (carried !== undefined) {
            store[key] = carried

            continue
        }

        const value = store[key] ?? root.querySelector(`[data-arte-carries~="${key}"]`)?.dataset?.[name]

        if (value === undefined) {
            console.error(`The advanced rich editor could not find the settings it shares as ${name}.`)

            continue
        }

        store[key] = value
        element.dataset[name] = value
    }
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor shared settings extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    return tiptap.Extension.create({
        name: 'arteSharedSettings',

        onBeforeCreate() {
            share(this.editor.options.element)
        },
    })
}
