<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Links;

/**
 * What one address answered, and how to say it to the person who wrote the link.
 */
final readonly class LinkStatus
{
    public function __construct(
        public LinkState $state,
        public ?int $status = null,
        public ?string $reason = null,
        public ?string $host = null,
    ) {}

    /**
     * What is wrong with the link, in a sentence, or null where nothing is - or nothing was
     * asked.
     */
    public function problem(): ?string
    {
        $key = 'filament-advanced-rich-editor::advanced-rich-editor.tools.link.check.';

        return match (true) {
            in_array($this->state, [LinkState::Working, LinkState::Unchecked], strict: true) => null,
            $this->status !== null => (string) __($key.'answers', ['status' => trim($this->status.' '.$this->reason)]),
            $this->state === LinkState::Broken => (string) __($key.'no_host', ['host' => (string) $this->host]),
            default => (string) __($key.'silent'),
        };
    }
}
