import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import embedExtension, { embedBox, embedSrc, parseEmbed, useProviders } from '../../resources/js/embed.js'
import PROVIDERS from './fixtures/embed-providers.json'

/**
 * What a pasted link turns into, and the card the editor draws in place of the player.
 *
 * `parseEmbed` reads the same service descriptions `EmbedUrl` reads in PHP, and the two have to
 * agree: this half decides what a paste becomes, that half decides what a save keeps. The
 * descriptions are the file PHP hands out - `EmbedProvidersTest` asserts the fixture is exactly
 * that - so this is where the reading of them is pinned, and where the way an address becomes
 * an iframe is, since that is the one place text from a stranger's link is put into a `src`.
 */

const YOUTUBE = 'dQw4w9WgXcQ'

const withYouTube = (changes) =>
    PROVIDERS.map((provider) => (provider.name === 'youtube' ? { ...provider, ...changes } : provider))

beforeEach(() => {
    useProviders(PROVIDERS)
})

describe('the links a share button writes', () => {
    it('reads a watch link, with or without the scheme and the www', () => {
        const expected = { provider: 'youtube', id: YOUTUBE, start: null }

        expect(parseEmbed(`https://www.youtube.com/watch?v=${YOUTUBE}`)).toEqual(expected)
        expect(parseEmbed(`youtube.com/watch?v=${YOUTUBE}`)).toEqual(expected)
        expect(parseEmbed(`  https://m.youtube.com/watch?v=${YOUTUBE}  `)).toEqual(expected)
    })

    it('reads the short link, and only its first path segment', () => {
        expect(parseEmbed(`https://youtu.be/${YOUTUBE}`)).toEqual({
            provider: 'youtube',
            id: YOUTUBE,
            start: null,
        })
        expect(parseEmbed(`https://youtu.be/${YOUTUBE}/extra`)?.id).toBe(YOUTUBE)
    })

    it('reads the embed, shorts, live and v paths', () => {
        for (const path of ['embed', 'shorts', 'live', 'v']) {
            expect(parseEmbed(`https://www.youtube.com/${path}/${YOUTUBE}`)?.id).toBe(YOUTUBE)
        }
    })

    it('reads the privacy-friendly host as YouTube', () => {
        expect(parseEmbed(`https://www.youtube-nocookie.com/embed/${YOUTUBE}`)?.provider).toBe(
            'youtube',
        )
    })

    it('reads a Vimeo link in the shapes Vimeo writes it', () => {
        const expected = { provider: 'vimeo', id: '123456789', start: null }

        expect(parseEmbed('https://vimeo.com/123456789')).toEqual(expected)
        expect(parseEmbed('https://player.vimeo.com/video/123456789')).toEqual(expected)
        // A channel path puts words in front of the number.
        expect(parseEmbed('https://vimeo.com/channels/staffpicks/123456789')).toEqual(expected)
    })
})

describe('the timestamp', () => {
    const at = (query) => parseEmbed(`https://www.youtube.com/watch?v=${YOUTUBE}${query}`)?.start

    it('takes plain seconds from `t` or from `start`', () => {
        expect(at('&t=90')).toBe(90)
        expect(at('&start=45')).toBe(45)
    })

    it('takes the shapes a share button writes: minutes and seconds, hours and minutes', () => {
        expect(at('&t=1m30s')).toBe(90)
        expect(at('&t=2h5m')).toBe(7500)
        expect(at('&t=1h')).toBe(3600)
        expect(at('&t=45s')).toBe(45)
    })

    it('finds it in the fragment, which is where Vimeo puts it', () => {
        expect(parseEmbed('https://vimeo.com/123456789#t=30s')?.start).toBe(30)
        expect(parseEmbed('https://vimeo.com/123456789#t=1m')?.start).toBe(60)
    })

    it('has none where the link says nothing usable', () => {
        // A start of zero is the beginning, which is what no timestamp means anyway.
        expect(at('&t=0')).toBeNull()
        expect(at('&t=0s')).toBeNull()
        expect(at('&t=soon')).toBeNull()
        expect(at('&t=-5')).toBeNull()
        expect(at('')).toBeNull()
    })
})

