# Changelog

All notable changes to `filament-advanced-rich-editor` will be documented in this file.

## Unreleased

### Fixed

- Rendering a rich content column that is still null returns an empty string instead of
  throwing. Filament's own renderer walks the document without checking that there is one
- The `@font-face` rules are written once per request instead of once per process. Under a
  persistent worker (Octane, Swoole, RoadRunner) a static flag meant every request after the
  first rendered a font picker offering typefaces the page was never told how to load
- Font family names and paths that could end the `@font-face` rule, or the `<style>` element
  around it, are skipped rather than written. Both halves come off the disk, and Filament's
  sanitiser does not look inside CSS
- The temporary copy a media library upload goes through is removed when the upload fails,
  instead of being left in the system temp directory
- Media library attachments are cleaned up per field instead of per collection. A media
  collection hangs off the record, so two rich editors on one model deleted each other's
  images on every save - as did anything else the project kept in that collection. Uploads
  now carry the name of the field that made them, and a save only removes its own

### Changed

- The font directory is read once per process rather than once per toolbar resolution -
  Filament resolves a toolbar several times while rendering one editor. `Fonts::forget()`
  drops the memo
- The CI matrix covers Laravel 13, which `composer.json` already allowed, and code style is
  checked in CI rather than only locally
- Static analysis runs at PHPStan level 6
- The published config file is shorter. Every key still says what it controls, which values
  are valid and how to override it per field; the reasoning behind each one lives in the
  README, which is where it is read
- `task_list` is a boolean rather than an array with a single `enabled` key, so the file
  follows one rule: a feature that is only on or off is a boolean, one that carries options
  is an array with `enabled` in front of them

### Added

- The link dialog asks for `rel`, `referrerpolicy`, `hreflang` and an anchor on top of the
  URL and the target, with `rel` as checkboxes and `referrerpolicy` as a select - both are
  closed vocabularies, and a typo in a free text field produces an attribute that is
  silently inert. A link opening in a new window is given `rel="noopener noreferrer"`
  whether or not anyone ticked them: `target="_blank"` on its own hands the opened page a
  handle on the window that opened it. `->linkAttributes(false)` falls back to Filament's
  dialog and mark
- An `id` typed into a heading through the source code view is kept. TipTap's heading
  declares `level` and nothing else, so the anchor used to be dropped the moment the
  document was parsed - and the link pointing at it stopped working on the next save
- `AdvancedRichContentRenderer`, Filament's renderer with this package's additions. A
  subclass rather than macros on Filament's own class, so installing this package does not
  change what every other package's content renders as; `::bind()` takes it over
  project-wide where that is wanted, including for model rich content attributes
- `anchorHeadings()` gives headings an `id` to link to, optionally with a link drawn into
  the heading (`before`, `after`, or wrapping the text). A repeated heading is numbered
  rather than given the same anchor twice, an id already in the markup is kept, and the
  transliteration language is configurable - `Über uns` is `uber-uns` or `ueber-uns`
  depending on who reads it
- `TableOfContents`, as a nested array or as nested ordered lists. Its links and the
  anchors on the page come from the same pass, so they cannot drift apart, and a document
  that skips a heading level nests one step rather than two
- `toMarkdown()`, with `league/html-to-markdown` as an optional dependency. Task lists keep
  their checkboxes as `- [x]` / `- [ ]` instead of losing the only state they carry
- `maxHeight()` caps a field and lets it scroll inside itself. A capped field turns its own
  sticky toolbar off, since the bar is not in the box that scrolls
- `composer build-assets`, and a test that fails when `resources/dist` drifts from its
  sources or when a `fi-arte-` class is written into markup the stylesheet has no rule for
- `CONTRIBUTING.md` and `SECURITY.md`

## 1.0.0 - 2026-08-18

Initial release.

- `AdvancedRichEditor` field — a drop-in replacement for Filament's `RichEditor`
- Fully configurable toolbar with nested groups, dropdowns and dividers, grouped by what
  a button does
- `headings`, `lists`, `alignment` and `lineHeight` dropdown tokens, plus custom tokens from config
- `more` overflow dropdown at the end of the toolbar for the tools that do not earn a button
  of their own (`subscript`, `superscript`, `code`, `clearFormatting`, `horizontalRule`,
  `details` by default), configurable through `more` / `->moreTools()`
