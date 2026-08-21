<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;

it('offers the embed button by default', function (): void {
    expect(toolbarGroup(editor(), 'embed'))->toContain('image')
        ->and(editor()->getTools())->toHaveKey('embed');
});

it('takes the button away where the field says so', function (): void {
    expect(editor()->embeds(false)->getTools())->not->toHaveKey('embed');
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
