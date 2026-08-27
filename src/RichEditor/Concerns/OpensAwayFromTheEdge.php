<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

/**
 * Turns a dropdown upwards when there is no room for it below.
 *
 * Every menu in this package hangs off its trigger with `position: absolute`, which is
 * fine in the middle of a page and wrong at the bottom of one. Two things cut a menu off
 * down there, and only one of them is the window: `.fi-fo-rich-editor-content` scrolls its
 * own overflow, so a menu opening near the foot of the editor is clipped by the editor
 * itself. The bar over a selection makes this the normal case rather than the edge case -
 * that bar hangs below the text it belongs to, so its menus start lower than any menu on
 * the toolbar ever does.
 *
 * Raising `z-index` does not help with either. A menu that reaches past the bottom of a
 * scrolling ancestor is clipped by geometry, and paint order has no say in it.
 *
 * The room is measured against whichever edge comes first - the window or the nearest
 * ancestor that clips - and measured once the menu exists rather than guessed from a
 * maximum height, because these lists are as long as a project's own configuration makes
 * them and a guess would turn a short menu that had room.
 *
 * Turning it over is only half the answer, and the half that is easy to stop at. A field
 * four hundred pixels tall with the bar over a selection somewhere in the middle of it has
 * under two hundred pixels above and under two hundred below, and a menu of seven languages
 * is taller than either - so there is no side to turn onto and the menu is cut off wherever
 * it goes. It is therefore also capped to the room it has, and scrolls inside that. A list
 * you have to scroll is a smaller problem than a list whose last three entries are behind
 * the edge of the field.
 */
trait OpensAwayFromTheEdge
{
    /**
     * The class the stylesheet hangs the turned state on.
     */
    public const MENU_UP_CLASS = 'fi-arte-menu-up';

    /**
     * The gap kept between a menu and the edge it was measured against, so a menu that only
     * just fits does not end up flush with the side of the field.
     */
    public const MENU_MARGIN = 8;

    /**
     * How short a capped menu is allowed to get. Below this it stops being a menu and
     * becomes a sliver with a scrollbar in it - at which point the honest thing is to
     * overhang a little and let the reader scroll the field.
     */
    public const MENU_MIN_HEIGHT = 96;

    /**
     * The Alpine state and the one method a component drops into its own `x-data`.
     *
     * Wants `x-ref="trigger"` on the button and `x-ref="menu"` on the menu, and expects the
     * component to call `positionMenu()` whenever it opens - composed into whatever else
     * that handler already does rather than replacing it.
     */
    protected function menuPositioning(): string
    {
        $class = static::MENU_UP_CLASS;

        $margin = static::MENU_MARGIN;
        $minimum = static::MENU_MIN_HEIGHT;

        return <<<JS
            dropUp: false,
            menuUpClass: '{$class}',
            positionMenu() {
                // Measured from the downward position, so a menu that is already turned
                // does not measure itself in the place the turning put it.
                this.dropUp = false

                this.\$nextTick(() => {
                    const menu = this.\$refs.menu
                    const trigger = this.\$refs.trigger?.getBoundingClientRect()

                    if (! trigger || ! menu) {
                        return
                    }

                    // Unconstrained first: a cap left behind by the last opening would be
                    // what gets measured, and the menu would ratchet shorter every time.
                    menu.style.maxHeight = ''
                    menu.style.overflowY = ''
                    menu.style.insetBlockStart = ''
                    menu.style.insetBlockEnd = ''

                    // `offsetHeight` rather than the bounding box: Filament's own menus open
                    // through a transition that scales them, and a box measured mid-scale is
                    // a menu measured smaller than it is.
                    const height = menu.offsetHeight

                    const clip = this.clippingRect(menu)
                    const floor = Math.min(window.innerHeight, clip.bottom)
                    const ceiling = Math.max(0, clip.top)

                    const below = floor - trigger.bottom - {$margin}
                    const above = trigger.top - ceiling - {$margin}

                    // Downwards unless it does not fit and there is more room the other way.
                    this.dropUp = height > below && above > below

                    if (height <= (this.dropUp ? above : below)) {
                        return
                    }

                    // Neither side of the trigger can hold it. Measuring one side of the
                    // trigger is the wrong question at that point: the box around it is
                    // usually roomy enough, the trigger just happens to sit in the middle
                    // of it. Squeezing the menu into the half it is on is what left this
                    // panel at a hundred pixels with its last control below the fold.
                    //
                    // So it is placed against the box instead, and capped to the box. It
                    // stops hanging off the trigger and starts being a menu that fits.
                    const wrapper = menu.offsetParent?.getBoundingClientRect()

                    if (! wrapper) {
                        menu.style.maxHeight = `\${Math.max(this.dropUp ? above : below, {$minimum})}px`
                        menu.style.overflowY = 'auto'

                        return
                    }

                    const box = floor - ceiling - 2 * {$margin}
                    const capped = Math.min(height, box)

                    // Against the nearer edge of the box, so it still reads as belonging to
                    // the thing that opened it rather than floating in the middle.
                    const top = this.dropUp
                        ? floor - {$margin} - capped
                        : ceiling + {$margin}

                    this.dropUp = false

                    menu.style.maxHeight = `\${Math.max(capped, {$minimum})}px`
                    menu.style.overflowY = capped < height ? 'auto' : ''
                    menu.style.insetBlockStart = `\${top - wrapper.top}px`
                    menu.style.insetBlockEnd = 'auto'
                })
            },
            // The nearest ancestor that would cut the menu off. The editor's own content
            // box is one of them, which is why the window alone is not enough to go on.
            clippingRect(element) {
                for (let node = element?.parentElement; node && node !== document.body; node = node.parentElement) {
                    const style = window.getComputedStyle(node)

                    if (style.overflow !== 'visible' || style.overflowY !== 'visible') {
                        return node.getBoundingClientRect()
                    }
                }

                return { top: 0, bottom: window.innerHeight }
            },
            JS;
    }
}
