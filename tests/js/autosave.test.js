import { beforeEach, describe, expect, it } from 'vitest'
import {
    PREFIX,
    draftKey,
    prune,
    readDraft,
    shouldOffer,
    writeDraft,
} from '../../resources/js/autosave.js'

/**
 * A draft in the browser.
 *
 * What is asserted here is everything about a draft that is not the editor: where it lives,
 * how long it lives, when it is worth offering back and what happens when the storage it
 * lives in is full. All of it is a function over a storage object and plain data, which is
 * the reason the awkward half - the quota, the expiry, the sweeping up - can be held to
 * account at all. What is left over is the transaction that puts one back, and only an
 * editor can prove that.
 *
 * The storage is a stand-in rather than the real `localStorage`, so that a quota can be
 * reached on purpose: a browser's is five megabytes and a test that filled one would be a
 * test nobody runs twice.
 */
function fakeStorage({ limit = Infinity } = {}) {
    const entries = new Map()

    return {
        get length() {
            return entries.size
        },
        key: (index) => [...entries.keys()][index] ?? null,
        getItem: (key) => entries.get(key) ?? null,
        removeItem: (key) => entries.delete(key),
        setItem: (key, value) => {
            const size = [...entries]
                .filter(([existing]) => existing !== key)
                .reduce((total, [, held]) => total + held.length, 0)

            if (size + value.length > limit) {
                throw new DOMException('quota', 'QuotaExceededError')
            }

            entries.set(key, value)
        },
    }
}

const doc = (text) => ({ type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text }] }] })

const DAY = 24 * 60 * 60 * 1000

let storage

beforeEach(() => {
    storage = fakeStorage()
})

describe('where a draft lives', () => {
    it('is told apart by the field and by the page it is on', () => {
        // Neither half knows enough alone: PHP knows the record and the field but not which
        // page they are on, because to Livewire every request looks like the same endpoint.
        expect(draftKey('abc123', '/articles/4/edit')).toBe(`${PREFIX}/articles/4/edit::abc123`)
        expect(draftKey('abc123', '/articles/create')).not.toBe(draftKey('abc123', '/articles/4/edit'))
    })
})

describe('reading one back', () => {
    it('gives back what was written', () => {
        writeDraft(storage, 'k', doc('hello'), { now: 1000 })

        expect(readDraft(storage, 'k')).toEqual({ content: doc('hello'), savedAt: 1000 })
    })

    it('has nothing to say about a field that never wrote one', () => {
        expect(readDraft(storage, 'k')).toBeNull()
    })

    it('throws away one that is older than a project allows', () => {
        writeDraft(storage, 'k', doc('hello'), { now: 0 })

        expect(readDraft(storage, 'k', { now: DAY + 1, ttl: DAY })).toBeNull()
        // And removed rather than merely refused, since nothing else would ever remove it.
        expect(storage.getItem('k')).toBeNull()
    })

    it('keeps one that is inside it', () => {
        writeDraft(storage, 'k', doc('hello'), { now: 0 })

        expect(readDraft(storage, 'k', { now: DAY - 1, ttl: DAY })).not.toBeNull()
    })

    it('refuses anything it cannot read as a draft', () => {
        storage.setItem('k', 'not json')
        expect(readDraft(storage, 'k')).toBeNull()

        storage.setItem('k', JSON.stringify({ content: doc('hello') }))
        expect(readDraft(storage, 'k')).toBeNull()
    })
})

describe('sweeping up', () => {
    it('removes every expired draft and leaves the rest', () => {
        writeDraft(storage, `${PREFIX}old`, doc('old'), { now: 0 })
        writeDraft(storage, `${PREFIX}new`, doc('new'), { now: DAY })

        expect(prune(storage, { now: DAY + 1, ttl: DAY })).toEqual([`${PREFIX}old`])
        expect(readDraft(storage, `${PREFIX}new`)).not.toBeNull()
    })

    it('removes a run of expired drafts without stepping over any of them', () => {
        // Removing from a storage shifts every key after it down one, so a walk that reads
        // by index while that is happening skips whatever moved into the place just freed.
        // Two entries cannot show it; three in a row can, and did.
        writeDraft(storage, `${PREFIX}a`, doc('a'), { now: 0 })
        writeDraft(storage, `${PREFIX}b`, doc('b'), { now: 0 })
        writeDraft(storage, `${PREFIX}c`, doc('c'), { now: 0 })

        expect(prune(storage, { now: DAY * 5, ttl: DAY })).toHaveLength(3)
        expect(storage.length).toBe(0)
    })

    it('touches nothing that another script put there', () => {
        storage.setItem('somebody-else', 'theirs')

        prune(storage, { now: DAY * 400, ttl: DAY })

        expect(storage.getItem('somebody-else')).toBe('theirs')
    })

    it('does nothing where a project asked for no expiry', () => {
        writeDraft(storage, `${PREFIX}old`, doc('old'), { now: 0 })

        expect(prune(storage, { now: DAY * 400, ttl: 0 })).toEqual([])
        expect(readDraft(storage, `${PREFIX}old`)).not.toBeNull()
    })
})

describe('a storage that is full', () => {
    it('drops its own older drafts to make room for the one being written', () => {
        const small = fakeStorage({ limit: 200 })

        writeDraft(small, `${PREFIX}old`, doc('an older article nobody came back for'), { now: 0 })

        expect(writeDraft(small, `${PREFIX}mine`, doc('what is being written now'), { now: DAY })).toBe(true)
        expect(readDraft(small, `${PREFIX}mine`)).not.toBeNull()
        // The one nobody came back for is worth less than the one being written.
        expect(readDraft(small, `${PREFIX}old`)).toBeNull()
    })

    it('leaves what another script stored alone and gives up instead', () => {
        const small = fakeStorage({ limit: 40 })

        small.setItem('somebody-else', 'x'.repeat(35))

        expect(writeDraft(small, `${PREFIX}mine`, doc('a whole article'), { now: 0 })).toBe(false)
        expect(small.getItem('somebody-else')).toBe('x'.repeat(35))
    })
})

describe('whether one is worth offering', () => {
    it('offers a draft that says something else', () => {
        expect(shouldOffer({ content: doc('draft'), savedAt: 0 }, doc('server'))).toBe(true)
    })

    it('says nothing about a draft of the document already on screen', () => {
        // The work reached the server after all. Offering to restore it would be offering
        // to do nothing, and the same question would come back on the next opening.
        expect(shouldOffer({ content: doc('same'), savedAt: 0 }, doc('same'))).toBe(false)
    })

    it('says nothing when there is no draft', () => {
        expect(shouldOffer(null, doc('server'))).toBe(false)
    })
})
