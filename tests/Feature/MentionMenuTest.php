<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\MentionProvider;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Illuminate\Support\Facades\Blade;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\MentionProvider as RowsMentionProvider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\MentionRow;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\MentionMenuPlugin;
use Livewire\Attributes\Renderless;

/**
 * The editor half of a mention: whose menu opens when a trigger is typed.
 *
 * The rows themselves are drawn in the browser and are asserted in
 * `tests/js/mention.test.js`. What is decided here is whether this package's extension is
 * loaded at all, and whether the script is handed everything it needs - a menu that
 * replaces Filament's own and then cannot reach the field would be a field that mentions
 * nobody.
 */
function mentioningEditor(): AdvancedRichEditor
{
    return editor()->mentions([
        MentionProvider::make('@')->items(['2' => 'Ada Lovelace']),
        MentionProvider::make('#')->items(['7' => 'Backend'])->getSearchResultsUsing(fn (): array => []),
    ]);
}

function hasMentionPlugin(AdvancedRichEditor $editor): bool
{
    foreach ($editor->getPlugins() as $plugin) {
        if ($plugin instanceof MentionMenuPlugin) {
            return true;
        }
    }

    return false;
}

it('hands the script the triggers the field was given', function (): void {
    $menu = mentioningEditor()->getMentionMenuForJs();

    expect($menu)->toHaveKeys(['key', 'triggers'])
        ->and(array_column($menu['triggers'], 'char'))->toBe(['@', '#'])
        // The trigger description is Filament's own, so a provider written against Filament
        // needs nothing done to it to work here.
        ->and($menu['triggers'][0])->toHaveKeys([
            'char', 'items', 'isSearchable', 'extraAttributes',
            'noOptionsMessage', 'noSearchResultsMessage', 'searchPrompt', 'searchingMessage',
        ])
        // A list of rows whatever Filament sent - an `id => label` map before 5.8.3, `{id, label}`
        // pairs since - because a map loses its order in the browser once its ids are numbers.
        ->and($menu['triggers'][0]['items'])->toBe([['id' => '2', 'label' => 'Ada Lovelace']])
        // Which trigger searches on the server and which was handed its whole list decides
        // whether the menu asks at all.
        ->and($menu['triggers'][0]['isSearchable'])->toBeFalse()
        ->and($menu['triggers'][1]['isSearchable'])->toBeTrue();
});

it('answers a search with rows, in the order the provider gave them', function (): void {
    // Filament 5.8.3 sends its script `{id, label}` pairs and types the conversion for labels
    // only, so a row with a picture in it was a TypeError there and the menu said "no
    // results" for every search. The answer is built here for that reason.
    $editor = editor()->mentions([
        RowsMentionProvider::make('@')->getSearchResultsUsing(fn (): array => [
            MentionRow::make(9, 'Zed')->hint('zed@example.com'),
            MentionRow::make(2, 'Ada Lovelace')->avatar('/ada.jpg'),
        ]),
    ]);

    expect($editor->getMentionSearchResultsForJs('a', '@'))->toBe([
        ['id' => '9', 'label' => 'Zed', 'hint' => 'zed@example.com'],
        ['id' => '2', 'label' => 'Ada Lovelace', 'avatar' => '/ada.jpg'],
    ]);
});

it('answers a provider written against Filament with rows too, keeping its order', function (): void {
    $editor = editor()->mentions([
        MentionProvider::make('@')->getSearchResultsUsing(fn (): array => ['9' => 'Zed', '2' => 'Ada Lovelace']),
    ]);

    expect($editor->getMentionSearchResultsForJs('a', '@'))->toBe([
        ['id' => '9', 'label' => 'Zed'],
        ['id' => '2', 'label' => 'Ada Lovelace'],
    ]);
});

it('asks the provider the character belongs to, and the first one where none claims it', function (): void {
    $editor = editor()->mentions([
        MentionProvider::make('@')->getSearchResultsUsing(fn (): array => ['2' => 'Ada Lovelace']),
        MentionProvider::make('#')->getSearchResultsUsing(fn (): array => ['7' => 'Backend']),
    ]);

    expect($editor->getMentionSearchResultsForJs('', '#'))->toBe([['id' => '7', 'label' => 'Backend']])
        ->and($editor->getMentionSearchResultsForJs('', '!'))->toBe([['id' => '2', 'label' => 'Ada Lovelace']])
        ->and(editor()->getMentionSearchResultsForJs('', '@'))->toBe([]);
});

