# Changelog

All notable changes to `filament-advanced-rich-editor` will be documented in this file.

## 1.0.0 - 2026-08-18

Initial release.

- `AdvancedRichEditor` field — a drop-in replacement for Filament's `RichEditor`
- Fully configurable toolbar with nested groups, dropdowns and dividers
- `headings`, `lists` and `alignment` dropdown tokens, plus custom tokens from config
- The alignment dropdown's trigger follows the caret's current alignment
- Configurable heading levels (h1–h6)
- Sticky toolbar with a configurable offset and matching top corner radius
- Toolbar alignment (`center` by default) via `->toolbarAlignment()` / `toolbar_alignment`
- Task lists (checkbox lists) as an opt-out TipTap plugin, with the checkbox centred
  on the first line whatever font size the text carries
- `image` toolbar tool with alt text support, and the stock `table` button next to it
- Font size stepper (`fontSize` token) with an inline font size mark on both sides
- Optional Spatie Media Library storage for image attachments
- Resizable images on by default, configurable through `images.resizable`, with a live
  pixel readout while dragging, an aspect ratio lock in the image floating toolbar, and a
  placeholder for images that fail to load
- English and German translations
- Heading tools relabelled to "Heading 1" … "Heading 6" via `tools.heading_level`
- Task list markup that survives Filament's HTML sanitiser (state carried by a class
  instead of `<input>` / `data-checked`)
- `saveFileAttachmentsToRecord()` override so uploads on a create page work for models
  that do not implement `HasRichContent`
