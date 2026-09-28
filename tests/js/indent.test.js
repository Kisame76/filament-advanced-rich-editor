import { afterEach, describe, expect, it, vi } from 'vitest'
import indentExtension, {
    DEFAULT_MAX,
    DEFAULT_STEP,
    lengthOf,
    level,
    levelOf,
    max,
    pixels,
    readIndent,
    step,
} from '../../resources/js/indent.js'

/**
 * The functions the indent is measured with. Every one of them mirrors a static on
 * `TipTapExtensions/Indent`, and the two halves have to agree: one writes the length the
 * other reads back.
 */

describe('reading a length', () => {
    it('converts the units a document may be written in', () => {
        expect(pixels('40px')).toBe(40)
        expect(pixels('2.5rem')).toBe(40)
        expect(pixels('36pt')).toBe(48)
        expect(pixels('1in')).toBe(96)
        expect(pixels(' 1.27CM ')).toBeCloseTo(48, 5)
    })

    it('takes a bare zero and nothing else without a unit', () => {
        // The one length CSS lets stand unitless, and the one an editor writes for "none".
        expect(pixels('0')).toBe(0)
        expect(pixels('40')).toBeNull()
    })

    it('refuses what is not a length', () => {
        expect(pixels('50%')).toBeNull()
        expect(pixels('auto')).toBeNull()
        expect(pixels('inherit')).toBeNull()
        expect(pixels('5rem)')).toBeNull()
        expect(pixels('calc(2rem + 1px)')).toBeNull()
        expect(pixels(null)).toBeNull()
    })
})

describe('the step a field moves by', () => {
    it('reads a bare number as rem and canonicalises the spelling', () => {
        expect(step(2)).toBe('2rem')
        expect(step('2.50rem')).toBe('2.5rem')
        expect(step('40PX')).toBe('40px')
    })

    it('falls back to the shipped step rather than to nothing', () => {
        // A field whose step is nothing has two buttons that do nothing.
        for (const nonsense of ['50%', '0rem', '-2rem', 'inherit', '2', '', null, undefined]) {
            expect(step(nonsense)).toBe(DEFAULT_STEP)
        }
    })
})

describe('how deep a block may go', () => {
    it('answers an out-of-range depth with the shipped one', () => {
        // A configured `0` is the feature being switched off in the wrong place; clamping
        // it to `1` would answer a different question.
        expect(max(4)).toBe(4)
        expect(max('6')).toBe(6)
        expect(max(0)).toBe(DEFAULT_MAX)
        expect(max(41)).toBe(DEFAULT_MAX)
        expect(max(2.5)).toBe(DEFAULT_MAX)
        expect(max('lots')).toBe(DEFAULT_MAX)
    })

    it('holds a depth inside it, and reads nothing under one as no indent', () => {
        expect(level(3, 8)).toBe(3)
        expect(level('3', 8)).toBe(3)
        expect(level(99, 8)).toBe(8)
        expect(level(0, 8)).toBeNull()
        expect(level(-2, 8)).toBeNull()
        expect(level(1.5, 8)).toBeNull()
        expect(level(null, 8)).toBeNull()
    })
})

describe('the length a depth writes', () => {
    it('multiplies the step and keeps its unit', () => {
        expect(lengthOf(1, '2.5rem')).toBe('2.5rem')
        expect(lengthOf(3, '2.5rem')).toBe('7.5rem')
        expect(lengthOf(2, '40px')).toBe('80px')
    })

    it('spells a number the one way PHP spells it', () => {
        // Both halves compare these strings; `1.27 * 3` is 3.8099999999999996 in binary and
        // has to come out as it does on the other side.
        expect(lengthOf(3, '1.27cm')).toBe('3.81cm')
        expect(lengthOf(2, '2.5rem')).toBe('5rem')
    })
})

describe('reading a length back as a depth', () => {
    it('rounds to the nearest whole step', () => {
        expect(levelOf('7.5rem', '2.5rem', 8)).toBe(3)
        // 36pt is 48px, a step and a fifth - near enough to one that somebody meant one.
        expect(levelOf('36pt', '2.5rem', 8)).toBe(1)
        // Half a step goes up, the way PHP's own `round` does for a positive number.
        expect(levelOf('1.25rem', '2.5rem', 8)).toBe(1)
    })

    it('reads nothing under half a step as no indent at all', () => {
        expect(levelOf('1rem', '2.5rem', 8)).toBeNull()
        expect(levelOf('0', '2.5rem', 8)).toBeNull()
        expect(levelOf('auto', '2.5rem', 8)).toBeNull()
    })

    it('holds a document inside the depth the field allows', () => {
        expect(levelOf('100rem', '2.5rem', 8)).toBe(8)
    })

    it('re-measures onto the grid the reading field uses', () => {
        expect(levelOf('5rem', '2rem', 8)).toBe(3)
    })
})

