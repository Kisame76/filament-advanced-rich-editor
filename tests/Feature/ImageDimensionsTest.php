<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\ImageAttributes;

/**
 * What an inserted picture carries besides its source.
 *
 * The measuring is `MediaDimensions`' job and happens on upload; this is the half that
 * decides what of it reaches the document. Kept apart from the action that inserts, for
 * the reason every other split in this package has: the action needs a Livewire component,
 * a resolved attachment and a browser behind it, and none of that says anything about
 * whether a width of `"0"` is a width.
 */
it('carries the measured size into the document', function (): void {
    expect(ImageAttributes::forInsert(
        item: ['id' => 7, 'url' => '/cat.jpg', 'width' => 1600, 'height' => 900],
        alt: 'Eine Katze',
        loading: null,
        withDimensions: true,
    ))->toBe([
        'alt' => 'Eine Katze',
        'id' => 7,
        'src' => '/cat.jpg',
        'width' => 1600,
        'height' => 900,
    ]);
});

it('leaves the size out when the file was never measured', function (): void {
    // A remote disk `getimagesize()` cannot reach, a format it does not know, an SVG. The
    // picture still goes in; it is the aspect ratio that is missing, not the image.
    expect(ImageAttributes::forInsert(
        item: ['id' => 7, 'url' => '/cat.jpg', 'width' => null, 'height' => null],
        alt: null,
        loading: null,
        withDimensions: true,
    ))->toBe([
        'alt' => null,
        'id' => 7,
        'src' => '/cat.jpg',
    ]);
});

it('needs both numbers or neither', function (): void {
    // Half a pair says nothing about the shape of the picture, and a lone `width` is worse
    // than none: Filament renders it as an inline `width` with no height beside it, which
    // is a picture squashed to a strip on any page with a `height: auto` reset.
    expect(ImageAttributes::forInsert(
        item: ['id' => 7, 'url' => '/cat.jpg', 'width' => 1600, 'height' => null],
        alt: null,
        loading: null,
        withDimensions: true,
    ))->not->toHaveKeys(['width', 'height']);
});

it('refuses a size that is not one', function (): void {
    // These arrive from a measuring pass over a file, not from a person, so the wrong
    // answer here is a zero rather than an attack - but a zero written into `width`
    // renders as `width: 0px` and takes the picture off the page.
    foreach ([['width' => 0, 'height' => 900], ['width' => -5, 'height' => 900], ['width' => 'breit', 'height' => 900]] as $broken) {
        expect(ImageAttributes::forInsert(
            item: ['id' => 7, 'url' => '/cat.jpg', ...$broken],
            alt: null,
            loading: null,
            withDimensions: true,
        ))->not->toHaveKeys(['width', 'height']);
    }
});

it('reads a size that arrived as a string of digits', function (): void {
    // Spatie keeps custom properties as JSON, and a number that went through it can come
    // back as `"1600"`.
    expect(ImageAttributes::forInsert(
        item: ['id' => 7, 'url' => '/cat.jpg', 'width' => '1600', 'height' => '900'],
        alt: null,
        loading: null,
        withDimensions: true,
    ))->toMatchArray(['width' => 1600, 'height' => 900]);
});

it('writes no size where a project asked for none', function (): void {
    expect(ImageAttributes::forInsert(
        item: ['id' => 7, 'url' => '/cat.jpg', 'width' => 1600, 'height' => 900],
        alt: null,
        loading: null,
        withDimensions: false,
    ))->not->toHaveKeys(['width', 'height']);
});

it('sets the loading hint only where one was asked for', function (): void {
    expect(ImageAttributes::forInsert(
        item: ['id' => 7, 'url' => '/cat.jpg', 'width' => 1600, 'height' => 900],
        alt: null,
        loading: 'lazy',
        withDimensions: true,
    ))->toMatchArray(['loading' => 'lazy'])
        ->and(ImageAttributes::forInsert(
            item: ['id' => 7, 'url' => '/cat.jpg', 'width' => 1600, 'height' => 900],
            alt: null,
            loading: null,
            withDimensions: true,
        ))->not->toHaveKey('loading');
});

it('allows only the two hints a browser knows', function (): void {
    // The value reaches an attribute the sanitiser lets through untouched. `lazy` and
    // `eager` are the whole of it; anything else is dropped rather than written, the same
    // decision the font size and the float gap make about what they interpolate.
    foreach (['auto', 'yes', 'lazy; onload=alert(1)', ''] as $rubbish) {
        expect(ImageAttributes::forInsert(
            item: ['id' => 7, 'url' => '/cat.jpg', 'width' => 1600, 'height' => 900],
            alt: null,
            loading: $rubbish,
            withDimensions: true,
        ))->not->toHaveKey('loading');
    }

    expect(ImageAttributes::forInsert(
        item: ['id' => 7, 'url' => '/cat.jpg', 'width' => 1600, 'height' => 900],
        alt: null,
        loading: 'EAGER',
        withDimensions: true,
    ))->toMatchArray(['loading' => 'eager']);
});

/**
 * Where the two answers come from: the field first, the config behind it.
 */
it('writes the size unless a field or the project says otherwise', function (): void {
    expect(editor()->hasImageDimensions())->toBeTrue()
        ->and(editor()->imageDimensions(false)->hasImageDimensions())->toBeFalse();

    config()->set('filament-advanced-rich-editor.images.dimensions', false);

    expect(editor()->hasImageDimensions())->toBeFalse()
        // The field is asked first, so a project that switched it off project-wide can
        // still switch it back on for the one field that wants it.
        ->and(editor()->imageDimensions()->hasImageDimensions())->toBeTrue();
});

it('writes no loading hint until it is told one', function (): void {
    expect(editor()->getImageLoading())->toBeNull()
        ->and(editor()->imageLoading('lazy')->getImageLoading())->toBe('lazy');

    config()->set('filament-advanced-rich-editor.images.loading', 'lazy');

    expect(editor()->getImageLoading())->toBe('lazy')
        // null is the way back to what the project said, not a way to say none - the same
        // two answers `->cached(false)` gives the renderer.
        ->and(editor()->imageLoading(null)->getImageLoading())->toBe('lazy')
        // And false is how a teaser field stays eager on a project that turned lazy
        // loading on everywhere. Without it there would be no way to say so.
        ->and(editor()->imageLoading(false)->getImageLoading())->toBeNull();
});
