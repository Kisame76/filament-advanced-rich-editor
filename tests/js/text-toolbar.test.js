import { afterEach, describe, expect, it, vi } from 'vitest'
import textToolbarExtension, {
    TOOLBAR,
    allowsMarks,
    holdsInteraction,
    textToolbarVisibility,
} from '../../resources/js/text-toolbar.js'

/**
 * The rule that decides whether the bar over selected text is on screen.
 *
 * Filament hard-codes one for the `paragraph` key: `isFocused && isActive('paragraph') &&
 * ! selection.empty`. Two things follow from the middle clause, and both are holes. In a
 * heading the bar never appears, so a field with no toolbar - `->notion()`, or plain
 * `->toolbarButtons([])` - has the link, the colours and the styles out of reach there. And
 * a selected picture answers yes to the outer two clauses, which is why a stylesheet rule
 * had to hide the bar rather than the rule that draws it.
 */

const blockOf = (name, { textblock = true, code = false, children = [] } = {}) => ({
    isTextblock: textblock,
    type: { name, spec: code ? { code: true } : {} },
    children,
})

/**
 * Enough of a document to be walked the way the rule walks one.
 *
 * It honours what the callback returns - false meaning "do not go into this one" - because
 * that is the half of ProseMirror's `nodesBetween` contract the rule leans on, and a double
 * that ignored it would agree with a rule that had stopped working.
 */
const docOf = (ranges) => ({
    nodesBetween(from, to, callback) {
        const walk = (nodes) => {
            for (const node of nodes) {
                if (callback(node) !== false) {
                    walk(node.children ?? [])
                }
            }
        }

        walk(ranges[from] ?? [])
    },
})

/**
 * `ranges` is what the selection actually covers, and `block` what `$from` resolves to. They
 * are the same thing for an ordinary selection and pointedly not for a selection across table
 * cells, which is the case the two being one variable used to hide.
 *
 * A range per entry, because a selection carries one per disjoint piece and `from`/`to` are
 * the first range's ends rather than the bounds of all of them. The fake document is keyed on
 * the range's own start so that each one is walked separately, the way a real one is.
 */
const editorIn = (
    block = blockOf('paragraph'),
    { focused = true, empty = false, node = null, ranges = null } = {},
) => {
    const covered = ranges ?? [[block]]

    return {
        isFocused: focused,
        state: {
            doc: docOf(covered),
            selection: {
                empty,
                node,
                $from: { parent: block },
                ranges: covered.map((_, index) => ({ $from: { pos: index }, $to: { pos: index } })),
            },
        },
    }
}

const barInside = (container) => {
    const element = document.createElement('div')
    const button = document.createElement('button')

    element.appendChild(button)
    container.appendChild(element)

    return { element, button }
}

describe('the blocks a mark means something in', () => {
    it('takes every ordinary text block', () => {
        // The whole point: a heading is a text block, and Filament's rule refused it.
        expect(allowsMarks(blockOf('paragraph'))).toBe(true)
        expect(allowsMarks(blockOf('heading'))).toBe(true)
        expect(allowsMarks(blockOf('blockquote'))).toBe(true)
    })

    it('refuses a block that holds code, because bold has nothing to say in one', () => {
        // Asked of the schema rather than of the node's name, so a project's own code-ish
        // block is covered by the same answer.
        expect(allowsMarks(blockOf('codeBlock', { code: true }))).toBe(false)
    })

    it('refuses what is not a text block at all', () => {
        // Asked of one block. What the selection covers is asked separately, and a cell that
        // answers no here still holds paragraphs that answer yes.
        expect(allowsMarks(blockOf('tableRow', { textblock: false }))).toBe(false)
        expect(allowsMarks(null)).toBe(false)
        expect(allowsMarks(undefined)).toBe(false)
    })
})

