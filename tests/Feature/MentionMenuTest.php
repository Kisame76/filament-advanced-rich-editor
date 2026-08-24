<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\MentionProvider;
use Illuminate\Support\Facades\Blade;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\MentionMenuPlugin;

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
        ->and($menu['triggers'][0]['items'])->toBe(['2' => 'Ada Lovelace'])
        // Which trigger searches on the server and which was handed its whole list decides
        // whether the menu asks at all.
        ->and($menu['triggers'][0]['isSearchable'])->toBeFalse()
        ->and($menu['triggers'][1]['isSearchable'])->toBeTrue();
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
