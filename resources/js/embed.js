/*
 * Embeds from other sites, on the editor's side: videos, songs, maps, pens.
 *
 * What is stored is what the embed *is* - a service, an id, a timestamp - and the embed URL
 * is built from that on the way out. The PHP half does the same, from the same parts, which
 * is what lets `youtube-nocookie` stay a setting instead of a decision frozen into every
 * record ever saved.
 *
 * The editor does not draw the frame. A panel with ten embeds in it would load ten players,
 * each with its own network traffic and its own cookie, in an admin screen where nobody is
 * watching anything. What is drawn instead is a card naming what will be there - which is
 * also selectable, draggable and deletable like any other block, and which an iframe
 * swallowing every click would not be.
 *
 * No service is known here. Each one is described in PHP (`EmbedProvider`) and handed over
 * through the element the editor is mounted on; what follows reads those descriptions the
 * way `EmbedUrl` reads them, so the two halves cannot disagree about a link - this one
 * decides what a paste turns into, that one what a save keeps.
 */

let providers = []

/**
 * The services links are read against, as PHP described them.
 */
export function useProviders(list) {
    providers = Array.isArray(list) ? list : []
}

const providerNamed = (name) => providers.find((provider) => provider.name === name) ?? null

/**
 * Seconds out of `90`, `1m30s` or `2h5m`, which are the three shapes a share button writes.
 */
function seconds(value) {
    if (!value) {
        return null
    }

    const raw = String(value).toLowerCase().replace(/^t=/, '')

    if (/^\d+$/.test(raw)) {
        return Number(raw) > 0 ? Number(raw) : null
    }

    const match = /^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/.exec(raw)

    if (!match) {
        return null
    }

    const total =
        Number(match[1] ?? 0) * 3600 + Number(match[2] ?? 0) * 60 + Number(match[3] ?? 0)

    return total > 0 ? total : null
}

function accepts(provider, id) {
    return typeof id === 'string' && new RegExp(`^(?:${provider.id})$`).test(id)
}

/**
 * The id in a link: the named groups of the first pattern that yields a valid one, joined
 * by `/`.
 */
function read(provider, subject) {
    for (const pattern of provider.patterns) {
        const groups = new RegExp(pattern).exec(subject)?.groups

        if (!groups) {
            continue
        }

        const id = Object.values(groups)
            .filter((part) => typeof part === 'string' && part !== '')
            .join('/')

        if (accepts(provider, id)) {
            return id
        }
    }

    return null
}

/**
 * The service, the id and the timestamp behind a pasted link - or behind the embed code a
 * share dialog hands out - or null.
 */
