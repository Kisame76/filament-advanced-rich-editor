import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import mediaPicker from '../../resources/js/media-picker.js'
import { item, mount } from './helpers.js'

/**
 * The media browser.
 *
 * Roughly six hundred lines of behaviour that used to live inside a Blade attribute, where
 * nothing could reach it. What is asserted here is the part a person notices when it breaks:
 * which page is asked for, what is selected after an upload, and whether the panel on the
 * right describes the picture on the left.
 *
 * Nothing here renders Filament. The component is handed the two callbacks the view builds
 * out of `$wire`, so the tests describe what the browser asks the server for without a
 * Livewire request existing at all.
 */

const page = (attributes = {}) => ({
    items: [item()],
    folders: [],
    types: ['jpeg'],
    parent: null,
    total: 1,
    hasMore: false,
    perPage: 40,
    ...attributes,
})

afterEach(() => {
    window.localStorage.clear()
    document.body.innerHTML = ''
})

describe('paging', () => {
    it('counts pages from what the server pages by, not from what came back', () => {
        const component = mount(mediaPicker)

        component.total = 95
        component.perPage = 40

        expect(component.pages).toBe(3)
    })

    it('is one page when the library is empty', () => {
        const component = mount(mediaPicker)

        component.total = 0

        expect(component.pages).toBe(1)
    })

    it('reads a page size of zero as one rather than dividing by it', () => {
        const component = mount(mediaPicker)

        component.total = 10
        component.perPage = 0

        // A footer counting ten pages of one picture is wrong about the library but right
        // about arithmetic. Infinity, or NaN, would take the footer out altogether.
        expect(component.pages).toBe(10)
    })

    it('refuses a page that does not exist and one that is already open', async () => {
        const fetchPage = vi.fn(async () => page())
        const component = mount(mediaPicker, { fetchPage })

        component.total = 95
        component.perPage = 40
        component.page = 2

        component.go(0)
        component.go(4)
        component.go(2)

        expect(fetchPage).not.toHaveBeenCalled()
        expect(component.page).toBe(2)

        component.go(3)

        expect(component.page).toBe(3)
        expect(fetchPage).toHaveBeenCalledOnce()
    })

    it('returns to the first page whenever the question changes', async () => {
        const fetchPage = vi.fn(async () => page())
        const component = mount(mediaPicker, { fetchPage })

        component.page = 4

        await component.reload()

        expect(component.page).toBe(1)
        expect(fetchPage).toHaveBeenCalledWith(expect.objectContaining({ page: 1 }))
    })
})

describe('loading a page', () => {
    it('asks for the folder, the search, the filter and the sort it is showing', async () => {
        const fetchPage = vi.fn(async () => page())
        const component = mount(mediaPicker, { fetchPage })

        component.search = 'sunset'
        component.folder = 'articles'
        component.type = 'png'
        component.sort = 'oldest'
        component.page = 2

        await component.load()

        expect(fetchPage).toHaveBeenCalledWith({
            search: 'sunset',
            folder: 'articles',
            page: 2,
            type: 'png',
            sort: 'oldest',
            kind: null,
        })
    })

    it('sends no type at all rather than an empty one', async () => {
        const fetchPage = vi.fn(async () => page())
        const component = mount(mediaPicker, { fetchPage })

        await component.load()

        expect(fetchPage).toHaveBeenCalledWith(expect.objectContaining({ type: null }))
    })

    it('takes the answer apart into what the grid draws', async () => {
        const component = mount(mediaPicker, {
            fetchPage: async () => page({
                items: [item({ id: 'a' }), item({ id: 'b' })],
                folders: ['articles'],
                types: ['jpeg', 'png'],
                parent: '',
                total: 42,
                hasMore: true,
                perPage: 12,
            }),
        })

        await component.load()

        expect(component.items).toHaveLength(2)
        expect(component.folders).toEqual(['articles'])
        expect(component.types).toEqual(['jpeg', 'png'])
        expect(component.parent).toBe('')
        expect(component.total).toBe(42)
        expect(component.hasMore).toBe(true)
        expect(component.perPage).toBe(12)
        expect(component.loading).toBe(false)
    })

    it('keeps the page size it was given when the answer carries none', async () => {
        const component = mount(mediaPicker, {
            pageSize: 25,
            fetchPage: async () => ({ items: [item()] }),
        })

        await component.load()

        expect(component.perPage).toBe(25)
        // Without a total, what came back is the whole of it.
        expect(component.total).toBe(1)
    })

    it('stops loading when the request fails, and says so once', async () => {
        const error = vi.spyOn(console, 'error').mockImplementation(() => {})

        const component = mount(mediaPicker, {
            fetchPage: async () => {
                throw new Error('gone')
            },
        })

        await component.load()

        expect(component.loading).toBe(false)
        expect(error).toHaveBeenCalledOnce()
    })
})

