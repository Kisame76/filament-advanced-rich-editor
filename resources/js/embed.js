/*
 * Video embeds, on the editor's side.
 *
 * What is stored is what the video *is* - a provider, an id, a timestamp - and the embed
 * URL is built from that on the way out. The PHP half does the same, from the same parts,
 * which is what lets `youtube-nocookie` stay a setting instead of a decision frozen into
 * every record ever saved.
 *
 * The editor does not draw the video. A panel with ten embeds in it would load ten players
 * from YouTube, each with its own network traffic and its own cookie, in an admin screen
 * where nobody is watching anything. What is drawn instead is a card naming what will be
 * there - which is also selectable, draggable and deletable like any other block, and
 * which an iframe swallowing every click would not be.
 *
 * The link parsing below mirrors `EmbedUrl` in PHP. The two must agree: this half decides
 * what a paste turns into, that half decides what a save keeps. The host list is not
 * duplicated - it comes from the config through the element the editor is mounted on -
 * but the shapes a share link comes in are written out in both places, so a provider added
 * to one has to be added to the other.
 */

const PATTERNS = {
    youtube: {
        hosts: ['youtube.com', 'youtu.be', 'youtube-nocookie.com'],
        id: /^[A-Za-z0-9_-]{6,20}$/,
    },
    vimeo: {
        hosts: ['vimeo.com'],
        id: /^[0-9]{6,12}$/,
    },
}

const hostMatches = (host, suffix) => host === suffix || host.endsWith(`.${suffix}`)

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

/**
 * The provider, the video and the timestamp behind a pasted link, or null.
 */
export function parseEmbed(url) {
    if (!url) {
        return null
    }

    let parsed

    try {
        parsed = new URL(String(url).trim().includes('://') ? url : `https://${url}`)
    } catch {
        return null
    }

    const host = parsed.hostname.toLowerCase()
    const provider = Object.keys(PATTERNS).find((name) =>
        PATTERNS[name].hosts.some((suffix) => hostMatches(host, suffix)),
    )

    if (!provider) {
        return null
    }

    const path = parsed.pathname
    let id = null

    if (provider === 'vimeo') {
        id = /\/(?:video\/)?(\d+)/.exec(path)?.[1] ?? null
    } else if (host === 'youtu.be') {
        id = path.replace(/^\/+/, '').split('/')[0] || null
    } else {
        id =
            /^\/(?:embed|shorts|v|live)\/([^/?#]+)/.exec(path)?.[1] ??
            parsed.searchParams.get('v')
    }

    if (!id || !PATTERNS[provider].id.test(id)) {
        return null
    }

    return {
        provider,
        id,
        start: seconds(
            parsed.searchParams.get('t') ??
                parsed.searchParams.get('start') ??
                parsed.hash.replace(/^#/, ''),
        ),
    }
}

export function embedSrc(provider, id, start, nocookie = true) {
    if (provider === 'vimeo') {
        return `https://player.vimeo.com/video/${id}${start ? `#t=${start}s` : ''}`
    }

    const host = nocookie ? 'www.youtube-nocookie.com' : 'www.youtube.com'

    return `https://${host}/embed/${id}${start ? `?start=${start}` : ''}`
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
            return { nocookie: true, labels: {} }
        }

        try {
            return { nocookie: true, labels: {}, ...JSON.parse(raw) }
        } catch (error) {
            console.error('The advanced rich editor could not read its embed settings:', error)

            return { nocookie: true, labels: {} }
        }
    }

    return Node.create({
        name: 'embed',

        group: 'block',

        atom: true,

        draggable: true,

        selectable: true,

        /**
         * The provider names and the cookie setting come from PHP, through the element the
         * editor was mounted on - the one channel a TipTap extension has to the field it
         * belongs to. Read lazily, because options are built before there is an editor.
         */
        addOptions() {
            return {
                settings: null,
            }
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
                ratio: {
                    default: '16 / 9',
                    parseHTML: (element) =>
                        /aspect-ratio:\s*([0-9.]+\s*\/\s*[0-9.]+)/i.exec(
                            element.getAttribute('style') ?? '',
                        )?.[1]?.trim() ?? '16 / 9',
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

            if (!provider || !id) {
                return ['div', { 'data-type': 'embed' }]
            }

            return [
                'div',
                {
                    class: 'fi-arte-embed',
                    'data-type': 'embed',
                    style: `aspect-ratio: ${ratio ?? '16 / 9'};`,
                },
                [
                    'iframe',
                    mergeAttributes({
                        src: embedSrc(provider, id, start, settingsOf(this.editor).nocookie),
                        ...(title ? { title } : {}),
                        loading: 'lazy',
                        allow: 'accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
                        allowfullscreen: 'true',
                        referrerpolicy: 'strict-origin-when-cross-origin',
                    }),
                ],
            ]
        },

        addNodeView() {
            return ({ node }) => {
                const labels = settingsOf(this.editor).labels
                const card = document.createElement('div')
                card.className = 'fi-arte-embed-card'
                card.dataset.type = 'embed'
                card.contentEditable = 'false'

                const provider = document.createElement('span')
                provider.className = 'fi-arte-embed-card-provider'
                provider.textContent = labels[node.attrs.provider] ?? node.attrs.provider ?? ''

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

                            if (!text || /\s/.test(text)) {
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
