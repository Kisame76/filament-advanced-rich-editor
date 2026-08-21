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
- **Anchored headings and a table of contents** — both from one slug pass, so a link in the
  list and an `id` on the page cannot drift apart
- **Links with `rel`, `referrerpolicy` and `hreflang`** — and `noopener noreferrer` added
  automatically to anything opening in a new window
- **Slash menu** — type `/` for a searchable list of the commands *this* field offers
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
    ['headings', 'fontFamily', 'fontSize'],
    'divider',
    ['bold', 'italic', 'underline', 'strike', 'textColor', 'textBackground'],
    'divider',
    ['alignment', 'lineHeight'],
    'divider',
    ['lists', 'link', 'image', 'table', 'blockquote', 'codeBlock'],
    'divider',
    ['more'],
    'pin',
    ['sourceCode', 'fullscreen', 'help'],
]
```

Each nested array is one visually grouped cluster, and the groups answer one question each:
what came before, what the text *is* (its block type, its typeface, its size), how the
characters look, how the block is laid out, what to put into the document, how to view it.
Left to right that is also the order the decisions are made in — you pick a heading before
you pick a font, and you emphasise a word before you decide how the paragraph sits.

The two block dropdowns are apart on purpose. Alignment and line spacing shape a paragraph,
so they share a group; a list is a thing you *make*, which is why it sits with the link, the
image, the table, the quote and the code block rather than with the alignment. Those five
all insert something, and that is what groups them rather than whether they happen to be a
mark or a node.

The last group sits behind `'pin'` — see [Pinned buttons](#pinned-buttons). Those three are
about the *editor* rather than about the text, so they keep a corner of the bar to
themselves instead of moving with everything else. The overflow menu is not one of them:
what it holds are tools for the text, so it stays with the aligned groups and ends them.

The tools most documents never need do not get a button of their own: `superscript`,
`subscript`, inline `code`, `clearFormatting`, `horizontalRule` and `details` sit in the
`'more'` dropdown at the end, together with this package's own `emoji` picker and the two
direction buttons. Every other Filament tool - `highlight`, `small`, `lead`, `attachFiles`,
`mergeTags`, `customBlocks` and the table editing ones - is registered too, so naming it
anywhere in the array, or in the `more` list, brings it into the bar.

Change the config to change every editor in the project at once.

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

Five tokens build a dropdown for you from the field's own configuration:

- `'headings'` — one entry per configured heading level
- `'lists'` — one entry per configured list type, including the task list when it is enabled
- `'alignment'` — one entry per configured alignment, see [Alignment](#alignment)
- `'lineHeight'` — one entry per configured spacing, see [Line spacing](#line-spacing)
- `'more'` — one entry per tool in the `more` list, see [The more menu](#the-more-menu)

All five render with a label next to the icon. `'headings'`, `'lists'` and `'alignment'`
mirror the icon of whatever is active in the current selection on their trigger; `'more'`
keeps its three dots, because an overflow menu that hides its own handle is one you cannot
find your way back to, and `'lineHeight'` keeps its own icon because its options are
numbers and there is nothing to swap it for. Both still highlight while one of their tools
is on. The options reuse the editor's
registered tools, so their labels are Filament's own (`h1` reads *Title*, `h2` *Heading 2*,
…) — override those in your app's `lang/vendor/filament-forms` files, or build a dropdown
with labels of your own.

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

### Line spacing

The `'lineHeight'` dropdown sits next to the alignment and writes a unitless `line-height`
into the block's inline style. Unitless is what makes it scale: a heading keeps its own
proportions instead of inheriting a paragraph's leading, and the same document reads the
same in the editor, in a saved page and in print.

```php
AdvancedRichEditor::make('content')
    ->lineHeights([1, 1.15, 1.5, 2])   // default: config('...line_height.values')
    // ->lineHeight(false);            // no dropdown, and no attribute in the schema
```

`1` and `2` are labelled *Single (1.0)* and *Double (2.0)*; every other value shows its
number. Picking the spacing a block already has takes it back off, which is the way back to
whatever your theme sets — there is no "default" entry to hunt for.

Values are bare numbers between 0.5 and 5. Anything else — `150%`, `24px`, a pasted
`inherit` — is dropped rather than carried through, on both sides: Filament's sanitiser
allows `style` on every element but does not look inside CSS, so a value with a unit in it
would be a way to smuggle a second declaration in behind a semicolon. `1.50` and `1.5` are
also collapsed into one option, because the toolbar compares the stored value against the
one its button carries.

Paragraphs, headings, quotes and list items carry a spacing. Turning the dropdown off drops
the extension with it, so a field that has none stops declaring the attribute — and content
that already carries one loses it on the next save, the same way the text direction does.

### Toolbar alignment

The groups are centred on the bar. Filament's own editor is left aligned; change it per
field or project-wide through `toolbar_alignment`:

```php
AdvancedRichEditor::make('content')
    ->toolbarAlignment('start');            // 'start' | 'center' | 'end' | 'between'
    // ->toolbarAlignment(Alignment::End);  // Filament's enum works too
