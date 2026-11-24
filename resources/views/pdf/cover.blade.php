{{--
    The cover `RichEditorPdf::cover()` prints where it is given nothing of the project's: the
    title, and the date beneath it. Publish the package's views to change it everywhere, or
    hand `cover()` markup of your own for one export.
--}}
<div class="fi-arte-pdf-cover-default">
    @if (filled($title))
        <h1 class="fi-arte-pdf-cover-title">{{ $title }}</h1>
    @endif
    @if (filled($date))
        <p class="fi-arte-pdf-cover-date">{{ $date }}</p>
    @endif
</div>
