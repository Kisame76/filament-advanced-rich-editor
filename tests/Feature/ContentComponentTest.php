<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;

/**
 * The component as a template renders it, whitespace squeezed out so an assertion reads
 * like the markup it is about rather than like the indentation of the Blade file.
 *
 * @param  array<string, mixed>  $data
 */
function renderComponent(string $template, array $data = []): string
{
    return trim((string) preg_replace('/\s+/', ' ', Blade::render($template, $data)));
}

it('prints a stored document inside a div', function (): void {
    expect(renderComponent('<x-arte-content :content="$body" />', ['body' => '<p>Ein Absatz</p>']))
        ->toBe('<div ><p>Ein Absatz</p></div>');
});

it('passes its attributes on to the wrapper', function (): void {
    expect(renderComponent('<x-arte-content :content="$body" class="prose" id="body" />', ['body' => '<p>Text</p>']))
        ->toContain('class="prose"')
        ->toContain('id="body"');
});

it('draws whatever element it is asked for', function (): void {
    expect(renderComponent('<x-arte-content :content="$body" tag="article" />', ['body' => '<p>Text</p>']))
        ->toStartWith('<article')
        ->toEndWith('</article>');
});

it('prints the document on its own where no element is wanted', function (): void {
    expect(renderComponent('<x-arte-content :content="$body" :tag="null" />', ['body' => '<p>Text</p>']))
        ->toBe('<p>Text</p>');
});

it('refuses a tag that is not a tag rather than writing it into the page', function (): void {
    // The value can only have come from a variable, and a variable can hold anything. What
    // is refused is the name, not the wrapper: dropping the element would take the caller's
    // attributes with it, and losing a page's typography over a typo is a second failure
    // nobody asked for.
    $html = renderComponent('<x-arte-content :content="$body" :tag="$tag" class="prose" />', [
        'body' => '<p>Text</p>',
        'tag' => 'div onload="alert(1)"',
    ]);

    expect($html)->toStartWith('<div ')
        ->toContain('class="prose"')
        ->toContain('<p>Text</p>')
        ->not->toContain('onload');
});

it('draws no element at all only when it is told null', function (): void {
    expect(renderComponent('<x-arte-content :content="$body" :tag="null" />', ['body' => '<p>Text</p>']))
        ->toBe('<p>Text</p>');
});

it('sanitises what it prints', function (): void {
    // Printed unescaped, so this is the assertion that matters: what arrives has been
    // through the sanitiser and carries no script.
    $html = renderComponent('<x-arte-content :content="$body" :tag="null" />', [
        'body' => '<p>Text</p><script>alert(1)</script>',
    ]);

    expect($html)->toBe('<p>Text</p>');
});

it('renders a document held as a TipTap array', function (): void {
    $document = [
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => 'Aus einem Array']],
        ]],
    ];

    expect(renderComponent('<x-arte-content :content="$body" :tag="null" />', ['body' => $document]))
        ->toBe('<p>Aus einem Array</p>');
});

it('anchors the headings when it is told to', function (): void {
    expect(renderComponent('<x-arte-content :content="$body" anchors :tag="null" />', ['body' => '<h2>Über uns</h2>']))
        ->toBe('<h2 id="uber-uns">Über uns</h2>');
});

it('takes the heading levels it is handed', function (): void {
    $html = renderComponent('<x-arte-content :content="$body" :anchors="[3]" :tag="null" />', [
        'body' => '<h2>Zwei</h2><h3>Drei</h3>',
    ]);

    expect($html)->toBe('<h2>Zwei</h2><h3 id="drei">Drei</h3>');
});

it('builds on a renderer it is handed', function (): void {
    $renderer = AdvancedRichContentRenderer::make()->anchorHeadings([2]);

    expect(renderComponent('<x-arte-content :renderer="$renderer" :content="$body" :tag="null" />', [
        'renderer' => $renderer,
        'body' => '<h2>Titel</h2>',
    ]))->toBe('<h2 id="titel">Titel</h2>');
});

it('has nothing to print for a record nobody has typed into', function (): void {
    expect(renderComponent('<x-arte-content :content="$body" :tag="null" />', ['body' => null]))->toBe('');
});
