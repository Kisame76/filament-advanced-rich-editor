<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Languages;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\LanguagePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\SlashMenu;

/**
 * The `lang` values in a fragment, in document order.
 *
 * @return array<int, string>
 */
function languages(string $html): array
{
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR);

    $found = [];

    foreach ((new DOMXPath($document))->query('//span[@lang]') as $span) {
        $found[] = $span->getAttribute('lang');
    }

    return $found;
}

it('renders a marked passage as a span carrying the language', function (): void {
    $stored = '<p>Der Titel lautet <span lang="fr">La Peste</span>.</p>';

    $rendered = AdvancedRichContentRenderer::make($stored)->toHtml();

    expect(languages($rendered))->toBe(['fr'])
        ->and($rendered)->toContain('La Peste');
});

it('renders without being told the field had languages on', function (): void {
    // The renderer declares the mark unconditionally: a passage somebody marked as French
    // is one a screen reader should still read in French, whatever this render was told.
    expect(languages(AdvancedRichContentRenderer::make('<p><span lang="la">sic</span></p>')->toHtml()))
        ->toBe(['la']);
});

it('survives the round trip a save goes through', function (): void {
    // Content is re-parsed on hydration and again on dehydration. A marking the parser
    // cannot read back is one that vanishes the first time the record is reopened.
    $stored = '<p>Ein <span lang="fr-ca">québécois</span> Wort.</p>';

    $once = editor()->getTipTapEditor()->setContent($stored)->getHTML();
    $twice = editor()->getTipTapEditor()->setContent($once)->getHTML();

    expect(languages($once))->toBe(['fr-ca'])
        ->and($twice)->toBe($once);
});

it('folds case, because lang is case-insensitive by specification', function (): void {
    // Kept apart, `fr-CA` and `fr-ca` would be two languages - and a document stored under
    // one spelling would light up no button for the other.
    expect(languages(AdvancedRichContentRenderer::make('<p><span lang="fr-CA">mot</span></p>')->toHtml()))
        ->toBe(['fr-ca']);
});

it('leaves a span alone that carries something which is not a language', function (): void {
    $rendered = AdvancedRichContentRenderer::make('<p><span lang="javascript:alert(1)">x</span></p>')->toHtml();

    expect(languages($rendered))->toBe([])
        // And the text inside it survives: refusing the attribute must not eat the words.
        ->and($rendered)->toContain('x');
});

it('keeps the attribute through the sanitiser', function (): void {
    // `lang` is on Symfony's safe list, exactly like `dir`, so nothing has to be allowed in
    // the application's own sanitiser config. This asserts that through the real sanitiser
    // rather than by reading the list.
    expect(languages(AdvancedRichContentRenderer::make('<p><span lang="es">hola</span></p>')->toHtml()))
        ->toBe(['es']);
});

it('registers one tool per language plus the way back out', function (): void {
    $tools = editor()->languageOptions(['fr', 'de' => 'Deutsch'])->getTools();

    expect(array_keys($tools))->toContain('languageNone', 'languageFr', 'languageDe')
        ->and(array_keys($tools))->not->toContain('languageEs')
        ->and($tools['languageFr']->getJsHandler())
        ->toBe("\$getEditor()?.chain().focus().toggleLanguage('fr').run()")
        ->and($tools['languageNone']->getJsHandler())
        ->toBe('$getEditor()?.chain().focus().unsetLanguage().run()');
});

it('names a language by its own name, and falls back to the code', function (): void {
    $tools = editor()->languageOptions(['fr', 'nb'])->getTools();

    // `fr` is one the package ships an endonym for; `nb` is not, so it is named by its code
    // rather than by a key nobody translated.
    expect((string) $tools['languageFr']->getLabel())->toBe('Français')
        ->and((string) $tools['languageNb']->getLabel())->toBe('nb');
});

