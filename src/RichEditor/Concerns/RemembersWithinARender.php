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
 *
 * A field also opens a window of its own around work Filament does outside its windows but
 * in which nothing about the field can move: see `rememberingThroughout()`.
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
     * How many windows of the field's own are open, and what was answered inside them.
     */
    protected static int $windowsOfOurOwn = 0;

    /**
     * @var WeakMap<object, array<string, mixed>>|null
     */
    protected static ?WeakMap $answersWithinOurWindow = null;

    /**
     * Runs a piece of work with every answer remembered for its length, then forgets them.
     *
     * For what Filament does outside its own windows and in which this field's configuration
     * cannot change - filling a form, above all, which hydrates every field's state and asks
     * each one, through the state cast, whether it takes uploads, which pool answers for them
     * and which tools it has. Each of those questions built the whole toolbar, and building it
     * asked them again from inside: twelve times per editor, two thirds of a request for a
     * page of twenty. Nested windows share the outermost one's answers.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    protected function rememberingThroughout(Closure $work): mixed
    {
        if (static::$windowsOfOurOwn === 0) {
            static::$answersWithinOurWindow = new WeakMap;
        }

        static::$windowsOfOurOwn++;

        try {
            return $work();
        } finally {
            static::$windowsOfOurOwn--;

            if (static::$windowsOfOurOwn === 0) {
                static::$answersWithinOurWindow = null;
            }
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $answer
     * @return T
     */
    protected function rememberWithinRender(string $question, Closure $answer): mixed
    {
        if ((static::$windowsOfOurOwn > 0) && (static::$answersWithinOurWindow !== null)) {
            $answers = static::$answersWithinOurWindow[$this] ?? [];

            if (array_key_exists($question, $answers)) {
                return $answers[$question];
            }

            $value = $answer();

            static::$answersWithinOurWindow[$this] = [
                ...(static::$answersWithinOurWindow[$this] ?? []),
                $question => $value,
            ];

            return $value;
        }

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
