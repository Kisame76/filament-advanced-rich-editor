{{--
    The document is printed unescaped on purpose: `toHtml()` put it through Symfony's
    sanitiser, and escaping it again would print the markup instead of the page.
--}}
@if ($element)
    <{{ $element }} {{ $attributes }}>{!! $html !!}</{{ $element }}>
@else
    {!! $html !!}
@endif