describe('what is not a link to be embedded', () => {
    it('has nothing to say about nothing', () => {
        expect(parseEmbed('')).toBeNull()
        expect(parseEmbed(null)).toBeNull()
        expect(parseEmbed(undefined)).toBeNull()
        expect(parseEmbed('not a link at all')).toBeNull()
    })

    it('refuses a host that is not a provider', () => {
        expect(parseEmbed(`https://example.com/watch?v=${YOUTUBE}`)).toBeNull()
        expect(parseEmbed(`https://example.com/?next=youtube.com/watch?v=${YOUTUBE}`)).toBeNull()
    })

    it('refuses a host that only ends the same way', () => {
        // `evilyoutube.com` ends in `youtube.com`, and a check that compares the end of the
        // string lets it through. The suffix has to begin at a dot.
        expect(parseEmbed(`https://notyoutube.com/watch?v=${YOUTUBE}`)).toBeNull()
        expect(parseEmbed(`https://youtube.com.example.test/watch?v=${YOUTUBE}`)).toBeNull()
        expect(parseEmbed('https://notvimeo.com/123456789')).toBeNull()
    })

    it('refuses an address that is not a web link', () => {
        expect(parseEmbed('javascript:alert(1)')).toBeNull()
    })

    it('refuses an id that could not have come from the provider', () => {
        // The id is the only part of a stranger's link that ends up in an `src`, and the
        // pattern is the whole of what keeps a quote or a slash out of it.
        expect(parseEmbed('https://youtu.be/abc')).toBeNull()
        expect(parseEmbed('https://www.youtube.com/watch?v=')).toBeNull()
        expect(parseEmbed('https://www.youtube.com/watch?v=abc%22onload%3D1')).toBeNull()
        expect(parseEmbed('https://www.youtube.com/watch?v=abc%3Cscript%3Ealert')).toBeNull()
        expect(parseEmbed('https://vimeo.com/12345')).toBeNull()
        expect(parseEmbed('https://vimeo.com/abcdefgh')).toBeNull()
    })
})

describe('the other services', () => {
    const MAP =
        '!1m18!1m12!1m3!1d2427.0!2d13.37!3d52.51!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x47a851c655f20989%3A0x26bbfb4e84674c63!2sBrandenburg%20Gate!5e0!3m2!1sen!2sde!4v1690000000000!5m2!1sen!2sde'

    it('reads a song, a recording, a pen, a TikTok and a map', () => {
        expect(parseEmbed('https://open.spotify.com/intl-de/track/4uLU6hMCjMI75M1A2tKUQC?si=x')).toEqual({
            provider: 'spotify',
            id: 'track/4uLU6hMCjMI75M1A2tKUQC',
            start: null,
        })
        expect(parseEmbed('https://www.loom.com/share/e5b8c04bca094dd8a5507925ab887002')?.id).toBe(
            'e5b8c04bca094dd8a5507925ab887002',
        )
        // Two halves, the author and the pen, joined the way PHP joins them.
        expect(parseEmbed('https://codepen.io/chriscoyier/pen/gfdDu')?.id).toBe('chriscoyier/gfdDu')
        expect(parseEmbed('https://www.tiktok.com/@scout2015/video/6718335390845095173')?.id).toBe(
            '6718335390845095173',
        )
        expect(parseEmbed(`https://www.google.com/maps/embed?pb=${MAP}`)?.id).toBe(MAP)
    })

    it('takes the embed code a share dialog hands out', () => {
        expect(
            parseEmbed(
                `<iframe src="https://www.google.com/maps/embed?pb=${MAP}" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe>`,
            ),
        ).toEqual({ provider: 'google_maps', id: MAP, start: null })
        expect(parseEmbed('<iframe src="javascript:alert(1)"></iframe>')).toBeNull()
        expect(parseEmbed('<iframe src="https://evil.example/embed/1"></iframe>')).toBeNull()
    })

    it('builds the addresses those are framed from, halves and all', () => {
        expect(embedSrc('codepen', 'chriscoyier/gfdDu', null)).toBe(
            'https://codepen.io/chriscoyier/embed/gfdDu?default-tab=result',
        )
        expect(embedSrc('spotify', 'track/4uLU6hMCjMI75M1A2tKUQC', 30)).toBe(
            'https://open.spotify.com/embed/track/4uLU6hMCjMI75M1A2tKUQC',
        )
        expect(embedSrc('google_maps', MAP, null)).toBe(`https://www.google.com/maps/embed?pb=${MAP}`)
    })

    it('refuses what only looks like one of them', () => {
        expect(parseEmbed('https://open.spotify.com.attacker.test/track/4uLU6hMCjMI75M1A2tKUQC')).toBeNull()
        expect(parseEmbed('https://vm.tiktok.com/ZMabcdef/')).toBeNull()
        expect(parseEmbed('https://www.google.com/maps/place/Brandenburger+Tor')).toBeNull()
        expect(parseEmbed('https://codepen.io/chriscoyier')).toBeNull()
    })
})

