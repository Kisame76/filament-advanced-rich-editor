/*
 * A video or a sound that lives on this server, on the editor's side.
 *
 * Filament loads this verbatim through a dynamic `import()`, so there are no `import`
 * statements here and TipTap is read from the global the editor publishes. See
 * `task-list.js` for the whole reasoning.
 *
 * One node for both elements; the PHP half is `Nodes/Media.php` and carries the reasoning
 * for that and for what is stored. This file is not `media-picker.js`, which browses the
 * pictures already on the server - that one is a library, this one is a block.
 *
 * The editor draws the real element, which is where this parts company with the embed. An
 * embed is a card because ten of them would be ten players loading from YouTube, each with
 * its own cookie, in a screen where nobody is watching anything. A file on this server has
 * no third party to call and `preload="metadata"` fetches a few kilobytes - and being able
 * to press play is most of the point of self-hosting, because that is how anyone finds out
 * the path was wrong before the page ships.
 */

/** Mirrors `MediaUrl::KINDS`. */
export const KINDS = ['video', 'audio']

/** Mirrors `MediaUrl::PRELOADS`. */
export const PRELOADS = ['none', 'metadata', 'auto']

/** Mirrors the video and audio halves of `Media/MediaKinds::TYPES`. */
export const EXTENSIONS = {
    video: ['mp4', 'm4v', 'webm', 'ogv', 'mov'],
    audio: ['mp3', 'm4a', 'aac', 'oga', 'ogg', 'opus', 'wav', 'flac', 'weba'],
}

/**
 * Whether a string holds whitespace or a control character - the two things a scheme can
 * hide inside.
 *
 * Written as a walk rather than as a character class, for two reasons. A regular expression
 * naming control characters is one the linter refuses on principle, and `\s` is not the same
 * set on both sides of this feature: JavaScript counts a non-breaking space and a line
 * separator, and the PHP half's byte class does not. Comparing code points keeps the two
 * halves answering the same question.
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
 * An address this package is willing to point an element at, or null.
 *
 * Mirrors `MediaUrl::src`. Control characters are refused rather than stripped: they are
 * how a scheme hides from a check that reads the front of the string, and a `javascript:`
 * with a newline inside it is still `javascript:` to a browser.
 */
export const mediaSrc = (value) => {
    if (typeof value !== 'string') {
        return null
    }

    const src = value.trim()

    if (src === '' || hidden(src)) {
        return null
    }

    const scheme = /^([a-z][a-z0-9+.-]*):/i.exec(src)

    if (scheme && !['http', 'https'].includes(scheme[1].toLowerCase())) {
        return null
    }

    return src
}

