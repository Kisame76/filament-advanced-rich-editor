<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedProviders;

/**
 * A pen on CodePen, opened on its result.
 *
 * Its id has two halves, the author and the pen, `{user}/{pen}`, because the player address
 * puts a word between them that the link does not have.
 */
class CodePen extends EmbedProvider
{
    public function name(): string
    {
        return 'codepen';
    }

    public function hosts(): array
    {
        return ['codepen.io'];
    }

    public function patterns(): array
    {
        return ['^codepen\.io/(?<user>[A-Za-z0-9_-]+)/(?:pen|embed|full|details|debug)/(?:preview/)?(?<pen>[A-Za-z0-9]+)'];
    }

    public function id(): string
    {
        return '[A-Za-z0-9_-]{1,50}/[A-Za-z0-9]{3,20}';
    }

    public function src(): string
    {
        return 'https://codepen.io/{id:0}/embed/{id:1}?default-tab=result';
    }

    public function share(): string
    {
        return 'https://codepen.io/{id:0}/pen/{id:1}';
    }

    public function oembed(): string
    {
        return 'https://codepen.io/api/oembed';
    }
}
