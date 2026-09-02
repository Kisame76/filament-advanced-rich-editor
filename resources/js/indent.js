/*
 * Indenting a paragraph, and taking the indent back off.
 *
 * Filament loads this verbatim through a dynamic `import()`, so there are no `import`
 * statements here and TipTap is read from the global the editor publishes. See
 * `task-list.js` for the whole reasoning.
 *
 * What is stored is a `margin-inline-start` in the block's inline `style`, and what the
 * document keeps is the depth it stands for. The PHP half writes and reads exactly the same
 * thing - see `TipTapExtensions/Indent.php`, which carries the reasoning behind the storage
 * form, the logical property and the depth.
 *
 * A selection inside a list is handed to the list instead. A list indents by nesting, which
 * is where its numbering and its bullets come from, and a margin beside that would be a
 * second indent the list knows nothing about. So the caret decides which of the two
 * meanings the button has - which is the same thing Word does, and the reason there is one
 * pair of buttons rather than two.
 */

/** One step, as a CSS length. Mirrors `Indent::DEFAULT_STEP`. */
export const DEFAULT_STEP = '2.5rem'

export const DEFAULT_MAX = 8

/** Mirrors `Indent::MAX_DEPTH`. */
export const MAX_DEPTH = 40

/**
 * What one of each unit is in CSS pixels, mirroring `Indent::UNITS`.
 *
 * Only ever used for reading: a length is converted to pick the depth it stands for, and
 * the writing side multiplies the configured step instead - so `em` and `ch` standing at
 * the root's own default is close enough to choose a step and never reaches a document.
 */
export const UNITS = {
    px: 1,
    pt: 1.3333333333333333,
    pc: 16,
    in: 96,
    cm: 37.795275590551185,
    mm: 3.7795275590551185,
    q: 0.9448818897637796,
    rem: 16,
    em: 16,
    ch: 8,
}

const A_LENGTH = /^\s*(-?\d+(?:\.\d+)?|-?\.\d+)\s*([a-z]*)\s*$/i

const A_STEP = /^\s*(\d+(?:\.\d+)?|\.\d+)\s*([a-z]+)\s*$/i

/**
 * The canonical spelling of a number, so a length written here and one written by PHP are
 * the same string - the toolbar and the parser both compare them.
 */
export const format = (value) => String(Number(value.toFixed(4)))

/** One length in CSS pixels, or null where it is not a length this side understands. */
export const pixels = (length) => {
    if (typeof length !== 'string') {
        return null
    }

    const match = A_LENGTH.exec(length)

    if (!match) {
        return null
    }

    const number = Number(match[1])
    const unit = match[2].toLowerCase()

    // A bare `0` is the one length CSS lets stand without a unit, and it is the one an
    // editor writes when it means "no indent".
    if (unit === '') {
        return number === 0 ? 0 : null
    }

    return Object.hasOwn(UNITS, unit) ? number * UNITS[unit] : null
}

/**
 * A configured step, canonicalised - or the shipped one where the settings do not name a
 * length this side can multiply.
 */
export const step = (value) => {
    if (typeof value === 'number') {
        value = `${format(value)}rem`
    }

    if (typeof value !== 'string') {
        return DEFAULT_STEP
    }

    const match = A_STEP.exec(value)

    if (!match) {
        return DEFAULT_STEP
    }

    const number = Number(match[1])
    const unit = match[2].toLowerCase()

    if (!(number > 0) || !Object.hasOwn(UNITS, unit)) {
        return DEFAULT_STEP
    }

    return `${format(number)}${unit}`
}

/**
 * How deep this field lets a block go. Out of range is the shipped depth rather than a
 * clamp: a configured `0` is the feature being switched off in the wrong place, and
 * answering it with `1` would answer a different question.
 */
export const max = (value) => {
    const depth = typeof value === 'string' && /^\d+$/.test(value) ? Number(value) : value

    if (!Number.isInteger(depth) || depth < 1 || depth > MAX_DEPTH) {
        return DEFAULT_MAX
    }

    return depth
}

/** A depth, held inside the field's maximum. Nothing under one is an indent at all. */
export const level = (value, maximum) => {
    const depth = typeof value === 'string' && /^-?\d+$/.test(value) ? Number(value) : value

    if (!Number.isInteger(depth) || depth < 1) {
        return null
    }

    return Math.min(depth, maximum)
}

/**
 * The length a depth writes: the step, that many times over.
 *
 * The step is canonicalised again rather than trusted, for the reason the PHP half does it:
 * every caller in here hands one that already is, and a pattern that assumed the shape would
 * answer a malformed step by reading a property of null.
 */
export const lengthOf = (depth, configuredStep) => {
    const match = A_STEP.exec(step(configuredStep))

    return `${format(Number(match[1]) * depth)}${match[2].toLowerCase()}`
}

/**
 * A length, read as a number of steps: rounded to the nearest whole one and held inside the
 * field's maximum. Rounding rather than requiring an exact multiple - see the PHP half.
 */
export const levelOf = (length, configuredStep, maximum) => {
    const there = pixels(length)
    const perStep = pixels(configuredStep)

    if (there === null || perStep === null || perStep <= 0) {
        return null
    }

    return level(Math.round(there / perStep), maximum)
}

