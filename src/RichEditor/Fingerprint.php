<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use BackedEnum;
use Closure;
use ReflectionFunction;
use Throwable;
use Tiptap\Core\Extension;
use UnitEnum;

/**
 * One short string for a pile of configuration, so two renders can be told apart without
 * running either of them.
 *
 * This exists for the render cache and for nothing else. The question it answers is
 * narrow: would these two renderers produce the same markup? A hash of the content alone
 * cannot answer it - the same document rendered with anchors, with a code theme or with a
 * different set of named styles is a different page - so what goes in is the content plus
 * everything the renderer was told.
 *
 * Two rules make the walk cheap and deterministic:
 *
 *  - A closure is its file and line. Two renders configured by the same code get the same
 *    print; a closure that closes over a changing value does not, and that is the limit
 *    named in `AdvancedRichContentRenderer::cached()`.
 *  - A stranger's object is its class name and no more. A mention provider may hold a
 *    query builder, a container or a whole model, and walking into one of those to
 *    fingerprint a render would cost more than the render. What such an object answers
 *    with is by definition not knowable without asking it.
 */
class Fingerprint
{
    /**
     * How deep the walk goes before it stops describing and starts summarising. A cycle
     * between two objects is the case this is really for; eight levels is deeper than any
     * configuration in this package goes.
     */
    protected const MAX_DEPTH = 8;

    public static function of(mixed $value): string
    {
        $stabilised = json_encode(
            static::stabilise($value),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE,
        );

        // xxh128 rather than a cryptographic hash: nothing here is a secret, and this runs
        // once per render on a string as long as the document.
        return hash('xxh128', $stabilised === false ? serialize(true) : $stabilised);
    }

    protected static function stabilise(mixed $value, int $depth = 0): mixed
    {
        if ($depth >= static::MAX_DEPTH) {
            return '…';
        }

        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            $stabilised = [];

            foreach ($value as $key => $item) {
                $stabilised[$key] = static::stabilise($item, $depth + 1);
            }

            return $stabilised;
        }

        if ($value instanceof Closure) {
            return static::ofClosure($value);
        }

        if ($value instanceof BackedEnum) {
            return ['enum', $value::class, $value->value];
        }

        if ($value instanceof UnitEnum) {
            return ['enum', $value::class, $value->name];
        }

        // An extension is the one kind of object worth opening: its options carry the
        // styles, the protocols and the colours a render is drawn with, and two renders
        // that differ only there differ on the page.
        if ($value instanceof Extension) {
            return ['extension', $value::class, $value::$name, static::stabilise($value->options, $depth + 1)];
        }

        if (is_object($value)) {
            return ['object', $value::class];
        }

        // A resource, or whatever else PHP grows next. It is not configuration.
        return ['other', gettype($value)];
    }

    /**
     * @return array<int, mixed>
     */
    protected static function ofClosure(Closure $closure): array
    {
        try {
            $reflection = new ReflectionFunction($closure);

            return ['closure', $reflection->getFileName(), $reflection->getStartLine()];
        } catch (Throwable $exception) {
            // Reflection over a closure does not normally fail. If it ever does, the honest
            // answer is "some closure", which lands every closure in one bucket - renders
            // that differ only there share a key, which is why this is a fallback and not
            // the rule.
            return ['closure'];
        }
    }
}