it('lights the button up only for the language the caret is in', function (): void {
    $tool = editor()->getTools()['languageFr'];

    expect($tool->getActiveKey())->toBe('language')
        ->and($tool->getActiveOptions())->toBe(['code' => 'fr'])
        // Nothing to light up for "the language of the page": it is not a state.
        ->and(editor()->getTools()['languageNone']->hasActiveStyling())->toBeFalse();
});

it('is registered but is nowhere in the shipped layout', function (): void {
    // The same bargain the typeface picker and striking out make: most documents never quote
    // a foreign phrase, and a bar should not carry a control for something most of its
    // readers will never do. The tools exist, so naming the token is all it takes.
    expect(array_keys(editor()->getTools()))->toContain('languageFr')
        ->and(collect(toolbarShape(editor()))->flatten()->all())->not->toContain(languagesShape())
        ->and(toolbarGroupsShape([editor()->getFloatingToolbars()['paragraph']])[0])
        ->not->toContain(languagesShape());
});

it('goes into the bar over a selection when a project names the token', function (): void {
    // Where it belongs once somebody wants it: marking a passage starts with selecting one.
    $editor = editor()->textToolbarButtons([
        'bold', 'italic', 'link', 'language',
    ]);

    expect(toolbarGroupsShape([$editor->getFloatingToolbars()['paragraph']])[0])
        ->toBe(['bold', 'italic', 'link', languagesShape($editor)]);
});

it('goes onto the main toolbar just as readily', function (): void {
    // It is an ordinary token, so it resolves anywhere a token does.
    $editor = editor()->toolbarButtons([['bold', 'language']]);

    expect(toolbarShape($editor))->toBe([['bold', languagesShape($editor)]]);
});

it('refuses a code that could not be a class or an argument', function (): void {
    // A code travels into a tool name and into the JavaScript a button carries, which is
    // assembled by interpolation. Codes that do not fit are dropped rather than escaped.
    expect(array_column(Languages::normalize(["fr') || alert('", ' DE ', 'de', '1x', 'zh-Hant', 'a']), 'code'))
        ->toBe(['de', 'zh-hant']);
});

it('drops the tools and the extension when the field switched them off', function (): void {
    $editor = editor()->languages(false);

    expect(pluginNames($editor))->not->toContain(LanguagePlugin::class)
        ->and(array_keys($editor->getTools()))->not->toContain('languageFr')
        ->and(toolbarGroupsShape([$editor->getFloatingToolbars()['paragraph']])[0])
        ->not->toContain(languagesShape());
});

it('drops the dropdown when every configured language was dropped as unusable', function (): void {
    // A dropdown holding only the way out of a marking nobody can apply is a door onto a
    // wall.
    $editor = editor()->languageOptions(['1x', '']);

    expect($editor->getLanguageOptions())->toBe([])
        ->and(pluginNames($editor))->not->toContain(LanguagePlugin::class);
});

it('offers no entry in the slash menu', function (): void {
    // The menu opens where the caret sits with nothing selected, and marking a passage
    // there would mark nothing at all - the same rule that keeps bold out of it.
    $names = collect(SlashMenu::for(editor())['groups'])->pluck('items')->flatten(1)->pluck('name');

    expect($names)->not->toContain('languageFr', 'languageNone');
});

it('keeps a marking somebody already wrote, button or no button', function (): void {
    // The tools being off the bar is a decision about the bar. The mark is still declared,
    // so a passage marked in a document that arrived from somewhere else survives the
    // round trip a save goes through rather than being quietly stripped.
    $stored = '<p>Ein <span lang="fr">mot</span>.</p>';

    expect(languages(editor()->getTipTapEditor()->setContent($stored)->getHTML()))->toBe(['fr']);
});

it('reads its defaults from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.languages', [
        'enabled' => true,
        'values' => ['pt' => 'Português'],
    ]);

    expect(editor()->getLanguageOptions())->toBe([['code' => 'pt', 'label' => 'Português']]);

    config()->set('filament-advanced-rich-editor.languages.enabled', false);

    expect(editor()->hasLanguages())->toBeFalse()
        ->and(editor()->languages()->hasLanguages())->toBeTrue();
});
