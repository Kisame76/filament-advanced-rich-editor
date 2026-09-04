<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes;

use DOMElement;
use Illuminate\Support\Str;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\ByteSize;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\FileTypes;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\MediaUrl;
use Tiptap\Core\Node;
use Tiptap\Utils\HTML;

/**
 * An uploaded document, as a card you can tell is a document.
 *
 * A pdf attached to a page arrives in the markup as an address in an `<a>`, and an address
 * is the one thing about a file that says nothing: `/storage/01J9.../a7f2c1.pdf` is a
 * reader's only clue that a hundred-page report is waiting behind it. The card says what
 * every file manager says - what kind, what name, how big - and it says it in the stored
 * document rather than only in the editor, which is the whole reason this is a node.
 *
 * **A node and not a decoration.** Drawing the card over the link the editor already has
 * would have been less code and would have solved nothing: a decoration lives in the
 * editor's view layer, so what a save writes is still the bare link, and the page a reader
 * opens still shows one. The complaint is about the page, so the card has to be in the
 * markup.
 *
 * **Its parse rule is `a[download]`.** Not a `data-type`, which is what `Embed` uses: a
 * download link written by hand, by another editor, or by an earlier version of this
 * package is already exactly this node, and `download` is the attribute that says so.
 * Nothing has to be migrated - a plain download link simply comes back as a card - and the
 * card reads itself back unchanged, which is the same statement from the other side.
 *
 * **Nothing here rides in an attribute the sanitiser can drop.** Filament allows six
 * `data-*` names through (`vendor/filament/support/src/SupportServiceProvider.php`), and
 * `data-size` and `data-name` are not among them. So the name and the size are the text of
 * their own spans, which survives because text always does, and the size is stored as the
 * label a reader sees rather than as a byte count - see `ByteSize`. `href`, `download` and
 * `title` are on Symfony's safe attribute list; `class`, `style`, `data-type` and `data-id`
 * are the ones Filament opens on every element.
 *
 * **`data-id` is here for the same reason it is on `Media`.** It is what
 * `resolveFileAttachmentIds()` collects, and what that method returns is the list
 * `cleanUpFileAttachments()` spares - a document whose id nothing walks is a file deleted
 * by the next save of the record that points at it. `Media/FileAttachments::TYPES` is where
 * this node joins that lifecycle.
 */
class FileCard extends Node
{
    /**
     * @var string
     */
    public static $name = 'file';

    /**
     * Inline, and not a block.
     *
     * A block would put every card on a line of its own and leave the rest of it empty -
     * three attachments at the end of an article, each with 400 pixels of nothing beside
     * it. Inline lets them sit next to each other and wrap when they run out of room, and
     * it puts the decision back where it belongs: a card on its own line is a paragraph of
     * its own, which is the same thing anyone does with anything else in a document.
     *
     * An atom regardless. There is nothing inside a card to type into - the three spans are
     * drawn from the attributes - and without this the name would be editable text that no
     * longer matched the `download` beside it.
     *
     * @var string
     */
    public static $group = 'inline';

    /**
     * @var bool
     */
    public static $inline = true;

    /**
     * @var bool
     */
    public static $atom = true;

    /**
     * The card's own shape, written inline rather than left to a class.
     *
     * The same decision `Media` and `Embed` document, and here it is the point rather than
     * a detail: this package's stylesheet is loaded into the admin panel, and the page the
     * content ends up on belongs to somebody else. A card that arrives there as three bare
     * spans is a file name with a word after it - which is what a plain link already was.
     * `style` survives the sanitiser, so the card travels with its own shape and a project
     * that wants a different one overrides it through the classes, which travel too.
     *
     * `box-sizing` is in here for that reason and not out of habit. A panel sets it to
     * `border-box` for everything; a plain page does not, and the same card was measured
     * 478 pixels wide on one and 448 on the other. A width that depends on whose page it
     * landed on is not a width.
     *
     * The card shrinks to what is on it rather than reserving a fixed width. Two short
     * attachments then sit side by side instead of leaving a column of empty card beside
     * each - and `max-width: 100%` is what keeps a long name from pushing past the text
     * column rather than a guessed number of rems.
     */
    public const CARD_STYLE = 'box-sizing: border-box; display: inline-flex; align-items: center; vertical-align: top; gap: 0.75rem; max-width: 100%; margin: 0.25rem 0.5rem 0.25rem 0; padding: 0.75rem; border: 1px solid rgba(113, 113, 122, 0.25); border-radius: 0.5rem; text-decoration: none; color: inherit;';

