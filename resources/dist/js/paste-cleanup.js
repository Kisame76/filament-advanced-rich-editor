/*
 * What arrives from the clipboard, made into a document again.
 *
 * Word does not put a paragraph on the clipboard. It puts a paragraph, the stylesheet it
 * was drawn with, the list it was never in, a handful of namespaced tags no browser has
 * heard of, and a comment saying which version of Office wrote them. Google Docs is tidier
 * and worse: every run of text is a `<span>` carrying eleven declarations, one of which is
 * the only place its bold lives. Pasting either of them into an editor that keeps what it
 * is given produces something that looks almost right and belongs to another document.
 *
 * The cleaning happens on the HTML before ProseMirror parses it, through
 * `transformPastedHTML`, and therefore also covers a drop - ProseMirror reads a drop off
 * the same clipboard path. Everything here is a string in and a string out over a detached
 * document, which is the reason the whole file can be tested without an editor: what a
 * paste is worth is decided before any of it becomes a node.
 *
 * Two jobs, and they are not the same job.
 *
 * The first is repair, and it only ever happens to markup that says it came from Word. A
 * Word list is not a list: it is a run of paragraphs, each carrying `mso-list` in its style
 * and its own bullet as text, and nothing downstream can put them back together because by
 * then they are twelve paragraphs starting with a dot. That has to be rebuilt here or not
 * at all.
 *
 * The second is the typography, and it happens to everything that did not come out of a
 * ProseMirror editor. A paste keeps its structure - headings, lists, tables, links, bold,
 * italic - and loses the fonts, the sizes and the colours it was wearing. That is a
 * deliberate opinion and the more useful one: this package parses `font-family`,
 * `font-size`, `color` and `line-height` into marks of its own, so anything left standing
 * is not cosmetic noise that the next save drops, it is Calibri 11pt in black, in the
 * document, for good, in a design system that never asked for it. What a project does want
 * to survive is named in `keepStyles`.
 *
 * The order of the two is the whole trick. Bold in Google Docs is `font-weight:700` in a
 * style attribute and nowhere else, so the styles are turned into tags before anything is
 * dropped - strip first and the paste arrives correct in structure and flat in meaning,
 * which is the one failure mode nobody notices until the article is published.
 *
 * A copy out of a ProseMirror editor is left entirely alone. It carries `data-pm-slice`,
 * it is already the shape the document wants, and cleaning it would mean a field that
 * quietly loses colours on the way from one editor to the next one beside it.
 */

/** A copy from a ProseMirror editor - this one, or the field next to it. */
export const EDITOR = 'editor'

/** Word, and everything that writes Word's HTML: Outlook, and Excel via the same clipboard. */
export const WORD = 'word'

/** Google Docs, and Google Slides with it. */
export const GOOGLE_DOCS = 'googleDocs'

/**
 * The style properties that survive a cleaned paste when the element carries no settings
 * at all - which in a Filament field never happens, because the extension and the settings
 * are put there by the same switch. It is a last resort for an editor mounted by hand, and
 * the default a project actually gets is the one in `config/filament-advanced-rich-editor.php`.
 *
 * Both of them are structure wearing a style attribute rather than typography: the
 * alignment is the one thing in Word's `style` that a reader would notice the absence of,
 * and the shape of an embed is what the embed is.
 */
export const KEPT_STYLES = ['text-align', 'aspect-ratio']

/**
 * Attributes worth keeping, by the element carrying them. Anything not named here goes,
 * which is the right way round: Word invents attributes faster than a list could be kept.
 *
 * `data-*` is not in the list and is never dropped - see `isKept()`. A paste from a page
 * this package rendered carries its own meaning there, and reading it back is the reason
 * mentions, anchors and named styles survive a round trip through a browser window.
 */
