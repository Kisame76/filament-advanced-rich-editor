import { afterEach, describe, expect, it, vi } from 'vitest'
import mediaReplace, { removeAttachment, replaceAttachment } from '../../resources/js/media-replace.js'

/**
 * A file replaced in the library, on the editor's side.
 *
 * The browser says which id changed and where its file is now; the editor walks its document
 * and points every node carrying that id at the new file. Nothing here needs ProseMirror to
 * exist: what is asserted is which nodes are rewritten, with what, and that it is one
 * transaction outside the undo history - the old address may not exist any more, so undoing
 * back to it would be undoing to a broken picture.
 */

/**
 * An editor that holds the given nodes and records what is written to them.
 */
const editorWith = (nodes) => {
    const marked = []
    const dispatched = []

    const deleted = []

    const tr = {
        meta: {},
        setNodeMarkup(pos, type, attrs) {
            marked.push({ pos, type, attrs })

            return tr
        },
        delete(from, to) {
            deleted.push([from, to])

            return tr
        },
        setMeta(key, value) {
            tr.meta[key] = value

            return tr
        },
    }

    return {
        marked,
        deleted,
        dispatched,
        state: {
            tr,
            doc: {
                descendants: (callback) => {
                    for (const { pos, type, attrs, size = 1 } of nodes) {
                        callback({ type: { name: type }, attrs, nodeSize: size }, pos)
                    }
                },
            },
        },
        view: { dispatch: (transaction) => dispatched.push(transaction) },
    }
}

const replaced = (detail = {}) => ({
    id: 'a',
    src: 'https://example.test/new.png',
    name: null,
    size: null,
    width: null,
    height: null,
    ...detail,
})

afterEach(() => {
    vi.useRealTimers()
    delete window.FilamentRichEditor
})

describe('pointing the document at the new file', () => {
    it('rewrites every node carrying the id, and nothing else, in one transaction', () => {
        const editor = editorWith([
            { pos: 1, type: 'image', attrs: { id: 'a', src: 'https://example.test/old.png' } },
            { pos: 5, type: 'paragraph', attrs: { id: 'a' } },
            { pos: 9, type: 'media', attrs: { id: 'a', src: 'https://example.test/old.png', kind: 'video' } },
            { pos: 12, type: 'image', attrs: { id: 'b', src: 'https://example.test/b.png' } },
        ])

        expect(replaceAttachment(editor, replaced())).toBe(true)

        expect(editor.marked.map(({ pos }) => pos)).toEqual([1, 9])
        expect(editor.marked[1].attrs).toEqual({ id: 'a', src: 'https://example.test/new.png', kind: 'video' })
        expect(editor.dispatched).toHaveLength(1)
        expect(editor.dispatched[0].meta.addToHistory).toBe(false)
    })

    it('writes the card\'s name and size into a card', () => {
        // A card carries both as text it was inserted with, and a reader downloads under the
        // name - so they follow the file.
        const editor = editorWith([
            { pos: 1, type: 'file', attrs: { id: 'a', src: 'https://example.test/report.pdf', name: 'report.pdf', size: '1 KB' } },
        ])

        replaceAttachment(editor, replaced({ src: 'https://example.test/report-2026.pdf', name: 'report.pdf', size: '2 KB' }))

        expect(editor.marked[0].attrs).toEqual({
            id: 'a',
            src: 'https://example.test/report-2026.pdf',
            name: 'report.pdf',
            size: '2 KB',
        })
    })

    it('keeps a sized picture\'s width and gives it the new picture\'s shape', () => {
        // The old height with a new picture of another shape would squash it.
        const editor = editorWith([
            { pos: 1, type: 'image', attrs: { id: 'a', src: 'https://example.test/old.png', width: '400', height: 300 } },
        ])

        replaceAttachment(editor, replaced({ width: 1200, height: 600 }))

        expect(editor.marked[0].attrs.width).toBe('400')
        expect(editor.marked[0].attrs.height).toBe(200)
    })

    it('leaves the size of a picture alone where either shape is unknown', () => {
        const editor = editorWith([
            { pos: 1, type: 'image', attrs: { id: 'a', src: 'https://example.test/old.png', width: null, height: null } },
            { pos: 4, type: 'image', attrs: { id: 'a', src: 'https://example.test/old.png', width: 400, height: 300 } },
        ])

        replaceAttachment(editor, replaced())

        expect(editor.marked[0].attrs).toMatchObject({ width: null, height: null })
        expect(editor.marked[1].attrs).toMatchObject({ width: 400, height: 300 })
    })

    it('draws the new file where the address stayed the same', () => {
        // A disk keeps the path, and the browser would go on drawing the old picture out of
        // its cache. A signed address is new every time and must not be added to.
        vi.useFakeTimers()
        vi.setSystemTime(new Date('2026-09-29T10:00:00Z'))

        const stamp = new Date('2026-09-29T10:00:00Z').getTime()

        const editor = editorWith([
            { pos: 1, type: 'image', attrs: { id: 'a', src: 'https://example.test/same.png' } },
            { pos: 4, type: 'image', attrs: { id: 'b', src: 'https://s3.test/b.png?X-Amz-Signature=1' } },
        ])

        replaceAttachment(editor, replaced({ src: 'https://example.test/same.png' }))
        replaceAttachment(editor, replaced({ id: 'b', src: 'https://s3.test/b.png?X-Amz-Signature=1' }))

        expect(editor.marked[0].attrs.src).toBe(`https://example.test/same.png?v=${stamp}`)
        expect(editor.marked[1].attrs.src).toBe('https://s3.test/b.png?X-Amz-Signature=1')
    })

    it('does nothing where nothing points at the file', () => {
        const editor = editorWith([{ pos: 1, type: 'image', attrs: { id: 'b', src: 'x' } }])

        expect(replaceAttachment(editor, replaced())).toBe(false)
        expect(editor.dispatched).toHaveLength(0)
    })

    it('ignores word of a replacement that says nothing', () => {
        const editor = editorWith([{ pos: 1, type: 'image', attrs: { id: 'a', src: 'x' } }])

        expect(replaceAttachment(editor, { id: 'a', src: '' })).toBe(false)
        expect(replaceAttachment(editor, { src: 'https://example.test/new.png' })).toBe(false)
        expect(replaceAttachment(null, replaced())).toBe(false)
        expect(editor.marked).toHaveLength(0)
    })
})

