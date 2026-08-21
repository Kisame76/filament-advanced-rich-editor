<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\Anchor;

it('carries an id on a heading through a round trip', function (): void {
    // Without the attribute being declared the parser drops it, so a heading written with
    // an anchor comes back without one - and every link pointing at it stops working the
    // first time the record is saved.
    expect(document('<h2 id="intro">Intro</h2>', [new Anchor])->getHTML())
        ->toBe('<h2 id="intro">Intro</h2>');
});

it('drops an id that is not usable as a fragment', function (): void {
    // `id` is on the sanitiser's safe list, so nothing downstream would object to a value
    // with a space or a quote in it. It simply would not be linkable.
    expect(document('<h2 id="not valid">Intro</h2>', [new Anchor])->getHTML())
        ->toBe('<h2>Intro</h2>');
});
