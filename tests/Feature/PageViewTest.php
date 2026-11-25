<?php

declare(strict_types=1);

use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\EditorFormComponent;
use Livewire\Livewire;

/**
 * The page view: the field drawn as a sheet of paper. The sheet is the stylesheet's, from
 * what the field writes on its wrapper, and the zoom is `page-view.js` - see
 * `tests/js/page-view.test.js`. What is tested here is the sheet the field asks for and that
 * it arrives on the page.
 */
it('ships off, with nothing loaded', function (): void {
    expect(editor()->hasPageView())->toBeFalse()
        ->and(editor()->getPageSheet())->toBeNull()
        ->and(editor()->getPageViewSettingsForJs())->toBeNull()
        ->and(editor()->getPageViewStyles())->toBe([])
        ->and(editor()->getTipTapJsExtensions())->not->toContain(FilamentAsset::getScriptSrc('advanced-rich-editor/page-view', 'kisame76/filament-advanced-rich-editor'));
});

it('draws the sheet the PDF export prints on, where no format is given', function (): void {
    config()->set('filament-advanced-rich-editor.pdf.page', 'a5');
    config()->set('filament-advanced-rich-editor.pdf.margins', [15]);

    $field = editor()->page();

    expect($field->hasPageView())->toBeTrue()
        ->and($field->getPageViewSettingsForJs())->toMatchArray([
            'width' => 148.0,
            'height' => 210.0,
            'margins' => [15.0, 15.0, 15.0, 15.0],
        ])
        ->and($field->getPageViewSettingsForJs()['labels']['sheet'] ?? null)->toBe('A5 · Portrait')
        ->and($field->getTipTapJsExtensions())->toContain(FilamentAsset::getScriptSrc('advanced-rich-editor/page-view', 'kisame76/filament-advanced-rich-editor'));
});

it('takes a format, an orientation and margins of the field\'s own', function (): void {
    $field = editor()->page('letter')->pageLandscape()->pageMargins(25, 20);

    expect($field->getPageViewStyles())->toBe([
        '--fi-arte-page-width' => '279.4mm',
        '--fi-arte-page-height' => '215.9mm',
        '--fi-arte-page-margin-top' => '25mm',
        '--fi-arte-page-margin-right' => '20mm',
        '--fi-arte-page-margin-bottom' => '25mm',
        '--fi-arte-page-margin-left' => '20mm',
    ])
        ->and($field->getPageViewSettingsForJs()['labels']['sheet'] ?? null)->toBe('Letter · Landscape');
});

it('reads the switch from the config file, and lets a field turn it off', function (): void {
    config()->set('filament-advanced-rich-editor.page_view', 'a3');

    expect(editor()->getPageSheet()?->width)->toBe(297.0)
        ->and(editor()->page(false)->hasPageView())->toBeFalse();
});

it('names the zoom in the language of whoever looks at it', function (): void {
    app()->setLocale('de');

    expect(editor()->page()->getPageViewSettingsForJs()['labels'] ?? [])->toMatchArray([
        'zoomIn' => 'Vergrößern',
        'fit' => 'An Breite anpassen',
        'sheet' => 'A4 · Hochformat',
    ]);
});

it('refuses a format it does not know', function (): void {
    editor()->page('a7');
})->throws(InvalidArgumentException::class, 'Unknown page format [a7]');

it('arrives on the page as a sheet before any script has run', function (): void {
    config()->set('filament-advanced-rich-editor.page_view', true);

    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="utf-8"?>'.Livewire::test(EditorFormComponent::class)->html(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);

    $wrapper = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " fi-arte-paged ")]')->item(0);
    $editor = $xpath->query('//*[@data-arte-page]')->item(0);

    assert($wrapper instanceof DOMElement && $editor instanceof DOMElement);

    expect($wrapper->getAttribute('style'))->toContain('--fi-arte-page-width: 210mm')->toContain('--fi-arte-page-margin-left: 20mm')
        ->and($editor->getAttribute('x-ref'))->toBe('editor')
        ->and(json_decode($editor->getAttribute('data-arte-page'), true)['width'] ?? null)->toBe(210);
});
