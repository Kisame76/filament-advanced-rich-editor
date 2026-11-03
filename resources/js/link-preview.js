/*
 * A link card, on the editor's side.
 *
 * Filament loads this verbatim through a dynamic `import()`, so there are no `import`
 * statements here and TipTap is read from the global the editor publishes. See
 * `task-list.js` for the whole reasoning.
 *
 * The PHP half is `Nodes/LinkPreview.php` and carries the reasoning: why what the card says is
 * written down when it is made, why the icon travels inside it, and why its shape is inline.
 * What has to hold here is that the two halves draw the *same* card - so the parts are built
 * once, in `linkPreview()`, and both the serialiser and the node view are handed them, the
 * arrangement `file-card.js` uses.
 */

/** Mirrors `Nodes/LinkPreview::*_LENGTH`. */
export const LENGTHS = { title: 200, description: 300, site: 80, icon: 16384 }

/** Mirrors `Nodes/LinkPreview::ICON_TYPES`. */
export const ICON_TYPES = [
    'image/png',
    'image/gif',
    'image/jpeg',
    'image/webp',
    'image/x-icon',
    'image/vnd.microsoft.icon',
    'image/svg+xml',
]

/** Mirrors the inline styles on `Nodes/LinkPreview`. */
export const STYLES = {
    card: 'box-sizing: border-box; display: flex; flex-direction: column; gap: 0.25rem; max-width: 100%; margin: 1rem 0; padding: 0.875rem 1rem; border: 1px solid rgba(113, 113, 122, 0.25); border-radius: 0.5rem; text-decoration: none; color: inherit;',
    title: 'margin: 0; font-weight: 600; line-height: 1.3; overflow-wrap: anywhere;',
    description:
        'display: -webkit-box; margin: 0; overflow: hidden; font-size: 0.875rem; line-height: 1.4; opacity: 0.75; -webkit-box-orient: vertical; -webkit-line-clamp: 2;',
    site: 'display: flex; align-items: center; gap: 0.375rem; margin: 0.25rem 0 0; font-size: 0.75rem; line-height: 1.2; opacity: 0.6;',
    icon: 'flex: 0 0 auto; width: 1rem; height: 1rem; margin: 0;',
}

/**
 * Whether a string holds whitespace or a control character - the two things a scheme can hide
 * inside. Lifted from `file-card.js`, which lifted it from `media.js`: a character class would
 * not be the same set on both sides of the feature.
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

/** Mirrors `Nodes/LinkPreview::href`: a page on the web, and nothing a browser would run. */
export const previewHref = (href) => {
    if (typeof href !== 'string') {
        return null
    }

    const trimmed = href.trim()

    // A control character or a space inside the scheme is how `java\nscript:` gets past a
    // check that only reads the front of the string.
    if (hidden(trimmed)) {
        return null
    }

    // Spelled out the way PHP's `parse_url()` needs to find a host. The browser's parser is
    // kinder - it reads `https:example.com` and `https:\\example.com` as addresses - and a card
    // the editor drew from one was dropped by the server on the next save.
    if (!/^https?:\/\//i.test(trimmed)) {
        return null
    }

    let url

    try {
        url = new URL(trimmed)
    } catch {
        return null
    }

    return ['http:', 'https:'].includes(url.protocol) && url.hostname !== '' ? trimmed : null
}

/** Mirrors `Nodes/LinkPreview::text`: one line, cut to a length, or null where nothing is left. */
export const previewText = (text, length) => {
    if (typeof text !== 'string') {
        return null
    }

    const line = text.replace(/\s+/gu, ' ').trim()

    if (line === '') {
        return null
    }

    const characters = [...line]

    return characters.length <= length ? line : `${characters.slice(0, length).join('').trimEnd()}…`
}

/** Mirrors `Nodes/LinkPreview::site`: the name given, or the host without `www.`. */
export const previewSite = (site, href) =>
    previewText(site, LENGTHS.site) ?? new URL(href).hostname.replace(/^www\./iu, '')

/** Mirrors `Nodes/LinkPreview::icon`: a small picture in a `data:` address, and nothing else. */
export const previewIcon = (icon) => {
    if (typeof icon !== 'string' || icon.length > LENGTHS.icon) {
        return null
    }

    const match = /^data:([a-z0-9.+/-]+);base64,[A-Za-z0-9+/]+={0,2}$/iu.exec(icon)

    return match && ICON_TYPES.includes(match[1].toLowerCase()) ? icon : null
}

/**
 * The card's parts, or null where there is no page to point at.
 */
export const linkPreview = (attrs) => {
    const href = previewHref(attrs?.href)

    if (href === null) {
        return null
    }

    return {
        href,
        title: previewText(attrs.title, LENGTHS.title) ?? href,
        description: previewText(attrs.description, LENGTHS.description),
        site: previewSite(attrs.site, href),
        icon: previewIcon(attrs.icon),
        attributes: {
            class: 'fi-arte-link-preview',
            'data-type': 'link-preview',
            href,
            style: STYLES.card,
        },
    }
}