const KEPT_ATTRIBUTES = {
    '*': ['dir', 'id', 'class', 'style'],
    a: ['href', 'target', 'rel', 'title', 'hreflang'],
    img: ['src', 'alt', 'title', 'width', 'height', 'loading'],
    // A frame and a player are the one place where `src` is the whole node rather than a
    // decoration on it: this package's embed reads the video off the `src` of the frame
    // inside it and drops the node entirely when it cannot, so an embed pasted from a page
    // this package rendered would come back as nothing at all. Which frames are allowed to
    // stand is not decided here - the schema takes only a frame inside an embed whose host
    // it recognises, and the sanitiser narrows it again on the way out.
    iframe: ['src', 'title', 'allow', 'allowfullscreen', 'referrerpolicy', 'width', 'height', 'loading'],
    video: ['src', 'poster', 'controls', 'width', 'height', 'preload', 'playsinline'],
    audio: ['src', 'controls', 'preload'],
    source: ['src', 'srcset', 'type', 'media', 'sizes'],
    track: ['src', 'kind', 'srclang', 'label', 'default'],
    td: ['colspan', 'rowspan'],
    th: ['colspan', 'rowspan', 'scope'],
    ol: ['start', 'type'],
    li: ['value'],
    time: ['datetime'],
}

/**
 * Elements removed with everything inside them.
 *
 * `<style>` is the important one and the least obvious: ProseMirror walks into an element
 * it has no rule for and keeps the text it finds, so a stylesheet that came along with the
 * paste is inserted into the document as three hundred words of CSS. Everything else here
 * is Word's: `<xml>` holds the document properties, `<o:p>` holds a non-breaking space, and
 * the VML shapes hold a drawing no browser has rendered since 2011.
 */
const DROPPED_ELEMENTS = new Set([
    'style', 'script', 'meta', 'link', 'title', 'base', 'noscript', 'xml',
])

/**
 * Namespaced tags, by their prefix. Word writes `<o:p>`, `<w:sdt>`, `<v:shape>`, `<m:oMath>`
 * and `<st1:city>`, and an HTML parser keeps all of them as elements with a colon in the
 * name.
 */
const DROPPED_PREFIXES = ['o:', 'w:', 'v:', 'm:', 'st1:', 'x:']

/**
 * Ids that are a generator's bookkeeping rather than an anchor somebody chose.
 *
 * The distinction matters because this package reads `id` on a heading as an anchor: keep
 * them all and every paste from Word plants `_Toc496` in the document, drop them all and a
 * paste from a page this package rendered loses the anchors it was written with.
 */
const NOISE_IDS = /^(docs-internal-guid|_Toc|_Ref|_Hlk|_GoBack|m_-?\d|gmail-|isPasted)/i

/**
 * What says the markup came out of Word.
 *
 * Any one of these is enough, and there are several because which of them shows up depends
 * on the version, the platform and whether it went through Outlook on the way.
 */
const WORD_MARKERS = /mso-|urn:schemas-microsoft-com|<\/?[owvm]:|class="?Mso|<!--\[if [^\]]*mso/i

/** Google Docs wraps the whole selection in one element carrying this id. */
const DOCS_MARKER = /docs-internal-guid-/i

/** ProseMirror's own marker, written onto the clipboard by every editor built on it. */
const SLICE_MARKER = /data-pm-slice/i

/**
 * A fragment that is not a whole element: table rows without their table, list items
 * without their list.
 *
 * An HTML parser throws those away rather than keeping them - `innerHTML = '<tr>...'` on a
 * `<div>` leaves the cells' text and nothing else - so a fragment like this is handed on
 * untouched. ProseMirror knows how to wrap it and this does not, and losing the table is a
 * worse outcome than keeping Word's fonts in it.
 */
const PARTIAL_FRAGMENT = /^\s*<(tr|td|th|tbody|thead|tfoot|colgroup|col|li|dt|dd|option)\b/i

/**
 * The `<meta charset='utf-8'>` a browser puts in front of what it hands over.
 *
 * It is not part of the paste and it is in front of nearly every real one, so anything
 * asking what the markup starts with has to look past it first - which is what
 * `prosemirror-view` does before the same question, and the reason this is a copy of its
 * pattern rather than an idea of its own.
 */