- Emoji picker (`emoji` tool): the full Unicode list, grouped and searchable, opening under
  the line being written and staying open across picks (draggable, with a close button),
  inserted as plain characters and so free of any server side extension; `emoji` /
  `->emoji()`
- Left-to-right and right-to-left blocks (`ltr` and `rtl` tools) writing a `dir` attribute
  on paragraphs, headings, quotes, list items and code blocks, mirrored on the PHP side.
  Registered but deliberately not in the default toolbar - `dir` only shows itself in a
  document that mixes scripts; `text_direction` / `->textDirection()`
- Line spacing (`lineHeight` token) writing a unitless `line-height` on paragraphs, headings,
  quotes and list items, mirrored on the PHP side and whitelisted to a bare number so a
  value cannot carry further CSS in behind a semicolon; picking the active spacing takes it
  back off. `line_height` / `->lineHeights()` / `->lineHeight()`
- `pin` toolbar marker: everything after it is pinned to the far end of the bar instead of
  travelling with the aligned groups, so the source view, fullscreen switch and help dialog
  keep a corner to themselves. A centred toolbar stays centred on the whole bar; the pinned
  half moves to the start only when the bar itself is aligned to the end
- The alignment dropdown's trigger follows the caret's current alignment
- Configurable heading levels (h1–h6), with the paragraph listed alongside them
- Sticky toolbar with a configurable offset and matching top corner radius
- Fullscreen button that expands the editor over the window, Escape to leave
- Toolbar alignment (`center` by default) via `->toolbarAlignment()` / `toolbar_alignment`
- Task lists (checkbox lists) as an opt-out TipTap plugin, with the checkbox centred
  on the first line whatever font size the text carries
- `image` toolbar tool with alt text support, and the stock `table` button next to it
- Font picker (`fontFamily` token) offering the typefaces a project actually has: font files
  are discovered in a configured directory and given `@font-face` rules, generic stacks come
  free, and declared families are checked in the browser before they are shown; nothing is
  loaded from a CDN. `fonts` / `->fonts()` / `->fontPicker()`
- Font size menu (`fontSize` token) with the usual sizes, a field for anything else and a
  way back to the theme's own size, on an inline font size mark on both sides
- Text colour and text background swatch dropdowns (`textColor`, `textBackground` tokens),
  with a twelve-colour default palette, a clear option and a free colour picker
- Optional Spatie Media Library storage for image attachments
- Resizable images on by default, configurable through `images.resizable`, with a live
  pixel readout while dragging, an aspect ratio lock in the image floating toolbar, and a
  placeholder for images that fail to load
- Download and delete buttons in the image floating toolbar
- Alt text and width/height panels, and quarter-turn rotation, in the image floating toolbar
- The size panel applies on demand and carries the aspect ratio lock between its fields
- Help dialog (`help` tool) listing the keyboard shortcuts the field answers to, built from
  that field's own configuration, with an optional second tab for a project's own note;
  `help` / `help_more` / `->help()` / `->helpMore()`
- Source code view (`sourceCode` tool) opening the document as HTML in Filament's code
  editor, laid out block by block for reading, and normalised through the field's own schema
  in both directions so the markup shown is the markup stored; `source_code` /
  `->sourceCode()`
- Character count under the editor, measured the way Filament's own `maxLength` validation
  measures it, counting towards `maxLength()` or a display-only `->characterCountLimit()`,
  with words on request; `character_count` / `->characterCount()`
- English and German translations
- Every package icon configurable through one `icons` registry, spelled out key by key in
  the published config file, with bundled Lucide icons
  where Heroicons has no equivalent (rotations, blockquote, and the letter, highlighter and
  palette the colour tools use)
- Heading tools relabelled to "Heading 1" … "Heading 6" via `tools.heading_level`
- Task list markup that survives Filament's HTML sanitiser (state carried by a class
  instead of `<input>` / `data-checked`)
- `saveFileAttachmentsToRecord()` override so uploads on a create page work for models
  that do not implement `HasRichContent`