describe('whether the bar over a selection stays on screen', () => {
    it('shows over selected text in a paragraph, the way Filament already did', () => {
        expect(textToolbarVisibility()({ editor: editorIn(), element: null })).toBe(true)
    })

    it('shows over selected text in a heading, which is the hole it closes', () => {
        const editor = editorIn(blockOf('heading'))

        expect(textToolbarVisibility()({ editor, element: null })).toBe(true)
    })

    it('stays away from a caret, since there is nothing to format', () => {
        const editor = editorIn(blockOf('paragraph'), { empty: true })

        expect(textToolbarVisibility()({ editor, element: null })).toBe(false)
    })

    it('stays away from a selected node, which is not a selection of text', () => {
        // What the stylesheet used to do with `:has(.ProseMirror-selectednode)`, said where
        // the decision belongs: a picture, an embed or a callout selected whole answers
        // "not empty", and bold, italic and a link have nothing to say about one.
        const editor = editorIn(blockOf('paragraph'), { node: { type: { name: 'image' } } })

        expect(textToolbarVisibility()({ editor, element: null })).toBe(false)
    })

    it('stays away from a code block', () => {
        const editor = editorIn(blockOf('codeBlock', { code: true }))

        expect(textToolbarVisibility()({ editor, element: null })).toBe(false)
    })

    it('shows over a selection running across table cells', () => {
        // A `CellSelection` resolves its first position inside the cell, so `$from.parent` is
        // the `tableCell` - not a text block, though every character selected sits in one.
        // Read from that block alone the bar disappeared the moment a selection crossed a
        // cell boundary, on text bold, italic and a link all still applied to.
        const cell = (child) =>
            blockOf('tableCell', { textblock: false, children: [child] })

        const editor = editorIn(blockOf('tableCell', { textblock: false }), {
            // One range per cell, the way a real one carries them - and the second holds the
            // block that answers, so a rule reading only the first would still be wrong.
            ranges: [[cell(blockOf('codeBlock', { code: true }))], [cell(blockOf('paragraph'))]],
        })

        expect(textToolbarVisibility()({ editor, element: null })).toBe(true)
    })

    it('shows where a selection leaves a code block for ordinary prose', () => {
        // One block that takes marks is enough. Refusing the whole selection because it began
        // in the one block that does not is a bar that is simply missing.
        const editor = editorIn(blockOf('codeBlock', { code: true }), {
            ranges: [[blockOf('codeBlock', { code: true }), blockOf('paragraph')]],
        })

        expect(textToolbarVisibility()({ editor, element: null })).toBe(true)
    })

    it('stays away from a selection holding no text block at all', () => {
        const rule = blockOf('horizontalRule', { textblock: false })

        expect(
            textToolbarVisibility()({ editor: editorIn(rule, { ranges: [[rule]] }), element: null }),
        ).toBe(false)
    })

    it('stays while the focus is inside the bar, which is where a colour picker puts it', () => {
        // The bar carries the style picker and both colour pickers, and every one of them
        // is a panel: clicking into one takes the focus off the editor, and the transaction
        // that click dispatches would otherwise find `isFocused` false and remove the bar -
        // with the open panel inside it.
        const { element, button } = barInside(document.body)

        button.focus()

        expect(
            textToolbarVisibility()({ editor: editorIn(blockOf('paragraph'), { focused: false }), element }),
        ).toBe(true)
    })

    it('stays while a press is landing inside it, which is where a blur commits from', () => {
        const { element, button } = barInside(document.body)

        document.body.focus()

        const shouldShow = textToolbarVisibility({ pressed: () => button })

        expect(shouldShow({ editor: editorIn(blockOf('paragraph'), { focused: false }), element })).toBe(true)
    })

    it('goes away when the focus and the press are both somewhere else', () => {
        const { element } = barInside(document.body)
        const elsewhere = document.createElement('button')

        document.body.appendChild(elsewhere)
        elsewhere.focus()

        const shouldShow = textToolbarVisibility({ pressed: () => elsewhere })

        expect(shouldShow({ editor: editorIn(blockOf('paragraph'), { focused: false }), element })).toBe(false)
    })

    it('answers the same question the list panel asks, and answers it the same way', () => {
        // A copy rather than an import, because the shipped modules never import a sibling
        // synchronously. Held to the list panel's behaviour here instead.
        expect(holdsInteraction(null)).toBe(false)

        const { element, button } = barInside(document.body)

        expect(holdsInteraction(element, button)).toBe(true)
        expect(holdsInteraction(element, document.body)).toBe(false)
    })
})

