<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\AccessibilityPlugin;

it('is shipped off, and puts nothing on a toolbar until it is asked for', function (): void {
    // A review tool rather than a way of writing, and a contrast rule measured against a
    // page this package has to be told the colour of: on by default, every project whose
    // pages are not white would be handed findings that are wrong.
    expect(editor()->hasAccessibility())->toBeFalse()
        ->and(editor()->getTools())->not->toHaveKey('accessibility')
        ->and(array_merge(...toolbarShape(editor())))->not->toContain('accessibility');
});

it('sits with the tools that are about the document rather than about the text', function (): void {
    // The shipped tools menu keeps a place for it between searching and the source view, so
    // switching the check on is the whole of what a project has to do - and it lands in the
    // menu rather than as another icon in the corner.
    expect(editor()->getToolsMenu())->toBe(['find', 'accessibility', 'statistics', 'sourceCode', 'help'])
        ->and(resolvedButtonNames(toolbarItem(editor()->accessibility(), toolsShape())))
        ->toBe(['find', 'accessibility', 'statistics', 'help']);
});

it('opens the report from the button', function (): void {
    expect(editor()->accessibility()->getTools()['accessibility']->getJsHandler())->toContain('openAccessibilityReport()');
});

it('stores nothing and parses nothing back', function (): void {
    // A check marks no document, and a picture that was given alt text is an ordinary
    // picture by the time it is saved.
    $plugin = AccessibilityPlugin::make();

    expect($plugin->getTipTapPhpExtensions())->toBe([])
        ->and($plugin->getEditorActions())->toBe([])
        ->and($plugin->getTipTapJsExtensions())->toHaveCount(1)
        ->and($plugin->getTipTapJsExtensions()[0])->toContain('accessibility');
});

it('hands over everything the browser cannot decide for itself', function (): void {
    $settings = editor()->accessibility()->getAccessibilitySettingsForJs();

    expect($settings)->toHaveKeys(['rules', 'weakPhrases', 'threshold', 'largeThreshold', 'background', 'text', 'palette', 'labels', 'icons'])
        ->and($settings['threshold'])->toBe(4.5)
        ->and($settings['largeThreshold'])->toBe(3.0)
        // What the editor cannot know, because it belongs to the front end.
        ->and($settings['background'])->toBe('#ffffff')
        // The second assumption, and the one that lets a chosen background be measured at
        // all: what the page writes in where nobody chose a colour.
        ->and($settings['text'])->toBe('#18181b')
        ->and($settings['labels']['rules'])->toHaveCount(7)
        ->and($settings['labels']['rules']['missing_alt'])->toBe('Image without alt text')
        // Two numbers in one string, because the second is what makes the first mean
        // anything - and where they sit in it is a question about the language.
        ->and($settings['labels']['ratio'])->toContain(':ratio')
        ->and($settings['labels']['ratio'])->toContain(':needed')
        ->and($settings['icons']['close'])->toContain('<svg')
        ->and($settings['icons']['grip'])->toContain('<svg');
});

it('carries them into the markup, which is the only way the extension has to them', function (): void {
    $compiled = Blade::compileString(file_get_contents(__DIR__.'/../../resources/views/rich-editor.blade.php'));

    expect($compiled)->toContain('data-arte-accessibility')
        ->and($compiled)->toContain('getAccessibilitySettingsForJs');
});

it('sends the weak link phrases in the language the panel is read in', function (): void {
    // "Click here" is a fact about English and not about the web: a list shipped in one
    // language finds nothing in another and calls the document fine.
    expect(AccessibilityPlugin::getWeakPhrases())->toContain('click here', 'read more');

    app()->setLocale('de');

    expect(AccessibilityPlugin::getWeakPhrases())->toContain('hier klicken', 'weiterlesen')
        ->and(AccessibilityPlugin::getWeakPhrases())->not->toContain('click here');
});

it('adds the phrases a project named to the shipped list rather than replacing it', function (): void {
    config()->set('filament-advanced-rich-editor.accessibility.weak_link_phrases', ['see the pdf']);

    expect(AccessibilityPlugin::getWeakPhrases())->toContain('see the pdf', 'click here');
});

it('turns the palette into colours, because the document only stores their names', function (): void {
    withStyles([]);
    config()->set('filament-advanced-rich-editor.colors.text_palette', [
        'ink' => ['label' => 'Ink', 'color' => '#18181b', 'dark' => '#f4f4f5'],
    ]);

    // Only the light half: a document rendered in both themes is two questions, and
    // answering one of them twice is a panel listing everything twice.
    expect(editor()->getAccessibilityPalette())->toBe(['ink' => '#18181b']);
});

it('asks all six rules unless a project names fewer', function (): void {
    expect(editor()->getAccessibilityRules())->toBe(AccessibilityPlugin::RULES)
        ->and(editor()->accessibilityRules(['missing_alt'])->getAccessibilityRules())->toBe(['missing_alt']);
});

it('refuses a rule name nobody implements', function (): void {
    // A name in a config file that quietly does nothing is worse than one that is dropped:
    // the panel would say the document is fine because a rule nobody wrote found nothing.
    expect(editor()->accessibilityRules(['missing_alt', 'made_up'])->getAccessibilityRules())
        ->toBe(['missing_alt']);
});

it('reads the numbers and the rules out of the config file', function (): void {
    config()->set('filament-advanced-rich-editor.accessibility.threshold', 7);
    config()->set('filament-advanced-rich-editor.accessibility.background', '#18181b');
    config()->set('filament-advanced-rich-editor.accessibility.rules', ['weak_contrast']);
    config()->set('filament-advanced-rich-editor.accessibility.enabled', true);

    $settings = editor()->getAccessibilitySettingsForJs();

    expect($settings['threshold'])->toBe(7.0)
        ->and($settings['background'])->toBe('#18181b')
        ->and($settings['rules'])->toBe(['weak_contrast']);
});

it('drops the tool and the panel when a field turns the check off', function (): void {
    $editor = editor()->accessibility()->accessibility(false);

    expect($editor->getTools())->not->toHaveKey('accessibility')
        ->and($editor->getAccessibilitySettingsForJs())->toBeNull()
        ->and(pluginNames($editor))->not->toContain(AccessibilityPlugin::class);
});

it('takes its name off the bar as well, not only out of the tool list', function (): void {
    // An unregistered name left standing in a toolbar group is not a missing button, it is
    // a `LogicException` out of the view - so the switch has to reach the layout too.
    expect(array_merge(...toolbarShape(editor()->accessibility()->accessibility(false))))->not->toContain('accessibility');
});

it('turns the check on from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.accessibility.enabled', true);

    expect(editor()->hasAccessibility())->toBeTrue()
        // And the place the shipped tools menu reserved for it fills itself in.
        ->and(resolvedButtonNames(toolbarItem(editor(), toolsShape())))->toContain('accessibility');
});

it('lets a field ask for it where a project did not', function (): void {
    expect(editor()->accessibility()->hasAccessibility())->toBeTrue();
});

it('draws its icons through the registry, so a project can swap them', function (): void {
    expect(Icons::get('accessibility'))->toBe('heroicon-o-clipboard-document-check');

    config()->set('filament-advanced-rich-editor.icons.accessibility', 'heroicon-o-eye');

    expect(Icons::get('accessibility'))->toBe('heroicon-o-eye');
});