describe('the address of the player', () => {
    it('builds the address PHP described, the cookie-free host included', () => {
        expect(embedSrc('youtube', YOUTUBE, null)).toBe(
            `https://www.youtube-nocookie.com/embed/${YOUTUBE}`,
        )

        useProviders(withYouTube({ src: 'https://www.youtube.com/embed/{id}' }))

        expect(embedSrc('youtube', YOUTUBE, null)).toBe(`https://www.youtube.com/embed/${YOUTUBE}`)
    })

    it('builds nothing for a service it was not told about, or an id that is not one', () => {
        expect(embedSrc('dailymotion', 'x8abc12', null)).toBeNull()
        expect(embedSrc('youtube', 'not an id', null)).toBeNull()

        useProviders([])

        expect(embedSrc('youtube', YOUTUBE, null)).toBeNull()
    })

    it('starts a YouTube video where the link said to', () => {
        expect(embedSrc('youtube', YOUTUBE, 90)).toBe(
            `https://www.youtube-nocookie.com/embed/${YOUTUBE}?start=90`,
        )
    })

    it('builds the Vimeo address, and puts the timestamp in the fragment', () => {
        expect(embedSrc('vimeo', '123456789', null)).toBe(
            'https://player.vimeo.com/video/123456789',
        )
        expect(embedSrc('vimeo', '123456789', 90)).toBe(
            'https://player.vimeo.com/video/123456789#t=90s',
        )
    })

    it('leaves the timestamp out where there is none', () => {
        expect(embedSrc('youtube', YOUTUBE, 0)).not.toContain('start')
        expect(embedSrc('vimeo', '123456789', 0)).not.toContain('#t=')
    })

    it('is never built from anything but what parsing kept', () => {
        // The round trip a paste makes: nothing of the pasted text survives except the two
        // validated parts, so a link full of tracking parameters comes out clean.
        const { provider, id, start } = parseEmbed(
            `https://www.youtube.com/watch?v=${YOUTUBE}&utm_source=mail&si=abc&t=30`,
        )

        expect(embedSrc(provider, id, start)).toBe(
            `https://www.youtube-nocookie.com/embed/${YOUTUBE}?start=30`,
        )
    })
})

/**
 * The node. What only an editor can prove: the tags it claims, what a document with an
 * iframe in it becomes, and what a paste on an empty line does.
 */

const build = () => {
    window.FilamentRichEditor = {
        tiptap: {
            core: {
                Node: { create: (definition) => definition },
                mergeAttributes: (...sets) => Object.assign({}, ...sets),
            },
            pmState: {
                Plugin: class {
                    constructor(spec) {
                        this.spec = spec
                    }
                },
                PluginKey: class {
                    constructor(name) {
                        this.name = name
                    }
                },
            },
        },
    }

    return embedExtension()
}

/**
 * The editor as far as the extension looks at it: the element it was mounted on, which is
 * how PHP hands over the cookie setting and the provider names.
 */
const editorWith = (settings) => ({
    options: {
        element: {
            dataset:
                settings === undefined
                    ? {}
                    : {
                          arteEmbed:
                              typeof settings === 'string' ? settings : JSON.stringify(settings),
                      },
        },
    },
})

const parse = (html) => {
    const holder = document.createElement('div')
    holder.innerHTML = html

    return holder.firstElementChild
}

const player = (src = `https://www.youtube.com/embed/${YOUTUBE}`, extra = '') =>
    `<div data-type="embed"${extra}><iframe src="${src}" title="A talk"></iframe></div>`

afterEach(() => {
    delete window.FilamentRichEditor
})

describe('the node', () => {
    it('is a block that is selected, dragged and deleted as one piece', () => {
        const definition = build()

        expect(definition.name).toBe('embed')
        expect(definition.group).toBe('block')
        expect(definition.atom).toBe(true)
        expect(definition.draggable).toBe(true)
        expect(definition.selectable).toBe(true)
    })

    it('says so rather than throwing when TipTap is not there', () => {
        const complaint = vi.spyOn(console, 'error').mockImplementation(() => {})

        expect(embedExtension()).toBeNull()
        expect(complaint).toHaveBeenCalled()
    })
})