```

### Pinned buttons

`'pin'` is not a thing on the bar but a place in it: everything after it is pushed to the
far end of the toolbar instead of travelling with the aligned groups. The source view, the
fullscreen switch and the help dialog live there by default — they are controls for the
editor, not for the text, and they should stay in the same corner whatever the rest of the
bar does.

```php
->toolbarButtons([
    ['bold', 'italic'],
    'pin',
    ['fullscreen', 'help'],   // hard against the far edge
])
```

The centred groups stay centred **on the whole bar**, not on what is left of it, so pinning
does not nudge the toolbar sideways. There is nothing to configure: the pinned buttons take
the edge the aligned groups are not pushed against — the end normally, and the start when
the toolbar itself is aligned to the end, because that bar has already taken the end for
itself.

The marker may sit anywhere, including inside a group, and it is dropped either way; a
second one has nothing left to split and goes the same way. Each half collapses its own
dividers, so a rule left leading or trailing by the split disappears rather than floating
in the gap. `->disableToolbarButtons(['pin'])` puts the whole bar back into one row.

### The more menu

The `'more'` token renders the overflow dropdown at the end of the toolbar: the tools that
earn a place in the editor but not a button of their own.

```php
AdvancedRichEditor::make('content')
    ->moreTools([
        'subscript', 'superscript', 'code', 'clearFormatting', 'horizontalRule', 'details',
        'emoji',
    ]);
```

That is also the default, and it lives in `config('filament-advanced-rich-editor.more')`.
Any Filament tool name is valid; an unknown one is dropped while resolving, exactly as it is
inside every other dropdown. An empty list removes the dropdown along with its trigger:

```php
AdvancedRichEditor::make('content')->moreTools([]);
```

Build another one anywhere with `ToolbarDropdown::more([...])`, or keep any dropdown's
trigger from following the caret with `->staticIcon()`.

The last entry is this package's own: the [emoji picker](#emoji), which has a switch of its
own. The two [direction](#text-direction) buttons are registered but not listed - name them
where you want them.

### Emoji

`'emoji'` opens a picker with the full Unicode emoji list - 1906 of them, searchable by
their Unicode names.

The tabs follow the grouping every phone keyboard uses rather than Unicode's own: what
Unicode files under *Smileys & Emotion* and *People & Body* is one tab here, because
someone looking for a face does not know which of the two it landed in. The first tab is
the emoji picked most recently, kept in the browser's own storage - it belongs to the
person, not to the record, and the same handful get reached for across every form in the
panel. Its icons are drawn ones from the `icons` registry (`emoji_recent`, `emoji_smileys`,
… ) rather than a representative emoji, so they read as chrome and can be swapped like
every other icon.

```php
AdvancedRichEditor::make('content')->emoji(false);   // default: config('...emoji')
```

An emoji is inserted as a plain Unicode character, so nothing about it is markup: it needs
no extension on the PHP side, survives the sanitiser and `RichContentRenderer` like any
other letter, and switching the picker off later leaves every emoji already written where
it is.

The picker opens under the line being written, not over it, and stays open until it is
dismissed: picking one emoji is rarely the whole job. Its header closes it and doubles as a
drag handle, Escape closes it, and so does a click anywhere outside it - except inside the
editor, where a click is how the caret gets moved to the next spot an emoji belongs in.

The list is a second asset, imported by the picker the first time it opens - an editor
nobody clicks that button in never loads 60 KB of emoji. The names come from Unicode's own
`emoji-test.txt` (see `resources/js/emoji-data.js`), skin tone variants left out.

### Text direction

`'ltr'` and `'rtl'` write a `dir` attribute onto the block the caret sits in - the
paragraph, heading, blockquote, list item or code block - which is the HTML that means this
and one of the attributes Filament's sanitiser lets through untouched.

**Neither is in the default toolbar.** `dir` is not the alignment dropdown in different
clothes: alignment says where a line sits in its box, `dir` says which way the text runs,
which is what orders mixed scripts, digits and punctuation and which side a list marker sits
on. In a document written in one left-to-right language it changes nothing you can see, so
it is registered and left out. Name the tools where you want them:

```php
AdvancedRichEditor::make('content')
    ->moreTools(['subscript', 'superscript', 'emoji', 'ltr', 'rtl']);
```

Picking the direction a block already has takes it back off, the same way the headings
dropdown returns a heading to a paragraph.

```php
AdvancedRichEditor::make('content')->textDirection(false);   // default: config('...text_direction')
```

That switch is a heavier thing than leaving the buttons out. Unlike the emoji this half is
part of the schema, and content is re-parsed on every hydration - so an editor that stops
declaring `dir` drops it on the next save, in documents that already carry one. Leaving the
extension registered and the buttons unlisted costs one small script and keeps that content
intact. The same applies to rendering saved content: pass the plugin, or the attribute is
dropped.

```php
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\TextDirectionPlugin;

RichContentRenderer::make($html)->plugins([TextDirectionPlugin::make()])->toHtml();
```

### Slash menu

Typing `/` on an empty line, or after a space, opens a searchable list of the commands the
field offers. It is the toolbar reached by typing instead of by aiming — which is worth
having precisely because this package's toolbar has an overflow dropdown: the tools nobody
could find are the ones a search is for.

```php
AdvancedRichEditor::make('content')
    ->slashMenu(false);   // default: config('filament-advanced-rich-editor.slash.enabled')
