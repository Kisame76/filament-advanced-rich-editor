<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use WeakMap;

/**
 * What a page render has written out in full already, so the editors after the first can name
 * it instead of repeating it.
 *
 * Kept only while a *page* is rendered - a request that is not a Livewire update. Every editor
 * of such a response arrives with the one that carries what they share, so the browser always
 * finds it. An update is different: an editor that is already on the page is never drawn again
 * (it is `wire:ignore`), so the one the server makes carry something may not carry it in the
 * browser - and a field whose toolbar changed meanwhile would name what no editor there ever
 * had. An update therefore writes everything on every editor, which is what it did before any
 * of this, and a field drawn outside a render this class saw begin does the same.
 *
 * Each render of a component starts the count again, a page with several components keeps one
 * count each, and Octane's next request finds nothing of this one: the components are the keys,
 * and they are gone with it.
 */
final class RenderLedger
{
    /**
     * Per component whose page render is under way, what it has written out in full.
     *
     * @var WeakMap<object, array<string, true>>|null
     */
    private static ?WeakMap $written = null;

    /**
     * A render of this component begins. Called for every Livewire render, from the service
     * provider.
     */
    public static function rendering(object $component): void
    {
        self::$written ??= new WeakMap;

        if (app('livewire')->isLivewireRequest()) {
            unset(self::$written[$component]);

            return;
        }

        self::$written[$component] = [];
    }

    /**
     * Whether this render has to write what `$key` names in full: the first time it asks, and
     * every time where it shares nothing.
     */
    public static function writesOut(object $component, string $key): bool
    {
        if ((self::$written === null) || (! isset(self::$written[$component]))) {
            return true;
        }

        $written = self::$written[$component];

        if (isset($written[$key])) {
            return false;
        }

        $written[$key] = true;
        self::$written[$component] = $written;

        return true;
    }

    /**
     * The name a piece of markup or a setting is shared under: what it holds, hashed - so two
     * fields set up differently can never answer to the same name.
     */
    public static function keyFor(string $content): string
    {
        return substr(hash('xxh128', $content), 0, 16);
    }
}
