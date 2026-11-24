{{--
    The list of parts `RichEditorPdf::tableOfContents()` prints after the cover. Each entry
    links to where its part starts; `$entry['page']` is the number of that page as DomPDF
    writes it in, and empty with a Chromium driver. Publish the package's views to change it.
--}}
<h1 class="fi-arte-pdf-toc-heading">{{ $heading }}</h1>
<table class="fi-arte-pdf-toc-entries">
    @foreach ($entries as $entry)
        <tr>
            <td class="fi-arte-pdf-toc-title"><a href="#{{ $entry['target'] }}">{{ $entry['title'] }}</a></td>
            <td class="fi-arte-pdf-toc-page">{{ $entry['page'] }}</td>
        </tr>
    @endforeach
</table>
