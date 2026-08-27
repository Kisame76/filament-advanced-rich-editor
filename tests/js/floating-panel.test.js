import { describe, expect, it } from 'vitest'
import { MARGIN, clampInto, cornerOf } from '../../resources/js/floating-panel.js'

/**
 * The geometry behind the small windows this package hangs off the body - the emoji picker
 * and the find bar.
 *
 * It is here rather than in either of them because both had it, and because it is the half
 * that can be got wrong without anyone noticing: a window placed past the edge of the
 * screen cannot be dragged back, and the only way to see that is to try it on a narrow
 * one. As numbers it takes four assertions.
 */

const viewport = (width, height) => ({ width, height })

describe('keeping a window on the screen', () => {
    it('leaves a position that already fits', () => {
        expect(clampInto(100, 300, 1000)).toBe(100)
    })

    it('pulls a window back from the far edge', () => {
        // 900 + 300 would end 200 past the right-hand side.
        expect(clampInto(900, 300, 1000)).toBe(1000 - 300 - MARGIN)
    })

    it('stops a window short of the near edge', () => {
        expect(clampInto(-40, 300, 1000)).toBe(MARGIN)
    })

    it('gives up on the far edge rather than the near one when nothing fits', () => {
        // A window wider than the screen has no position that satisfies both edges. The
        // near edge wins, because a window whose left-hand end is off the screen is one
        // that cannot be reached at all.
        expect(clampInto(0, 2000, 1000)).toBe(MARGIN)
    })
})

describe('the corner a window opens in', () => {
    const anchor = { top: 100, right: 800, bottom: 500, left: 200 }

    it('sits inside the top right of what it belongs to', () => {
        expect(cornerOf(anchor, { width: 384, height: 44 }, viewport(1200, 900))).toEqual({
            left: 800 - 384 - MARGIN,
            top: 100 + MARGIN,
        })
    })

    it('comes back onto a narrow screen instead of hanging off it', () => {
        expect(cornerOf(anchor, { width: 384, height: 44 }, viewport(500, 900)).left).toBe(
            500 - 384 - MARGIN,
        )
    })

    it('does not start above the screen when what it belongs to does', () => {
        // A field scrolled halfway out of the top of the window has a negative top, and a
        // panel placed at it would be unreachable.
        expect(cornerOf({ ...anchor, top: -300 }, { width: 384, height: 44 }, viewport(1200, 900)).top).toBe(
            MARGIN,
        )
    })
})
