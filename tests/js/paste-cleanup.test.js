import { describe, expect, it } from 'vitest'
import {
    EDITOR,
    GOOGLE_DOCS,
    WORD,
    cleanPastedHtml,
    markerKind,
    markerStart,
    semanticTagsFor,
    sourceOf,
} from '../../resources/js/paste-cleanup.js'

/**
 * Cleaning what arrives from the clipboard.
 *
 * Everything asserted here is a string in and a string out, and that is the design rather
 * than a convenience: what a paste is worth is decided before any of it becomes a node, so
 * the whole of it can be held to account without an editor. What is left over - that
 * ProseMirror calls the transform at all, and that it calls it for a drop as well - is
 * wiring only an editor can prove.
 *
 * The fixtures are shortened but not tidied: `class=MsoNormal` really does arrive without
 * quotes, the marker really does sit in a span inside a span, and a level really is only
 * ever named in a style property no browser has an opinion about.
 */

const clean = (html, options) =>
    cleanPastedHtml(html, options).replace(/\s+/g, ' ').replace(/>\s+</g, '><').trim()

describe('where the markup came from', () => {
    it('knows Word by any of the several things it leaves behind', () => {
        expect(sourceOf('<p class=MsoNormal>Hello</p>')).toBe(WORD)
        expect(sourceOf("<p style='mso-list:l0 level1 lfo1'>Hello</p>")).toBe(WORD)
        expect(sourceOf('<o:p></o:p>')).toBe(WORD)
        expect(sourceOf('<html xmlns:o="urn:schemas-microsoft-com:office:office"><body>Hi</body></html>')).toBe(WORD)
    })

    it('knows Google Docs by the wrapper it puts around a selection', () => {
        expect(sourceOf('<b style="font-weight:normal" id="docs-internal-guid-4f2a">Hi</b>')).toBe(GOOGLE_DOCS)
    })

    it('knows its own kind, and says so before anything else', () => {
        // A slice may well hold markup that came out of Word once. What matters is that it
        // is in a document now.
        expect(sourceOf('<div data-pm-slice="1 1 []"><p class=MsoNormal>Hi</p></div>')).toBe(EDITOR)
    })

    it('says nothing about markup that says nothing', () => {
        expect(sourceOf('<p>Hello</p>')).toBeNull()
        expect(sourceOf('')).toBeNull()
    })
})

describe('what is left alone', () => {
    it('does not touch a copy from an editor', () => {
        const slice = '<div data-pm-slice="1 1 []"><p style="color: #ff0000">Red</p></div>'

        // Not merely unchanged in meaning: unchanged. A field that quietly took the colours
        // off content on its way to the field beside it would be worse than one that keeps
        // Word's fonts.
        expect(cleanPastedHtml(slice)).toBe(slice)
    })

    it('does not touch a fragment that is not a whole element', () => {
        // An HTML parser throws loose table rows away, so a transform that parsed this
        // would hand back the text of the cells and nothing else.
        const rows = '<tr><td style="color: red">One</td></tr>'

        expect(cleanPastedHtml(rows)).toBe(rows)
    })

    it('looks past the meta a browser puts in front of everything', () => {
        // The shape every one of these questions is really asked in: Chrome hands over
        // `<meta charset='utf-8'>` and then the paste. A guard anchored at the start of the
        // string and read against the raw one answers no to all of them and flattens the
        // table into `OneTwo`.
        const rows = `<meta charset='utf-8'><tr><td>One</td><td>Two</td></tr>`

        expect(cleanPastedHtml(rows)).toBe(rows)

        const slice = `<meta charset='utf-8'><div data-pm-slice="1 1 []"><p style="color: red">Red</p></div>`

        expect(cleanPastedHtml(slice)).toBe(slice)

        expect(clean(`<meta charset='utf-8'><p class=MsoNormal>Hello<o:p></o:p></p>`)).toBe('<p>Hello</p>')
    })

    it('does not touch plain text', () => {
        expect(cleanPastedHtml('Just words')).toBe('Just words')
    })
})