const partOf = (element, name) => element.querySelector(`.fi-arte-link-preview-${name}`)

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor link card extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Node, mergeAttributes } = tiptap

    return Node.create({
        name: 'linkPreview',

        group: 'block',

        atom: true,

        draggable: true,

        selectable: true,

        addAttributes() {
            return {
                href: {
                    default: null,
                    parseHTML: (element) => previewHref(element.getAttribute('href')),
                    renderHTML: () => ({}),
                },
                title: {
                    default: null,
                    parseHTML: (element) => previewText(partOf(element, 'title')?.textContent, LENGTHS.title),
                    renderHTML: () => ({}),
                },
                description: {
                    default: null,
                    parseHTML: (element) =>
                        previewText(partOf(element, 'description')?.textContent, LENGTHS.description),
                    renderHTML: () => ({}),
                },
                site: {
                    default: null,
                    parseHTML: (element) => previewText(partOf(element, 'site')?.textContent, LENGTHS.site),
                    renderHTML: () => ({}),
                },
                icon: {
                    default: null,
                    parseHTML: (element) => previewIcon(partOf(element, 'icon')?.getAttribute('src')),
                    renderHTML: () => ({}),
                },
            }
        },

        parseHTML() {
            return [
                {
                    tag: 'a[data-type="link-preview"]',
                    // Above the link mark, which takes the same `<a>` by its `href`: ProseMirror
                    // asks marks before nodes at equal priority - see `file-card.js`.
                    priority: 60,
                    getAttrs: (element) => (previewHref(element.getAttribute('href')) === null ? false : null),
                },
            ]
        },

        renderHTML({ node }) {
            const card = linkPreview(node.attrs)

            // Nothing to point at. The PHP half drops the node on its way to a reader.
            if (card === null) {
                return ['a', { 'data-type': 'link-preview' }]
            }

            // The spaces between the parts are separators - see `Nodes/LinkPreview::renderHTML()`.
            return [
                'a',
                mergeAttributes(card.attributes),
                ['span', { class: 'fi-arte-link-preview-title', style: STYLES.title }, card.title],
                ...(card.description
                    ? [
                          ' ',
                          [
                              'span',
                              { class: 'fi-arte-link-preview-description', style: STYLES.description },
                              card.description,
                          ],
                      ]
                    : []),
                ' ',
                [
                    'span',
                    { class: 'fi-arte-link-preview-site', style: STYLES.site },
                    ...(card.icon
                        ? [
                              [
                                  'img',
                                  {
                                      class: 'fi-arte-link-preview-icon',
                                      src: card.icon,
                                      role: 'presentation',
                                      alt: '',
                                      width: '16',
                                      height: '16',
                                      style: STYLES.icon,
                                  },
                              ],
                              ' ',
                          ]
                        : []),
                    card.site,
                ],
            ]
        },

        /**
         * The same card, built as elements - for the reason the file card needs one: an `<a>` in a
         * contenteditable answers a click with a cursor in its text, and the card would be typed
         * into. Not followed while it is being written, either; that would leave the form.
         */
        addNodeView() {
            return ({ node }) => {
                const dom = document.createElement('a')
                const card = linkPreview(node.attrs)

                dom.className = 'fi-arte-link-preview'
                dom.dataset.type = 'link-preview'
                dom.contentEditable = 'false'
                dom.addEventListener('click', (event) => event.preventDefault())

                if (card === null) {
                    return { dom }
                }

                for (const [name, value] of Object.entries(card.attributes)) {
                    dom.setAttribute(name, value)
                }

                const part = (name, text) => {
                    const element = document.createElement('span')
                    element.className = `fi-arte-link-preview-${name}`
                    element.setAttribute('style', STYLES[name])
                    element.textContent = text ?? ''

                    return element
                }

                dom.append(part('title', card.title))

                if (card.description) {
                    dom.append(part('description', card.description))
                }

                const site = part('site', card.site)

                if (card.icon) {
                    const icon = document.createElement('img')
                    icon.className = 'fi-arte-link-preview-icon'
                    icon.src = card.icon
                    icon.setAttribute('role', 'presentation')
                    icon.alt = ''
                    icon.width = 16
                    icon.height = 16
                    icon.setAttribute('style', STYLES.icon)
                    site.prepend(icon)
                }

                dom.append(site)

                return { dom }
            }
        },

        addCommands() {
            return {
                setLinkPreview:
                    (attributes) =>
                    ({ commands }) =>
                        commands.insertContent({ type: this.name, attrs: attributes }),
            }
        },
    })
}
