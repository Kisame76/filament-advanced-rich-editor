<?php

declare(strict_types=1);

use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Str;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\RenderLedger;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\SharedSettings;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\RepeatedEditorsFormComponent;
use Livewire\Livewire;

/**
 * The settings every editor on a page reads the same - the slash menu, both pickers' strings,
 * the find bar's - written out in full once a render instead of once an editor.
 *
 * Measured at 45 KB of escaped JSON per editor, 900 KB of a page of twenty in a repeater, and
 * none of it shrunk by gzip: the window ends long before the next editor repeats it. What the
 * browser does with the attributes - putting the settings back on every editor before any
 * extension reads them - is `tests/js/shared-settings.test.js`.
 */

/**
 * The `data-arte-*` attributes of every editor element on a page, in page order and unescaped.
 *
 * @return array<int, array<string, string>>
 */
function sharingEditors(string $html): array
{
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);

    $editors = [];

    foreach ((new DOMXPath($document))->query('//*[@x-ref="editor"]') as $element) {
        $attributes = [];

        foreach ($element->attributes as $attribute) {
            if (str_starts_with($attribute->name, 'data-arte-')) {
                $attributes[$attribute->name] = $attribute->value;
            }
        }

        $editors[] = $attributes;
    }

    return $editors;
}

const SHARED_ATTRIBUTES = ['data-arte-slash', 'data-arte-find', 'data-arte-emoji', 'data-arte-characters'];

it('writes the shared settings in full on the first editor of a render, and only names them on the rest', function (): void {
    $editors = sharingEditors(Livewire::test(RepeatedEditorsFormComponent::class)->html());
    $shared = json_decode($editors[0]['data-arte-shared'], true);

    expect($editors)->toHaveCount(3)
        ->and(array_keys($shared))->toBe(['arteSlash', 'arteFind', 'arteEmoji', 'arteCharacters'])
        ->and($editors[0])->toHaveKeys(SHARED_ATTRIBUTES)
        // The carrier says which settings it carries, so an editor that starts before it can
        // find it in the page.
        ->and(explode(' ', $editors[0]['data-arte-carries']))->toBe(array_values($shared));

    foreach ([1, 2] as $index) {
        expect(array_intersect(array_keys($editors[$index]), [...SHARED_ATTRIBUTES, 'data-arte-carries']))->toBe([])
            ->and(json_decode($editors[$index]['data-arte-shared'], true))->toBe($shared);
    }
})->skip(fn (): bool => toolActionsNameTheirEditor(), 'Filament before 5.10.2 names each editor in its slash menu, so no two are the same.');

it('carries the slash menu on every editor where Filament names the editor in its tools, and shares the rest', function (): void {
    // The menu runs the toolbar's tools, and before 5.10.2 those say which editor they belong
    // to. The find bar and the pickers' strings name nothing of the kind and are still shared.
    $editors = sharingEditors(Livewire::test(RepeatedEditorsFormComponent::class)->html());

    foreach ([1, 2] as $index) {
        expect($editors[$index])->toHaveKey('data-arte-slash')
            ->and(array_intersect(array_keys($editors[$index]), ['data-arte-find', 'data-arte-emoji', 'data-arte-characters']))->toBe([]);
    }
})->skip(fn (): bool => ! toolActionsNameTheirEditor(), 'Filament 5.10.2 and later write the same slash menu for every editor.');

it('names each setting by what it holds, so a name can only ever mean the one setting', function (): void {
    // A field set up differently gets a different name and carries its own: nothing is ever
    // looked up by the field it belongs to.
    $editors = sharingEditors(Livewire::test(RepeatedEditorsFormComponent::class)->html());

    foreach (json_decode($editors[0]['data-arte-shared'], true) as $name => $key) {
        $attribute = 'data-'.Str::kebab($name);

        expect(substr(hash('xxh128', $editors[0][$attribute]), 0, 16))->toBe($key);
    }
});

