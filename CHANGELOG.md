# Changelog

All notable changes to `filament-advanced-rich-editor` will be documented in this file.

## Unreleased

### Added

- Where a picture sits: three buttons on the image toolbar - left, centre, right. Left and
  right let the text run past it, which is the oldest thing anybody has ever asked an editor
  for and the last piece of laying a picture out that was missing; the size, the rotation and
  the caption were all already there. Centre is not a float and cannot be one - CSS has no
  way to run text down both sides of a block - so it is what every editor means by centre:
  the picture on its own line, in the middle, with the text above and below. Pressing the
  placement a picture already has takes it off, so three buttons cover four states; the
  callouts already work that way. The button of the current placement is drawn active, and
  that had to be spelled out: Filament decides active by asking `editor.isActive(<the tool's
  name>)`, which only ever recognises a node or a mark, and a placement is a global attribute
  on the image node. The placement rides in the inline `style` like the rotation does,
  because that is what survives the sanitiser, and it is whitelisted to three words before it
  is written - nothing in the stack validates CSS. The gap beside a floated picture is
  written with it, since the page a document lands on has not loaded this package's
  stylesheet and a bare float has the words against the frame; `images.float_gap` sets it,
  and null writes the bare `float` for a project that would rather draw the gap itself. A
  captioned picture moves its placement out to the `<figure>`, because placing a picture
  inside a block places it within the block rather than placing the block. Inside the editor
  the node view's outer box is placed instead of the picture, which is the element a
  paragraph actually lays out. Per field: `->imageFloat()`; project-wide: `images.float`

### Fixed

- A highlighted code block no longer loses half its syntax inside prose styles. The colour
  was written once, on the `<pre>`, and everything inside took it by inheritance - which
  loses to any rule naming `code` directly. Filament's own prose styles do exactly that
  (`.fi-prose code { color: var(--prose-code-color) }`), and so does Tailwind's typography
  plugin. In a dark panel that drew white text over the light theme's white background, so
  every token the theme gives no colour of its own - the brackets, the commas, the spaces -
  disappeared while the coloured ones stayed. It reads as a highlighter that swallowed the
  punctuation. The `<code>` now carries `color: inherit`, which beats a stylesheet and still
  follows the `<pre>` when a project swaps a pair of themes over

- Inside a Filament panel, a code block given a pair of themes now swaps to the dark one on
  its own. The swap was documented and left to the project, which is right for a front end
  and wrong for the one place where "dark mode" has a single meaning: the package stylesheet
  is registered with Filament and loads nowhere else, so it ships the rule

- Seven things stopped disappearing from a plain render of a stored document, and it was one
  bug found seven times: an extension arrived only with the plugin that puts its button on
  the toolbar, so `AdvancedRichContentRenderer::make($article->content)->toHtml()` - the call
  every front end makes - dropped it without a word. A task list came back as an ordinary
  bullet list with every tick gone; a font size, a typeface, a line height, a highlight, a
  writing direction and an image rotation were simply not there. The page just looked plainer
  than the editor did, and nothing said why. All seven are declared unconditionally now, on
  the reasoning this renderer already states four times over for the anchors, the embeds, the
  callouts and the captions. A field's own plugins still win where they are handed over -
  they carry that field's configuration. `RenderCompletenessTest` is the guard: a feature
  added without a line in the renderer fails it

- A font size can no longer carry a second CSS declaration out of the document and onto the
  page. The size was interpolated into a `style` attribute with no check at all, and it does
  not only arrive through the parser - the document the browser submits carries it verbatim
  into the PHP editor. A size of `1px; position: fixed; inset: 0` rendered as exactly that,
  and Filament's sanitiser passes `style` through untouched, so it became an overlay over
  the page. It is whitelisted now to a number and an optional CSS length unit, which is the
  pattern Filament's own `ImageExtension` uses on a width and a height - and the guard every
  sibling that writes into `style` already had: the typeface, the highlight, the spacing and
  the rotation

- A named style stays on a block the picker was narrowed away from. The browser half
  declares the style attribute over all five block types; this half declared it only over
  the ones the configured styles happened to name, so a project that narrowed a style to
  paragraphs lost it the moment somebody styled a paragraph and then turned it into a
  heading - the editor kept it on screen and the save threw it away. Narrowing says where
  the picker may offer a style, not that the same words stop being a lead once they are a
  heading. Which classes count is unchanged: a class the project never declared is still
  dropped

- A turned picture stays turned on a plain render of a stored document. The rotation
  attribute was only declared where `ImageResizePlugin` was handed to the renderer, so
  rendering an article without naming the plugin quietly straightened every turned picture
  in it. `AdvancedRichContentRenderer` declares it unconditionally now, on the same
  reasoning as the anchors, the embeds and the callouts: a renderer that has to be told is
  one that drops it the day somebody forgets to say so

- `toText()` no longer breaks a sentence apart at a link, a bold word or a mention. TipTap's
  text serialiser puts its block separator between *any* two children rather than between
  blocks, so `<p>Hallo <strong>Welt</strong>!</p>` came back as three lines, two of them one
  word long - the shape a search index and an excerpt both fall over. Adjacent pieces of
  text are joined into one before serialising, which is what the mention pass already did
  for the sentences it touched

- `toText()` spells entities out instead of handing back the escaped text the serialiser
  produces. Escaping is right on the way into markup and wrong in the one method that
  promises there is none: an index holding `Tom &amp; Jerry` does not answer a search for
  Tom & Jerry, and a meta description built on it says the entity out loud. Whoever prints
  the result is printing text, and escaping text for a page is `{{ }}`'s job

## 1.2.0 - 2026-08-27

### Added

- Callouts: the note, tip, warning and danger boxes every documentation site has. One node
  with the kind as an attribute rather than four nodes, so turning a note into a warning is
  a change of colour rather than a delete and a rewrite, and pressing the entry for the kind
  you are already in takes the box off. They hold blocks rather than text, so a callout can
  carry a list or a second paragraph - which is the difference between an infobox and a
  coloured sentence. The `'callouts'` token puts one dropdown on the shipped toolbar next to
  the quote, the same kinds are in the slash menu under *Style*, and typing `:::warning ` at
  the start of a line makes one as well. `data-type="callout"` says it is one and a class
  says which kind, because those are the two attributes Filament's sanitiser keeps - so the
  colour survives the trip to a page rather than sitting unused in the database. A project
  can name kinds of its own: the tool, the menu entry and the class are all built from the
  name, and three custom properties on one CSS rule are the whole colour. Per field:
  `->callouts()` and `->calloutVariants([...])`; project-wide: the `callouts` config key

