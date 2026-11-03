<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedProviders;

/**
 * One service an embed can come from, described rather than programmed.
 *
 * A pasted link is read in two places - by the editor in the browser, which decides what a
 * paste turns into, and here, which decides what a save keeps and what a page frames. The two
 * have to agree, and they agree because they read the same description: what follows is data
 * the editor is handed as it is, and both halves interpret it the same way. A service added
 * here is added to both.
 *
 * So the patterns are regular expressions both engines read alike: named groups, character
 * classes and anchors, and nothing only PCRE knows. They are written without delimiters.
 *
 * What is stored is what the embed *is* - the service, an id, a timestamp - and the address a
 * browser frames is built from those on the way out. A link somebody pasted is never framed as
 * it was found: a watch page refuses to be framed, and a host this package does not know has
 * no business in an `iframe`.
 */
abstract class EmbedProvider
{
    /**
     * The key it is stored under. Lower case; it ends up in a file name and a translation key.
     */
    abstract public function name(): string;

    /**
     * Every host a link to it may come from, spelled out and compared exactly. A substring
     * test frames `youtube.com.attacker.test`, which is a domain anybody can register.
     *
     * @return array<int, string>
     */
    abstract public function hosts(): array;

    /**
     * Where the id sits in a link, as patterns over the host, the path and the query - in that
     * order, `host/path?query`, without the fragment. The first one that yields a valid id
     * wins. The id is the named groups of the match joined by `/`, so a service whose id has
     * two halves names both.
     *
     * @return array<int, string>
     */
    abstract public function patterns(): array;

    /**
     * What an id may look like - narrower than it could be, and never anything that would
     * change the meaning of the address it is written into.
     */
    abstract public function id(): string;

    /**
     * The address a browser frames. `{id}` is the id, `{id:0}` and `{id:1}` its halves.
     */
    abstract public function src(): string;

    /**
     * The address a person opens, for a link out of the media library.
     */
    abstract public function share(): string;

    /**
     * How a timestamp is added to the framed address, `{start}` being the seconds - or null
     * for a service that has none, whose links are then read without one.
     */
    public function start(): ?string
    {
        return null;
    }

    /**
     * The same for the share address, which may spell it differently.
     */
    public function shareStart(): ?string
    {
        return $this->start();
    }

    /**
     * The name shown on the card in the editor and on the tile in the library.
     */
    public function label(): string
    {
        $key = 'filament-advanced-rich-editor::advanced-rich-editor.tools.embed.providers.'.$this->name();
        $label = __($key);

        return ($label === $key) ? $this->name() : (string) $label;
    }

    /**
     * The hosts the framed address may have, for the sanitiser's allow list - the second line
     * behind the node, which only ever builds addresses from `src()`.
     *
     * @return array<int, string>
     */
    public function frameHosts(): array
    {
        return $this->hosts();
    }

    /**
     * The shape the frame is drawn in until somebody picks another.
     */
    public function ratio(): string
    {
        return '16 / 9';
    }

    /**
     * A height in pixels for a player that has one - a music player is a strip, whatever the
     * width - or null where the shape is a ratio.
     */
    public function height(): ?int
    {
        return null;
    }

    /**
     * Where a still of it can be fetched from directly, `{id}` filled in - for the tile in the
     * library. Null where it has to be asked for, or where there is none.
     */
    public function cover(): ?string
    {
        return null;
    }

    /**
     * The oEmbed endpoint that answers with a still, asked with the share address - for a
     * service whose still cannot be built from the id.
     */
    public function oembed(): ?string
    {
        return null;
    }

    /**
     * The id in a link of this service, or null.
     *
     * @param  string  $subject  `host/path?query`, the host in lower case
     */
    public function read(string $subject): ?string
    {
        foreach ($this->patterns() as $pattern) {
            if (preg_match(static::regex($pattern), $subject, $matches) !== 1) {
                continue;
            }

            $parts = array_filter(
                $matches,
                static fn (string $value, int|string $key): bool => is_string($key) && $value !== '',
                ARRAY_FILTER_USE_BOTH,
            );

            $id = implode('/', $parts);

            if ($this->accepts($id)) {
                return $id;
            }
        }

        return null;
    }

    public function accepts(mixed $id): bool
    {
        return is_string($id) && preg_match(static::regex('^(?:'.$this->id().')$'), $id) === 1;
    }

    /**
     * A template filled in with an id, and with a timestamp where the service takes one.
     */
    public function fill(string $template, string $id, ?int $start = null, ?string $startTemplate = null): string
    {
        $parts = explode('/', $id);

        $address = (string) preg_replace_callback(
            '/\{id(?::(\d+))?\}/',
            static fn (array $match): string => isset($match[1]) ? ($parts[(int) $match[1]] ?? '') : $id,
            $template,
        );

        return ($start === null || $start <= 0 || $startTemplate === null)
            ? $address
            : $address.str_replace('{start}', (string) $start, $startTemplate);
    }

    /**
     * Everything the editor needs to read a link and draw a card, as it is handed to it.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name(),
            'label' => $this->label(),
            'hosts' => array_values($this->hosts()),
            'patterns' => array_values($this->patterns()),
            'id' => $this->id(),
            'src' => $this->src(),
            'start' => $this->start(),
            'ratio' => $this->ratio(),
            'height' => $this->height(),
        ];
    }

    /**
     * A pattern as PHP reads it, the way a script reads it. `D` is the one difference that
     * matters: without it PCRE lets `$` match before a final line break, so `abc\n` passed for
     * an id here that the editor refuses.
     */
    protected static function regex(string $pattern): string
    {
        return '~'.str_replace('~', '\~', $pattern).'~D';
    }
}
