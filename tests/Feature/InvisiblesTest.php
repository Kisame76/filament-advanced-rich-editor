<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\RichEditorTool;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\InvisiblesPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarLayout;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarPresets;

/**
 * The two views that show what a document holds and does not draw: the invisible
 * characters, and the outline of every block.
 *
 * What they show is worked out in the browser and tested in `tests/js/invisibles.test.js`.
 * What is tested here is the wiring - the switch, the place in the tools menu, the pressed
 * state that has to reach a screen reader, and the stylesheet that draws every sign.
 */
function invisiblesTool(string $name): RichEditorTool
{
    return collect(InvisiblesPlugin::make()->getEditorTools())
        ->firstWhere(fn (RichEditorTool $tool): bool => $tool->getName() === $name);
}

it('ships on, because looking changes nothing', function (): void {
    // Unlike the source view, neither view hands anybody a way past the toolbar, and unlike
    // the brush neither changes what the next click does: both only show what is there.
    expect(editor()->hasInvisibles())->toBeTrue()
        ->and(editor()->getTools())->toHaveKeys(['invisibleCharacters', 'blockOutlines']);
});

it('takes both tools away with the switch', function (): void {
    expect(editor()->invisibles(false)->getTools())
        ->not->toHaveKey('invisibleCharacters')
        ->not->toHaveKey('blockOutlines');
});

it('reads the switch from the config file, and lets a field overrule it', function (): void {
    config()->set('filament-advanced-rich-editor.invisibles', false);

    expect(editor()->hasInvisibles())->toBeFalse()
        ->and(editor()->invisibles()->hasInvisibles())->toBeTrue()
        ->and(editor()->invisibles(fn (): bool => true)->getTools())->toHaveKey('blockOutlines');
});

it('resolves its tokens away where it is switched off', function (): void {
    // A raw name in a group with no token behind it raises while the view renders and costs
    // the whole field, so a project that puts either on the bar needs the token.
    foreach (['invisibleCharacters', 'blockOutlines'] as $name) {
        expect(ToolbarLayout::tokens())->toHaveKey($name)
            ->and(ToolbarLayout::tokens()[$name](editor()))->toBe($name)
            ->and(ToolbarLayout::tokens()[$name](editor()->invisibles(false)))->toBe([]);
    }
});

it('sits in the tools menu with the other ways of looking at the document', function (): void {
    // After the preview and before the source view: three ways of seeing the same document,
    // and the source view last because it is the one that edits.
    $expected = ['find', 'accessibility', 'statistics', 'preview', 'invisibleCharacters', 'blockOutlines', 'sourceCode', 'help'];

    expect(config('filament-advanced-rich-editor.tools_menu'))->toBe($expected)
        ->and(editor()->getToolsMenu())->toBe($expected)
        ->and(resolvedButtonNames(toolbarItem(editor(), toolsShape())))
        ->toContain('invisibleCharacters', 'blockOutlines');

    foreach (['blog', 'default', 'full'] as $preset) {
        expect(ToolbarPresets::shipped()[$preset]['tools_menu'])->toBe($expected);
    }
});

it('switches each view through its own command, and gives the editor the caret back', function (): void {
    expect(invisiblesTool('invisibleCharacters')->getJsHandler())
        ->toBe('$getEditor()?.chain().focus().toggleInvisibleCharacters().run()')
        ->and(invisiblesTool('blockOutlines')->getJsHandler())
        ->toBe('$getEditor()?.chain().focus().toggleBlockOutlines().run()');
});

it('never says a view is on because its script is missing', function (): void {
    // `$getEditor()` is undefined until the editor has loaded, and the storage is undefined
    // on a field whose script never arrived. A strict comparison with `true` is false for
    // both, so a button cannot read as pressed for a view that is not there.
    expect(invisiblesTool('invisibleCharacters')->getActiveJsExpression())
        ->toBe('$getEditor()?.storage?.arteInvisibles?.characters === true')
        ->and(invisiblesTool('blockOutlines')->getActiveJsExpression())
        ->toBe('$getEditor()?.storage?.arteInvisibles?.blocks === true');
});

