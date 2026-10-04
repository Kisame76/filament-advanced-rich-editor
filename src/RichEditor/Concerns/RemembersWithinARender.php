<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Closure;
use Filament\Schemas\Components\Component;
use WeakMap;

/**
 * Answers that hold for one render, worked out once in it.
 *
 * Filament asks a field the same things over and over - every tool, plugin and helper wants
 * to know about the bar - and evaluates the field's configuration again for each question.
 * That is right across requests, where the state moves, and wasteful within one render, where
 * it does not: a page of five editors in a repeater resolved the whole toolbar three hundred
 * and seventy-five times, and spent most of its request doing it.
 *
 * Filament's own answer to the same problem is its visibility cache, switched on while a
 * schema renders, validates or takes a snapshot and emptied when that is done. This keeps to
 * exactly those windows. Inside one, an answer is worked out once per field; outside one,
 * every time, as before. A closure that reads the state is therefore asked again in the next
 * window, which is the next time the state can have changed.
 *
 * Held in a WeakMap rather than on the field. Repeaters and custom block modals clone fields,
 * and a property would be copied into every clone with the original's answer in it; an entry
 * in the map belongs to its object and goes when the object does.
 */
trait RemembersWithinARender
{
    /**
     * Written into Filament's visibility cache to tell one window from the next. Switching the
     * cache on empties it, so a missing marker means whatever is held is an earlier window's.
     */
    protected static string $renderWindowMarker = 'kisame76/filament-advanced-rich-editor:render-window';

    /**
     * @var WeakMap<object, array<string, mixed>>|null
     */
    protected static ?WeakMap $answersWithinRender = null;

    /**
     * @template T
     *
     * @param  Closure(): T  $answer
     * @return T
     */
    protected function rememberWithinRender(string $question, Closure $answer): mixed
    {
        if (! Component::isVisibilityCacheEnabled()) {
            return $answer();
        }

        if ((static::$answersWithinRender === null) || (! Component::hasVisibilityCacheKey(static::$renderWindowMarker))) {
            static::$answersWithinRender = new WeakMap;

            Component::setVisibilityCacheValue(static::$renderWindowMarker, true);
        }

        $answers = static::$answersWithinRender[$this] ?? [];

        if (array_key_exists($question, $answers)) {
            return $answers[$question];
        }

        $value = $answer();

        // Read again rather than reusing the copy above: working the answer out can remember
        // other answers for this field on the way.
        static::$answersWithinRender[$this] = [
            ...(static::$answersWithinRender[$this] ?? []),
            $question => $value,
        ];

        return $value;
    }
}
