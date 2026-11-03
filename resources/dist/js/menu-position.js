/**
 * Turns a dropdown upwards when there is no room for it below, and caps it to the room it has.
 *
 * Every menu in this package hangs off its trigger with `position: absolute`, which is fine in
 * the middle of a page and wrong at the bottom of one. Two things cut a menu off down there,
 * and only one of them is the window: `.fi-fo-rich-editor-content` scrolls its own overflow,
 * so a menu opening near the foot of the editor is clipped by the editor itself. The bar over
 * a selection makes this the normal case rather than the edge case - that bar hangs below the
 * text it belongs to, so its menus start lower than any menu on the toolbar ever does.
 *
 * Raising `z-index` does not help with either. A menu that reaches past the bottom of a
 * scrolling ancestor is clipped by geometry, and paint order has no say in it.
 *
 * One module rather than a method in every menu's `x-data`, which is where this lived: two
 * dozen copies of it per editor, a hundred kilobytes of every editor's markup. The menus keep
 * their state - `dropUp` - and hand themselves to `positionMenu()` when they open.
 */

/**
 * The gap kept between a menu and the edge it was measured against, so a menu that only just
 * fits does not end up flush with the side of the field.
 */
export const MARGIN = 8

/**
 * How short a capped menu is allowed to get. Below this it stops being a menu and becomes a
 * sliver with a scrollbar in it - at which point the honest thing is to overhang a little and
 * let the reader scroll the field.
 */
export const MIN_HEIGHT = 96

/**
 * Measures the room around a menu that has just opened, and turns or caps it.
 *
 * `component` is the Alpine component the menu belongs to: it carries `dropUp`, the
 * `trigger` and `menu` refs, and `$nextTick`.
 */
export function positionMenu(component) {
    // Measured from the downward position, so a menu that is already turned does not measure
    // itself in the place the turning put it.
    component.dropUp = false

    component.$nextTick(() => place(component))
}

function place(component) {
    const menu = component.$refs.menu
    const trigger = component.$refs.trigger?.getBoundingClientRect()

    if (!trigger || !menu) {
        return
    }

    // Unconstrained first: a cap left behind by the last opening would be what gets measured,
    // and the menu would ratchet shorter every time.
    menu.style.maxHeight = ''
    menu.style.overflowY = ''
    menu.style.insetBlockStart = ''
    menu.style.insetBlockEnd = ''
    menu.style.insetInlineStart = ''

    if (alignWithTrigger(menu, trigger)) {
        follow(component)
    }

    // `offsetHeight` rather than the bounding box: Filament's own menus open through a
    // transition that scales them, and a box measured mid-scale is a menu measured smaller
    // than it is.
    const height = menu.offsetHeight

    // The part of the page that can be seen, which with a phone's keyboard up is not the
    // window: the keyboard covers the bottom of it, and a menu measured against the window
    // would open into the keys.
    const visible = window.visualViewport
    const clip = clippingRect(menu)
    const floor = Math.min(window.innerHeight, visible ? visible.offsetTop + visible.height : Number.POSITIVE_INFINITY, clip.bottom)
    const ceiling = Math.max(0, visible?.offsetTop ?? 0, clip.top)

    const below = floor - trigger.bottom - MARGIN
    const above = trigger.top - ceiling - MARGIN

    // Downwards unless it does not fit and there is more room the other way.
    component.dropUp = height > below && above > below

    if (height <= (component.dropUp ? above : below)) {
        return
    }

    // Neither side of the trigger can hold it. Measuring one side of the trigger is the wrong
    // question at that point: the box around it is usually roomy enough, the trigger just
    // happens to sit in the middle of it. Squeezing the menu into the half it is on is what
    // left this panel at a hundred pixels with its last control below the fold.
    //
    // So it is placed against the box instead, and capped to the box. It stops hanging off the
    // trigger and starts being a menu that fits.
    const wrapper = menu.offsetParent?.getBoundingClientRect()

    if (!wrapper) {
        menu.style.maxHeight = `${Math.max(component.dropUp ? above : below, MIN_HEIGHT)}px`
        menu.style.overflowY = 'auto'

        return
    }

    const box = floor - ceiling - 2 * MARGIN
    const capped = Math.min(height, box)

    // Against the nearer edge of the box, so it still reads as belonging to the thing that
    // opened it rather than floating in the middle.
    const top = component.dropUp ? floor - MARGIN - capped : ceiling + MARGIN

    component.dropUp = false

    menu.style.maxHeight = `${Math.max(capped, MIN_HEIGHT)}px`
    menu.style.overflowY = capped < height ? 'auto' : ''
    menu.style.insetBlockStart = `${top - wrapper.top}px`
    menu.style.insetBlockEnd = 'auto'
}

/**
 * Lines a menu up under its trigger where it is not positioned against the trigger's own box,
 * and says whether it had to.
 *
 * That is the toolbar on a touch screen. Its row scrolls sideways there, and a menu positioned
 * inside a scrolling row is cut off by it - so the stylesheet takes the position off the
 * dropdown's own box, and the menu is placed against the bar instead, which does not scroll.
 * Its `inset-inline-start: 0` then means the start of the bar, so the trigger's offset is
 * written in, kept inside the window.
 */
function alignWithTrigger(menu, trigger) {
    const box = menu.offsetParent

    if (!box || box === menu.parentElement || !box.contains(menu)) {
        return false
    }

    const outer = box.getBoundingClientRect()
    const width = menu.offsetWidth
    const room = document.documentElement.clientWidth || window.innerWidth
    const clamp = (value, low, high) => Math.max(low, Math.min(value, high))

    menu.style.insetInlineStart =
        window.getComputedStyle(box).direction === 'rtl'
            ? `${outer.right - clamp(trigger.right, MARGIN + width, room - MARGIN)}px`
            : `${clamp(trigger.left, MARGIN, room - MARGIN - width) - outer.left}px`

    return true
}

/**
 * Keeps a menu under its trigger while the row the trigger sits in scrolls - a finger that
 * swipes the bar with a menu open would otherwise leave the menu behind. Lets go of the row
 * once the menu has closed.
 */
function follow(component) {
    const { menu, trigger } = component.$refs
    let row = null

    for (let node = trigger.parentElement; node && node !== menu.offsetParent; node = node.parentElement) {
        if (/(auto|scroll)/.test(window.getComputedStyle(node).overflowX)) {
            row = node

            break
        }
    }

    if (!row || menu._arteFollowing) {
        return
    }

    const onScroll = () => {
        if (!menu.isConnected || menu.offsetParent === null) {
            row.removeEventListener('scroll', onScroll)
            menu._arteFollowing = false

            return
        }

        alignWithTrigger(menu, trigger.getBoundingClientRect())
    }

    menu._arteFollowing = true
    row.addEventListener('scroll', onScroll, { passive: true })
}

/**
 * The nearest ancestor that would cut the menu off. The editor's own content box is one of
 * them, which is why the window alone is not enough to go on.
 *
 * Looked for from the box the menu is positioned against rather than from its parent: an
 * ancestor in between does not cut a positioned menu off, however it scrolls - which is the
 * whole of how a menu gets out of the toolbar's sideways scrolling row on a touch screen.
 */
function clippingRect(element) {
    const box = element?.offsetParent?.contains(element) ? element.offsetParent : element?.parentElement

    for (let node = box; node && node !== document.body; node = node.parentElement) {
        const style = window.getComputedStyle(node)

        if (style.overflow !== 'visible' || style.overflowY !== 'visible') {
            return node.getBoundingClientRect()
        }
    }

    return { top: 0, bottom: window.innerHeight }
}
