<?php

declare(strict_types=1);

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Http;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\LinkAction;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\TestSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\Post;

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

/**
 * The address field of the link dialog, as a field set up this way draws it.
 */
function linkDialogHref(AdvancedRichEditor $editor): ?TextInput
{
    $editor->container(Schema::make(new TestSchemaComponent)->operation('edit')->record(Post::create(['title' => 'Post'])));

    $schema = $editor->getAction('link')?->schemaComponent($editor)->getSchema(testSchema());

    return collect($schema?->getFlatComponents() ?? [])
        ->first(static fn (object $component): bool => $component instanceof TextInput && ($component->getName() === 'href'));
}

it('asks nothing about a typed address unless the field says so', function (): void {
    // It is the server that asks, so every address somebody types is a request leaving it -
    // and a server that may not reach the internet would warn about every link.
    expect(editor()->hasLinkCheck())->toBeFalse()
        ->and(linkDialogHref(editor())?->isLive())->toBeFalse();

    config()->set('filament-advanced-rich-editor.link.check.dialog', true);

    expect(editor()->hasLinkCheck())->toBeTrue()
        ->and(editor()->linkCheck(false)->hasLinkCheck())->toBeFalse();
});

it('asks nothing when a link is saved with the check off either', function (): void {
    // Filament runs the hook for a field that is not live too, as soon as its value reaches
    // the server - with the save. Unguarded, every link saved sent a request and waited for it.
    Http::preventStrayRequests();
    Http::fake();
    resolvingHosts(['example.com' => ['93.184.215.14']]);

    $href = linkDialogHref(editor());
    $href?->state('https://example.com/')->callAfterStateUpdated();

    Http::assertNothingSent();

    $checked = linkDialogHref(editor()->linkCheck());
    $checked?->state('https://example.com/')->callAfterStateUpdated();

    Http::assertSentCount(1);
});

it('asks once the address field is left, rather than at every keystroke', function (): void {
    // Half an address is always broken.
    $href = linkDialogHref(editor()->linkCheck());

    expect($href?->isLive())->toBeTrue()
        ->and($href?->isLiveOnBlur())->toBeTrue();
});

it('warns about a typed address that is gone, and only one on the public internet', function (): void {
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response('', 404)]);

    resolvingHosts(['example.com' => ['93.184.215.14'], 'intranet.test' => ['10.0.0.8']]);

    expect(LinkAction::problemWith('https://example.com/gone'))->toBe('This address answers 404 Not Found.')
        ->and(LinkAction::problemWith('http://intranet.test/gone'))->toBeNull()
        ->and(LinkAction::problemWith('mailto:a@example.com'))->toBeNull()
        ->and(LinkAction::problemWith(''))->toBeNull()
        ->and(LinkAction::problemWith(null))->toBeNull();

    // The intranet address was never asked.
    Http::assertSentCount(2);
});
