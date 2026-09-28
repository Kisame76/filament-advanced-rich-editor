<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Closure;

/**
 * The mode with no bar above the field.
 *
 * Everything it needs already exists: the slash menu knows this field's own tools, the grip
 * rearranges blocks, and the bar over a selection carries the marks. What was missing was
 * the decision not to draw the toolbar, and a name for the arrangement - a mode is
 * something switched on once rather than assembled out of five calls every time.
 *
 * The four things it stands on are on by default anyway, so the mode is not a shortcut. It
 * is the statement that this field is a document: a project that switched the grip off for
 * its ordinary fields did not mean it for the one with nothing else left to reach a block
 * with. That is why the mode sits between the field and the config file rather than beside
 * them - the field still overrules it, the config no longer does.
 *
 * The limit this mode was shipped with is gone, and it is worth saying what it was, because
 * it is why the mode reads as complete now and did not then: Filament registers the bar over
 * a selection under the `paragraph` key and showed it only while `editor.isActive('paragraph')`,
 * so inside a heading it never appeared - and with no toolbar, the link, the colours and the
 * styles were out of reach there. `TextToolbarPlugin` replaces that rule for every field, so
 * the hole is closed here and on any field with `->toolbarButtons([])` at the same time.
 */
trait WritesWithoutAToolbar
{
    protected bool|Closure|null $isNotion = null;

    /**
     * Hide the toolbar and let the document carry the editor.
     *
     * The slash menu, the block grip with its insert plus, and the bar over a selection are
     * switched on unless this field says otherwise, and the bar above the field is not
     * drawn. Naming a preset alongside is a more specific statement about the bar than the
     * mode makes, so the preset's bar wins.
     */
    public function notion(bool|Closure $condition = true): static
    {
        $this->isNotion = $condition;

        return $this;
    }

    public function isNotion(): bool
    {
        return (bool) $this->evaluate($this->isNotion);
    }

    /**
     * What the mode holds on, by name.
     *
     * Written out rather than implied, so that what the mode *is* can be read in one place
     * - and so that a switch consulting it by mistake gets no opinion instead of a `true`
     * that looks like a decision. Uploads are on the list for a reason of their own: with
     * no bar the field would otherwise read that answer off a bar that is not there, and
     * the slash menu's insert group ships `image` and `attachFiles`.
     *
     * @var array<int, string>
     */
    protected const NOTION_HOLDS = [
        'slashMenu',
        'dragHandle',
        'dragHandleInsert',
        'textToolbar',
        'fileAttachments',
    ];

    /**
     * What the mode answers for one switch the field has not answered itself.
     *
     * `null` where the mode is off, or where it has nothing to say about this switch, which
     * is what lets every reader keep falling through to the config file as it did before.
     */
    protected function notionDefaultFor(string $switch): ?bool
    {
        return $this->isNotion() && in_array($switch, static::NOTION_HOLDS, strict: true)
            ? true
            : null;
    }
}