- The language of a passage, as `<span lang="fr">`. A mark rather than an attribute on the
  block, and that is the whole feature: WCAG 3.1.2 is about a *passage*, usually a phrase
  inside a sentence, and a `lang` on the paragraph cannot say "these three words are
  French" - which is exactly the case a screen reader gets wrong without it. `lang` is on
  the sanitiser's safe list, like `dir`, so nothing has to be allowed. The dropdown sits in
  the bar over a selection rather than on the toolbar, because marking a passage starts with
  selecting one - but it is registered rather than shipped there, the same bargain the
  typeface picker and striking out make: most documents never quote a foreign phrase, and a
  bar should not carry a control for something most of its readers will never do. Name
  `'language'` in `text_toolbar_buttons` and it appears. The mark is declared either way, so
  a passage marked elsewhere survives a save on a field with no button for it. Its first
  entry is the way back to the language of the page, and codes are lowercased throughout
  since `lang` is case-insensitive by specification. Per field: `->languages()` and
  `->languageOptions([...])`

- What a list is told about itself: the marker it draws, the number it starts counting at
  and whether it counts backwards - TinyMCE's `advlist`. All three ride in the attributes
  HTML already has (`type`, `start`, `reversed`), which are on the sanitiser's safe list. The
  marker is written twice and both are load-bearing: the attribute is what both halves parse
  and what a bare browser honours, and an inline `list-style-type` is what survives a
  stylesheet that sets one - Filament's own prose styles do, so the attribute alone is drawn
  with numbers. It is read back out of that CSS as well, which is what makes a list pasted
  from Word or Google Docs arrive numbered the way it looked. The panel offers Default and
  then the markers that differ from it - the ones a browser already draws unasked, `1` and
  `disc`, would be a second button drawing exactly what Default draws. Both are still
  accepted, so a document already carrying one keeps it. The controls are a panel in the
  bubble that appears while the caret is in a list, not a button on the bar: they mean
  nothing anywhere else in a document. That bubble is shown by a wider rule than Filament's,
  the way the image one already is: a toolbar that only exists while the *editor* has the
  focus takes itself off the moment a marker is clicked - the click focuses the button, and
  the bubble removes its element, and the panel and the state saying it was open go with it.
  So it counts as wanted while the focus, or the press on its way to it, is inside the panel
  as well; it still goes away when either lands anywhere else. Per field:
  `->listProperties()`

- A special characters picker: dashes, typographic quotation marks, currencies, mathematics,
  arrows, marks, and the accented and Greek letters a keyboard cannot reach. The emoji
  picker's twin, sharing its popup - the two do the same thing to the same kind of thing, so
  the popup moved into a file of its own and both are now adapters over it. Only one of them
  is ever open. Nothing touches the schema: a dash is a character, inserted as text, so
  switching the picker off later leaves every one already written where it is. Per field:
  `->characters()`


- A `'tools'` toolbar token: a second overflow for what a field does rather than what it
  writes - searching, the accessibility check, the source view, the shortcut list - so a
  corner that has grown past a few buttons can be `['tools', 'fullscreen']` instead. Named
  rather than a second set of three dots, because two menus both called "More" on one bar
  are two doors with the same sign and different rooms behind them. Not in the shipped
  toolbar as `['tools', 'fullscreen']`, so the corner keeps its shape: the check and the
  source view drop out of the menu while they are off and land in it when they are switched
  on, rather than becoming a third and fourth icon beside it. The cost is that finding is one
  click deeper on a field that switched nothing on - `Ctrl+F` is unaffected. A project that
  would rather have the buttons names them individually. `'tools_menu'` in the config, or
  `->toolsMenu()` per field

- An accessibility check, shipped off and switched on with `->accessibility()` or in the
  config, which then puts it on the toolbar between finding and the source view. It asks six
  questions - an image with no alt text, a link with nothing in it, a link whose whole text
  is "click here", a heading level jumped over, a table whose first row is ordinary cells,
  and a set text colour that cannot be read on the page it is going to - and every row in
  the panel selects the thing it is about, which is the whole reason it runs in the editor
  rather than as a report after saving. Heading levels are checked for jumps and never for
  where a document starts, since an article whose page already carries the `<h1>` starts at
  two. A weak link text has to be the whole text: "click here for the report" says what it
  is and is left alone, and the phrases follow the locale, because "click here" is a fact
  about English rather than about the web. Contrast is the one rule with an assumption in
  it and it is stated rather than hidden: a colour is measured against
  `accessibility.background`, white unless a project says otherwise, or against the
  background set on the same words, and headings and text of 24px and up are held to WCAG's
  easier level for large text. The finding says the ratio it got and the ratio it needed.
  That last rule is why it is off by default rather than on: it is measured against a page
  this package has to be told the colour of, so shipped on, every project whose pages are
  not white would be handed findings that are wrong. Nothing about any of it is stored

- A draft of what is being written, kept in the browser's own storage, so a reply that comes
  back as an expired session or as a 500 is not an afternoon's work. It is not a save:
  nothing about it reaches the application, and a draft that is never restored is never
  anything. Three parts, because they fail at three different moments - the document is
  written to storage once typing stops, a bar above the document offers a draft that says
  something the page does not, and closing the tab with changes the server has not been told
  about raises the browser's own question. The draft is found again by a key built from the
  Livewire component, the model, the record and the field, hashed, plus the path of the page,
  which PHP cannot reliably know because to Livewire every request looks like the same
  endpoint - and nothing in it is in the clear, since it is a key anything on the origin can
  read. Restoring is a ProseMirror transaction rather than a call into TipTap, which is what
  makes the restored document reach the state the form submits and what makes restoring one
  step in the undo chain. A draft is dropped as soon as the document on screen says the same
  thing, when the form is submitted, when it is discarded and when it is older than
  `autosave.ttl` - a day by default, because this is content sitting in a browser's storage
  on whatever machine somebody was working on. `->autosave(false)` for a field that should
  not keep one, `->autosaveWarnOnLeave(false)` for the question alone

