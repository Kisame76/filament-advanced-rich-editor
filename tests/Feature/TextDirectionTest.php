<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\TextDirectionPlugin;

it('registers both directions without putting them in anyone\'s way', function (): void {
    // `dir` decides which way the text runs, which only shows in a document mixing scripts.
    // In one left-to-right language it does nothing the alignment dropdown does not, so the
    // buttons are not shipped - naming them in a toolbar is what asks for them.
    expect(editor()->getTools())->toHaveKeys(['ltr', 'rtl'])
        ->and(editor()->getMoreTools())->not->toContain('ltr')
        ->and(toolbarShape(editor()))->not->toContain(['ltr']);

    expect(resolvedButtonNames(toolbarDropdown(editor()->moreTools(['ltr', 'rtl']), 'ltr')))
        ->toBe(['ltr', 'rtl']);
});

it('writes the direction with its own command', function (): void {
    $tools = editor()->getTools();

    // TipTap's bundled `setTextDirection` writes `dir` onto every node in the selection,
    // including the ones that never declared the attribute, where ProseMirror throws. The
    // package's command walks the declared types only.
    expect($tools['rtl']->getJsHandler())->toContain("toggleBlockDirection('rtl')")
        ->and($tools['ltr']->getJsHandler())->toContain("toggleBlockDirection('ltr')")
        ->and($tools['rtl']->getActiveJsExpression())->toContain("dir: 'rtl'");
});

it('keeps the direction across the php round trip', function (): void {
    $html = '<p dir="rtl">مرحبا</p>';

    $rendered = RichContentRenderer::make($html)->plugins([TextDirectionPlugin::make()])->toHtml();

    expect($rendered)->toContain('dir="rtl"');
});

it('loses the direction without the plugin, which is why it exists', function (): void {
    // Content is re-parsed on every hydration and the parser only keeps attributes that
    // something declared, so this is also what a field with the tools switched off does.
    expect(RichContentRenderer::make('<p dir="rtl">مرحبا</p>')->toHtml())->not->toContain('dir=');
});

it('refuses a direction that is not one', function (): void {
    $rendered = RichContentRenderer::make('<p dir="javascript:alert(1)">x</p>')
        ->plugins([TextDirectionPlugin::make()])
        ->toHtml();

    expect($rendered)->not->toContain('javascript')
        ->and($rendered)->not->toContain('dir=');
});

it('carries the direction on the blocks that can hold one', function (): void {
    $html = '<h2 dir="rtl">عنوان</h2><blockquote dir="rtl"><p dir="rtl">اقتباس</p></blockquote>';

    $rendered = RichContentRenderer::make($html)->plugins([TextDirectionPlugin::make()])->toHtml();

    expect(substr_count($rendered, 'dir="rtl"'))->toBe(3);
});

it('drops both tools when a field turns the direction off', function (): void {
    $editor = editor()->textDirection(false);

    expect($editor->getTools())->not->toHaveKey('ltr')
        ->and($editor->getTools())->not->toHaveKey('rtl')
        // Named in a toolbar it is still dropped, because nothing registered it.
        ->and(resolvedButtonNames(toolbarDropdown($editor->moreTools(['emoji', 'rtl']), 'emoji')))->toBe(['emoji']);

    config()->set('filament-advanced-rich-editor.text_direction', false);

    expect(editor()->hasTextDirection())->toBeFalse();
});