/** The kind an address looks like, or null where its ending says nothing. */
export const guessKind = (src) => {
    if (typeof src !== 'string' || src === '') {
        return null
    }

    // The path alone: a query string carries tokens, and none of them is the file's name.
    const path = src.split(/[?#]/)[0]
    const extension = (path.split('.').pop() ?? '').toLowerCase()

    return KINDS.find((kind) => EXTENSIONS[kind].includes(extension)) ?? null
}

/** Which element to draw. Mirrors `MediaUrl::kind`. */
export const mediaKind = (kind, src = null) =>
    KINDS.includes(kind) ? kind : (guessKind(src) ?? 'video')

/** Mirrors `MediaUrl::preload`. */
export const mediaPreload = (value) => (PRELOADS.includes(value) ? value : 'metadata')

/**
 * The address an element points at: its own `src`, or the first `<source>` inside it -
 * which is what a hand-written document and most other editors produce.
 */
export const readSrc = (element) =>
    element?.getAttribute?.('src') ||
    element?.querySelector?.('source')?.getAttribute('src') ||
    null

/**
 * The element to draw and what to put on it, or null where there is nothing to play.
 *
 * Shared by the serialiser and the node view so that what the editor shows and what a save
 * writes cannot drift apart - the one place this node decides anything.
 */
export const mediaElement = (attrs) => {
    const src = mediaSrc(attrs.src)

    if (src === null) {
        return null
    }

    const kind = mediaKind(attrs.kind, src)
    const poster = kind === 'video' ? mediaSrc(attrs.poster) : null

    return {
        kind,
        attributes: {
            src,
            // The attachment this node points at, where it points at one. The same `data-id`
            // Filament's image node carries, and it has to be: the id is what the save walks
            // to decide which files are still in use. See `Media/FileAttachments.php`.
            ...(attrs.id ? { 'data-id': attrs.id } : {}),
            controls: 'controls',
            preload: mediaPreload(attrs.preload),
            ...(attrs.loop ? { loop: 'loop' } : {}),
            ...(attrs.title ? { title: attrs.title } : {}),
            ...(poster ? { poster } : {}),
            class: `fi-arte-media fi-arte-media-${kind}`,
            // Inline rather than left to the class, the way the embed's shape is: this
            // package's stylesheet is loaded into the admin panel and the page the content
            // ends up on is somebody else's.
            style: kind === 'video' ? 'width: 100%; height: auto;' : 'width: 100%;',
        },
    }
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor media extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Node, mergeAttributes } = tiptap

    return Node.create({
        name: 'media',

        group: 'block',

        atom: true,

        draggable: true,

        selectable: true,

        addAttributes() {
            return {
                kind: {
                    default: 'video',
                    // The tag itself, which is the one place the answer cannot be wrong.
                    parseHTML: (element) =>
                        mediaKind(element.tagName.toLowerCase(), readSrc(element)),
                    renderHTML: () => ({}),
                },
                src: {
                    default: null,
                    parseHTML: (element) => mediaSrc(readSrc(element)),
                    renderHTML: () => ({}),
                },
                id: {
                    default: null,
                    parseHTML: (element) => element.getAttribute('data-id') || null,
                    renderHTML: () => ({}),
                },
                poster: {
                    default: null,
                    parseHTML: (element) => mediaSrc(element.getAttribute('poster')),
                    renderHTML: () => ({}),
                },
                title: {
                    default: null,
                    parseHTML: (element) => element.getAttribute('title') || null,
                    renderHTML: () => ({}),
                },
                preload: {
                    default: 'metadata',
                    parseHTML: (element) => mediaPreload(element.getAttribute('preload')),
                    renderHTML: () => ({}),
                },
                loop: {
                    default: false,
                    parseHTML: (element) => element.hasAttribute('loop'),
                    renderHTML: () => ({}),
                },
            }
        },

        parseHTML() {
            return [{ tag: 'video' }, { tag: 'audio' }]
        },

        renderHTML({ node }) {
            const resolved = mediaElement(node.attrs)

            // Nothing to play. An element with no `src` is a broken control bar in the
            // middle of the page, so an empty marker goes out instead of a player - and
            // the PHP half drops the node entirely on its way to the reader.
            if (resolved === null) {
                return ['div', { 'data-type': 'media' }]
            }

            return [resolved.kind, mergeAttributes(resolved.attributes)]
        },

        /**
         * The real element, inside a block that can be selected and dragged.
         *
         * The wrapper is what makes the node behave like the rest of the document: a
         * `<video>` on its own answers every click with its own controls, so there would be
         * no way left to select it, drag it or delete it. The wrapper takes the clicks that
         * land beside the element and carries the drag handle; the controls keep the ones
         * that land on them, which is what anyone pressing play expects.
         */
        addNodeView() {
            return ({ node }) => {
                const block = document.createElement('div')
                block.className = 'fi-arte-media-block'
                block.dataset.type = 'media'
                block.dataset.dragHandle = ''
                block.contentEditable = 'false'

                const resolved = mediaElement(node.attrs)

                if (resolved === null) {
                    block.classList.add('fi-arte-media-block-empty')

                    return { dom: block }
                }

                const element = document.createElement(resolved.kind)

                for (const [name, value] of Object.entries(resolved.attributes)) {
                    element.setAttribute(name, value)
                }

                block.append(element)

                return {
                    dom: block,
                    // Everything in here is the node view's own furniture and none of it is
                    // document content - a media element mutates itself as it loads and
                    // plays, and ProseMirror handed those mutations would try to read them
                    // back as text. `task-item.js` and `code-block.js` guard the same way.
                    ignoreMutation: () => true,
                }
            }
        },

        addCommands() {
            return {
                setMedia:
                    (attributes) =>
                    ({ commands }) =>
                        commands.insertContent({ type: this.name, attrs: attributes }),
            }
        },
    })
}