const LEADING_META = /^(\s*<meta [^>]*>)*/i

/**
 * Where the markup came from, or null when nothing in it says.
 *
 * The editor is asked about first: a slice may well contain markup that once came from
 * Word, and what matters is that it is in a document now.
 */
export function sourceOf(html) {
    if (typeof html !== 'string' || html === '') {
        return null
    }

    if (SLICE_MARKER.test(html)) {
        return EDITOR
    }

    if (WORD_MARKERS.test(html)) {
        return WORD
    }

    if (DOCS_MARKER.test(html)) {
        return GOOGLE_DOCS
    }

    return null
}

/**
 * The clipboard's HTML, as a document worth parsing.
 *
 * @param {string} html
 * @param {{ keepStyles?: Array<string> }} options
 * @returns {string}
 */
export function cleanPastedHtml(html, { keepStyles = KEPT_STYLES } = {}) {
    if (typeof html !== 'string' || ! html.includes('<')) {
        return html
    }

    const source = sourceOf(html)

    // Already a document. Cleaning it would be this package taking colours off its own
    // content on the way from one field to the one beside it. The original is handed back
    // rather than the one without its `<meta>`, because ProseMirror drops that itself.
    if (source === EDITOR || PARTIAL_FRAGMENT.test(html.replace(LEADING_META, ''))) {
        return html
    }

    const body = new DOMParser().parseFromString(html, 'text/html').body

    if (! body) {
        return html
    }

    const kept = new Set(keepStyles)

    dropNoise(body)

    if (source === WORD) {
        // Before the attributes go: a Word list is only a list for as long as `mso-list` is
        // still standing in the style attribute that is about to be thrown away, and a
        // section is only nameable while it still has the class saying which one it is.
        unwrapWordSections(body)
        rebuildWordLists(body)
    }

    dropFakeEmphasis(body)
    promoteSemantics(body, kept)
    stripAttributes(body, kept)
    unwrapBareInline(body)

    if (source === WORD) {
        // After the wrappers, not before: Word's blank line is a paragraph holding one span
        // holding one non-breaking space, and it only reads as empty once the span it was
        // wearing has been taken off.
        dropWordFillers(body)
    }

    // Unwrapping left the text of two spans as two text nodes, and a run of spaces split
    // across a seam is not a run to anything reading one node at a time.
    body.normalize()
    collapseSpaces(body)

    return body.innerHTML
}

/**
 * Comments, stylesheets and the tags Word invented, gone with what they contain.
 */
function dropNoise(root) {
    const document = root.ownerDocument

    // Comments hold the conditional blocks Word wraps its bullets in, and a `NodeIterator`
    // is the only way to reach a node the selectors cannot name.
    const comments = document.createNodeIterator(root, 128 /* SHOW_COMMENT */)

    const found = []

    let comment

    while ((comment = comments.nextNode())) {
        found.push(comment)
    }

    for (const node of found) {
        node.remove()
    }

    for (const element of Array.from(root.querySelectorAll('*'))) {
        const name = element.tagName.toLowerCase()

        if (DROPPED_ELEMENTS.has(name) || DROPPED_PREFIXES.some((prefix) => name.startsWith(prefix))) {
            element.remove()
        }
    }
}

/**
 * The style attribute as a map of property to value, lowercased on both sides of the colon
 * for the property and on neither for the value - `font-family:"Calibri Light"` keeps its
 * capitals because a value can be a name.
 */
function declarationsOf(style) {
    const declarations = {}

    for (const part of String(style ?? '').split(';')) {
        const colon = part.indexOf(':')

        if (colon < 1) {
            continue
        }

        const property = part.slice(0, colon).trim().toLowerCase()
        const value = part.slice(colon + 1).trim()

        if (property && value) {
            declarations[property] = value
        }
    }

    return declarations
}

/**
 * The level a Word list item sits at, or null when the paragraph is not one.
 *
 * `mso-list: l0 level2 lfo1` is a list id, a depth and a numbering to take the format from.
 * Only the depth is usable here: the numbering lives in the stylesheet that was dropped
 * three steps ago, which is why the bullet is read off the text instead.
 */
