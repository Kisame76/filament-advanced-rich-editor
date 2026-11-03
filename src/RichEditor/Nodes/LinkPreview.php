<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes;

use DOMElement;
use Tiptap\Core\Node;
use Tiptap\Utils\HTML;

/**
 * A link drawn as a card: the page's title, a line or two of what it is about, and the site it
 * is on, with the site's icon.
 *
 * **What the card says is written down when it is made.** The page is read once, in the dialog,
 * and what it said goes into the document like any other text - which the person making the card
 * can still correct. Read again on every render instead, the card would always be current, and a
 * page view would be a request to somebody else's server: slow, unreliable and nobody's idea of
 * what showing an article involves. A card that has gone stale is a card somebody can edit.
 *
 * **The icon travels inside the card,** as a few hundred bytes of `data:` address. Pointing at
 * the site's own would have every reader's browser ask that site for it - the reason embeds go
 * through youtube-nocookie - and the page's large preview picture is left out for the same reason
 * and one more: it is the size of a photograph, and a card is not one.
 *
 * **Its own shape, inline,** for the reason `FileCard` and `Embed` document: the stylesheet of
 * this package is the panel's, and the page the content ends up on is somebody else's. The parts
 * are the text of their own spans, because text survives the sanitiser and a `data-` attribute
 * named anything but the six Filament opens does not.
 */
class LinkPreview extends Node
{
    /**
     * @var string
     */
    public static $name = 'linkPreview';

    /**
     * @var string
     */
    public static $group = 'block';

    /**
     * @var bool
     */
    public static $atom = true;

    /** The longest a title, a description and a site name are kept: a card, not the page. */
    public const TITLE_LENGTH = 200;

    public const DESCRIPTION_LENGTH = 300;

    public const SITE_LENGTH = 80;

    /**
     * The largest icon kept, as the `data:` address it is stored as. 16 KB of it - 12 KB of file -
     * is a generous favicon; anything larger is a picture somebody named one.
     */
    public const ICON_LENGTH = 16_384;