- A grip in the margin of the block under the mouse, and a plus beside it. Dragging the grip
  moves the block; clicking it selects the block, which is what puts the floating toolbar on
  it. Almost none of the moving is this package's: ProseMirror already knows where a slice
  may land, draws the line saying so and makes the move one step in the undo chain, so what
  is added here is the part that had no home anywhere else, which is something to take hold
  of. The plus starts a new block under the one being hovered and opens the slash menu on
  top of it, so what it offers is everything that could go there rather than a paragraph -
  done by typing the slash character into the new block, which is the same event as somebody
  pressing the key, down to backspacing out of it closing the menu again. Only the top level
  of the document gets a handle, so the grip on a list takes the list rather than the item
  under the mouse: a list item may only live inside a list, and the honest version of that
  feature is a drag that refuses more often than it works. A field that has a handle is
  given a wider left margin to keep it in, because Filament leaves twenty pixels there and
  two controls need fifty - fields without the handle are laid out exactly as Filament lays
  them out. It carries `fi-not-prose`, Filament's own way out of the typography and the same
  one its floating toolbars take: the handle is drawn inside `.fi-prose`, whose rule for two
  adjacent elements puts a top margin on the second of them, which is the grip - so without
  it the two controls sat eight pixels apart on the vertical. Nothing about it is stored, so
  `->dragHandle(false)` changes nothing already written

- The help dialog lists `Ctrl+Shift+V`, which takes the text on the clipboard and none of
  its markup. Nothing in this package binds it and nothing needs to - a browser reads it as
  paste-and-match-style and hands over the plain half on its own, and where one keeps that
  key for itself ProseMirror sees the shift and takes the text half anyway - so it works
  whether or not a field cleans its pastes. It is listed because it is the way out of a
  paste that arrived wearing more than it should, and until now the only people who knew it
  was there were the ones who guessed

- A paste from Word and Google Docs arrives as a document rather than as somebody else's
  document. Word does not put a paragraph on the clipboard: it puts a paragraph, the
  stylesheet it was drawn with, a handful of tags no browser has heard of, and a list that
  is not a list. What survives now is the structure - headings, paragraphs, lists, tables,
  links, images, bold, italic, underline, struck-out text, superscript and subscript, and
  the alignment - and what stays behind is the typography, because this package parses
  `font-family`, `font-size`, `color` and `line-height` into marks of its own: a declaration
  left standing is not cosmetic noise the next save drops, it is Calibri 11pt in black, in
  the document, for good. Two properties are kept because they are structure wearing a style
  attribute rather than typography - `text-align` and the `aspect-ratio` an embed is drawn
  at - and an element that *is* its `src`, a frame or a player, keeps that for the same
  reason; `->pasteKeepStyles()` names anything else a project does want to survive. A bulleted list in a Word paste is a run of paragraphs each carrying `mso-list`
  in its style and drawing its own bullet as text, which is the one thing that cannot be
  repaired later - by the time it reaches the document it is twelve paragraphs starting with
  a dot - so the run is put back together, nested by the level in the style, with bulleted
  or numbered read off the marker: a number always brings its `.` or `)` along, which is
  what keeps Word's second-level bullet, the letter `o` in Courier, from turning into a
  lettered list. The order of the two halves is the whole trick, because bold in Google Docs
  is `font-weight:700` in a style attribute and nowhere else: the styles become `<strong>`,
  `<em>`, `<u>`, `<s>`, `<sup>` and `<sub>` before anything is dropped, since stripping
  first gives a paste that is right in structure and flat in meaning, which is the one
  failure nobody notices until it is published. A `<style>` block that came along is removed
  rather than kept, because ProseMirror walks into an element it has no rule for and keeps
  the text it finds - three hundred words of CSS in the article. Ids are kept where somebody
  chose them and dropped where a generator made them up, `data-*` is never dropped, and a
  copy from another editor is left exactly as it is: it carries `data-pm-slice`, it is
  already the shape the document wants, and a field that quietly took the colours off
  content on its way to the field beside it would be worse than one that kept Word's fonts.
  The cleaning happens before ProseMirror parses the markup, so a drag and drop of the same
  content is cleaned the same way. Nothing about it is stored, so `->pasteCleanup(false)`
  changes the next paste and no document already written

- Finding and replacing inside a field. `'find'` sits at the end of the toolbar and `Ctrl+F`
  opens the same small window while the caret is in the editor, which was the most conspicuous thing
  every commercial editor could do and this one could not. Every hit is marked in the text
  and the one being looked at is marked differently, because a search that highlighted all
  twelve the same way answers "is it in here" but never "where am I". Two keys open it and each stands
  for one of its two states rather than for a change to whichever it is in: `Ctrl+F` with
  the replacing row put away, `Ctrl+Alt+F` with it out - and `Ctrl+H` alongside that second
  one for the muscle memory Word and Google Docs built, which never arrives on a Mac because
  `Cmd+H` hides the application before the page sees it, the same pair and the same reason
  as VS Code. Pressed again they repeat themselves rather than toggling: the usual reason for
  pressing `Ctrl+F` a second time is that something else is selected now, and the second
  press picks it up the way the first one did. Only the button in the bar shows and hides
  the replacing row. The search runs on the document's text laid end to end rather than on
  its tree: `he<strong>ll</strong>o` is three text nodes and one word, and a search walking
  the tree would find neither. Between two blocks, and either side of an image or a line
  break, sits a character nobody can type, which is what keeps a hit from running from the
  end of one paragraph into the start of the next without a special case anywhere. Whole
  words are counted in letters rather than in ASCII, so `Mü` does not match inside `Müller`,
  and the query is text rather than a pattern. Replacing every hit is one transaction and
  therefore one step in the undo chain, worked back to front so that replacing one does not
  move the ones still to come, and picking up after the replacement rather than at it - so
  replacing `cat` with `cats` moves on instead of replacing forever. The caret is never
  moved while the bar is open: a hit is scrolled to, not selected, so the bar keeps what is
  being typed into it. The bar is a window hanging off the body, one row tall and draggable
  by the grip on its left, rather than a row inside the field - Filament lays the editor's
  body out as a two-column row from `2xl` up, so a bar living in there is a column and takes
  half the editor on a wide screen. Staying on the screen, being dragged, closing on Escape
  and closing on a click that is not in the editor are in `floating-panel.js`, on its own
  because the emoji picker wants the same four things and the half that drifts silently
  between two copies is the geometry - a window placed past the edge cannot be dragged back,
  and nothing says so until somebody opens it on a narrow screen. The picker has not moved
  onto it yet. Nothing is stored, so `->find(false)` takes the button and the keys away and
  leaves every document written with it alone

- `->stylePreview()` marks the text a style sits on, for the projects that have not written
  their own rules yet. Off by default, and that is the same reasoning the empty styles list
  follows rather than an oversight: a style is a set of the project's classes, none of which
  resolve in an admin panel that has never loaded the front end's stylesheet, so the package
  can show that a style is set and never what it looks like. Inventing an appearance for
  content it knows nothing about is how an editor ends up lying about the page. Turned on,
  a styled block gets a rule down its side and a styled run of text a dotted underline - a
  stopgap that a project's own `[data-style]` rules override, so leaving it on while those
  are written costs nothing. The manual now spells out those rules, which are the real answer

