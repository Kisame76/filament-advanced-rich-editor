<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\DateTimeFormats;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Tiptap\Core\Extension;

/**
 * Writing the current date or time into the document.
 *
 * No PHP extension, no mark and - this is the unusual half - no JavaScript either. What is
 * inserted is ordinary text, so there is nothing to parse, nothing to allow through the
 * sanitiser and nothing to teach the renderer on the way back out; and `insertContent` is
 * one of TipTap's own commands, so there is no module to write, register, publish or hold
 * in step with a PHP list. This is the only tool in the package with no file under
 * `resources/js` at all.
 *
 * One tool per configured format rather than one tool taking the format as an argument.
 * A toolbar array carries names and nothing else - they are matched by exact equality out
 * of the configuration - so there is no channel for a parameter beside a name, and
 * `'d.m.Y H:i'` cannot itself be one. That is the shape `LineHeight`, `Callouts` and
 * `TextCase` all have, for the same reason.
 *
 * The string is fetched rather than carried. A date written into the button at render time
 * is the date the page was opened, and a field left open across an afternoon would then
 * insert an afternoon-old timestamp - so the click asks the server, over the same
 * `callSchemaComponentMethod` seam the media browser and the mention menu use, and the
 * answer arrives already formatted in the application's language and the display timezone.
 * The cost is one request per insert, and it is the honest one: the alternative that needs
 * no request is the browser's own clock and the browser's own idea of a language, and this
 * package takes neither anywhere else.
 *
 * What the browser sends is the configured KEY, never a format. The field looks it up in
 * its own list and answers `null` for anything else, so a crafted request cannot make the
 * server render a format nobody configured - the same reason the tickable task list asks
 * its permission question again rather than trusting the markup it drew.
 *
 * Shipped registered but unplaced, the way the case tools are: most documents never date a
 * paragraph, and the bar is finite. The way in is the slash menu, plus the means to place
 * the buttons - the names in `more`, or the `dateTime` token on a bar.
 */
class DateTimePlugin implements RichContentPlugin
{
    /**
     * @param  array<string, string>  $formats  key => the format it writes, already resolved
     */
    final public function __construct(
        protected array $formats = [],
    ) {}

    /**
     * @param  array<string, string>  $formats
     */
    public static function make(array $formats = []): static
    {
        return app(static::class, ['formats' => $formats]);
    }

    public static function toolName(string $key): ?string
    {
        return DateTimeFormats::toolName($key);
    }

    /**
     * @return array<Extension>
     */
    public function getTipTapPhpExtensions(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        $tools = [];

        foreach ($this->formats as $key => $format) {
            $name = static::toolName($key);

            if ($name === null) {
                continue;
            }

            $tools[] = RichEditorTool::make($name)
                ->label(static::getLabel($key, $format))
                ->icon(static::getIcon($key))
                ->jsHandler(static fn (RichEditorTool $tool): string => static::getJsHandler($tool, $key));
        }

        return $tools;
    }

    /**
     * The click, in one expression: ask the field for the string, and write it where the
     * caret is if an answer came back.
     *
     * `insertContent` with a plain string is TipTap's text fast path - it ends in
     * `tr.insertText`, which takes the marks at the caret - so a date written inside a bold
     * sentence comes out bold, the way a typed one would. That is also why the answer is
     * escaped on the way out: the same path parses its argument as HTML, and a format
     * holding an ampersand would otherwise arrive as half a character reference.
     *
     * Nothing is inserted when no answer came back - the switched-off field, the key that is
     * not in this field's list, and a request that failed are three cases where doing nothing
     * is the whole correct behaviour. The test is written out rather than left to `text &&`,
     * because a format can legitimately render to `"0"`: `G` between midnight and one, `w`
     * on a Sunday, `z` on New Year's Day. All three are falsy in JavaScript, and a button
     * that silently does nothing one hour out of twenty-four is worse than one that never
     * works.
     */
    protected static function getJsHandler(RichEditorTool $tool, string $key): string
    {
        $component = Js::from($tool->getEditor()->getKey())->toHtml();
        $argument = Js::from($key)->toHtml();

        return "\$wire.callSchemaComponentMethod({$component}, 'getDateTimeForJs', { key: {$argument} })".
            ".then((text) => text != null && text !== '' && ".
            '$getEditor()?.chain().focus().insertContent(text).run())';
    }

    /**
     * The label a project translated, and otherwise the format read as it will read.
     *
     * The lookup sits under `formats` rather than beside the dropdown's own `label`, so that
     * the keys a project configures live in a namespace of their own: a format keyed `label`
     * would otherwise resolve to the trigger's text and call itself "Date and time".
     *
     * A translated name is the better label where there is one, because it says what the
     * entry is rather than what today happens to look like. A format a project added has
     * no name anybody wrote down, and its own characters mean nothing to a reader - so the
     * fallback is a worked example. It is drawn when the page is drawn, which is worth
     * knowing for a format that is only a clock: the menu then shows the hour the page was
     * opened while the insert writes the hour it was clicked.
     */
    public static function getLabel(string $key, string $format): string
    {
        $translation = 'filament-advanced-rich-editor::advanced-rich-editor.tools.date_time.formats.'.Str::snake($key);

        return Lang::has($translation)
            ? __($translation)
            : DateTimeFormats::render($format);
    }

    /**
     * A calendar for a date, a clock for a time, and the calendar with its days for
     * everything else - which is both the combined format and any a project adds, because
     * a format nobody named is not a thing an icon can be picked for.
     */
    public static function getIcon(string $key): string|BackedEnum
    {
        return Icons::get(match ($key) {
            'date' => 'date_time_date',
            'time' => 'date_time_time',
            default => 'date_time',
        });
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [];
    }
}
