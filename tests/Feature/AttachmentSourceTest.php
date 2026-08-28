<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\RichContentAttribute;
use Kisame76\FilamentAdvancedRichEditor\Infolists\Components\AdvancedRichEntry;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * What happens to a picture whose attachment cannot be resolved.
 *
 * Filament stores two things on an uploaded image: the `src` it had when it was written, and
 * the attachment id it came from. On render it treats the id as the only truth and assigns
 * whatever it resolves to - `$node->attrs->src = $this->getFileAttachmentUrl($node->attrs->id)`
 * in `RichContentRenderer::processFileAttachments()` - without asking whether that is
 * anything at all.
 *
 * Where no attachment provider reached the renderer, it is null, and the assignment then does
 * not fail to improve the `src`: it deletes a perfectly good one. What comes out is an `<img>`
 * with its measurements and no source, which a browser draws as an empty box of exactly the
 * right size. That is the whole of the bug this file pins down, and it is worth pinning
 * because everything about it looks correct - the document is right, the file is there, the
 * disk is public, the URL resolves in a browser.
 *
 * Keeping the stored `src` is the lesser of the two wrongs rather than a free win. If the
 * medium moved after the document was written, the kept source is stale and the page shows a
 * broken image. But a stale source is the same risk this package already carries for every
 * picture that has no attachment id at all, and it only arises where the provider is missing,
 * which is a misconfiguration either way. Losing a good source is unconditional.
 */
it('keeps a stored source when the attachment cannot be resolved', function (): void {
    $html = '<p><img src="/storage/1/foto.png" data-id="cd606c96-74b3-418b-8787-b1efc1ed5405" width="200" height="255"></p>';

    expect(AdvancedRichContentRenderer::make($html)->toHtml())
        ->toContain('src="/storage/1/foto.png"');
});

it('does not turn an unresolvable attachment into a picture with no source at all', function (): void {
    // The failure this replaces: an `<img>` carrying its measurements and nothing to draw.
    $html = '<p><img src="/storage/1/foto.png" data-id="unknown" width="200" height="255"></p>';

    $rendered = AdvancedRichContentRenderer::make($html)->toHtml();

    expect($rendered)->toContain('<img')
        ->and($rendered)->toContain('src=');
});

it('leaves a picture without an attachment id exactly as it was', function (): void {
    // The path that always worked, kept here so a fix to the other one cannot break it.
    $html = '<p><img src="/a.png" width="10" height="10"></p>';

    expect(AdvancedRichContentRenderer::make($html)->toHtml())->toContain('src="/a.png"');
});

it('still prefers what the provider resolves over what was stored', function (): void {
    // The id is the durable half and the stored source is a copy of what it once pointed at.
    // Keeping the copy is a fallback, not a change of mind about which one wins.
    $renderer = AdvancedRichContentRenderer::make(
        '<p><img src="/storage/1/stale.png" data-id="known" width="200" height="255"></p>',
    )->fileAttachmentProvider(new class implements FileAttachmentProvider
    {
        public function attribute(RichContentAttribute $attribute): static
        {
            return $this;
        }

        public function getFileAttachmentUrl(mixed $file): ?string
        {
            return $file === 'known' ? '/storage/1/fresh.png' : null;
        }

        public function saveUploadedFileAttachment(TemporaryUploadedFile $file): mixed
        {
            return null;
        }

        public function getDefaultFileAttachmentVisibility(): ?string
        {
            return null;
        }

        public function isExistingRecordRequiredToSaveNewFileAttachments(): bool
        {
            return false;
        }

        public function cleanUpFileAttachments(array $exceptIds): void {}
    });

    expect($renderer->toHtml())->toContain('src="/storage/1/fresh.png"')
        ->and($renderer->toHtml())->not->toContain('stale.png');
});

it('lets an entry and a column be handed a provider of their own', function (): void {
    // The trait passes the disk and the visibility through, and until now not the provider -
    // so the one thing that actually resolves an upload was the one thing a view page could
    // not be given. Reaching for `->plugins()` works but says nothing about what it is for.
    expect(method_exists(AdvancedRichEntry::class, 'fileAttachmentProvider'))->toBeTrue();

    $entry = AdvancedRichEntry::make('content')->fileAttachmentProvider(null);

    expect($entry)->toBeInstanceOf(AdvancedRichEntry::class);
});