it('announces its state to a screen reader from inside the menu as well', function (): void {
    // Filament writes `aria-pressed` for a toggle on the bar, and not for a tool drawn as an
    // option of a dropdown - which is where these two ship. So it is written here, against
    // `editorUpdatedAt`: Alpine does not watch the editor's storage, and without the reactive
    // property the attribute would be written once at first paint and never again.
    foreach (['invisibleCharacters' => 'characters', 'blockOutlines' => 'blocks'] as $name => $view) {
        $tool = invisiblesTool($name);

        expect($tool->isToggle())->toBeTrue()
            ->and($tool->getExtraAttributes()['x-bind:aria-pressed'] ?? null)
            ->toBe("editorUpdatedAt && \$getEditor()?.storage?.arteInvisibles?.{$view} === true ? 'true' : 'false'");
    }
});

it('names both views in every language', function (): void {
    expect(invisiblesTool('invisibleCharacters')->getLabel())->toBe('Show invisible characters')
        ->and(invisiblesTool('blockOutlines')->getLabel())->toBe('Show blocks');

    app()->setLocale('de');

    expect(invisiblesTool('invisibleCharacters')->getLabel())->toBe('Formatierungszeichen anzeigen')
        ->and(invisiblesTool('blockOutlines')->getLabel())->toBe('Blöcke anzeigen');
});

it('draws its icons through the registry, so a project can swap them', function (): void {
    expect(Icons::get('invisible_characters'))->toBe('arte-pilcrow')
        ->and(Icons::get('block_outlines'))->toBe('arte-square-dashed')
        ->and(invisiblesTool('invisibleCharacters')->getIcon())->toBe('arte-pilcrow')
        ->and(invisiblesTool('blockOutlines')->getIcon())->toBe('arte-square-dashed')
        ->and(file_exists(__DIR__.'/../../resources/svg/pilcrow.svg'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../../resources/svg/square-dashed.svg'))->toBeTrue();
});

it('teaches the schema nothing, because nothing it shows is stored', function (): void {
    expect(InvisiblesPlugin::make()->getTipTapPhpExtensions())->toBe([])
        ->and(InvisiblesPlugin::make()->getEditorActions())->toBe([])
        ->and(InvisiblesPlugin::make()->getTipTapJsExtensions())->toHaveCount(1)
        ->and(InvisiblesPlugin::make()->getTipTapJsExtensions()[0])->toContain('invisibles');
});

it('ships the signs in the sheet the editor loads, not on every page', function (): void {
    // Every sign and outline is drawn by the script after somebody switches a view on, so
    // nothing of it can be on screen at first paint - the line the overlay sheet is drawn at.
    $overlays = file_get_contents(__DIR__.'/../../resources/css/filament-advanced-rich-editor-overlays.css');
    $page = file_get_contents(__DIR__.'/../../resources/css/filament-advanced-rich-editor.css');

    foreach ([
        '.fi-arte-invisible-space::after',
        '.fi-arte-invisible-hyphen::after',
        '.fi-arte-invisible-zero-width::after',
        '.fi-arte-invisible-break::before',
        '.fi-arte-invisible-paragraph::before',
        '.fi-arte-show-blocks',
    ] as $selector) {
        expect($overlays)->toContain($selector)
            ->and($page)->not->toContain($selector);
    }

    expect(file_get_contents(__DIR__.'/../../resources/dist/filament-advanced-rich-editor-overlays.css'))->toBe($overlays);
});

it('labels a block on its ::after, because ::before is already spoken for', function (): void {
    // Filament draws the placeholder of an empty editor on the first paragraph's `::before`,
    // and the callout draws its icon on its own. An outline label there would take one of
    // them away.
    $overlays = file_get_contents(__DIR__.'/../../resources/css/filament-advanced-rich-editor-overlays.css');

    preg_match_all('/\.fi-arte-show-blocks[^{]*::before/', $overlays, $before);

    expect($before[0])->toBe([])
        ->and($overlays)->toMatch('/\.fi-arte-show-blocks[^{]*\bp::after\s*\{[^}]*content:\s*\'P\'/s');
});