    /**
     * The name over the size, in a column beside the tile.
     *
     * Side by side they made the card as wide as both of them plus the tile, and a row of
     * attachments then ran out of line after two. Stacked, the card is as wide as the
     * longest of the two - which is always the name - and the column comes out the same
     * height as the tile beside it, so nothing had to be measured to make them line up.
     *
     * The size sits at the end of the column rather than under the name's first letter.
     * The name is the wider of the two and the one being read; hanging the size off its
     * right edge keeps the eye on one line down the left and puts the smaller fact where
     * it is found rather than where it interrupts.
     *
     * The room around the card is untouched by all of this. What the stack saves is width,
     * and the tightening it needed was between the two lines - not around them: a card with
     * less air top and bottom is a smaller card, not a better-arranged one. The padding is
     * the same on all four sides for the same reason it is on a button - two numbers there
     * would be a claim that the sides matter differently, and here they do not.
     *
     * The two lines keep a hair of space between them. At the tile's height there is room
     * for it, and without it the name and the size read as one block of text rather than as
     * two facts about the file.
     *
     * The size is set two steps down from the name rather than one. At a single step the
     * two were near enough in size to read as one line that happened to wrap; the point of
     * the second line is that it is secondary, and a size is a thing looked up rather than
     * read. The lighter ink says the same thing again.
     *
     * Where there is a size, the column is stretched to the tile's height and the two are
     * pushed apart: the name sits on the top line and the size on the bottom one, level
     * with the foot of the tile. Centred as a pair - which is what it was - the size hung
     * off the name instead of standing on anything, and the card had a line of writing with
     * a smaller line stuck under it rather than two rows.
     *
     * Where there is no size, the name is a single line and goes back to the middle. Pushed
     * apart, one child ends up at the top of a stretched column with the whole of the tile's
     * height empty beneath it.
     *
     * Both lines carry their own `line-height`, and that is what keeps the card the height
     * it was before the stack. Left to inherit, the two lines came to 38 pixels against a
     * 36-pixel tile - so the text, not the tile, decided how tall the card was, and the
     * card grew for a change that was supposed to make it smaller. At 1.2 the pair fits
     * inside the tile with room to spare and the tile is the tallest thing again.
     *
     * Every one of them says `margin: 0`, and that is not tidiness either. Prose styles -
     * Filament's in the panel, a project's own on the page - hand a top margin to the
     * children of a document. Measured in the panel: 16 pixels onto the column and 16 more
     * onto the size, which made a 50-pixel card 78. An inline style beats them, but only
     * for the properties it actually names, so the card has to name this one.
     */
    public const TEXT_STYLE = 'display: inline-flex; flex-direction: column; align-items: flex-start; min-width: 0; margin: 0; gap: 0.125rem;';

    public const NAME_STYLE = 'max-width: 100%; margin: 0; overflow-wrap: anywhere; font-weight: 500; line-height: 1.2;';

    public const SIZE_STYLE = 'align-self: flex-end; margin: 0; font-size: 0.75rem; line-height: 1.2; opacity: 0.6; font-variant-numeric: tabular-nums;';

