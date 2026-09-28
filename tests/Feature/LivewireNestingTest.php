<?php

declare(strict_types=1);

use Illuminate\Contracts\View\View;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\LivewireNesting;

/**
 * Livewire caps how deep a property path may go, and a rich editor entangles a document
 * rather than a string. Text inside a list item sits at
 * `data.content.content.0.content.0.content.0.content.0.text` - eleven segments - so the
 * shipped cap of ten answers the first keystroke in the first list with a 500 that names
 * neither this package nor the setting behind it.
 *
 * The README has said so since the beginning. This is the same sentence, said by the code.
 */
it('reads the effective limit rather than asking whether the key was published', function (): void {
    // Livewire merges its own config, so the value is there whether or not anybody
    // published `livewire.php`. Checking for the key's existence would find it missing on a
    // fresh install and conclude that all was well.
    config()->set('livewire.payload.max_nesting_depth', 10);

    expect(LivewireNesting::limit())->toBe(10);
});

it('treats no limit at all as deep enough', function (): void {
    // Livewire skips the check entirely on null. It is not an unset value to be defaulted,
    // it is the answer "as deep as you like".
    config()->set('livewire.payload.max_nesting_depth', null);

    expect(LivewireNesting::isEnough(null, 32))->toBeTrue()
        ->and(LivewireNesting::isEnough(10, 32))->toBeFalse()
        ->and(LivewireNesting::isEnough(32, 32))->toBeTrue()
        ->and(LivewireNesting::isEnough(64, 32))->toBeTrue();
});

it('knows how deep a list item actually sits', function (): void {
    // The number the failure starts at, kept next to the check that uses it rather than
    // only in prose: a paragraph in a list item is eleven segments deep.
    expect(LivewireNesting::LIST_ITEM_DEPTH)->toBe(11)
        ->and(LivewireNesting::REQUIRED)->toBeGreaterThan(LivewireNesting::LIST_ITEM_DEPTH);
});

it('says what is wrong, what it needs and what to do about it', function (): void {
    config()->set('livewire.payload.max_nesting_depth', 10);

    expect(fn () => LivewireNesting::guard(32))
        ->toThrow(
            RuntimeException::class,
            'Livewire is configured with a maximum nesting depth of 10, and the advanced rich editor needs at least 32.',
        );
});

it('passes silently once the limit is high enough', function (): void {
    config()->set('livewire.payload.max_nesting_depth', 32);

    LivewireNesting::guard(32);

    expect(true)->toBeTrue();
});

it('names the setting and the way out in the message', function (): void {
    config()->set('livewire.payload.max_nesting_depth', 10);

    try {
        LivewireNesting::guard(32);
    } catch (RuntimeException $exception) {
        // Whoever reads this is looking at a stack trace, not at the README.
        expect($exception->getMessage())
            ->toContain('config/livewire.php')
            ->toContain('payload.max_nesting_depth')
            ->toContain('->nestingCheck(false)');

        return;
    }

    $this->fail('The guard let a limit of 10 through.');
});

it('stops a field from rendering where the limit would break it', function (): void {
    config()->set('livewire.payload.max_nesting_depth', 10);

    expect(fn () => editor()->render())->toThrow(RuntimeException::class);
});

it('renders as usual once the limit is raised', function (): void {
    config()->set('livewire.payload.max_nesting_depth', 32);

    expect(editor()->render())->toBeInstanceOf(View::class);
});

it('lets a field opt out of the check', function (): void {
    // A field that will never hold a list is a field the limit does not reach.
    config()->set('livewire.payload.max_nesting_depth', 10);

    expect(editor()->nestingCheck(false)->render())->toBeInstanceOf(View::class);
});

it('lets a field ask for a depth of its own', function (): void {
    config()->set('livewire.payload.max_nesting_depth', 12);

    expect(editor()->nestingCheck(12)->render())->toBeInstanceOf(View::class)
        ->and(fn () => editor()->nestingCheck(13)->render())->toThrow(RuntimeException::class);
});

it('reads the default depth from the config file', function (): void {
    config()->set('livewire.payload.max_nesting_depth', 20);
    config()->set('filament-advanced-rich-editor.nesting_check', 16);

    expect(editor()->getRequiredNestingDepth())->toBe(16)
        ->and(editor()->render())->toBeInstanceOf(View::class);

    config()->set('filament-advanced-rich-editor.nesting_check', false);

    expect(editor()->getRequiredNestingDepth())->toBeFalse();
});
