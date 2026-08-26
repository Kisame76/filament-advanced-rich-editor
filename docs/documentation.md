# Filament Advanced Rich Editor — documentation

Everything the package does, in one file, installation included. The short version lives in
the [README](../README.md).

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
    ['headings', 'styles', 'fontSize'],
    'divider',
    ['bold', 'italic', 'underline', 'link', 'textColor', 'textBackground'],
    'divider',
    ['alignment', 'lineHeight'],
    'divider',
    ['lists', 'image', 'embed', 'table', 'blockquote'],
    'divider',
    ['more'],
    'pin',
    ['tools', 'fullscreen'],
]
```

Each nested array is one visually grouped cluster, and the groups answer one question each:
what came before, what the text *is* (its block type, its size), how the characters look,
how the block is laid out, what to put into the document, how to view it. Left to right that
is also the order the decisions are made in — you pick a heading before you pick a size, and
you emphasise a word before you decide how the paragraph sits.

**Two things are registered but deliberately not on this bar.** The typeface picker
(`fontFamily`) is one: choosing a font in an article is the front end's business, not the
author's, and this package strips `font-family` out of a paste on exactly that reasoning —
a bar that offers the picker invites somebody to do by hand what the paste cleanup exists to
undo. Where a project genuinely needs it, name the token. The `styles` dropdown is the
sanctioned way to reach a theme's typography, because it carries the theme's own names.

Striking out is the other. It stays in the bubble toolbar over a selection, which is the
only time anybody wants it, and it is in the `more` menu for the times they want it without
one — the top bar spends no button on it.

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
All four keyboard shortcuts work - `Ctrl+Shift+L`, `E`, `R` and `J`. Two of them only do
because this package rebinds them: TipTap binds `L` and `R` to the alignments `left` and
`right`, which Filament's editor is not configured with, so both keys did nothing and
`Ctrl+Shift+R` reached the browser as a hard reload. The repair is registered on every
field, whatever the field's own `alignments()` is, because the keys are bound by Filament's
build either way. See [Help](#help).

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

### The tools menu

`'tools'` is a second overflow for the other half of a toolbar: what a field *does* rather
than what it writes - searching, the accessibility check, the source view, the shortcut
list.

It is the shipped corner: `['tools', 'fullscreen']`, with the menu holding
`['find', 'accessibility', 'sourceCode', 'help']`.

```php
// A project that would rather have the buttons names them individually.
->toolbarButtons([
    // ...
    'pin',
    ['find', 'accessibility', 'sourceCode', 'fullscreen', 'help'],
]);
```

It is named rather than being a second set of three dots, and that is the whole reason it
can exist. Two menus both called "More" on one bar are two doors with the same sign and
different rooms behind them; a menu called Tools is a different kind of thing, and a reader
has to be told which once rather than guess every time.

Shipped that way the corner never changes shape. The accessibility check and the source
view are both off by default and drop out of the menu while they are; switching either on
puts it *in* the menu rather than adding a third and fourth icon beside it, and the preview,
statistics, focus mode and export tools still to come go the same way.

The cost is that finding is one click deeper on a field that has switched nothing on -
`Ctrl+F` is unaffected, and the help dialog lists it.

An empty menu is dropped rather than drawn, and emptiness counts what survived rather than
what was asked for: every tool in the list belongs to a feature that can be switched off, so
all four can be gone while the list naming them is as long as it ever was.

### The more menu

The `'more'` token renders the overflow dropdown at the end of the toolbar: the tools that
earn a place in the editor but not a button of their own.

```php
AdvancedRichEditor::make('content')
    ->moreTools([
        'subscript', 'superscript', 'code', 'codeBlock', 'clearFormatting', 'horizontalRule', 'details',
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

### Code blocks

A code block carries the language it is written in, picked from a select in its own corner:

```php
AdvancedRichEditor::make('content')
    ->codeBlockLanguages([                 // default: config('...code_block.languages')
        'php' => 'PHP',
        'blade' => 'Blade',
    ]);
```

Twelve languages are offered out of the box. An empty list takes the picker away — one
switch rather than two, since a project that curated the languages down to nothing has
already said it does not want one. A language a block already carries is offered even when
it is not on the list: it is still what that block is written in.

The picker sits on the block rather than in the toolbar, because the language is a property
of that block and a document may hold several in different ones. What it writes is the
`language-…` class TipTap already stores, so nothing new goes into the schema.

#### Colours

Colouring happens in PHP, when the page is rendered:

```php
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;

AdvancedRichContentRenderer::make($article->content)
    ->highlightCode()                              // default: config('...code_block.theme')
    ->toHtml();
```

**The editor shows plain monospace, and that is the intended state.** A highlighter worth
having in a browser is measured in megabytes and needs a build step this package does not
have — and it would colour text that only its author ever sees. The reader is where colour
matters, and there the work happens once, on render.

[Phiki](https://phiki.dev) does it and is an **optional** dependency: it carries every
TextMate grammar and every theme, which is nine megabytes nobody who does not colour code
should be made to carry. Without it the call throws with the command to install it:

```bash
composer require phiki/phiki
```

A block that declares no language is left alone — guessing the language is guessing — and
so is a language nothing knows about. The code itself is never touched; only spans are
added around it.

#### Dark mode

One theme by default, which needs no stylesheet of yours. Passing a pair writes both into
the same markup — the light one as ordinary colours, the dark one as custom properties —
so the page is not rendered twice:

```php
AdvancedRichContentRenderer::make($article->content)
    ->highlightCode(themes: ['light' => 'github-light', 'dark' => 'github-dark'])
    ->toHtml();
```

That needs three lines in your own stylesheet to swap them, because the switch is your
project's idea of dark mode rather than this package's:

```css
.dark .phiki-themes,
.dark .phiki-themes span {
    color: var(--phiki-dark-color) !important;
    background-color: var(--phiki-dark-background-color) !important;
}
```

Any [Phiki theme name](https://phiki.dev) works, and the defaults live in
`code_block.theme` and `code_block.themes`.

### Video embeds

The `embed` tool takes a link and puts a video in the document. Paste the link from the
address bar or the share button — every shape either of them produces is understood:

```
https://www.youtube.com/watch?v=ID          https://vimeo.com/ID
https://youtu.be/ID?t=90                    https://player.vimeo.com/video/ID
https://www.youtube.com/shorts/ID           https://www.youtube.com/embed/ID?start=45
```

The timestamp in a link shared "from 1:30" is kept, in all three spellings (`t=90`,
`t=1m30s`, `start=45`). What is stored is what the video **is** — a provider, an id, a
timestamp — and the embed URL is built from that on the way out. Storing the URL instead
would mean trusting whatever the document held: a watch link, which no browser will frame,
or a host this package has no business pointing an iframe at.

**Pasting a video link on an empty line turns it into a video.** The same link pasted into
the middle of a sentence stays a link, because there it is a link somebody is writing.

```php
AdvancedRichEditor::make('content')
    ->embeds(false);   // default: config('filament-advanced-rich-editor.embed.enabled')
```

Turning it off removes the button and the script. Videos already in stored content are
still rendered — a field that stops offering something has no business deleting what was
written while it did.

**The editor does not play the video.** It draws a card naming what will be there. Ten
embeds in one document would otherwise be ten players loading from YouTube, each with its
own requests and its own cookie, in a screen where nobody is watching anything — and an
iframe swallows every click, so the block could not be selected, dragged or deleted like
the rest of the document.

#### The sanitiser

Filament renders rich content through `Str::sanitizeHtml()`, and Symfony's safe element
list has no `<iframe>` in it. That is the right default and it also removes every embed. So
this package allows the element back — and only ever together with a restriction:

```php
// config/filament-advanced-rich-editor.php
'embed' => [
    'sanitizer' => true,                       // allow <iframe> back at all
    'allowed_hosts' => [
        'youtube-nocookie.com',
        'youtube.com',
        'vimeo.com',
    ],
],
```

An iframe whose host is not on the list loses its `src` on the way to the page. The node
goes further and refuses to build the element at all, so what reaches the sanitiser is
already an embed rather than an iframe — the sanitiser is the second line, for markup that
arrived some other way. A database is not written only by this editor.

Subdomains of a listed host count (`player.vimeo.com` for `vimeo.com`); a host that merely
*ends* in one does not — `youtube.com.attacker.test` is a domain anybody can register.

`'sanitizer' => false` leaves the application's sanitiser alone. Embeds are then still
stored and edited, and stripped from every rendered page — which is a coherent choice only
if something else in the project renders them.

> **Living with another package.** Symfony chains every sanitiser registered for the same
> element and attribute, and each one may only narrow what the last returned. Two packages
> that both allowlist `iframe src` therefore end up with the intersection of their lists,
> and each other's embeds vanish. If something else in your project embeds iframes, add its
> hosts to `allowed_hosts` rather than expecting both to work side by side.

#### Privacy

YouTube is embedded through `youtube-nocookie.com` by default: embedding a video should not
decide on its own to put a tracking cookie on the reader's machine. The frame is also given
`referrerpolicy="strict-origin-when-cross-origin"` and `loading="lazy"`, and its `allow`
list is short — fullscreen and picture-in-picture, no camera and no microphone.

```php
'embed' => ['youtube_nocookie' => false],   // for a project with a reason to
```

#### Shape and titles

The wrapper carries the aspect ratio in its own `style`, and the frame fills it — so a
video keeps its shape at any width. 16:9, 4:3, 1:1, 21:9 and 9:16 are offered in the dialog.

A title is optional and worth setting: a screen reader announces it instead of "video".

**The rendered embed needs no stylesheet of yours.** The aspect ratio and the frame's size
are written into the markup as inline styles, because this package's stylesheet is loaded
into the admin panel and the page the content ends up on is somebody else's - an embed
arriving there with only a class on it is a 300×150 box in the corner. `.fi-arte-embed` is
still on the wrapper for styling beyond that.

### Slash menu

Typing `/` on an empty line, or after a space, opens a searchable list of the commands the
field offers. It is the toolbar reached by typing instead of by aiming — which is worth
having precisely because this package's toolbar has an overflow dropdown: the tools nobody
could find are the ones a search is for.

```php
AdvancedRichEditor::make('content')
    ->slashMenu(false)                   // default: config('...slash.enabled')
    ->slashChar(';')                     // default: config('...slash.char')
    ->slashGroups([                      // default: config('...slash.groups')
        'style' => ['paragraph', 'headings'],
        'insert' => ['image'],
    ]);
```

Everything below can be set project-wide in the config file and overruled on the field that
wants something else — a short summary field may offer three commands where the article body
offers sixteen. Every setter takes a closure too, so a menu can depend on the record or the
current user.

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

The group keys are also the translation keys their headings are read from, so a new group
needs a `slash.groups.<key>` entry in your language files. `'headings'` expands to the levels
the field offers:

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
fullscreen button. **Off by default:**

```php
AdvancedRichEditor::make('content')->sourceCode();        // default: config('...source_code')
```

It hands an editor a way past every control the toolbar stands for — whoever types into that
box writes whatever the schema will accept, and the toolbar stops being the answer to what a
document may contain. Worth having where the people using it know HTML, not worth giving to
everybody who installs the package. The token stays in the shipped toolbar and resolves to
nothing while this is off, so turning it on needs no toolbar surgery.

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

### Find and replace

`'find'` opens a bar inside the field, and so does `Ctrl+F` while the caret is in the
editor. Every hit is marked in the text, the one being looked at is marked differently, and
the counter says which of how many.

```php
AdvancedRichEditor::make('content')->find(false);   // default: config('...find')
```

The bar is a small window, one row tall, opening in the top right corner of the field and
draggable by the grip on its left - the same kind of window the emoji picker is. It hangs
off the body rather than sitting inside the field, which is not a matter of taste: Filament
lays the editor's body out as a two-column row from `2xl` up, so a bar living in there
becomes a column and takes half the editor on a wide screen.

It carries the query, a counter, two switches, the way through the hits and the way out.

Two keys open it, and each stands for one of its two states rather than for a change to
whichever it is in:

| Key | What it opens |
|---|---|
| `Ctrl+F` | the window with the replacing row put away |
| `Ctrl+Alt+F`, or `Ctrl+H` on Windows and Linux | the same window with the replacing row out |

Pressed again they repeat themselves, and that is deliberate: the usual reason for pressing
`Ctrl+F` a second time is that something else is selected now, so the second press picks
that up the way the first one did. Only the button in the bar shows and hides the replacing
row, because that is the one place it is a thing to be toggled.

`Ctrl+H` is bound alongside `Ctrl+Alt+F` for the muscle memory Word and Google Docs built,
and it never arrives on a Mac - `Cmd+H` hides the application before the page sees it, which
is the same reason VS Code settled on `Cmd+Alt+F`. The shortcut list in the help dialog
names `Ctrl+Alt+F`, since that is the one of the two that is true on every platform.

Enter steps to the next hit and Shift+Enter to the previous one, Escape closes the window
and puts the cursor back in the text. A click anywhere outside closes it too - except in the
editor, where clicking is how the next place to search from gets chosen.

Two switches narrow the search: `Aa` minds upper and lower case, and `ab` matches whole
words only - and whole words are counted in letters rather than in ASCII, so `Mü` does not
match inside `Müller`. What is typed is text and never a pattern: searching for `a.b` finds
those three characters.

A hit may run through formatting. `he<strong>ll</strong>o` is three text nodes in the
document and one word on the page, so the search runs on the text laid end to end rather
than on the tree - and a hit never runs across a block boundary, an image or a line break,
because a paragraph ending in `hello` followed by one starting with `world` is not the
phrase `hello world`.

Replacing every hit is one step in the undo chain rather than one per hit, and the cursor is
never moved while the bar is open: the hit is scrolled to, not selected, so what is being
typed in the bar keeps the focus.

Nothing about any of this is stored. A search marks no document and a replacement is
ordinary text by the time it is saved, so turning the feature off later leaves everything
written with it exactly as it is - it only takes the button and the keys away.

### Accessibility check

**Shipped off.** Switch it on and `'accessibility'` opens a panel listing what is wrong with
the document, with every row selecting the thing it is about.

```php
AdvancedRichEditor::make('content')
    ->accessibility()                                 // default: config('...accessibility.enabled'), false
    ->accessibilityRules(['missing_alt', 'empty_link']);
```

Off is the default for two reasons, and the second is the real one. It is a review tool
rather than a way of writing, so it belongs on the bar of the fields a project decided it
belongs on. And the contrast rule is measured against a page this package has to be told the
colour of: shipped on, every project whose pages are not white would be handed findings that
are wrong - which is the surest way to teach somebody to stop reading a panel.

The shipped toolbar already keeps a place for it between `'find'` and `'sourceCode'`, and
that place fills itself in as soon as the check is switched on. A project that has published
the config file adds the token to its own `toolbar` array.

Six questions, and they are six because they are the ones a person writing an article can
answer and nobody downstream can. A stylesheet cannot invent alt text and a renderer cannot
decide what a link should have said.

| Rule | What it finds |
|---|---|
| `missing_alt` | an image with no alt text |
| `empty_link` | a link with nothing in it |
| `weak_link_text` | a link whose whole text is "click here" or the like |
| `skipped_heading` | a heading level jumped over, such as `h2` followed by `h4` |
| `table_without_header` | a table whose first row is ordinary cells |
| `weak_contrast` | a set text colour that cannot be read on the page it is going to |

**Heading levels are only checked for jumps, never for where the document starts.** An
article whose page already carries the `<h1>` starts at two, and one rendered inside a card
may reasonably start at three. What no document can defend is two, then four.

**A weak link text has to be the whole text, not part of it.** "Click here for the report"
says what it is and is left alone; "click here" on its own does not. The phrases come from
the translation files, because "click here" is a fact about English rather than about the
web - and `accessibility.weak_link_phrases` adds a project's own to the shipped list rather
than replacing it.

**Contrast is the one rule with an assumption in it, and it is stated rather than hidden.**
The editor cannot know what colour the page will be, because that belongs to the front end.
A colour is measured against `accessibility.background` - white unless a project says
otherwise - or against the text background where one was set on the same words, and it has
to reach `accessibility.threshold`, which is 4.5, WCAG AA for ordinary text. Headings of the
first two levels and text of 24px and up are held to `large_threshold` instead, which is 3,
the same standard's easier level for large text. The finding says the number it got and the
number it needed, because "not enough" is not something anybody can act on.

Only the light half of the palette is checked. A document rendered in both a light and a
dark theme is two questions, and answering one of them twice would be a panel listing
everything twice.

The panel rechecks itself as the document changes, so a finding disappears when it is fixed
rather than when the panel is opened again. Nothing about any of it is stored: a check marks
no document, and a picture that was given alt text is an ordinary picture by the time it is
saved.

### Drafts in the browser

A draft of what is being written is kept in the browser's own storage, offered back the next
time the same field on the same record is opened, and dropped as soon as the document on
screen says the same thing.

```php
AdvancedRichEditor::make('content')
    ->autosave(false)              // default: config('...autosave.enabled')
    ->autosaveWarnOnLeave(false);  // the "leave site?" question only
```

This is not a save. Nothing about it reaches the application, nothing about it is a record,
and a draft that is never restored is never anything. It exists for the moment a submit
comes back as an expired session, as a validation error on a field nobody can see, or as
the 500 that [Raise Livewire's nesting limit](#raise-livewires-nesting-limit) explains how
to cause - where the reply is not the saved record but a page that has forgotten the
article.

Three parts, and they are three because they fail at three different moments:

| When | What happens |
|---|---|
| While typing | the document is written to the browser's storage, once typing stops for `autosave.debounce` |
| On opening | a bar above the document offers a draft that says something the page does not |
| On leaving | the browser's own "leave site?" question, for an editor with changes the server has not been told about |

**The draft is found again by its field, its record and its page.** The key is a hash of the
Livewire component, the model, the record's key and the path to the field within the form,
plus the path of the page in the browser - PHP knows the first four and cannot reliably know
the last, because to Livewire every request looks like the same endpoint. Nothing in the key
is in the clear: it is a key in storage that anything on the origin can read, so what it says
is that two drafts are different rather than what either of them is about.

**Restoring is an ordinary edit.** It is a ProseMirror transaction rather than a call into
TipTap, which is what makes the restored document reach the state the form submits - and it
is one step in the undo chain, so restoring can be taken back with `Ctrl+Z`.

**A draft is dropped as soon as it is stale**: when the document on screen says the same
thing, when the form is submitted, when it is discarded, and when it is older than
`autosave.ttl`. Expired drafts belonging to this package are swept whenever a field opens,
because the tab that wrote one is usually the tab that is never coming back.

> **This is content in a browser's storage.** It sits on whatever machine somebody was
> working on and it outlives the session that wrote it - a day by default, `autosave.ttl`
> otherwise. A field holding something that should not sit there switches this off with
> `->autosave(false)`, and a project that wants none of it anywhere sets
> `autosave.enabled` to false.

Where the browser has no storage to offer - a private window, storage switched off, a
third-party iframe - the field is the field it was before: no draft, no bar, no question on
the way out.

### Drag handle

Hovering a block puts two controls in the margin to its left: a grip to move the block, and
a plus to start a new one under it.

```php
AdvancedRichEditor::make('content')
    ->dragHandle(false)          // default: config('...drag_handle.enabled')
    ->dragHandleInsert(false);   // the plus only: config('...drag_handle.insert')
```

Dragging the grip moves the block. Where it may land is ProseMirror's answer rather than
this package's - it draws the line saying where, it refuses a place a node may not go, and
the move is one step in the undo chain. Clicking the grip selects the block instead, which
is what puts the floating toolbar on it.

The plus starts a new block under the one being hovered and opens the slash menu on top of
it, so what the button offers is everything that could go there rather than a paragraph. It
does that by typing the slash character into the new block, which is the same event as
somebody pressing the key - down to the query it starts with and to backspacing out of it
closing the menu again. On a block that is already an empty paragraph it uses that one
rather than making a second. Where the slash menu is switched off it makes the empty block
and stops, which is the whole of what it can honestly do without a list to offer.

Only the top level of the document gets a handle, so the grip on a list takes the list
rather than the item under the mouse. Grabbing a single item out of a list is the thing
people ask for next and it is deliberately not here: a list item is a node that may only
live inside a list, so dropping one into a paragraph is a question ProseMirror answers with
"nowhere" - the honest version of that feature is a drag that refuses more often than it
works.

**A field with a handle has a wider left margin.** The controls sit in the editor's own
padding, and Filament leaves twenty pixels there where two of them need fifty, so the
stylesheet widens it for fields carrying the handle and for no others. That is the price of
the feature and it is worth knowing before switching it on: an editor without the grip is
laid out exactly as Filament lays it out.

Nothing about any of this is stored. Rearranging a document changes the order of what is in
it and leaves no trace of how, so turning the handle off later changes nothing that was ever
written with it.

### Pasting from Word and Google Docs

A paste is cleaned on the way in. Nothing is stored about it, there is no button for it and
nothing says it happened - what arrives in the document is what the paste was worth.

```php
AdvancedRichEditor::make('content')
    ->pasteCleanup(false)                          // default: config('...paste.cleanup')
    ->pasteKeepStyles(['text-align', 'color']);    // default: config('...paste.keep_styles')
                                                   // shipped: ['text-align', 'aspect-ratio']
```

Word does not put a paragraph on the clipboard. It puts a paragraph, the stylesheet it was
drawn with, a handful of tags no browser has heard of, and a list that is not a list.
Google Docs is tidier and worse: every run of text is a `<span>` carrying eleven
declarations, one of which is the only place its bold lives.

What survives is the document: headings, paragraphs, lists, tables, links, images, video
embeds, bold, italic, underline, struck-out text, superscript and subscript, and the
alignment. What does not is the typography - the fonts, the sizes, the colours, the line
heights and the margins of a document that was written somewhere else.

Two style properties are kept, because both of them are structure wearing a style attribute
rather than typography: `text-align`, and the `aspect-ratio` an embed is drawn at. An
element that *is* its `src` - a frame, a video, an audio player - keeps it for the same
reason: this package's embed reads the video off the frame inside it and drops the node
when it cannot, so an embed whose `src` had been stripped would come back as nothing at
all. Which frames are allowed to stand is not decided here: the schema takes only a frame
inside an embed whose host it recognises, and the sanitiser narrows it again on the way out.

That last part is a deliberate opinion rather than a shortcut. This package parses
`font-family`, `font-size`, `color`, `background-color` and `line-height` into marks of its
own, so a declaration left standing is not cosmetic noise that the next save drops: it is
Calibri 11pt in black, in the document, for good, in a design system that never asked for
it. A project that wants a paste to arrive wearing its colours names the properties in
`pasteKeepStyles()`, and naming one also takes it out of the promotion below - keeping
`font-weight` means wanting the style, not a `<strong>` and a style.

**Word's lists are rebuilt.** A bulleted list in a Word paste is a run of paragraphs, each
carrying `mso-list` in its style and drawing its own bullet as text in a span. Twelve
paragraphs starting with a dot is what every editor without a paste filter shows, and it
cannot be fixed later - by then the list is gone. The run is put back together here, nested
by the level in the style, and whether a level is bulleted or numbered is read off the
marker: a number always brings its `.` or `)` along, which is what keeps Word's second-level
bullet - the letter `o` in Courier - from turning into a lettered list. A list continued
after an interruption keeps counting from where it was.

**The meaning that only lives in a style attribute is turned into tags first.** Bold in
Google Docs is `font-weight:700` and nothing else, so the styles become `<strong>`, `<em>`,
`<u>`, `<s>`, `<sup>` and `<sub>` before anything is dropped. Strip first and the paste
arrives correct in structure and flat in meaning, which is the one failure mode nobody
notices until the article is published. The `<b style="font-weight:normal">` that Google
Docs wraps a whole selection in - a tag meaning bold and a style meaning it is not - goes
the other way and is unwrapped.

**A stylesheet that came along is removed rather than kept.** ProseMirror walks into an
element it has no rule for and keeps the text it finds, so a `<style>` block in a paste
arrives in the document as three hundred words of CSS. So do `<o:p>`, `<xml>`, the VML
shapes and Word's conditional comments, along with the empty paragraphs Word leaves between
everything and the runs of non-breaking spaces it indents with. A single non-breaking space
is a decision somebody made and stays.

**What a browser puts in front of a paste is looked past.** Chrome hands over
`<meta charset='utf-8'>` and then the markup, so every question this asks about what the
paste starts with is asked of the string without it - otherwise a loose run of table rows,
which has to be handed on untouched because an HTML parser throws it away rather than keeps
it, would be flattened into the text of its cells.

**Ids are kept where somebody chose them.** `id` on a heading is an anchor in this package,
so dropping them all would cost a paste from a page it rendered the anchors it was written
with - and keeping them all plants Word's `_Toc496` in the document. The generated ones go
by name: `_Toc`, `_Ref`, `_Hlk`, `_GoBack`, `docs-internal-guid`. `data-*` is never dropped,
which is the other half of the same round trip: a mention, an anchor and a named style are
`data-*` and nothing else, so content copied out of a rendered page comes back as itself.

**A copy from another editor is left exactly as it is.** It carries ProseMirror's own
`data-pm-slice`, it is already the shape the document wants, and a field that quietly took
the colours off content on its way to the field beside it would be worse than one that kept
Word's fonts. The same goes for a fragment that is not a whole element - loose table rows,
which an HTML parser throws away rather than keeps.

The cleaning happens before ProseMirror parses the markup, which is also why a drag and drop
of the same content is cleaned the same way, and why images in a Word paste still reach
Filament's uploader: it hands its rebuilt markup back through the same door.

Turning it off changes the next paste and no document already written with it.

#### Pasting as plain text

`Ctrl+Shift+V` takes the text and none of the markup - no headings, no lists, no links, no
emphasis, whatever was on the clipboard. It is the way out of a paste that arrived wearing
more than it should, and it is bound in the help dialog's shortcut list alongside everything
else the editor answers to.

Nothing in this package binds it, which is why it works whether or not a field cleans its
pastes. A browser reads `Ctrl+Shift+V` (`Cmd+Shift+V`) as paste-and-match-style and hands
over the plain half of the clipboard on its own; where one keeps that key for itself -
Safari does - ProseMirror sees the shift and takes the text half anyway. Line breaks in the
text become paragraphs, the way they do in every editor built on ProseMirror.

There is no menu entry for it and there is not going to be one. A button cannot paste: it
would have to read the clipboard itself through `navigator.clipboard.readText()`, which
needs a permission Chrome prompts for, Safari confirms with its own dialog and Firefox does
not give a page at all - a button that silently does nothing on one browser in three is
worse than no button.

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
Two of them are repaired rather than reported. TipTap's `TextAlign` binds `Ctrl+Shift+L`
and `Ctrl+Shift+R` to the alignments `left` and `right`, Filament configures the extension
with `start` and `end` so that right-to-left content behaves, and `setTextAlign` answers an
alignment it was not configured with by doing nothing - which also hands the key back to
the browser, where `Ctrl+Shift+R` is a hard reload with the draft still in the field. This
package rebinds those two to `start` and `end`. Centring and justifying are TipTap's own
and always worked, since `center` and `justify` are spelled the same on both lists.


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

### Styles

Named styles from your own design system, offered as a dropdown. This is the thing a
Filament editor can do that a generic one cannot: the classes belong to your front end, so
an editor gets at the design system without anyone opening the source dialog.

Shipped empty — an editor offering styles nobody designed is worse than one offering none.

```php
// config/filament-advanced-rich-editor.php
'styles' => [
    'lead'   => ['label' => 'Lead',   'class' => 'text-lg text-slate-600', 'scope' => 'block'],
    'kicker' => ['label' => 'Kicker', 'class' => 'uppercase tracking-wide', 'scope' => 'inline'],
],
```

Then put `'styles'` in the toolbar, or in `more`. Per field:

```php
AdvancedRichEditor::make('content')
    ->styles(['note' => ['label' => 'Note', 'class' => 'rounded bg-amber-50 p-4']]);

AdvancedRichEditor::make('comment')->styles([]);   // no styles on this field
```

**Two scopes, because there are two mechanisms.** A `block` style is an attribute on the
paragraph or heading the caret sits in; an `inline` style is a mark on the selected text.
`scope` defaults to `block`, which is the common case by a distance. A block entry may name
`'types' => ['heading']` to restrict where it applies; the default is every block that can
also carry a text direction. A style that cannot apply where the caret is stays in the menu,
dimmed — a list that changes length as the caret moves is a list nobody can aim at.

**One style at a time, per scope.** Picking a second replaces the first, the way a heading
level does. A style that wants two of your classes together is one entry holding both.

#### What gets stored

```html
<p data-style="lead" class="text-lg text-slate-600">…</p>
```

Both, and both are needed. The classes are what the page uses; the key is what the next
parse reads. Editing a style's class list in the config therefore updates documents that
already exist, instead of leaving them on the old classes until a save quietly dropped them.
`data-style` is not on Filament's sanitiser allow list, so it reaches the database and not
the reader — which is exactly the split that is wanted. Content that arrives carrying only
the classes, pasted out of a rendered page, is recognised by them.

#### Seeing a style in the editor

**The editor cannot show you what a style looks like, and neither can this package.** A
style is a set of *your* classes, and an admin panel has never loaded your front end's
stylesheet — `text-lg` and `bg-amber-50` mean nothing in there. So the editor's markup
carries the key rather than the classes:

```html
<p data-style="lead">…</p>
```

The dropdown names the style and shows a checkmark, and that is the whole of the feedback
you get out of the box. Two ways to get more.

**Style the keys in your panel theme.** This is the real answer: precise, scoped to the
editor, and it makes the editor look like the page. Six lines, once:

```css
/* resources/css/filament/admin/theme.css */
.fi-fo-rich-editor-content [data-style="lead"]   { font-size: 1.125rem; color: #475569; }
.fi-fo-rich-editor-content [data-style="kicker"] { text-transform: uppercase; letter-spacing: .05em; }
.fi-fo-rich-editor-content [data-style="note"]   { border-radius: .5rem; background: #fffbeb; padding: 1rem; }
```

Write one rule per style you configured, using the same declarations your front end's
classes produce. Nothing else is needed — the attribute is already in the markup.

**Or switch on the neutral marking** while you have not written those rules yet:

```php
AdvancedRichEditor::make('content')->stylePreview();   // default: config('...style_preview')
```

Styled text then gets a rule down the side of a block and a dotted line under a run of text.
It says that a style is set, never what it looks like — a stopgap, and your own
`[data-style]` rules override it, so leaving it on while you write them costs nothing.

Why the package does not simply do this for you: inventing an appearance for content it
knows nothing about is how an editor ends up lying about the page. The list of styles ships
empty for the same reason.

#### Class names

Anything a class can be, including Tailwind's colons, slashes, brackets and leading hyphens.
An entry with no label, no classes, an unknown scope, or characters that could not appear in
a class attribute at all is left out of the list — visibly absent rather than quietly
rendered as nonsense. The value is escaped on its way into the attribute either way.

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

### Required, and what counts as empty

An empty editor is not an empty value. TipTap keeps at least one paragraph in the document
at all times, so a field nobody typed into reaches the validator as
`['type' => 'doc', 'content' => [['type' => 'paragraph']]]` — an array Laravel's `required`
is perfectly happy with. Filament rejects that exact shape; press return once and the
document has two empty paragraphs, which it does not.

This package answers the question once, for every shape state arrives in:

```php
AdvancedRichEditor::make('content')->required();
```

A document counts as **empty** when it holds nothing but paragraphs, line breaks and
whitespace — ordinary spaces, and the non-breaking ones a paste from Word leaves behind.
Everything else is content: a list, a heading, a horizontal rule, an image, a table, a
custom block, and any node a project or another package adds.

That direction is deliberate. A list of nodes that count as content would need extending
every time a node is added, and the day one was forgotten it would throw away somebody's
work. Stated this way an unknown node counts as content, so the worst this can do is let an
empty-looking document through — visible, and reported — rather than reject a document that
had something in it.

The same question is available on the field, which is what a custom rule or an observer
wants:

```php
$field->hasContent($state);   // markup, a document, or null - all three
```

#### Storing nothing instead of `<p></p>`

By default an empty document is stored as `<p></p>`, exactly as Filament stores it. That is
noise in a nullable column, and it is why `@if($post->content)` is true for a post with no
content. To store nothing instead:

```php
AdvancedRichEditor::make('content')->nullWhenEmpty();   // default: config('...null_when_empty')
```

It is off by default, and that is a decision about your database rather than a preference: a
column that is `NOT NULL` without a default takes `<p></p>` and refuses a null, so turning
this on for everyone would break a save that works today. Turn it on per field, or once in
the config file when you know your columns.

Emptiness means the same thing here as it does above, and `json()` fields get the same
answer even though what they store is a document rather than markup.

#### Hydrating an empty column

A `text NOT NULL DEFAULT ''` column is ordinary, and Filament's cast only guards against
null - an empty string went to TipTap's DOM parser, which reached for a `<body>` that was
never built and threw `DOMParser::getDocumentBody(): Return value must be of type
DOMElement, null returned` out of a form that was only being rendered. This package treats a
blank string as no content, so such a record opens on an empty editor like any other.

### Toolbar over a selection

Select text and a small bar appears over it: the project's own styles, bold, italic,
underline, strike, link, colour and the highlighter. Filament ships one of these for a
selected image and one for a table cell; this is the third, and the one people reach for
most.

It offers the same buttons in the same order as the group at the top of the field, which is
why the link sits with the marks in both places rather than between the image and the table.
A link is an annotation on selected text, the same as bold; two bars that offer the same
things in a different order are two things to learn instead of one.

```php
AdvancedRichEditor::make('comment')->textToolbar(false);              // no bar
AdvancedRichEditor::make('content')->textToolbarButtons(['bold', 'link']);
```

It takes the same tokens the main toolbar does, so `'styles'` and `'textColor'` mean the
same thing in both, and a feature switched off on the field takes its button out of here too
rather than leaving a dead one behind. An empty list takes the bar away, and so does
`text_toolbar => false` in the config.

Any dropdown in the bar — the styles picker, the two colour pickers — opens upwards when
there is no room below it. The bar hangs under the text it belongs to, so near the foot of a
document a menu would otherwise be cut off by the editor's own scrolling content box.

The bar is keyed `'paragraph'` rather than `'text'`, which is not a naming choice: Filament's
JavaScript treats that one key as a special case and shows its toolbar on a non-empty
selection inside a paragraph, where every other key waits for a node to be active. A key
called anything else would be drawn and never shown.

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

#### Captions

Click an image and the toolbar's text panel asks for two things: the **alt text**, which
stands in for the picture where it cannot be seen, and the **caption**, which is printed
under it for everyone. They are different jobs and they sit together, because anyone writing
one is thinking about the other.

A captioned image is rendered as the markup a caption means:

```html
<figure class="fi-arte-figure">
    <img src="/hafen.jpg" alt="Kräne im Nebel">
    <figcaption>Hamburger Hafen, 1962</figcaption>
</figure>
```

The paragraph the image was alone in is replaced rather than kept around the figure — a
`<figure>` inside a `<p>` is markup browsers close early and disagree about. **An image
sitting between words is left where it is**, caption or not: a figure is a block that stands
apart from the text, and lifting an inline image out of a sentence would rewrite the
sentence. The caption is dropped from the output there rather than shown somewhere it does
not belong.

Clearing the field removes the caption instead of storing an empty one, the same way the alt
text does.

**What is stored is `data-caption` on the image, not the figure.** A `<figure>` is a
structure, and a TipTap attribute can only add attributes — building one would mean
replacing Filament's image node and taking on its resizing, its uploads and its node view
for the sake of one line of text. The figure is built when the page is rendered, out of that
attribute. Nothing has to be added to your sanitiser config for it: what is stored is never
sanitised, and by the time the sanitiser sees the content the attribute has become a
`<figcaption>` — which, along with `<figure>`, is on Filament's safe list already.

In the editor the caption is drawn under the picture. On the page, the figure carries one
inline style: `margin-inline: 0`, which takes off the 40px browsers indent every `<figure>`
by — a user agent default rather than anyone's design decision, and one that would push a
captioned image out of line with the paragraphs around it.

Everything else is yours, through `.fi-arte-figure`. Two rules are worth having, and most
projects have the second one in a reset already:

```css
.fi-arte-figure figcaption {
    padding-top: 0.375rem;
    font-size: 0.8125rem;
    color: #71717a;
    /* text-align: center; if that is your house style */
}

.fi-arte-figure img {
    max-width: 100%;
    height: auto;
}
```

### Media browser

The image button opens the pictures that are already on the server, with uploading as the
second tab — because uploading is what you do when the picture is *not* there yet. Filament's
own dialog only ever asks for a file, so the same image lands on the disk once per article
that shows it.

Picking an existing picture stores exactly what an upload would have stored: the media UUID
for a field with a media collection, the storage path for one without. **Nothing is copied.**
One file on the disk backs any number of references, and content saved before this dialog
existed is the same content as content saved through it — no migration, no new attribute.

Resizing and rotating stay where they were: they are attributes of the image *node*, never of
the file. The same picture can appear at three sizes in three articles without any of them
disturbing the others.

```php
AdvancedRichEditor::make('content')->mediaLibrary(false);   // default: config('...media_library.enabled')
```

Switching it off restores Filament's own upload dialog exactly. Everything that opens the
dialog — the toolbar button, the slash menu entry, clicking an image that is already in the
text — keeps working either way, because the browser replaces that one action rather than
adding a second one beside it.

#### What it shows

Out of the box the pool is **the collection the field uploads to** — every picture in it,
whichever record or model owns it. The collection *is* the library: a picture put in
`rich-editor` is a picture for rich editors, so an article and a post that both upload there
draw from one pool instead of each fetching the same file again. Separate libraries are
separate collections, which is what collections are for.

Nothing is duplicated: both documents reference the same media row and the same file.

Two narrower settings, for content whose pictures have no business being seen from elsewhere:

```php
->mediaLibraryScope('model')    // only records of the model being edited
->mediaLibraryScope('record')   // only the record in front of you
```

To define the pool outright:

```php
// A media collection: the closure is the pool.
AdvancedRichEditor::make('content')
    ->spatieMediaLibrary('rich-editor')
    ->mediaLibraryQuery(fn (Builder $query) => $query->where('collection_name', 'library'));

// Plain files on a disk: the directory is the pool, and it is browsable with real folders.
AdvancedRichEditor::make('content')
    ->fileAttachmentsDisk('public')
    ->mediaLibraryDirectory('library');
```

`->mediaLibraryPageSize(60)` changes how many pictures one request fetches; the footer counts
the library and the arrows beside it turn the pages.

#### Thumbnails

A tile is about 120 pixels wide, so a grid drawing full-size photographs is a dialog that
takes seconds to open for no visible gain. Point it at a small conversion:

```php
AdvancedRichEditor::make('content')
    ->spatieMediaLibrary('rich-editor')
    ->mediaLibraryThumbnail('arte-thumb');   // default: config('...media_library.thumbnail')
```

The conversion has to be declared on the model — that is the only place the media library
accepts one, so a package cannot add it to your model for you:

```php
public function registerMediaConversions(?Media $media = null): void
{
    $this->addMediaConversion('arte-thumb')->fit(Fit::Contain, 320, 320);
}
```

It is deliberately **separate from the conversion an inserted picture uses**: the tile should
be small, and the image that lands in the document should not be.

Anything not generated yet falls back to the original file — a model that never declared the
conversion, or a fresh upload with a queued job still behind it. Naming a conversion before it
exists costs nothing and starts working the moment it does, and a library of broken thumbnails
is never the answer.

#### Tiles or list

The browser opens on tiles, because picking a picture is done by looking at pictures. The
list is what tiles cannot do — names, sizes and dimensions lined up in columns, which is how
you find one file among four hundred rather than recognise one among twelve. The switch is in
the dialog, and **which one was last used is remembered in the browser**, because that is a
habit rather than a setting. `->mediaLibraryListView()` and `media_library.list_view`
decide what a reader who has no habit yet gets.

Everything the dialog draws is built from Filament's own components — its input, its select,
its dropdown, its buttons — so a panel with its own theme, its own dark mode or its own input
styling gets a dialog that belongs to it.

A search box, a filter for the kinds of picture the pool actually holds, and a sort — newest,
oldest, name, largest, smallest. The filter is **derived from the pool**, so it never offers
WebP to a library that has none.

Selecting a picture fills a details panel beside the grid: preview, name, size, dimensions,
type, modified, and a button that copies its URL.

**Dimensions are read off the picture**, because for a field storing plain files there is
nowhere else they could come from — no row to stamp, no custom properties. The same path is
used with a media collection, so both behave alike:

- everything this package uploads is measured at upload time, where the file is a local copy
  that has already been read and measuring costs nothing;
- everything else is measured from the file — the local path where the disk has one, so only
  the few hundred bytes of header get read, and a ranged read otherwise.

A measurement is **remembered per file**, keyed by its size and modification time, so a
listing pays for a picture once rather than once per listing — and a file replaced under the
same name is measured again. `media_library.cache_store` chooses where. With a media
collection the numbers are also written onto the row once its details have been opened, so
they survive a cache flush.

#### Uploads appear straight away

**The library is the dropzone.** Drag a picture anywhere onto the grid or the list and it
uploads; the **Upload** button in the header opens the same file dialog. There is no separate
upload field to find — a second target would sit exactly where the pictures being compared
want to be.

Both ways in go through Filament's own file upload, which is kept in the dialog but off
screen: it is the whole upload path, with Livewire's protocol, the size and type validation,
the progress events and the pending attachment behind it. Nothing here re-implements any of
that.

An upload is not in the library until the form is saved — Filament holds it as a pending
attachment and only writes it on save. The browser shows it anyway, at the front of the first
page, drawn with a dashed border and labelled as not yet saved. A fresh upload selects itself,
because it is what somebody just went and fetched.

**The dialog can hold two candidates at once**, and what decides which one is inserted is what
is *selected* — not which of the two happens to exist. Choosing a picture from the library
leaves an upload sitting where it is, unselected: it was fetched on purpose, and having it
vanish because something else was clicked would be a surprise.

Nothing is lost by keeping it. Only pictures that end up in the content are turned into
attachments, so an upload nobody inserted stays a temporary file and expires on its own —
there is nothing to delete and nothing to tidy up. Attachments that *were* inserted and are
later taken out of the text are removed on the next save, by the same sweep that has always
done it — unless the browser shares its pool across records, which switches the sweep off for
a reason worth reading: see [Sharing a library safely](#sharing-a-library-safely).

This also works on create pages, where there is no record for a media row to belong to yet.

#### One rule

**What the browser lists is what a stored `data-id` is allowed to resolve to.** The grid and
the lookup are the same object, so they cannot drift into a gap: opening the browser wider and
widening what saved content may point at are one act rather than two.

On a media collection the file attachment provider enforces it — every lookup Filament makes
goes through the provider, and it resolves a UUID against the record's own collection *and*
the pool, and nothing else. On a plain disk there is no provider to enforce anything, so the
browser switches on Filament's own `preventFileAttachmentPathTampering()` and answers it from
the same pool. Two things stay valid regardless, and both have to: a path that is already in
the saved content, so nothing anyone has published breaks, and a file uploaded a moment ago,
which is a pending attachment rather than a path.

A field that calls `preventFileAttachmentPathTampering()` itself overrides this.

#### Sharing a library safely

Reuse is a reference, not a copy, and that has one consequence worth stating before anything
else: **a shared library is not swept automatically.**

The sweep can read the document in front of it. It cannot read every other document in the
application, and it has no way to learn which models and columns even hold rich content. So
the moment the pool is wider than the record — which is the shipped default, because sharing
is what the browser is for — the picture being removed here may equally be sitting in another
record's content, and deleting the file would take that record's picture away too, silently
and permanently. The editor therefore stops deleting: with a shared pool, taking a picture out
of a document leaves the file where it is.

The two mistakes are not comparable. A file kept too long costs disk space, is visible in the
library, and can be removed whenever you decide; a file deleted too early costs somebody else's
content and cannot be undone. Tidy a shared library deliberately — `spatie/laravel-medialibrary`
ships cleanup commands that can see the whole picture, which this sweep cannot.

Automatic clean-up comes back the moment nothing else can be holding the id:

```php
->mediaLibraryScope('record')   // the browser shows only this record; the sweep resumes
->mediaLibrary(false)           // no browser at all; likewise
```

Beyond that, **a picture is deleted by whoever owns it.** The sweep only ever considers
attachments *this field* uploaded to *this record* — media it did not put there is never
touched, so an image picked from a shared pool is not a candidate in the first place. But if
the pool points at another record's collection, deleting that record still takes its files.

That matters because of how the media library stores things. A media row belongs to a model,
and the default path generator builds the file's path out of the **row's id** — so two rows can
never share one file, and `$media->copy()` copies the bytes. A picture uploaded while editing
an article is that article's, and deleting the article takes the file with it.

Give the pictures a model of their own and the coupling is gone:

```php
->mediaLibraryUploadsTo(fn () => MediaLibrary::firstOrCreate(['key' => 'editor']))
```

New uploads then belong to that model rather than to whichever record happened to be open.
Nothing an editor deletes owns them, and the per-field sweep never touches them — it only ever
looks at the record's own collection. Reading and browsing are unchanged: the pool is still the
collection, so the library and the articles drawing from it see each other.

It also removes the wait on create pages. A library exists before the form is opened, so there
is no record to be inserted first and the attachment is written straight away.

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
one returns `''`. The same goes for `toText()`.

### Table column widths

A column somebody dragged wider stays wider on the page. Filament configures TipTap's table
with `resizable: true`, so dragging already worked and the width was already kept in the
document — it just never reached the reader. `ueberdosis/tiptap-php` writes it as
`data-colwidth` on the cell, which is neither on the sanitiser's allow list nor anything a
browser does something with: CSS cannot read an attribute value as a width. The editor
looked right, the page did not, and nothing said so.

The renderer now turns those widths into the `<colgroup>` ProseMirror itself draws while
resizing, and `colgroup`, `col` and `style` all survive the sanitiser:

```html
<table style="table-layout: fixed;">
    <colgroup><col style="width: 220px;" /><col /></colgroup>
    ...
```

Three things are worth knowing:

- **The widths are read off the first row**, which is where ProseMirror reads them too. A
  width sitting only on a later row is not one the editor is showing either.
- **`table-layout: fixed` comes with the widths and only with them.** Without it a column
  width is a suggestion the browser drops as soon as the text is wider. A table nobody
  resized is left exactly as it was — forcing a fixed layout on it would make every column
  equally wide, which is a change to content that was fine.
- **The styles are inline**, like the ones a caption gets, because the page this lands on
  has never loaded this package's stylesheet.

Nothing about what is stored changes, and the editor's own round trip is untouched.

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

### Mentions

Mentions are Filament's own feature — `RichEditor::mentions()`, the `@` menu, more than one
trigger — and a field configured the way Filament documents it works here unchanged:

```php
use Filament\Forms\Components\RichEditor\MentionProvider;

AdvancedRichEditor::make('content')
    ->mentions([
        MentionProvider::make('@')
            ->getSearchResultsUsing(fn (string $search): array => User::query()
                ->where('name', 'like', "%{$search}%")
                ->limit(10)
                ->pluck('name', 'id')
                ->all())
            ->getLabelsUsing(fn (array $ids): array => User::query()
                ->whereKey($ids)
                ->pluck('name', 'id')
                ->all())
            ->url(fn (string $id): string => "/users/{$id}"),
    ]);
```

**Hand the renderer the same providers.** A stored mention is an id and a copy of the name
as it stood when it was typed; the provider is the only thing that knows the name *now*, and
`url()` is never called anywhere else — the editor writes no `href`. A renderer that was not
given the providers renders a stale name nobody can click:

```php
AdvancedRichContentRenderer::make($article->content)
    ->mentions($providers)     // the same array the field was given
    ->toHtml();
```

What this package adds on top:

**A menu with a row worth reading.** Filament draws a suggestion as one line of text: the
name, and nothing else. Five people called Müller are five identical rows. This package's
menu has room for the picture somebody is recognised by and a line under the name — a role,
a team, an email address — which is what tells them apart:

```php
use Kisame76\FilamentAdvancedRichEditor\RichEditor\MentionProvider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\MentionRow;

AdvancedRichEditor::make('content')
    ->mentions([
        MentionProvider::make('@')
            ->getSearchResultsUsing(fn (string $search): array => User::query()
                ->where('name', 'like', "%{$search}%")
                ->limit(10)
                ->get()
                ->map(fn (User $user): MentionRow => MentionRow::make($user->id, $user->name)
                    ->avatar($user->avatar_url)
                    ->hint($user->job_title))
                ->all()),
    ]);
```

`MentionProvider` here is this package's own, extending Filament's: `getLabelsUsing()`,
`url()`, `extraAttributes()` and the messages are its methods, called by its code. A row
without an avatar is drawn with the initials of the name rather than a gap, and a row
without a hint is drawn as one line.

A trigger whose whole list is known up front is handed over with `rows()` instead, and the
browser filters it without a request per keystroke — the name and the second line both:

```php
MentionProvider::make('#')
    ->rows(fn (): array => Team::query()
        ->get()
        ->map(fn (Team $team): MentionRow => MentionRow::make($team->id, $team->name)
            ->avatar($team->logo_url)
            ->hint($team->department))
        ->all())
```

A row may also be a plain array — `['id' => 7, 'label' => 'Ada', 'hint' => 'Mathematician']` —
so a query that already selects the right columns needs no mapping.

The menu is on by default and can be switched off per field with `->mentionMenu(false)` or
for the whole project with the `mentions.menu` config key, in which case Filament's own menu
opens instead. Nothing about what is stored changes either way: the same node, the same
`data-id`, the same markup on the page.

Two things are worth knowing about how this is done. The extension carries Filament's own
name and therefore *replaces* it — that is the only seam Filament offers, because its
suggestion is built into ProseMirror plugins while the editor is constructed. And it is only
loaded on a field that was actually given providers, so a field without mentions keeps
Filament's node untouched.

**A class to style.** Filament renders a mention as `data-type` and `data-id` and nothing
else, which on a page is indistinguishable from any other span. It cannot render more:
Filament's sanitiser allows `class`, `data-id`, `data-type` and `style`, so the `data-char`
saying which trigger was used is stripped before the markup reaches anyone. The trigger
therefore goes into the class instead:

```html
<a data-type="mention" data-id="2" href="/users/2"
   class="fi-arte-mention fi-arte-mention-at">@Ada Lovelace</a>
```

`at`, `hash`, `plus`, `tilde`, `dollar`, `percent`, `amp`, `bang`, `slash` and `question`
are named; any other trigger gets `fi-arte-mention` alone. Nothing is styled for you — the
stylesheet this package ships is the editor's, and the page your content ends up on is
yours.

**Mentions survive `toText()`.** TipTap's PHP text serialiser walks `content` and `text` and
calls `renderText()` on nothing at all, so a mention — an atom node with neither — used to
come out as a hole with a blank line on each side. It reads the way it was typed now, which
is what a search index, an excerpt or the body of a notification is copying:

```
Ping @Ada Lovelace and #Backend.
```

**`Mentions` answers who was mentioned.** The question that follows every mention feature,
and the one neither the editor nor the renderer answers: the observer that has to send the
mail has a column of markup and nothing else.

```php
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Mentions;

$mentions = Mentions::in($article->content);

$mentions->ids('@');    // ['2', '7'] — each id once, in the order they were written
$mentions->ids();       // every id, whichever trigger wrote it
$mentions->grouped();   // ['@' => ['2', '7'], '#' => ['4']]
$mentions->all();       // ['char' => '@', 'id' => '2', 'label' => 'Ada Lovelace'], repeats included
```

It takes no providers on purpose. Resolving a mention needs configuration, and a `saved()`
hook that had to be handed the same providers as the field would be one more place for the
two to drift apart. This reads what the document itself carries; whoever wants the record
now has the id to look it up with.

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
    ->slashGroups(['insert' => ['image']])         // what that menu offers, and in what groups
    ->slashChar('/')                               // the character that opens it
    ->embeds(true)                                 // the video button, and the paste handler
    ->pasteCleanup(true)                           // clean a paste from Word and Google Docs
    ->dragHandle(true)                             // the grip and the plus in the margin
    ->autosave(true)                               // keep a draft in the browser's storage
    ->accessibility()                              // the check, and the panel it reports in (off by default)
    ->codeBlockLanguages(['php' => 'PHP'])         // the language picker on a code block
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
- **Livewire's nesting limit is too low for a rich editor.** Raising it is a step of installing
  this package, not an optional tweak — see
  [Raise Livewire's nesting limit](#raise-livewires-nesting-limit).

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

- ~~**Finding and replacing.**~~ A bar inside the field, opened by the button or by
  `Ctrl+F`, marking every hit and stepping through them. See
  [Find and replace](#find-and-replace).
- ~~**A richer mention menu.**~~ Rows carry a picture and a line of context now, and a row
  without a picture is drawn with the initials of the name. See
  [Mentions](#mentions).
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

Security reports go to the address in [SECURITY.md](https://github.com/Kisame76/filament-advanced-rich-editor/blob/main/SECURITY.md), never to
the issue tracker.
