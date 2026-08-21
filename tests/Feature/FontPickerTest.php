<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Fonts;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarFontPicker;

beforeEach(function (): void {
    // A stand-in for the project's own font directory, laid out the two ways a project
    // usually lays one out: a folder per family, and loose files named after theirs.
    $this->fontDirectory = sys_get_temp_dir().'/arte-fonts-'.getmypid();

    @mkdir($this->fontDirectory.'/Inter', recursive: true);

    touch($this->fontDirectory.'/Inter/Inter-Regular.woff2');
    touch($this->fontDirectory.'/Inter/Inter-BoldItalic.woff2');
    touch($this->fontDirectory.'/Fraunces-Light.woff');

    config()->set('filament-advanced-rich-editor.fonts.directory', $this->fontDirectory);
});

afterEach(function (): void {
    foreach (glob($this->fontDirectory.'/*/*') ?: [] as $file) {
        @unlink($file);
    }

    foreach (glob($this->fontDirectory.'/*') ?: [] as $entry) {
        is_dir($entry) ? @rmdir($entry) : @unlink($entry);
    }

    @rmdir($this->fontDirectory);
});

it('offers the fonts that are in the project, not a list of hopes', function (): void {
    $labels = array_column(Fonts::for(editor()), 'label');

    expect($labels)->toContain('Inter')
        ->toContain('Fraunces')
        // The generic stacks need no file to resolve, so they are always there.
        ->toContain('System');
});

it('lists everything in one alphabetical run', function (): void {
    // No headings, no sections: where a typeface came from says nothing about where
    // someone looks for it.
    $labels = array_column(Fonts::for(editor()), 'label');
    $sorted = $labels;
    usort($sorted, strnatcasecmp(...));

    expect($labels)->toBe($sorted)
        ->and(Fonts::for(editor())[0])->not->toHaveKey('group');
});

it('names a family once, however many files it has', function (): void {
    $inter = array_values(array_filter(Fonts::for(editor()), fn (array $font): bool => $font['label'] === 'Inter'));

    expect($inter)->toHaveCount(1)
        ->and($inter[0]['stack'])->toStartWith('"Inter"');
});

it('writes a face for every file so the browser can actually draw it', function (): void {
    $css = Fonts::styleSheet(editor());

    expect(substr_count($css, '@font-face'))->toBe(3)
        // Weight and style are read out of the file name, which is the only place a
        // project writes them down.
        ->and($css)->toContain('font-weight: 700')
        ->and($css)->toContain('font-style: italic')
        ->and($css)->toContain('font-weight: 300')
        ->and($css)->toContain("format('woff2')");
});

it('lets a project add families it loads somewhere else', function (): void {
    config()->set('filament-advanced-rich-editor.fonts.families', ['Brand Sans' => '"Brand Sans", system-ui, sans-serif']);

    $fonts = Fonts::for(editor());
    $brand = array_values(array_filter($fonts, fn (array $font): bool => $font['label'] === 'Brand Sans'));

    // Nothing on the server can prove that one is loaded, so the browser is asked before
    // it is offered - the flag is what says so.
    expect($brand[0]['verify'])->toBeTrue()
        ->and(array_values(array_filter($fonts, fn (array $font): bool => $font['label'] === 'Inter'))[0]['verify'])
        ->toBeFalse();
});

it('lets a field say the list itself', function (): void {
    $fonts = Fonts::for(editor()->fonts(['Only This' => '"Only This", serif']));

    expect(array_column($fonts, 'label'))->toBe(['Only This']);
});

it('drops the picker when there is nothing to pick', function (): void {
    config()->set('filament-advanced-rich-editor.fonts.directory', null);
    config()->set('filament-advanced-rich-editor.fonts.generic', false);

    expect(Fonts::for(editor()))->toBe([])
        ->and(toolbarItem(editor(), 'fontFamily'))->toBeNull();
});

it('refuses a family that is trying to be a stylesheet', function (): void {
    // The value ends up in a `style` attribute, which Filament's sanitiser passes through
    // untouched. Nothing else checks it, so it is checked here.
    expect(ToolbarFontPicker::sanitise('"Inter", sans-serif'))->toBe('"Inter", sans-serif')
        ->and(ToolbarFontPicker::sanitise('red; background: url(evil)'))->toBeNull()
        ->and(ToolbarFontPicker::sanitise('expression(alert(1))'))->toBeNull()
        ->and(ToolbarFontPicker::sanitise(str_repeat('a', 300)))->toBeNull();
});
