<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\HeadingIds;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\Anchor;

it('turns a heading into a slug', function (): void {
    expect((new HeadingIds)->assign('Getting started'))->toBe('getting-started');
});

it('numbers a heading that repeats, rather than handing out the same anchor twice', function (): void {
    $ids = new HeadingIds;

    // Two sections called "Installation" is ordinary in a long document, and an anchor
    // that appears twice sends every link to the first one.
    expect($ids->assign('Installation'))->toBe('installation')
        ->and($ids->assign('Installation'))->toBe('installation-2')
        ->and($ids->assign('Installation'))->toBe('installation-3');
});

it('keeps an anchor that was written by hand, and counts it as taken', function (): void {
    $ids = new HeadingIds;

    // An id typed into the source code view is a decision, and a link somewhere else may
    // already point at it. Overwriting it with a slug would break that link silently.
    expect($ids->assign('Installation', existing: 'install'))->toBe('install')
        ->and($ids->assign('Install'))->toBe('install-2');
});

it('still hands out an anchor for a heading that slugs to nothing', function (): void {
    $ids = new HeadingIds;

    // A heading made of emoji or punctuation leaves `Str::slug()` with an empty string,
    // and an empty `id` is markup that cannot be linked to at all.
    expect($ids->assign('🎉'))->toBe('section')
        ->and($ids->assign('???'))->toBe('section-2');
});

it('slugs in the language the project writes in', function (): void {
    // Transliteration is a language's own business: German readers expect `ueber-uns`,
    // and the plain ASCII fold that produces `uber-uns` is only right where nobody
    // spells it out. The anchor ends up in URLs, so this is a project-wide decision
    // rather than something to guess per heading.
    expect((new HeadingIds)->assign('Über uns'))->toBe('uber-uns');

    config()->set('filament-advanced-rich-editor.anchors.language', 'de');

    expect((new HeadingIds)->assign('Über uns'))->toBe('ueber-uns');
});

it('walks a document and reports every heading it anchored', function (): void {
    $headings = (new HeadingIds)->assignTo(document('<h2>Intro</h2><p>Body</p><h3>Details</h3>'), [2, 3]);

    expect($headings)->toBe([
        ['level' => 2, 'text' => 'Intro', 'id' => 'intro'],
        ['level' => 3, 'text' => 'Details', 'id' => 'details'],
    ]);
});

it('writes the anchor into the document, not only into its report', function (): void {
    $document = document('<h2>Intro</h2>', [new Anchor]);

    (new HeadingIds)->assignTo($document, [2]);

    expect($document->getHTML())->toBe('<h2 id="intro">Intro</h2>');
});

it('reads a heading as one sentence, whatever marks run through it', function (): void {
    // `Get<strong>ting</strong> started` is one word followed by another. Joining the
    // text nodes with a space - the obvious way to write this - would slug it to
    // `get-ting-started` and split a word that was never split.
    $headings = (new HeadingIds)->assignTo(document('<h2>Get<strong>ting</strong> started</h2>'), [2]);

    expect($headings[0]['text'])->toBe('Getting started')
        ->and($headings[0]['id'])->toBe('getting-started');
});

it('ignores the levels it was not asked about', function (): void {
    $headings = (new HeadingIds)->assignTo(document('<h2>Kept</h2><h4>Skipped</h4>'), [2, 3]);

    expect($headings)->toHaveCount(1)
        ->and($headings[0]['text'])->toBe('Kept');
});
