import { afterEach, describe, expect, it, vi } from 'vitest'
import alignmentExtension, { ALIGNMENT_KEYS } from '../../resources/js/alignment.js'

/**
 * The repair for TipTap's two dead alignment shortcuts.
 *
 * Unlike the callout and the language mark, there is no pure function under this one and
 * no PHP round trip that could prove it: the whole of it is the keymap, and a keymap that
 * names the wrong alignment is exactly what went wrong upstream. So the extension is built
 * here against a stubbed `Extension.create` and its handlers are actually run - a test
 * asserting the map alone would have passed on the broken version too.
 *
 * The values matter more than they look. Filament configures `TextAlign` with
 * `['start', 'center', 'end', 'justify']`, and `setTextAlign` returns false for anything
 * else, which both loses the alignment and hands the key to the browser - where
 * `Ctrl+Shift+R` is a hard reload.
 */

const build = () => {
    window.FilamentRichEditor = {
        tiptap: { core: { Extension: { create: (definition) => definition } } },
    }

    return alignmentExtension()
}

const shortcutsOf = (extension, editor) => extension.addKeyboardShortcuts.call({ editor })

const spyingEditor = (result = true) => ({
    commands: { setTextAlign: vi.fn(() => result) },
})

afterEach(() => {
    delete window.FilamentRichEditor
})

describe('which keys are rebound', () => {
    it('takes the two TipTap binds to alignments Filament never configured', () => {
        expect(Object.keys(ALIGNMENT_KEYS)).toEqual(['Mod-Shift-l', 'Mod-Shift-r'])
    })

    it('leaves centring and justifying to TipTap, which already names them right', () => {
        // Binding them a second time would be a second place to keep correct, for no gain.
        expect(ALIGNMENT_KEYS).not.toHaveProperty('Mod-Shift-e')
        expect(ALIGNMENT_KEYS).not.toHaveProperty('Mod-Shift-j')
    })

    it('names the alignments Filament configured rather than the ones TipTap does', () => {
        // The regression, stated: `left` and `right` are what the broken handlers pass.
        expect(Object.values(ALIGNMENT_KEYS)).toEqual(['start', 'end'])
        expect(Object.values(ALIGNMENT_KEYS)).not.toContain('left')
        expect(Object.values(ALIGNMENT_KEYS)).not.toContain('right')
    })
})

describe('the extension it builds', () => {
    it('carries a name of its own, so it collides with nothing Filament ships', () => {
        expect(build().name).toBe('arteAlignmentKeys')
    })

    it('aligns to the start of the line on Mod+Shift+L', () => {
        const editor = spyingEditor()

        shortcutsOf(build(), editor)['Mod-Shift-l']()

        expect(editor.commands.setTextAlign).toHaveBeenCalledWith('start')
    })

    it('aligns to the end of the line on Mod+Shift+R', () => {
        const editor = spyingEditor()

        shortcutsOf(build(), editor)['Mod-Shift-r']()

        expect(editor.commands.setTextAlign).toHaveBeenCalledWith('end')
    })

    it('hands back what the command answered, which is what keeps the key off the browser', () => {
        // A handler returning false leaves the event alone, and the browser reloads.
        expect(shortcutsOf(build(), spyingEditor(true))['Mod-Shift-r']()).toBe(true)
        expect(shortcutsOf(build(), spyingEditor(false))['Mod-Shift-r']()).toBe(false)
    })

    it('says so and builds nothing where Filament exposed no TipTap', () => {
        const complaint = vi.spyOn(console, 'error').mockImplementation(() => {})

        expect(alignmentExtension()).toBeNull()
        expect(complaint).toHaveBeenCalled()
    })
})