export function wordListLevel(element) {
    if (element.nodeType !== 1 || element.tagName.toLowerCase() !== 'p') {
        return null
    }

    const style = element.getAttribute('style') ?? ''
    const level = /mso-list\s*:\s*l\d+\s+level(\d+)/i.exec(style)

    if (level) {
        return Number(level[1])
    }

    // Word Online and a few older versions write the class and leave the style out. The
    // marker span is what makes it a list item either way, so the class alone is not taken
    // as an answer.
    if (/MsoListParagraph/i.test(element.getAttribute('class') ?? '') && markerSpanIn(element)) {
        return 1
    }

    return null
}

/**
 * The span holding the bullet or the number a Word list item draws for itself.
 */
function markerSpanIn(element) {
    for (const span of element.querySelectorAll('span[style]')) {
        if (/mso-list\s*:\s*ignore/i.test(span.getAttribute('style') ?? '')) {
            return span
        }
    }

    return null
}

/**
 * Whether a marker reads as a number or as a bullet.
 *
 * The delimiter decides, and it has to: Word's second-level bullet is the letter `o` in
 * Courier, so a rule that called any single letter a number would turn every nested bullet
 * list into `o.` counting from fifteen. A number always brings its `.` or `)` along, and
 * sometimes an opening bracket as well - `(1)` and `(a)` are two of the numbering formats
 * Word ships, and no bullet it draws looks anything like them.
 */
export function markerKind(marker) {
    return /^\(?[0-9A-Za-z]+(?:[.)][0-9A-Za-z]+)*[.)]$/.test(marker) ? 'ordered' : 'bullet'
}

/**
 * Where an ordered marker starts counting, when that is not one.
 *
 * A list continued after an interruption starts at seven, and the seven is only in the
 * marker - nothing else in the paste says so.
 */
export function markerStart(marker) {
    const first = /^\(?(\d+)[.)]/.exec(marker)

    return first ? Number(first[1]) : 1
}

/**
 * Takes the marker out of a list item and says what it was.
 */
function takeMarker(element) {
    const span = markerSpanIn(element)

    if (! span) {
        return ''
    }

    const marker = (span.textContent ?? '').replace(/[\s\u00A0]+/g, ' ').trim()

    // The marker usually sits inside a second span carrying the Symbol font it is drawn
    // with, and that one is left holding nothing.
    const parent = span.parentElement

    span.remove()

    if (parent && parent !== element && parent.tagName.toLowerCase() === 'span' && ! parent.textContent.trim()) {
        parent.remove()
    }

    return marker
}

/**
 * The `<div class=WordSection1>` everything Word writes is wrapped in.
 *
 * It is a page setup - margins, columns, headers - and none of that is coming with it, so
 * what is left is a block element around the whole paste for no reason. ProseMirror would
 * walk through it either way; a paste that reads back as what was pasted is worth the four
 * lines.
 */
function unwrapWordSections(root) {
    for (const section of Array.from(root.querySelectorAll('div[class]'))) {
        if (/WordSection\d/i.test(section.getAttribute('class'))) {
            unwrap(section)
        }
    }
}

/**
 * Word's runs of list-shaped paragraphs, put back into lists.
 *
 * Done per parent rather than over the document, so a list inside a table cell is rebuilt
 * in the cell and a run interrupted by a heading is two lists rather than one.
 */
function rebuildWordLists(root) {
    const parents = new Set()

    for (const paragraph of root.querySelectorAll('p')) {
        if (wordListLevel(paragraph) !== null && paragraph.parentNode) {
            parents.add(paragraph.parentNode)
        }
    }

    for (const parent of parents) {
        buildListsIn(parent)
    }
}

/**
 * One parent's children, with every run of list items replaced by the nesting it describes.
 *
 * The stack holds one entry per open level. A deeper item opens a list inside the last item
 * of the level above it, a shallower one closes back down to its own, and anything that is
 * not a list item at all closes the lot - which is what makes a paragraph between two lists
 * into a break rather than into a gap in the numbering.
 */
