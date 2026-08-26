/*
 * The two alignment shortcuts TipTap binds and then refuses to answer.
 *
 * `TextAlign` ships its own keymap - `Mod+Shift+L`, `E`, `R`, `J` - and the four handlers
 * call `setTextAlign` with `left`, `center`, `right` and `justify`. Filament configures the
 * extension with `['start', 'center', 'end', 'justify']` instead, which is the right call
 * for a panel that renders in both directions, and `setTextAlign` answers an alignment it
 * was not configured with by returning false. So the middle two keys work and the outer two
 * are dead: `left` and `right` are not on the list.
 *
 * Dead is not the whole of it. A shortcut handler that returns false leaves the event
 * alone, so the key reaches the browser - and `Ctrl+Shift+R` is a hard reload in Chrome and
 * Firefox. The advertised way to align a paragraph left threw the draft away.
 *
 * Rebinding the two is enough, and the order the extensions are registered in does not
 * matter: ProseMirror tries every keymap until one handles the key, and TipTap's own
 * handler declines these two before this one is reached.
 */

/**
 * The keys, and the alignment each one means on a Filament editor.
 *
 * `center` and `justify` are absent on purpose: TipTap's own handlers name the same values
 * Filament configured, so those two keys already do what the list in the help dialog says
 * they do, and binding them twice would only be a second place to keep them right.
 */
export const ALIGNMENT_KEYS = {
    'Mod-Shift-l': 'start',
    'Mod-Shift-r': 'end',
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor alignment extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap

    return Extension.create({
        name: 'arteAlignmentKeys',

        addKeyboardShortcuts() {
            return Object.fromEntries(
                Object.entries(ALIGNMENT_KEYS).map(([key, alignment]) => [
                    key,
                    // Setting rather than toggling, which is what the toolbar buttons and
                    // TipTap's own four handlers do. It also returns true, which is what
                    // keeps the key away from the browser.
                    () => this.editor.commands.setTextAlign(alignment),
                ]),
            )
        },
    })
}
