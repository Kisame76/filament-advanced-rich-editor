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
- **Image tool** — insert and re-edit images including their alt text
- **Spatie Media Library** — opt in per field to store attachments in a media collection
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

## Usage

Swap the import — that is the whole migration:

```php
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;

AdvancedRichEditor::make('content')
    ->label('Content')
    ->columnSpanFull();
```

### The default toolbar

Out of the box the field renders the layout from `config/filament-advanced-rich-editor.php`:

```php
[
    ['undo', 'redo'],
    'divider',
    ['headings', 'fontSize', 'blockquote', 'codeBlock'],
    'divider',
    ['bold', 'italic', 'strike', 'underline', 'link'],
    'divider',
    ['superscript', 'subscript'],
    'divider',
    ['alignment', 'lists'],
    'divider',
    ['image', 'table'],
]
```

Each nested array is one visually grouped cluster. Change the config to change every
editor in the project at once.

### Rearranging the toolbar

`toolbarButtons()` is Filament's own method and takes the same widened array — button
names, nested groups, tokens and component instances, mixed freely:

```php
AdvancedRichEditor::make('content')
    ->toolbarButtons([
        ['bold', 'italic', 'link'],
        'divider',
        ['headings'],
        'divider',
        ['bulletList', 'orderedList', 'taskList'],
        ['image', 'attachFiles'],
    ]);
```

Every button Filament ships is available (`bold`, `italic`, `underline`, `strike`,
`subscript`, `superscript`, `link`, `h1`–`h6`, `alignStart`, `alignCenter`, `alignEnd`,
`alignJustify`, `blockquote`, `codeBlock`, `bulletList`, `orderedList`, `table`,
`attachFiles`, `customBlocks`, `mergeTags`, `undo`, `redo`), plus this package's `image`
and `taskList`.

### Dividers

`'divider'` renders a vertical rule between two clusters. Use the class directly if you
prefer explicit objects:

```php
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDivider;

->toolbarButtons([
    ['bold', 'italic'],
    ToolbarDivider::make(),
    ['link'],
])
```

### Dropdowns

Two tokens build a dropdown for you from the field's own configuration:

- `'headings'` — one entry per configured heading level
- `'lists'` — one entry per configured list type, including the task list when it is enabled

Both render with a label next to the icon, and the trigger mirrors the icon of whatever is
active in the current selection. The options reuse the editor's registered tools, so their
labels are Filament's own (`h1` reads *Title*, `h2` *Heading 2*, …) — override those in your
app's `lang/vendor/filament-forms` files, or build a dropdown with labels of your own.

Build your own with `ToolbarDropdown`, which extends Filament's `ToolbarButtonGroup`:

```php
use Filament\Support\Icons\Heroicon;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDropdown;

->toolbarButtons([
    ['bold', 'italic'],
    ToolbarDropdown::make('Alignment', ['alignStart', 'alignCenter', 'alignEnd'])
        ->icon(Heroicon::Bars3BottomLeft)
        ->textualButtons(),   // show the label next to each option
])
```

`ToolbarDropdown::make()` takes the trigger label first and the button names second; the
names are resolved against the editor's registered tools, so an unknown or disabled button
is simply dropped.

### Custom tokens

Anything you find yourself repeating can become a token of its own. Tokens are keyed
closures that receive the field and return a component (or a plain button name), and they
are merged over the built-in ones — defining `headings` here replaces the built-in
dropdown:

```php
// config/filament-advanced-rich-editor.php

use Filament\Support\Icons\Heroicon;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDropdown;

'tokens' => [
    'inline' => fn (AdvancedRichEditor $editor) => ToolbarDropdown::make('Inline', [
        'bold', 'italic', 'strike', 'underline',
    ])->icon(Heroicon::Bold)->textualButtons(),
],
```

```php
->toolbarButtons([['undo', 'redo'], 'divider', ['inline', 'link']])
```

