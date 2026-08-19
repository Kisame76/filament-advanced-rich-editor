<?php

declare(strict_types=1);

use Filament\Support\Components\Contracts\HasEmbeddedView;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDivider;

it('renders a hidden span carrying the package class', function (): void {
    $html = ToolbarDivider::make()->toEmbeddedHtml();

    expect(ToolbarDivider::make())->toBeInstanceOf(HasEmbeddedView::class)
        ->and($html)->toStartWith('<span ')
        ->and($html)->toEndWith('></span>')
        ->and($html)->toContain('class="fi-arte-toolbar-divider"')
        // Decoration only: a screen reader has nothing to announce here.
        ->and($html)->toContain('aria-hidden="true"');
});

it('renders nothing but the separator span', function (): void {
    // Asserted on the parsed markup rather than the raw string: the attribute
    // order comes from Laravel's attribute bag and is not part of the contract.
    $document = new DOMDocument;
    $document->loadHTML(ToolbarDivider::make()->toEmbeddedHtml(), LIBXML_NOERROR);

    $span = $document->getElementsByTagName('span')->item(0);

    expect($document->getElementsByTagName('span'))->toHaveCount(1)
        ->and($span->textContent)->toBe('')
        ->and($span->getAttribute('class'))->toBe('fi-arte-toolbar-divider')
        ->and($span->getAttribute('aria-hidden'))->toBe('true');
});

it('renders the extra attributes of the divider', function (): void {
    $html = ToolbarDivider::make()
        ->extraAttributes(['data-role' => 'separator'])
        ->toEmbeddedHtml();

    expect($html)->toContain('data-role="separator"')
        ->and($html)->toContain('class="fi-arte-toolbar-divider"');
});

it('builds a new instance on every call', function (): void {
    expect(ToolbarDivider::make())->not->toBe(ToolbarDivider::make());
});
