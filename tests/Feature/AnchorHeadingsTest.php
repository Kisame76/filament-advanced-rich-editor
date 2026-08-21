<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AnchorPosition;

it('gives every heading an anchor to link to', function (): void {
    expect(AdvancedRichContentRenderer::make('<h2>Intro</h2><h3>Details</h3>')->anchorHeadings()->toHtml())
        ->toBe('<h2 id="intro">Intro</h2><h3 id="details">Details</h3>');
});

it('adds nothing visible until a position asks for a marker', function (): void {
    // Anchors are usually wanted so a table of contents can link into the page. A symbol
    // appearing next to every heading is a change to a design nobody asked to change, so
    // it is not what happens by default.
    expect(AdvancedRichContentRenderer::make('<h2>Intro</h2>')->anchorHeadings()->toHtml())
        ->not->toContain('<a');
});

it('draws the marker after the heading text', function (): void {
    expect(AdvancedRichContentRenderer::make('<h2>Intro</h2>')
        ->anchorHeadings(position: AnchorPosition::After)
        ->toHtml())
        ->toBe('<h2 id="intro">Intro<a href="#intro" class="fi-arte-anchor">#</a></h2>');
});

it('draws the marker in front of the heading text', function (): void {
    expect(AdvancedRichContentRenderer::make('<h2>Intro</h2>')
        ->anchorHeadings(position: AnchorPosition::Before)
        ->toHtml())
        ->toBe('<h2 id="intro"><a href="#intro" class="fi-arte-anchor">#</a>Intro</h2>');
});

it('turns the heading itself into the link when asked to wrap it', function (): void {
    // The one position that needs no explaining to a screen reader: the link's text is
    // the heading, so it is announced as the section it leads to rather than as a symbol.
    expect(AdvancedRichContentRenderer::make('<h2>Intro</h2>')
        ->anchorHeadings(position: AnchorPosition::Wrap)
        ->toHtml())
        ->toBe('<h2 id="intro"><a href="#intro" class="fi-arte-anchor">Intro</a></h2>');
});

it('keeps an anchor the stored markup already carried', function (): void {
    // Someone typed this id into the source code view, and something out there links to
    // it. Replacing it with a slug of the current wording would break that link on the
    // next render, without anyone touching the document.
    expect(AdvancedRichContentRenderer::make('<h2 id="start-here">Getting started</h2>')->anchorHeadings()->toHtml())
        ->toBe('<h2 id="start-here">Getting started</h2>');
});

it('takes the marker symbol and class from the call', function (): void {
    expect(AdvancedRichContentRenderer::make('<h2>Intro</h2>')
        ->anchorHeadings(position: AnchorPosition::After, symbol: '¶', class: 'permalink')
        ->toHtml())
        ->toBe('<h2 id="intro">Intro<a href="#intro" class="permalink">¶</a></h2>');
});

it('reads the position and the levels from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.anchors.position', 'after');
    config()->set('filament-advanced-rich-editor.anchors.levels', [2]);

    expect(AdvancedRichContentRenderer::make('<h2>Intro</h2><h3>Details</h3>')->anchorHeadings()->toHtml())
        ->toBe('<h2 id="intro">Intro<a href="#intro" class="fi-arte-anchor">#</a></h2><h3>Details</h3>');
});

it('leaves the document exactly as it was until anchors are asked for', function (): void {
    expect(AdvancedRichContentRenderer::make('<h2>Intro</h2>')->toHtml())
        ->toBe('<h2>Intro</h2>');
});

it('renders an empty record without falling over', function (): void {
    // A rich content column is null until someone types into it, and rendering one is an
    // ordinary thing a page does.
    expect(AdvancedRichContentRenderer::make(null)->anchorHeadings()->toHtml())->toBe('');
});