it('leaves the settings the editors do not share on every editor, as before', function (): void {
    // Small, and some of them belong to the one field - the draft is kept under its own key.
    $editors = sharingEditors(Livewire::test(RepeatedEditorsFormComponent::class)->html());

    expect(array_keys($editors[2]))->toContain('data-arte-drag-handle', 'data-arte-paste');
});

it('writes everything on every editor of an update', function (): void {
    // An editor already on the page is never drawn again, so the one an update makes carry the
    // settings may not carry them in the browser - and a field set up differently since would
    // name settings no editor there ever had. See `RenderLedger`.
    $test = Livewire::test(RepeatedEditorsFormComponent::class);

    $editors = sharingEditors($test->call('$refresh')->html());

    foreach ($editors as $editor) {
        expect($editor)->toHaveKeys(SHARED_ATTRIBUTES);
    }
});

it('counts each render on its own, and each component on its own', function (): void {
    $settings = ['arteSlash' => ['char' => '/'], 'arteFind' => ['label' => 'Find']];
    $page = new stdClass;
    $other = new stdClass;

    RenderLedger::rendering($page);
    RenderLedger::rendering($other);

    expect(SharedSettings::attributes($page, $settings))->toContain('data-arte-slash=')
        ->and(SharedSettings::attributes($page, $settings))->not->toContain('data-arte-slash=')
        ->and(SharedSettings::attributes($other, $settings))->toContain('data-arte-slash=');

    RenderLedger::rendering($page);

    expect(SharedSettings::attributes($page, $settings))->toContain('data-arte-slash=');
});

it('writes everything, every time, where it never saw a render begin', function (): void {
    // A field drawn some other way than by a Livewire render: nothing can be assumed to be on
    // the page already, so nothing is left out.
    $settings = ['arteSlash' => ['char' => '/']];
    $component = new stdClass;

    expect(SharedSettings::attributes($component, $settings))->toContain('data-arte-slash=')
        ->and(SharedSettings::attributes($component, $settings))->toContain('data-arte-slash=');
});

it('carries a setting that differs in full, beside one that is shared', function (): void {
    $component = new stdClass;

    RenderLedger::rendering($component);

    $first = SharedSettings::attributes($component, ['arteSlash' => ['char' => '/'], 'arteFind' => ['label' => 'Find']]);
    $second = SharedSettings::attributes($component, ['arteSlash' => ['char' => '#'], 'arteFind' => ['label' => 'Find']]);

    expect($second)->toContain('data-arte-slash=')
        ->and($second)->not->toContain('data-arte-find=')
        ->and($first)->toContain('data-arte-find=');
});

it('leaves out a setting that is switched off, and writes nothing where nothing is shared', function (): void {
    $component = new stdClass;

    expect(SharedSettings::attributes($component, ['arteSlash' => null, 'arteFind' => ['label' => 'Find']]))
        ->not->toContain('arteSlash')
        ->and(SharedSettings::attributes($component, ['arteSlash' => null, 'arteEmoji' => []]))->toBe('');
});

it('keeps a setting whole inside its attribute, quotes and markup and all', function (): void {
    $setting = ['icon' => '<svg class="a" data-x=\'b\'>&amp;</svg>'];
    $html = '<div x-ref="editor"'.SharedSettings::attributes(new stdClass, ['arteFind' => $setting]).'></div>';

    $editor = sharingEditors($html)[0];

    expect(json_decode($editor['data-arte-find'], true))->toBe($setting)
        ->and(json_decode($editor['data-arte-shared'], true))->toHaveKey('arteFind');
});

it('loads the script that puts them back only where there is something to share', function (): void {
    $script = FilamentAsset::getScriptSrc('advanced-rich-editor/shared-settings', 'kisame76/filament-advanced-rich-editor');

    expect(editor()->getTipTapJsExtensions())->toContain($script)
        ->and(editor()->find(false)->emoji(false)->characters(false)->getTipTapJsExtensions())->toContain($script)
        ->and(editor()->slashMenu(false)->find(false)->emoji(false)->characters(false)->getTipTapJsExtensions())
        ->not->toContain($script);
});
