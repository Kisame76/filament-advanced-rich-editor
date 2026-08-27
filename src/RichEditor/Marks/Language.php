<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Marks;

use DOMElement;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Languages;
use Tiptap\Core\Mark;
use Tiptap\Utils\HTML;

/**
 * The PHP half of the language a passage is written in.
 *
 * Content is stored as HTML and re-parsed on every hydration, and the parser keeps only
 * what something declares - so without this the `lang` on a quoted French title would be
 * gone the first time the record is reopened, and a screen reader would go back to reading
 * it in the voice of the page.
 *
 * Nothing has to be added to the application's sanitiser: `lang` is on Symfony's safe
 * attribute list, exactly like `dir`, so the attribute travels to the rendered page on its
 * own. The value is checked against `Languages::CODE` all the same - being allowed through
 * is not the same as being a language, and `lang="javascript:"` would sail past a list that
 * only asks for the attribute's name.
 */
class Language extends Mark
{
    /**
     * @var string
     */
    public static $name = 'language';

    /**
     * @return array<string, mixed>
     */
    public function addOptions(): array
    {
        return [
            'HTMLAttributes' => [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parseHTML(): array
    {
        return [
            [
                'tag' => 'span[lang]',
                // Returning `false` rejects the rule, so a span carrying something that is
                // not a language is left to whichever other mark claims it.
                'getAttrs' => static fn (mixed $DOMNode): ?bool => static::read($DOMNode) === null ? false : null,
            ],
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function addAttributes(): array
    {
        return [
            'code' => [
                'parseHTML' => static fn (mixed $DOMNode): ?string => static::read($DOMNode),
                'renderHTML' => static function (mixed $attributes): array {
                    $value = is_object($attributes) ? ($attributes->code ?? null) : null;
                    $code = is_string($value) ? Languages::code($value) : null;

                    return $code === null ? [] : ['lang' => $code];
                },
            ],
        ];
    }

    /**
     * @param  object  $mark
     * @param  array<string, mixed>  $HTMLAttributes
     * @return array<mixed>
     */
    public function renderHTML($mark, $HTMLAttributes = [])
    {
        return [
            'span',
            HTML::mergeAttributes($this->options['HTMLAttributes'], $HTMLAttributes),
            0,
        ];
    }

    protected static function read(mixed $DOMNode): ?string
    {
        return ($DOMNode instanceof DOMElement)
            ? Languages::code($DOMNode->getAttribute('lang'))
            : null;
    }
}