function buildListsIn(parent) {
    const document = parent.ownerDocument

    let stack = []

    for (const node of Array.from(parent.childNodes)) {
        // Whitespace between two items is the newline Word wrote between them.
        if (node.nodeType === 3 && ! node.textContent.trim()) {
            continue
        }

        const level = node.nodeType === 1 ? wordListLevel(node) : null

        if (level === null) {
            stack = []

            continue
        }

        const marker = takeMarker(node)
        const kind = markerKind(marker)

        while (stack.length && stack[stack.length - 1].level > level) {
            stack.pop()
        }

        let top = stack[stack.length - 1]

        if (! top || top.level < level || top.kind !== kind) {
            const list = document.createElement(kind === 'ordered' ? 'ol' : 'ul')
            const start = kind === 'ordered' ? markerStart(marker) : 1

            if (start > 1) {
                list.setAttribute('start', String(start))
            }

            if (top && top.level < level) {
                // A nested list belongs inside the item above it. Where a document skips a
                // level - one, then three - there is no item to put it in, so one is made:
                // an empty bullet is closer to what was meant than a list hanging off a list.
                const host = top.list.lastElementChild ?? top.list.appendChild(document.createElement('li'))

                host.appendChild(list)
            } else if (top) {
                // Same level, other kind: bullets that turn into numbers halfway down are
                // two lists, side by side.
                stack.pop()
                top.list.parentNode.insertBefore(list, top.list.nextSibling)
            } else {
                parent.insertBefore(list, node)
            }

            stack.push({ level, kind, list })
            top = stack[stack.length - 1]
        }

        const item = document.createElement('li')

        while (node.firstChild) {
            item.appendChild(node.firstChild)
        }

        trimEdges(item)
        top.list.appendChild(item)
        node.remove()
    }
}

/**
 * The whitespace a marker left behind at either end of a list item.
 */
function trimEdges(element) {
    const empty = (node) => node && node.nodeType === 3 && ! node.textContent.replace(/[\s\u00A0]+/g, '').length

    while (empty(element.firstChild)) {
        element.firstChild.remove()
    }

    while (empty(element.lastChild)) {
        element.lastChild.remove()
    }
}

/**
 * The empty paragraphs Word leaves between everything.
 *
 * They are `<p class=MsoNormal><o:p>&nbsp;</o:p></p>` - a paragraph whose entire content is
 * a tag that has just been removed. Left standing they arrive as a blank line between every
 * two lines of the article, which is the second thing people notice about a Word paste.
 */
function dropWordFillers(root) {
    for (const paragraph of Array.from(root.querySelectorAll('p'))) {
        if (paragraph.children.length === 0 && ! paragraph.textContent.replace(/[\s\u00A0]+/g, '').length) {
            paragraph.remove()
        }
    }
}

/**
 * Emphasis that says it is not emphasis.
 *
 * Google Docs wraps a whole selection in `<b style="font-weight:normal">`, which is a tag
 * meaning bold and a style meaning it is not. TipTap reads the style and gets it right;
 * anything reading the tag - a sanitiser, a Markdown export, the next editor along - does
 * not, so the tag goes.
 */
function dropFakeEmphasis(root) {
    for (const element of Array.from(root.querySelectorAll('b[style], strong[style], i[style], em[style]'))) {
        const declarations = declarationsOf(element.getAttribute('style'))
        const name = element.tagName.toLowerCase()

        const isFake = (name === 'b' || name === 'strong')
            ? /^(normal|400|lighter|300|200|100)$/.test(declarations['font-weight'] ?? '')
            : (declarations['font-style'] ?? '') === 'normal'

        if (isFake) {
            unwrap(element)
        }
    }
}

