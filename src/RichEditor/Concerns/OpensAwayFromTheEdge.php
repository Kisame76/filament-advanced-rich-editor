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
 */
trait OpensAwayFromTheEdge
{
    /**
     * The class the stylesheet hangs the turned state on.
     */
    public const MENU_UP_CLASS = 'fi-arte-menu-up';

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

        return <<<JS
            dropUp: false,
            menuUpClass: '{$class}',
            positionMenu() {
                // Measured from the downward position, so a menu that is already turned
                // does not measure itself in the place the turning put it.
                this.dropUp = false

                this.\$nextTick(() => {
                    const trigger = this.\$refs.trigger?.getBoundingClientRect()
                    const menu = this.\$refs.menu?.getBoundingClientRect()

                    if (! trigger || ! menu) {
                        return
                    }

                    const clip = this.clippingRect(this.\$refs.menu)
                    const floor = Math.min(window.innerHeight, clip.bottom)
                    const ceiling = Math.max(0, clip.top)

                    this.dropUp =
                        trigger.bottom + menu.height > floor &&
                        trigger.top - menu.height > ceiling
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
