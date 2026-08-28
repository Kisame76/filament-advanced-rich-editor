/*
 * What is wrong with this document, said while there is still somebody to say it to.
 *
 * Six questions, and they are the six that a person writing an article can answer and
 * nobody downstream can: a picture nobody described, a link whose text is "click here", a
 * heading level jumped over, a table with no header row, a link with nothing in it, and a
 * colour somebody chose that cannot be read on the page it is going to. None of them is
 * something a stylesheet or a renderer can put right later - they are all decisions, and
 * the moment to make them is the moment the content is being written.
 *
 * The check runs in the browser and not on the server, and that is the whole point rather
 * than a convenience. A report that arrives after saving is a list of things to go back and
 * find; a panel in the editor is a list of things to click on. Every finding carries the
 * position it was found at, so a row in the panel selects the picture, the link or the
 * heading it is about.
 *
 * The judging is separate from the looking, which is why any of this can be held to
 * account. `subjectsOf()` walks the document and writes down what it saw - a picture and
 * its alt text, a link and its text, a heading and its level - as plain data with positions
 * attached. `findingsFor()` reads that list and knows every rule; it has never seen a
 * document, and it does not need one to be wrong or right about a contrast ratio.
 *
 * Contrast is the rule with an assumption in it, and the assumption is stated rather than
 * hidden: the editor cannot know what colour the page will be, because that belongs to the
 * front end. It is checked against `accessibility.background`, white unless a project says
 * otherwise, and against the light half of the palette. A project rendering this content on
 * another colour says so there, and one that renders it twice - light and dark - is asking
 * a question this cannot answer for both at once.
 */

/** Every rule, in the order a report reads best in: the picture, the links, the structure. */
export const RULES = [
    'missing_alt',
    'decorative_link',
    'empty_link',
    'weak_link_text',
    'skipped_heading',
    'table_without_header',
    'weak_contrast',
]

/** WCAG AA, which is the level almost every rule anybody is held to means. */
const THRESHOLD = 4.5

/** The same level for large text, which needs less contrast to be as legible. */
const LARGE_THRESHOLD = 3

/** From what size text counts as large: eighteen point, which is where WCAG puts it. */
const LARGE_SIZE = 24

/** What a CSS length is worth in pixels, for the units a font size is written in. */
const UNITS = { px: 1, pt: 96 / 72, rem: 16, em: 16 }

/**
 * A colour as three channels, or null for anything this cannot be sure about.
 *
 * Anything translucent answers null rather than a guess. A ratio computed from a colour
 * that is half of what will be on the page is not a cautious answer, it is a wrong one, and
 * a wrong finding costs more than a missing one: it is the finding that teaches people to
 * stop reading the panel.
 */
export function parseColor(value) {
    if (typeof value !== 'string') {
        return null
    }

    const text = value.trim().toLowerCase()

    const hex = /^#([0-9a-f]{3}|[0-9a-f]{6})$/.exec(text)

    if (hex) {
        const digits = hex[1].length === 3
            ? hex[1].split('').map((digit) => digit + digit).join('')
            : hex[1]

        return {
            r: parseInt(digits.slice(0, 2), 16),
            g: parseInt(digits.slice(2, 4), 16),
            b: parseInt(digits.slice(4, 6), 16),
        }
    }

    const rgb = /^rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)\s*(?:[,/]\s*([\d.%]+)\s*)?\)$/.exec(text)

    if (! rgb) {
        return null
    }

    if (rgb[4] !== undefined && parseFloat(rgb[4]) < (rgb[4].endsWith('%') ? 100 : 1)) {
        return null
    }

    return { r: Number(rgb[1]), g: Number(rgb[2]), b: Number(rgb[3]) }
}

const channel = (value) => {
    const part = value / 255

    return part <= 0.03928 ? part / 12.92 : ((part + 0.055) / 1.055) ** 2.4
}

/**
 * How much light a colour gives back, the way WCAG defines it - which is not how bright it
 * looks: green counts for seven times what blue does.
 */
export function relativeLuminance({ r, g, b }) {
    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b)
}

/**
 * The ratio between two colours, from 1 for two of the same to 21 for black on white. Null
 * where either of them was not a colour this could read.
 */
