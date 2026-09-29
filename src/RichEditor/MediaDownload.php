<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Illuminate\Support\Js;

/**
 * The Download button on the bar over a picture, a card, a film or a sound.
 *
 * One handler for all of them, written once, because they are one action: take the address
 * the selected node points at and save what is there. What differs is only the name it is
 * saved under - a card knows its own, `Quartalsbericht Q3.pdf`, and everything else is named
 * after the last part of its address.
 *
 * An anchor with `download` rather than a request of its own: the browser honours it for a
 * file on this host, and opens a file on another one - a signed S3 address, a CDN - in a new
 * tab instead, which is the most anybody can do for a file somebody else serves.
 */
class MediaDownload
{
    public static function handler(string $node): string
    {
        $node = Js::from($node)->toHtml();

        return <<<JS
            (() => {
                const attributes = \$getEditor()?.getAttributes({$node}) ?? {}
                const source = attributes.src

                if (! source) {
                    return
                }

                let name = attributes.name

                if (! name) {
                    try {
                        name = decodeURIComponent(source.split('#')[0].split('?')[0].split('/').pop() ?? '')
                    } catch {
                        // A percent sign that is not an escape. The browser names it instead.
                        name = ''
                    }
                }

                const link = document.createElement('a')
                link.href = source
                link.download = name
                link.target = '_blank'
                link.rel = 'noopener'

                document.body.appendChild(link)
                link.click()
                link.remove()
            })()
            JS;
    }
}