    /**
     * The kinds of picture a browser draws from a `data:` address in an `<img>`. An SVG is one of
     * them, and safe there: a picture in an `<img>` runs no script.
     *
     * @var array<int, string>
     */
    public const ICON_TYPES = ['image/png', 'image/gif', 'image/jpeg', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/svg+xml'];

    /**
     * The card: a block, as wide as the text, the parts stacked. `color: inherit` and no
     * underline, because prose styles dress every `<a>` as a link, and this one is a card.
     */
    public const CARD_STYLE = 'box-sizing: border-box; display: flex; flex-direction: column; gap: 0.25rem; max-width: 100%; margin: 1rem 0; padding: 0.875rem 1rem; border: 1px solid rgba(113, 113, 122, 0.25); border-radius: 0.5rem; text-decoration: none; color: inherit;';

    public const TITLE_STYLE = 'margin: 0; font-weight: 600; line-height: 1.3; overflow-wrap: anywhere;';

    /** Two lines at most: what the page is about, not the page. */
    public const DESCRIPTION_STYLE = 'display: -webkit-box; margin: 0; overflow: hidden; font-size: 0.875rem; line-height: 1.4; opacity: 0.75; -webkit-box-orient: vertical; -webkit-line-clamp: 2;';

    public const SITE_STYLE = 'display: flex; align-items: center; gap: 0.375rem; margin: 0.25rem 0 0; font-size: 0.75rem; line-height: 1.2; opacity: 0.6;';

    /** `margin: 0` against prose styles, which give a picture two lines of room either side. */
    public const ICON_STYLE = 'flex: 0 0 auto; width: 1rem; height: 1rem; margin: 0;';

    /**
     * @return array<string, mixed>
     */
    public function addAttributes(): array
    {
        return [
            'href' => [
                'default' => null,
                'parseHTML' => static fn ($DOMNode): ?string => ($DOMNode instanceof DOMElement)
                    ? static::href($DOMNode->getAttribute('href'))
                    : null,
            ],
            'title' => [
                'default' => null,
                'parseHTML' => static fn ($DOMNode): ?string => static::text(static::partOf($DOMNode, 'fi-arte-link-preview-title')?->textContent, static::TITLE_LENGTH),
            ],
            'description' => [
                'default' => null,
                'parseHTML' => static fn ($DOMNode): ?string => static::text(static::partOf($DOMNode, 'fi-arte-link-preview-description')?->textContent, static::DESCRIPTION_LENGTH),
            ],
            'site' => [
                'default' => null,
                'parseHTML' => static fn ($DOMNode): ?string => static::text(static::partOf($DOMNode, 'fi-arte-link-preview-site')?->textContent, static::SITE_LENGTH),
            ],
            'icon' => [
                'default' => null,
                'parseHTML' => static fn ($DOMNode): ?string => static::icon(static::partOf($DOMNode, 'fi-arte-link-preview-icon')?->getAttribute('src')),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parseHTML(): array
    {
        return [
            [
                // `data-type` carries cards of other kinds too, and the parser cannot express a
                // value with a hyphen in its selector - so the value is checked here, as
                // `Embed` does.
                'tag' => 'a[data-type]',
                // Above the link mark's rule, which takes the same `<a>` by its `href` - see
                // `FileCard::parseHTML()`.
                'priority' => 60,
                'getAttrs' => static fn ($DOMNode) => ($DOMNode instanceof DOMElement)
                    && ($DOMNode->getAttribute('data-type') === 'link-preview')
                    && (static::href($DOMNode->getAttribute('href')) !== null)
                        ? null
                        : false,
            ],
        ];
    }

    /**
     * The card, as markup written here - the `content` form, for the reason `FileCard` gives:
     * the serialiser cannot put text inside a render array. Everything is escaped on the way in.
     *
     * @param  object  $node
     * @param  array<string, mixed>  $HTMLAttributes
     * @return array<mixed>|null
     */
    public function renderHTML($node, $HTMLAttributes = [])
    {
        $attributes = (array) ($node->attrs ?? []);

        $href = static::href($attributes['href'] ?? null);

        // A card pointing nowhere is a box that does nothing when clicked.
        if ($href === null) {
            return null;
        }

        $title = static::text($attributes['title'] ?? null, static::TITLE_LENGTH) ?? $href;
        $description = static::text($attributes['description'] ?? null, static::DESCRIPTION_LENGTH);
        $icon = static::icon($attributes['icon'] ?? null);

        // The spaces between the parts are separators, as on `FileCard`: the flex box drops them,
        // and everything reading the document as text - an excerpt, a search index - keeps the
        // title and the description from running into one word.
        $html = '<a'.HTML::renderAttributes([
            'class' => 'fi-arte-link-preview',
            'data-type' => 'link-preview',
            'href' => $href,
            'style' => static::CARD_STYLE,
        ]).'>'
            .static::part('title', static::TITLE_STYLE, static::escape($title));

        if ($description !== null) {
            $html .= ' '.static::part('description', static::DESCRIPTION_STYLE, static::escape($description));
        }

        $site = ($icon === null) ? '' : '<img'.HTML::renderAttributes([
            'class' => 'fi-arte-link-preview-icon',
            'src' => $icon,
            // Decorative: the site's name is beside it. The empty `alt` is dropped on the way
            // out and put back by `DecorativeImages`, which goes by the role.
            'role' => 'presentation',
            'alt' => '',
            'width' => '16',
            'height' => '16',
            'style' => static::ICON_STYLE,
        ]).'> ';

        $html .= ' '.static::part('site', static::SITE_STYLE, $site.static::escape(static::site($attributes['site'] ?? null, $href)));

        return ['content' => $html.'</a>'];
    }

    /**
     * The address a card may point at: a page on the web, and nothing a browser would run.
     */
    public static function href(mixed $href): ?string
    {
        if (! is_string($href)) {
            return null;
        }

        $href = trim($href);

        // A control character or a space inside the scheme is how `java\nscript:` gets past a
        // check that only reads the front of the string; a browser reads straight through it.
        if (preg_match('/[\x00-\x20\x7f]/', $href) === 1) {
            return null;
        }

        $parts = parse_url($href);

        return (is_array($parts) && filled($parts['host'] ?? null) && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], strict: true))
            ? $href
            : null;
    }

    /**
     * What the card says the site is called: the name it was given, or the host without `www.`.
     */
    public static function site(mixed $site, string $href): string
    {
        return static::text($site, static::SITE_LENGTH)
            ?? (string) preg_replace('/^www\./i', '', (string) parse_url($href, PHP_URL_HOST));
    }

    /**
     * A site's icon, kept only as a picture in a `data:` address of a kind and a size a card
     * can carry - a value from anywhere else is somebody else's address, which is the one thing
     * the card was meant not to point at.
     */
    public static function icon(mixed $icon): ?string
    {
        if (! is_string($icon) || (strlen($icon) > static::ICON_LENGTH)) {
            return null;
        }

        if (preg_match('#^data:([a-z0-9.+/-]+);base64,[A-Za-z0-9+/]+={0,2}$#i', $icon, $matches) !== 1) {
            return null;
        }

        return in_array(strtolower($matches[1]), static::ICON_TYPES, strict: true) ? $icon : null;
    }

    /**
     * A part of the card as one line of text, cut to a length - or null where nothing is left.
     *
     * The length is in characters, as the dialog's `maxLength()` and the editor's script count
     * it. Not `Str::limit()`, which counts the width a terminal would draw - two for every
     * Chinese or Japanese character - and so cut a title the dialog had accepted to half its
     * length on the way into the document.
     */
    public static function text(mixed $text, int $length): ?string
    {
        if (! is_string($text)) {
            return null;
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '') {
            return null;
        }

        return (mb_strlen($text) <= $length) ? $text : rtrim(mb_substr($text, 0, $length)).'…';
    }

    protected static function part(string $name, string $style, string $html): string
    {
        return '<span'.HTML::renderAttributes(['class' => "fi-arte-link-preview-{$name}", 'style' => $style]).">{$html}</span>";
    }

    /**
     * The element of the card carrying a class. By hand, because `tiptap-php`'s parser takes a
     * single selector per rule - see `FileCard::textOf()`.
     */
    protected static function partOf(mixed $DOMNode, string $class): ?DOMElement
    {
        if (! $DOMNode instanceof DOMElement) {
            return null;
        }

        foreach ($DOMNode->getElementsByTagName('*') as $element) {
            if (in_array($class, preg_split('/\s+/', $element->getAttribute('class')) ?: [], strict: true)) {
                return $element;
            }
        }

        return null;
    }

    protected static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
