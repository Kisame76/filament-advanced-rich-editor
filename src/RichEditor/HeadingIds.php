<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Closure;
use Illuminate\Support\Str;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\Anchor;
use Tiptap\Editor;

class HeadingIds
{
    /**
     * The anchor a heading gets when its text carries nothing a slug can be built from.
     */
    public const FALLBACK = 'section';

    /**
     * Every anchor handed out so far, so a repeated heading gets a number instead of a
     * duplicate.
     *
     * @var array<string, true>
     */
    protected array $used = [];

    /**
     * The language whose transliteration rules build the slug, or null for the plain
     * ASCII fold `Str::slug()` uses by default.
     */
    protected ?string $language;

    public function __construct(?string $language = null)
    {
        $this->language = $language ?? config('filament-advanced-rich-editor.anchors.language');
    }

    public function assign(string $text, ?string $existing = null): string
    {
        // An id already on the node was chosen by a person; it is taken as given and only
        // registered, so nothing generated afterwards can collide with it.
        if (filled($existing)) {
            $this->used[$existing] = true;

            return $existing;
        }

        $slug = Str::slug($text, language: $this->language ?? 'en');

        // Emoji and punctuation slug to an empty string, and an element with `id=""` is
        // one nothing can link to. A generic anchor is worse than a descriptive one and
        // better than none.
        return $this->deduplicate($slug === '' ? static::FALLBACK : $slug);
    }

    /**
     * Anchors every heading in a document, in reading order, and reports what it did.
     *
     * The report is what a table of contents is built from, which is the whole reason
     * this is one pass and not two: an anchor written here and a link written from a
     * second walk would be two slug algorithms that agree only by accident.
     *
     * `$onHeading` is handed the node and what was decided about it, so a caller that
     * wants to change the heading - draw a link into it, count it, rewrite it - does so
     * in this pass rather than in a second walk that would have to find the same node
     * again by position.
     *
     * @param  array<int, int>  $levels
     * @param  (Closure(object, array{level: int, text: string, id: string}): void) | null  $onHeading
     * @return array<int, array{level: int, text: string, id: string}>
     */
    public function assignTo(Editor $editor, array $levels, ?Closure $onHeading = null): array
    {
        // A renderer built with no content at all has no document, and TipTap's walker
        // reads the node's type before it checks for one. An empty record is an ordinary
        // thing to render, so it must not be a crash.
        if (blank($editor->getDocument())) {
            return [];
        }

        $headings = [];

        $editor->descendants(function (object &$node) use (&$headings, $levels, $onHeading): void {
            if (($node->type ?? null) !== 'heading') {
                return;
            }

            $level = $node->attrs->level ?? null;

            if (! in_array($level, $levels, strict: true)) {
                return;
            }

            $text = static::textOf($node);
            $id = $this->assign($text, Anchor::normalise($node->attrs->id ?? null));

            $node->attrs->id = $id;

            $entry = ['level' => $level, 'text' => $text, 'id' => $id];

            if ($onHeading instanceof Closure) {
                $onHeading($node, $entry);
            }

            $headings[] = $entry;
        });

        return $headings;
    }

    /**
     * The plain text of a node, joined the way the browser joins it.
     *
     * Every text node under the heading counts, however deeply a mark nested it - and
     * nothing is inserted between them. Joining with a space instead would turn
     * `<strong>Get</strong>ting` into two words, which is a different heading and a
     * different anchor.
     */
    protected static function textOf(object $node): string
    {
        if (($node->type ?? null) === 'text') {
            return $node->text ?? '';
        }

        return array_reduce(
            $node->content ?? [],
            static fn (string $text, object $child): string => $text.static::textOf($child),
            initial: '',
        );
    }

    protected function deduplicate(string $slug): string
    {
        if (! isset($this->used[$slug])) {
            $this->used[$slug] = true;

            return $slug;
        }

        // The first repeat is `-2`, because the one already handed out is the first.
        $suffix = 2;

        while (isset($this->used[$slug.'-'.$suffix])) {
            $suffix++;
        }

        $this->used[$slug.'-'.$suffix] = true;

        return $slug.'-'.$suffix;
    }
}