```

↑ and ↓ move, Enter or Tab picks, Escape closes — for the word being typed rather than for
good, so the next `/` opens it again.

A row carries an icon and a name and nothing else. A keyboard shortcut in a third column
makes every row as wide as its widest entry, and the panel then covers the text being
written — which is the one thing a menu opened mid-sentence must not do. The shortcuts live
in the [help dialog](#help), where they are looked up on purpose.

**The list is derived, not declared.** Every entry is a tool the field actually registered,
carrying that tool's own label, icon and handler — picking one evaluates the same string
the toolbar button runs. A command and its button therefore cannot come apart: there is
only one of them, and the menu is a different way to press it. Switch the task list off and
it leaves the menu with it; offer only `h2` and `h3` and those are the only headings listed;
name a tool in the config that this field does not have and it is dropped, exactly as it is
inside a toolbar dropdown.

Merge tags and custom blocks are a special case: Filament registers both tools whether or
not anything was configured for them, so the menu offers them only once the field has some.
A picker over an empty list is not a command.

**Two groups, split by the question each command answers.** *Style* changes what the block
already there **is** — a heading, a list, a quote. *Insert* is what **arrives** — an image,
a table, a rule. Grouping by node type instead would put the horizontal rule next to the
blockquote, which is a fact about the schema rather than about what anyone is doing.
Uploading is not a group of its own, the way it is in some editors: one entry does not need
a heading over it.

**Only blocks and things you insert.** The menu opens where the caret sits with nothing
selected, and `/bold` there would mark nothing at all — an entry that does nothing is worse
than a missing one. Inline formatting keeps to the toolbar, where there is a selection to
apply it to.

The groups and their contents are configurable, and `'headings'` expands to the levels the
field offers:

```php
// config/filament-advanced-rich-editor.php
'slash' => [
    'enabled' => true,
    'char' => '/',
    'groups' => [
        'style' => ['paragraph', 'headings', 'bulletList', 'orderedList', 'taskList',
                    'blockquote', 'codeBlock'],
        'insert' => ['image', 'attachFiles', 'table', 'horizontalRule', 'details', 'emoji',
                     'customBlocks', 'mergeTags'],
    ],
],
```

A group left with nothing in it does not appear, and a menu left with no groups does not
open — the data attribute carrying it is not even written.

**Searching** matches the label, the tool name and a list of aliases, with a command whose
name *starts* with what was typed ranked above one that merely contains it: `/co` means the
code block far more often than it means "table of columns". The aliases are translated, so
`/liste` finds the bullet list in a German panel and `/ul` finds it in any:

```php
// resources/lang/de/advanced-rich-editor.php
'slash' => ['aliases' => ['bulletList' => 'ul, liste, aufzählung, punkte']],
```

A slash only opens the menu at the start of a block or after a space, so `and/or` stays a
word somebody is writing. Code blocks are left alone entirely — a slash there is nearly
always code.

The panel is drawn on `document.body` rather than inside the editor, because a field with
[`maxHeight()`](#maximum-height) scrolls and a menu clipped by that box would be unusable on
the last line. Style it through `.fi-arte-slash` and the classes under it.

### Links

The `link` dialog asks for the attributes a link in a published document carries, not only
the ones a link in a form does:

| Field | Attribute |
| --- | --- |
| URL | `href` |
| Opens in | `target` — same window, new window, parent frame, top frame |
| Relationship | `rel` — checkboxes for `nofollow`, `noopener`, `noreferrer`, `sponsored`, `ugc`, plus a field for the rest |
| Referrer policy | `referrerpolicy` — the eight the specification defines |
| Language of the linked page | `hreflang` |
| Anchor | `id`, so something can link to this spot in the text |

`rel` is checkboxes and `referrerpolicy` a select rather than the free text fields the
obvious implementation reaches for. Both are closed vocabularies, and a typo in either
produces an attribute that is silently inert — worse than a missing one, because the author
believes it is doing something. `rel` itself is an open list, which is what the field beside
the checkboxes is for: `me`, `alternate`, `license` and anything else valid go there, and
the two halves are merged into one attribute with each value appearing once.

**A link that opens in a new window is given `rel="noopener noreferrer"` whether or not
anyone ticked them.** `target="_blank"` on its own hands the opened page a handle on the
window that opened it, which it can navigate somewhere else while the reader is looking at
the new tab. Nothing further down the stack prevents that, and nobody ticking "new window"
is thinking about it.

```php
AdvancedRichEditor::make('content')
    ->linkAttributes(false);   // default: config('filament-advanced-rich-editor.link.attributes')