export function parseEmbed(url) {
    if (!url) {
        return null
    }

    let link = String(url).trim()

    const code = /<iframe\b[^>]*\bsrc\s*=\s*(["'])(.*?)\1/is.exec(link)

    if (code) {
        const decoder = document.createElement('textarea')
        decoder.innerHTML = code[2]
        link = decoder.value.trim()
    }

    let parsed

    try {
        parsed = new URL(link.includes('://') ? link : `https://${link}`)
    } catch {
        return null
    }

    const host = parsed.hostname.toLowerCase()
    const provider = providers.find((candidate) => candidate.hosts.includes(host))

    if (!provider) {
        return null
    }

    const id = read(provider, `${host}${parsed.pathname}${parsed.search}`)

    if (!id) {
        return null
    }

    return {
        provider: provider.name,
        id,
        start: provider.start
            ? seconds(
                  parsed.searchParams.get('t') ??
                      parsed.searchParams.get('start') ??
                      parsed.hash.replace(/^#/, ''),
              )
            : null,
    }
}

/**
 * The address a browser frames, or null for a service that is not on the list.
 */
export function embedSrc(name, id, start) {
    const provider = providerNamed(name)

    if (!provider || !accepts(provider, id)) {
        return null
    }

    const parts = id.split('/')
    const address = provider.src.replace(/\{id(?::(\d+))?\}/g, (_match, index) =>
        index === undefined ? id : (parts[Number(index)] ?? ''),
    )

    return start > 0 && provider.start ? address + provider.start.replace('{start}', String(start)) : address
}

/**
 * A ratio in the one spelling the wrapper writes, or null.
 */
function ratioOf(value) {
    const match = /^\s*([0-9]+(?:\.[0-9]+)?)\s*\/\s*([0-9]+(?:\.[0-9]+)?)\s*$/.exec(String(value ?? ''))

    return match && Number(match[1]) > 0 && Number(match[2]) > 0 ? `${match[1]} / ${match[2]}` : null
}

/**
 * The wrapper's shape, as `Embed::box()` writes it: a player's own height, or a ratio - held
 * to the width of a phone where it stands upright.
 */
export function embedBox(name, ratio) {
    const provider = providerNamed(name)

    if (provider?.height) {
        return `width: 100%; height: ${provider.height}px;`
    }

    const shape = ratioOf(ratio) ?? ratioOf(provider?.ratio) ?? '16 / 9'
    const [width, height] = shape.split('/').map(Number)

    return `aspect-ratio: ${shape}; width: 100%;${width < height ? ' max-width: 22.5rem;' : ''}`
}

export function embedLabel(name) {
    return providerNamed(name)?.label ?? name ?? ''
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core
    const pmState = window.FilamentRichEditor?.tiptap?.pmState

    if (!tiptap || !pmState) {
        console.error(
            'The advanced rich editor embed extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Node, mergeAttributes } = tiptap
    const { Plugin, PluginKey } = pmState

    const readIframe = (element) => element.querySelector('iframe')

    const settingsOf = (editor) => {
        const raw = editor?.options?.element?.dataset?.arteEmbed

        if (!raw) {
            return { providers: [] }
        }

        try {
            return { providers: [], ...JSON.parse(raw) }
        } catch (error) {
            console.error('The advanced rich editor could not read its embed settings:', error)

            return { providers: [] }
        }
    }

    return Node.create({
        name: 'embed',

        group: 'block',

        atom: true,

        draggable: true,

        selectable: true,

        /**
         * The services come from PHP, through the element the editor was mounted on - the one
         * channel a TipTap extension has to the field it belongs to. Read before the content
         * is, since reading the content is reading links.
         */
        onBeforeCreate() {
            useProviders(settingsOf(this.editor).providers)
        },

        addAttributes() {
            return {
                provider: {
                    default: null,
                    parseHTML: (element) => parseEmbed(readIframe(element)?.getAttribute('src'))?.provider ?? null,
                    renderHTML: () => ({}),
                },
                id: {
                    default: null,
                    parseHTML: (element) => parseEmbed(readIframe(element)?.getAttribute('src'))?.id ?? null,
                    renderHTML: () => ({}),
                },
                start: {
                    default: null,
                    parseHTML: (element) => parseEmbed(readIframe(element)?.getAttribute('src'))?.start ?? null,
                    renderHTML: () => ({}),
                },
                title: {
                    default: null,
                    parseHTML: (element) => readIframe(element)?.getAttribute('title') || null,
                    renderHTML: () => ({}),
                },
                // None until somebody picks one, as in PHP: the service's own shape is the
                // default, and a TikTok left at widescreen would be drawn on its side.
                ratio: {
                    default: null,
                    parseHTML: (element) =>
                        ratioOf(
                            /aspect-ratio:\s*([0-9.]+\s*\/\s*[0-9.]+)/i.exec(
                                element.getAttribute('style') ?? '',
                            )?.[1],
                        ),
                    renderHTML: () => ({}),
                },
            }
        },

        parseHTML() {
            return [
                {
                    // `data-type` carries grids and custom blocks too, so the value is
                    // checked rather than only the attribute.
                    tag: 'div[data-type="embed"]',
                    getAttrs: (element) =>
                        parseEmbed(readIframe(element)?.getAttribute('src')) ? null : false,
                },
            ]
        },

        renderHTML({ node }) {
            const { provider, id, start, title, ratio } = node.attrs

            if (!provider || !id || !embedSrc(provider, id, start)) {
                return ['div', { 'data-type': 'embed' }]
            }

            return [
                'div',
                {
                    class: 'fi-arte-embed',
                    'data-type': 'embed',
                    style: embedBox(provider, ratio),
                },
                [
                    'iframe',
                    mergeAttributes({
                        src: embedSrc(provider, id, start),
                        ...(title ? { title } : {}),
                        loading: 'lazy',
                        allow: 'accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
                        allowfullscreen: 'true',
                        referrerpolicy: 'strict-origin-when-cross-origin',
                        style: 'width: 100%; height: 100%; border: 0;',
                    }),
                ],
            ]
        },

        addNodeView() {
            return ({ node }) => {
                const card = document.createElement('div')
                // Out of the typography, as the drag handle is: `.fi-prose` puts a top margin
                // on the second of two adjacent elements, and the title, beside the badge
                // rather than under it, sat that far below the middle of the card.
                card.className = 'fi-arte-embed-card fi-not-prose'
                card.dataset.type = 'embed'
                card.contentEditable = 'false'

                const provider = document.createElement('span')
                provider.className = 'fi-arte-embed-card-provider'
                provider.textContent = embedLabel(node.attrs.provider)

                const name = document.createElement('span')
                name.className = 'fi-arte-embed-card-title'
                name.textContent = node.attrs.title || node.attrs.id || ''

                card.append(provider, name)

                if (node.attrs.start) {
                    const start = document.createElement('span')
                    start.className = 'fi-arte-embed-card-start'
                    const minutes = Math.floor(node.attrs.start / 60)
                    const rest = node.attrs.start % 60
                    start.textContent = `${minutes}:${String(rest).padStart(2, '0')}`
                    card.append(start)
                }

                return { dom: card }
            }
        },

        addCommands() {
            return {
                setEmbed:
                    (attributes) =>
                    ({ commands }) =>
                        commands.insertContent({ type: this.name, attrs: attributes }),
            }
        },

        addProseMirrorPlugins() {
            const type = this.type

            return [
                new Plugin({
                    key: new PluginKey('arteEmbedPaste'),
                    props: {
                        handlePaste: (view, event) => {
                            const text = event.clipboardData?.getData('text/plain')?.trim()

                            // A link is one word. The embed code a map or a pen is shared as
                            // is not, and is read the same way.
                            if (!text || (/\s/.test(text) && !/^<iframe\b/i.test(text))) {
                                return false
                            }

                            const embed = parseEmbed(text)

                            if (!embed) {
                                return false
                            }

                            // Only where the link would be the whole line. Pasting a video
                            // URL into the middle of a sentence means the URL, not a player
                            // dropped into the paragraph.
                            const { $from, empty } = view.state.selection

                            if (!empty || $from.parent.content.size > 0 || $from.parent.type.spec.code) {
                                return false
                            }

                            view.dispatch(
                                view.state.tr.replaceSelectionWith(type.create(embed)),
                            )

                            return true
                        },
                    },
                }),
            ]
        },
    })
}