- Every dropdown in the package turns upwards when there is no room for it below - the
  colour pickers, the font and size pickers, the image panel and the styles picker. They all
  hang off their trigger with `position: absolute`, which is right in the middle of a page
  and wrong at the foot of one, and the bar over a selection turned that from an edge case
  into the normal one: it hangs below the text it belongs to, so its menus start lower than
  any menu on the toolbar ever does

  Two edges cut a menu off and only one of them is the window. `.fi-fo-rich-editor-content`
  scrolls its own overflow, so a menu opening low in the editor is clipped by the editor -
  and raising `z-index` does nothing about either, because a menu reaching past the bottom of
  a scrolling ancestor is cut off by geometry and paint order has no say in it. The room is
  measured against whichever edge comes first, and measured once the menu exists rather than
  guessed from a maximum height, since these lists are as long as a project's configuration
  makes them. `OpensAwayFromTheEdge` holds it once for all five

- A toolbar over selected text: the project's styles, bold, italic, underline, strike, link
  and colour, right at the selection. Filament ships one of these bars for a selected image
  and one for a table cell, and this is the third - the one people reach for most, and the
  reason the styles picker is worth having at the selection rather than only at the top of
  the field. It takes the same tokens the main toolbar does, so a feature switched off on the
  field takes its button out of the bar too. `->textToolbar(false)`,
  `->textToolbarButtons([...])`, and the `text_toolbar` config keys

  Keyed `'paragraph'` rather than `'text'`, which is not a naming choice: Filament's
  JavaScript treats that one key as a special case and shows its toolbar on a non-empty
  selection inside a paragraph, where every other key waits for a node to be active. A key
  called anything else would be drawn and never shown

- Named styles from the project's own design system, as a dropdown behind the `styles`
  token. This is the thing a Filament editor can do that a generic one cannot: an editor
  reaches the front end's classes without ever opening the source dialog. An entry is a
  label, a set of classes and a scope - `block` puts them on the paragraph or heading the
  caret sits in, `inline` on the selected text - and a block entry may name the types it
  applies to. One style at a time per scope, the way a heading level works; a style wanting
  two of the project's classes together is one entry holding both. Shipped empty, because an
  editor offering styles nobody designed is worse than one offering none. Per field with
  `->styles([...])`, and `->styles([])` takes the button away

  Stored as `data-style="<key>"` beside the classes, and both are needed: the classes are
  what the page uses, the key is what the next parse reads. Editing a style's class list in
  the configuration therefore updates the documents that already exist, where a document
  carrying only classes would keep the old ones until a save quietly dropped them. The
  sanitiser passes the classes through and drops the key, so it reaches the database and not
  the reader. Content pasted out of a rendered page is recognised by its classes alone

  The editor's own markup carries the key rather than the classes. Writing the classes there
  would produce text that looks styled in a panel that has never loaded the front end's
  stylesheet - markup that renders plain the moment it leaves. A project that wants the
  preview styles `[data-style="…"]` in its panel theme

- `->nullWhenEmpty()` stores an empty document as nothing rather than as the `<p></p>` TipTap
  always keeps, so a field that shows nothing on the page is also nothing in the record -
  which is what `@if($post->content)` and a `whereNull` both already assume. Off by default,
  and that is a decision about somebody else's database rather than a preference: a column
  that is `NOT NULL` without a default takes `<p></p>` and refuses a null, so flipping it for
  everyone would break a save that works today. `null_when_empty` in the config file turns it
  on for a project that knows its columns, and a field still wins over it in both directions

- The mention menu is this package's own, and its rows have room for a picture and a line of
  context under the name. Filament draws a suggestion as one line of text, which makes five
  people called the same thing five identical rows; `MentionRow` carries an avatar and a
  hint, `MentionProvider` extends Filament's and hands the rows over with `rows()` or from
  `getSearchResultsUsing()`, and a row without a picture is drawn with the initials of the
  name rather than a gap. On by default, `->mentionMenu(false)` or the `mentions.menu` config
  key gives Filament's own menu back, and nothing about what is stored differs either way -
  the same node, the same `data-id`, the same markup on the page
- The media browser's behaviour is tested. Fifty tests over paging, folders, filtering, the
  details panel, uploads, drag and drop and the numbers under a picture, run by Vitest in
  jsdom and wired into CI beside the PHP suite. This was the largest untested thing in the
  package: roughly six hundred lines that decide which page is asked for and what is
  selected after an upload


### Changed

- The README opens with what the field does that Filament's own does not, as a table rather
  than a list of claims. Every row names the stock behaviour beside this package's, each
  checked against `filament/forms` v5.7 rather than remembered, and links to the section
  that covers it. Five of the claims the list made were wrong: stock registers all six
  heading levels and a paragraph tool, ships a free colour picker behind
  `customTextColors()`, already offers alt text that reopens for correction, and registers
  four alignments rather than three

- The documentation is grouped rather than flat. Forty-five sections hung under one `##
  Usage`, which is why the plugin directory's own index listed eight entries for a file with
  eighty headings. They now sit under The toolbar, Blocks, Characters, Media and links,
  Typing instead of aiming and Getting it right, with a contents list above them and the
  comparison table above that - so the page a reader lands on opens with what the package is
  for rather than with its PHP version. No section moved out of the file and no anchor
  changed

- One table says which features ship on and which ship off, with the config key and the
  per-field method for each. Thirty sections never stated their default, and three - the
  drag handle, the drafts and the callouts - read as though they were off when they ship on


- The shipped toolbar is shorter by three. The typeface picker is registered but no longer
  on it: choosing a font in an article is the front end's business, and this package strips
  `font-family` out of a paste on exactly that reasoning - a bar offering the picker invites
  somebody to do by hand what the paste cleanup exists to undo. Name the `fontFamily` token
  to put it back. Striking out is out of the shipped layout altogether - not on the bar, not
  in the overflow menu, not in the bubble - and is still registered, so naming `'strike'` in
  any of those three lists brings it back; the language dropdown ships the same way. And the
  quote moved into the `more`
  menu: a quote is a thing you reach for occasionally rather than while writing, and the
  callout dropdown took the place it had - a note, a warning and a tip come up far more often
  than a pull quote does

