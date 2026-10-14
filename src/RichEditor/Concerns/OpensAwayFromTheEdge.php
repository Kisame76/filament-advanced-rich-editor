<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Js;

/**
 * Turns a dropdown upwards when there is no room for it below, and caps it to the room it has.
 *
 * The measuring is `resources/js/menu-position.js`, and why it measures what it measures is
 * written there. This hands a menu the state that module reads and writes, and a
 * `positionMenu()` that passes the menu to it.
 *
 * It used to be the measuring itself, inlined into every menu's `x-data`: two dozen copies per
 * editor, a hundred kilobytes of every editor's markup, compiled by Alpine two dozen times.
 * `dropUp` is still reset here, before the module is asked, so a menu that was turned the last
 * time it opened does not open turned again while the module is being fetched.
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

        $module = Js::from($this->menuPositionScript())->toHtml();

        return <<<JS
            dropUp: false,
            menuUpClass: '{$class}',
            positionMenu() {
                this.dropUp = false
                import({$module}).then(({ positionMenu }) => positionMenu(this))
            },
            JS;
    }

    /**
     * Where the measuring module is served from, for a menu whose behaviour lives in a module
     * of its own and is handed the URL rather than the inline state above.
     */
    protected function menuPositionScript(): string
    {
        return FilamentAsset::getScriptSrc('advanced-rich-editor/menu-position', 'kisame76/filament-advanced-rich-editor');
    }
}