/**
 * The styles that mean something, turned into the tags that mean it.
 *
 * Google Docs writes every run as a span and puts its bold, its italic and its underline in
 * the style attribute; Word does the same for anything a theme applied rather than a button.
 * Both are about to lose that attribute, so whatever it was saying is said in tags first.
 *
 * A property a project chose to keep is left alone here: naming `font-weight` in
 * `keepStyles` means wanting the style, not a `<strong>` and the style.
 */
function promoteSemantics(root, kept) {
    for (const element of Array.from(root.querySelectorAll('[style]'))) {
        if (! element.firstChild) {
            continue
        }

        const document = element.ownerDocument
        const tags = semanticTagsFor(declarationsOf(element.getAttribute('style')), kept)
            // An element already saying it: `<em style="font-style:italic">` is one emphasis.
            .filter((tag) => ! SYNONYMS[tag].has(element.tagName.toLowerCase()))

        if (! tags.length) {
            continue
        }

        let content = document.createDocumentFragment()

        while (element.firstChild) {
            content.appendChild(element.firstChild)
        }

        for (const tag of tags) {
            const wrapper = document.createElement(tag)

            wrapper.appendChild(content)
            content = wrapper
        }

        element.appendChild(content)
    }
}

/**
 * Which tags stand for the same thing, so a style is not promoted onto an element that is
 * already it.
 */
const SYNONYMS = {
    strong: new Set(['strong', 'b']),
    em: new Set(['em', 'i']),
    u: new Set(['u', 'ins']),
    s: new Set(['s', 'strike', 'del']),
    sup: new Set(['sup']),
    sub: new Set(['sub']),
}

/**
 * The tags a set of declarations is worth.
 *
 * `font-weight` is a number as often as a word, and the line is where CSS draws it: 600 and
 * up is bold, and `bolder` is relative to something this markup no longer has, so it is
 * taken at face value.
 */
export function semanticTagsFor(declarations, kept = new Set()) {
    const tags = []
    const dropped = (property) => ! kept.has(property)

    const weight = declarations['font-weight'] ?? ''

    if (dropped('font-weight') && /^(bold|bolder|[6-9]00)$/.test(weight)) {
        tags.push('strong')
    }

    if (dropped('font-style') && /^(italic|oblique)/.test(declarations['font-style'] ?? '')) {
        tags.push('em')
    }

    // Both are read, and together rather than one instead of the other: Google Docs writes
    // `text-decoration: none` and then says what it meant in the longhand beside it, so a
    // rule taking the first one present finds the `none` and loses the underline.
    const decoration = `${declarations['text-decoration'] ?? ''} ${declarations['text-decoration-line'] ?? ''}`

    if (dropped('text-decoration') && dropped('text-decoration-line')) {
        if (decoration.includes('underline')) {
            tags.push('u')
        }

        if (decoration.includes('line-through')) {
            tags.push('s')
        }
    }

    const vertical = declarations['vertical-align'] ?? ''

    if (dropped('vertical-align')) {
        if (vertical === 'super') {
            tags.push('sup')
        }

        if (vertical === 'sub') {
            tags.push('sub')
        }
    }

    return tags
}

/**
 * Whether an attribute is worth carrying into the document.
 */
function isKept(name, tag) {
    // Never dropped, and the reason the round trip through a rendered page works at all:
    // a mention, an anchor and a named style are `data-*` and nothing else.
    if (name.startsWith('data-')) {
        return true
    }

    // `style`, `class` and `id` get through here and are cut down below rather than kept
    // whole - each of the three carries one thing worth having among several that are not.
    return KEPT_ATTRIBUTES['*'].includes(name) || (KEPT_ATTRIBUTES[tag] ?? []).includes(name)
}

/**
 * Every attribute the paste is not keeping, gone - and what is left of the two that are
 * kept conditionally.
 */
