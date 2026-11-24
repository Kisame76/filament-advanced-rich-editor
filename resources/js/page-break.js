/**
 * The page break: where a printed document starts a new page.
 *
 * Filament loads this verbatim through a dynamic `import()`, so there are no `import`
 * statements here and TipTap is read from the global the editor publishes. See
 * `task-list.js` for the whole reasoning.
 *
 * Stored as an empty `div` that asks for the break in its own `style`, so a browser printing
 * the page, DomPDF and Chromium all break there without this package's stylesheet. On a
 * page it is nothing - no height, no text. In the editor it is drawn as a dashed line with
 * its name on it, translated by the field: see `PageBreak.php` and `getPageBreakSettingsForJs()`.
 */

export const NAME = 'pageBreak'

/**
 * Both spellings of the break, as `PageBreak::STYLE` writes them.
 */
export const STYLE = 'break-after: page; page-break-after: always;'

/**
 * What the field handed over: the line's label. Anything unreadable leaves the line without
 * one rather than breaking the editor.
 */
export function settingsOf(element) {
    try {
        const settings = JSON.parse(element?.dataset?.artePageBreak ?? 'null')

        return { label: String(settings?.label ?? '') }
    } catch {
        return { label: '' }
    }
}

/**
 * Whether pasted markup is a break another editor wrote: an empty element whose only
 * business is a forced break, the way CKEditor writes one. Mirrors
 * `PageBreak::isForeignBreak()`, so a paste and a save read the same thing.
 */
export function isPageBreakElement(element) {
    if (!element || typeof element.getAttribute !== 'function') {
        return false
    }

    if (element.getAttribute('data-type') === NAME) {
        return true
    }

    const isEmpty = (element.textContent ?? '').replace(/ /g, ' ').trim() === ''
    const style = element.getAttribute('style') ?? ''

    return isEmpty && /(?:^|;)\s*(?:page-break-(?:after|before)\s*:\s*always|break-(?:after|before)\s*:\s*page)\b/i.test(style)
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core
    const pmState = window.FilamentRichEditor?.tiptap?.pmState

    if (!tiptap || !pmState) {
        console.error(
            'The advanced rich editor page break extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Node, mergeAttributes } = tiptap
    const { NodeSelection, TextSelection } = pmState

    return Node.create({
        name: NAME,

        group: 'block',

        atom: true,

        selectable: true,

        draggable: true,

        addOptions() {
            return { HTMLAttributes: {} }
        },

        parseHTML() {
            // Above the default 50, for the reasons `callout.js` gives: a plain `div` rule
            // further down would otherwise take the element first.
            return [
                { tag: `div[data-type="${NAME}"]`, priority: 51 },
                { tag: 'div', priority: 51, getAttrs: (element) => (isPageBreakElement(element) ? null : false) },
            ]
        },

        renderHTML({ HTMLAttributes }) {
            return [
                'div',
                mergeAttributes(this.options.HTMLAttributes, HTMLAttributes, {
                    class: 'fi-arte-page-break',
                    'data-type': NAME,
                    style: STYLE,
                }),
            ]
        },

        addNodeView() {
            return () => {
                const dom = document.createElement('div')

                dom.className = 'fi-arte-page-break'
                dom.dataset.type = NAME
                dom.dataset.label = settingsOf(this.editor.options.element).label
                dom.contentEditable = 'false'

                return { dom }
            }
        },

        addCommands() {
            return {
                /**
                 * Puts a break where the caret is - splitting the paragraph it stands in - and
                 * the caret after it, as TipTap's horizontal rule does. At the end of the
                 * document that takes a new paragraph, or there would be nowhere to type the
                 * next page's first word.
                 */
                setPageBreak:
                    () =>
                    ({ chain, state }) => {
                        const { selection } = state
                        const insertion = chain()

                        if (selection instanceof NodeSelection) {
                            insertion.insertContentAt(selection.$to.pos, { type: NAME })
                        } else {
                            insertion.insertContent({ type: NAME })
                        }

                        return insertion
                            .command(({ tr, dispatch }) => {
                                if (!dispatch) {
                                    return true
                                }

                                const { $to } = tr.selection
                                const after = $to.nodeAfter

                                if (after?.isTextblock) {
                                    tr.setSelection(TextSelection.create(tr.doc, $to.pos + 1))
                                } else if (after?.isBlock) {
                                    tr.setSelection(NodeSelection.create(tr.doc, $to.pos))
                                } else if (!after) {
                                    const paragraph = $to.parent.type.contentMatch.defaultType?.create()

                                    if (paragraph) {
                                        tr.insert($to.end(), paragraph)
                                        tr.setSelection(TextSelection.create(tr.doc, $to.end() + 1))
                                    }
                                }

                                tr.scrollIntoView()

                                return true
                            })
                            .run()
                    },
            }
        },
    })
}
