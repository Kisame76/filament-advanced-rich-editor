/*
 * The keys a project moved: a tool given another key, a first one, or none.
 *
 * Filament loads this verbatim through a dynamic `import()`, so there are no `import`
 * statements here and TipTap is read from the global the editor publishes. See
 * `task-list.js` for the whole reasoning.
 *
 * Nothing is decided here. Which key runs what is worked out by `Shortcuts::bindings()` -
 * only PHP knows which tools the field has and what their handlers are - and arrives as a
 * list: a key and the handler of the tool it now runs, or a key and no handler, for one a
 * tool shipped with and has lost. Only a field whose keys were changed loads this at all.
 */

/**
 * The bindings the field handed over, read off the element the editor was mounted on - the
 * one channel a TipTap extension has to anything the field knows.
 *
 * @returns {Array<{keys: string, tool: string, handler: ?string}>}
 */
export function readBindings(element) {
    const raw = element?.dataset?.arteShortcuts

    if (!raw) {
        return []
    }

    try {
        const bindings = JSON.parse(raw)

        return Array.isArray(bindings) ? bindings.filter((binding) => typeof binding?.keys === 'string') : []
    } catch (error) {
        console.error('The advanced rich editor could not read its keyboard shortcuts:', error)

        return []
    }
}

/**
 * Runs a tool's own handler in the editor's Alpine scope, which is what pressing its button
 * does - and what the slash menu does, for the reasons `slash-menu.js` gives. Nothing is
 * reimplemented, so a key and the button it stands for cannot drift apart.
 */
export function runTool(element, binding) {
    const Alpine = window.Alpine

    if (!Alpine?.evaluate) {
        console.error('The advanced rich editor needs Alpine to run a keyboard shortcut.')

        return
    }

    try {
        Alpine.evaluate(element, binding.handler, { scope: { $event: { currentTarget: element } } })
    } catch (error) {
        console.error(`The advanced rich editor could not run [${binding.tool}]:`, error)
    }
}

/**
 * The keymap TipTap is given. Every key in it is answered: a key with a handler runs its
 * tool, and a key without one does nothing - it must not fall through to the extension that
 * shipped with it, which is all that taking a key away means. Not to the browser either,
 * which would apply its own bold behind the editor's back.
 */
export function keymapOf(bindings, element, isEditable, run = runTool) {
    return Object.fromEntries(
        bindings.map((binding) => [
            binding.keys,
            () => {
                if (!isEditable()) {
                    return false
                }

                if (binding.handler) {
                    run(element, binding)
                }

                return true
            },
        ]),
    )
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor shortcut extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap

    return Extension.create({
        name: 'arteShortcuts',

        // Ahead of every extension that binds a key - the highest, TipTap's paragraph and
        // link, sit at 1000 - so a key handed out here is answered here, before the extension
        // that shipped with it is asked.
        priority: 10000,

        addKeyboardShortcuts() {
            const element = this.editor.options.element

            return keymapOf(readBindings(element), element, () => this.editor.isEditable)
        },
    })
}
