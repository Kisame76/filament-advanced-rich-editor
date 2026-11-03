<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

/**
 * Markup every editor of a page draws the same, sent once a page and stamped out per editor.
 *
 * The bars that float over a selection, a picture, a table or a list are identical for every
 * field set up the same way, byte for byte - 82 KB per editor, 40 % of everything an editor
 * writes, and none of it seen until somebody selects something. gzip cannot help: its window
 * ends long before the next editor repeats them.
 *
 * So the markup goes into a `<template>`, in full on the first editor of a page and empty but
 * named on the rest. As Alpine reaches a template, its `x-init` stamps out a copy right after
 * it and hands the copy to Alpine - from its own content, from the page's store, or from the
 * template that carries it - before the editor's script asks for any of the bars by `x-ref`:
 * that happens once its extensions have loaded, which is always later. When a page is and is
 * not shared is `RenderLedger`'s: an update carries everything on every editor.
 *
 * The stamp is cleared out first if one is already there, which is what a page brought back
 * by `wire:navigate` from its snapshot has - a copy of the DOM after Alpine had been at it.
 */
final class SharedMarkup
{
    /**
     * What the template runs as Alpine reaches it. Backticks rather than quotes, so that next
     * to nothing in it has to be escaped inside the attribute.
     */
    private const STAMP = <<<'JS'
        (() => {
            const key = $el.dataset.arteMarkup;
            const store = (window.arteSharedMarkup ??= {});
            if ($el.content.firstElementChild) store[key] = $el.content;
            const source = store[key] ?? [...document.querySelectorAll(`template[data-arte-markup='${key}']`)].find((template) => template.content.firstElementChild)?.content;
            if (! source) return console.error(`The advanced rich editor could not find the markup it shares as ${key}.`);
            store[key] = source;
            while ($el.nextElementSibling?.hasAttribute(`data-arte-stamped`)) $el.nextElementSibling.remove();
            const stamp = source.firstElementChild.cloneNode(true);
            $el.after(stamp);
            Alpine.initTree(stamp);
        })()
        JS;

    /**
     * The template for this markup: carrying it where this page has not written it before, and
     * only naming it everywhere else.
     */
    public static function template(object $component, string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $key = RenderLedger::keyFor($html);

        $content = RenderLedger::writesOut($component, $key)
            ? '<div data-arte-stamped style="display: contents">'.$html.'</div>'
            : '';

        return '<template data-arte-markup="'.$key.'" x-init="'.e(self::stamp()).'">'.$content.'</template>';
    }

    /**
     * The stamping script on one line, the indentation of the heredoc gone.
     */
    private static function stamp(): string
    {
        return (string) preg_replace('/\s*\n\s*/', ' ', self::STAMP);
    }
}