describe('the noise a word processor sends along', () => {
    it('removes a stylesheet rather than letting it into the document as text', () => {
        // The one that surprises people: ProseMirror walks into an element it has no rule
        // for and keeps the text it finds, so a stylesheet arrives as three hundred words
        // of CSS in the article.
        const html = '<p class=MsoNormal>Hello</p><style>p.MsoNormal { font-size: 11pt; }</style>'

        expect(clean(html)).toBe('<p>Hello</p>')
    })

    it('removes the tags Word invented, with what is inside them', () => {
        const html = "<p class=MsoNormal>Hello<o:p></o:p></p><xml><w:WordDocument>settings</w:WordDocument></xml>"

        expect(clean(html)).toBe('<p>Hello</p>')
    })

    it('removes the comments, including the ones a browser reads as tags', () => {
        const html = '<p class=MsoNormal><!--[if gte mso 9]>hidden<![endif]-->Hello</p>'

        expect(clean(html)).toContain('Hello')
        expect(clean(html)).not.toContain('mso')
    })

    it('drops the empty paragraphs Word puts between everything', () => {
        const html = '<p class=MsoNormal>One</p><p class=MsoNormal><o:p>&nbsp;</o:p></p><p class=MsoNormal>Two</p>'

        expect(clean(html)).toBe('<p>One</p><p>Two</p>')
    })

    it('drops one that is wearing a span, which is the shape it usually arrives in', () => {
        // `<p><span style='font-family:Symbol'>&nbsp;</span></p>`: a paragraph that only
        // reads as empty once the span it was wearing has been taken off, so the pass that
        // finds it has to run after the one that unwraps.
        const html = "<p class=MsoNormal>One</p><p class=MsoNormal><span style='font-family:Symbol'>&nbsp;</span></p><p class=MsoNormal>Two</p>"

        expect(clean(html)).toBe('<p>One</p><p>Two</p>')
    })

    it('keeps an empty paragraph that is holding something', () => {
        const html = '<p class=MsoNormal><img src="cat.png"></p>'

        expect(clean(html)).toContain('<img src="cat.png">')
    })
})

describe("Word's lists, which are not lists", () => {
    const item = (level, marker, text) =>
        `<p class=MsoListParagraphCxSpMiddle style='mso-list:l0 level${level} lfo1'>` +
        `<span style='font-family:Symbol'><span style='mso-list:Ignore'>${marker}<span style='font:7.0pt "Times New Roman"'>&nbsp;&nbsp;</span></span></span>` +
        `${text}</p>`

    it('puts a run of bullet paragraphs back into one list', () => {
        const html = item(1, '·', 'One') + item(1, '·', 'Two')

        expect(clean(html)).toBe('<ul><li>One</li><li>Two</li></ul>')
    })

    it('reads the numbering off the marker, because the stylesheet holding it is gone', () => {
        const html = item(1, '1.', 'One') + item(1, '2.', 'Two')

        expect(clean(html)).toBe('<ol><li>One</li><li>Two</li></ol>')
    })

    it('does not mistake a nested bullet for a number', () => {
        // Word draws the second level as the letter o in Courier. A rule calling any single
        // letter a number would turn every nested bullet list into a lettered one.
        expect(markerKind('o')).toBe('bullet')
        expect(markerKind('§')).toBe('bullet')
        expect(markerKind('·')).toBe('bullet')
        expect(markerKind('a.')).toBe('ordered')
        expect(markerKind('iv.')).toBe('ordered')
        expect(markerKind('1)')).toBe('ordered')
        expect(markerKind('1.1.')).toBe('ordered')
        // Two of the numbering formats Word ships, and no bullet it draws looks like them.
        expect(markerKind('(1)')).toBe('ordered')
        expect(markerKind('(a)')).toBe('ordered')
        expect(markerStart('(7)')).toBe(7)
    })

    it('nests a deeper item inside the item above it', () => {
        const html = item(1, '·', 'One') + item(2, 'o', 'Deeper') + item(1, '·', 'Two')

        expect(clean(html)).toBe('<ul><li>One<ul><li>Deeper</li></ul></li><li>Two</li></ul>')
    })

    it('starts a second list where the kind changes at the same level', () => {
        const html = item(1, '·', 'Bullet') + item(1, '1.', 'Number')

        expect(clean(html)).toBe('<ul><li>Bullet</li></ul><ol><li>Number</li></ol>')
    })

    it('keeps a list that was continued after an interruption counting from where it was', () => {
        expect(markerStart('7.')).toBe(7)
        expect(markerStart('a.')).toBe(1)

        expect(clean(item(1, '7.', 'Seven'))).toBe('<ol start="7"><li>Seven</li></ol>')
    })

    it('ends a run at anything that is not a list item', () => {
        const html = `${item(1, '·', 'One')}<p class=MsoNormal>A paragraph</p>${item(1, '·', 'Two')}`

        expect(clean(html)).toBe('<ul><li>One</li></ul><p>A paragraph</p><ul><li>Two</li></ul>')
    })

    it('keeps the formatting inside an item and drops only the marker', () => {
        const html = item(1, '·', 'One <b>bold</b> word')

        expect(clean(html)).toBe('<ul><li>One <b>bold</b> word</li></ul>')
    })

    it('rebuilds a list inside a table cell in the cell', () => {
        const html = `<table><tr><td>${item(1, '·', 'One')}</td></tr></table>`

        expect(clean(html)).toContain('<td><ul><li>One</li></ul></td>')
    })

    it('leaves a real list alone', () => {
        const html = '<p class=MsoNormal>Word wrote a real one</p><ul><li>One</li></ul>'

        expect(clean(html)).toBe('<p>Word wrote a real one</p><ul><li>One</li></ul>')
    })
})

