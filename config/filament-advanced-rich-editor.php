<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Toolbar
    |--------------------------------------------------------------------------
    | The default toolbar layout for every AdvancedRichEditor field. A nested array
    | is one visually grouped button cluster, exactly like Filament's own
    | `toolbarButtons()`. On top of the stock button names you may use tokens —
    | plain strings that are expanded into components when the field renders:
    |
    |   'divider'  — a vertical rule between two clusters
    |   'headings' — a dropdown listing the configured `heading_levels`
    |   'lists'    — a dropdown listing the configured `lists`
    |
    | Tokens may appear at any nesting depth, and objects (a ToolbarDropdown or a
    | RichEditorTool instance) may be mixed in freely. Override this per field with
    | `->toolbarButtons([...])`.
    */
    'toolbar' => [
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Toolbar alignment
    |--------------------------------------------------------------------------
    | Where the button groups sit on the bar: 'start', 'center', 'end' or
    | 'between' (groups spread across the full width). Filament's own editor is
    | left aligned; this package centres the toolbar because the default layout is
    | built around a symmetrical set of groups. Override per field with
    | `->toolbarAlignment()`.
    */
    'toolbar_alignment' => 'center',

    /*
    |--------------------------------------------------------------------------
    | Custom toolbar tokens
    |--------------------------------------------------------------------------
    | Extra tokens usable in the `toolbar` array above, merged over the built-in
    | ones (so a key defined here wins and can replace 'headings' or 'lists').
    | Each value is a closure receiving the AdvancedRichEditor instance and
    | returning the component to render — a ToolbarDropdown, a RichEditorTool, a
    | ToolbarDivider, or a plain button name string:
    |
    |   'inline' => fn (AdvancedRichEditor $editor) => ToolbarDropdown::make('Inline', [
    |       'bold', 'italic', 'strike',
    |   ])->icon(Heroicon::Sparkles)->textualButtons(),
    |
    | Config files must stay serialisable for `config:cache`, so closures only work
    | in an unpublished/uncached config. Register tokens from a service provider
    | instead when you cache your config.
    */
    'tokens' => [],

    /*
    |--------------------------------------------------------------------------
    | Heading levels
    |--------------------------------------------------------------------------
    | Which heading levels the 'headings' dropdown offers, in the listed order.
    | Only 1 to 6 are valid — anything else throws. Note that Filament's stock
    | editor exposes h2 and h3 only; listing h1 here also enables the `h1` button.
    */
    'heading_levels' => [1, 2, 3, 4],

    /*
    |--------------------------------------------------------------------------
    | Lists
    |--------------------------------------------------------------------------
    | Which list types the 'lists' dropdown offers, in the listed order. Valid
    | entries are 'bulletList', 'orderedList' and 'taskList'. The 'taskList' entry
    | is silently dropped on fields where the task list is disabled.
    */
    'lists' => ['bulletList', 'orderedList', 'taskList'],

    /*
    |--------------------------------------------------------------------------
    | Alignments
    |--------------------------------------------------------------------------
    | Which alignments the 'alignment' dropdown offers, in the listed order. Valid
    | entries are 'alignStart', 'alignCenter', 'alignEnd' and 'alignJustify'. The
    | dropdown's trigger shows the alignment the caret is currently in, falling back
    | to the first entry.
    */
    'alignments' => ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'],

    /*
    |--------------------------------------------------------------------------
    | Sticky toolbar
    |--------------------------------------------------------------------------
    | Keep the toolbar pinned to the top of the viewport while a long document is
    | scrolled. `offset` is any CSS length and should match the height of whatever
    | sits above the form — in a standard Filament panel that is the topbar.
    */
    'sticky' => [
        'enabled' => true,
        'offset' => '4rem',
    ],

    /*
    |--------------------------------------------------------------------------
    | Task list
    |--------------------------------------------------------------------------
    | Enables the checkbox task list: the TipTap extensions, the 'taskList'
    | toolbar button, and the rendering of `<ul data-type="task-list">` in saved
    | content. Turn it off to keep the editor's JSON free of task list nodes.
    */
    'task_list' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Font size
    |--------------------------------------------------------------------------
    | The stepper the 'fontSize' token renders: minus, an editable number, plus. It
    | applies an inline font size to the selection, in pixels.
    |
    | Text without an explicit size is measured off the page, so the stepper shows what
    | is actually rendered - the theme's paragraph size, or a heading's while the caret
    | sits in one - and the first step goes in the direction the user expects. `default`
    | is only the last resort for when that measurement is not possible.
    */
    'font_size' => [
        'enabled' => true,
        'min' => 8,
        'max' => 96,
        'step' => 1,
        'default' => 16,
    ],

    /*
    |--------------------------------------------------------------------------
    | Images
    |--------------------------------------------------------------------------
    | `resizable` lets an image be dragged to a new size inside the editor, which
    | writes a width onto the image node and is kept in the saved markup. Filament
    | ships this switched off; this package turns it on because the image tool is
    | part of the default toolbar. Override per field with `->resizableImages()`.
    */
    'images' => [
        'resizable' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Spatie Media Library attachments
    |--------------------------------------------------------------------------
    | Defaults used when a field opts into media library storage with
    | `->spatieMediaLibrary()`. `conversion` is the conversion whose URL gets
    | embedded in the content (null = the original file), `disk` falls back to the
    | collection's own disk, and `visibility` is passed through to the filesystem.
    | This is opt-in per field — nothing here has an effect on its own.
    */
    'spatie' => [
        'collection' => 'rich-editor',
        'conversion' => null,
        'disk' => null,
        'visibility' => 'public',
    ],
];
