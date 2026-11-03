<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Links;

/**
 * What asking an address found, from a link that works to one nobody can follow.
 *
 * Three ways of failing rather than one, because they call for different things. A page that
 * is gone, or a host that no longer exists, is a link to fix. A server that answered with an
 * error may be down for the afternoon, or may turn away anything that is not a browser - a
 * lot of large sites do. And an address that did not answer at all says as much about the
 * machine asking as about the one asked.
 */
enum LinkState: string
{
    case Working = 'working';

    /** 404 or 410, or a host that does not exist. */
    case Broken = 'broken';

    /** Any other error the server answered with. */
    case Erroring = 'erroring';

    /** No answer: a timeout, a refused connection, a certificate that did not check out. */
    case Unreachable = 'unreachable';

    /** Not asked: not a web address, or one this check was not allowed to ask. */
    case Unchecked = 'unchecked';
}