describe('folders', () => {
    it('drops the search when it walks into a folder, so the folder is not read as empty', async () => {
        const fetchPage = vi.fn(async () => page())
        const component = mount(mediaPicker, { fetchPage })

        component.search = 'sunset'
        component.page = 3

        component.open('articles')

        expect(component.folder).toBe('articles')
        expect(component.search).toBe('')
        expect(fetchPage).toHaveBeenCalledWith(expect.objectContaining({
            folder: 'articles',
            search: '',
            page: 1,
        }))
    })
})

describe('what is selected', () => {
    it('picks, and unpicks the one already picked', () => {
        const component = mount(mediaPicker)

        component.pick(item({ id: 'a' }))
        expect(component.picked).toBe('a')

        component.pick(item({ id: 'b' }))
        expect(component.picked).toBe('b')

        component.pick(item({ id: 'b' }))
        expect(component.picked).toBeNull()
    })

    it('describes the selection from the row until something better arrives', () => {
        const component = mount(mediaPicker)

        component.items = [item({ id: 'a', size: 10 })]
        component.picked = 'a'

        expect(component.selected.size).toBe(10)

        component.details = { id: 'a', size: 999 }
        component.detailsFor = 'a'

        expect(component.selected.size).toBe(999)
    })

    it('ignores a measurement that belongs to a different picture', () => {
        const component = mount(mediaPicker)

        component.items = [item({ id: 'a', size: 10 })]
        component.picked = 'a'
        component.details = { id: 'b', size: 999 }
        component.detailsFor = 'b'

        expect(component.selected.size).toBe(10)
    })

    it('has nothing selected when nothing matches', () => {
        const component = mount(mediaPicker)

        component.items = [item({ id: 'a' })]
        component.picked = 'zzz'

        expect(component.selected).toBeNull()
    })
})

describe('the details panel', () => {
    it('clears when nothing is selected', async () => {
        const fetchDetails = vi.fn()
        const component = mount(mediaPicker, { fetchDetails })

        component.details = { id: 'a' }
        component.detailsFor = 'a'

        await component.loadDetails(null)

        expect(component.details).toBeNull()
        expect(component.detailsFor).toBeNull()
        expect(fetchDetails).not.toHaveBeenCalled()
    })

    it('does not measure the same picture twice', async () => {
        const fetchDetails = vi.fn(async () => ({ id: 'a' }))
        const component = mount(mediaPicker, { fetchDetails })

        component.detailsFor = 'a'

        await component.loadDetails('a')

        expect(fetchDetails).not.toHaveBeenCalled()
    })

    it('shows the row it has, then replaces it with what was measured', async () => {
        const component = mount(mediaPicker, {
            fetchDetails: async () => ({ id: 'a', width: 4000, height: 3000 }),
        })

        component.items = [item({ id: 'a', width: null, height: null })]

        const loading = component.loadDetails('a')

        expect(component.details.id).toBe('a')
        expect(component.details.width).toBeNull()

        await loading

        expect(component.details.width).toBe(4000)
    })

    it('throws away an answer for a picture that is no longer selected', async () => {
        let release
        const component = mount(mediaPicker, {
            fetchDetails: () => new Promise((resolve) => {
                release = resolve
            }),
        })

        const loading = component.loadDetails('a')

        component.detailsFor = 'b'
        component.details = { id: 'b' }

        release({ id: 'a', width: 4000 })
        await loading

        expect(component.details.id).toBe('b')
    })

    it('survives a request that fails', async () => {
        const error = vi.spyOn(console, 'error').mockImplementation(() => {})

        const component = mount(mediaPicker, {
            fetchDetails: async () => {
                throw new Error('gone')
            },
        })

        component.items = [item({ id: 'a' })]

        await component.loadDetails('a')

        expect(component.details.id).toBe('a')
        expect(error).toHaveBeenCalledOnce()
    })
})