describe('the meaning that only lives in a style attribute', () => {
    it('turns the weight Google Docs writes into a tag before dropping it', () => {
        // The failure this exists to prevent is silent: strip first and the paste arrives
        // correct in structure and flat in meaning.
        const html = '<p dir="ltr"><span style="font-size:11pt;font-family:Arial;color:#000000;font-weight:700">Bold</span></p>'

        expect(clean(html)).toBe('<p dir="ltr"><strong>Bold</strong></p>')
    })

    it('does the same for italic, underline, struck out, super and subscript', () => {
        expect(semanticTagsFor({ 'font-style': 'italic' })).toEqual(['em'])
        expect(semanticTagsFor({ 'text-decoration': 'underline line-through' })).toEqual(['u', 's'])
        expect(semanticTagsFor({ 'vertical-align': 'super' })).toEqual(['sup'])
        expect(semanticTagsFor({ 'vertical-align': 'sub' })).toEqual(['sub'])
    })

    it('reads both halves of the decoration, not whichever comes first', () => {
        // Google Docs writes `text-decoration: none` and then says what it meant in the
        // longhand beside it. A rule taking the first one present finds the `none`.
        expect(semanticTagsFor({ 'text-decoration': 'none', 'text-decoration-line': 'underline' })).toEqual(['u'])
        expect(semanticTagsFor({ 'text-decoration-line': 'line-through' })).toEqual(['s'])
        expect(semanticTagsFor({ 'text-decoration': 'none' })).toEqual([])
    })

    it('draws the line at 600, where CSS draws it', () => {
        expect(semanticTagsFor({ 'font-weight': '600' })).toEqual(['strong'])
        expect(semanticTagsFor({ 'font-weight': '500' })).toEqual([])
        expect(semanticTagsFor({ 'font-weight': 'bold' })).toEqual(['strong'])
        expect(semanticTagsFor({ 'font-weight': 'normal' })).toEqual([])
    })

    it('does not say the same thing twice', () => {
        expect(clean('<em style="font-style:italic">Once</em>')).toBe('<em>Once</em>')
    })

    it('unwraps the bold that says it is not bold', () => {
        // Google Docs wraps a whole selection in one: a tag meaning bold and a style
        // meaning it is not.
        const html = '<b style="font-weight:normal" id="docs-internal-guid-4f2a"><p dir="ltr">Hello</p></b>'

        expect(clean(html)).toBe('<p dir="ltr">Hello</p>')
    })

    it('leaves the promotion alone where a project asked to keep the style itself', () => {
        const html = '<span style="font-weight:700">Bold</span>'

        expect(clean(html, { keepStyles: ['font-weight'] })).toBe('<span style="font-weight: 700">Bold</span>')
    })
})

describe('the typography that is not coming with it', () => {
    it('drops the fonts, the sizes and the colours', () => {
        const html = '<p style="font-family:Calibri;font-size:11pt;color:#000000;line-height:115%">Hello</p>'

        expect(clean(html)).toBe('<p>Hello</p>')
    })

    it('keeps the alignment, which is structure wearing a style attribute', () => {
        const html = '<p class=MsoNormal align=center style="text-align:center;font-family:Calibri">Middle</p>'

        expect(clean(html)).toBe('<p style="text-align: center">Middle</p>')
    })

    it('keeps whatever else a project named', () => {
        const html = '<p style="color:#ff0000;font-size:11pt">Red</p>'

        expect(clean(html, { keepStyles: ['color'] })).toBe('<p style="color: #ff0000">Red</p>')
    })

    it('drops the style attribute entirely where nothing in it survived', () => {
        expect(clean('<p style="font-size:11pt">Hello</p>')).toBe('<p>Hello</p>')
    })

    it('unwraps a span that has nothing left to say', () => {
        const html = '<p><span style="font-family:Arial"><span style="font-size:11pt">Hello</span></span></p>'

        expect(clean(html)).toBe('<p>Hello</p>')
    })

    it('unwraps a font tag whatever it is carrying', () => {
        expect(clean('<p><font color="red" size="4">Hello</font></p>')).toBe('<p>Hello</p>')
    })
})

