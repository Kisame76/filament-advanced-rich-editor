import { describe, expect, it } from 'vitest'
import { handlePosition, reusesBlock, visibleWindow } from '../../resources/js/drag-handle.js'

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

const rect = ({ top = 0, height = 24, left = 100, width = 600, bottom = null }) => ({
    top,
    height,
    left,
    right: left + width,
    bottom: bottom ?? top + height,
})

const editor = rect({ top: 0, height: 400, left: 80, width: 640 })

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

    it('sits beside the start of the block and not beside its left edge', () => {
        // A field written right to left starts at the other end, and a handle placed by the
        // left edge would sit the width of the field away from the block it belongs to.
        const block = rect({ top: 100, left: 200, width: 400 })

        expect(handlePosition(block, editor, { width: 44, gap: 6, rtl: true }).left).toBe(606)
        expect(handlePosition(block, editor, { width: 44, gap: 6, rtl: false }).left).toBe(150)
    })

    it('never leaves the editor on that side either', () => {
        const block = rect({ top: 100, left: 100, width: 616 })

        expect(handlePosition(block, editor, { width: 44, gap: 6, margin: 2, rtl: true }).left)
            .toBe(674)
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

    it('holds the handle at the top of the field once the start of a block has scrolled out', () => {
        // A tall block - a table, a long quotation - can be mostly in view with its first
        // line already above the top of the field. Following that line up would draw the
        // handle over the toolbar; holding it at the top keeps it beside the block.
        const tall = rect({ top: -100, height: 300 })

        expect(handlePosition(tall, editor, { height: 24, lineHeight: 24 }).top).toBe(0)
    })

    it('lets go of a block that shows less of itself than the handle is tall', () => {
        // Held at the top any longer, the handle would sit beside the block after it.
        expect(handlePosition(rect({ top: -100, height: 110 }), editor, { height: 24 })).toBeNull()
        expect(handlePosition(rect({ top: -100, height: 124 }), editor, { height: 24 })).not.toBeNull()
    })
})

describe('the window the document is seen through', () => {
    it('is the document where the document scrolls inside its box', () => {
        // `maxHeight()` caps the document, not the box around it - and the top of that box
        // is right under the toolbar. The case that was measured: the box began where the
        // toolbar ended, at 278, and the document twelve pixels lower.
        const box = rect({ top: 278, height: 520, left: 80, width: 640 })
        const page = rect({ top: 290, height: 496, left: 100, width: 600 })

        expect(visibleWindow(box, page)).toEqual({ left: 80, right: 720, top: 290, bottom: 786 })

        // A block whose first line had scrolled up to 258 drew the handle across the bar.
        const block = rect({ top: 246, height: 200 })

        expect(handlePosition(block, visibleWindow(box, page), { height: 24, lineHeight: 48 }).top).toBe(290)
    })

    it('is the box where the box scrolls and the document runs past it', () => {
        // Fullscreen: the overlay's box scrolls, and the document is as long as it is.
        const box = rect({ top: 0, height: 900, left: 80, width: 640 })
        const page = rect({ top: -500, height: 3000, left: 100, width: 600 })

        expect(visibleWindow(box, page)).toEqual({ left: 80, right: 720, top: 0, bottom: 900 })
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