- The shipped tools menu has an `'accessibility'` token in it, between `'find'` and
  `'sourceCode'`. It resolves to nothing while the check is off, which is how it ships, so
  no toolbar changes for anybody until the check is switched on - and switching it on is
  then the whole of what a project has to do. A project that published the config file
  already has it in `tools_menu` and needs to change nothing; naming `'accessibility'` in
  `toolbar` instead gives it a button of its own on the bar


- The source dialog is off by default. It hands an editor a way past every control the
  toolbar stands for - whoever types into that box writes whatever the schema will accept,
  and the toolbar stops being the answer to what a document may contain. Worth having where
  the people using it know HTML, not worth giving to everybody who installs the package. The
  `sourceCode` token stays in the shipped toolbar and resolves to nothing, so `->sourceCode()`
  or `source_code => true` is all it takes to get the button back

  **Upgrading:** a project that wants the button as before sets `source_code => true` in its
  config. Nothing about stored content changes either way

- The code block moved into the overflow menu, next to the inline code button it belongs
  with. The bar had grown to seven groups; this is the tool in the insert group that the
  fewest documents ever use

- The link moved out of the insert group and in with the marks, so the shipped toolbar now
  reads `bold, italic, underline, strike, link, textColor, textBackground` and the insert
  group is `lists, image, embed, table, blockquote, codeBlock`. A link is an annotation on
  selected text, the same as bold, and it sat between the image and the table only by
  habit - which meant the bar over a selection and the bar at the top offered the same
  buttons in a different order, two things to learn where there should be one

  **Upgrading:** nothing breaks, and nothing moves for anyone who configured their own
  `toolbar`. A project that took the shipped layout and wants the link back where it was
  puts `'link'` back into the insert group in its published config

- The bar over a selection carries the highlighter as well. It was the one thing the top bar
  offered on selected text and this one did not

- The shipped toolbar has a `styles` slot beside the heading levels. It removes itself while
  a project has named no styles, exactly as the font picker does when there are no fonts, so
  nothing changes for anyone until they configure one - and nobody who has not read the
  manual is left unable to find the feature

- The media browser is an Alpine component in `resources/js/media-picker.js` rather than an
  `x-data` attribute in the Blade view, and is loaded the way Filament loads its own fields.
  Nothing it does has changed - the move is what makes it testable at all, because an
  attribute cannot be imported. The view still owns what only PHP knows: the labels, the two
  settings, the entangled selection and the two calls that reach the editor. A published copy
  of the view keeps working, since the version somebody published carries its own behaviour

  **Upgrading:** run `php artisan filament:assets`. The browser is a registered asset now, and
  an asset that was never published is a 404 the field cannot report - the dialog opens empty
  and silent rather than showing an error. This is the step the installation instructions
  already ask for after every install and deploy; it is repeated here because this release is
  the first where skipping it is visible

- The README and the manual both end with what is planned and an invitation to report bugs
  and suggest changes. Both files carry it rather than one linking to the other, because the
  plugin directory renders only the manual and its readers are exactly the people worth
  hearing from
- Workflow actions are pinned to a commit SHA rather than a moving tag, and Dependabot keeps
  those pins and the Composer dependencies current on a weekly schedule with a seven day
  cooldown. A tag can be repointed at any commit by whoever owns the action; a SHA cannot
- The README is a README again. At 1909 lines it was a manual, nearly ten times the length of
  the other plugins in this account, so the whole of it now lives in `docs/documentation.md`
  and what is left is what a reader wants first: what the package is, how to install it, the
  Livewire step it cannot work without, and a short example. Split into one file rather than
  many on purpose - the Filament plugin directory renders a single Markdown file and builds
  its own navigation from the headings in it, so a README of links would leave that listing
  empty and send every reader away from it. `docs/documentation.md` repeats the requirements
  and the installation steps rather than linking back to them, so that a reader arriving
  from the plugin directory is not left without the Livewire setting the package needs


### Fixed

- Three statements in the documentation described a toolbar that no longer ships. The
  pinned corner was said to hold the source view, the fullscreen switch and the help
  dialog; it holds `['tools', 'fullscreen']`, with the other three one click inside the
  menu. `'help'` was said to sit at the end of the toolbar; it ships inside `tools_menu`.
  And the accessibility check named `toolbar` as the place a published config adds its
  token, where the shipped place is `tools_menu`. All three date from the release that made
  the tools menu the shipped corner, and all three would have sent a reader looking for a
  button that is not where they were told to look

- Three features could not be reached from the documentation alone. The `fontFamily` token
  is in none of the shipped lists, and the Fonts section walked a reader through configuring
  a font directory without ever saying the dropdown would not appear; the Colours snippet
  used `TextColor::make()` with no import, so it did not compile; and `->floatingToolbars()`
  and the four panel classes behind it - the only way to change what the image bar, the
  selection bar or the list bubbles hold - appeared nowhere in the file at all. Each now has
  the call that makes it work

- Seven public methods and classes were documented nowhere: `toUnsafeHtml()`,
  `normaliseSourceHtml()`, `measureCharacterCount()`, `DocumentContent::isBlank()`,
  `TableColumnWidths::apply()`, `ImageCaptions::apply()` and
  `CodeHighlighter::isAvailable()`. They are the escape hatches for importers, seeders,
  observers and anyone rendering outside `AdvancedRichContentRenderer`, and each now has the
  signature and the case it exists for

- The Livewire snippet in both the README and the documentation showed
  `'max_nesting_depth' => 32` without the `payload` array it belongs inside or the file it
  belongs in, so pasting it where a reader would paste it did nothing


- The slash menu and the mention menu keep their rounded corners once there is enough in
  them to scroll. A scrollbar is painted inside the border box and a `border-radius` does
  not clip it, so a panel that scrolled itself drew a straight bar across its own top and
  bottom right corners - the two corners the rounding was for. The shell and the scrolling
  are now two elements: the outer one keeps the border, the radius and the shadow and
  clips, the inner one scrolls inside its padding, and the bar sits clear of the curve
  whatever a platform decides a scrollbar looks like. `role="listbox"` moved with the
  scrolling, so the options are still held by the element that claims them

- `Ctrl+Shift+L` and `Ctrl+Shift+R` align a paragraph left and right, which is what the help
  dialog has been saying they do. TipTap's `TextAlign` binds those two keys to the alignments
  `left` and `right`, Filament configures the extension with `start` and `end` so that
  right-to-left content behaves, and `setTextAlign` answers an alignment it was not
  configured with by doing nothing at all. The dead half was the smaller problem: a shortcut
  handler that does nothing also returns false, which leaves the key to the browser - so the
  advertised way to align a paragraph right was a hard reload in Chrome and Firefox, with
  the unsaved draft still in the field. Centring and justifying were never affected, since
  `center` and `justify` are spelled the same on both lists