describe('emptiness', () => {
    it('is not empty while it is still loading', () => {
        const component = mount(mediaPicker)

        component.loading = true

        expect(component.isEmpty).toBe(false)
    })

    it('is empty only when neither a picture nor a folder came back', () => {
        const component = mount(mediaPicker)

        expect(component.isEmpty).toBe(true)

        component.folders = ['articles']

        expect(component.isEmpty).toBe(false)
    })

    it('counts a filter and a sort, but not a search, as filtered', () => {
        const component = mount(mediaPicker)

        expect(component.isFiltered).toBe(false)

        component.search = 'sunset'
        expect(component.isFiltered).toBe(false)

        component.type = 'png'
        expect(component.isFiltered).toBe(true)

        component.type = ''
        component.sort = 'oldest'
        expect(component.isFiltered).toBe(true)
    })
})

describe('starting up', () => {
    beforeEach(() => {
        vi.useFakeTimers()
    })

    afterEach(() => {
        vi.useRealTimers()
    })

    it('opens in the layout this browser was last left in', () => {
        window.localStorage.setItem('arte-media-view', 'list')

        const component = mount(mediaPicker, { listView: false })

        component.init()

        expect(component.list).toBe(true)
    })

    it('ignores a remembered value that is not a layout', () => {
        window.localStorage.setItem('arte-media-view', 'something-else')

        const component = mount(mediaPicker, { listView: true })

        component.init()

        expect(component.list).toBe(true)
    })

    it('remembers a layout that is switched', () => {
        const component = mount(mediaPicker)

        component.init()
        component.trigger('list', true)

        expect(window.localStorage.getItem('arte-media-view')).toBe('list')

        component.trigger('list', false)

        expect(window.localStorage.getItem('arte-media-view')).toBe('grid')
    })

    it('survives a browser that refuses to remember anything', () => {
        const setItem = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new Error('quota')
        })

        const component = mount(mediaPicker)

        component.init()

        expect(() => component.trigger('list', true)).not.toThrow()

        setItem.mockRestore()
    })

    it('waits out a burst of typing before it asks', () => {
        const fetchPage = vi.fn(async () => page())
        const component = mount(mediaPicker, { fetchPage })

        component.init()
        fetchPage.mockClear()

        component.trigger('search')
        vi.advanceTimersByTime(200)
        component.trigger('search')
        vi.advanceTimersByTime(200)

        expect(fetchPage).not.toHaveBeenCalled()

        vi.advanceTimersByTime(100)

        expect(fetchPage).toHaveBeenCalledOnce()
    })

    it('reloads a filter and a sort at once', () => {
        const fetchPage = vi.fn(async () => page())
        const component = mount(mediaPicker, { fetchPage })

        component.init()
        fetchPage.mockClear()

        component.trigger('type')
        component.trigger('sort')

        expect(fetchPage).toHaveBeenCalledTimes(2)
    })

    it('follows the selection rather than the click', () => {
        const fetchDetails = vi.fn(async () => null)
        const component = mount(mediaPicker, { fetchDetails })

        component.init()
        component.trigger('picked', 'a')

        expect(fetchDetails).toHaveBeenCalledWith('a')
    })

    it('describes a selection it was opened with', () => {
        const fetchDetails = vi.fn(async () => null)
        const component = mount(mediaPicker, { picked: 'a', fetchDetails })

        component.init()

        expect(fetchDetails).toHaveBeenCalledWith('a')
    })
})

