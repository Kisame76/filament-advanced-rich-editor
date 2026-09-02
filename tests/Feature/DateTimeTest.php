<?php

declare(strict_types=1);

use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Support\Carbon;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\DateTimeFormats;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\DateTimePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\SlashMenu;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarLayout;

it('ships off, because a date button is worth having in some documents and not in others', function (): void {
    // Daily in a template that gets filled in, never in a blog post. That is a decision
    // rather than a default, so it is a line of configuration - the same reason the brush
    // and the typographic replacements ship off.
    expect(editor()->hasDateTime())->toBeFalse()
        ->and(editor()->getTools())->not->toHaveKey('insertDate');
});

it('offers one tool per configured format', function (): void {
    $tools = editor()->dateTime()->getTools();

    expect($tools)->toHaveKeys(['insertDate', 'insertTime', 'insertDateTime']);
});

it('asks the field for the string rather than carrying one', function (): void {
    // A date written into the button at render time is the date the page was opened. This
    // handler asks at the moment of the click, which is the whole reason the round trip is
    // worth its cost.
    expect(editor()->dateTime()->getTools()['insertDate']->getJsHandler())
        ->toBe(
            '$wire.callSchemaComponentMethod(\'content\', \'getDateTimeForJs\', { key: \'date\' })'.
            ".then((text) => text != null && text !== '' && ".
            '$getEditor()?.chain().focus().insertContent(text).run())',
        );
});

it('inserts nothing when the answer is empty', function (): void {
    // The guard is the whole error handling: a switched-off field, a key this field does not
    // have and a request that failed all arrive here as null, and in all three cases doing
    // nothing is the correct behaviour.
    expect(editor()->dateTime()->getTools()['insertTime']->getJsHandler())
        ->toContain("(text) => text != null && text !== ''");
});

it('labels the three it ships and draws the format for anything else', function (): void {
    $tools = editor()
        ->dateTime()
        ->dateTimeFormats(['date' => 'Y-m-d', 'stamp' => '\\S\\t\\a\\n\\d: Y'])
        ->getTools();

    // A translated name says what the entry is; a format a project added has no name
    // anybody wrote down, so the fallback is a worked example of it.
    expect($tools['insertDate']->getLabel())->toBe('Date')
        ->and($tools['insertStamp']->getLabel())->toBe('Stand: '.Carbon::now()->format('Y'));
});

it('draws a calendar, a clock, and the calendar with days for the rest', function (): void {
    $tools = editor()->dateTime()->dateTimeFormats(['date' => 'Y', 'time' => 'H', 'stamp' => 'Y'])->getTools();

    expect($tools['insertDate']->getIcon())->toBe(Icons::get('date_time_date'))
        ->and($tools['insertTime']->getIcon())->toBe(Icons::get('date_time_time'))
        ->and($tools['insertStamp']->getIcon())->toBe(Icons::get('date_time'));
});

it('takes the format the schema answers with when none is named', function (): void {
    // Filament already answers "what does a date look like here" three times over, on the
    // schema rather than on the panel. Inheriting is the same precedence its own columns
    // and entries follow.
    $editor = editor()->dateTime();
    $container = $editor->getContainer();

    expect($editor->getDateTimeFormats())->toBe([
        'date' => $container->getDefaultDateDisplayFormat(),
        'time' => $container->getDefaultTimeDisplayFormat(),
        'dateTime' => $container->getDefaultDateTimeDisplayFormat(),
    ]);
});

it('lets a schema change the inherited answer', function (): void {
    $editor = editor()->dateTime();
    $editor->getContainer()->defaultDateDisplayFormat('j. F Y');

    expect($editor->getDateTimeFormats()['date'])->toBe('j. F Y');
});

it('drops a key that names no format and inherits none', function (): void {
    // Only the three keys something else answers for may stand empty. A fourth naming
    // nothing has no fallback, and a tool that inserts nothing is worse than no tool.
    expect(editor()->dateTime()->dateTimeFormats(['date' => null, 'stamp' => null])->getDateTimeFormats())
        ->toHaveKey('date')
        ->not->toHaveKey('stamp');
});

it('refuses a key that could not be a tool name', function (): void {
    // A dot would be read as nesting in the alias translation key, would truncate the
    // derived label, and would break the exact-equality match a toolbar array does.
    expect(DateTimeFormats::toolName('date'))->toBe('insertDate')
        ->and(DateTimeFormats::toolName('dateTime'))->toBe('insertDateTime')
        ->and(DateTimeFormats::toolName('d.m.Y H:i'))->toBeNull()
        ->and(DateTimeFormats::toolName('Date'))->toBeNull()
        ->and(DateTimeFormats::toolName('date_time'))->toBeNull()
        ->and(DateTimeFormats::map(['d.m.Y' => 'd.m.Y', 'ok' => 'Y']))->toBe(['ok' => 'Y']);
});

