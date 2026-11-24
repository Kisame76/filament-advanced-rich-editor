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
    <main class="fi-arte-pdf">
{!! $body !!}
    </main>
</body>
</html>