- The character and emoji pickers draw their rows at the top of a tab instead of spreading
  them over its height. The grid is a flex child filling the popup and a grid stretches its
  rows by default, so a tab holding two rows drew two rows a hundred pixels tall with the
  characters floating in the middle of each. It never showed on the emoji tabs, which always
  have more rows than fit

- A tab in either picker is now a square around its icon rather than a slab across the row.
  Stretched, the highlight on the active tab ran up against the icons either side of it,
  which reads as three tabs selected rather than one

- The options in a textual toolbar dropdown all have the same height again. The menu is a
  column flex box and Filament leaves its options shrinkable, so a list that ran past the
  height cap - this package's cap, since Filament sets none - did not scroll, it squashed,
  every row losing a few pixels. The
  overflow menu was doing exactly that and its rows were shorter than the ones in the menu
  beside it, which is the failure mode here nobody would think to look for. Options no
  longer shrink, and the cap moved past the longest list this package ships so that none of
  them scrolls out of the box

- A textual toolbar dropdown scrolls one way only. Filament sets no height and no overflow
  on that menu - the cap and the scrolling are this package's - and asking for `overflow-y`
  alone is asking for both, because the specification computes the other axis from `visible`
  to `auto` as soon as one of them is anything else. So the menu had a sideways scrollbar
  nobody wanted and nothing worth reaching sideways for. Not `scrollbar-gutter: stable`
  either: the browser already accounts for the scrollbar when it works out how wide a menu
  wants to be, so the gutter fixes nothing and charges every menu that does not scroll
  fifteen pixels of dead space on the right, with the highlight under the pointer stopping
  short of it

- An extension declared by both a plugin and the renderer is no longer applied twice. The
  field's TipTap editor is an `AdvancedRichContentRenderer` carrying the field's own plugins,
  and the renderer also declares several of this package's extensions unconditionally so that
  a stored document keeps its videos and its markings whatever a render was told. Those two
  lists overlapped, and `tiptap-php` applies both copies rather than letting one win - so a
  field with videos switched on wrote a stray `</div>` after every embed on every save,
  which a browser drops silently and a diff does not show. The renderer now keeps the first
  of any repeat, which is the plugin's: that is the instance carrying the field's
  configuration

- `required()` now rejects an empty document in every shape it comes in. An empty editor is
  not an empty value: TipTap keeps at least one paragraph in the document at all times, so a
  field nobody typed into reaches the validator as an array with three keys, and Laravel's
  `required` is happy with it. Filament rejects that exact shape and nothing else — press
  return once and the document has two empty paragraphs, which it lets through, and so are a
  stray space, a non-breaking space from a Word paste, a line break, the same document in
  its markup form, and a field holding nothing at all. `DocumentContent` states the rule the
  other way round and only once: a document is empty when it holds nothing but paragraphs,
  line breaks and whitespace, and every other node — a list, a heading, a rule, an image, a
  table, a custom block, one a project added — is content. An unknown node therefore counts
  as content, so the mistake this can make is letting an empty-looking document through
  rather than throwing away a document that had something in it. The same question is on the
  field as `hasContent()`

- A column somebody dragged wider stays wider on the page. Filament configures TipTap's
  table with `resizable: true`, so dragging worked and the width was kept in the document -
  it just never reached the reader. `ueberdosis/tiptap-php` writes it as `data-colwidth` on
  the cell, which is neither on the sanitiser's allow list nor anything a browser does
  something with, because CSS cannot read an attribute value as a width. The editor looked
  right, the page did not, and nothing said so. `TableColumnWidths` turns the widths into the
  `<colgroup>` ProseMirror itself draws while resizing, read off the first row the way
  ProseMirror reads them, with `table-layout: fixed` alongside so that a width is not a
  suggestion the browser drops as soon as the text is wider. A table nobody resized is left
  exactly as it was. Nothing about what is stored changes

- A record whose column holds an empty string opens instead of throwing. `text NOT NULL
  DEFAULT ''` is an ordinary column and a record nobody has edited yet holds exactly that,
  but Filament's state cast only guards against null: the empty string went to TipTap's DOM
  parser, which reached for a `<body>` that was never built and died with
  `DOMParser::getDocumentBody(): Return value must be of type DOMElement, null returned` - a
  TypeError out of a form that was only being rendered, naming a class the application has
  never heard of, and unrecoverable because hydration is what failed. The cast this package
  registers treats a blank string the way the one it extends treats null


## 1.1.1 - 2026-08-22

The test matrix never actually ran: there was no phpunit configuration in the repository, so
every leg died before reaching a test. With one added, it immediately found two bugs that a
green local suite could not see.

### Fixed

- Filament is required as `^5.7` rather than `^5.0`. `RichEditorTool::toggle()`, which the
  toolbar has always called, only exists from v5.7.0, so any install resolving an earlier 5.x
  fataled with a `BadMethodCallException` on the first form render
- The embed host sanitiser empties an `iframe src` it rejects instead of returning `null`.
  `null` is what the attribute sanitiser contract asks for, but only `symfony/html-sanitizer`
  v8 stops the chain there; on v7, which is what Laravel 11 and 12 ship, the `null` reached the
  next sanitiser for the same attribute, whose signature will not take it, so rendering stored
  content that carried a foreign iframe died with a `TypeError` rather than dropping one
  source. Nothing loads from an empty source, so what a page shows is unchanged
- The README header was duplicated, title and badges twice, with a dead image link between them

### Changed

- `phpunit.xml.dist` ships, and CI turns off Composer's advisory block for its own run so the
  Laravel 11 legs can resolve at all: `PKSA-mdq4-51ck-6kdq` covers `>=11.0.0,<12.0.0` with no
  fixed 11.x release, so the whole major is otherwise refused

## 1.1.0 - 2026-08-22

### Added

- `->mediaLibraryListView()` chooses which layout the browser opens on, per field. The README
  documented it as a field method from the start; it only ever existed on the internal picker,
  so the documented call threw
- The slash menu answers to `/youtube`, `/vimeo`, `/iframe` and `/einbetten` for video embeds.
  `embed` was the one command in the shipped list with no aliases at all
- **Mentions carry through to the rendered page.** The editor half is Filament's own and is
  untouched, but a rendered mention used to be a `data-type` and a `data-id` and nothing else -
  indistinguishable from any other span, and impossible to style, because Filament's sanitiser
  strips every other attribute a mention carries. It now renders `fi-arte-mention` plus a class
  naming the trigger it was written with (`fi-arte-mention-at`, `-hash`, and eight more), which
  is the only place that survives the sanitiser. Hand the renderer the same providers the field
  was given and `url()` links it and the label is the one the provider knows now, rather than
  the copy stored when it was typed
