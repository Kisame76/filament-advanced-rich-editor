<a href="https://github.com/Kisame76/filament-advanced-rich-editor" class="filament-hidden">
    <img src="https://raw.githubusercontent.com/Kisame76/filament-advanced-rich-editor/main/art/social-preview.jpg" alt="Filament Advanced Rich Editor" style="width: 100%; max-width: 100%;" class="filament-hidden">
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

- **Same field, better toolbar** — extends `Filament\Forms\Components\RichEditor`, so every
  method you already use (`->disableToolbarButtons()`, `->customBlocks()`, `->mergeTags()`,
  `->fileAttachmentsDisk()`, …) keeps working
- **Toolbar tokens** — write `'divider'`, `'headings'` or `'lists'` anywhere in the toolbar
  array and they expand into real components
- **Dropdowns** — fold any set of buttons behind one trigger, with icons or icon + label
- **Sticky toolbar** — stays reachable in long documents, with a configurable offset
- **Heading levels 1 to 6** — not just the stock `h2` / `h3`
- **Task lists** — checkbox lists as a proper TipTap plugin, with the JS loaded on request
- **Image tool** — insert and re-edit images including their alt text and caption
- **Media browser** — the image button opens the pictures already on the server, so one file
  can be reused across articles instead of uploaded again
- **Spatie Media Library** — opt in per field to store attachments in a media collection
- **Anchored headings and a table of contents** — both from one slug pass, so a link in the
  list and an `id` on the page cannot drift apart
- **Links with `rel`, `referrerpolicy` and `hreflang`** — and `noopener noreferrer` added
  automatically to anything opening in a new window
- **Mention menu with faces** — a picture and a line of context under each name, so five
  people called the same thing are five different rows
- **Slash menu** — type `/` for a searchable list of the commands *this* field offers
- **Find and replace** — `Ctrl+F` inside the field, every hit marked, whole words and case
  optional, and replacing all of them is one undo
- **Drafts in the browser** — a lost reply is not a lost article: the draft is offered back
  on the next opening, and closing the tab with unsaved changes asks first
- **Drag handle** — a grip in the margin to move a block and a plus to start one, which
  opens the slash menu rather than inserting a paragraph
- **Paste from Word and Google Docs** — the list Word writes as twelve paragraphs is a list
  again, and the fonts, sizes and colours of somebody else's document stay behind
- **Video embeds** — paste a YouTube or Vimeo link and get a player, timestamp included,
  through the cookie-free host
- **Code blocks** — a language picker on the block, and syntax colours rendered in PHP
- **Markdown export** — task lists keep their checkboxes
- **Configurable project-wide** — one config file sets the default toolbar for every field

## Requirements

- PHP 8.2+
- Filament v5

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
'max_nesting_depth' => 32,   // 10 is Livewire's default and is not enough
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