describe('letting go of a deleted file', () => {
    it('takes every node carrying the id out, from the back, in one transaction', () => {
        // From the back, so taking one out does not move the ones still to go.
        const editor = editorWith([
            { pos: 1, type: 'image', attrs: { id: 'a' } },
            { pos: 4, type: 'image', attrs: { id: 'b' } },
            { pos: 7, type: 'media', attrs: { id: 'a' }, size: 1 },
            { pos: 9, type: 'file', attrs: { id: 'a' } },
        ])

        expect(removeAttachment(editor, { id: 'a' })).toBe(true)

        expect(editor.deleted).toEqual([[9, 10], [7, 8], [1, 2]])
        expect(editor.dispatched).toHaveLength(1)
        expect(editor.dispatched[0].meta.addToHistory).toBe(false)
    })

    it('does nothing where nothing points at the file', () => {
        const editor = editorWith([{ pos: 1, type: 'image', attrs: { id: 'b' } }])

        expect(removeAttachment(editor, { id: 'a' })).toBe(false)
        expect(removeAttachment(editor, {})).toBe(false)
        expect(editor.dispatched).toHaveLength(0)
    })
})

describe('the extension', () => {
    const extensionConfig = () => {
        let config = null

        window.FilamentRichEditor = {
            tiptap: { core: { Extension: { create: (definition) => (config = definition) } } },
        }

        mediaReplace()

        return config
    }

    it('listens while the editor lives, and stops when it goes', () => {
        const config = extensionConfig()
        const editor = editorWith([{ pos: 1, type: 'image', attrs: { id: 'a', src: 'https://example.test/old.png' } }])
        const self = { editor, storage: config.addStorage() }

        config.onCreate.call(self)

        window.dispatchEvent(new CustomEvent('arte-media-replaced', { detail: replaced() }))

        expect(editor.marked).toHaveLength(1)

        window.dispatchEvent(new CustomEvent('arte-media-deleted', { detail: { id: 'a' } }))

        expect(editor.deleted).toHaveLength(1)

        config.onDestroy.call(self)

        window.dispatchEvent(new CustomEvent('arte-media-replaced', { detail: replaced() }))
        window.dispatchEvent(new CustomEvent('arte-media-deleted', { detail: { id: 'a' } }))

        expect(editor.marked).toHaveLength(1)
        expect(editor.deleted).toHaveLength(1)
    })

    it('is nothing where Filament published no TipTap', () => {
        vi.spyOn(console, 'error').mockImplementation(() => {})

        expect(mediaReplace()).toBeNull()
    })
})