/**
 * Installing that rule. Through the bubble menu plugin's own `updateOptions` message, the
 * way the image toolbar and the two list toolbars already do it, rather than by touching
 * Filament's component.
 */

const build = () => {
    window.FilamentRichEditor = {
        tiptap: { core: { Extension: { create: (definition) => definition } } },
    }

    return textToolbarExtension()
}

const fakeEditor = () => {
    const metas = {}
    const transaction = {
        setMeta(key, value) {
            metas[key] = value

            return transaction
        },
    }

    const dom = document.createElement('div')

    document.body.appendChild(dom)

    return { metas, editor: { state: { tr: transaction }, view: { dom, dispatch: vi.fn() } } }
}

afterEach(() => {
    delete window.FilamentRichEditor
    document.body.innerHTML = ''
})

describe('the toolbar it takes over', () => {
    it('names the one key Filament treats as a special case', () => {
        // Keyed `paragraph` rather than `text`, and that is Filament's name rather than a
        // choice: a key called anything else would be drawn and never shown.
        expect(TOOLBAR).toBe('paragraph')
    })

    it('hands that bubble menu a rule of its own', () => {
        const { editor, metas } = fakeEditor()
        const extension = build()

        extension.onCreate.call({ editor, storage: extension.addStorage() })

        expect(editor.view.dispatch).toHaveBeenCalledOnce()
        expect(metas[`floatingToolbar::${TOOLBAR}`]).toMatchObject({ type: 'updateOptions' })
        expect(typeof metas[`floatingToolbar::${TOOLBAR}`].options.shouldShow).toBe('function')
    })

    it('keeps the message out of the undo history, because it is not an edit', () => {
        const { editor, metas } = fakeEditor()
        const extension = build()

        extension.onCreate.call({ editor, storage: extension.addStorage() })

        expect(metas.addToHistory).toBe(false)
    })

    it('follows the pointer, so a press inside the bar holds it open', () => {
        const { editor, metas } = fakeEditor()
        const extension = build()
        const storage = extension.addStorage()

        extension.onCreate.call({ editor, storage })

        const { element, button } = barInside(document.body)
        const shouldShow = metas[`floatingToolbar::${TOOLBAR}`].options.shouldShow
        const blurred = { editor: editorIn(blockOf('paragraph'), { focused: false }), element }

        expect(shouldShow(blurred)).toBe(false)

        button.dispatchEvent(new Event('pointerdown', { bubbles: true }))

        expect(shouldShow(blurred)).toBe(true)

        button.dispatchEvent(new Event('pointerup', { bubbles: true }))

        expect(shouldShow(blurred)).toBe(false)
    })

    it('takes its listeners away with it', () => {
        const { editor, metas } = fakeEditor()
        const extension = build()
        const storage = extension.addStorage()

        extension.onCreate.call({ editor, storage })
        extension.onDestroy.call({ editor, storage })

        const { element, button } = barInside(document.body)

        button.dispatchEvent(new Event('pointerdown', { bubbles: true }))

        expect(
            metas[`floatingToolbar::${TOOLBAR}`].options.shouldShow({
                editor: editorIn(blockOf('paragraph'), { focused: false }),
                element,
            }),
        ).toBe(false)
    })

    it('says so and stands down where Filament exposed no TipTap', () => {
        window.FilamentRichEditor = {}

        const error = vi.spyOn(console, 'error').mockImplementation(() => {})

        expect(textToolbarExtension()).toBeNull()
        expect(error).toHaveBeenCalled()

        error.mockRestore()
    })
})