describe('the attributes', () => {
    it('keeps what an element is and drops what it was wearing', () => {
        const html = '<a href="/one" target="_blank" rel="noopener" lang="en" v:shapes="_x0000_i1025">One</a>'

        expect(clean(html)).toBe('<a href="/one" target="_blank" rel="noopener">One</a>')
    })

    it('keeps the language a code block is written in and drops every other class', () => {
        const html = '<pre><code class="language-php prettyprint">echo 1;</code></pre>'

        expect(clean(html)).toBe('<pre><code class="language-php">echo 1;</code></pre>')
    })

    it('never drops a data attribute', () => {
        // The reason a paste from a page this package rendered keeps its meaning: a
        // mention, an anchor and a named style are `data-*` and nothing else.
        const html = '<p data-style="lead" class="text-xl">Lead</p>'

        expect(clean(html)).toBe('<p data-style="lead">Lead</p>')
    })

    it('drops the ids a generator made up and keeps the ones somebody chose', () => {
        expect(clean('<h2 id="_Toc496">Heading</h2>')).toBe('<h2>Heading</h2>')
        expect(clean('<h2 id="docs-internal-guid-4f2a">Heading</h2>')).toBe('<h2>Heading</h2>')
        expect(clean('<h2 id="pricing">Heading</h2>')).toBe('<h2 id="pricing">Heading</h2>')
    })

    it('keeps the source of a frame and a player, which is the whole node', () => {
        // This package's embed reads the video off the `src` of the frame inside it and
        // drops the node when it cannot, so an embed pasted from a page it rendered would
        // otherwise come back as nothing at all. The shape comes along for the same reason.
        const html = '<div data-type="embed" style="aspect-ratio: 16 / 9"><iframe src="https://www.youtube-nocookie.com/embed/abc" title="Video" allowfullscreen></iframe></div>'

        expect(clean(html)).toBe(
            '<div data-type="embed" style="aspect-ratio: 16 / 9">' +
            '<iframe src="https://www.youtube-nocookie.com/embed/abc" title="Video" allowfullscreen=""></iframe>' +
            '</div>',
        )

        expect(clean('<video src="/clip.mp4" controls></video>')).toContain('src="/clip.mp4"')
    })

    it('keeps what a table needs to keep its shape', () => {
        const html = '<table border=1 style="border-collapse:collapse"><tr><td colspan=2 width=100>Wide</td></tr></table>'

        expect(clean(html)).toContain('<td colspan="2">Wide</td>')
    })
})

describe('the spaces', () => {
    it('collapses the runs a word processor indents with', () => {
        expect(cleanPastedHtml('<p>One&nbsp;&nbsp;&nbsp;&nbsp;Two</p>')).toBe('<p>One Two</p>')
    })

    it('collapses a run that was split across two wrappers', () => {
        // Word writes its indentation one `mso-spacerun` span at a time, and unwrapping
        // them leaves two text nodes: a run that is only a run once the two are one node.
        const html = `<p class=MsoNormal>One<span style='mso-spacerun:yes'>&nbsp;</span><span style='mso-spacerun:yes'>&nbsp;</span>Two</p>`

        expect(cleanPastedHtml(html)).toBe('<p>One Two</p>')
    })

    it('keeps a single one, which is a decision somebody made', () => {
        expect(cleanPastedHtml('<p>10&nbsp;km</p>')).toBe('<p>10&nbsp;km</p>')
    })

    it('leaves the inside of a code block exactly as it is', () => {
        const html = '<pre><code>if (a)&nbsp;&nbsp;&nbsp;return</code></pre>'

        expect(cleanPastedHtml(html)).toBe(html)
    })
})

describe('a whole document', () => {
    it('comes out as headings, lists and paragraphs', () => {
        const html = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office">
            <head><style>p.MsoNormal { margin: 0 }</style></head>
            <body lang=EN-GB>
            <div class=WordSection1>
            <h1 style='mso-outline-level:1'><span style='font-family:"Calibri Light",sans-serif'>The heading</span></h1>
            <p class=MsoNormal style='font-size:11.0pt'>A <b>paragraph</b> with a <a href="https://example.com" style='color:#0563C1'>link</a>.<o:p></o:p></p>
            <p class=MsoNormal><o:p>&nbsp;</o:p></p>
            <p class=MsoListParagraphCxSpFirst style='mso-list:l0 level1 lfo1'><span style='mso-list:Ignore'>1.<span style='font:7.0pt "Times New Roman"'>&nbsp;&nbsp;</span></span>First</p>
            <p class=MsoListParagraphCxSpLast style='mso-list:l0 level1 lfo1'><span style='mso-list:Ignore'>2.<span style='font:7.0pt "Times New Roman"'>&nbsp;&nbsp;</span></span>Second</p>
            </div>
            </body></html>
        `

        expect(clean(html)).toBe(
            '<h1>The heading</h1>' +
            '<p>A <b>paragraph</b> with a <a href="https://example.com">link</a>.</p>' +
            '<ol><li>First</li><li>Second</li></ol>',
        )
    })
})