    /**
     * @return array<string, mixed>
     */
    public function addAttributes(): array
    {
        return [
            'src' => [
                'default' => null,
                'parseHTML' => static fn ($DOMNode): ?string => $DOMNode instanceof DOMElement
                    ? MediaUrl::src($DOMNode->getAttribute('href'))
                    : null,
            ],
            'name' => [
                'default' => null,
                'parseHTML' => static fn ($DOMNode): ?string => static::name($DOMNode),
            ],
            // The size as its label, never as a number - see the class docblock.
            'size' => [
                'default' => null,
                'parseHTML' => static fn ($DOMNode): ?string => ByteSize::label(
                    static::textOf($DOMNode, 'fi-arte-file-size'),
                ),
            ],
            'id' => [
                'default' => null,
                'parseHTML' => static fn ($DOMNode): ?string => ($DOMNode instanceof DOMElement)
                    ? ($DOMNode->getAttribute('data-id') ?: null)
                    : null,
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
                'tag' => 'a[download]',
                // An address this package will not point at is not a card - it is a link
                // somebody else owns, and handing it back untouched is the only safe answer.
                'getAttrs' => static fn ($DOMNode) => ($DOMNode instanceof DOMElement)
                    && MediaUrl::src($DOMNode->getAttribute('href')) !== null
                        ? null
                        : false,
            ],
        ];
    }

    /**
     * The card, as markup this class writes itself.
     *
     * The `content` key rather than the usual nested-array form, and not by preference:
     * `tiptap-php`'s `DOMSerializer` cannot put text inside one. Its `renderOpeningTag()`
     * walks a render array looking only for tag names and attribute arrays and skips
     * everything else, so `['span', [...], 'PDF']` reaches a page as an empty `<span>` -
     * silently, which is the worst way for it to be wrong. `['content' => ...]` is the
     * serializer's own escape hatch for exactly this: `renderNode()` substitutes the string
     * whole and `renderClosingTag()` stands down.
     *
     * Everything written here is escaped on the way in. Nothing else does it for us once
     * the string leaves this method.
     *
     * The attributes are read off the node rather than out of `$HTMLAttributes`, for the
     * reason `Embed` and `Media` both document: the serialiser calls this a second time
     * with the node alone to work out the closing tag.
     *
     * @param  object  $node
     * @param  array<string, mixed>  $HTMLAttributes
     * @return array<mixed>|null
     */
    public function renderHTML($node, $HTMLAttributes = [])
    {
        $attributes = (array) ($node->attrs ?? []);

        $src = MediaUrl::src($attributes['src'] ?? null);

        // Nothing to fetch. A card with no address is a button that does nothing, which
        // reads as a broken page rather than as a missing file.
        if ($src === null) {
            return null;
        }

        $name = static::filename($attributes['name'] ?? null, $src);
        $size = ByteSize::label($attributes['size'] ?? null);

        $card = HTML::renderAttributes([
            'class' => 'fi-arte-file',
            'data-type' => 'file',
            'data-id' => $attributes['id'] ?? null,
            'href' => $src,
            // The name, so the browser saves the file under it rather than under the
            // hashed one the disk gave it. An empty value would still be a download; the
            // name is what makes it a useful one.
            'download' => $name,
            'style' => static::CARD_STYLE,
        ]);

        $kind = HTML::renderAttributes([
            'class' => 'fi-arte-file-kind',
            'style' => static::kindStyle(FileTypes::tint($name, $src)),
        ]);

        // The spaces between the spans are separators and not stray whitespace. A flex
        // container drops a run of white space between two items, so the card is drawn
        // exactly as it was without them - but everything that reads the document as
        // *text* keeps them. Without them the excerpt that fills
        // `<meta name="description">` reads `Quartalsbericht Q3.pdf88 KB`, the parts of a
        // card run into one word.
        $text = '<span'.HTML::renderAttributes([
            'class' => 'fi-arte-file-name',
            'style' => static::NAME_STYLE,
        ]).'>'.static::escape($name).'</span>';

        if ($size !== null) {
            $text .= ' <span'.HTML::renderAttributes([
                'class' => 'fi-arte-file-size',
                'style' => static::SIZE_STYLE,
            ]).'>'.static::escape($size).'</span>';
        }

        $html = "<a{$card}>"
            ."<span{$kind}>".static::escape(FileTypes::label($name, $src)).'</span> '
            .'<span'.HTML::renderAttributes([
                'class' => 'fi-arte-file-text',
                'style' => static::textStyle($size !== null),
            ]).">{$text}</span>";

        return ['content' => $html.'</a>'];
    }

