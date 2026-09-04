/*
 * An uploaded document, on the editor's side.
 *
 * Filament loads this verbatim through a dynamic `import()`, so there are no `import`
 * statements here and TipTap is read from the global the editor publishes. See
 * `task-list.js` for the whole reasoning.
 *
 * The PHP half is `Nodes/FileCard.php` and carries the reasoning for the card, for why it
 * is a node rather than a decoration, and for why nothing rides in an attribute a sanitiser
 * can drop. What has to hold here is that the two halves draw the *same* card: this one
 * decides what a person sees while they write, that one decides what a reader gets, and a
 * card that changes shape on save is a card nobody trusts.
 *
 * So the markup is built once, in `fileCard()`, and both the serialiser and the node view
 * are handed it - the same arrangement `media.js` uses and for the same reason.
 */

/** Mirrors `Media/FileTypes::TINTS`. Grouped by what a file is for, not by its format. */
export const TINTS = {
    pdf: '#dc2626',
    doc: '#2563eb',
    docx: '#2563eb',
    odt: '#2563eb',
    rtf: '#2563eb',
    pages: '#2563eb',
    txt: '#2563eb',
    md: '#2563eb',
    csv: '#16a34a',
    tsv: '#16a34a',
    numbers: '#16a34a',
    ods: '#16a34a',
    xls: '#16a34a',
    xlsx: '#16a34a',
    key: '#ea580c',
    odp: '#ea580c',
    ppt: '#ea580c',
    pptx: '#ea580c',
    '7z': '#a16207',
    bz2: '#a16207',
    gz: '#a16207',
    rar: '#a16207',
    tar: '#a16207',
    zip: '#a16207',
}

/** Mirrors `Media/FileTypes::DEFAULT_TINT`. */
export const DEFAULT_TINT = '#52525b'

/**
 * Whether a string holds whitespace or a control character - the two things a scheme can
 * hide inside. Lifted from `media.js`, and written the same way for the same reason: a
 * character class would not be the same set on both sides of the feature.
 */
const hidden = (value) => {
    for (const character of value) {
        const code = character.codePointAt(0)

        if (code <= 0x20 || code === 0x7f) {
            return true
        }
    }

    return false
}

/**
 * An address this package is willing to point a card at, or null. Mirrors `MediaUrl::src`.
 */
export const fileSrc = (value) => {
    if (typeof value !== 'string') {
        return null
    }

    const trimmed = value.trim()

    if (trimmed === '' || hidden(trimmed)) {
        return null
    }

    const scheme = /^([a-z][a-z0-9+.-]*):/i.exec(trimmed)?.[1]?.toLowerCase()

    return scheme && scheme !== 'http' && scheme !== 'https' ? null : trimmed
}

/** Mirrors `Media/FileTypes::extensionOf`. */
const extensionOf = (value) => {
    if (typeof value !== 'string') {
        return ''
    }

    const path = value.split('#')[0].split('?')[0]
    const extension = (path.split('/').pop() ?? '').split('.').slice(1).pop() ?? ''

    return /^[a-z0-9]{1,8}$/.test(extension.toLowerCase()) ? extension.toLowerCase() : ''
}

/** Mirrors `Media/FileTypes::extension`: the name's ending, else the address's. */
const extensionFor = (name, src) => extensionOf(name) || extensionOf(src)

/** Mirrors `Media/FileTypes::label`. */
export const fileLabel = (name, src) =>
    (extensionFor(name, src) || 'file').slice(0, 4).toUpperCase()

/** Mirrors `Media/FileTypes::tint`. */
export const fileTint = (name, src) => TINTS[extensionFor(name, src)] ?? DEFAULT_TINT

/** Mirrors `Nodes/FileCard::filename`: what the card ends up calling the file. */
export const fileName = (name, src) => {
    if (typeof name === 'string' && name.trim() !== '') {
        return name.trim()
    }

    let basename = ''

    try {
        basename = decodeURIComponent(src.split('#')[0].split('?')[0].split('/').pop() ?? '')
    } catch {
        // A percent sign that is not an escape. The address is still an address; only its
        // last part is unreadable, and the whole of it is a better name than an empty one.
        basename = ''
    }

    return basename === '' ? src : basename
}

