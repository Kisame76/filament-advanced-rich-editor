import { describe, expect, it } from 'vitest'
import { handlePosition, reusesBlock } from '../../resources/js/drag-handle.js'

/**
 * The grip in the margin.
 *
 * Almost all of this feature is ProseMirror's: it knows which node a point is inside,
 * where a slice may land and how to draw the line saying so, and a drag begins by handing
 * it a selection and getting out of the way. What is left over is what this asserts -
 * where the handle goes, and whether the plus makes a block or uses the one it was pressed
 * on. Both are functions over plain data, and the rest is wiring only an editor can prove.
 *
 * Rectangles are viewport coordinates, the way `getBoundingClientRect()` gives them.
 */

const rect = ({ top = 0, height = 24, left = 100, bottom = null }) => ({
    top,
    height,
    left,
    bottom: bottom ?? top + height,
})

const editor = rect({ top: 0, height: 400, left: 80 })

describe('where the handle goes', () => {
    it('sits in the margin, clear of the text', () => {
        const where = handlePosition(rect({ top: 100, left: 200 }), editor, { width: 44, gap: 6 })

        expect(where.left).toBe(150)
    })

    it('is centred on the first line rather than on the block', () => {
        // A handle centred on a paragraph of nine lines sits halfway down the text, which
        // reads as belonging to the middle of it rather than to the block.
        const tall = rect({ top: 100, height: 216 })

        expect(handlePosition(tall, editor, { height: 24, lineHeight: 24 }).top).toBe(100)

        // And a one-line block is its own first line.
        expect(handlePosition(rect({ top: 100, height: 24 }), editor, { height: 24 }).top).toBe(100)
    })

    it('centres on a line taller than the handle', () => {
        const heading = rect({ top: 100, height: 40 })

        expect(handlePosition(heading, editor, { height: 24, lineHeight: 40 }).top).toBe(108)
    })

    it('never leaves the editor, however far the text is indented', () => {
        // A blockquote or a nested list starts well inside the box. The handle would rather
        // sit on the edge of the field than outside it, next to a different field.
        const indented = rect({ top: 100, left: 90 })

        expect(handlePosition(indented, editor, { width: 44, gap: 6, margin: 2 }).left).toBe(82)
    })

    it('says nothing for a block scrolled out of the field', () => {
        // `maxHeight()` makes the editor its own window, so a block can be above or below
        // what is visible while the mouse is still inside the box - and a handle drawn for
        // it would float over the toolbar.
        expect(handlePosition(rect({ top: 420, height: 24 }), editor)).toBeNull()
        expect(handlePosition(rect({ top: -40, height: 24 }), editor)).toBeNull()
    })

    it('keeps a block that is only half in view', () => {
        expect(handlePosition(rect({ top: 390, height: 24 }), editor)).not.toBeNull()
    })
})

describe('what the plus does', () => {
    const node = (name, size) => ({ type: { name }, content: { size } })

    it('uses an empty paragraph rather than making a second one', () => {
        // Pressing plus on the empty line somebody just made and getting another empty line
        // under it is the kind of thing that is obvious once and irritating afterwards.
        expect(reusesBlock(node('paragraph', 0))).toBe(true)
    })

    it('makes a new block below anything that is holding something', () => {
        expect(reusesBlock(node('paragraph', 5))).toBe(false)
        expect(reusesBlock(node('heading', 0))).toBe(false)
        expect(reusesBlock(node('horizontalRule', 0))).toBe(false)
        expect(reusesBlock(null)).toBe(false)
    })
})