it('answers only for a key this field actually offers', function (): void {
    // What arrives from the browser is a key and never a format, and it is looked up in
    // the field's own list - so a crafted request cannot make the server render a format
    // nobody configured.
    $editor = editor()->dateTime()->dateTimeFormats(['date' => 'Y']);

    expect($editor->getDateTimeForJs('date'))->toBe(Carbon::now()->format('Y'))
        ->and($editor->getDateTimeForJs('time'))->toBeNull()
        ->and($editor->getDateTimeForJs('d.m.Y'))->toBeNull()
        ->and(editor()->getDateTimeForJs('date'))->toBeNull();
});

it('escapes the answer, because the command receiving it parses HTML', function (): void {
    // `insertContent` with a plain string parses its argument as HTML on the way to the
    // text it inserts. Without this an ampersand in a format would arrive as half a
    // character reference.
    expect(editor()->dateTime()->dateTimeFormats(['stamp' => '\\A \\& \\B'])->getDateTimeForJs('stamp'))
        ->toBe('A &amp; B');
});

it('writes the month in the application language', function (): void {
    // Carbon's own Laravel provider keeps a global locale in step with the application's,
    // and this suite registers providers by hand and never gets it - so an ambient locale
    // would read English here and German in an application. The locale is asked for
    // explicitly, and this is the test that would go red if it stopped being.
    // March rather than today: September is spelled the same in both languages, so half the
    // year this assertion would pass while reading English.
    Carbon::setTestNow(Carbon::parse('2026-03-04 12:00:00'));
    app()->setLocale('de');

    expect(DateTimeFormats::render('F'))->toBe('März')
        ->and(DateTimeFormats::render('l'))->toBe('Mittwoch')
        ->and(DateTimeFormats::render('F', 'en'))->toBe('March');

    Carbon::setTestNow();
});

it('inserts a rendered zero rather than reading it as no answer', function (): void {
    // `G` between midnight and one, `w` on a Sunday and `z` on New Year's Day all render to
    // the string "0", which is falsy in JavaScript - so a handler guarding on `text &&`
    // would silently insert nothing for one hour out of twenty-four.
    Carbon::setTestNow(Carbon::parse('2026-03-01 00:30:00'));

    try {
        expect(editor()->dateTime()->dateTimeFormats(['hour' => 'G'])->getDateTimeForJs('hour'))
            ->toBe('0')
            ->and(editor()->dateTime()->getTools()['insertDate']->getJsHandler())
            ->toContain("text != null && text !== ''")
            ->and(editor()->dateTime()->getTools()['insertDate']->getJsHandler())
            ->not->toContain('(text) => text &&');
    } finally {
        Carbon::setTestNow();
    }
});

it('answers rather than throwing for a key of the wrong shape', function (): void {
    // The parameter is whatever a request carried. An array where a key was expected belongs
    // in the same "answered with nothing" branch as an unknown key, not in a 500.
    expect(editor()->dateTime()->getDateTimeForJs(['a' => 1]))->toBeNull()
        ->and(editor()->dateTime()->getDateTimeForJs(null))->toBeNull();
});

it('survives being asked outside a schema', function (): void {
    // `Component::$container` is a typed property with no default, so reading it on a field
    // that was never put in a schema is a fatal error. Every other question this field
    // answers can be asked of it standing on its own, and so must this one - the shipped
    // formats are all inherited, so this fired on the very first call.
    // Only the field's own answer is asked for here: `getTools()` reaches the container
    // through Filament itself and has never worked outside a schema, which is not this
    // feature's business to change.
    $loose = AdvancedRichEditor::make('content')->dateTime();

    expect($loose->getDateTimeFormats())->toBe([])
        ->and($loose->getDateTimeForJs('date'))->toBeNull()
        ->and($loose->dateTimeFormats(['stamp' => 'Y'])->getDateTimeFormats())
        ->toBe(['stamp' => 'Y']);
});

it('keeps a configured key out of the namespace of the dropdown label', function (): void {
    // The per-format labels sit under `formats`; beside `label` a format keyed `label` would
    // resolve to the trigger's text and call itself "Date and time".
    $tools = editor()->dateTime()->dateTimeFormats(['label' => 'Y'])->getTools();

    expect((string) $tools['insertLabel']->getLabel())->toBe(Carbon::now()->format('Y'))
        ->and((string) editor()->dateTime()->getTools()['insertDate']->getLabel())->toBe('Date');
});

