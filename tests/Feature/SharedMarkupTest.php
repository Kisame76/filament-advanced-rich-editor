<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\RenderLedger;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\SharedMarkup;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\RepeatedEditorsFormComponent;
use Livewire\Livewire;

/**
 * The bars that float over a selection, a picture, a table or a list, sent once a page.
 *
 * They are the same for every field set up the same way, byte for byte - 82 KB of each
 * editor's markup, none of it seen until somebody selects something. What stamps them out in
 * the browser is the template's `x-init`, run under `tests/js/shared-markup.test.js`.
 */

/**
 * Every shared template on a page, in page order.
 *
 * @return array<int, array{key: string, bars: array<int, string>}>
 */
function markupTemplates(string $html): array
{
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);

    $templates = [];

    foreach ($xpath->query('//template[@data-arte-markup]') as $template) {
        $templates[] = [
            'key' => $template->getAttribute('data-arte-markup'),
            'bars' => array_map(
                static fn (DOMElement $bar): string => $bar->getAttribute('x-ref'),
                iterator_to_array($xpath->query('.//*[starts-with(@x-ref, "floatingToolbar::")]', $template)),
            ),
        ];
    }

    return $templates;
}

it('sends the floating bars once a page, and only names them on every other editor', function (): void {
    $templates = markupTemplates(Livewire::test(RepeatedEditorsFormComponent::class)->html());

    expect($templates)->toHaveCount(3)
        ->and($templates[0]['bars'])->toContain('floatingToolbar::paragraph', 'floatingToolbar::image', 'floatingToolbar::table')
        ->and($templates[1]['bars'])->toBe([])
        ->and($templates[2]['bars'])->toBe([])
        ->and(array_unique(array_column($templates, 'key')))->toHaveCount(1);
})->skip(fn (): bool => toolActionsNameTheirEditor(), 'Filament before 5.10.2 names each editor in its bars, so no two are the same.');

it('carries the bars on every editor where Filament names the editor in its buttons', function (): void {
    // Before 5.10.2 every button that opens a dialog says which editor it belongs to, so the
    // bars of two editors differ by that much - and a markup named by what it holds is then
    // a markup of its own on every editor, carried by each. Nothing is lost but the saving.
    $templates = markupTemplates(Livewire::test(RepeatedEditorsFormComponent::class)->html());

    expect($templates)->toHaveCount(3)
        ->and(array_unique(array_column($templates, 'key')))->toHaveCount(3);

    foreach ($templates as $template) {
        expect($template['bars'])->toContain('floatingToolbar::paragraph', 'floatingToolbar::image');
    }
})->skip(fn (): bool => ! toolActionsNameTheirEditor(), 'Filament 5.10.2 and later write the same bars for every editor.');

it('writes the bars inside the element the editor is mounted on, where the editor looks for them', function (): void {
    // Filament reads them by `x-ref` off the component, and the text toolbar's rule asks the
    // element TipTap mounts into whether a paragraph bar is there.
    $html = Livewire::test(RepeatedEditorsFormComponent::class)->html();

    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $templates = (new DOMXPath($document))->query('//*[@x-ref="editor"]/template[@data-arte-markup]');

    expect($templates)->toHaveCount(3);
});

it('carries the bars on every editor of an update', function (): void {
    // An editor already on the page is never drawn again, so it cannot be relied on to carry
    // anything an update adds. See `RenderLedger`.
    $test = Livewire::test(RepeatedEditorsFormComponent::class);

    foreach (markupTemplates($test->call('$refresh')->html()) as $template) {
        expect($template['bars'])->toContain('floatingToolbar::image');
    }
});

it('names the markup by what it holds, so differently set up fields never share', function (): void {
    $component = new stdClass;

    RenderLedger::rendering($component);

    $first = SharedMarkup::template($component, '<div x-ref="a"></div>');
    $other = SharedMarkup::template($component, '<div x-ref="b"></div>');
    $again = SharedMarkup::template($component, '<div x-ref="a"></div>');

    expect($first)->toContain('data-arte-markup="'.RenderLedger::keyFor('<div x-ref="a"></div>').'"')
        ->and($first)->toContain('<div data-arte-stamped style="display: contents"><div x-ref="a"></div></div>')
        ->and($other)->toContain('<div x-ref="b"></div>')
        ->and($again)->toEndWith('"></template>')
        ->and($again)->not->toContain('x-ref="a"');
});

it('shares nothing during an update, nor where it never saw a render begin', function (): void {
    $outside = new stdClass;

    expect(SharedMarkup::template($outside, '<div></div>'))->toContain('data-arte-stamped')
        ->and(SharedMarkup::template($outside, '<div></div>'))->toContain('data-arte-stamped');

    request()->headers->set('X-Livewire', 'true');

    $updated = new stdClass;

    RenderLedger::rendering($updated);

    expect(SharedMarkup::template($updated, '<div></div>'))->toContain('data-arte-stamped')
        ->and(SharedMarkup::template($updated, '<div></div>'))->toContain('data-arte-stamped');
});

it('writes nothing for nothing', function (): void {
    expect(SharedMarkup::template(new stdClass, "  \n "))->toBe('');
});

it('hands Alpine the stamp it made, rather than leaving it to notice', function (): void {
    // Alpine runs `x-init` after it has walked the tree it found, so markup put in from there is
    // not walked. Left to Alpine's mutation observer, it would be set up a microtask later -
    // racing the editor's own start, which reads the bars by `x-ref`.
    expect(SharedMarkup::template(new stdClass, '<div></div>'))->toContain('Alpine.initTree(stamp)');
});
