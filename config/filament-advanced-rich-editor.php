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
    |   'pin'      — the point the bar splits at: everything after it is pinned to the
    |                far end of the bar instead of travelling with the aligned groups
    |                (to the far start instead, when the bar itself is aligned to the end)
    |   'headings' — a dropdown listing the configured `heading_levels`
    |   'lists'    — a dropdown listing the configured `lists`
    |   'more'     — an overflow dropdown listing the configured `more` tools
    |
    | Tokens may appear at any nesting depth, and objects (a ToolbarDropdown or a
    | RichEditorTool instance) may be mixed in freely. Override this per field with
    | `->toolbarButtons([...])`.
    */
    'toolbar' => [
        // Grouped by what a button does: what came before, what the text is set in, how the
        // characters look, how the block is laid out, what to put into the document, how to
        // view it. Reading left to right that is the order the decisions are actually made
        // in - the block type first, then its typeface and size, then the emphasis inside
        // it, then the shape of the paragraph, and only then what gets inserted into it.
        //
        // The last group sits behind 'pin': those buttons are about the editor rather than
        // about the text, so they keep a corner of the bar to themselves instead of moving
        // with everything else. No divider is needed in front of them - the gap is the
        // separation.
        ['undo', 'redo'],
        'divider',
        ['headings', 'fontFamily', 'fontSize'],
        'divider',
        ['bold', 'italic', 'underline', 'strike', 'textColor', 'textBackground'],
        'divider',
        ['alignment', 'lineHeight'],
        'divider',
        ['lists', 'link', 'image', 'table', 'blockquote', 'codeBlock'],
        'pin',
        ['more', 'sourceCode', 'fullscreen', 'help'],

        // Every other Filament tool is registered too and can be named anywhere above, or
        // added to the 'more' list below: 'highlight', 'small', 'lead', 'attachFiles',
        // 'mergeTags', 'customBlocks' and the table editing ones among them.
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
    | Paragraph in the headings dropdown
    |--------------------------------------------------------------------------
    | Lists the plain paragraph in front of the heading levels, so the dropdown
    | covers every block the caret can sit in and offers the way back out of a
    | heading. Picking the active level again also returns to a paragraph, so a
    | block is never left without a type.
    */
    'heading_paragraph' => true,

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
    | More
    |--------------------------------------------------------------------------
    | What the 'more' dropdown offers, in the listed order - the tools that earn a
    | place in the editor but not a button of their own. Any Filament tool name is
    | valid here, and an unknown one is silently dropped. An empty list removes the
    | button along with the dropdown, since a trigger that opens onto nothing is
    | worse than no trigger. Override per field with `->moreTools([...])`.
    */
    'more' => [
        'subscript', 'superscript', 'code', 'clearFormatting', 'horizontalRule', 'details',
        'emoji',
    ],

    /*
    |--------------------------------------------------------------------------
    | Emoji
    |--------------------------------------------------------------------------
    | The emoji picker behind the 'emoji' tool. Emojis are inserted as ordinary
    | Unicode characters, so nothing about them is stored as markup and turning the
    | picker off leaves the ones already written alone. The list itself is bundled
    | and only fetched when the picker is first opened. Override per field with
    | `->emoji(false)`.
    */
    'emoji' => true,

    /*
    |--------------------------------------------------------------------------
    | Text direction
    |--------------------------------------------------------------------------
    | The 'ltr' and 'rtl' tools, which write a `dir` attribute onto the block the
    | caret sits in. Deliberately not in the 'more' list above: `dir` decides which
    | way the text runs, which only shows in a document that mixes scripts - for a
    | document written in one left-to-right language it does nothing that the
    | alignment dropdown does not already do. Add 'ltr' and 'rtl' to a toolbar or to
    | the 'more' list to get the buttons.
    |
    | The extension stays registered so that content which already carries a `dir`
    | keeps it: this half is part of the schema, and content is re-parsed on every
    | hydration, so an editor that stops declaring the attribute drops it on the next
    | save. Set this to false - or `->textDirection(false)` per field - only where
    | that is wanted.
    */
    'text_direction' => true,

    /*
    |--------------------------------------------------------------------------
    | Help
    |--------------------------------------------------------------------------
    | The question mark at the end of the toolbar. It lists the keyboard shortcuts
    | the field answers to - built from the field's own configuration, so it names
    | the heading levels that field offers and nothing it cannot do.
    |
    | `help_more` adds a second tab with whatever the project wants to tell the
    | people writing: a house rule, a reminder, a link to the style guide. Without
    | one the dialog stays a single list. A plain string is escaped and keeps its
    | line breaks; per field, `->helpMore()` takes an `Htmlable` for markup.
    */
    'help' => true,

    'help_more' => null,

    /*
    |--------------------------------------------------------------------------
    | Source code
    |--------------------------------------------------------------------------
    | The button that opens the document as HTML, next to the fullscreen one. Both
    | directions run through the field's own TipTap schema, so what the modal shows
    | is the markup that gets stored and what it hands back has been read by the
    | schema that has to hold it - the same treatment pasted markup gets. Override
    | per field with `->sourceCode(false)`.
    */
    'source_code' => true,

    /*
    |--------------------------------------------------------------------------
    | Fullscreen
    |--------------------------------------------------------------------------
    | The button that expands the editor over the window. It is a fixed overlay
    | rather than the browser's Fullscreen API, so Filament's modals - the file
    | upload among them - still appear above it.
    */
    'fullscreen' => true,

    /*
    |--------------------------------------------------------------------------
    | Colours
    |--------------------------------------------------------------------------
    | Two swatch dropdowns: 'textColor' paints the letters, 'textBackground' paints
    | behind them.
    |
    | The text palette is stored by NAME, and each entry carries a light and a dark
    | value - that is what lets the same text stay readable in both themes, which a
    | hand-picked colour cannot do. Filament's own default is deliberately not used
    | here: it lists all 26 Tailwind hues, nine of which are near-identical greys and
    | browns, which makes for a confusing grid. The set below is one row of neutrals
    | and one of the colours people actually reach for. `->textColors([...])` still
    | overrides it per field.
    |
    | The background palette is keyed by CSS colour with the label as the value, and
    | is kept light on purpose: it sits behind text that has to stay readable.
    |
    | 'custom' adds a free colour picker to both dropdowns. A colour chosen there is
    | stored as given and therefore looks the same in dark mode.
    */
    'colors' => [
        'text' => true,
        'background' => true,
        'custom' => true,

        'text_palette' => [
            'ink' => ['label' => 'Ink', 'color' => '#18181b', 'dark' => '#f4f4f5'],
            'grey' => ['label' => 'Grey', 'color' => '#71717a', 'dark' => '#a1a1aa'],
            'slate' => ['label' => 'Slate', 'color' => '#475569', 'dark' => '#94a3b8'],
            'red' => ['label' => 'Red', 'color' => '#dc2626', 'dark' => '#f87171'],
            'orange' => ['label' => 'Orange', 'color' => '#ea580c', 'dark' => '#fb923c'],
            'amber' => ['label' => 'Amber', 'color' => '#d97706', 'dark' => '#fbbf24'],
            'green' => ['label' => 'Green', 'color' => '#16a34a', 'dark' => '#4ade80'],
            'teal' => ['label' => 'Teal', 'color' => '#0d9488', 'dark' => '#2dd4bf'],
            'blue' => ['label' => 'Blue', 'color' => '#2563eb', 'dark' => '#60a5fa'],
            'indigo' => ['label' => 'Indigo', 'color' => '#4f46e5', 'dark' => '#818cf8'],
            'purple' => ['label' => 'Purple', 'color' => '#9333ea', 'dark' => '#c084fc'],
            'pink' => ['label' => 'Pink', 'color' => '#db2777', 'dark' => '#f472b6'],
        ],

        'background_palette' => [
            '#f4f4f5' => 'Grey',
            '#fee2e2' => 'Red',
            '#ffedd5' => 'Orange',
            '#fef9c3' => 'Yellow',
            '#dcfce7' => 'Green',
            '#ccfbf1' => 'Teal',
            '#dbeafe' => 'Blue',
            '#e0e7ff' => 'Indigo',
            '#f3e8ff' => 'Purple',
            '#fce7f3' => 'Pink',
            '#fde047' => 'Bright yellow',
            '#86efac' => 'Bright green',
        ],
    ],

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
    | Line spacing
    |--------------------------------------------------------------------------
    | The 'lineHeight' dropdown: the spacings it offers, in the listed order. Each
    | one is a unitless `line-height` written into the block's inline style, which
    | is the spelling that scales with whatever font size the block ends up at - a
    | heading keeps its own proportions instead of inheriting a paragraph's leading.
    |
    | Values are bare numbers between 0.5 and 5; anything else is dropped rather
    | than silently corrected, because the sanitiser does not look inside CSS and a
    | value with a unit could carry a second declaration in behind a semicolon.
    | `1` and `2` are labelled "Single" and "Double"; every other value shows its
    | number. Picking the spacing a block already has takes it back off, which is
    | the way back to whatever the theme sets. Override per field with
    | `->lineHeights([...])` or switch the dropdown off with `->lineHeight(false)`.
    */
    'line_height' => [
        'enabled' => true,
        'values' => [1, 1.15, 1.5, 2],
    ],

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
    | Fonts
    |--------------------------------------------------------------------------
    | The typeface dropdown in front of the size stepper. Nothing here is fetched
    | from anywhere: no CDN, no Google Fonts, no network at all.
    |
    | `directory` is where the project keeps its own font files, relative to public/.
    | Every file found is offered and gets an `@font-face` rule written for it, so a
    | typeface is added by putting it there and nothing else. The family comes from
    | the folder name, or from the file name up to its first separator, and the
    | weight and style from the rest of it: `Inter/Inter-SemiBoldItalic.woff2` is
    | Inter at 600, italic.
    |
    | `families` is for typefaces the project already loads somewhere else - a theme,
    | a self-hosted kit. Those are the only entries the browser is asked about before
    | they are shown, since nothing on this side can prove they arrived.
    |
    | `generic` adds the three stacks that resolve everywhere without a file.
    | Override per field with `->fontPicker(false)` or `->fonts([...])`.
    */
    'fonts' => [
        'enabled' => true,
        'directory' => 'fonts',
        'families' => [
            // 'Brand Sans' => '"Brand Sans", system-ui, sans-serif',
        ],
        'generic' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Font size
    |--------------------------------------------------------------------------
    | The 'fontSize' token's menu: the sizes worth offering, plus a field for the one
    | that is not on the list. It applies an inline font size to the selection, in
    | pixels. `min` and `max` bound what a typed size is clamped to, and any entry in
    | `sizes` outside them is dropped rather than silently corrected.
    |
    | Text without an explicit size is measured off the page, so the stepper shows what
    | is actually rendered - the theme's paragraph size, or a heading's while the caret
    | sits in one - and the first step goes in the direction the user expects. `default`
    | is only the last resort for when that measurement is not possible.
    */
    'font_size' => [
        'enabled' => true,
        'sizes' => [8, 9, 10, 11, 12, 14, 16, 18, 24, 30, 36, 48, 60, 72, 96],
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

        // The toolbar that appears over a selected image: the aspect ratio switch (only
        // where resizing is allowed), a download and a delete button.
        'toolbar' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Character count
    |--------------------------------------------------------------------------
    | The line under the editor saying how long the text is. It counts the way
    | Filament's own `maxLength` validation counts, so the number a writer watches is
    | the number a save is rejected over, and it shows a limit as soon as the field
    | has one - `maxLength()`, or `->characterCountLimit()` for a target without a
    | rule behind it. Override per field with `->characterCount(false)`.
    */
    'character_count' => [
        'enabled' => true,
        'words' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Icons
    |--------------------------------------------------------------------------
    | Every icon this package draws, in one place. A bare Heroicon name ('trash',
    | 'photo') is handed to Filament as its enum, which picks the variant matching
    | the size it is drawn at - the filled one in a toolbar. Anything with a set
    | prefix is used verbatim: 'heroicon-o-*', Filament's own 'fi-o-*', this
    | package's bundled 'arte-*', 'lucide-*' once that package is installed, and
    | any other Blade Icons set the same way.
    |
    | Filament's own buttons - bold, italic, the headings - are not in this list.
    | They belong to Filament and are swapped through `FilamentIcon::register()`.
    */
    'icons' => [
        // Toolbar. Outline throughout, so a swapped-in icon should be too - a bare
        // Heroicon name gives Filament's filled variant and would stand out.
        'headings' => 'fi-o-heading',
        'lists' => 'heroicon-o-list-bullet',
        'line_height' => 'arte-line-spacing',
        'task_list' => 'arte-task-list',
        'blockquote' => 'arte-message-square-quote',
        'image' => 'heroicon-o-photo',
        'text_color' => 'arte-letter-a',
        'text_background' => 'arte-highlighter',
        'color_custom' => 'arte-palette',
        'more' => 'heroicon-o-ellipsis-horizontal',
        'source_code' => 'heroicon-o-code-bracket',
        'help' => 'heroicon-o-question-mark-circle',
        'emoji' => 'heroicon-o-face-smile',

        // The emoji picker's own tabs.
        'emoji_recent' => 'heroicon-o-clock',
        'emoji_smileys' => 'heroicon-o-face-smile',
        'emoji_nature' => 'heroicon-o-bug-ant',
        'emoji_food' => 'heroicon-o-cake',
        'emoji_activities' => 'heroicon-o-trophy',
        'emoji_travel' => 'heroicon-o-globe-americas',
        'emoji_objects' => 'heroicon-o-light-bulb',
        'emoji_symbols' => 'heroicon-o-hashtag',
        'emoji_flags' => 'heroicon-o-flag',
        'emoji_close' => 'heroicon-o-x-mark',

        'direction_ltr' => 'arte-pilcrow-right',
        'direction_rtl' => 'arte-pilcrow-left',
        'fullscreen_enter' => 'heroicon-o-arrows-pointing-out',
        'fullscreen_exit' => 'heroicon-o-arrows-pointing-in',

        // The toolbar over a selected image.
        'image_rotate_left' => 'arte-rotate-ccw',
        'image_rotate_right' => 'arte-rotate-cw',
        'image_alt' => 'heroicon-o-chat-bubble-bottom-center-text',
        'image_size' => 'heroicon-o-arrows-pointing-out',
        'image_download' => 'heroicon-o-arrow-down-tray',
        'image_delete' => 'heroicon-o-trash',
        'image_locked' => 'heroicon-o-lock-closed',
        'image_unlocked' => 'heroicon-o-lock-open',
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