```

Turning it off falls back to Filament's own dialog — a URL and a checkbox — and to
Filament's own link mark. Note that this is a heavier switch than hiding a button: the
extra attributes are part of the schema, and content is re-parsed on every hydration and
again on every save, so a field that stops declaring them drops them on the next save in
documents that already carry them.

**Why the mark is replaced rather than extended.** Filament declares `href`, `target`,
`rel` and `class`; the parser keeps only what something declares, so anything else is
dropped. Two extensions of the same name are both applied, and a second link mark
alongside Filament's renders `<a><a>text</a></a>`. This package therefore swaps Filament's
mark out by name — in its own renderer and in its own field, carrying the protocol allow
list across — rather than rebinding `Tiptap\Marks\Link` in the container, which would
change every other package's links too.

Everything the dialog writes survives Filament's sanitiser: `rel`, `target`, `hreflang`,
`referrerpolicy` and `id` all reach the page as written.

### Anchors in the editor

An `id` typed into a heading through the [source code view](#source-code) is kept. TipTap's
heading declares `level` and nothing else, so without this half the anchor is dropped the
moment the document is parsed — and the link pointing at it stops working on the next save,
with nothing to say it happened.

```html
<h2 id="preise">Preise</h2>
```

An id that could not be linked to — one with a space or a quote in it — is dropped rather
than stored. It would survive the sanitiser and still not be a fragment any browser jumps
to. The same rule applies to the anchor on a link.

Anchors this package generates while rendering are a separate thing and need no
configuration in the editor; see [Anchors](#anchors).

### Source code

`'sourceCode'` opens the document as HTML, in Filament's own code editor, next to the
fullscreen button.

```php
AdvancedRichEditor::make('content')->sourceCode(false);   // default: config('...source_code')
```

Both directions go through the field's own TipTap schema rather than being passed along
untouched, and that is the point of it:

- **Opening** shows the markup as it is stored, not as the browser happened to serialise it.
  Every plugin the field carries is in that schema, so a rotation, a background colour or a
  direction is there to see.
- **Applying** hands the markup to the same schema before it reaches the editor. Anything it
  cannot represent is gone at that moment - the same thing that happens to pasted markup,
  but visibly, and before the record is written rather than silently on the next save.

Which also means the source view is not a way to store markup the editor does not support.
Filament sanitises rich content on the way out anyway; this simply makes the boundary
visible.

The markup is laid out for reading on the way in - every block on its own line, indented by
nesting - and compacted again on the way out. That costs nothing, because the parser drops
whitespace between block tags; inline content is never broken, since the whitespace between
inline elements is part of the sentence, and anything inside a `<pre>` is copied through
exactly as written. A single long paragraph still arrives as a single long line, wrapped by
the code editor: that one is the document's own doing, not the layout's.

### Fullscreen

The last button expands the editor over the window, and Escape leaves again.

```php
AdvancedRichEditor::make('content')->fullscreen(false);   // default: config('...fullscreen')
```

It is a fixed overlay, not the browser's Fullscreen API: that promotes one element into
the top layer, and Filament renders its modals at the end of the body, so the file upload
dialog would be invisible while the editor was expanded. The overlay deliberately sits
below Filament's modal layer, so those dialogs still work. While expanded the editor body
is the scroll container, and a sticky toolbar pins to it rather than to the page.

### Help

`'help'` sits at the end of the toolbar and opens a dialog listing the keyboard shortcuts
the field answers to.

```php
AdvancedRichEditor::make('content')->help(false);   // default: config('...help')
```

The list is built from the field's own configuration, not from a fixed table: it names the
heading levels that field offers, leaves the task list out where the task list is off, and
mentions the table keys only where there is a table tool. Every shortcut in it was read out
of the editor build Filament ships - a list that names keys nobody bound is worse than no
list.

Keys are drawn as caps and named by the machine reading them: ⌘⌥⇧ on a Mac, Ctrl/Alt/Shift
everywhere else.

Add a second tab with something to tell the people writing in the field:

```php
AdvancedRichEditor::make('content')
    ->helpMore('Product names are never translated. Ask the editorial team before publishing.');
```

Without a note the dialog stays one plain list - a tab bar over a single tab is furniture.
A plain string is escaped and keeps its line breaks; pass an `Htmlable` for markup, which is
trusted, so build it in code rather than out of anything a user typed. The tab's own label
is the second argument, and `help_more` in the config sets a note for every field at once.

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

### Maximum height

A long document pushes everything below it off the screen. `maxHeight()` caps the field and
lets it scroll inside itself instead:

```php
AdvancedRichEditor::make('content')
    ->maxHeight('400px');              // default: config('filament-advanced-rich-editor.max_height')

AdvancedRichEditor::make('content')->maxHeight(400);    // a bare number is pixels
AdvancedRichEditor::make('content')->maxHeight(null);   // grow freely, against a configured height
```

Any CSS length. A bare number is read as pixels, because `max-height: 400` is not a length
any browser accepts and the field would keep growing with nothing to show for the call.

Only the text box is capped, so **a capped field turns its own sticky toolbar off**: the bar
sits above the box that scrolls and stays in view without being pinned to anything. Pinning
it to the viewport as well would peel it off the field as the page scrolls past. Fullscreen
wins over both — the overlay already fills the window, and a 400px box inside it would
leave most of the screen empty.

### Heading levels

The dropdown lists the plain paragraph in front of the levels, so it reads as a choice of
block rather than a row of toggles, and there is an obvious way back out of a heading.
Picking the level a block already has also returns it to a paragraph, so a block is never
left without a type. Drop the entry with `->headingParagraph(false)` or the
`heading_paragraph` config key.

The six heading tools are relabelled to "Heading 1" … "Heading 6" (Filament labels `h1`
"Title", which reads wrong next to the other levels in a dropdown). Override the wording
through the `tools.heading_level` translation key.

```php
AdvancedRichEditor::make('content')
    ->headingLevels([2, 3, 4]);   // default: config('...heading_levels')
