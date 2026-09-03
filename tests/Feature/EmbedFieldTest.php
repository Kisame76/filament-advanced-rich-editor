<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\SlashMenu;

/** Every name the slash menu offers, flattened out of its groups. */
function slashNamesOf(AdvancedRichEditor $editor): array
{
    return array_merge(...array_map(
        static fn (array $group): array => array_column($group['items'], 'name'),
        SlashMenu::for($editor)['groups'],
    ));
}

it('ships registered and on, but not on the bar', function (): void {
    // Off the shipped bar since the media browser arrived: that button covers video from
    // your own server, and two video-shaped buttons beside each other is one door too many
    // for a bar with a finite number of places. The tool itself is untouched - the slash
    // menu still finds it, and a bar that names it still gets it.
    expect(editor()->hasEmbeds())->toBeTrue()
        ->and(editor()->getTools())->toHaveKey('embed')
        ->and(array_merge(...toolbarShape(editor())))->not->toContain('embed')
        ->and(slashNamesOf(editor()))->toContain('embed');
});

it('goes back on a bar that names it', function (): void {
    expect(toolbarShape(editor()->toolbarButtons([['bold', 'embed']])))
        ->toBe([['bold', 'embed']]);
});

it('takes the button away where the field says so', function (): void {
    expect(editor()->embeds(false)->getTools())->not->toHaveKey('embed');
});

it('takes the name off the toolbar with the button', function (): void {
    // The tool and the name on the bar are two halves of one switch. Leaving the name
    // behind is not a stale button - the view throws on a name it cannot resolve, so the
    // field that switched embeds off is the field that no longer renders.
    expect(array_merge(...toolbarShape(editor()->embeds(false))))->not->toContain('embed');
});

it('reads whether embeds are offered from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.embed.enabled', false);

    expect(editor()->hasEmbeds())->toBeFalse()
        ->and(editor()->embeds()->hasEmbeds())->toBeTrue();
});

it('still renders a stored video after the button is taken away', function (): void {
    // Turning a feature off is not the same as deleting what was written while it was on.
    // The renderer declares the node whatever the field decided.
    $stored = '<div data-type="embed"><iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"></iframe></div>';

    config()->set('filament-advanced-rich-editor.embed.enabled', false);

    expect(AdvancedRichContentRenderer::make($stored)->toHtml())->toContain('<iframe');
});

it('tells the script the provider names and the cookie setting', function (): void {
    $settings = editor()->getEmbedSettingsForJs();

    expect($settings['nocookie'])->toBeTrue()
        ->and($settings['labels']['youtube'])->toBe('YouTube')
        ->and($settings['labels']['vimeo'])->toBe('Vimeo');
});

it('tells the script nothing while embeds are off', function (): void {
    expect(editor()->embeds(false)->getEmbedSettingsForJs())->toBeNull();
});