/** Mirrors `Media/ByteSize::label`: a size somebody wrote, kept only where it is short. */
export const fileSize = (value) => {
    if (typeof value !== 'string') {
        return null
    }

    const label = value.replace(/\s+/gu, ' ').trim()

    return label === '' || label.length > 32 ? null : label
}

/**
 * The card's parts, or null where there is no address to point at.
 *
 * Mirrors the constants and the inline styles on `Nodes/FileCard`. Written out here rather
 * than fetched from the element the editor mounts on, because these are not settings: a
 * project changing the card's look does it through the classes, which both halves emit.
 */
export const fileCard = (attrs) => {
    const src = fileSrc(attrs.src)

    if (src === null) {
        return null
    }

    const name = fileName(attrs.name, src)
    const size = fileSize(attrs.size)

    return {
        src,
        name,
        size,
        label: fileLabel(attrs.name, src),
        attributes: {
            class: 'fi-arte-file',
            'data-type': 'file',
            ...(attrs.id ? { 'data-id': attrs.id } : {}),
            href: src,
            download: name,
            style: 'box-sizing: border-box; display: inline-flex; align-items: center; vertical-align: top; gap: 0.75rem; max-width: 100%; margin: 0.25rem 0.5rem 0.25rem 0; padding: 0.75rem; border: 1px solid rgba(113, 113, 122, 0.25); border-radius: 0.5rem; text-decoration: none; color: inherit;',
        },
        kindStyle: `flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; margin: 0; border-radius: 0.375rem; background-color: ${fileTint(attrs.name, src)}; color: #ffffff; font-size: 0.625rem; font-weight: 700; line-height: 1; letter-spacing: 0.02em; text-indent: 0.02em;`,
        // The name over the size, in a column beside the tile - see `Nodes/FileCard.php`
        // for why. Short version: side by side, two attachments filled a line; stacked,
        // the column comes out the same height as the tile and the card as wide as the
        // name.
        // Two answers, not one - see `Nodes/FileCard.php`. Two lines want the height of the
        // tile and its top and bottom, so the size stands on the bottom line instead of
        // hanging off the name; one line wants the middle.
        textStyle:
            'display: inline-flex; flex-direction: column; align-items: flex-start; min-width: 0; margin: 0; gap: 0.125rem;' +
            (size === null
                ? ' justify-content: center;'
                : ' align-self: stretch; justify-content: space-between;'),
        // Both lines carry their own line-height and their own zero margin. Inherited, the
        // pair came to 38px against a 36px tile; and prose styles - Filament's in the
        // panel, a project's on the page - hand a 16px top margin to a document's children,
        // which turned a 50px card into a 78px one. See `Nodes/FileCard.php`.
        nameStyle:
            'max-width: 100%; margin: 0; overflow-wrap: anywhere; font-weight: 500; line-height: 1.2;',
        sizeStyle:
            'align-self: flex-end; margin: 0; font-size: 0.75rem; line-height: 1.2; opacity: 0.6; font-variant-numeric: tabular-nums;',
    }
}

