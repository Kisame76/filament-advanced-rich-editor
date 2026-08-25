<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;

beforeEach(function (): void {
    withStyles([
        'lead' => ['label' => 'Lead', 'class' => 'text-lg text-slate-600'],
        'kicker' => ['label' => 'Kicker', 'class' => 'uppercase', 'scope' => 'inline'],
    ]);
});

/**
 * The markup a save writes, which is also the markup the next hydration parses.
 */
function stored(string $html): string
{
    return editor()->getTipTapEditor()->setContent($html)->getHTML();
}

it('renders a block style as the classes the project named', function (): void {
    $html = AdvancedRichContentRenderer::make('<p data-style="lead">Text</p>')->toHtml();

    expect($html)->toContain('class="text-lg text-slate-600"')
        ->toContain('Text')
        // The key is how the style is stored, not something the reader is given: the
        // sanitiser drops it, and the classes are what the page needs.
        ->not->toContain('data-style');
});

it('renders an inline style as the classes the project named', function (): void {
    $html = AdvancedRichContentRenderer::make('<p>a <span data-style="kicker">b</span> c</p>')->toHtml();

    expect($html)->toContain('class="uppercase"')
        ->toContain('>b<')
        ->not->toContain('data-style');
});

it('keeps the key in what is stored, so the classes can change later', function (): void {
    // The whole reason the key is written alongside the classes: a project that edits the
    // class list in its config finds every existing document following along, instead of
    // finding that they all quietly kept the old classes until the next save dropped them.
    expect(stored('<p data-style="lead">Text</p>'))->toContain('data-style="lead"');

    withStyles([
        'lead' => ['label' => 'Lead', 'class' => 'font-serif'],
    ]);

    expect(AdvancedRichContentRenderer::make(stored('<p data-style="lead">Text</p>'))->toHtml())
        ->toContain('class="font-serif"')
        ->not->toContain('text-lg');
});

it('recognises a style that arrived by its classes alone', function (): void {
    // Content pasted from a rendered page, or written before the key was introduced.
    expect(stored('<p class="text-lg text-slate-600">Text</p>'))
        ->toContain('data-style="lead"');
});

it('leaves a block alone that carries classes belonging to nobody', function (): void {
    expect(stored('<p class="something-else">Text</p>'))->not->toContain('data-style');
});

it('keeps a style through the round trip a save goes through', function (): void {
    $once = stored('<p data-style="lead">Text</p><p>b <span data-style="kicker">c</span></p>');
    $twice = stored($once);

    expect($twice)->toBe($once)
        ->and($once)->toContain('data-style="lead"')
        ->and($once)->toContain('data-style="kicker"');
});

it('replaces a style rather than collecting them', function (): void {
    // Exclusive by design: a block carries one, the way it carries one heading level.
    expect(stored('<p data-style="lead" class="text-lg text-slate-600">Text</p>'))
        ->toContain('data-style="lead"')
        ->and(substr_count(stored('<p data-style="lead">Text</p>'), 'data-style'))->toBe(1);
});

it('adds the classes to the ones a node already carries', function (): void {
    withStyles([
        'lead' => ['label' => 'Lead', 'class' => 'text-lg', 'types' => ['codeBlock']],
    ]);

    // A code block is drawn with a class of Filament's own, and a style must join it
    // rather than take its place.
    $html = AdvancedRichContentRenderer::make('<pre data-style="lead"><code>x</code></pre>')->toHtml();

    expect($html)->toContain('text-lg');
});

it('renders nothing extra when the project named no styles', function (): void {
    withStyles([]);

    expect(AdvancedRichContentRenderer::make('<p data-style="lead">Text</p>')->toHtml())
        ->toBe('<p>Text</p>');
});

it('parses a field with its own styles through its own styles', function (): void {
    // The field decides what it offers, so the schema a save goes through has to be built
    // from the same list - otherwise a field's own style is offered, applied, and then
    // dropped by the very parse that stores it.
    $field = editor()->styles(['own' => ['label' => 'Own', 'class' => 'font-serif']]);

    expect($field->getTipTapEditor()->setContent('<p data-style="own">Text</p>')->getHTML())
        ->toContain('data-style="own"')
        // And the project's list is not the one in force here.
        ->and($field->getTipTapEditor()->setContent('<p data-style="lead">Text</p>')->getHTML())
        ->not->toContain('data-style');
});

it('declares the style extensions once, however they arrive', function (): void {
    // Two extensions of the same name are both applied, so a second copy renders a span
    // inside a span and grows another layer on every save. The field brings them through
    // its plugin and the renderer brings them on its own, and only one pair may survive.
    $field = editor();

    $once = $field->getTipTapEditor()->setContent('<p>a <span data-style="kicker">b</span></p>')->getHTML();

    expect(substr_count($once, '<span'))->toBe(1)
        ->and(substr_count($field->getTipTapEditor()->setContent($once)->getHTML(), '<span'))->toBe(1);
});
