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
    |   'dateTime'   dropdown of `date_time.formats`, absent while it is switched off
    |   'callouts'   dropdown of `callouts.variants`
    |   'language'   dropdown of `languages.values`
    |   'characters' the special characters picker, absent while it is switched off
    |   'emoji'      the emoji picker, absent while it is switched off
    |   'indent'     one step in, and 'outdent' one step out, both absent while
    |                indenting is switched off
    |   'styles'     dropdown of `styles`, absent while that list is empty
    |
    | Tokens work at any depth, and a ToolbarDropdown or RichEditorTool may be mixed
    | in. Every other Filament tool is registered too and can be named anywhere:
    | 'highlight', 'small', 'lead', 'attachFiles', 'mergeTags', 'customBlocks',
    | 'ltr', 'rtl' and the table editing ones. Per field: `->toolbarButtons([...])`, or
    | `->preset('blog')` for a named starting point - see 'toolbar_presets' below.
    */
    'toolbar' => [
        ['undo', 'redo'],
        'divider',
        ['headings', 'styles', 'fontSize'],
        'divider',
        ['bold', 'italic', 'underline', 'link', 'textColor', 'textBackground'],
        'divider',
        ['alignment', 'lineHeight'],
        'divider',
        ['lists', 'image', 'embed', 'table', 'callouts'],
        'divider',
        ['more'],
        'pin',
        ['tools', 'fullscreen'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Toolbar presets
    |--------------------------------------------------------------------------
    | Named starting points for all four bars at once, asked for with `->preset('blog')`.
    | Five are shipped, from the shortest bar to the longest:
    |
    |   'minimal'  bold, italic, underline, link and lists. Nothing else, no uploads
    |   'comment'  the same plus the quote and the emoji picker, no uploads
    |   'blog'     structure, pictures and both menus, without the typographic controls
    |   'default'  the bar above, under a name
    |   'full'     the longest bar this package draws: 'fontFamily', 'textCase' and
    |              'language' on it, 'strike' in 'more'. Not literally everything - the
    |              features that ship switched off (the format brush, the date tools) are
    |              a decision per project rather than a preset's to make
    |
    | A preset answers five things - 'toolbar', 'more', 'tools_menu',
    | 'text_toolbar_buttons' and 'file_attachments' - and may answer only some of them.
    | What it leaves out falls through to the keys in this file, so a preset is free to
    | speak about the main bar and leave the rest alone.
    |
    | 'file_attachments' is named rather than inferred on purpose. Without it a shrinking
    | bar takes the upload, the drop and the paste-upload with it, because the field's
    | answer is otherwise read off the presence of the picture button. It holds only while
    | the preset still describes what is on screen: a field that replaces the bar with
    | `->toolbarButtons()` is answered by the picture button again, the way an unpreset
    | field is.
    |
    | A preset is a fixed list and not a copy of the keys in this file: `->preset('default')`
    | stays the bar this package ships even where a project has rebuilt 'toolbar' into
    | something else. That is what makes it a starting point rather than a second name for
    | the current state.
    |
    | Add your own here, or replace a shipped one by naming it again:
    |
    |   'toolbar_presets' => [
    |       'house' => [
    |           'toolbar' => [['bold', 'italic'], 'divider', ['link']],
    |           'file_attachments' => false,
    |       ],
    |   ],
    |
    | A field always wins: 'toolbar_presets' sets defaults, and `->toolbarButtons()`,
    | `->moreTools()`, `->toolsMenu()`, `->textToolbarButtons()` and `->fileAttachments()`
    | each override the preset's answer for their own bar.
    */
    'toolbar_presets' => [],

    /*
    |--------------------------------------------------------------------------
    | The mode with no toolbar
    |--------------------------------------------------------------------------
    | `->notion()` takes the bar away and leaves the document to carry the editor: the
    | slash menu knows this field's own tools, the grip rearranges blocks, and the bar over
    | a selection holds the marks.
    |
    | There is no key here, on purpose. All three of those are on by default already, so a
    | switch in this file would only be a second name for switching them off - and the
    | point of the mode is that it holds them on for the one field that has nothing else
    | left to reach a block with. It sits between the field and this file: `->notion()
    | ->dragHandle(false)` still takes the grip away, and 'drag_handle.enabled' => false
    | no longer does.
    |
    | Naming a preset alongside it puts a bar back, because a preset says something more
    | specific about the bar than "there is none" - and answers for uploads, which the mode
    | otherwise says yes to. It has to: the upload answer is read off the bar, this mode has
    | no bar, and the slash menu's insert group ships 'image' and 'attachFiles'.
    |
    | One limit, and it is Filament's: the bar over a selection is registered under the
    | 'paragraph' key and its compiled bundle shows it only while the caret is in a
    | paragraph. Inside a heading it does not appear, so with no toolbar the link, the
    | colours and the styles are out of reach there. Any field with `->toolbarButtons([])`
    | has the same hole today.
    */

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
        'subscript', 'superscript', 'code', 'codeBlock', 'blockquote', 'clearFormatting', 'horizontalRule',
        'details',
        'emoji', 'characters',
    ],

    /*
    |--------------------------------------------------------------------------
    | The tools menu
    |--------------------------------------------------------------------------
    | What the 'tools' token offers: the things a field does rather than the things it
    | writes. Named rather than a second set of three dots, because two menus both
    | called "More" on one bar are two doors with the same sign and different rooms
    | behind them.
    |
    | It is in the shipped toolbar, as ['tools', 'fullscreen']. Shipped that way the
    | corner never changes shape: switching the accessibility check or the source view on
    | puts them in the menu rather than adding a fourth and fifth icon beside it, and the
    | preview, statistics and export tools still to come go the same way.
    |
    | The cost is that finding is one click deeper than it was on a field that switched
    | nothing on - Ctrl+F is unaffected, and the help dialog lists it. A project that
    | would rather have the buttons names them individually instead:
    |
    |   ['find', 'accessibility', 'sourceCode', 'fullscreen', 'help']
    |
    | An empty menu is dropped rather than drawn. Per field: `->toolsMenu()`.
    */
    'tools_menu' => ['find', 'accessibility', 'statistics', 'preview', 'sourceCode', 'help'],

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
    | Shipped off. It is a review tool rather than a way of writing, and the contrast
    | rule is measured against a page this package has to be told the colour of - on by
    | default, every project whose pages are not white would be handed findings that are
    | wrong. Switch it on here or per field with `->accessibility()`, and the button
    | appears where the shipped toolbar already reserves a place for it, between 'find'
    | and 'sourceCode'. Nothing about any of it is stored.
    |
    | Per field: `->accessibility()`, `->accessibilityRules()`.
    */
    'accessibility' => [
        'enabled' => false,
        'rules' => [
            'missing_alt',
            'decorative_link',
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
    | selected, and an inline format there would mark nothing. `'headings'` and
    | `'callouts'` expand to what the field offers - its heading levels and its kinds of
    | callout. Per field: `->slashMenu()`.
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
                'blockquote', 'codeBlock', 'callouts',
            ],
            'insert' => [
                'image', 'attachFiles', 'embed', 'table', 'horizontalRule', 'details', 'emoji', 'characters',
                'dateTime', 'customBlocks', 'mergeTags',
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
    'statistics' => [
        'enabled' => true,
        // How fast a reader is assumed to be. 200 is the usual figure for prose in a
        // European language; technical writing is slower and a project that knows its own
        // readers should say so. The dialog rounds up, so this only has to be roughly
        // right - it is an estimate presented as one, not a measurement.
        'words_per_minute' => 200,
    ],

    /*
    |--------------------------------------------------------------------------
    | Preview
    |--------------------------------------------------------------------------
    | The document drawn the way the site draws it, in an isolated frame. The panel
    | cannot answer that question in place: it has already loaded this package's whole
    | stylesheet, and the content rules in it are deliberately unscoped so that they apply
    | wherever content is rendered - including inside anything drawn here. A frame is the
    | only boundary the browser enforces.
    |
    | Which is why this is a seam rather than a feature: the package supplies the frame,
    | the project supplies the CSS. Per field: `->preview(false)`, `->previewStylesheets()`
    | and `->previewWrapperClass()`.
    */
    'preview' => [
        /*
         * Not what switches the preview on, despite reading like it: `stylesheets` below
         * ships empty, and without one named there is no button on any field. A default
         * installation draws `find`, `statistics` and `help` in this menu and nothing else.
         *
         * This is the switch for taking the tool away from a project that named a stylesheet
         * and does not want the button - which is why it defaults to true rather than false.
         * Defaulting it to false would mean doing two things to get one feature, and the
         * second of them would do nothing on its own.
         */
        'enabled' => true,

        /*
         * The stylesheets the preview frame loads, as URLs a browser can reach. Your own
         * built stylesheet - the same file the site loads.
         *
         * Shipped empty, and the tool is not offered until one is named. That is not
         * caution, it is the only honest answer: a front end's CSS belongs to the project,
         * this package has none to lend, and a frame with no stylesheet draws unstyled
         * markup. A button labelled "preview" opening onto that lies about what it did.
         *
         * The preview is therefore also a check on something this documentation asks for
         * elsewhere: the package stylesheet is registered with Filament and loads in the
         * panel only, so the callout and task list rules have to be copied into your own
         * sheet. If they were not, the frame shows that - correctly.
         */
        'stylesheets' => [],

        /*
         * The class the frame's `<body>` carries, e.g. 'prose dark:prose-invert mx-auto
         * max-w-2xl'.
         *
         * A stylesheet alone usually draws very little: rendered content normally sits in a
         * container that sets the measure and in a `prose` class that styles the bare tags
         * at all, and a dark theme is usually a class on an ancestor. On the body rather
         * than on a wrapper inside it, so one string covers all three.
         */
        'wrapper_class' => null,
    ],

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
    | Indenting
    |--------------------------------------------------------------------------
    | Two buttons, 'indent' and 'outdent', that move a block one step further in and one
    | step back out, and Mod+] and Mod+[ on every field that has them — whether or not the
    | buttons are on a bar.
    |
    | The step is stored as a `margin-inline-start` in the block's inline style — logical,
    | so an indent on a right-to-left paragraph sits on its right, and a style rather than
    | a class because a class means nothing without a stylesheet this package does not ship
    | over your rendered pages. `margin-left` is read on the way in and converted.
    |
    | 'step' is a CSS length in any absolute unit ('2.5rem', '1.27cm', '40px'); a bare
    | number is read as rem. What a document keeps is the number of steps, not the length,
    | so both buttons always land on the grid — which also means that changing the step
    | here re-measures existing documents on their next save. 'max' is how many steps deep
    | a block may go, 1 to 40.
    |
    | Inside a list the same two buttons nest the item instead, which is what a list's
    | numbering and bullets are made of.
    |
    | Ships off, and nothing ships on a bar either. Most documents indent nothing, and the
    | ones that do are a kind rather than a majority — a contract, a report, minutes — so
    | this is a decision rather than a default. Switching it on is this line, and the two
    | keys work from that moment; put the names in 'more', or the 'indent' and 'outdent'
    | tokens on a bar, to give them buttons as well. Per field: `->indent()`,
    | `->indentStep('1.27cm')`, `->indentMax(4)`.
    */
    'indent' => [
        'enabled' => false,
        'step' => '2.5rem',
        'max' => 8,
    ],

    /*
    |--------------------------------------------------------------------------
    | Date and time
    |--------------------------------------------------------------------------
    | Writing today's date, or the time, into the document. One tool per entry below,
    | named `insert` plus the key: 'date' is `insertDate`, 'dateTime' is `insertDateTime`.
    | A key is a bare identifier — a lower-case letter, then letters and digits — because
    | it becomes a tool name, and a tool name is what a toolbar array names it by.
    |
    | The value is a PHP date format. `null` means "whatever this schema says a date looks
    | like" and works for the three keys Filament itself answers for — 'date', 'time' and
    | 'dateTime'; any other key must name its own format or it is dropped. Change the
    | inherited answer with `Schema::configureUsing()` in a service provider rather than
    | here, and note that Filament's own date pickers do not read it.
    |
    | Formats are rendered with Carbon's `translatedFormat()`, so month and day names come
    | out in the application's language. Two things it does differently from `date()`: `S`
    | is the ordinal suffix of that language rather than the English one, and `e`, `p`, `x`
    | and `X` are not translated and arrive as the bare letter — use `T`, `O` or `P` to
    | name a zone. Every other unescaped letter is a token, so literals need a backslash:
    | `'\H\e\u\t\e, j. F Y'`.
    |
    | A format carrying a time is rendered in Filament's display timezone
    | (`FilamentTimezone::set()`); a date on its own is not, because an offset applied to a
    | date moves it a whole day either side of midnight.
    |
    | Ships off, and nothing ships on a bar either. A date typed once is a date; a button
    | for it is worth having where a template is filled in daily and worth nothing in a
    | blog, so this is a decision rather than a default. Switching it on is this line, and
    | the way in is then the slash menu - put the names in 'more', or the 'dateTime' token
    | on a bar, to give them buttons as well. Per field: `->dateTime()`,
    | `->dateTimeFormats([...])`.
    */
    'date_time' => [
        'enabled' => false,
        'formats' => [
            'date' => null,
            'time' => null,
            'dateTime' => null,
        ],
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
        'styles', 'bold', 'italic', 'underline', 'link', 'textColor', 'textBackground',
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
    | Callouts
    |--------------------------------------------------------------------------
    | The note, tip, warning and danger boxes: a paragraph pulled out of the flow and
    | drawn as something the reader is meant to stop at. One node with the kind on it,
    | so turning a note into a warning is a change of colour rather than a rewrite.
    | Per field: `->callouts()` and `->calloutVariants([...])`.
    |
    | `variants` is which kinds are offered and in what order — that order is what the
    | 'callouts' dropdown and the slash menu read in. A name is a lowercase word,
    | optionally hyphenated; anything else is dropped rather than escaped, because a
    | variant becomes part of a CSS class and part of a button's JavaScript handler.
    |
    | Naming one the package does not ship works: the tool, the menu entry and the class
    | are all built from the name. It gets its name in title case for a label, the
    | family's icon, and the neutral box the stylesheet draws for a kind it has no colour
    | for — add `icons.callout_<name>` and a rule for `.fi-arte-callout-<name>` to give it
    | its own.
    |
    | In the editor a callout is also reachable as ':::warning ' at the start of a line,
    | the spelling documentation generators use. That input rule accepts any well-formed
    | name, since the extension is loaded as a plain file and is never told what a
    | particular field offers.
    */
    'callouts' => [
        'enabled' => true,
        'variants' => ['note', 'tip', 'warning', 'danger'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Language of a passage
    |--------------------------------------------------------------------------
    | Marks a phrase as being written in another language, as `<span lang="fr">`. WCAG
    | 3.1.2 asks for it and a screen reader needs it: without it a French title inside a
    | German paragraph is read out in German.
    |
    | A mark rather than an attribute on the block, because the requirement is about a
    | *passage* — usually a phrase inside a sentence, which a `lang` on the paragraph
    | cannot say. `lang` is on the sanitiser's safe list, so nothing has to be allowed.
    |
    | Registered but nowhere in the shipped layout, the same way the typeface picker and
    | striking out are: most documents never quote a foreign phrase, and a bar should not
    | carry a control for something most of its readers will never do. Where it belongs when
    | a project does want it is the bar over a selection - marking a passage starts with
    | selecting one - so add `'language'` to `text_toolbar_buttons`:
    |
    |   'text_toolbar_buttons' => ['styles', 'bold', 'italic', 'underline', 'link',
    |                              'textColor', 'textBackground', 'language'],
    |
    | The token works in the main `toolbar` array too, and per field through
    | `->textToolbarButtons([...])`.
    |
    | `values` may be written either way round — `['fr' => 'Français']` or `['fr']`. The
    | labels are endonyms on purpose: a language is best named in itself, which is the name
    | somebody picking it out of a list recognises. Codes are lowercased throughout, since
    | `lang` is case-insensitive by specification. Per field: `->languages()` and
    | `->languageOptions([...])`.
    */
    'languages' => [
        'enabled' => true,
        'values' => [
            'en' => 'English',
            'de' => 'Deutsch',
            'fr' => 'Français',
            'es' => 'Español',
            'it' => 'Italiano',
            'la' => 'Latina',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | List properties
    |--------------------------------------------------------------------------
    | The marker a list draws, the number it starts counting at and whether it counts
    | backwards — TinyMCE's `advlist`. All three ride in the attributes HTML already has
    | for them (`type`, `start`, `reversed`), which are on the sanitiser's safe list, so a
    | list keeps its numbering on the page with no stylesheet and nothing allowed.
    |
    | The controls are a panel in the bubble that appears while the caret is in a list,
    | rather than a button on the bar: they mean nothing anywhere else in a document.
    |
    | Off means the schema is not declared on either side, so a stored list keeps its
    | markup in the database and loses it on the next save. Per field: `->listProperties()`.
    */
    'list_properties' => true,

    /*
    |--------------------------------------------------------------------------
    | Special characters
    |--------------------------------------------------------------------------
    | The picker behind the 'characters' tool: dashes, typographic quotation marks,
    | currencies, mathematics, arrows, marks, and accented and Greek letters. Characters
    | are inserted as plain text, exactly like emoji, so switching the picker off leaves
    | every one already written where it is. Per field: `->characters()`.
    |
    | It shares its popup with the emoji picker, so only one of the two is ever open.
    */
    'characters' => true,

    /*
    |--------------------------------------------------------------------------
    | Changing the case of a selection
    |--------------------------------------------------------------------------
    | Sentence case, lower case and UPPER CASE, plus `Shift+F3` to walk the three. Nothing
    | about it is stored - a raised letter is a letter - so switching it off later leaves
    | every word already changed exactly as it is.
    |
    | Not on the bar and not in the overflow menu: this is a tool most documents never need,
    | and 'more' is finite. What is shipped is the shortcut and the means to place it - add
    | the three names to 'more', or the 'textCase' token to 'toolbar' or
    | 'text_toolbar_buttons', and it is there.
    |
    | Per field: `->textCase()`.
    */
    /*
    |--------------------------------------------------------------------------
    | Format brush
    |--------------------------------------------------------------------------
    |
    | The brush from Word, TinyMCE's permanent pen: pick the formatting up at one
    | passage and put it down on another. One click arms it for a single stroke,
    | a second keeps it armed, a third puts it away.
    |
    | It carries character formatting only - the marks this field's own schema
    | declares, minus three. A code run takes every other mark off with it, a link
    | is a destination rather than a look and carries an anchor that has to stay
    | unique, and a language says what a passage *is* rather than how it looks.
    |
    | Ships off. Most documents are written once rather than matched to a passage
    | somewhere else, and a brush is a mode - it changes what the next click does,
    | which is a thing to hand somebody deliberately. Switching it on is this line
    | plus a place for the button: `formatBrush` on the bar, in the selection
    | bubble or in `more`. Per field: `->formatBrush()`.
    |
    */

    'format_brush' => false,

    'text_case' => true,

    /*
    |--------------------------------------------------------------------------
    | Typography while typing
    |--------------------------------------------------------------------------
    | Straight quotes become the ones the language uses, three dots become an ellipsis, and
    | two hyphens become that language's dash. None of it is stored as anything but
    | characters, so switching it off later leaves every quotation already written as it is.
    |
    | Which characters are right is a question about a language and not about typography in
    | general: German opens with `„` and closes with `“` - the shape English uses to open -
    | and sets the shorter dash. Shipped for the two languages this package is translated
    | into; anything else falls back to the English convention, and a project whose language
    | is not here describes it under 'languages' rather than accepting the guess.
    |
    | The language is the application's locale unless the field says otherwise, which it can:
    | a German site may hold one field of English copy.
    |
    | Shipped OFF, unlike most of what this package adds. Everything else here gives a field
    | something it can do; this one changes what somebody typed, and what it changes ends up
    | in the database. The two mistakes are not the same size: switching it on costs a line
    | of configuration, while switching it off after the fact does not un-write the quotation
    | marks already stored. An editor may well expect a word processor's behaviour - the
    | developer installing a rich text field does not expect it to rewrite characters.
    |
    | Per field: `->typography()`, `->typographyLanguage('en')`.
    */
    'typography' => [
        'enabled' => false,
        'languages' => [
            // 'fr' => ['open' => '«', 'close' => '»', 'openSingle' => '‹',
            //          'closeSingle' => '›', 'dash' => '—'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Livewire's nesting limit
    |--------------------------------------------------------------------------
    | The editor entangles a document, so the path to a word is the path through it: text
    | inside a list item is eleven segments deep, and Livewire ships with a cap of ten. The
    | result is a 500 on the first keystroke inside the first list, naming neither this
    | package nor the editor.
    |
    | Raising the cap is an installation step - see the README. This is the check that says
    | so before anybody types, rather than after. A number asks for that depth instead of the
    | default; `false` switches the check off for a field that will never hold a list.
    |
    | Per field: `->nestingCheck(false)` or `->nestingCheck(16)`.
    */
    'nesting_check' => true,

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
    |
    | `float` puts the two side buttons on that strip, which let the text run past the
    | picture instead of starting below it. Pressing the side a picture is already on
    | takes the float off again, so two buttons cover three states. Per field:
    | `->imageFloat()`.
    |
    | `float_gap` is the space written between the picture and the text beside it, as a
    | CSS length. It travels in the stored markup, because the page a document ends up on
    | has not loaded this package's stylesheet and a floated picture with no gap has the
    | text touching it. Set it to null to write the bare `float` and draw the gap in your
    | own stylesheet.
    |
    | `decorative` puts a switch on the image bar for a picture that carries nothing worth
    | describing — a divider, a texture, a flourish. It writes an empty `alt` together with
    | `role="presentation"`, and the pair is the point: an empty `alt` on its own is what a
    | checker has to report as a description somebody forgot.
    |
    | Off, because that sentence is the whole of what it buys, and the checker it buys it
    | from ships off as well. Switch it on beside `accessibility.enabled`, which is where it
    | pays; on a field without the check it is a button whose effect nobody ever sees. Note
    | that a field with this off does not know the attribute, so a picture marked elsewhere
    | loses the mark when that field saves — the same bargain every switch here makes.
    | Per field: `->imageDecorative()`.
    |
    | `link` lets a picture be given an address to point at, rendered as an `<a>` around it
    | — and around the picture inside a `<figure>` where there is a caption, since a caption
    | is text about the picture rather than part of what is being linked. Per field:
    | `->imageLink()`.
    |
    | `dimensions` writes the size a picture was measured at on upload onto the picture as
    | it is inserted, so a browser leaves the right hole for it and the article below stops
    | jumping when it arrives. Per field: `->imageDimensions()`.
    |
    | It is on by default and there is one thing to know before it is left that way:
    | Filament renders `width` as an inline `style` as well as an attribute — the pair this
    | package's own resizing drags — so the measured size is also the displayed size. Pages
    | carrying the usual `img { max-width: 100%; height: auto }` gain the aspect ratio for
    | nothing. A page that caps the width and lets the height stand gets a squashed picture,
    | and should turn this off.
    |
    | `loading` is the hint written onto an inserted picture: 'lazy', 'eager' or null for
    | none. Null on purpose — a field cannot know where on a page its pictures land, and
    | 'lazy' on the one above the fold delays the largest contentful paint rather than
    | helping it. Per field: `->imageLoading('lazy')`.
    */
    'images' => [
        'resizable' => true,

        'toolbar' => true,

        'float' => true,

        'float_gap' => '1rem',

        'decorative' => false,

        'link' => true,

        'dimensions' => true,

        'loading' => null,
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
        // Whether the editor refuses input past `maxLength()` as well, instead of only
        // letting the save be refused over it. A setting rather than an assumption: a
        // comment box wants to block, an article wants to warn.
        //
        // Off by default, because refusing a keystroke is the more surprising of the two
        // and a project should ask for it. What is refused is growth past the limit and
        // nothing else - a document that is already too long, from before the limit existed
        // or from an import, still opens and can still be shortened.
        //
        // The limit held is `maxLength()` and not `character_count.limit`: only the first
        // is a rule the save is checked against. Per field: `->enforceMaxLength()`.
        'enforce' => false,
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
        'indent' => 'arte-indent-increase',
        'outdent' => 'arte-indent-decrease',
        'task_list' => 'arte-task-list',
        'blockquote' => 'arte-message-square-quote',

        // The callouts: the circled i for the whole family, then what each kind is.
        'callouts' => 'heroicon-o-information-circle',
        'callout_note' => 'heroicon-o-information-circle',
        'callout_tip' => 'heroicon-o-light-bulb',
        'callout_warning' => 'heroicon-o-exclamation-triangle',
        'callout_danger' => 'heroicon-o-shield-exclamation',
        'image' => 'heroicon-o-photo',
        'embed' => 'heroicon-o-film',
        // The date family: the calendar with its days as the sign for the whole thing, and
        // a bare calendar and a clock for the two options under it.
        'date_time' => 'heroicon-o-calendar-days',
        'date_time_date' => 'heroicon-o-calendar',
        'date_time_time' => 'heroicon-o-clock',
        'text_color' => 'arte-letter-a',
        'text_background' => 'arte-highlighter',
        'color_custom' => 'arte-palette',
        'more' => 'heroicon-o-ellipsis-horizontal',
        'tools_menu' => 'heroicon-o-wrench-screwdriver',
        'source_code' => 'heroicon-o-code-bracket',
        'statistics' => 'heroicon-o-chart-bar',
        'preview' => 'heroicon-o-eye',
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

        // The language a passage is written in, and the way back to the language of the
        // page. The globe with letters on it is the sign for "this is in another one";
        // the cross is the same cross every other clearing control in here uses.
        'language' => 'heroicon-o-language',
        'language_none' => 'heroicon-o-x-mark',

        // The two list panels, each carrying the list it belongs to rather than a
        // wrench: the bubble it sits in has one button, so that button has to say which
        // kind of list is being talked about.
        'list_bullet' => 'heroicon-o-list-bullet',
        'list_ordered' => 'heroicon-o-numbered-list',

        // The special characters picker and its tabs. Drawn icons rather than a
        // representative glyph, for the reason the emoji tabs use them: a row of eight
        // characters reads as things to pick rather than as the chrome around them.
        'characters' => 'heroicon-o-hashtag',
        'characters_close' => 'heroicon-o-x-mark',
        'characters_recent' => 'heroicon-o-clock',
        'characters_punctuation' => 'heroicon-o-chat-bubble-oval-left',
        'characters_currency' => 'heroicon-o-banknotes',
        'characters_math' => 'heroicon-o-calculator',
        'characters_arrows' => 'heroicon-o-arrows-right-left',
        'characters_symbols' => 'heroicon-o-sparkles',
        // The letter this package already draws, put to a second use: the Latin tab is
        // the one holding letters.
        'characters_latin' => 'arte-letter-a',
        'format_brush' => 'heroicon-o-paint-brush',
        'text_case' => 'arte-case-sensitive',
        'text_case_sentence' => 'arte-case-sensitive',
        'text_case_upper' => 'arte-case-upper',
        'text_case_lower' => 'arte-case-lower',
        'characters_greek' => 'heroicon-o-academic-cap',

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
        'image_float_left' => 'arte-image-left',
        'image_float_center' => 'arte-image-center',
        'image_float_right' => 'arte-image-right',

        'image_decorative' => 'heroicon-o-eye-slash',

        'image_link' => 'heroicon-o-link',
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
    | Render cache
    |--------------------------------------------------------------------------
    | Defaults for `AdvancedRichContentRenderer::cached()`, which is off unless a render
    | asks for it. Turning a document into HTML is a parse, a walk and a sanitiser pass,
    | and an article printed to a thousand readers pays for all of it a thousand times.
    |
    |   'ttl'     how long a rendered document is kept, in seconds. null keeps it until
    |             something clears the store. Capped automatically where the markup holds
    |             temporary URLs, which expire sooner than any of this.
    |   'store'   which cache store to use. null is the application's default.
    |   'prefix'  what every key starts with, so a store can be swept by pattern.
    |
    | The key covers the content and the renderer's configuration — but not what a closure
    | closes over, and not what a mention provider will answer. Per render: `->cached()`,
    | `->cacheKey()`.
    */
    'render_cache' => [
        'ttl' => 86400,
        'store' => null,
        'prefix' => 'arte.render',
    ],

    /*
    |--------------------------------------------------------------------------
    | Excerpt
    |--------------------------------------------------------------------------
    | Defaults for `AdvancedRichContentRenderer::toExcerpt()`, the teaser it builds out
    | of a stored document — for a meta description, a card, a search result.
    |
    |   'characters'  how long the text may be. The ellipsis is added on top of it, the
    |                 way `Str::limit()` counts. 160 is what a search engine shows of a
    |                 meta description before it stops.
    |   'end'         what marks the cut. Nothing is appended where the text already
    |                 ends in a full stop, so the two marks never meet.
    |
    | The cut falls on a word boundary. Per call: `->toExcerpt(200, ' …')`.
    */
    'excerpt' => [
        'characters' => 160,
        'end' => '…',
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