/** The text of the one child carrying a class, or null. Mirrors `FileCard::textOf`. */
const textOf = (element, className) => {
    const span = element?.querySelector?.(`span.${className}`)
    const text = span?.textContent?.trim()

    return text ? text : null
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor file extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Node, mergeAttributes } = tiptap

    return Node.create({
        name: 'file',

        // Inline, and not a block - see `Nodes/FileCard.php` for the whole of it. Short
        // version: a block leaves 400 pixels of nothing beside every attachment, and
        // putting a card on a line of its own is what pressing return already does.
        group: 'inline',

        inline: true,

        atom: true,

        draggable: true,

        selectable: true,

        addAttributes() {
            return {
                src: {
                    default: null,
                    parseHTML: (element) => fileSrc(element.getAttribute('href')),
                    renderHTML: () => ({}),
                },
                name: {
                    default: null,
                    parseHTML: (element) =>
                        textOf(element, 'fi-arte-file-name') ||
                        element.getAttribute('download')?.trim() ||
                        // What the link said. A hand-written `<a download>Handbuch</a>`
                        // names the file, and only a card without a name span gets here -
                        // so this never reads a card's own three spans back as one name.
                        (element.textContent?.trim()?.length <= 120
                            ? element.textContent.trim()
                            : null) ||
                        null,
                    renderHTML: () => ({}),
                },
                // The size as its label, never as a number: Filament's sanitiser passes six
                // `data-*` names and `data-size` is not one, so a byte count parked there
                // would be gone by the time the page rendered. See `Media/ByteSize`.
                size: {
                    default: null,
                    parseHTML: (element) => fileSize(textOf(element, 'fi-arte-file-size')),
                    renderHTML: () => ({}),
                },
                id: {
                    default: null,
                    parseHTML: (element) => element.getAttribute('data-id') || null,
                    renderHTML: () => ({}),
                },
            }
        },

        parseHTML() {
            return [
                {
                    // `download` is the attribute that says "this link is a file to take
                    // away", so a download link written by hand or by another editor is
                    // already this node and comes back as a card. Nothing to migrate.
                    tag: 'a[download]',
                    getAttrs: (element) =>
                        fileSrc(element.getAttribute('href')) === null ? false : null,
                },
            ]
        },

        renderHTML({ node }) {
            const card = fileCard(node.attrs)

            // Nothing to fetch. An empty marker goes out rather than a link that does
            // nothing - and the PHP half drops the node entirely on its way to a reader.
            if (card === null) {
                return ['a', { 'data-type': 'file' }]
            }

            // The spaces between the spans are a separator, not stray whitespace: a flex
            // container drops a white-space run between two items, so the card looks the
            // same - but anything reading the document as text keeps the parts apart. See
            // `Nodes/FileCard::renderHTML()`.
            return [
                'a',
                mergeAttributes(card.attributes),
                ['span', { class: 'fi-arte-file-kind', style: card.kindStyle }, card.label],
                ' ',
                [
                    'span',
                    { class: 'fi-arte-file-text', style: card.textStyle },
                    ['span', { class: 'fi-arte-file-name', style: card.nameStyle }, card.name],
                    ...(card.size
                        ? [
                              ' ',
                              [
                                  'span',
                                  { class: 'fi-arte-file-size', style: card.sizeStyle },
                                  card.size,
                              ],
                          ]
                        : []),
                ],
            ]
        },

        /**
         * The same card, built as elements.
         *
         * A node view rather than letting the serialiser draw it, for the reason the embed
         * needs one: an `<a>` in a contenteditable answers a click by putting a cursor in
         * its text, so without this the three spans would be editable and the card could be
         * typed into. `contentEditable = 'false'` makes it a block that selects, drags and
         * deletes like every other one.
         */
        addNodeView() {
            return ({ node }) => {
                const dom = document.createElement('a')
                const card = fileCard(node.attrs)

                dom.className = 'fi-arte-file'
                dom.dataset.type = 'file'
                dom.contentEditable = 'false'

                if (card === null) {
                    dom.classList.add('fi-arte-file-empty')

                    return { dom }
                }

                for (const [name, value] of Object.entries(card.attributes)) {
                    dom.setAttribute(name, value)
                }

                // Not a link while it is being written. Following it would leave the form
                // with unsaved changes, and the editor is not where anyone reads a pdf.
                dom.addEventListener('click', (event) => event.preventDefault())

                const kind = document.createElement('span')
                kind.className = 'fi-arte-file-kind'
                kind.setAttribute('style', card.kindStyle)
                kind.textContent = card.label

                const text = document.createElement('span')
                text.className = 'fi-arte-file-text'
                text.setAttribute('style', card.textStyle)

                const name = document.createElement('span')
                name.className = 'fi-arte-file-name'
                name.setAttribute('style', card.nameStyle)
                name.textContent = card.name

                text.append(name)

                if (card.size) {
                    const size = document.createElement('span')
                    size.className = 'fi-arte-file-size'
                    size.setAttribute('style', card.sizeStyle)
                    size.textContent = card.size

                    text.append(size)
                }

                dom.append(kind, text)

                return { dom }
            }
        },

        addCommands() {
            return {
                setFile:
                    (attributes) =>
                    ({ commands }) =>
                        commands.insertContent({ type: this.name, attrs: attributes }),
            }
        },
    })
}