it('leaves the search to Filament where its own menu is drawn', function (): void {
    // Its script reads the shape its own version sends - a map before 5.8.3, pairs since - so
    // whatever the installed Filament answers is the only right answer.
    $providers = fn (): array => [
        MentionProvider::make('@')->getSearchResultsUsing(fn (): array => ['9' => 'Zed', '2' => 'Ada Lovelace']),
    ];

    $upstream = RichEditor::make('content')->container(testSchema())->mentions($providers());

    expect(editor()->mentionMenu(false)->mentions($providers())->getMentionSearchResultsForJs('a', '@'))
        ->toBe($upstream->getMentionSearchResultsForJs('a', '@'));
});

it('keeps the search reachable from the browser', function (): void {
    // Filament answers `callSchemaComponentMethod()` only for a method carrying the
    // attribute, and an override does not inherit it.
    $method = new ReflectionMethod(AdvancedRichEditor::class, 'getMentionSearchResultsForJs');

    expect($method->getAttributes(ExposedLivewireMethod::class))->toHaveCount(1)
        ->and($method->getAttributes(Renderless::class))->toHaveCount(1);
});

it('carries the key the script calls back with', function (): void {
    // Without it the menu could not search: the call is the same one the media browser
    // makes, and it addresses this field by its schema key.
    expect(mentioningEditor()->getMentionMenuForJs()['key'])->toBe(mentioningEditor()->getKey());
});

it('offers no menu on a field that mentions nothing', function (): void {
    // The extension carries Filament's own name and therefore takes its place. Doing that
    // on a field with no providers would swap a working node for one nobody configured.
    $editor = editor();

    expect($editor->getMentionMenuForJs())->toBeNull()
        ->and(hasMentionPlugin($editor))->toBeFalse();
});

it('gives Filament its own menu back when this one is switched off', function (): void {
    $editor = mentioningEditor()->mentionMenu(false);

    expect($editor->hasMentionMenu())->toBeFalse()
        ->and($editor->getMentionMenuForJs())->toBeNull()
        ->and(hasMentionPlugin($editor))->toBeFalse();
});

it('can be switched off for the whole project, and back on per field', function (): void {
    config()->set('filament-advanced-rich-editor.mentions.menu', false);

    expect(mentioningEditor()->hasMentionMenu())->toBeFalse()
        ->and(mentioningEditor()->mentionMenu()->hasMentionMenu())->toBeTrue();
});

it('loads the extension where there is something to mention', function (): void {
    expect(hasMentionPlugin(mentioningEditor()))->toBeTrue();
});

it('replaces Filament\'s extension rather than joining it', function (): void {
    // Filament keeps the last extension it is handed for a given name, so the script must
    // be named `mention` on the JavaScript side. Two extensions of that name would leave
    // which one wins to the order of an array.
    $source = file_get_contents(__DIR__.'/../../resources/js/mention.js');

    expect($source)->toContain("name: 'mention'")
        // And the class Filament configures its own node with, which is lost along with the
        // configuration when the extension is replaced.
        ->toContain('fi-fo-rich-editor-mention');
});

it('hands the menu to the script through the element the editor is mounted on', function (): void {
    // A TipTap extension has no other channel to the field it belongs to, and the closure
    // Filament passes to its own extension is gone once this one replaces it. Asserted on
    // the view rather than on rendered markup because the editor view is Livewire's to
    // render: it reads `$this->getId()`, which only a component has.
    $view = file_get_contents(__DIR__.'/../../resources/views/rich-editor.blade.php');

    expect($view)
        ->toContain('$mentionMenu = $getMentionMenuForJs();')
        ->toContain('data-arte-mentions="{{ json_encode($mentionMenu) }}"');
});

it('compiles the view that carries it', function (): void {
    $compiled = Blade::compileString(file_get_contents(__DIR__.'/../../resources/views/rich-editor.blade.php'));

    $file = tempnam(sys_get_temp_dir(), 'arte-mention-view-').'.php';
    file_put_contents($file, $compiled);

    exec('php -l '.escapeshellarg($file).' 2>&1', $output, $status);

    unlink($file);

    expect($status)->toBe(0, implode(PHP_EOL, $output));
});