export function contrastRatio(one, two) {
    if (! one || ! two) {
        return null
    }

    const first = relativeLuminance(one)
    const second = relativeLuminance(two)

    return (Math.max(first, second) + 0.05) / (Math.min(first, second) + 0.05)
}

/**
 * A font size in pixels, whatever it was written in.
 *
 * The unit is not decoration: eighteen point is exactly the size WCAG calls large, and a
 * comparison that read the number and ignored the `pt` would call it 18 and hold it to the
 * stricter of the two ratios - a finding against text that passes.
 */
export function sizeInPixels(value) {
    const match = /^([\d.]+)\s*(px|pt|rem|em)?$/i.exec(String(value ?? '').trim())

    if (! match) {
        return 0
    }

    return parseFloat(match[1]) * (UNITS[(match[2] ?? 'px').toLowerCase()] ?? 1)
}

/**
 * Whether a link says nothing about where it goes.
 *
 * The whole text has to be the phrase, rather than contain it: "click here for the report"
 * tells somebody reading a list of links what the link is, and "click here" does not. A rule
 * matching anywhere in the text would flag the first one, and a check that is wrong about
 * things people did correctly is a check people switch off.
 */
export function isWeakLinkText(text, phrases = []) {
    // Trimmed before the punctuation is taken off and again after: a link written as
    // "read more… " ends in a space, so a rule anchored at the end of the string finds no
    // punctuation there at all.
    const normalised = String(text ?? '')
        .toLowerCase()
        .replace(/[\s\u00A0]+/g, ' ')
        .trim()
        .replace(/[.!?,:;…"'»«„“”]+$/g, '')
        .trim()

    return normalised !== '' && phrases.some(
        (phrase) => normalised === String(phrase).toLowerCase().trim(),
    )
}

/**
 * Runs of text that say the same thing, put back together.
 *
 * A link over `click <strong>here</strong>` is two text nodes and one link, and judging them
 * apart would find a link whose text is "here" that nobody wrote. Only runs that touch are
 * joined: two links to the same place with a word between them are two links.
 */
export function mergeRuns(runs, keyOf) {
    const merged = []

    for (const run of runs) {
        const last = merged[merged.length - 1]

        if (last && last.to === run.from && keyOf(last) === keyOf(run)) {
            last.to = run.to
            last.text += run.text

            continue
        }

        merged.push({ ...run })
    }

    return merged
}

/**
 * A colour name resolved against the palette a project configured, or taken as it stands.
 *
 * Filament stores the name of a palette entry rather than the colour itself, which is the
 * right way round - a project that restyles its palette restyles everything written with it
 * - and it means the colour only exists where the palette does.
 */
export function resolveColor(value, palette = {}) {
    if (typeof value !== 'string' || value === '') {
        return null
    }

    return parseColor(palette[value] ?? value)
}

const finding = (rule, subject, text) => ({
    rule,
    from: subject.from,
    to: subject.to,
    node: Boolean(subject.node),
    text,
})

/**
 * Every rule, over what the walk wrote down.
 *
 * Pure, and the reason the rules can be argued about without an editor open: a heading
 * sequence is a list of numbers, a contrast ratio is two colours, and a weak link is a
 * string and a list of phrases.
 */
export function findingsFor(subjects, options = {}) {
    const {
        rules = RULES,
        weakPhrases = [],
        threshold = THRESHOLD,
        largeThreshold = LARGE_THRESHOLD,
        background = '#ffffff',
        // What the page paints text in where nobody chose a colour. The editor cannot know
        // it any more than it knows the background, and both are the front end's business.
        text = '#18181b',
        palette = {},
    } = options

    const enabled = new Set(rules)
    const findings = []
    const page = parseColor(background)

    let previousLevel = null

    for (const subject of subjects) {
        switch (subject.kind) {
            case 'image':
                // A picture marked as carrying nothing is a decision, not an omission. It
                // reaches here with the same empty alt as one somebody forgot to describe,
                // and the only thing telling them apart is that somebody said so - which is
                // exactly why the mark exists. Reporting a decision is how a check teaches
                // people to switch it off.
                if (enabled.has('missing_alt') && subject.decorative !== true && ! String(subject.alt ?? '').trim()) {
                    findings.push(finding('missing_alt', subject, subject.src ?? ''))
                }

                // A marked picture that is also a link. Each half is right on its own, which
                // is why nothing else catches the pair: the mark tells a screen reader to
                // skip the picture, the picture is the whole of the link, and what is
                // announced is "link" and nothing else. The alt text is what would name it,
                // and the mark is a promise that there is none.
                if (enabled.has('decorative_link') && subject.decorative === true && String(subject.href ?? '').trim()) {
                    findings.push(finding('decorative_link', subject, subject.href))
                }

                break

            case 'link': {
                const text = String(subject.text ?? '')

                // Empty first: a link with nothing in it is also a link whose text says
                // nothing about where it goes, and one finding is the useful number.
                if (enabled.has('empty_link') && text.replace(/[\s\u00A0]+/g, '') === '') {
                    findings.push(finding('empty_link', subject, subject.href ?? ''))
                } else if (enabled.has('weak_link_text') && isWeakLinkText(text, weakPhrases)) {
                    findings.push(finding('weak_link_text', subject, text))
                }

                break
            }

            case 'heading':
                // Only a jump, and never the level a document starts at: an article whose
                // page already carries the `<h1>` starts at two, and one inside a card may
                // reasonably start at three. What no document can defend is two, then four.
                if (
                    enabled.has('skipped_heading') &&
                    previousLevel !== null &&
                    subject.level > previousLevel + 1
                ) {
                    findings.push(finding('skipped_heading', subject, subject.text ?? ''))
                }

                previousLevel = subject.level

                break

            case 'table':
                if (enabled.has('table_without_header') && ! subject.headerCells) {
                    findings.push(finding('table_without_header', subject, subject.text ?? ''))
                }

                break

            case 'colour': {
                if (! enabled.has('weak_contrast')) {
                    break
                }

                // Either half may be the one somebody chose. Text in the default colour on
                // a background out of the palette is the same failure as a colour on the
                // default background, and it is the more common one.
                const colour = subject.color ? resolveColor(subject.color, palette) : parseColor(text)
                const behind = subject.background ? resolveColor(subject.background, palette) : page
                const ratio = contrastRatio(colour, behind)

                if (ratio === null) {
                    break
                }

                const needed = subject.large ? largeThreshold : threshold

                if (ratio < needed) {
                    findings.push({
                        ...finding('weak_contrast', subject, subject.text ?? ''),
                        ratio: Math.round(ratio * 100) / 100,
                        needed,
                    })
                }

                break
            }
        }
    }

    return findings
}

/**
 * What the document has to say about itself, as plain data with positions attached.
 *
 * Mechanical on purpose: everything that is a decision lives in `findingsFor()`, and this
 * only writes down what is there. A subject is a range - `from` and `to` - so that a row in
 * the panel can select what it is about, and `node` says whether that range is one node,
 * which is the difference between selecting a picture and selecting the words in a link.
 */
export function subjectsOf(doc) {
    const subjects = []
    const links = []
    const colours = []

    doc.descendants((node, pos, parent) => {
        const from = pos
        const to = pos + node.nodeSize

        if (node.type.name === 'image') {
            subjects.push({
                kind: 'image',
                from,
                to,
                node: true,
                alt: node.attrs.alt,
                src: node.attrs.src,
                decorative: node.attrs.decorative === true,
                href: node.attrs.href ?? null,
            })

            return false
        }

        if (node.type.name === 'heading') {
            subjects.push({ kind: 'heading', from, to, node: true, level: node.attrs.level, text: node.textContent })

            return true
        }

        if (node.type.name === 'table') {
            subjects.push({
                kind: 'table',
                from,
                to,
                node: true,
                headerCells: headerCellsIn(node),
                // The first row, so that three tables with no header row are three rows in
                // the panel saying which is which rather than the same sentence three times.
                text: node.firstChild?.textContent ?? '',
            })

            return true
        }

        if (! node.isText) {
            return true
        }

        const link = node.marks.find((mark) => mark.type.name === 'link')

        if (link) {
            links.push({ kind: 'link', from, to, href: link.attrs.href ?? '', text: node.text ?? '' })
        }

        const colour = node.marks.find((mark) => mark.type.name === 'textColor')
        const background = node.marks.find((mark) => mark.type.name === 'textBackground')

        // Either mark is worth measuring: text left in the page's own colour on a chosen
        // background is as unreadable as a chosen colour on the page's own background.
        if (colour || background) {
            const size = node.marks.find((mark) => mark.type.name === 'fontSize')

            colours.push({
                kind: 'colour',
                from,
                to,
                text: node.text ?? '',
                color: colour ? (colour.attrs['data-color'] ?? colour.attrs.color ?? null) : null,
                background: background?.attrs?.color ?? null,
                large: isLarge(parent, size),
            })
        }

        return false
    })

    return [
        ...subjects,
        ...mergeRuns(links, (run) => run.href),
        ...mergeRuns(colours, (run) => `${run.color}|${run.background}|${run.large}`),
    ].sort((one, two) => one.from - two.from)
}

/**
 * Whether text is large enough to be held to the easier of the two thresholds: a heading of
 * the first two levels, or anything a font size was set on that is at least eighteen point.
 */
function isLarge(parent, size) {
    if (parent?.type?.name === 'heading' && (parent.attrs?.level ?? 6) <= 2) {
        return true
    }

    return sizeInPixels(size?.attrs?.size) >= LARGE_SIZE
}

/**
 * How many header cells the first row of a table has. A table whose first row is ordinary
 * cells is a grid of numbers that a screen reader reads out with nothing to attach them to.
 */
function headerCellsIn(table) {
    const row = table.firstChild

    if (! row) {
        return 0
    }

    let headers = 0

    row.forEach((cell) => {
        if (cell.type.name === 'tableHeader') {
            headers += 1
        }
    })

    return headers
}

/**
 * The shared panel, fetched once per page rather than imported at the top of this file: a
 * static import would resolve without the version query Filament serves this module with,
 * and a package upgrade would then be served the old panel out of the cache.
 */
let panel = null

let panelPromise = null

function loadPanel() {
    if (! panelPromise) {
        const here = new URL(import.meta.url)
        const url = new URL('./floating-panel.js', here)

        url.search = here.search

        panelPromise = import(url.href)
            .then((module) => (panel = module))
            .catch((error) => {
                console.error('The advanced rich editor could not load its floating panel:', error)

                panelPromise = null

                return null
            })
    }

    return panelPromise
}

loadPanel()

function settingsOf(editor) {
    const raw = editor.options.element?.dataset?.arteAccessibility

    if (! raw) {
        return null
    }

    try {
        return JSON.parse(raw)
    } catch (error) {
        console.error('The advanced rich editor could not read its accessibility settings:', error)

        return null
    }
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core
    const pmState = window.FilamentRichEditor?.tiptap?.pmState

    if (! tiptap || ! pmState) {
        console.error(
            'The advanced rich editor accessibility check needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap
    const { NodeSelection, Plugin, PluginKey, TextSelection } = pmState

    /**
     * The report: a window listing what is wrong, and a way to get to each of them.
     */
    class Report {
        constructor(view, settings) {
            this.view = view
            this.settings = settings
            this.window = null
        }

        findings() {
            return findingsFor(subjectsOf(this.view.state.doc), this.settings)
        }

        open() {
            // A second press puts it away. The button lives in the toolbar, which the panel
            // is told to stay open for, so the press reaches this rather than being taken
            // as a click outside - which is what would otherwise close and reopen it, and
            // lose wherever it had been dragged to on the way.
            if (this.window) {
                return this.close()
            }

            if (! panel) {
                return loadPanel().then(() => panel && this.open())
            }

            const element = document.createElement('div')
            element.className = 'fi-arte-a11y'

            const header = document.createElement('div')
            header.className = 'fi-arte-a11y-header'

            const grip = document.createElement('span')
            grip.className = 'fi-arte-a11y-grip'
            grip.innerHTML = this.settings.icons?.grip ?? ''

            const title = document.createElement('p')
            title.className = 'fi-arte-a11y-title'
            title.textContent = this.settings.labels?.title ?? ''

            const close = document.createElement('button')
            close.type = 'button'
            close.className = 'fi-arte-a11y-close'
            close.title = this.settings.labels?.close ?? ''
            close.setAttribute('aria-label', this.settings.labels?.close ?? '')
            close.innerHTML = this.settings.icons?.close ?? ''
            close.addEventListener('click', () => this.window?.close())

            header.append(grip, title, close)

            this.list = document.createElement('div')
            this.list.className = 'fi-arte-a11y-list'

            element.append(header, this.list)

            this.window = panel.floatingPanel(element, {
                handle: header,
                // The toolbar as well as the text: pressing another button while the report
                // is open is how somebody fixes what it is pointing at, and the button that
                // opened it has to be able to close it again.
                keepOpenInside: [this.view.dom, this.toolbar()].filter(Boolean),
                onClose: () => {
                    this.window = null
                    this.list = null
                },
                onResize: () => this.place(element),
            })

            // Drawn before it is placed: the panel is a header and nothing else until the
            // list is in it, and a corner worked out from that height puts a full panel off
            // the bottom of the screen.
            this.draw()
            this.place(element)
        }

        toolbar() {
            return this.view.dom.closest('.fi-fo-rich-editor')?.querySelector('.fi-fo-rich-editor-toolbar') ?? null
        }

        place(element) {
            const where = panel.cornerOf(
                this.view.dom.getBoundingClientRect(),
                element.getBoundingClientRect(),
                { width: window.innerWidth, height: window.innerHeight },
            )

            element.style.left = `${where.left}px`
            element.style.top = `${where.top}px`
        }

        /**
         * The list, rebuilt from the document as it stands.
         */
        draw() {
            if (! this.list) {
                return
            }

            const findings = this.findings()

            this.list.replaceChildren()

            if (! findings.length) {
                const empty = document.createElement('p')
                empty.className = 'fi-arte-a11y-empty'
                empty.textContent = this.settings.labels?.empty ?? ''
                this.list.append(empty)

                return
            }

            for (const found of findings) {
                this.list.append(this.row(found))
            }
        }

        row(found) {
            const button = document.createElement('button')
            button.type = 'button'
            button.className = 'fi-arte-a11y-finding'

            const label = document.createElement('span')
            label.className = 'fi-arte-a11y-rule'
            label.textContent = this.settings.labels?.rules?.[found.rule] ?? found.rule

            const context = document.createElement('span')
            context.className = 'fi-arte-a11y-context'
            // The ratio is the one finding where the number is the whole of the answer -
            // "not enough" is not something anybody can act on, and 3.1 against 4.5 is.
            context.textContent = found.rule === 'weak_contrast'
                ? (this.settings.labels?.ratio ?? ':ratio / :needed')
                    .replace(':ratio', String(found.ratio))
                    .replace(':needed', String(found.needed))
                : found.text

            button.append(label, context)
            button.addEventListener('click', () => this.go(found))

            return button
        }

        /**
         * The thing a finding is about, selected and scrolled to.
         */
        go(found) {
            const { doc } = this.view.state

            if (found.from > doc.content.size) {
                // Edited away since the list was drawn.
                return this.draw()
            }

            let selection

            try {
                selection = found.node
                    ? NodeSelection.create(doc, found.from)
                    : TextSelection.create(doc, found.from, Math.min(found.to, doc.content.size))
            } catch {
                return this.draw()
            }

            this.view.dispatch(this.view.state.tr.setSelection(selection).scrollIntoView())
            this.view.focus()
        }

        close() {
            this.window?.close()
        }
    }

    return Extension.create({
        name: 'arteAccessibility',

        addStorage() {
            return { report: null }
        },

        addProseMirrorPlugins() {
            const editor = this.editor
            const storage = this.storage

            return [
                new Plugin({
                    key: new PluginKey('arteAccessibility'),
                    view: (view) => {
                        const settings = settingsOf(editor)

                        if (! settings) {
                            return {}
                        }

                        const report = new Report(view, settings)

                        // The extension's own storage rather than a property hung on the
                        // editor: that is the drawer TipTap provides for this, it is cleared
                        // with the extension, and it takes no name in a namespace this
                        // package does not own.
                        storage.report = report

                        return {
                            // Only while it is open, and only when the document actually
                            // changed: a list that went stale the moment somebody fixed
                            // something would be a list nobody trusts.
                            update: (_, previous) => {
                                if (report.window && ! previous.doc.eq(view.state.doc)) {
                                    report.draw()
                                }
                            },
                            destroy: () => {
                                report.close()
                                storage.report = null
                            },
                        }
                    },
                }),
            ]
        },

        addCommands() {
            return {
                openAccessibilityReport: () => () => {
                    this.storage.report?.open()

                    return true
                },
            }
        },
    })
}
