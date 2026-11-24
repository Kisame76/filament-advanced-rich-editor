{{--
    The document a PDF driver prints. Publish the package's views to change what stands
    around the content; the content itself arrives finished - sanitised by the renderer, then
    reduced by `PdfMarkup` to what a PDF engine draws.
--}}
<!DOCTYPE html>
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    @if (filled($title))
        <title>{{ $title }}</title>
    @endif
    <style>
{!! $stylesheet !!}
    </style>
</head>
<body>
    {{--
        The header and the footer are children of <body> on purpose: DomPDF repeats a fixed
        element on every page only from there. The cover keeps them off its own page by having
        no margins for them to stand in - see `PdfPage::coverCss()`.
    --}}
    @if (filled($cover))
        <section class="fi-arte-pdf-cover">
{!! $cover !!}
        </section>
    @endif
    @if (filled($header))
        <div class="fi-arte-pdf-header">{!! $header !!}</div>
    @endif
    @if (filled($footer))
        <div class="fi-arte-pdf-footer">{!! $footer !!}</div>
    @endif
    @if (filled($tableOfContents))
        <nav class="fi-arte-pdf-toc">
{!! $tableOfContents !!}
        </nav>
    @endif
    <main class="fi-arte-pdf">
{!! $body !!}
    </main>
</body>
</html>