/**
 * The depth an element's inline style stands for.
 *
 * The logical property first and `margin-left` only after it: a document this package wrote
 * carries the logical one, and an element carrying both was written twice.
 */
export const readIndent = (element, configuredStep, maximum) => {
    const style = element?.style

    if (!style) {
        return null
    }

    for (const property of ['marginInlineStart', 'marginLeft']) {
        const depth = levelOf(style[property], configuredStep, maximum)

        if (depth !== null) {
            return depth
        }
    }

    return null
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor indent extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap

    // Read where it is used rather than kept from `onCreate()`: the initial content is
    // parsed while the editor is being constructed, which is before `onCreate()` runs, and a
    // field whose step is not the shipped one would otherwise read its first document at the
    // wrong grid. `editor.options.element` is set from the constructor, so this is answerable
    // that early.
    const settingsOf = (editor) => {
        const raw = editor?.options?.element?.dataset?.arteIndent

        if (!raw) {
            return { step: DEFAULT_STEP, max: DEFAULT_MAX }
        }

        try {
            const settings = JSON.parse(raw)

            return { step: step(settings?.step), max: max(settings?.max) }
        } catch (error) {
            console.error('The advanced rich editor could not read its indent settings:', error)

            return { step: DEFAULT_STEP, max: DEFAULT_MAX }
        }
    }

    return Extension.create({
        name: 'arteIndent',

        addOptions() {
            return {
                types: ['paragraph', 'heading', 'blockquote'],
                // The nodes whose own nesting is the indent. Mirrors the list this package
                // registers: TipTap's `listItem` and the task list's `taskItem`.
                itemTypes: ['listItem', 'taskItem'],
            }
        },

        addGlobalAttributes() {
            return [
                {
                    types: this.options.types,
                    attributes: {
                        arteIndent: {
                            default: null,
                            parseHTML: (element) => {
                                const settings = settingsOf(this.editor)

                                return readIndent(element, settings.step, settings.max)
                            },
                            renderHTML: (attributes) => {
                                const settings = settingsOf(this.editor)
                                const depth = level(attributes.arteIndent, settings.max)

                                return depth === null
                                    ? {}
                                    : { style: `margin-inline-start: ${lengthOf(depth, settings.step)}` }
                            },
                        },
                    },
                },
            ]
        },

        addCommands() {
            // The list item the caret is standing in, if it is standing in one.
            const itemAt = ($from) => {
                const itemTypes = new Set(this.options.itemTypes)

                for (let depth = $from.depth; depth > 0; depth--) {
                    const name = $from.node(depth).type.name

                    if (itemTypes.has(name)) {
                        return name
                    }
                }

                return null
            }

            // Written over the declared types only, the way the line height is: TipTap's own
            // attribute commands walk every node in the selection, including the ones that
            // never declared the attribute, where ProseMirror then throws.
            const write =
                (next) =>
                ({ state, tr, dispatch, editor }) => {
                    const settings = settingsOf(editor)
                    const types = new Set(
                        this.options.types.filter((type) => state.schema.nodes[type]),
                    )
                    const itemTypes = new Set(this.options.itemTypes)

                    const { from, to } = state.selection

                    let changed = false

                    state.doc.nodesBetween(from, to, (node, pos) => {
                        // A list item and everything under it, skipped whole. A selection
                        // spanning a paragraph and a list would otherwise put a margin on the
                        // paragraphs inside the list items as well - two indents on one line,
                        // one of which the list did not ask for.
                        if (itemTypes.has(node.type.name)) {
                            return false
                        }

                        if (!types.has(node.type.name)) {
                            return
                        }

                        const current = level(node.attrs.arteIndent, settings.max)
                        const depth = next(current, settings)

                        if (depth === current) {
                            return
                        }

                        changed = true

                        if (dispatch) {
                            tr.setNodeMarkup(pos, undefined, { ...node.attrs, arteIndent: depth })
                        }
                    })

                    return changed
                }

            return {
                indentBlock: () => (props) => {
                    const item = itemAt(props.state.selection.$from)

                    return item
                        ? props.commands.sinkListItem(item)
                        : write((current, settings) => Math.min((current ?? 0) + 1, settings.max))(props)
                },

                outdentBlock: () => (props) => {
                    const item = itemAt(props.state.selection.$from)

                    if (item) {
                        return props.commands.liftListItem(item)
                    }

                    return write((current) => ((current ?? 0) - 1 < 1 ? null : current - 1))(props)
                },

                setBlockIndent: (depth) => write((current, settings) => level(depth, settings.max)),

                unsetBlockIndent: () => write(() => null),
            }
        },

        addKeyboardShortcuts() {
            // Handled whether or not anything moved. A handler that returns false leaves the
            // key to the browser, and on macOS Cmd+] and Cmd+[ are forward and back - so a
            // paragraph already at the deepest step would have navigated away from the draft
            // instead of standing still. The alignment extension is here for the same reason.
            return {
                'Mod-]': () => {
                    this.editor.commands.indentBlock()

                    return true
                },
                'Mod-[': () => {
                    this.editor.commands.outdentBlock()

                    return true
                },
            }
        },
    })
}