describe('reading it off an element', () => {
    const paragraph = (style) => {
        const element = document.createElement('p')

        element.setAttribute('style', style)

        return element
    }

    it('prefers the logical property, which is the one this package writes', () => {
        expect(readIndent(paragraph('margin-inline-start: 5rem'), '2.5rem', 8)).toBe(2)
        expect(
            readIndent(paragraph('margin-inline-start: 5rem; margin-left: 20rem'), '2.5rem', 8),
        ).toBe(2)
    })

    it('reads margin-left too, because it is what every other editor writes', () => {
        expect(readIndent(paragraph('margin-left: 5rem'), '2.5rem', 8)).toBe(2)
    })

    it('answers nothing for an element carrying no indent', () => {
        expect(readIndent(paragraph('color: red'), '2.5rem', 8)).toBeNull()
        expect(readIndent(null, '2.5rem', 8)).toBeNull()
    })
})

/**
 * The extension. What is only provable with an editor: which settings it reads, what it
 * writes into the schema, and which of the two meanings the buttons take.
 */

const build = () => {
    window.FilamentRichEditor = {
        tiptap: { core: { Extension: { create: (definition) => definition } } },
    }

    return indentExtension()
}

const fakeEditor = (settings) => {
    const element = document.createElement('div')

    if (settings !== null) {
        element.dataset.arteIndent = settings
    }

    return { options: { element } }
}

const attributeOf = (definition, editor) => {
    const context = { options: definition.addOptions(), editor }

    return definition.addGlobalAttributes.call(context)[0].attributes.arteIndent
}

afterEach(() => {
    delete window.FilamentRichEditor
})

describe('the attribute it declares', () => {
    it('sits on the blocks that can hold an indent and on no list item', () => {
        const definition = build()
        const groups = definition.addGlobalAttributes.call({
            options: definition.addOptions(),
            editor: fakeEditor(null),
        })

        expect(groups[0].types).toEqual(['paragraph', 'heading', 'blockquote'])
        expect(definition.addOptions().itemTypes).toEqual(['listItem', 'taskItem'])
    })

    it('reads the field settings where it is used, not where it was made', () => {
        // The initial content is parsed while the editor is being constructed, which is
        // before `onCreate()` runs - a field whose step is not the shipped one would
        // otherwise read its first document onto the wrong grid.
        const attribute = attributeOf(build(), fakeEditor('{"step":"2rem","max":3}'))
        const paragraph = document.createElement('p')

        paragraph.setAttribute('style', 'margin-inline-start: 4rem')

        expect(attribute.parseHTML(paragraph)).toBe(2)
        expect(attribute.renderHTML({ arteIndent: 9 })).toEqual({
            style: 'margin-inline-start: 6rem',
        })
    })

    it('falls back to the shipped settings when the element carries none', () => {
        const attribute = attributeOf(build(), fakeEditor(null))

        expect(attribute.renderHTML({ arteIndent: 2 })).toEqual({
            style: 'margin-inline-start: 5rem',
        })
        expect(attribute.renderHTML({ arteIndent: null })).toEqual({})
    })

    it('says so rather than throwing when the settings are not readable', () => {
        const complaint = vi.spyOn(console, 'error').mockImplementation(() => {})
        const attribute = attributeOf(build(), fakeEditor('{not json'))

        expect(attribute.renderHTML({ arteIndent: 1 })).toEqual({
            style: `margin-inline-start: ${DEFAULT_STEP}`,
        })
        expect(complaint).toHaveBeenCalled()

        complaint.mockRestore()
    })
})