- `Mentions::in($content)` answers who a document mentioned: `->ids('@')` for the ids one
  trigger named, each of them once, `->grouped()` for all of them by trigger, `->all()` for
  every occurrence with the label as stored. It takes no providers, so a `saved()` hook that
  notifies the people named needs no configuration and cannot drift out of step with the field
- **A media browser behind the image button.** It opens the pictures that are already on the
  server, with uploading as the second tab, so the same image is not uploaded once per article
  that shows it. Picking one stores what an upload would have stored - the media UUID, or the
  storage path on a field without a media collection - so nothing is copied, one file backs
  any number of references, and content saved before the dialog existed is the same content as
  content saved through it. Size and rotation stay on the image node, never on the file.
  Works both for `->spatieMediaLibrary()` fields and for plain `fileAttachmentsDisk()` ones,
  where it browses real folders. `->mediaLibrary(false)` restores Filament's own dialog, and
  the browser replaces that one action rather than adding a second button, so the toolbar, the
  slash menu and re-opening an existing image all keep working untouched
- The pool is the collection the field uploads to, not the record in front of you - which is
  what the browser exists for: a picture uploaded for one article is the picture the next one
  wants, and uploading it again costs a second copy on disk for nothing. An article and a post
  sharing a collection share a library; separate collections stay separate. The pool is also
  what decides what a stored `data-id` may resolve to. `->mediaLibraryScope('model'|'record')`
  narrows it
- `->mediaLibraryUploadsTo()` gives new uploads a model of their own instead of hanging them off
  whichever record was open. A media row belongs to a model and its file path is built from the
  row's id, so an article that owns a picture takes it with it when deleted - which is fine
  until a second article reuses it. Uploads directed at a library model are owned by nothing an
  editor deletes, are never touched by the per-field sweep, and need no saved record, so create
  pages stop deferring them
- `->mediaLibraryQuery()` and `->mediaLibraryDirectory()` define the pool outright. Whatever the pool lists
  is also what a stored `data-id` may resolve to: the grid and the lookup are one object, so
  widening the browser and widening the lookup cannot drift apart. On a media collection the
  file attachment provider enforces that; on a plain disk the field switches on Filament's own
  `preventFileAttachmentPathTampering()` and answers it from the same pool, while a path
  already in the saved content and a file uploaded a moment ago stay valid either way
- Uploads show up in the browser straight away. Filament holds an upload as a pending
  attachment and only writes it to the collection when the form is saved, so a browser that
  listed only what was stored answered "no such picture" about the file somebody had just
  chosen. Pending uploads now sit at the front of the first page, drawn as not yet saved, and
  work on create pages too - where there is no record for a media row to belong to yet
- The whole library is the dropzone: a picture dragged onto the grid or the list uploads, and
  the Upload button in the header opens the same file dialog. Both go through Filament's own
  file upload, kept in the dialog but off screen, so Livewire's protocol, the validation, the
  progress events and the pending attachment are untouched rather than re-implemented
- Which layout was last browsed in is remembered in the browser
- The image dialog inserts what is selected rather than whichever candidate exists. With a file
  uploaded and a library picture then chosen, the file used to win - so choosing did nothing,
  and the passed-over upload reappeared the next time the dialog was opened. Picking from the
  library also no longer throws the upload away: it stays in the browser, unselected, and an
  upload that is never inserted never becomes an attachment at all
- The browser is a two-column dialog rather than a bare grid: a search box, a filter over the
  kinds of picture the pool actually holds, a sort, a switch between tiles and a list, a count
  and page numbers, all built from Filament's own input, select, dropdown and button
  components - and a details panel beside it showing the selected picture's name, size,
  dimensions, type and modification date, with a button that copies its URL. `->mediaLibraryListView()`
  chooses which layout it opens on
- Dimensions are read off the picture itself, which is the only place they can come from for a
  field that stores plain files rather than media - no row to stamp, no custom properties. The
  local path is used where the disk has one, so only the header is read. A measurement is
  remembered per file, keyed by its size and modification time, so a listing pays for a picture
  once and a file replaced under the same name is measured again
- - `->mediaLibraryThumbnail()` draws the grid from a small conversion instead of from full-size
  files, and falls back to the original for anything the model has not generated - a conversion
  that was never declared, or one whose queue job has not run. Separate from the conversion an
  inserted picture uses, because a tile is 120 pixels wide and the image in the document is not
- `->mediaLibraryPageSize()` and a `media_library` section in the config file

### Fixed

- Media browser search is case-insensitive on PostgreSQL. Its `LIKE` is case-sensitive, unlike
  MySQL's default collation and unlike SQLite, so searching a library for "hafen" answered that
  there is no "Hamburger Hafen". The query uses `ilike` there
- A malformed image id no longer takes the page down on PostgreSQL. Media ids are UUIDs and the
  column is a real `uuid` type there, so looking one up with a stray value raised a query
  exception instead of answering "not in the pool" - and the value comes out of stored content
- The media browser no longer opens over a whole filesystem disk. Filament leaves
  `fileAttachmentsDirectory()` null by default, and the pool fell back to the disk root - so the
  grid listed every image on it, other features' uploads included, and a stored path could
  resolve to any of them. A directory is what turns a disk into a pool; without one the field
  has nothing browsable and Filament's own image dialog takes the button back
- A file attachment provider other than this package's own is no longer mistaken for a plain
  disk field. It was given a disk pool it has nothing to do with, and Filament's path-tamper
  guard was switched on with that pool as the authoriser - so ids the provider itself issued
  were refused and its images stopped resolving. Such a field now gets no browser at all, which
  is the only honest answer: where an attachment lives and what its id means are the provider's
  to define
- Media library search finds file names containing `_` or `%` again. The term was escaped for
  `LIKE` without the `ESCAPE` clause that escaping needs, and that clause cannot be written
  portably - so on SQLite a search for `IMG_2043` matched nothing at all. The term is used as a
  pattern now, which is what Filament's own table search does; it over-matches at worst
- `fileAttachmentsAcceptedFileTypes(['image/*'])` no longer empties the browser. The wildcard is
  a value Filament accepts and Laravel validates against, but the pool matched the list exactly,
  so a correctly configured field got a permanently empty library and nothing to pick
