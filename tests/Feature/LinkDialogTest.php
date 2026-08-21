<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\LinkAction;

it('turns the form into the attributes the link carries', function (): void {
    expect(LinkAction::attributesFrom([
        'href' => '/preise',
        'target' => '',
        'rel' => ['nofollow'],
        'relExtra' => null,
        'hreflang' => 'de',
        'referrerpolicy' => 'no-referrer',
        'id' => 'ref',
    ]))->toBe([
        'href' => '/preise',
        'target' => null,
        'rel' => 'nofollow',
        'hreflang' => 'de',
        'referrerpolicy' => 'no-referrer',
        'id' => 'ref',
    ]);
});

it('leaves out what the form was not filled in with', function (): void {
    // A blank field means "no attribute", not `hreflang=""`. Both renderers drop falsy
    // attributes, so storing an empty string would be a value the markup never shows.
    expect(LinkAction::attributesFrom(['href' => '/x']))->toBe([
        'href' => '/x',
        'target' => null,
        'rel' => null,
        'hreflang' => null,
        'referrerpolicy' => null,
        'id' => null,
    ]);
});

it('protects a link that opens a new tab', function (): void {
    // `target="_blank"` without `rel="noopener"` hands the opened page a handle on the
    // window that opened it, which it can navigate elsewhere. Nothing in the editor, in
    // Filament or in the sanitiser prevents that; the dialog is the last place it can be
    // caught, and nobody ticking "new tab" is thinking about it.
    expect(LinkAction::attributesFrom(['href' => '/x', 'target' => '_blank'])['rel'])
        ->toBe('noopener noreferrer');
});

it('does not repeat a protection the author already ticked', function (): void {
    expect(LinkAction::attributesFrom([
        'href' => '/x',
        'target' => '_blank',
        'rel' => ['nofollow', 'noopener'],
    ])['rel'])->toBe('nofollow noopener noreferrer');
});

it('keeps rel values the author typed that the checkboxes do not offer', function (): void {
    // `rel` is an open list - `me`, `alternate`, `license` and more are all valid and none
    // of them belongs in a row of checkboxes.
    expect(LinkAction::attributesFrom([
        'href' => '/x',
        'rel' => ['nofollow'],
        'relExtra' => 'me  alternate',
    ])['rel'])->toBe('nofollow me alternate');
});

it('says the same thing once, however often it was given', function (): void {
    expect(LinkAction::attributesFrom([
        'href' => '/x',
        'rel' => ['nofollow'],
        'relExtra' => 'nofollow me',
    ])['rel'])->toBe('nofollow me');
});

it('hands the dialog the attributes the link already has', function (): void {
    // Filament's tool reads `href` and whether the target is `_blank`, so reopening a link
    // that carries a referrer policy would show an empty field and overwrite it on save.
    $arguments = editor()->getTools()['link']->getJsHandler();

    expect($arguments)->toContain('href')
        ->toContain('target')
        ->toContain('rel')
        ->toContain('hreflang')
        ->toContain('referrerpolicy');
});

it('registers one link dialog, not two', function (): void {
    $link = array_filter(editor()->getDefaultActions(), fn ($action): bool => $action->getName() === 'link');

    expect($link)->toHaveCount(1);
});

it('still registers one link dialog with the attributes turned off', function (): void {
    $link = array_filter(
        editor()->linkAttributes(false)->getDefaultActions(),
        fn ($action): bool => $action->getName() === 'link',
    );

    expect($link)->toHaveCount(1);
});