    protected static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * The column holding the name and, where there is one, the size.
     *
     * Two answers rather than one, and the reason is in the class docblock: two lines want
     * the height of the tile and the top and bottom of it, one line wants the middle.
     */
    protected static function textStyle(bool $hasSize): string
    {
        return static::TEXT_STYLE.($hasSize
            ? ' align-self: stretch; justify-content: space-between;'
            : ' justify-content: center;');
    }

    /**
     * The tile behind the letters. Built rather than stored, because it follows from the
     * ending and a stored colour is a second answer to a question `FileTypes` already
     * answers - one that would go stale the day a tint changes.
     */
    protected static function kindStyle(string $tint): string
    {
        // `line-height: 1` is the whole of the vertical fix, and it is about capitals.
        // Flex centres the *line box*, not the letters, and a line box carries room under
        // the baseline for descenders - which `PDF` and `DOCX` do not have. At the default
        // line height the letters end up visibly high in the tile; collapsing the box to
        // the type size puts the caps within a tenth of a pixel of the centre. Measured on
        // this font at this size, not guessed.
        //
        // A padding to nudge them further was tried and taken out again: it overshot by
        // most of a pixel and, being padding on a `height`-sized box, quietly made the
        // square 0.8px taller than it was wide.
        //
        // The letter spacing is trailing, so the last letter carries a gap the first one
        // does not and the word sits a fraction left of centre. `text-indent` puts that
        // gap back on the left.
        return "flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; margin: 0; border-radius: 0.375rem; background-color: {$tint}; color: #ffffff; font-size: 0.625rem; font-weight: 700; line-height: 1; letter-spacing: 0.02em; text-indent: 0.02em;";
    }

    /**
     * What to call the file: what the card says, else what the browser was told to save it
     * as, else the last part of the address.
     *
     * Three readings of the same question because three things write it. The span is what
     * this package wrote last; `download` is what a hand-written link carries; and an
     * address is what is left when neither exists.
     */
    protected static function name(mixed $DOMNode): ?string
    {
        if (! ($DOMNode instanceof DOMElement)) {
            return null;
        }

        $written = static::textOf($DOMNode, 'fi-arte-file-name');

        if (filled($written)) {
            return $written;
        }

        $download = trim($DOMNode->getAttribute('download'));

        if ($download !== '') {
            return $download;
        }

        // What the link said. `<a download>Handbuch</a>` names the file `Handbuch`, and
        // dropping that to fall through to the address would rename somebody's document to
        // the hash the disk gave it. Only reached where no card wrote a name span, so it
        // never sees a card's own three-span text.
        $text = trim(preg_replace('/\s+/u', ' ', $DOMNode->textContent) ?? '');

        // A file name, not a paragraph. Anything longer came from markup this rule had no
        // business reading a name out of, and the address is the better answer than a
        // sentence set in bold across the card.
        return ($text === '' || mb_strlen($text) > 120) ? null : $text;
    }

    /**
     * The name a card ends up wearing, with an address as the last resort.
     */
    protected static function filename(mixed $name, string $src): string
    {
        if (is_string($name) && trim($name) !== '') {
            return trim($name);
        }

        $basename = rawurldecode(basename(Str::before(Str::before($src, '#'), '?')));

        return $basename === '' ? $src : $basename;
    }

    /**
     * The text of the one child carrying a class, or null.
     *
     * By hand, because `tiptap-php`'s parser takes a single `tag` or `tag[attr]` selector
     * and no descendant selectors - the same limitation `Embed` reads its iframe around.
     */
    protected static function textOf(mixed $DOMNode, string $class): ?string
    {
        if (! ($DOMNode instanceof DOMElement)) {
            return null;
        }

        foreach ($DOMNode->getElementsByTagName('span') as $span) {
            if (in_array($class, preg_split('/\s+/', $span->getAttribute('class')) ?: [], strict: true)) {
                $text = trim($span->textContent);

                return $text === '' ? null : $text;
            }
        }

        return null;
    }
}