describe('uploads', () => {
    beforeEach(() => {
        vi.useFakeTimers()
    })

    afterEach(() => {
        vi.useRealTimers()
        delete window.Alpine
    })

    /**
     * The dialog as the upload field sits in it: a modal, the grid, and Filament's own
     * uploader somewhere beside it with its FilePond instance on its Alpine data.
     */
    const withUploader = (pond) => {
        const modal = document.createElement('div')
        modal.className = 'fi-modal'

        const root = document.createElement('div')
        const uploader = document.createElement('div')
        uploader.className = 'fi-arte-media-uploader'

        modal.append(root, uploader)
        document.body.append(modal)

        window.Alpine = { $data: (element) => (element === uploader ? { pond } : null) }

        return root
    }

    it('finds the upload field inside the dialog it belongs to', () => {
        const pond = { browse: vi.fn(), on: vi.fn(), addFiles: vi.fn() }
        const component = mount(mediaPicker, {}, { root: withUploader(pond) })

        expect(component.pond).toBe(pond)
    })

    it('has no upload field before one is rendered', () => {
        const component = mount(mediaPicker)

        expect(component.pond).toBeNull()
    })

    it('waits for the field that is still loading, and gives up eventually', () => {
        const component = mount(mediaPicker)
        const callback = vi.fn()

        component.whenPond(callback)

        vi.advanceTimersByTime(100 * 59)
        expect(callback).not.toHaveBeenCalled()

        const pond = { browse: vi.fn(), on: vi.fn() }
        component.$root = withUploader(pond)

        vi.advanceTimersByTime(100)
        expect(callback).toHaveBeenCalledWith(pond)

        // And nothing is left ticking once it has answered.
        callback.mockClear()
        vi.advanceTimersByTime(100 * 100)
        expect(callback).not.toHaveBeenCalled()
    })

    it('stops asking after a minute of no upload field', () => {
        const component = mount(mediaPicker)
        const callback = vi.fn()

        component.whenPond(callback)

        vi.advanceTimersByTime(100 * 120)

        const pond = { browse: vi.fn(), on: vi.fn() }
        component.$root = withUploader(pond)

        vi.advanceTimersByTime(100 * 10)

        expect(callback).not.toHaveBeenCalled()
    })

    it('brings the grid back to where a new picture can be seen', async () => {
        const fetchPage = vi.fn(async () => page({
            items: [item({ id: 'new', pending: true }), item({ id: 'old' })],
        }))
        const component = mount(mediaPicker, { fetchPage })

        component.folder = 'articles'
        component.search = 'sunset'
        component.type = 'png'
        component.page = 3

        await component.revealUploads()

        expect(component.folder).toBeNull()
        expect(component.search).toBe('')
        expect(component.type).toBe('')
        expect(fetchPage).toHaveBeenCalledWith({
            search: '',
            folder: null,
            page: 1,
            type: null,
            sort: 'newest',
            kind: null,
        })
    })

    it('selects the picture that has just arrived, and nothing when none has', () => {
        const component = mount(mediaPicker)

        component.items = [item({ id: 'old' }), item({ id: 'new', pending: true })]
        component.selectNewest()

        expect(component.picked).toBe('new')

        component.picked = null
        component.items = [item({ id: 'old' })]
        component.selectNewest()

        expect(component.picked).toBeNull()
    })

    it('reveals and selects an upload the moment the field reports it', async () => {
        const handlers = {}
        const pond = {
            browse: vi.fn(),
            addFiles: vi.fn(),
            on: (event, callback) => {
                handlers[event] = callback
            },
        }

        const component = mount(mediaPicker, {
            fetchPage: async () => page({ items: [item({ id: 'new', pending: true })] }),
        }, { root: withUploader(pond) })

        component.watchUploads()

        expect(handlers.processfile).toBeTypeOf('function')

        await handlers.processfile()

        expect(component.picked).toBe('new')
    })

    it('opens the file dialog through the upload field itself', () => {
        const pond = { browse: vi.fn(), on: vi.fn() }
        const component = mount(mediaPicker, {}, { root: withUploader(pond) })

        component.upload()

        expect(pond.browse).toHaveBeenCalledOnce()
    })
})

