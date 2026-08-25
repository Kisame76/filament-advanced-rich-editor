<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Toolbar
    |--------------------------------------------------------------------------
    | The default layout for every field. A nested array is one visually grouped
    | cluster, exactly like Filament's own `toolbarButtons()`. Alongside the stock
    | button names these tokens expand when the field renders:
    |
    |   'divider'    a vertical rule between two clusters
    |   'pin'        everything after it is pinned to the far end of the bar
    |   'headings'   dropdown of `heading_levels`      'lists'  dropdown of `lists`
    |   'alignment'  dropdown of `alignments`          'more'   overflow dropdown
    |   'lineHeight' dropdown of `line_height.values`
    |   'styles'     dropdown of `styles`, absent while that list is empty
    |
    | Tokens work at any depth, and a ToolbarDropdown or RichEditorTool may be mixed
    | in. Every other Filament tool is registered too and can be named anywhere:
    | 'highlight', 'small', 'lead', 'attachFiles', 'mergeTags', 'customBlocks',
    | 'ltr', 'rtl' and the table editing ones. Per field: `->toolbarButtons([...])`.
    */
    'toolbar' => [
        ['undo', 'redo'],
        'divider',
        ['headings', 'styles', 'fontFamily', 'fontSize'],
        'divider',
        ['bold', 'italic', 'underline', 'strike', 'link', 'textColor', 'textBackground'],
        'divider',
        ['alignment', 'lineHeight'],
        'divider',
        ['lists', 'image', 'embed', 'table', 'blockquote'],
        'divider',
        ['more'],
        'pin',
        ['find', 'accessibility', 'sourceCode', 'fullscreen', 'help'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Toolbar alignment
    |--------------------------------------------------------------------------
    | Where the groups sit on the bar: 'start', 'center', 'end' or 'between' (spread
    | across the full width). The pinned half takes whichever edge is left over.
    | Per field: `->toolbarAlignment()`.
    */
    'toolbar_alignment' => 'center',

    /*
    |--------------------------------------------------------------------------
    | Custom toolbar tokens
    |--------------------------------------------------------------------------
    | Extra tokens for the `toolbar` array, merged over the built-in ones — a key
    | defined here replaces 'headings' or 'lists'. Each value is a closure taking the
    | field and returning what to render:
    |
    |   'inline' => fn (AdvancedRichEditor $editor) => ToolbarDropdown::make('Inline', [
    |       'bold', 'italic', 'strike',
    |   ])->icon(Heroicon::Sparkles)->textualButtons(),
    |
    | Closures cannot be cached, so register tokens from a service provider if you run
    | `config:cache`.
    */
    'tokens' => [],

    /*
    |--------------------------------------------------------------------------
    | Heading levels
    |--------------------------------------------------------------------------
    | Which levels the 'headings' dropdown offers, in order. Only 1 to 6 are valid,
    | and listing h1 also enables the `h1` button. Per field: `->headingLevels()`.
    */
    'heading_levels' => [1, 2, 3, 4],

    /*
    |--------------------------------------------------------------------------
    | Paragraph in the headings dropdown
    |--------------------------------------------------------------------------
    | Lists the plain paragraph in front of the levels, so the dropdown covers every
    | block the caret can sit in. Per field: `->headingParagraph()`.
    */
    'heading_paragraph' => true,

    /*
    |--------------------------------------------------------------------------
    | Lists
    |--------------------------------------------------------------------------
    | Which list types the 'lists' dropdown offers, in order: 'bulletList',
    | 'orderedList', 'taskList'. Per field: `->listTypes()`.
    */
    'lists' => ['bulletList', 'orderedList', 'taskList'],

    /*
    |--------------------------------------------------------------------------
    | More
    |--------------------------------------------------------------------------
    | What the overflow dropdown offers, in order. Any Filament tool name is valid, an
    | unknown one is dropped, and an empty list removes the button altogether.
    | Per field: `->moreTools([...])`.
    */
    'more' => [
        'subscript', 'superscript', 'code', 'codeBlock', 'clearFormatting', 'horizontalRule',
        'details',
        'emoji',
    ],

    /*
    |--------------------------------------------------------------------------
    | Emoji
    |--------------------------------------------------------------------------
    | The emoji picker behind the 'emoji' tool. Emojis are inserted as ordinary Unicode
    | characters, so turning the picker off leaves the ones already written alone.
    | Per field: `->emoji()`.
    */
    'emoji' => true,

    /*
    |--------------------------------------------------------------------------
    | Find and replace
    |--------------------------------------------------------------------------
    | The 'find' tool and the bar behind it, opened by the button or by Ctrl+F while
    | the caret is in the editor. Nothing about it is stored: a search marks no
    | document and a replacement is ordinary text, so turning it off later changes
    | nothing that was written with it. Off also takes the keyboard shortcut away,
    | because the extension carrying it is then not loaded at all.
    | Per field: `->find()`.
    */
    'find' => true,

    /*
    |--------------------------------------------------------------------------
    | Accessibility check
    |--------------------------------------------------------------------------
    | The 'accessibility' tool and the panel behind it: a picture nobody described, a
    | link whose text is "click here", a heading level jumped over, a table with no
    | header row, a link with nothing in it, and a colour that cannot be read on the
    | page it is going to. Every finding is a row that selects what it is about.
    |
    | 'rules' is which of the six are asked; a name left out is not reported.
    |
    | Contrast is the one rule with two assumptions in it, and they are stated rather
    | than hidden: the editor cannot know what colour the page will be, nor what colour
    | it writes on that page where nobody chose one, because both belong to the front
    | end. 'background' and 'text' are those two - a chosen colour is measured against
    | the background, and a chosen background against the text colour, so highlighting a
    | sentence in dark blue is caught as well. 'threshold' is what the ratio has to reach - 4.5 is WCAG AA for ordinary text, and 'large_threshold'
    | is the easier level that headings and text of 24px and up are held to. Only the
    | light half of the palette is checked: a document rendered in both themes is two
    | questions, and answering one of them twice is a panel listing everything twice.
    |
    | 'weak_link_phrases' are added to the translated list rather than replacing it -
    | "click here" is a fact about English and not about the web, so the shipped list
    | follows the locale, and this is where a project adds the wording its own house
    | style keeps producing. A link's whole text has to be the phrase: "click here for
    | the report" says what it is and is left alone.
    |
    | Nothing about any of it is stored. Per field: `->accessibility()`,
    | `->accessibilityRules()`.
    */
    'accessibility' => [
        'enabled' => true,
        'rules' => [
            'missing_alt',
            'empty_link',
            'weak_link_text',
            'skipped_heading',
            'table_without_header',
            'weak_contrast',
        ],
        'background' => '#ffffff',
        'text' => '#18181b',
        'threshold' => 4.5,
        'large_threshold' => 3.0,
        'weak_link_phrases' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Drafts in the browser
    |--------------------------------------------------------------------------
    | A draft of what is being written, kept in the browser's own storage, offered
    | back the next time the same field on the same record is opened, and dropped as
    | soon as the document on screen says the same thing. Nothing about it reaches the
    | application: it exists for the moment a submit comes back as an expired session
    | or a 500 instead of as a saved record.
    |
    | 'debounce' is how long typing has to stop before a draft is written, in
    | milliseconds. 'ttl' is how long one survives, in seconds - a day by default,
    | which is long enough to recover a lost article and short enough that a shared
    | machine does not become an archive. This is content in a browser's storage that
    | outlives the session that wrote it, so a field holding something that should not
    | sit there turns this off: `->autosave(false)`.
    |
    | 'warn_on_leave' is the browser's own "leave site?" question, raised on closing a
    | tab whose editor says something the server has not been told. The wording is the
    | browser's; the only decision here is whether it is asked at all. Per field:
    | `->autosaveWarnOnLeave()`.
    */
    'autosave' => [
        'enabled' => true,
        'debounce' => 1500,
        'ttl' => 86400,
        'warn_on_leave' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Drag handle
    |--------------------------------------------------------------------------
    | The grip that appears in the margin of the block under the mouse, and the plus
    | beside it. Dragging the grip moves the block; the plus starts a new one under it
    | and opens the slash menu on top, so what it offers is everything that could go
    | there rather than a paragraph.
    |
    | Only the top level of the document gets one, so the grip on a list takes the
    | list rather than the item under the mouse: a list item may only live inside a
    | list, and a drag of one refuses more often than it works.
    |
    | Nothing about it is stored - rearranging a document changes the order of what is
    | in it and leaves no trace of how - so turning it off changes nothing already
    | written. Per field: `->dragHandle()`, `->dragHandleInsert()`.
    */
    'drag_handle' => [
        'enabled' => true,
        'insert' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pasting
    |--------------------------------------------------------------------------
    | What arrives from the clipboard, made into a document again. Word sends a
    | stylesheet, a handful of tags no browser has heard of and a list that is not a
    | list; Google Docs sends every run of text in a span carrying eleven declarations,
    | one of which is the only place its bold lives. Both are cleaned on the way in -
    | headings, lists, tables, links and emphasis kept, typography dropped - and a copy
    | from another editor is left exactly as it is.
    |
    | 'keep_styles' names the style properties that survive. Shipped as the two that are
    | structure rather than typography - the alignment, and the shape of an embed -
    | because everything else there is parsed into a mark of this package's own, so a
    | property left standing is not noise the next save drops, it is Calibri 11pt in
    | black in the document for good. Add 'color', 'background-color', 'font-family' or
    | 'font-size' to a project that wants pastes to arrive wearing them; taking
    | 'aspect-ratio' out costs a pasted embed its ratio and nothing else.
    |
    | Nothing about any of it is stored, so turning it off changes the next paste and no
    | document already written. Per field: `->pasteCleanup()`, `->pasteKeepStyles()`.
    */
    'paste' => [
        'cleanup' => true,
        'keep_styles' => ['text-align', 'aspect-ratio'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Text direction
    |--------------------------------------------------------------------------
    | The 'ltr' and 'rtl' tools, which write a `dir` attribute on the block the caret
    | sits in. Registered but deliberately not in the default toolbar — name them in a
    | toolbar or in `more` to get the buttons.
    |
    | The extension stays registered either way, so content that already carries a
    | `dir` keeps it. Set this to false only where losing it on the next save is
    | wanted. Per field: `->textDirection()`.
    */
    'text_direction' => true,

    /*
    |--------------------------------------------------------------------------
    | Code blocks
    |--------------------------------------------------------------------------
    | The picker on a code block, and the colours on the rendered page.
    |
    |   'languages'  what the picker offers, as `value => label`. The value is written into
    |                `class="language-…"`, which is where the highlighter reads it. An empty
    |                list takes the picker away.
    |   'theme'      the theme `->highlightCode()` uses. Any Phiki theme name.
    |   'themes'     a light/dark pair instead, e.g. ['light' => 'github-light',
    |                'dark' => 'github-dark']. Both are written into the same markup, and a
    |                rule in your own stylesheet swaps them - see the README.
    |
    | Colouring happens in PHP when the page is rendered, and needs phiki/phiki. The editor
    | shows plain monospace: a highlighter in the panel colours text only its author sees.
    | Per field: `->codeBlockLanguages()`.
    */
    'code_block' => [
        'languages' => [
            'bash' => 'Bash',
            'css' => 'CSS',
            'diff' => 'Diff',
            'html' => 'HTML',
            'javascript' => 'JavaScript',
            'json' => 'JSON',
            'markdown' => 'Markdown',
            'php' => 'PHP',
            'python' => 'Python',
            'sql' => 'SQL',
            'typescript' => 'TypeScript',
            'yaml' => 'YAML',
        ],
        'theme' => 'github-light',
        'themes' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Embeds
    |--------------------------------------------------------------------------
    | Video embeds. A pasted link is taken apart and the embed URL is built from it, so
    | every shape a share button produces works - watch, youtu.be, shorts, embed, and the
    | Vimeo equivalents - including the timestamp.
    |
    |   'sanitizer'         allows `<iframe>` back into rendered content, with `src`
    |                       narrowed to 'allowed_hosts'. Filament's sanitiser drops iframes,
    |                       so without this an embed is stored and never shown. It changes a
    |                       configuration the whole application shares, which is why it is a
    |                       switch rather than something the package just does.
    |   'allowed_hosts'     hosts an embed may point at, subdomains included. Something else
    |                       in the project embedding iframes has to be listed here too - see
    |                       the README on why both cannot allowlist separately.
    |   'youtube_nocookie'  embeds through youtube-nocookie.com, which is what keeps an
    |                       embedded video from setting a tracking cookie on the reader.
    |
    | Per field: `->embeds()`.
    */
    'embed' => [
        'enabled' => true,
        'sanitizer' => true,
        'youtube_nocookie' => true,
        'allowed_hosts' => [
            'youtube-nocookie.com',
            'youtube.com',
            'vimeo.com',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Slash menu
    |--------------------------------------------------------------------------
    | Typing the slash character on an empty line, or after a space, opens a searchable
    | menu of the commands the field offers. Every entry is a tool the field actually
    | registered - a feature switched off disappears from the menu with it, and a name
    | listed here that the field does not have is dropped.
    |
    | Only blocks and things you insert: the menu opens where the caret sits with nothing
    | selected, and an inline format there would mark nothing. `'headings'` expands to the
    | levels the field offers. Per field: `->slashMenu()`.
    */
    /*
    |--------------------------------------------------------------------------
    | Mentions
    |--------------------------------------------------------------------------
    |
    | Whose menu opens when a trigger is typed. This package's own has room for a picture
    | and a line of context under the name, which is what tells two people called the same
    | thing apart; Filament's draws the name and nothing else.
    |
    | The mention itself is unchanged either way - the same node, the same `data-id`, the
    | same markup on the page - so this can be switched at any time without touching
    | anything already written. Per field: `->mentionMenu()`.
    |
    */

    'mentions' => [
        'menu' => true,
    ],

    'slash' => [
        'enabled' => true,
        'char' => '/',
        'groups' => [
            // 'style' changes what the block already there is; 'insert' adds something new.
            'style' => [
                'paragraph', 'headings',
                'bulletList', 'orderedList', 'taskList',
                'blockquote', 'codeBlock',
            ],
            'insert' => [
                'image', 'attachFiles', 'embed', 'table', 'horizontalRule', 'details', 'emoji',
                'customBlocks', 'mergeTags',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Link attributes
    |--------------------------------------------------------------------------
    | Whether the link dialog offers `rel`, `referrerpolicy`, `hreflang` and an anchor on
    | top of the URL and the target, and whether the schema keeps them. Both halves move
    | together: a dialog writing an attribute the schema drops would be lying.
    |
    | Turning this off falls back to Filament's own dialog and its own link mark, and the
    | extra attributes are stripped from content that already carries them on the next
    | save. Per field: `->linkAttributes()`.
    */
    'link' => [
        'attributes' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Help
    |--------------------------------------------------------------------------
    | The question mark at the end of the toolbar, listing the keyboard shortcuts that
    | field answers to. `help_more` adds a second tab for whatever the project wants to
    | tell the people writing; a plain string is escaped and keeps its line breaks.
    | Per field: `->help()` and `->helpMore()`, which also takes an `Htmlable`.
    */
    'help' => true,

    'help_more' => null,

    /*
    |--------------------------------------------------------------------------
    | Source code
    |--------------------------------------------------------------------------
    | The button that opens the document as HTML. Both directions run through the
    | field's own TipTap schema, so what the modal shows is what gets stored.
    |
    | Off, because it hands an editor a way past every control the toolbar stands for:
    | whoever types into that box writes whatever the schema will accept, and the toolbar
    | stops being the answer to what a document may contain. Worth having where the people
    | using it know HTML, not worth giving to everybody who installs the package. The token
    | stays in the shipped toolbar and resolves to nothing, so turning this on is all it
    | takes. Per field: `->sourceCode()`.
    */
    'source_code' => false,

    /*
    |--------------------------------------------------------------------------
    | Fullscreen
    |--------------------------------------------------------------------------
    | The button that expands the editor over the window. A fixed overlay rather than
    | the browser's Fullscreen API, so Filament's modals still appear above it.
    */
    'fullscreen' => true,

    /*
    |--------------------------------------------------------------------------
    | Colours
    |--------------------------------------------------------------------------
    | Two swatch dropdowns: 'textColor' paints the letters, 'textBackground' paints
    | behind them, 'custom' adds a free colour picker to both.
    |
    | The text palette is keyed by NAME and each entry carries a light and a dark
    | value, which is what keeps the same text readable in both themes. The background
    | palette is keyed by CSS colour and is kept light on purpose: it sits behind text.
    | Per field: `->textColors([...])`, `->backgroundColors([...])`.
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
    | Which alignments the 'alignment' dropdown offers, in order: 'alignStart',
    | 'alignCenter', 'alignEnd', 'alignJustify'. Per field: `->alignments()`.
    */
    'alignments' => ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'],

    /*
    |--------------------------------------------------------------------------
    | Line spacing
    |--------------------------------------------------------------------------
    | Which spacings the 'lineHeight' dropdown offers, in order. Each is a unitless
    | `line-height`, so a heading keeps its own proportions. Values are bare numbers
    | between 0.5 and 5 and anything else is dropped; `1` and `2` are labelled "Single"
    | and "Double". Per field: `->lineHeights([...])`, `->lineHeight(false)`.
    */
    'line_height' => [
        'enabled' => true,
        'values' => [1, 1.15, 1.5, 2],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sticky toolbar
    |--------------------------------------------------------------------------
    | Keeps the toolbar pinned while a long document is scrolled. `offset` is any CSS
    | length and should match whatever sits above the form — usually the topbar.
    */
    /*
    |--------------------------------------------------------------------------
    | Maximum height
    |--------------------------------------------------------------------------
    | Caps every editor's height and lets it scroll inside the field instead of pushing
    | the rest of the form down the page. Any CSS length; a bare number is read as pixels.
    | Null lets the editor grow with its content, which is the default.
    |
    | A capped field turns its own sticky toolbar off: the bar sits above the box that
    | scrolls, so it stays in view without being pinned to anything.
    | Per field: `->maxHeight()`.
    */
    'max_height' => null,

    'sticky' => [
        'enabled' => true,
        'offset' => '4rem',
    ],

    /*
    |--------------------------------------------------------------------------
    | Marking styled text in the editor
    |--------------------------------------------------------------------------
    | Whether the editor marks the text a style sits on. Off, and that is deliberate
    | rather than an oversight: a style is a set of your classes, and none of them resolve
    | in an admin panel that never loaded your front end's stylesheet. The package can
    | therefore show that a style is set, never what it looks like.
    |
    | Turned on, styled text gets a neutral marking - a rule down the side of a block, a
    | dotted line under a run of text. It is a stopgap, not the real thing.
    |
    | The real thing is six lines in your panel theme, and it beats this in every way:
    |
    |   [data-style="lead"]   { font-size: 1.125rem; color: #475569; }
    |   [data-style="kicker"] { text-transform: uppercase; letter-spacing: .05em; }
    |
    | Those override this, so switching it on while you write them costs nothing.
    | Per field: `->stylePreview()`.
    */
    'style_preview' => false,

    /*
    |--------------------------------------------------------------------------
    | Toolbar over a selection
    |--------------------------------------------------------------------------
    | The little bar that appears over selected text. Filament ships one for a selected
    | image and one for a table cell; this is the third, and the one people reach for
    | most - at a selection they expect bold, a link and the project's own styles right
    | there rather than at the top of the field.
    |
    | 'text_toolbar_buttons' takes the same tokens the main toolbar does, so 'styles' and
    | 'textColor' mean the same thing in both, and a feature switched off on the field
    | takes its button out of here too. An empty list takes the bar away, and so does
    | setting 'text_toolbar' to false.
    |
    | Per field: `->textToolbar(false)`, `->textToolbarButtons([...])`.
    */
    'text_toolbar' => true,

    'text_toolbar_buttons' => [
        'styles', 'bold', 'italic', 'underline', 'strike', 'link', 'textColor', 'textBackground',
    ],

    /*
    |--------------------------------------------------------------------------
    | Styles
    |--------------------------------------------------------------------------
    | Named styles from your own design system, offered by the 'styles' token. Shipped
    | empty on purpose: the classes belong to your front end, and an editor offering
    | styles nobody designed is worse than one offering none.
    |
    | Each entry is a key, a label and the classes it applies:
    |
    |   'label'  what the dropdown shows
    |   'class'  the classes written into the rendered page
    |   'scope'  'block' (default) puts them on the paragraph or heading the caret sits
    |            in; 'inline' puts them on the selected text
    |   'types'  block only: which blocks may carry it. Defaults to all of paragraph,
    |            heading, blockquote, listItem and codeBlock
    |
    | One style at a time, per scope: picking a second replaces the first, the way a
    | heading level does. A style that wants two of your classes together is one entry
    | holding both.
    |
    | Stored as `data-style="<key>"` alongside the classes. The key is what the next parse
    | reads, so editing the classes here updates documents that already exist rather than
    | leaving them on the old ones. The sanitiser drops the key when the page is rendered
    | and keeps the classes, which is exactly the split that is wanted.
    |
    | Per field: `->styles([...])`, and `->styles([])` takes the button away.
    */
    'styles' => [
        // 'lead' => [
        //     'label' => 'Lead',
        //     'class' => 'text-lg text-slate-600',
        //     'scope' => 'block',
        // ],
        // 'kicker' => [
        //     'label' => 'Kicker',
        //     'class' => 'uppercase tracking-wide text-sm',
        //     'scope' => 'inline',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Empty documents
    |--------------------------------------------------------------------------
    | Whether a document with nothing in it is stored as nothing rather than as the
    | `<p></p>` TipTap always keeps. Off by default, and that is a decision about your
    | database rather than a preference: a column that is `NOT NULL` without a default
    | takes `<p></p>` and refuses a null, so a save that works today would stop working.
    |
    | Turned on, a field that shows nothing on the page is also nothing in the record,
    | which is what `@if($post->content)` and a `whereNull` both already assume.
    |
    | A document counts as empty when it holds nothing but paragraphs, line breaks and
    | whitespace - the non-breaking kind included. Anything else is content, an image and
    | a horizontal rule as much as a word. Per field: `->nullWhenEmpty()`.
    |
    | This says nothing about `required()`, which rejects an empty document either way.
    */
    'null_when_empty' => false,

    /*
    |--------------------------------------------------------------------------
    | Task list
    |--------------------------------------------------------------------------
    | The checkbox task list: the TipTap extensions, the 'taskList' button and the
    | rendering of `<ul data-type="task-list">` in saved content. Off keeps the
    | editor's JSON free of task list nodes. Per field: `->taskList()`.
    |
    | A feature that is only on or off is a boolean here; one that carries options
    | is an array with `enabled` in front of them.
    */
    'task_list' => true,

    /*
    |--------------------------------------------------------------------------
    | Fonts
    |--------------------------------------------------------------------------
    | The typeface dropdown. Nothing is fetched from anywhere: no CDN, no Google Fonts,
    | no network at all.
    |
    | `directory` is where the project keeps its font files, relative to public/. Every
    | file found is offered and gets an `@font-face` rule. The family comes from the
    | folder name, or from the file name up to its first separator, and the weight and
    | style from the rest: `Inter/Inter-SemiBoldItalic.woff2` is Inter at 600, italic.
    |
    | `families` is for typefaces the project loads elsewhere, and those are the only
    | entries the browser is asked about before they are shown. `generic` adds the
    | three stacks that resolve everywhere. Per field: `->fonts([...])`,
    | `->fontPicker(false)`.
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
    | The 'fontSize' menu: the sizes worth offering, plus a field for the one that is
    | not on the list. Sizes are in pixels, `min` and `max` bound a typed size, and an
    | entry in `sizes` outside them is dropped. Text without an explicit size is
    | measured off the page, so `default` is only the last resort when that measurement
    | is not possible. Per field: `->fontSize()`.
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
    | `resizable` lets an image be dragged to a new size, which writes a width onto the
    | node and is kept in the saved markup. `toolbar` is the strip over a selected
    | image: aspect ratio lock, size and alt text panels, rotation, download, delete.
    | Per field: `->resizableImages()`.
    */
    'images' => [
        'resizable' => true,

        'toolbar' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Media library
    |--------------------------------------------------------------------------
    | The image button opens a browser of the pictures that are already on the
    | server, with uploading as its second tab. Picking one stores what an upload
    | would have stored — a media UUID, or a storage path on a field without a media
    | collection — so one file can back any number of references and nothing is
    | copied. Size and rotation stay on the image node, never on the file.
    |
    | Out of the box the browser is a shared library: it shows every picture in the
    | collection the field uploads to, whichever record or model owns it. That is what
    | the browser is for — a picture uploaded for one article is the picture the next
    | one wants — and it is the 'scope' setting below. Narrow it with
    | `'scope' => 'record'` where each record should only see its own.
    |
    | Sharing has one consequence worth knowing before you ship it: removing a picture
    | from a document no longer deletes the file. It cannot — the uuid may equally be
    | sitting in another record's content, which nothing here can see, so deleting it
    | would take that record's picture away too. A shared library is therefore tidied
    | deliberately, with `spatie/laravel-medialibrary`'s own cleanup commands or by
    | hand. `'scope' => 'record'` restores automatic clean-up, because then nothing
    | else can be holding the uuid.
    |
    | Name the pool outright per field where the scopes do not fit:
    |
    |   ->mediaLibraryQuery(fn (Builder $query) => $query->where('collection_name', 'library'))
    |   ->mediaLibraryDirectory('library')   // fields storing plain files on a disk
    |
    | Whatever the pool lists is also what a stored `data-id` is allowed to resolve
    | to — the browser and the lookup are the same object, so they cannot drift apart.
    |
    | 'directory' is the project-wide default for the disk pool; null keeps every
    | field on its own `fileAttachmentsDirectory()`. Per field: `->mediaLibrary()`.
    */
    'media_library' => [
        'enabled' => true,

        /*
         * How far the browser looks with a media collection. Three settings, each narrower than
         * the last:
         *
         *   'collection'  every picture in the collection the field uploads to, whichever
         *                 record or model owns it — the default, because the collection *is*
         *                 the library. An article and a post uploading to 'rich-editor' draw
         *                 from one pool instead of each fetching the same picture again;
         *                 separate libraries are separate collections.
         *   'model'       only the records of the model being edited.
         *   'record'      only the record in front of you.
         *
         * Whatever it lists is also what a stored `data-id` may resolve to, so the two can
         * never drift apart. Per field: `->mediaLibraryScope()`, or `->mediaLibraryQuery()`.
         */
        'scope' => 'collection',

        'page_size' => 40,

        'directory' => null,

        /*
         * The conversion the grid draws its tiles from - separate from the one an inserted
         * picture uses, because a tile is 120 pixels wide and the picture in the document is
         * not. Declare it on the model, which is the only place Spatie accepts one:
         *
         *   public function registerMediaConversions(?Media $media = null): void
         *   {
         *       $this->addMediaConversion('arte-thumb')->fit(Fit::Contain, 320, 320);
         *   }
         *
         * Anything not generated yet falls back to the original file, so naming a conversion
         * before it exists costs nothing. Per field: `->mediaLibraryThumbnail()`.
         */
        'thumbnail' => null,

        /*
         * Which of the two layouts the browser opens in. Tiles by default, because picking a
         * picture is done by looking at pictures; the list is what tiles cannot do - names,
         * sizes and dates lined up in columns. The switch is in the dialog either way.
         */
        'list_view' => false,

        /*
         * Where a measurement is remembered. Reading how big a picture is means opening it,
         * and a listing does that for every row - so the answer is cached against the file's
         * size and modification time, and a file replaced under the same name is measured
         * again. Null uses the application's default store.
         */
        'cache_store' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Character count
    |--------------------------------------------------------------------------
    | The line under the editor saying how long the text is. It counts the way
    | Filament's own `maxLength` validation counts, and shows a limit as soon as the
    | field has one — `maxLength()`, or `->characterCountLimit()` for a target without
    | a rule behind it. Per field: `->characterCount()`.
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
    | 'photo') is handed to Filament as its enum, which picks the variant matching the
    | size it is drawn at; anything with a set prefix is used verbatim ('heroicon-o-*',
    | Filament's 'fi-o-*', this package's 'arte-*', 'lucide-*', any Blade Icons set).
    |
    | Filament's own buttons — bold, italic, the headings — are not in this list. They
    | belong to Filament and are swapped through `FilamentIcon::register()`.
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
        'embed' => 'heroicon-o-film',
        'text_color' => 'arte-letter-a',
        'text_background' => 'arte-highlighter',
        'color_custom' => 'arte-palette',
        'more' => 'heroicon-o-ellipsis-horizontal',
        'source_code' => 'heroicon-o-code-bracket',
        'find' => 'heroicon-o-magnifying-glass',
        'find_previous' => 'heroicon-o-chevron-up',
        'find_next' => 'heroicon-o-chevron-down',
        'find_close' => 'heroicon-o-x-mark',
        'find_replace' => 'arte-replace',
        'find_grip' => 'arte-grip-vertical',

        // The accessibility report and the window it opens in.
        'accessibility' => 'heroicon-o-clipboard-document-check',
        'accessibility_grip' => 'arte-grip-vertical',
        'accessibility_close' => 'heroicon-o-x-mark',

        // The grip in the margin, and the plus that starts a block under it.
        'drag_handle' => 'arte-grip-vertical',
        'drag_handle_insert' => 'heroicon-o-plus',
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
    | Anchors
    |--------------------------------------------------------------------------
    | Headings that a link can point at, used by `AdvancedRichContentRenderer::
    | anchorHeadings()` and by `TableOfContents`. Both take their ids from the same pass,
    | so a link in the list and an `id` on the page cannot drift apart.
    |
    |   'levels'    which heading levels get an anchor, and appear in a table of contents
    |   'position'  where the heading's link to itself is drawn: 'none' (nothing is added,
    |               the default), 'before', 'after', or 'wrap' to turn the heading text
    |               itself into the link
    |   'symbol'    the marker drawn for 'before' and 'after'
    |   'class'     the class on that link and, with `-toc`, on the table of contents
    |   'language'  whose transliteration rules build the slug: null folds to plain ASCII
    |               ("Uber uns"), 'de' spells the umlaut out ("ueber-uns"). The anchor ends
    |               up in URLs, so this is a project-wide decision.
    |
    | An id already in the stored markup is kept as it is - something out there may link
    | to it - and counted, so nothing generated afterwards collides with it.
    */
    'anchors' => [
        'levels' => [2, 3],
        'position' => 'none',
        'symbol' => '#',
        'class' => 'fi-arte-anchor',
        'language' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Spatie Media Library attachments
    |--------------------------------------------------------------------------
    | Defaults for fields that opt in with `->spatieMediaLibrary()`. `conversion` is the
    | conversion whose URL gets embedded (null = the original file), `disk` falls back
    | to the collection's own disk, `visibility` is passed to the filesystem.
    |
    | Every upload is stamped with the field that made it, so two editors on one record
    | can share a collection without cleaning up after each other.
    */
    'spatie' => [
        'collection' => 'rich-editor',
        'conversion' => null,
        'disk' => null,
        'visibility' => 'public',
    ],
];