describe('reading a document', () => {
    it('claims a block whose iframe points at a provider', () => {
        const [rule] = build().parseHTML()

        expect(rule.tag).toBe('div[data-type="embed"]')
        expect(rule.getAttrs(parse(player()))).toBeNull()
    })

    it('leaves a block that only shares the attribute to the extension it belongs to', () => {
        // `data-type` is written by grids and custom blocks too, so the tag alone would
        // swallow them. Returning `false` is how a rule says "not mine".
        const [rule] = build().parseHTML()

        expect(rule.getAttrs(parse('<div data-type="embed"></div>'))).toBe(false)
        expect(rule.getAttrs(parse(player('https://example.com/embed/abcdefghijk')))).toBe(false)
    })

    it('reads the provider, the video, the start, the title and the shape off the block', () => {
        const attributes = build().addAttributes()
        const element = parse(
            player(
                `https://www.youtube-nocookie.com/embed/${YOUTUBE}?start=90`,
                ' style="aspect-ratio: 4 / 3; width: 100%;"',
            ),
        )

        expect(attributes.provider.parseHTML(element)).toBe('youtube')
        expect(attributes.id.parseHTML(element)).toBe(YOUTUBE)
        expect(attributes.start.parseHTML(element)).toBe(90)
        expect(attributes.title.parseHTML(element)).toBe('A talk')
        expect(attributes.ratio.parseHTML(element)).toBe('4 / 3')
    })

    it('falls back to nothing where the block says less, the shape included', () => {
        // No shape is the service's shape: a TikTok left at a widescreen default would be
        // drawn on its side.
        const attributes = build().addAttributes()
        const element = parse('<div data-type="embed"></div>')

        expect(attributes.ratio.default).toBeNull()
        expect(attributes.ratio.parseHTML(element)).toBeNull()
        expect(attributes.provider.parseHTML(element)).toBeNull()
        expect(attributes.id.parseHTML(element)).toBeNull()
        expect(attributes.start.parseHTML(element)).toBeNull()
        expect(attributes.title.parseHTML(element)).toBeNull()
    })

    it('keeps every attribute out of the markup, which is built from the node instead', () => {
        // The stored element is the iframe and the wrapper, not a `data-provider` beside them;
        // renderHTML below is the one place that writes it.
        const attributes = build().addAttributes()

        for (const name of ['provider', 'id', 'start', 'title', 'ratio']) {
            expect(attributes[name].renderHTML()).toEqual({})
        }
    })
})

describe('writing a document', () => {
    const write = (attrs, settings) => {
        const definition = build()

        return definition.renderHTML.call({ editor: editorWith(settings) }, { node: { attrs } })
    }

    it('writes an empty wrapper for a node that names no video', () => {
        expect(write({ provider: null, id: null })).toEqual(['div', { 'data-type': 'embed' }])
        expect(write({ provider: 'youtube', id: null })).toEqual(['div', { 'data-type': 'embed' }])
    })

    it('writes a sized wrapper around a lazy iframe', () => {
        const [tag, wrapper, [iframeTag, iframe]] = write({
            provider: 'youtube',
            id: YOUTUBE,
            start: null,
            title: 'A talk',
            ratio: '4 / 3',
        })

        expect(tag).toBe('div')
        expect(wrapper['data-type']).toBe('embed')
        expect(wrapper.class).toBe('fi-arte-embed')
        // The shape travels with the markup, since the page it ends up on has none of this
        // package's styles.
        expect(wrapper.style).toContain('aspect-ratio: 4 / 3')
        expect(iframeTag).toBe('iframe')
        expect(iframe.src).toBe(`https://www.youtube-nocookie.com/embed/${YOUTUBE}`)
        expect(iframe.title).toBe('A talk')
        expect(iframe.loading).toBe('lazy')
        expect(iframe.allowfullscreen).toBe('true')
        expect(iframe.referrerpolicy).toBe('strict-origin-when-cross-origin')
    })

    it('leaves the title out where there is none, and widescreen where the shape is missing', () => {
        const [, wrapper, [, iframe]] = write({ provider: 'vimeo', id: '123456789', start: 90 })

        expect(iframe).not.toHaveProperty('title')
        expect(iframe.src).toBe('https://player.vimeo.com/video/123456789#t=90s')
        expect(wrapper.style).toContain('aspect-ratio: 16 / 9')
    })

    it('reads the services off the element the field was mounted with, before the content', () => {
        const definition = build()

        definition.onBeforeCreate.call({
            editor: editorWith({ providers: withYouTube({ src: 'https://www.youtube.com/embed/{id}' }) }),
        })

        expect(write({ provider: 'youtube', id: YOUTUBE })[2][1].src).toBe(
            `https://www.youtube.com/embed/${YOUTUBE}`,
        )
    })

    it('knows no service where nothing was passed, and says so where it could not be read', () => {
        const complaint = vi.spyOn(console, 'error').mockImplementation(() => {})
        const definition = build()

        definition.onBeforeCreate.call({ editor: editorWith(undefined) })

        expect(write({ provider: 'youtube', id: YOUTUBE })).toEqual(['div', { 'data-type': 'embed' }])

        definition.onBeforeCreate.call({ editor: editorWith('{not json') })

        // Said once, for the one that was broken, and not for the one that was missing.
        expect(complaint).toHaveBeenCalledTimes(1)
    })

    it('draws a player with a height of its own at that height, and an upright one narrow', () => {
        const [, spotify] = write({ provider: 'spotify', id: 'track/4uLU6hMCjMI75M1A2tKUQC', ratio: '16 / 9' })
        const [, tiktok] = write({ provider: 'tiktok', id: '6718335390845095173', ratio: '9 / 16' })

        expect(spotify.style).toBe('width: 100%; height: 352px;')
        expect(tiktok.style).toBe('aspect-ratio: 9 / 16; width: 100%; max-width: 22.5rem;')
    })

    it('writes nothing into the style that is not a ratio', () => {
        expect(embedBox('youtube', '16 / 9; background: url(x)')).toBe('aspect-ratio: 16 / 9; width: 100%;')
        expect(embedBox('google_maps', null)).toBe('aspect-ratio: 4 / 3; width: 100%;')
    })
})