describe('dropping files', () => {
    beforeEach(() => {
        vi.useFakeTimers()
    })

    afterEach(() => {
        vi.useRealTimers()
        delete window.Alpine
    })

    const dragEvent = (types) => ({
        dataTransfer: { types },
        preventDefault: vi.fn(),
    })

    it('lights up for files and ignores dragged text', () => {
        const component = mount(mediaPicker)

        const text = dragEvent(['text/plain'])
        component.onDragOver(text)

        expect(component.dropping).toBe(false)
        expect(text.preventDefault).not.toHaveBeenCalled()

        const files = dragEvent(['Files'])
        component.onDragOver(files)

        expect(component.dropping).toBe(true)
        expect(files.preventDefault).toHaveBeenCalledOnce()
    })

    it('keeps the highlight while the pointer crosses a child', () => {
        const component = mount(mediaPicker)
        const area = document.createElement('div')
        const child = document.createElement('div')
        area.append(child)

        component.dropping = true
        component.onDragLeave({ currentTarget: area, relatedTarget: child })

        expect(component.dropping).toBe(true)

        component.onDragLeave({ currentTarget: area, relatedTarget: document.createElement('div') })

        expect(component.dropping).toBe(false)
    })

    it('hands a dropped file to the upload field, and does nothing without one', () => {
        const pond = { addFiles: vi.fn(), on: vi.fn(), browse: vi.fn() }
        const modal = document.createElement('div')
        modal.className = 'fi-modal'
        const root = document.createElement('div')
        const uploader = document.createElement('div')
        uploader.className = 'fi-arte-media-uploader'
        modal.append(root, uploader)
        document.body.append(modal)
        window.Alpine = { $data: (element) => (element === uploader ? { pond } : null) }

        const component = mount(mediaPicker, {}, { root })

        const empty = { dataTransfer: { files: [] }, preventDefault: vi.fn() }
        component.onDrop(empty)

        expect(empty.preventDefault).not.toHaveBeenCalled()
        expect(pond.addFiles).not.toHaveBeenCalled()

        const file = new File(['x'], 'one.jpg', { type: 'image/jpeg' })
        const dropped = { dataTransfer: { files: [file] }, preventDefault: vi.fn() }

        component.dropping = true
        component.onDrop(dropped)

        expect(dropped.preventDefault).toHaveBeenCalledOnce()
        expect(component.dropping).toBe(false)
        expect(pond.addFiles).toHaveBeenCalledWith([file])
    })
})

describe('copying the link', () => {
    beforeEach(() => {
        vi.useFakeTimers()
    })

    afterEach(() => {
        vi.useRealTimers()
    })

    it('copies the selected picture and says so for a moment', async () => {
        const writeText = vi.fn(async () => {})
        Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true })

        const component = mount(mediaPicker)
        component.items = [item({ id: 'a', url: 'https://example.test/a.jpg' })]
        component.picked = 'a'

        await component.copy()

        expect(writeText).toHaveBeenCalledWith('https://example.test/a.jpg')
        expect(component.copied).toBe(true)

        vi.advanceTimersByTime(1500)

        expect(component.copied).toBe(false)
    })

    it('does nothing when nothing is selected', async () => {
        const writeText = vi.fn(async () => {})
        Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true })

        const component = mount(mediaPicker)

        await component.copy()

        expect(writeText).not.toHaveBeenCalled()
        expect(component.copied).toBe(false)
    })

    it('stays quiet when the browser refuses', async () => {
        const error = vi.spyOn(console, 'error').mockImplementation(() => {})
        Object.defineProperty(navigator, 'clipboard', {
            value: {
                writeText: async () => {
                    throw new Error('denied')
                },
            },
            configurable: true,
        })

        const component = mount(mediaPicker)
        component.items = [item({ id: 'a' })]
        component.picked = 'a'

        await component.copy()

        expect(component.copied).toBe(false)
        expect(error).toHaveBeenCalledOnce()
    })
})

