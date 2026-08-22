<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\MentionProvider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;

/**
 * Two mentions written with different triggers, exactly as the editor stores them.
 */
function mentionContent(): string
{
    return '<p>Ping <span data-type="mention" data-id="2" data-label="Ada Lovelace" data-char="@"></span>'
        .' and <span data-type="mention" data-id="7" data-label="Backend" data-char="#"></span>.</p>';
}

/**
 * @return array<int, MentionProvider>
 */
function mentionProviders(): array
{
    return [
        MentionProvider::make('@')
            ->items(['2' => 'Ada Lovelace'])
            ->url(fn (string $id): string => "/users/{$id}"),
        MentionProvider::make('#')
            ->items(['7' => 'Backend']),
    ];
}

it('gives a rendered mention a class to hang a style on', function (): void {
    // Filament renders a mention with `data-type` and `data-id` and nothing else. On a page
    // that makes it indistinguishable from any other span or link, so nothing can style it
    // - and a mention that looks like running text is not a mention.
    expect(array_column(mentionElements(AdvancedRichContentRenderer::make(mentionContent())->toHtml()), 'class'))
        ->toBe(['fi-arte-mention fi-arte-mention-at', 'fi-arte-mention fi-arte-mention-hash']);
});

it('keeps the trigger in the class, because the sanitiser strips the attribute', function (): void {
    // `data-char` is rendered and then removed: Filament's sanitiser allows `class`,
    // `data-id`, `data-type` and `style`, and nothing else that a mention carries. Without
    // the class, a page cannot tell a person from a category once it is rendered.
    $mentions = mentionElements(AdvancedRichContentRenderer::make(mentionContent())->toHtml());

    expect(array_column($mentions, 'char'))->toBe([null, null])
        ->and(array_column($mentions, 'class'))->each->toContain('fi-arte-mention-');
});

it('links a mention when the provider knows where it points', function (): void {
    // `url()` is only ever called while rendering - the editor never writes an href - so a
    // renderer that was not given the providers renders a mention nobody can click.
    $mentions = mentionElements(
        AdvancedRichContentRenderer::make(mentionContent())->mentions(mentionProviders())->toHtml(),
    );

    expect($mentions[0])->toMatchArray(['tag' => 'a', 'href' => '/users/2'])
        ->and($mentions[1])->toMatchArray(['tag' => 'span', 'href' => null]);
});

it('keeps mentions in the plain text', function (): void {
    // TipTap's text serialiser walks `content` and `text` and calls `renderText()` on
    // nothing, so an atom like a mention contributes an empty string - and the block
    // separator around it turns into a hole in the middle of the sentence.
    expect(AdvancedRichContentRenderer::make(mentionContent())->toText())
        ->toBe('Ping @Ada Lovelace and #Backend.');
});

it('reads a stale stored label from the provider', function (): void {
    // The document holds a copy of the name as it was when it was typed. The provider is
    // the only thing that knows the name now.
    $html = '<p>Ping <span data-type="mention" data-id="2" data-label="Ada L." data-char="@"></span></p>';

    $renderer = AdvancedRichContentRenderer::make($html)->mentions(mentionProviders());

    expect($renderer->toText())->toBe('Ping @Ada Lovelace')
        ->and(mentionElements($renderer->toHtml())[0]['text'])->toBe('@Ada Lovelace');
});

it('falls back to the id when nothing knows the label', function (): void {
    // Better a visible id than a hole where a name should be.
    $html = '<p>Ping <span data-type="mention" data-id="2" data-char="@"></span></p>';

    expect(AdvancedRichContentRenderer::make($html)->toText())->toBe('Ping @2');
});

it('renders no text at all for a record nobody has typed into', function (): void {
    // The same reasoning as `toUnsafeHtml()`: TipTap's serialiser reads the document before
    // it checks for one, so an empty column throws rather than rendering nothing.
    expect(AdvancedRichContentRenderer::make(null)->toText())->toBe('')
        ->and(AdvancedRichContentRenderer::make('')->toText())->toBe('');
});

it('leaves the text of a document without mentions alone', function (): void {
    expect(AdvancedRichContentRenderer::make('<p>Nothing to see</p>')->toText())
        ->toBe('Nothing to see');
});