A config file holding closures cannot be cached, so register tokens from a service
provider instead if you run `php artisan config:cache`.

### Custom tools

A token can just as well return a `RichEditorTool`, which is how the package's own `image`
button is built:

```php
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Icons\Heroicon;

'tokens' => [
    'highlight' => fn () => RichEditorTool::make('highlight')
        ->label('Highlight')
        ->icon(Heroicon::PaintBrush)
        ->jsHandler('$getEditor()?.chain().focus().toggleHighlight().run()')
        ->activeKey('highlight'),
],
```

Tools that need a TipTap extension belong in a `RichContentPlugin` — see Filament's
[rich content plugin docs](https://filamentphp.com/docs/5.x/forms/rich-editor) and this
package's `TaskListPlugin` for a worked example.

### Alignment

The four alignment buttons live in one dropdown whose trigger shows the alignment the
caret is currently in - left by default, and the justify icon while the cursor sits in a
justified paragraph. Its options are labelled after what they do (Left, Center, Right,
Justify) while the underlying tool names stay logical (`alignStart`, `alignEnd`), so
right-to-left content keeps working; the wording is translatable.

```php
AdvancedRichEditor::make('content')
    ->alignments(['alignStart', 'alignCenter', 'alignEnd']);   // default: config('...alignments')
```

### Toolbar alignment

The groups are centred on the bar. Filament's own editor is left aligned; change it per
field or project-wide through `toolbar_alignment`:

```php
AdvancedRichEditor::make('content')
    ->toolbarAlignment('start');            // 'start' | 'center' | 'end' | 'between'
    // ->toolbarAlignment(Alignment::End);  // Filament's enum works too
```

### Sticky toolbar

The toolbar is pinned by default. `offset` is any CSS length and should match the height of
whatever sits above the form — the panel topbar in a standard Filament panel:

```php
AdvancedRichEditor::make('content')
    ->stickyToolbar()                  // default: config('filament-advanced-rich-editor.sticky.enabled')
    ->stickyToolbarOffset('4rem');     // default: config('filament-advanced-rich-editor.sticky.offset')

AdvancedRichEditor::make('excerpt')
    ->stickyToolbar(false);            // short field, nothing to pin
```

### Heading levels

The six heading tools are relabelled to "Heading 1" … "Heading 6" (Filament labels `h1`
"Title", which reads wrong next to the other levels in a dropdown). Override the wording
through the `tools.heading_level` translation key.

```php
AdvancedRichEditor::make('content')
    ->headingLevels([2, 3, 4]);
```

The levels drive both the `'headings'` dropdown and which of the `h1`–`h6` buttons are
available. Only 1 to 6 are valid — anything else throws a `LogicException` at build time
rather than silently rendering a broken editor.

### Lists and task lists

```php
AdvancedRichEditor::make('content')
    ->listTypes(['bulletList', 'orderedList', 'taskList'])
    ->taskList();                      // default: config('filament-advanced-rich-editor.task_list.enabled')
```

The task list adds checkbox items that stay checkable in the editor and are saved as
`<ul data-type="taskList">` in the content. Its TipTap extensions are registered as
`loadedOnRequest()` assets, so nothing extra is downloaded on pages without a task list.
`->taskList(false)` unregisters the `taskList` tool, so the entry disappears from the
`'lists'` dropdown on its own and the same `listTypes()` config works for fields with and
without it.

Both the browser extension and the PHP renderer stamp the `fi-arte-task-list` /
`fi-arte-task-item` classes onto the saved markup, so the same stylesheet covers the editor,
Filament text entries and your own front end. The package CSS is registered with Filament and
therefore loads in the panel only — copy those rules into your front end stylesheet if you
render the content outside Filament.

The checkbox sits on the optical centre of the item's first line at any text size. A
stylesheet can only size it against the list item's own font, so the editor's node view
measures the real first line - including an inline font size applied to the text - and
hands the result to CSS as `--fi-arte-task-line`. Outside the editor the stylesheet falls
back to the line box, which is correct for content that carries no inline sizes.

The saved markup carries no `<input type="checkbox">`. Filament sanitises rich content before
it reaches a page, and its sanitiser removes `input` elements as well as the `data-checked`
attribute, which would take the tick state with them. The state is therefore also written as a
`fi-arte-task-item-checked` class — `class` survives sanitisation — and the checkbox itself is
drawn in CSS. Inside the editor a real checkbox is rendered by the node view, so ticking an
item works as usual.

### Font size

The `fontSize` token renders a stepper - minus, an editable number, plus - that applies an
inline size to the selection, and follows the caret as it moves through text of different
sizes. The size is written into the `style` attribute, which is what Filament's HTML
sanitiser keeps, so it survives all the way to the rendered page.

Where the text carries no size of its own, the stepper reports the size the browser
actually renders (the theme's, or a heading's while the caret is inside one) rather than a
fixed starting value, so the first click never jumps the wrong way. `default` is only used
when that measurement fails.

```php
AdvancedRichEditor::make('content')
    ->fontSize()                                            // default: config('...font_size.enabled')
    ->fontSizeOptions(min: 10, max: 40, step: 2, default: 16);
```

`->fontSize(false)` removes the stepper and the TipTap extensions with it, so nothing
writes or parses a size. Anything left out of `fontSizeOptions()` keeps the configured
default from the `font_size` section.

### Images

The `image` tool inserts an image and, when the cursor sits on one, re-opens the same
dialog pre-filled with its `src` and `alt` so the alt text can be corrected without
deleting the node. It uses Filament's normal file attachment pipeline, so the usual
methods apply:

```php
AdvancedRichEditor::make('content')
    ->fileAttachmentsDisk('public')
    ->fileAttachmentsDirectory('editor')
    ->fileAttachmentsVisibility('public');
```

Inserted images can be dragged to a new size, with the pixel size shown next to the
pointer while dragging. Clicking an image opens a floating toolbar carrying a lock: with
the ratio unlocked, a drag changes width and height independently, so a square can be
pulled into a rectangle. Holding shift during a drag always keeps the ratio, whichever way
the lock is set.

An image whose file does not load is drawn as a dashed placeholder rather than as an empty
hole: the node keeps its width and height, so without it a broken source looks like a bug
in the editor instead of a missing file.

Filament fixes its own resizing to the image's aspect ratio and reads that setting once,
when the editor is built, so it cannot be reconfigured later. The lock therefore works on
the node view that performs the drag - no part of Filament's image extension is replaced -
and the state lives on the editor rather than on the image, because it modifies a drag
rather than describing content.

Inserted images can be dragged to a new size. Filament ships that switched off; this
package turns it on because the image tool is part of the default toolbar. The default
lives in `images.resizable`, and a field always wins:

```php
AdvancedRichEditor::make('content')->resizableImages(false);
```

### Spatie Media Library

Optional. Install `spatie/laravel-medialibrary`, make the model implement `HasMedia`, and
opt the field in — attachments then land in a media collection instead of a raw disk path:

```php
AdvancedRichEditor::make('content')
    ->spatieMediaLibrary(
        collection: 'rich-editor',   // default: config('filament-advanced-rich-editor.spatie.collection')
        conversion: 'web',           // null (default) embeds the original file
        disk: 's3',                  // null falls back to the collection's own disk
        visibility: 'public',
    );
```

The record is resolved from the field at runtime, so this works on create pages too: the
attachment is associated once the record exists. Defaults come from the `spatie` section of
the config, and the whole feature is inert until you call the method.

## Configuration

```php
AdvancedRichEditor::make('content')
    ->toolbarButtons([...])                        // full toolbar layout
    ->stickyToolbar(true)                          // pin the toolbar while scrolling
    ->stickyToolbarOffset('4rem')                  // distance from the top of the viewport
    ->headingLevels([1, 2, 3, 4])                  // levels offered by the headings dropdown
    ->listTypes(['bulletList', 'orderedList', 'taskList'])
    ->taskList(true)                               // checkbox task lists
    ->spatieMediaLibrary('rich-editor');           // opt into media library attachments
```

Every setter also accepts a closure, so anything can depend on the record or the current
user. Project-wide defaults live in `config/filament-advanced-rich-editor.php`.

### Translations

Tool labels are translatable. English (`en`) and German (`de`) ship with the package;
publish the language files to add or override locales:

```bash
php artisan vendor:publish --tag="filament-advanced-rich-editor-translations"
```

This writes `lang/vendor/filament-advanced-rich-editor/{locale}/advanced-rich-editor.php`,
where each locale defines `tools.image` (the image tool), `tools.task_list` (the task list
tool) and `tools.headings` / `tools.lists` (the two dropdown triggers). Every other label in
the toolbar is Filament's own and lives in `filament-forms::components.rich_editor`.

### Theming

Everything the package adds is prefixed with `fi-arte-` and can be restyled from your panel
theme:

```css
/* Opaque background painted behind the pinned toolbar. */
.fi-fo-rich-editor-toolbar.fi-arte-sticky {
  --fi-arte-sticky-bg: #fff;
}

.fi-arte-toolbar-divider { /* the vertical rule between clusters, --fi-arte-divider-color */ }
.fi-arte-task-list { /* <ul data-type="taskList"> */ }
.fi-arte-task-item { /* a single checkbox item */ }
```

`--fi-arte-sticky-offset` is the one exception: the field writes it as an inline style, so it
cannot be overridden from a stylesheet. Use `->stickyToolbarOffset()` — or the `sticky.offset`
config key — instead.

The blade view is publishable too, if the toolbar markup itself needs changing:

```bash
php artisan vendor:publish --tag="filament-advanced-rich-editor-views"
```

## How it works / caveats

- **It is still Filament's editor.** The field extends `RichEditor` and only widens
  `getToolbarButtons()`: string tokens and component instances are resolved right before
  render, everything else is passed through untouched. Filament's own toolbar features
  (custom blocks, merge tags, tables, mentions) keep working alongside the additions.
- **Tokens resolve recursively.** `'divider'`, `'headings'` and `'lists'` work at any
  nesting depth, so a token inside a group behaves exactly like a button name there.
- **Dropdowns never render dead entries.** Dropdown entries are resolved against the
  editor's registered tools, so a name with no matching tool — `taskList` on a field with
  `->taskList(false)`, for instance — is dropped instead of rendering a menu item that does
  nothing. `->disableToolbarButtons()` works on the toolbar array itself and therefore does
  not reach inside a `'headings'` or `'lists'` dropdown; narrow those with
  `->headingLevels()` / `->listTypes()`.
- **Sticky needs a scroll container.** The toolbar uses `position: sticky`, which only
  takes effect while an ancestor actually scrolls. Inside a modal with its own
  `overflow: auto` body, set the offset to `0` — the panel topbar is not in that
  scroll context.
- **Task list content is portable.** Task lists are stored as standard TipTap task list
  nodes (`<ul data-type="taskList">`), so `RichContentRenderer` and any other TipTap consumer
  read them without this package installed — only the `fi-arte-` classes and the checkbox
  styling come from here.
- **The deferred save is patched.** When a file attachment provider needs a persisted
  record — the media library one does, because media hangs off a saved model — Filament
  writes the state back after the record is created and resolves the attribute name through
  `getContentAttribute()`, which only exists when the *model* registers rich content. The
  field overrides `saveFileAttachmentsToRecord()` to fall back to its own name, so plain
  models work on create pages instead of fataling on the first upload.
- **Media library is opt-in.** Without `->spatieMediaLibrary()` no media library code is
  touched, and the package works fine when `spatie/laravel-medialibrary` is not installed
  at all.

## License

MIT.
