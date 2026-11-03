<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\View\Components;

use Filament\Support\Facades\FilamentAsset;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The script and the stylesheet the lightbox needs, once a page.
 *
 *     <x-arte-lightbox />
 *
 * For a page that prints rendered content itself; `<x-arte-content lightbox>` brings this
 * along on its own. Anywhere on the page, as often as there are documents on it - the second
 * and every later one print nothing. The files are Filament assets, so they are where
 * `php artisan filament:assets` put them.
 *
 * The close button is named here, in the language of the request, and handed to the script on
 * its own tag: the links around the pictures cannot carry it, since the sanitiser keeps only a
 * few named `data-` attributes.
 */
class Lightbox extends Component
{
    public const PACKAGE = 'kisame76/filament-advanced-rich-editor';

    public function render(): View
    {
        return view('filament-advanced-rich-editor::components.lightbox', [
            'script' => FilamentAsset::getScriptSrc('advanced-rich-editor/lightbox', static::PACKAGE),
            'style' => FilamentAsset::getStyleHref('filament-advanced-rich-editor-lightbox', static::PACKAGE),
            'close' => (string) __('filament-advanced-rich-editor::advanced-rich-editor.lightbox.close'),
        ]);
    }
}