- `mediaLibraryQuery()` now throws when its closure returns something other than the query it was
  handed, instead of falling back to an unfiltered pool. The pool is also what a stored `data-id`
  may resolve to, so a forgotten `return` quietly widened the browser to every image in the media
  table
- The media browser's pager no longer invents pages. It guessed the page size from how many tiles
  came back, so a short last page - almost every library has one - was read as a tiny page size
  and the whole library divided by it: a two-page library showed "2 / 41" and a Next button
  leading to an empty grid. The server sends the page size it actually used
- An upload made while a folder is open, or while a search or type filter is active, appears
  again. A picture that is not saved yet can only be shown on the first page of the root, so the
  grid answered without it: no tile, no selection, and - the upload field being deliberately
  invisible - no sign that anything had happened. Uploading now returns the grid to where the
  new picture can be seen. If a selection is still somehow missing, Apply falls back to the
  upload that just arrived rather than inserting the previously selected picture
- The browser's pager arrows have an accessible name. They passed `label` as an Alpine binding,
  which is not the Blade prop, so no `aria-label` or `title` was rendered and a screen reader
  announced them as "button"
- A JPEG carrying a large colour profile or EXIF block is measured rather than reported as
  unmeasurable. Only the first 64 KB were read, which some cameras and photo editors push the
  frame header past - and the failure was then cached for a day
- A create page no longer resolves any media uuid in the application. A field without a record
  fell through to an unscoped lookup, which is right for a renderer reading a saved document and
  wrong on a create form, where the content is being typed at that moment - pasting a foreign
  uuid into a new article resolved it to a URL. A field with no record is now scoped to the pool
  the browser offers, exactly as it is once the record exists; only a renderer handed the
  provider directly stays unscoped
- **A shared media library no longer eats other records' pictures.** The browser's pool is the
  collection by default, so a picture uploaded for one article is offered to - and referenced by
  - the next; the per-field sweep, however, still deleted anything this field had uploaded to
  this record and no longer referenced. Removing a picture from one article therefore deleted
  the file out from under every other article using it, silently and permanently. The sweep now
  runs only when nothing else can be holding the id - `->mediaLibraryScope('record')`, or no
  browser at all. With a shared pool the file stays and is tidied deliberately, because a file
  kept too long costs disk space while a file deleted too early costs somebody's content
- `->linkAttributes(false)` no longer throws away every video embed and every image caption
  in the document. The renderer returned early for that switch, past the point where the
  embed and caption nodes are declared, so asking for Filament's plain links silently
  dropped content that has nothing to do with links - at render time, with the stored
  markup untouched and nothing to say it had happened. Only the link mark hangs off the
  switch now
- Mentions no longer vanish from `toText()`. TipTap's PHP text serialiser walks `content` and
  `text` and calls `renderText()` on nothing, so a mention - an atom node with neither - came
  out as an empty string with a block separator on each side: a hole in the middle of the
  sentence, in exactly the copy a search index, an excerpt or a notification body is made from.
  They are rewritten into the text they read as before serialising, the way Filament already
  does it for merge tags. `toText()` also returns `''` for an empty record rather than throwing,
  which `toHtml()` already did
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

- The Livewire nesting note is one short step in Installation, and says to change one line
  rather than paste a `payload` block: a published `payload` replaces the vendor one outright,
  and its other keys differ between releases - `max_components` is `20` on Livewire 4.1 and
  `200` on 4.4 - so a copied block quietly applies another version's limits. Upgrading Livewire
  is not an alternative; every release from 4.1 to 4.4.1 ships the same default of `10`
- The suite runs against any driver. `DB_CONNECTION` and friends point it at MySQL or
  PostgreSQL; SQLite in memory stays the default. Both PostgreSQL bugs above were found the
  first time it ran anywhere else
- `spatie/laravel-medialibrary` is declared as `^11.0`. `^12.0` was also advertised, but there
  is no stable v12 - only `v12.x-dev` - so the claim could never resolve and was never tested
- The `media_library` config comment said the browser was record-scoped out of the box; the
  shipped default is and remains the whole collection. The comment now describes what ships,
  and says what sharing costs before you ship it
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

- Image captions. The image toolbar's text panel now asks for a caption beside the alt text,
  and a captioned image is rendered as `<figure>` with a `<figcaption>` - the paragraph it
  was alone in replaced rather than kept around it, since a figure inside a paragraph is
  markup browsers disagree about. An image sitting between words is left where it is. What
  is stored is `data-caption` on the image: a figure is a structure, and building one in the
  schema would mean replacing Filament's image node and taking on its resizing, its uploads
  and its node view for one line of text
- A language picker on the code block, drawn on the block rather than in the toolbar - the
  language is a property of that block, and a document may hold several in different ones.
  It writes the `language-…` class TipTap already stores, so nothing new goes into the
  schema, and an empty list of languages takes the picker away
- `highlightCode()` colours code blocks when rendering, through phiki/phiki as an optional
  dependency. In PHP rather than in the browser: the only highlighter worth having there is
  measured in megabytes and would colour text only its author sees. A block with no language
  is left alone, the code itself is never touched, and a light/dark pair rides in one piece
  of markup instead of rendering the page twice
- Video embeds. A pasted YouTube or Vimeo link is taken apart and the embed URL is built
  from it, so every shape a share button produces works - watch, youtu.be, shorts, embed,
  the Vimeo equivalents - and the timestamp in a link shared "from 1:30" survives. Pasting
  such a link on an empty line turns it into a video; the same link mid-sentence stays a
  link. The editor draws a card rather than a player, so a document with ten videos in it
  does not load ten players in the panel. YouTube goes through `youtube-nocookie.com` by
  default. Filament's sanitiser drops `<iframe>`, so `embed.sanitizer` allows it back with
  `src` narrowed to a host allowlist - and the node refuses to build the element for a host
  that is not on it, which makes the sanitiser the second line rather than the only one.
  The shape of the frame is written inline, so a rendered embed keeps it on a page this
  package's stylesheet never reaches
- A slash menu: typing `/` opens a searchable list of the commands the field offers, with
  arrow keys, Enter or Tab to pick. The list is derived from the tools the field registered
  and each entry runs that tool's own handler, so a command and its button cannot come
  apart - a feature switched off leaves the menu with it. Two groups, split by the question
  each command answers: `style` changes what the block already there is, `insert` is what
  arrives. Blocks and things you insert only: the menu opens where nothing is selected, and
  an inline format there would mark nothing. Groups, their contents and the character that
  opens the menu are set project-wide and overruled per field with `->slashGroups()` and
  `->slashChar()`, like every other list in this package
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