```

The levels drive both the `'headings'` dropdown and which of the `h1`–`h6` buttons are
available. Only 1 to 6 are valid — anything else throws a `LogicException` at build time
rather than silently rendering a broken editor.

### Lists and task lists

```php
AdvancedRichEditor::make('content')
    ->listTypes(['bulletList', 'orderedList', 'taskList'])
    ->taskList();                      // default: config('filament-advanced-rich-editor.task_list')
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

### Colours

Two swatch dropdowns sit next to the other inline marks: `textColor` paints the letters,
`textBackground` paints behind them. Both offer the palette, a way back to no colour, and
a free colour picker.

```php
AdvancedRichEditor::make('content')
    ->textColors(['brand' => TextColor::make('Brand', '#0ea5e9', darkColor: '#38bdf8')])
    ->backgroundColors(['#fef08a' => 'Yellow', '#bbf7d0' => 'Green'])
    ->customColors(false)          // drop the free colour picker
    ->textColor(false)             // drop the text colour dropdown entirely
    ->textBackground(false);       // and the background one
```

The text palette is stored by name, and each entry carries a light and a dark value - that
is what keeps a colour readable in both themes, since the document holds only the name.
The package ships twelve: three neutrals and nine hues, in `colors.text_palette`:

```php
'text_palette' => [
    'ink' => ['label' => 'Ink', 'color' => '#18181b', 'dark' => '#f4f4f5'],
    // …
],
```

Filament's own default is not used, because it lists all 26 Tailwind hues and nine of them
are near-identical greys and browns - fine in the labelled select it was built for, poor as
a grid of swatches. Configuring `->textColors([...])` on the field (or on the model's rich
content attribute) still takes over completely.

A colour chosen through the free picker is stored as given and therefore looks identical in
dark mode - a property of hand-picked colours, not a bug.

The background is this package's own mark. Filament registers TipTap's highlight without
colour support, and its own colourless mark is added to the renderer unconditionally, so
replacing it by name would nest another `<mark>` on every save. A separate mark name
sidesteps that entirely, and Filament's plain `highlight` tool keeps working alongside it.
Colours are run through Filament's CSS colour sanitiser before they reach the `style`
attribute.

### Fonts

The `fontFamily` token is a dropdown of typefaces, and every entry in it is one the project
can actually draw. Nothing is fetched from anywhere - no CDN, no Google Fonts, no network at
all.

Fonts are found rather than declared. Point the config at the directory where the project
keeps its own font files and every file in it is offered:

```php
// config/filament-advanced-rich-editor.php
'fonts' => [
    'directory' => 'fonts',   // relative to public/
],
```

`fonts/Inter/Inter-SemiBoldItalic.woff2` becomes Inter at weight 600, italic; a loose
`fonts/Fraunces-Light.woff` becomes Fraunces at 300. The family comes from the folder name,
or from the file name up to its first separator; the weight and style from the rest of it.
An `@font-face` rule is written for every file found, once per page, so the picker never
offers a typeface the page was not told how to load. Only the directory and one level
inside it are read, which is what keeps a panel's own published fonts out of the list.

Three generic stacks come free, since `system-ui`, `serif` and `monospace` resolve
everywhere without a byte being downloaded. For typefaces the project loads somewhere else -
a theme, a self-hosted kit - name them, and the browser is asked whether they arrived before
they are shown:

```php
'fonts' => [
    'families' => ['Brand Sans' => '"Brand Sans", system-ui, sans-serif'],
    'generic' => false,       // drop the three stacks
],
```

Per field, `->fonts(['Label' => 'CSS, stack'])` replaces the whole list - no directory is
read and no face is written, the field saying it knows what is loaded - and
`->fontPicker(false)` removes the dropdown along with the mark that stores the choice.

The family is written into the `style` attribute, checked against a pattern on both sides
of the round trip. That check is the only thing between the editor and a stylesheet smuggled
in as a family name: Filament's sanitiser passes `style` through untouched.

### Font size

The `fontSize` token shows the size in force and opens a menu of the sizes people actually
pick, with `Default` to take a size back off. The number in the toolbar is the field: click
it and type, for the size that is not on the list.

```php
AdvancedRichEditor::make('content')
    ->fontSize()                                            // default: config('...font_size.enabled')
    ->fontSizeOptions(min: 10, max: 40, step: 2, default: 16);
```

The offered sizes live in `font_size.sizes`. Anything outside `min`/`max` is dropped from
the menu rather than offered and then silently corrected, and a typed size is clamped to the
same bounds.