describe('the numbers under a picture', () => {
    it('reads a size in the largest unit that leaves a whole number in front', () => {
        const component = mount(mediaPicker)

        expect(component.bytes(0)).toBe('—')
        expect(component.bytes(null)).toBe('—')
        expect(component.bytes(512)).toBe('512 B')
        expect(component.bytes(1024)).toBe('1.0 KB')
        expect(component.bytes(1024 * 15)).toBe('15 KB')
        expect(component.bytes(1024 * 1024 * 2.5)).toBe('2.5 MB')
        expect(component.bytes(1024 ** 4)).toBe('1024 GB')
    })

    it('reads dimensions only when both are known', () => {
        const component = mount(mediaPicker)

        expect(component.pixels(item({ width: 800, height: 600 }))).toBe('800 × 600')
        expect(component.pixels(item({ width: null, height: 600 }))).toBeNull()
        expect(component.pixels(null)).toBeNull()
    })

    it('joins what it knows and leaves out what it does not', () => {
        const component = mount(mediaPicker)

        expect(component.meta(item({ width: 800, height: 600, size: 2048 }))).toBe('800 × 600 · 2.0 KB')
        expect(component.meta(item({ width: null, height: null, size: 2048 }))).toBe('2.0 KB')
    })

    it('names a type from the mime, and falls back to a picture', () => {
        const component = mount(mediaPicker)

        expect(component.format(item({ mime: 'image/jpeg' }))).toBe('JPEG')
        expect(component.format(item({ mime: 'image/svg+xml' }))).toBe('SVG+')
        expect(component.format(item({ mime: 'video/mp4' }))).toBe('MP4')
        expect(component.format(item({ mime: null }))).toBe('FILE')
        expect(component.format(null)).toBe('FILE')
    })

    it('reads a server timestamp as local time', () => {
        const component = mount(mediaPicker)

        expect(component.when(null)).toBe('—')
        expect(component.when('not a date')).toBe('not a date')
        expect(component.when('2026-08-24 21:27:00'))
            .toBe(new Date(2026, 7, 24, 21, 27).toLocaleString())
    })
})

describe('the family tabs', () => {
    it('fills the tab row even when the dialog opened on one tab', async () => {
        // The video button opens the browser on Video. A guard that only populated the tab
        // list while no tab was chosen never populated it at all here, which left the row
        // hidden and no way back to All - and both sources answer with the families of the
        // POOL, not of the filtered page, so there is nothing to protect against.
        const fetchPage = vi.fn(async () => page({ kinds: ['image', 'video'] }))
        const component = mount(mediaPicker, { fetchPage, kind: 'video' })

        expect(component.kind).toBe('video')

        await component.load()

        expect(component.kinds).toEqual(['image', 'video'])
    })

    it('asks for the tab it is standing on', async () => {
        const fetchPage = vi.fn(async () => page({ kinds: ['image', 'video'] }))
        const component = mount(mediaPicker, { fetchPage, kind: 'audio' })

        await component.load()

        expect(fetchPage).toHaveBeenCalledWith(expect.objectContaining({ kind: 'audio' }))
    })

    it('sends one request when a tab change clears the mime filter', async () => {
        // Two watchers, one intention. Without the guard the tab change and the cleared
        // filter each fired a page request and the later answer won by luck.
        const fetchPage = vi.fn(async () => page())
        const component = mount(mediaPicker, { fetchPage })

        component.init()

        component.type = 'image/png'
        fetchPage.mockClear()

        // The tab change clears the filter, and the filter's own watcher then fires for a
        // change the tab change already caused.
        component.kind = 'video'
        component.trigger('kind')
        component.trigger('type')

        expect(component.type).toBe('')
        expect(fetchPage).toHaveBeenCalledTimes(1)
    })
})
