<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedProviders;

/**
 * A map from Google Maps, as its "Embed a map" dialog hands it out.
 *
 * Only that form is read. A place link or a `maps.app.goo.gl` short link names a place, not a
 * map someone composed, and turning one into a frame takes an API key this package does not
 * have. The embed code is accepted whole - the dialog takes the `<iframe>` somebody copied and
 * keeps its address.
 *
 * The id is the `pb` parameter as it was written, percent-encoding included: it is put back
 * into the address exactly as it came.
 */
class GoogleMaps extends EmbedProvider
{
    public function name(): string
    {
        return 'google_maps';
    }

    public function hosts(): array
    {
        return ['google.com', 'www.google.com', 'maps.google.com'];
    }

    public function patterns(): array
    {
        return ['^[^/]+/maps/embed\?(?:[^#]*&)?pb=(?<id>[^&#]+)'];
    }

    public function id(): string
    {
        return '[A-Za-z0-9!._%-]{10,2000}';
    }

    public function src(): string
    {
        return 'https://www.google.com/maps/embed?pb={id}';
    }

    public function share(): string
    {
        return $this->src();
    }

    /**
     * The one host the map is framed from, rather than the three a link is read from: the
     * sanitiser's list should be as short as what is actually framed.
     */
    public function frameHosts(): array
    {
        return ['www.google.com'];
    }

    public function ratio(): string
    {
        return '4 / 3';
    }
}