`Default` is marked while the text carries no size of its own, and a size is marked while it
does - which is not the same question as which number is on screen. Text inheriting 14px and
text set to 14px look alike and behave differently: only the first follows a restyled theme.

Where the text carries no size of its own, the trigger reports the size the browser actually
renders - the theme's, or a heading's while the caret is inside one - rather than a fixed
starting value. `default` is only used when that measurement fails.

`->fontSize(false)` removes the dropdown and the TipTap extensions with it, so nothing
writes or parses a size. The size is written into the `style` attribute, which is what
Filament's sanitiser keeps, so it survives to the rendered page.

### Character count

A quiet line under the editor saying how long the text is. It is on by default and needs no
setup:

```php
AdvancedRichEditor::make('content')->characterCount(false);   // default: config('...character_count.enabled')
```

With a `maxLength()` on the field it counts towards it - `1,234 / 2,000 characters` - and
turns amber at 90% and red past it. For a target with no rule behind it, set the limit
directly:

```php
AdvancedRichEditor::make('content')
    ->characterCountLimit(160)      // shown, never enforced
    ->characterCountWords();        // adds "218 words ·" in front
```

**The number is the one Filament validates.** That is the whole point of it, and it is not
free: `maxLength` on a rich editor is measured server side with
`Str::length($tiptapEditor->getText())`, and that serialiser escapes the text - a single `&`
counts as the five characters of `&amp;` - and separates every nesting level with a blank
line, so a list item costs two of them. A counter reading the text off the screen would show
a smaller number than the one a save is rejected over. This one mirrors those rules, in PHP
for the first render and in the browser for every keystroke after it.

The editor announces its counts as a DOM event and the line listens - nothing is polled, no
view is replaced, and two editors on a page keep their numbers apart. Turning the counter
off also drops the script that does the counting.

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

Clicking an image opens a floating toolbar: the aspect ratio lock, a size panel, rotate
left and right, a divider, an alt text panel, a download and a delete.

**Size** writes the same `width` and `height` attributes a drag commits, so both ways agree.
The aspect ratio lock sits between the two fields, and it is the same switch as the one in
the bar - one state, shown where each is useful: in the bar while dragging, between the
fields while typing. With it closed, editing one field previews the other; nothing is
written until **Apply**, because committing each field on its own would undo the other
before both numbers had been entered. Reset clears both and hands the image back to its
natural size.

While a panel is open it stops following the editor, so a live update cannot overwrite what
is being typed; it reads the current values again each time it opens.

**Rotation** turns in quarter steps. A turned image can still be dragged to a new size; the
handles work on the image's own box, so with the picture on its side a sideways drag changes
what now reads as its height. The size panel is the exact way round that. There is no rotation attribute in TipTap or in
Filament, so the package adds one as a *global attribute* on the image node - the mechanism
`Tiptap\Extensions\TextAlign` uses - on both sides. The angle travels in the inline
`style`, the only carrier Filament's sanitiser keeps, and is whitelisted to quarter turns
before it is written, because nothing else in the stack validates CSS. A turned image also
gets margin compensation: a transform leaves the layout box alone, so without it a quarter
turned picture would overlap the lines around it. The resize handles are hidden while an
image is turned - their axes are swapped at that point, and the size panel still works.

**Alt text** is stored on the image like any other attribute. Clearing the field removes it
rather than storing an empty string: both renderers drop falsy attributes, so an empty alt
cannot survive a save and pretending otherwise would be a lie in the UI.

The bar holds text inputs, which is only possible because the package widens its visibility
rule. Filament shows a floating toolbar while the *editor* has focus, and typing in an input
takes that focus away - the first write after that would hide the bar along with the field
being typed into. The rule is replaced through the bubble menu's own options message, for
the image toolbar only, with the same condition TipTap's own default uses. Delete removes the image from the content; with the media library provider
the file itself is cleaned up on the next save, along with every other attachment the
content no longer references. Download hands the browser the image's own URL - files on
your own domain save directly, a remote one opens in a new tab, which is the browser's
call rather than something a page can override.

```php
AdvancedRichEditor::make('content')->imageToolbar(false);   // default: config('...images.toolbar')
```

The lock only appears where resizing is allowed; the two actions are there either way.

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

Every upload is stamped with the name of the field that made it, and removing an image from
the text only ever deletes that field's own attachment. Two editors on one record can share
a collection — the default one — without cleaning up after each other, and anything else the
project keeps in that collection is never touched:

```php
AdvancedRichEditor::make('content')->spatieMediaLibrary(),
AdvancedRichEditor::make('summary')->spatieMediaLibrary(),   // same collection, separate images
```

## Rendering

Everything above is about the editor. This part is about the page the content ends up on.

`AdvancedRichContentRenderer` is Filament's `RichContentRenderer` with what this package
adds, used exactly the same way — every method you already know is still there:

```php
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;

AdvancedRichContentRenderer::make($article->content)
    ->anchorHeadings()
    ->toHtml();
```

A subclass rather than macros on Filament's own class. A macro is registered once and
applies to every renderer in the application, including the ones other packages build for
their own content — installing this package would change what those render, without anyone
asking for it. Where the additions *should* apply everywhere, including Filament's model
rich content attributes, which build their renderer themselves and take no arguments, one
line says so:

