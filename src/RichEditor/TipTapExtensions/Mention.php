<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions;

use Filament\Forms\Components\RichEditor\TipTapExtensions\MentionExtension;
use Tiptap\Utils\HTML;

/**
 * Filament's mention node, with a class on it.
 *
 * Filament renders a mention as `data-type` and `data-id` and nothing else, which on a page
 * is indistinguishable from any other span or link - and a mention that reads as running
 * text is not a mention.
 *
 * The trigger goes into the class rather than staying in an attribute because it cannot
 * stay in an attribute: Filament's sanitiser allows `class`, `data-id`, `data-type` and
 * `style`, so the `data-char` this node renders is removed again before the markup reaches
 * anyone. A page that wants to draw a person differently from a category has the class or
 * it has nothing.
 *
 * This *replaces* Filament's node rather than joining it - two extensions of the same name
 * are both applied - so the renderer swaps it out by class, carrying the options across.
 */
class Mention extends MentionExtension
{
    /**
     * What each trigger is called in a class name.
     *
     * A name rather than the character itself, because a class is a name: `.fi-arte-mention-@`
     * needs escaping to be written in a stylesheet at all. A trigger that is not listed here
     * gets the base class alone, which still styles it as a mention.
     *
     * @var array<string, string>
     */
    public const TRIGGERS = [
        '@' => 'at',
        '#' => 'hash',
        '+' => 'plus',
        '~' => 'tilde',
        '$' => 'dollar',
        '%' => 'percent',
        '&' => 'amp',
        '!' => 'bang',
        '/' => 'slash',
        '?' => 'question',
    ];

    /**
     * @param  object  $node
     * @param  array<string, mixed>  $HTMLAttributes
     * @return array<mixed>
     */
    public function renderHTML($node, $HTMLAttributes = []): array
    {
        [$tag, $attributes, $content] = parent::renderHTML($node, $HTMLAttributes);

        return [
            $tag,
            HTML::mergeAttributes($attributes, ['class' => static::classes($node->attrs->char ?? '@')]),
            $content,
        ];
    }

    /**
     * The classes one trigger renders with.
     */
    public static function classes(string $char): string
    {
        $trigger = static::TRIGGERS[$char] ?? null;

        return 'fi-arte-mention'.($trigger === null ? '' : " fi-arte-mention-{$trigger}");
    }
}
