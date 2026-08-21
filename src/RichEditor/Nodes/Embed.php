<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes;

use DOMElement;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedHostSanitizer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedUrl;
use Tiptap\Core\Node;
use Tiptap\Utils\HTML;

/**
 * A video, as the frame a browser will actually show.
 *
 * The node stores what the video *is* - a provider, an id, a timestamp - and builds the
 * embed URL on the way out. Storing the URL instead would mean trusting whatever was in
 * the document: a watch link, which no browser will frame, or a host this package has no
 * business pointing an iframe at. Rebuilding it is also what lets `youtube-nocookie` be a
 * setting rather than a decision frozen into every record ever saved.
 *
 * The wrapper carries `data-type="embed"`, which is one of the few data attributes
 * Filament's sanitiser keeps - so nothing has to be added to the application's sanitiser
 * config for the wrapper itself. It is also why the parse rule checks the *value*: task
 * lists, grids and custom blocks all ride on the same attribute.
 *
 * `tiptap-php`'s parser takes a single `tag` or `tag[attr]` selector and no descendant
 * selectors, so the rule matches the wrapper and the `iframe` inside it is read by hand.
 */
class Embed extends Node
{
    /**
     * @var string
     */
    public static $name = 'embed';

    /**
     * @var string
     */
    public static $group = 'block';

    /**
     * @var bool
     */
    public static $atom = true;

    public const DEFAULT_RATIO = '16 / 9';

    /**
     * What the frame is allowed to do. Deliberately short: a video needs fullscreen and
     * the picture-in-picture button, and nothing here needs a camera or a microphone.
     */
    public const ALLOW = 'accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';

    /**
     * @return array<string, mixed>
     */
    public function addAttributes(): array
    {
        return [
            'provider' => [
                'default' => null,
                'parseHTML' => static fn ($DOMNode): ?string => static::read($DOMNode)['provider'] ?? null,
            ],
            'id' => [
                'default' => null,
                'parseHTML' => static fn ($DOMNode): ?string => static::read($DOMNode)['id'] ?? null,
            ],
            'start' => [
                'default' => null,
                'parseHTML' => static fn ($DOMNode): ?int => static::read($DOMNode)['start'] ?? null,
            ],
            'title' => [
                'default' => null,
                'parseHTML' => static fn ($DOMNode): ?string => static::iframe($DOMNode)?->getAttribute('title') ?: null,
            ],
            'ratio' => [
                'default' => static::DEFAULT_RATIO,
                'parseHTML' => static function ($DOMNode): string {
                    if (! ($DOMNode instanceof DOMElement)) {
                        return static::DEFAULT_RATIO;
                    }

                    // The ratio rides in the wrapper's own `aspect-ratio`, which is the
                    // property doing the work rather than a copy of it kept alongside.
                    preg_match('/aspect-ratio:\s*([0-9.]+\s*\/\s*[0-9.]+)/i', $DOMNode->getAttribute('style'), $matches);

                    return isset($matches[1]) ? trim($matches[1]) : static::DEFAULT_RATIO;
                },
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
                'tag' => 'div[data-type]',
                // `data-type` carries task lists, grids and custom blocks as well, and the
                // parser cannot express a value in its selector - so the value is checked
                // here, and everything else is handed back to whoever owns it.
                'getAttrs' => static fn ($DOMNode) => ($DOMNode instanceof DOMElement)
                    && $DOMNode->getAttribute('data-type') === 'embed'
                    && static::read($DOMNode) !== null
                        ? null
                        : false,
            ],
        ];
    }

    /**
     * The attributes are read off the node rather than out of `$HTMLAttributes`.
     *
     * The serialiser calls this a second time to work out the closing tag, and that call
     * passes the node alone - so a render built from the second argument produces the
     * opening tag correctly and then fails on the way out.
     *
     * @param  object  $node
     * @param  array<string, mixed>  $HTMLAttributes
     * @return array<mixed>|null
     */
    public function renderHTML($node, $HTMLAttributes = [])
    {
        $attributes = (array) ($node->attrs ?? []);

        $provider = $attributes['provider'] ?? null;
        $id = $attributes['id'] ?? null;

        // Nothing to frame. Rendering an empty iframe instead - which is what allowing the
        // element and dropping the `src` leaves behind - would be a hole in the page where
        // the reader expects a video and the author expects nothing to be wrong.
        if (! is_string($provider) || ! is_string($id) || ! isset(EmbedUrl::IDS[$provider])) {
            return null;
        }

        $src = EmbedUrl::src($provider, $id, static::start($attributes['start'] ?? null));

        if (! static::isAllowed($src)) {
            return null;
        }

        return [
            'div',
            [
                'class' => 'fi-arte-embed',
                'data-type' => 'embed',
                // Inline rather than left to a class. This package's stylesheet is loaded
                // into the admin panel, and the page the content ends up on is somebody
                // else's - an embed arriving there with only a class on it is a 300x150
                // box in the corner. `style` survives the sanitiser, so the shape travels
                // with the markup. The class is still there to style further.
                'style' => 'aspect-ratio: '.($attributes['ratio'] ?? static::DEFAULT_RATIO).'; width: 100%;',
            ],
            [
                'iframe',
                HTML::mergeAttributes([
                    'src' => $src,
                    'title' => $attributes['title'] ?? null,
                    'loading' => 'lazy',
                    'allow' => static::ALLOW,
                    'allowfullscreen' => 'true',
                    // The referrer is the page, not the reader's path through it.
                    'referrerpolicy' => 'strict-origin-when-cross-origin',
                    // The frame fills the box the wrapper's aspect ratio drew.
                    'style' => 'width: 100%; height: 100%; border: 0;',
                ]),
                null,
            ],
        ];
    }

    /**
     * What the `iframe` inside a wrapper says the video is.
     *
     * The `src` is the one place the answer lives, so a document written by hand, imported,
     * or saved by an older version of this package is read the same way as one this node
     * wrote.
     *
     * @return array{provider: string, id: string, start: int|null}|null
     */
    protected static function read(mixed $DOMNode): ?array
    {
        $iframe = static::iframe($DOMNode);

        return $iframe === null ? null : EmbedUrl::parse($iframe->getAttribute('src'));
    }

    protected static function iframe(mixed $DOMNode): ?DOMElement
    {
        if (! ($DOMNode instanceof DOMElement)) {
            return null;
        }

        $iframe = $DOMNode->getElementsByTagName('iframe')->item(0);

        return $iframe instanceof DOMElement ? $iframe : null;
    }

    protected static function start(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    protected static function isAllowed(string $src): bool
    {
        $host = parse_url($src, PHP_URL_HOST);

        return is_string($host) && EmbedHostSanitizer::allows(
            $host,
            (array) config('filament-advanced-rich-editor.embed.allowed_hosts', []),
        );
    }
}