describe('the two moves', () => {
    const inside = (...ancestors) => ({
        depth: ancestors.length,
        node: (depth) => ({ type: { name: ancestors[depth - 1] } }),
    })

    const fakeState = (nodes, $from) => ({
        schema: { nodes: { paragraph: {}, heading: {}, blockquote: {}, listItem: {} } },
        selection: { from: 0, to: 100, $from },
        doc: {
            // `false` from the visitor stops the walk descending into that node and carries
            // on with its siblings, which is what ProseMirror's own `nodesBetween` does -
            // and the whole of what the list guard relies on.
            nodesBetween: (from, to, visit) => {
                const walk = (children) => {
                    for (const node of children) {
                        if (visit(node, node.pos) !== false) {
                            walk(node.children ?? [])
                        }
                    }
                }

                walk(nodes)
            },
        },
    })

    const run = (command, { nodes = [], $from = inside('paragraph'), settings = null } = {}) => {
        const definition = build()
        const editor = fakeEditor(settings)
        const commands = { sinkListItem: vi.fn(() => true), liftListItem: vi.fn(() => true) }
        const written = []
        const state = fakeState(nodes, $from)

        const result = definition.addCommands
            .call({ options: definition.addOptions(), editor })
            [command]()({
                state,
                tr: {
                    setNodeMarkup: (pos, type, attrs) => written.push([pos, attrs.arteIndent]),
                },
                dispatch: true,
                editor,
                commands,
            })

        return { result, written, commands }
    }

    const paragraph = (arteIndent, pos = 0) => ({
        type: { name: 'paragraph' },
        attrs: { arteIndent },
        pos,
    })

    it('steps a block in and back out', () => {
        expect(run('indentBlock', { nodes: [paragraph(null)] }).written).toEqual([[0, 1]])
        expect(run('indentBlock', { nodes: [paragraph(2)] }).written).toEqual([[0, 3]])
        expect(run('outdentBlock', { nodes: [paragraph(2)] }).written).toEqual([[0, 1]])
    })

    it('takes the attribute off rather than writing a zero', () => {
        expect(run('outdentBlock', { nodes: [paragraph(1)] }).written).toEqual([[0, null]])
    })

    it('stands still at either end and says it did nothing', () => {
        expect(run('outdentBlock', { nodes: [paragraph(null)] }).result).toBe(false)
        expect(run('indentBlock', { nodes: [paragraph(8)] }).result).toBe(false)
    })

    it('hands a selection inside a list to the list', () => {
        // A list indents by nesting, which is where its numbering and bullets come from,
        // and a margin beside that would be a second indent the list knows nothing about.
        const { commands, written } = run('indentBlock', {
            nodes: [paragraph(null)],
            $from: inside('bulletList', 'listItem', 'paragraph'),
        })

        expect(commands.sinkListItem).toHaveBeenCalledWith('listItem')
        expect(written).toEqual([])

        const out = run('outdentBlock', {
            nodes: [paragraph(null)],
            $from: inside('taskList', 'taskItem', 'paragraph'),
        })

        expect(out.commands.liftListItem).toHaveBeenCalledWith('taskItem')
    })

    it('skips a list item and everything under it, inside a wider selection', () => {
        // A selection spanning a paragraph and a list would otherwise put a margin on the
        // paragraphs inside the list items as well: two indents on one line, one of which
        // the list did not ask for. The paragraph beside the list still moves.
        const { written } = run('indentBlock', {
            nodes: [
                {
                    type: { name: 'listItem' },
                    attrs: {},
                    pos: 0,
                    children: [paragraph(null, 1)],
                },
                paragraph(null, 9),
            ],
        })

        expect(written).toEqual([[9, 1]])
    })

    it('writes only the types that declared the attribute', () => {
        // TipTap's own attribute commands walk every node in the selection, including the
        // ones that never declared it, where ProseMirror then throws.
        const { written } = run('indentBlock', {
            nodes: [{ type: { name: 'image' }, attrs: {}, pos: 0 }, paragraph(null, 5)],
        })

        expect(written).toEqual([[5, 1]])
    })
})

describe('the two keys', () => {
    it('swallows them whether or not anything moved', () => {
        // A handler that returns false leaves the key to the browser, and on macOS Cmd+]
        // and Cmd+[ are forward and back - a paragraph at the deepest step would have
        // navigated away from the draft instead of standing still.
        const definition = build()
        const commands = { indentBlock: vi.fn(() => false), outdentBlock: vi.fn(() => false) }
        const shortcuts = definition.addKeyboardShortcuts.call({ editor: { commands } })

        expect(shortcuts['Mod-]']()).toBe(true)
        expect(shortcuts['Mod-[']()).toBe(true)
        expect(commands.indentBlock).toHaveBeenCalled()
        expect(commands.outdentBlock).toHaveBeenCalled()
    })
})