describe('the card drawn in the editor', () => {
    const draw = (attrs, settings) => {
        const definition = build()

        return definition.addNodeView.call({ editor: editorWith(settings) })({
            node: { attrs },
        }).dom
    }

    it('is a block that cannot be typed into, and names the provider and the video', () => {
        const card = draw({ provider: 'youtube', id: YOUTUBE, title: 'A talk' })

        expect(card.className).toBe('fi-arte-embed-card')
        expect(card.dataset.type).toBe('embed')
        expect(card.contentEditable).toBe('false')
        expect(card.querySelector('.fi-arte-embed-card-provider').textContent).toBe('YouTube')
        expect(card.querySelector('.fi-arte-embed-card-title').textContent).toBe('A talk')
    })

    it('says the service the way the panel is read in, and its key where it was not described', () => {
        useProviders(withYouTube({ label: 'YouTube-Video' }))

        expect(draw({ provider: 'youtube', id: YOUTUBE }).querySelector('.fi-arte-embed-card-provider').textContent).toBe(
            'YouTube-Video',
        )
        expect(draw({ provider: 'dailymotion', id: 'x8abc12' }).querySelector('.fi-arte-embed-card-provider').textContent).toBe(
            'dailymotion',
        )
    })

    it('names the video by its id where it has no title', () => {
        const card = draw({ provider: 'youtube', id: YOUTUBE, title: null })

        expect(card.querySelector('.fi-arte-embed-card-title').textContent).toBe(YOUTUBE)
    })

    it('shows where the video starts, as minutes and seconds, and only when it does', () => {
        expect(
            draw({ provider: 'youtube', id: YOUTUBE, start: 90 }).querySelector(
                '.fi-arte-embed-card-start',
            ).textContent,
        ).toBe('1:30')
        expect(
            draw({ provider: 'youtube', id: YOUTUBE, start: 65 }).querySelector(
                '.fi-arte-embed-card-start',
            ).textContent,
        ).toBe('1:05')
        expect(
            draw({ provider: 'youtube', id: YOUTUBE, start: null }).querySelector(
                '.fi-arte-embed-card-start',
            ),
        ).toBeNull()
    })

    it('puts a title in as text and never as markup', () => {
        // A title comes out of the iframe of whatever document was pasted or imported.
        const card = draw({
            provider: 'youtube',
            id: YOUTUBE,
            title: '<img src=x onerror=alert(1)>',
        })

        expect(card.querySelector('img')).toBeNull()
        expect(card.querySelector('.fi-arte-embed-card-title').textContent).toBe(
            '<img src=x onerror=alert(1)>',
        )
    })
})

describe('the insert command', () => {
    it('puts the node into the document with the attributes it was given', () => {
        const definition = build()
        const insertContent = vi.fn(() => true)
        const attributes = { provider: 'youtube', id: YOUTUBE }

        const done = definition.addCommands.call({ name: 'embed' }).setEmbed(attributes)({
            commands: { insertContent },
        })

        expect(done).toBe(true)
        expect(insertContent).toHaveBeenCalledWith({ type: 'embed', attrs: attributes })
    })
})

