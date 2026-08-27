<a href="https://github.com/Kisame76/filament-advanced-rich-editor" class="filament-hidden">
    <img src="https://repository-images.githubusercontent.com/1339516505/9678a014-7c11-4125-a271-54a76d9499a0" alt="Filament Advanced Rich Editor" style="width: 100%; max-width: 100%;" class="filament-hidden">
</a>

# Filament Advanced Rich Editor

[![Filament](https://img.shields.io/badge/Filament-5.x-FdAE4B?style=flat-square&logo=laravel&logoColor=white)](https://filamentphp.com)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/kisame76/filament-advanced-rich-editor.svg?style=flat-square)](https://packagist.org/packages/kisame76/filament-advanced-rich-editor)
[![Tests](https://img.shields.io/github/actions/workflow/status/Kisame76/filament-advanced-rich-editor/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/Kisame76/filament-advanced-rich-editor/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/kisame76/filament-advanced-rich-editor.svg?style=flat-square)](https://packagist.org/packages/kisame76/filament-advanced-rich-editor)

A drop-in replacement for Filament's `RichEditor` with a toolbar you actually control.
Group buttons, collapse them into dropdowns, separate clusters with dividers, pin the
whole thing to the top while a long document is scrolled — and get task lists, an image
tool with alt text and optional Spatie Media Library storage on top.

## What you get that the stock `RichEditor` does not

It extends Filament v5's `Filament\Forms\Components\RichEditor`, so everything you already
call — `->disableToolbarButtons()`, `->customBlocks()`, `->mergeTags()`,
`->fileAttachmentsDisk()`, `->mentions()` — keeps working. This is what is on top of it,
checked against the current Filament v5 release:

| | Filament v5 `RichEditor` | This package |
| --- | --- | --- |
| **Toolbar** | A fixed default row. Groups and dropdowns exist, but each one is assembled by hand | [Tokens](docs/documentation.md#rearranging-the-toolbar) that expand into whole groups, [dividers](docs/documentation.md#dividers), [two overflow menus](docs/documentation.md#the-tools-menu), a [pinned corner](docs/documentation.md#pinned-buttons), [start, centre, end or spread across the width](docs/documentation.md#toolbar-alignment), [sticky while scrolling](docs/documentation.md#sticky-toolbar), and [one config file](docs/documentation.md#configuration) for every field in the project |
| **Your own toolbar entries** | `->tools()` registers a tool, and you place it by hand in every toolbar array that wants it | [A token of your own](docs/documentation.md#custom-tokens) that expands wherever it is named, and [replaces a built-in one](docs/documentation.md#custom-tools) when it takes its key — `headings` defined in your config *is* the headings dropdown |
| **Headings** | All six exist; the default row offers `h2` and `h3`, and every level is a separate button | [One dropdown](docs/documentation.md#heading-levels) of the levels *this* field allows, with the plain paragraph in front of them as the way back out |
| **Lists** | Bullets and numbers | [Task lists](docs/documentation.md#lists-and-task-lists) too, plus [markers, a starting number and counting backwards](docs/documentation.md#lists-markers-numbering-and-direction) |
| **Block layout** | Four separate alignment buttons, three of them in the default row | [One alignment dropdown](docs/documentation.md#alignment) showing the alignment the caret is in, and [line spacing](docs/documentation.md#line-spacing) beside it |
| **Writing direction** | — | [`dir` on the block the caret sits in](docs/documentation.md#text-direction), so a Hebrew paragraph inside a German article reads the right way round — registered on every field, and named `ltr` / `rtl` when you want the buttons |
| **Character look** | `textColor` with an optional free picker, `highlight`, `small`, `lead` | + [a background colour with a palette of its own](docs/documentation.md#colours), [font size](docs/documentation.md#font-size), [your design system's own named styles](docs/documentation.md#styles), [a typeface picker](docs/documentation.md#fonts) |
| **Images** | `attachFiles` uploads a file — every time, even the same one | [A browser over the pictures already on the server](docs/documentation.md#media-browser), [a caption beside the alt text](docs/documentation.md#images), and [Spatie Media Library](docs/documentation.md#spatie-media-library) as an option |
| **Sizing a picture** | `->resizableImages()`, off by default, and the drag always keeps the ratio | [On by default, with the pixel size beside the pointer](docs/documentation.md#images) — a ratio lock you can open, quarter turns, a panel to type the two numbers into, and a download |
| **Links** | A URL and "open in a new tab" | + [`rel`, `referrerpolicy` and `hreflang`](docs/documentation.md#links), with `noopener noreferrer` added automatically to anything opening in a new window |
| **Video** | — | [Paste a YouTube or Vimeo link](docs/documentation.md#video-embeds) and get a player, timestamp included, through the cookie-free host |
| **Code** | A code block with no language | [A language picker on the block](docs/documentation.md#code-blocks), and syntax colours rendered in PHP |
| **Callouts** | — | [Note, tip, warning and danger](docs/documentation.md#callouts) boxes that hold whole blocks, from the bar, the slash menu or by typing `:::warning` |
| **Finding text** | The browser's `Ctrl+F` — which finds, and stops there | [Find and replace inside the field](docs/documentation.md#find-and-replace) — every hit marked at once, whole words and case optional, and replacing all of them is one undo |
| **Reaching a tool** | Aim at the bar | [Type `/`](docs/documentation.md#slash-menu) for the commands *this* field offers, or use [the grip in the margin](docs/documentation.md#drag-handle) to move a block and the plus to start one |
| **Formatting what you selected** | `->floatingToolbars()` exists; the only bar Filament ships is the one over a table cell | [A bar over the selected text](docs/documentation.md#toolbar-over-a-selection) carrying the styles, the marks, the link and both colour pickers — taking the same tokens the main toolbar does |
| **Pasting from Word** | What Word wrote, minus what the sanitiser strips on save | [Cleaned on the way in](docs/documentation.md#pasting-from-word-and-google-docs): the list Word writes as twelve paragraphs is a list again, and somebody else's fonts, sizes and colours stay behind |
| **Accessibility** | — | [A check](docs/documentation.md#accessibility-check) for missing alt text, "click here" links, skipped heading levels, tables with no header row and colours too weak to read — and [`lang` on a passage](docs/documentation.md#language-of-a-passage), which is what WCAG 3.1.2 actually asks for |
| **Closing the tab** | The text is gone | [A draft kept in the browser](docs/documentation.md#drafts-in-the-browser), offered back the next time the field is opened, and a warning before the tab closes |
| **Seeing the HTML** | — | [The document in Filament's own code editor](docs/documentation.md#source-code) |
| **How long it is** | `minLength()` / `maxLength()`, and an untouched field still stores one empty paragraph | [A visible counter](docs/documentation.md#character-count) in characters or words, [an empty document that fails `required`](docs/documentation.md#required-and-what-counts-as-empty) instead of passing it, and [`null` in the column](docs/documentation.md#storing-nothing-instead-of-pp) when you ask for it |
| **Mentions** | The `@` menu — the ids sit in the document, and reading them back out is yours to write | [A picture and a line of context](docs/documentation.md#mentions) under each name, so five people called the same thing are five different rows — and `Mentions::in($post->content)` for the `saved()` hook that has to send the mail |
| **Editor chrome** | — | [Fullscreen](docs/documentation.md#fullscreen), [a maximum height](docs/documentation.md#maximum-height), [a shortcut list](docs/documentation.md#help), [emoji](docs/documentation.md#emoji) and [special characters](docs/documentation.md#special-characters) |
| **Rendering** | `toHtml()`, `toText()`, `toArray()` | + [heading anchors](docs/documentation.md#anchors), [a table of contents](docs/documentation.md#table-of-contents) from the same slug pass, [column widths that reach the page](docs/documentation.md#table-column-widths), [Markdown](docs/documentation.md#markdown) with the checkboxes intact, and [an excerpt](docs/documentation.md#excerpts) for the meta description — plus the two repairs that made one possible, a sentence that stops breaking apart at a link and entities that are spelled out |
| **And the smaller half** | Mostly yours to write | The parts you reach for once rather than daily: [rebuilding the image bar button by button](docs/documentation.md#floating-toolbars), [normalising imported HTML through the field's own schema](docs/documentation.md#source-code), [the character and word counts as numbers](docs/documentation.md#character-count), [asking a stored document whether it is blank](docs/documentation.md#required-and-what-counts-as-empty), and [swapping any icon](docs/documentation.md#icons) or [translating any label](docs/documentation.md#translations). The [contents list](docs/documentation.md#contents) is the whole of it |

Everything is off, on or replaceable per field, and the defaults live in one config file. The
[documentation](docs/documentation.md) has a section per row above, and the last row is a
list of the ones that did not need one.

## Requirements

- PHP 8.2+
- Filament v5.7+

## Installation

```bash
composer require kisame76/filament-advanced-rich-editor
```

The CSS and the task list scripts auto-register with Filament. After install (and on
deploy) run:

```bash
php artisan filament:assets
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag="filament-advanced-rich-editor-config"
```

### Raise Livewire's nesting limit

One line, and not optional. Livewire caps the depth of a property path at `10` and answers
anything deeper with a 500. The editor entangles a TipTap document, and text inside a list item
is already eleven levels deep — so typing in any list, or saving afterwards, throws
`MaxNestingDepthExceededException`.

Publish Livewire's config if you have not already, then raise the limit inside `payload`:

```bash
php artisan livewire:publish --config
```

```php
// config/livewire.php
'payload' => [
    // …
    'max_nesting_depth' => 32,   // 10 is Livewire's default and is not enough
],
```

Change that one line rather than pasting a whole `payload` block — a published `payload`
replaces the vendor one outright, and its other keys differ between Livewire releases.

Upgrading does not help: every release from 4.1 to 4.4.1 ships the same default. Nor is this
package the cause — a stock Filament `RichEditor` with a plain bullet list does the same. It
just ships the task lists, tables and details that make documents deep, so you meet it here
first.

## Usage

Swap `RichEditor` for `AdvancedRichEditor`. Everything you already call on Filament's field
keeps working, and the toolbar becomes yours to arrange:

```php
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;

AdvancedRichEditor::make('content')
    ->toolbarButtons([
        ['undo', 'redo'],
        'divider',
        ['headings', 'bold', 'italic'],
        'divider',
        ['lists', 'link', 'image', 'embed'],
        'pin',
        ['fullscreen', 'help'],
    ])
    ->stickyToolbar()
    ->columnSpanFull()
```

Rendering stored content works the same way, with the additions this package makes:

```php
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;

AdvancedRichContentRenderer::make($article->content)
    ->anchorHeadings()
    ->cached()
    ->toHtml();
```

**[Read the full documentation](https://github.com/Kisame76/filament-advanced-rich-editor/blob/main/docs/documentation.md)** for every option: the toolbar tokens and dropdowns,
the media browser, the slash menu, video embeds, code blocks, mentions, the table of contents,
Markdown export and the config file.

## What's next

The editor is finished in the sense that it does what it says. It is not finished in the
sense that there is nothing left to build.

- **AI tools in the editor.** Rewrite a paragraph, shorten it, fix the tone, draft alt text
  for a picture you just dropped in. This is the next big one, and it is deliberately not
  rushed: it has to work with whichever provider a project already pays for, and it has to
  be switched off by default. Coming in a later release.

Nothing here is a promise with a date on it. If one of them is what you need, say so and it
moves up.

Done since this list was written:

- ~~**A richer mention menu.**~~ Rows carry a picture and a line of context now, and a row
  without a picture is drawn with the initials of the name. See
  [Mentions](docs/documentation.md#mentions).
- ~~**Tests for the media browser's front end.**~~ The browser moved out of the Blade
  attribute it was written in and into `resources/js/media-picker.js`, where fifty tests
  cover paging, folders, filtering, the details panel, uploads and drag and drop.

## Found a bug? Got an idea?

Both are welcome, and neither needs an apology.

- **Something broken?** [Open an issue](https://github.com/Kisame76/filament-advanced-rich-editor/issues). A short reproduction beats a long
  description, but a long description beats staying quiet.
- **Missing a feature, or the API feels wrong?** [Open an issue](https://github.com/Kisame76/filament-advanced-rich-editor/issues) too. A good number
  of the things in this package exist because the shape somebody suggested was better than the
  one that was there.
- **Wrote a fix?** Pull requests are read and answered. `composer test`, `composer pint` and
  `composer analyse` all have to pass, and the suite runs against SQLite, MySQL and PostgreSQL.

Security reports go to the address in [SECURITY.md](SECURITY.md), never to the issue tracker.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Security reports go to the address in
[SECURITY.md](SECURITY.md) rather than to the issue tracker.

## License

MIT.