function stripAttributes(root, kept) {
    for (const element of Array.from(root.querySelectorAll('*'))) {
        const tag = element.tagName.toLowerCase()

        for (const name of element.getAttributeNames()) {
            if (! isKept(name, tag)) {
                element.removeAttribute(name)
            }
        }

        if (element.hasAttribute('style')) {
            const style = Object.entries(declarationsOf(element.getAttribute('style')))
                .filter(([property, value]) => kept.has(property) && ! /mso-/i.test(value))
                .map(([property, value]) => `${property}: ${value}`)
                .join('; ')

            style ? element.setAttribute('style', style) : element.removeAttribute('style')
        }

        if (element.hasAttribute('class')) {
            // The language on a code block is a class and is the document's, not the
            // stylesheet's. Everything else belongs to a theme this page is not wearing.
            const classes = element.getAttribute('class')
                .split(/\s+/)
                .filter((name) => name.startsWith('language-'))
                .join(' ')

            classes ? element.setAttribute('class', classes) : element.removeAttribute('class')
        }

        if (element.hasAttribute('id') && NOISE_IDS.test(element.getAttribute('id'))) {
            element.removeAttribute('id')
        }
    }
}

/**
 * Wrappers with nothing left to say.
 *
 * A `<span>` is only ever its attributes, so one that has none is a node standing between
 * the text and its paragraph for no reason - and there are hundreds of them in any paste
 * from Google Docs. `<font>` goes whatever it carries: everything it can say, it says in
 * attributes this paste is not keeping.
 */
function unwrapBareInline(root) {
    for (const element of Array.from(root.querySelectorAll('span, font'))) {
        if (element.tagName.toLowerCase() === 'font' || element.getAttributeNames().length === 0) {
            unwrap(element)
        }
    }
}

/**
 * An element replaced by what was inside it.
 */
function unwrap(element) {
    const parent = element.parentNode

    if (! parent) {
        return
    }

    while (element.firstChild) {
        parent.insertBefore(element.firstChild, element)
    }

    element.remove()
}

/**
 * The runs of non-breaking spaces a word processor indents with.
 *
 * A single one is a decision - a name that must not be broken across two lines - and stays.
 * Three in a row are Word's idea of a tab stop, and they are the reason a pasted paragraph
 * cannot be reflowed afterwards: nothing collapses them, because as far as anything reading
 * the document is concerned they are letters.
 */
function collapseSpaces(root) {
    const document = root.ownerDocument
    const text = document.createNodeIterator(root, 4 /* SHOW_TEXT */)

    const found = []

    let node

    while ((node = text.nextNode())) {
        found.push(node)
    }

    for (const candidate of found) {
        if (candidate.parentElement?.closest('pre, code')) {
            continue
        }

        candidate.textContent = candidate.textContent.replace(/\u00A0{2,}/g, ' ')
    }
}

/**
 * The extension. It owns no state, no command and no key: pasting already has all three,
 * and this only decides what the paste is made of.
 */
export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core
    const pmState = window.FilamentRichEditor?.tiptap?.pmState

    if (! tiptap || ! pmState) {
        console.error(
            'The advanced rich editor paste extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap
    const { Plugin, PluginKey } = pmState

    const settingsOf = (editor) => {
        const raw = editor?.options?.element?.dataset?.artePaste

        if (! raw) {
            return { keepStyles: KEPT_STYLES }
        }

        try {
            const settings = JSON.parse(raw)

            return { keepStyles: settings?.keepStyles ?? KEPT_STYLES }
        } catch (error) {
            console.error('The advanced rich editor could not read its paste settings:', error)

            return { keepStyles: KEPT_STYLES }
        }
    }

    return Extension.create({
        name: 'artePasteCleanup',

        addProseMirrorPlugins() {
            const editor = this.editor

            return [
                new Plugin({
                    key: new PluginKey('artePasteCleanup'),
                    props: {
                        // A ProseMirror plugin prop rather than TipTap's own hook of the
                        // same name: both are reached, and this one is reached by every
                        // version. Filament's file handler calls it by hand on the markup
                        // it rebuilt after uploading the images out of a paste, so a Word
                        // document with a picture in it is cleaned once, either way in.
                        transformPastedHTML: (html) => cleanPastedHtml(html, settingsOf(editor)),
                    },
                }),
            ]
        },
    })
}