```php
// AppServiceProvider::register()
AdvancedRichContentRenderer::bind();
```

It also renders an empty record as an empty string. Filament's own renderer walks the
document without first checking that there is one, and a rich content column is null until
somebody types into it — so `RichContentRenderer::make(null)->toHtml()` throws where this
one returns `''`.

### Anchors

`anchorHeadings()` gives every heading an `id`, so a link can point at one.

```php
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AnchorPosition;

// ids only, nothing visible on the page
AdvancedRichContentRenderer::make($article->content)->anchorHeadings()->toHtml();

// …and a link to each heading's own anchor
AdvancedRichContentRenderer::make($article->content)
    ->anchorHeadings(position: AnchorPosition::After)
    ->toHtml();
```

The id is a slug of the heading's own text. A heading that repeats is numbered rather than
given the same anchor twice — two sections called *Installation* become `installation` and
`installation-2`, because an anchor that appears twice sends every link to the first one.
A heading that slugs to nothing, because it is an emoji or a piece of punctuation, still
gets one: `section`, `section-2`.

An id already in the stored markup — typed into the [source code view](#source-code), or
carried in from an import — is kept as it is and counted as taken, so nothing generated
afterwards collides with it. Something out there may link to it, and replacing it with a
slug of the current wording would break that link without anyone touching the document.

Four positions decide what is drawn into the heading:

| `position` | What the heading gets |
| --- | --- |
| `None` | The `id`, and nothing else. The default. |
| `Before` | A marker in front of the text. |
| `After` | A marker after the text. |
| `Wrap` | No marker; the heading text itself becomes the link. |

`None` is the default because the usual reason to want anchors is a table of contents
linking into the page, and a symbol appearing next to every heading is a change to a design
nobody asked to change.

**On the marker and screen readers.** `Before` and `After` draw a link whose text is a
symbol, and a screen reader announces it as one — "number sign, link". Filament's sanitiser
strips `aria-label` and `aria-hidden` from stored content, so that cannot be papered over
from here. `Wrap` is the position to reach for where it matters: the link's text is the
heading, so it is announced as the section it leads to.

The four arguments, and their config defaults:

```php
->anchorHeadings(
    levels: [2, 3],                    // default: config('...anchors.levels')
    position: AnchorPosition::After,   // default: config('...anchors.position')
    symbol: '#',                       // default: config('...anchors.symbol')
    class: 'fi-arte-anchor',           // default: config('...anchors.class')
)
```

The class is a hook, not a rule: the marker is drawn on your page, which this package's
stylesheet is not loaded into. Style it in your own theme.

Umlauts are a decision rather than a default. `Über uns` folds to `uber-uns` in plain
ASCII, which is right where nobody spells it out, and to `ueber-uns` under German
transliteration rules, which is what German readers expect to see in a URL. The anchor ends
up in links, so it is set once for the project:

```php
// config/filament-advanced-rich-editor.php
'anchors' => ['language' => 'de'],
```

The `id` attribute survives Filament's sanitiser untouched, so an anchored heading reaches
the page as written. Anchors are read and written on both sides of the round trip, which is
what keeps a hand-written one from being dropped the next time the record is saved.

### Table of contents

The headings of a document, as a list that links into it:

```php
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TableOfContents;

TableOfContents::make($article->content)->asHtml();    // <nav> with nested <ol>s
TableOfContents::make($article->content)->asArray();   // build your own markup
```

`asArray()` gives back the headings nested, each with the `level`, the `text`, the `id` and
its `children`. `asHtml()` writes that as nested ordered lists inside a `<nav>`, with the
heading text escaped.

```php
TableOfContents::make($article->content)
    ->levels([2, 3])                  // default: config('...anchors.levels')
    ->class('fi-arte-toc')
    ->plugins([TaskListPlugin::make()])
    ->asHtml();
```

The ids come from the same pass `anchorHeadings()` uses, so a link in the list and an `id`
on the page cannot drift apart. That is the whole reason both go through one class: two
slug algorithms agree until two headings share a name, and then every duplicate link points
at the wrong section.

A document that jumps from `h2` to `h4` — which is most documents nobody wrote to a style
guide — nests one step, not two. The reader meant *this belongs under that*; a list that
opened two levels for it would be describing the markup rather than the document.

### Markdown

```php
AdvancedRichContentRenderer::make($article->content)->toMarkdown();
```

Conversion is done by [league/html-to-markdown](https://github.com/thephpleague/html-to-markdown),
which is an **optional** dependency — a project that never calls this should not carry it.
Without it the call throws with the command to install it rather than a fatal error:

```bash
composer require league/html-to-markdown
```

Two defaults are set on top of the library's own, and anything passed to `toMarkdown()`
wins over them:

- `header_style: 'atx'` — `## Heading` rather than an underlined one. Both are valid
  Markdown; only one of them still looks deliberate in a diff.
- `strip_tags: true` — markup the converter has no spelling for becomes its text instead of
  raw HTML. Markdown that carries stray HTML is Markdown only in name.

**Task lists keep their boxes.** A task item is rendered as a label, a box and a content
div, so a converter that has never heard of `data-checked` writes plain bullets and throws
away the state — which is the only thing a task list is about. This package teaches the
converter the `- [x]` / `- [ ]` spelling that GitHub, GitLab and every editor that renders
task lists agree on:

```
- [x] Anchors built
- [ ] Slash menu open
```

```php
AdvancedRichContentRenderer::make($article->content)
    ->plugins([TaskListPlugin::make()])              // so task items parse at all
    ->toMarkdown(['header_style' => 'setext']);      // overrules the default
```

## Configuration

```php
AdvancedRichEditor::make('content')
    ->toolbarButtons([...])                        // full toolbar layout
    ->stickyToolbar(true)                          // pin the toolbar while scrolling
    ->stickyToolbarOffset('4rem')                  // distance from the top of the viewport
    ->maxHeight('400px')                           // cap the field and scroll inside it
    ->linkAttributes(true)                         // rel, referrerpolicy, hreflang, anchor
    ->slashMenu(true)                              // type / for a searchable command list
    ->headingLevels([1, 2, 3, 4])                  // levels offered by the headings dropdown
    ->listTypes(['bulletList', 'orderedList', 'taskList'])
    ->taskList(true)                               // checkbox task lists
    ->spatieMediaLibrary('rich-editor');           // opt into media library attachments
```

Every setter also accepts a closure, so anything can depend on the record or the current
user. Project-wide defaults live in `config/filament-advanced-rich-editor.php`.

### Icons

Every icon the package draws goes through one registry, and each entry can be pointed at any
registered Blade Icons set:

```php
// config/filament-advanced-rich-editor.php
'icons' => [
    'image_rotate_left' => 'lucide-rotate-ccw',
    'image_delete' => 'lucide-trash-2',
],
```

A **bare Heroicon name** (`trash`, `arrows-pointing-out`) is handed to Filament as its enum,
which picks the variant matching the size it is drawn at - the filled 20px one in a toolbar.
A **prefixed name** (`heroicon-o-trash`, `fi-o-heading`, `arte-rotate-cw`, `lucide-trash-2`)
is used verbatim.

The defaults spell out `heroicon-o-*`, i.e. outline throughout. Filament's editor already
mixes the two - its heading buttons are outline `fi-o-*` drawings next to a filled `Bold` -
and outline is what this package's own buttons and the bundled Lucide drawings agree on.
Swap an entry for a bare name to get Filament's filled variant instead.

Filament ships `heroicon-*` and its own `fi-o-*`; this package ships `arte-*`; installing
`mallardduck/blade-lucide-icons` adds `lucide-*`, and any other Blade Icons package works
the same way.

Every key is spelled out in the `icons` block of the published config file with the default
it currently draws, so swapping one is an edit in place rather than a name to look up. An
entry left out (or set to `null`) falls back to that default.

Six defaults are Lucide drawings bundled with the package (ISC, see
`resources/svg/LICENSE.md`), redrawn at Heroicons' stroke width so they sit level with the
rest. They are there where Heroicons has nothing that says the same thing: the two image
rotations (`arte-rotate-ccw`, `arte-rotate-cw`), since Heroicons' curved arrows read as undo
and redo, which the toolbar already uses for exactly that; the blockquote
(`arte-message-square-quote`); and the three colour icons - `arte-letter-a` for the text
colour, `arte-highlighter` for the background, `arte-palette` for the free colour inside
both menus.

Filament's own buttons - bold, italic, the headings - are not in that registry. They belong
to Filament and are changed through its icon alias registry:

```php
FilamentIcon::register([
    'forms::components.rich-editor.toolbar.bold' => 'lucide-bold',
]);
```

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

/* Opaque background painted behind the fullscreen overlay. */
.fi-fo-rich-editor.fi-arte-fullscreen {
  --fi-arte-fullscreen-bg: #fff;
}

.fi-arte-toolbar-divider { /* the vertical rule between clusters, --fi-arte-divider-color */ }
.fi-arte-toolbar-flow { /* the aligned half of a toolbar carrying a 'pin' */ }
.fi-arte-toolbar-pinned { /* the half pinned to an edge */ }
.fi-arte-task-list { /* <ul data-type="taskList"> */ }
.fi-arte-task-item { /* a single checkbox item */ }
```

Two classes are written into rendered content rather than into the editor, so this package's
stylesheet — which is loaded into the admin panel — never reaches them. Style them in the
theme of the page the content ends up on, or rename them with the `anchors.class` config key
and `TableOfContents::class()`:

```css
.fi-arte-anchor { /* the link to a heading's own anchor, see Anchors */ }
.fi-arte-toc    { /* the <nav> around a table of contents */ }
```

`--fi-arte-sticky-offset` and `--fi-arte-max-height` are the exceptions: the field writes both
as inline styles, so they cannot be overridden from a stylesheet. Use `->stickyToolbarOffset()`
and `->maxHeight()` — or the `sticky.offset` and `max_height` config keys — instead.

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

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Security reports go to the address in
[SECURITY.md](SECURITY.md) rather than to the issue tracker.

## License

MIT.