describe('pasting a link', () => {
    const type = { create: vi.fn((attrs) => ({ made: attrs })) }

    /**
     * A view whose caret is where the test says: how much is in the paragraph, whether
     * something is selected and whether it is code.
     */
    const viewAt = ({ size = 0, empty = true, code = false } = {}) => {
        const replaceSelectionWith = vi.fn((node) => ({ replaced: node }))

        return {
            dispatch: vi.fn(),
            replaceSelectionWith,
            state: {
                selection: {
                    empty,
                    $from: { parent: { content: { size }, type: { spec: { code } } } },
                },
                tr: { replaceSelectionWith },
            },
        }
    }

    const paste = (text, view = viewAt()) => {
        const [plugin] = build().addProseMirrorPlugins.call({ type })

        return {
            handled: plugin.spec.props.handlePaste(view, {
                clipboardData: { getData: () => text },
            }),
            view,
        }
    }

    it('registers one plugin under its own key', () => {
        const plugins = build().addProseMirrorPlugins.call({ type })

        expect(plugins).toHaveLength(1)
        expect(plugins[0].spec.key.name).toBe('arteEmbedPaste')
    })

    it('takes the embed code a map is shared as, spaces and all', () => {
        const map =
            '!1m18!1m12!1m3!1d2427.0!2d13.37!3d52.51!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e0!3m2!1sen!2sde!4v1690000000000!5m2!1sen!2sde'
        const { handled } = paste(
            `<iframe src="https://www.google.com/maps/embed?pb=${map}" width="600" height="450" style="border:0;" loading="lazy"></iframe>`,
        )

        expect(handled).toBe(true)
        expect(type.create).toHaveBeenLastCalledWith({ provider: 'google_maps', id: map, start: null })
    })

    it('leaves a sentence with a link in it to be pasted as a sentence', () => {
        expect(paste(`Watch https://youtu.be/${YOUTUBE} later`).handled).toBe(false)
    })

    it('turns a link alone on an empty line into a player', () => {
        const { handled, view } = paste(`https://youtu.be/${YOUTUBE}?t=30`)

        expect(handled).toBe(true)
        expect(type.create).toHaveBeenCalledWith({ provider: 'youtube', id: YOUTUBE, start: 30 })
        expect(view.replaceSelectionWith).toHaveBeenCalledWith({
            made: { provider: 'youtube', id: YOUTUBE, start: 30 },
        })
        expect(view.dispatch).toHaveBeenCalledTimes(1)
    })

    it('ignores the space around what was copied', () => {
        expect(paste(`  https://youtu.be/${YOUTUBE}\n`).handled).toBe(true)
    })

    it('leaves a link in the middle of a sentence as the text it is', () => {
        // Pasting a video address into a paragraph means the address, and not a player
        // dropped into the paragraph.
        const { handled, view } = paste(`https://youtu.be/${YOUTUBE}`, viewAt({ size: 12 }))

        expect(handled).toBe(false)
        expect(view.dispatch).not.toHaveBeenCalled()
    })

    it('leaves a paste over a selection, and a paste into code, alone', () => {
        expect(paste(`https://youtu.be/${YOUTUBE}`, viewAt({ empty: false })).handled).toBe(false)
        expect(paste(`https://youtu.be/${YOUTUBE}`, viewAt({ code: true })).handled).toBe(false)
    })

    it('leaves anything that is more than one link alone', () => {
        expect(paste(`look at this https://youtu.be/${YOUTUBE}`).handled).toBe(false)
        expect(paste(`https://youtu.be/${YOUTUBE} https://vimeo.com/123456789`).handled).toBe(false)
    })

    it('leaves a link with words behind it alone, even where the link would parse', () => {
        // The id sits early in the query, so what follows a later parameter cannot spoil the
        // parse. That is why the paste has to look at the whole text and not only at what
        // `parseEmbed` makes of it.
        expect(
            paste(`https://www.youtube.com/watch?v=${YOUTUBE}&feature=share and a comment`).handled,
        ).toBe(false)
    })

    it('leaves a link to anywhere else, and nothing at all, alone', () => {
        expect(paste('https://example.com/article').handled).toBe(false)
        expect(paste('').handled).toBe(false)
        expect(paste(undefined).handled).toBe(false)
    })
})