it('reads a time out of a format, because that is all there is to read', function (): void {
    // Which of the two a format is decides whether the display timezone applies to it, and
    // a format is only characters. A backslash escapes the letter after it, exactly as it
    // does in `date()`, so the `t` in `\t\o\d\a\y` is a letter rather than a count of days.
    expect(DateTimeFormats::carriesTime('H:i'))->toBeTrue()
        ->and(DateTimeFormats::carriesTime('c'))->toBeTrue()
        ->and(DateTimeFormats::carriesTime('U'))->toBeTrue()
        ->and(DateTimeFormats::carriesTime('j. F Y'))->toBeFalse()
        ->and(DateTimeFormats::carriesTime('\\t\\o\\d\\a\\y: j.n.Y'))->toBeFalse()
        ->and(DateTimeFormats::carriesTime('\\H j.n.Y'))->toBeFalse()
        // `date()` calls these zone tokens; `translatedFormat()` emits the bare letter, so
        // they say nothing about a time and must not move a date-only format a day.
        ->and(DateTimeFormats::carriesTime('j. F Y e'))->toBeFalse()
        ->and(DateTimeFormats::carriesTime('j. F Y p'))->toBeFalse()
        ->and(DateTimeFormats::carriesTime('j. F Y T'))->toBeTrue();
});

it('shows a time in the display timezone and leaves a date alone', function (): void {
    // An offset applied to a date moves it a whole day either side of midnight, which is
    // why Filament exempts date-only values from the display timezone. This follows it.
    config()->set('app.timezone', 'UTC');
    FilamentTimezone::set('Australia/Sydney');
    Carbon::setTestNow(Carbon::parse('2026-03-04 23:30:00', 'UTC'));

    // Both are process-wide, so they are put back on the failing path too. Without the
    // `finally` a regression here would leave a frozen clock and a foreign timezone behind
    // and redden whatever ran next, hiding the one test that actually broke.
    try {
        expect(DateTimeFormats::render('H:i'))->toBe('10:30')
            ->and(DateTimeFormats::render('Y-m-d'))->toBe('2026-03-04')
            // `e` is a zone token to `date()` and a bare letter to `translatedFormat()`, so
            // it must not drag a date-only format into the display timezone.
            ->and(DateTimeFormats::render('Y-m-d e'))->toBe('2026-03-04 e');
    } finally {
        Carbon::setTestNow();
        FilamentTimezone::set(null);
    }
});

it('expands to a dropdown of whatever the field offers', function (): void {
    expect(toolbarShape(editor()->dateTime()->toolbarButtons([['dateTime']])))
        ->toBe([['dropdown:insertDate,insertTime,insertDateTime']])
        // A token that expands to nothing leaves an empty group, and an empty group is
        // dropped rather than drawn as a gap on the bar. Switched off is one of the two
        // ways to get there, and so is a list configured down to nothing.
        ->and(toolbarShape(editor()->toolbarButtons([['dateTime']])))
        ->toBe([])
        ->and(toolbarShape(editor()->dateTime()->toolbarButtons([['dateTime']])->dateTimeFormats([])))
        ->toBe([]);
});

it('reaches the slash menu through the same token', function (): void {
    // Expanded rather than written out, so a project that adds a fourth format gets it in
    // the menu without naming it in the group as well.
    $names = array_column(
        SlashMenu::for(editor()->dateTime()->dateTimeFormats(['stamp' => 'Y']))['groups'][1]['items'],
        'name',
    );

    expect($names)->toContain('insertStamp')
        ->and(array_column(SlashMenu::for(editor())['groups'][1]['items'], 'name'))
        ->not->toContain('insertDate');
});

it('ships with no button anywhere, and the slash menu as the way in', function (): void {
    // Most documents never date a paragraph, and the bar is finite. The tools are
    // registered - so they can be configured in, and so the names work - but nothing on the
    // shipped bar spends a slot on them.
    expect(editor()->dateTime()->getMoreTools())->not->toContain('insertDateTime')
        ->and(array_merge(...toolbarShape(editor()->dateTime())))->not->toContain('insertDate');
});

it('goes away with its switch, in both halves', function (): void {
    expect(pluginNames(editor()->dateTime(false)))->not->toContain(DateTimePlugin::class)
        ->and(pluginNames(editor()->dateTime()))->toContain(DateTimePlugin::class);

    config()->set('filament-advanced-rich-editor.date_time.enabled', true);

    expect(editor()->hasDateTime())->toBeTrue()
        ->and(editor()->dateTime(false)->hasDateTime())->toBeFalse();
});

it('stores nothing, so it declares no extension on either side', function (): void {
    // A date is text. Nothing to parse, nothing for the sanitiser to allow, nothing for the
    // renderer to be taught - and `insertContent` is TipTap's own command, so there is no
    // JavaScript module either. It is the only tool in the package without one.
    expect(DateTimePlugin::make()->getTipTapPhpExtensions())->toBe([])
        ->and(DateTimePlugin::make()->getTipTapJsExtensions())->toBe([])
        ->and(DateTimePlugin::make()->getEditorActions())->toBe([]);
});

it('is a token like every other switchable feature', function (): void {
    expect(array_keys(ToolbarLayout::tokens()))->toContain('dateTime');
});
