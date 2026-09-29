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

    it('badges an embed with the service it comes from', () => {
        const component = mount(mediaPicker)

        expect(component.format({ kind: 'embed', embed: { provider: 'youtube' } })).toBe('YOUTUBE')
        expect(component.format({ kind: 'embed' })).toBe('EMBED')
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

describe('what a tile draws', () => {
    it('draws the cover a film was given, rather than a badge', () => {
        // The bug this pins: covers were made, stored, and never shown - the tile asked
        // whether the row was a picture instead of whether it had one.
        const component = mount(mediaPicker)

        const film = item({ kind: 'video', mime: 'video/mp4', thumbnail: '/storage/cover.jpg' })

        expect(component.drawable(film)).toBe(true)
        expect(component.thumbnailOf(film)).toBe('/storage/cover.jpg')
    })

    it('draws a badge for a film that has no cover yet', () => {
        // And never the film's own address: an mp4 in an `<img>` is a broken-image icon.
        const film = item({ kind: 'video', mime: 'video/mp4', thumbnail: null, url: '/storage/a.mp4' })
        const component = mount(mediaPicker)

        expect(component.thumbnailOf(film)).toBeNull()
        expect(component.drawable(film)).toBe(false)
    })

    it('lets a picture stand in for itself', () => {
        const component = mount(mediaPicker)

        expect(component.thumbnailOf(item({ thumbnail: null, url: '/storage/a.png' })))
            .toBe('/storage/a.png')
    })

    it('keeps the panel drawing only a picture in an image element', () => {
        // The panel draws a film in a `<video>`, so a film with a cover must not also get an
        // `<img>` pointing at the mp4 beside it.
        const component = mount(mediaPicker)

        expect(component.isPicture(item({ kind: 'video', thumbnail: '/storage/cover.jpg' }))).toBe(false)
        expect(component.isPicture(item())).toBe(true)
    })
})

describe('an embed in the panel', () => {
    it('knows one from a file, and names the service', () => {
        const component = mount(mediaPicker, {
            labels: { sorts: {}, providers: { youtube: 'YouTube' } },
        })

        const embed = item({ kind: 'embed', embed: { provider: 'youtube', id: 'abc' } })

        expect(component.isEmbed(embed)).toBe(true)
        expect(component.isEmbed(item())).toBe(false)
        expect(component.providerOf(embed)).toBe('YouTube')
    })

    it('does not play until it is asked, and stops when the selection moves', async () => {
        // An iframe drawn on selection would call the video service from every editor that
        // opens the dialog; one left running in a hidden element is a video you can hear and
        // cannot stop.
        const component = mount(mediaPicker, {
            fetchDetails: async () => item({ id: 'b' }),
        })

        component.items = [item({ id: 'a', kind: 'embed' }), item({ id: 'b' })]
        component.picked = 'a'
        component.playing = true

        await component.loadDetails('b')

        expect(component.playing).toBe(false)
    })

    it('stops playing when nothing is selected at all', async () => {
        const component = mount(mediaPicker)

        component.playing = true

        await component.loadDetails(null)

        expect(component.playing).toBe(false)
    })

    it('copies the link a person recognises rather than the frame address', async () => {
        const writeText = vi.fn(async () => {})
        Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true })

        const component = mount(mediaPicker)

        component.items = [item({
            id: 'a',
            kind: 'embed',
            url: 'https://www.youtube.com/watch?v=abc',
            frame: 'https://www.youtube-nocookie.com/embed/abc',
        })]
        component.picked = 'a'

        await component.copy()

        expect(writeText).toHaveBeenCalledWith('https://www.youtube.com/watch?v=abc')
    })
})

describe('describing what is selected', () => {
    it('reads the description out of the details it fetched', async () => {
        const component = mount(mediaPicker, {
            fetchDetails: async () => item({ id: 'a', alt: 'The harbour', title: null }),
        })

        component.items = [item({ id: 'a' })]

        await component.loadDetails('a')

        expect(component.description).toBe('The harbour')
    })

    it('asks for the title rather than the alt text of a sound', async () => {
        const component = mount(mediaPicker, {
            fetchDetails: async () =>
                item({ id: 'a', kind: 'audio', mime: 'audio/mpeg', alt: null, title: 'The talk' }),
        })

        component.items = [item({ id: 'a', kind: 'audio', mime: 'audio/mpeg' })]
        component.picked = 'a'

        await component.loadDetails('a')

        expect(component.descriptionKey).toBe('title')
        expect(component.description).toBe('The talk')
    })

    it('saves once when the field is left, and says so for a moment', async () => {
        vi.useFakeTimers()

        const saveMetadata = vi.fn(async () => true)
        const component = mount(mediaPicker, { saveMetadata })

        component.items = [item({ id: 'a' })]
        component.picked = 'a'
        component.details = item({ id: 'a', alt: '' })
        component.detailsFor = 'a'
        component.description = 'The harbour'

        await component.saveDescription()

        expect(saveMetadata).toHaveBeenCalledWith('a', { alt: 'The harbour' })
        expect(component.descriptionSaved).toBe(true)

        vi.advanceTimersByTime(2000)

        expect(component.descriptionSaved).toBe(false)

        vi.useRealTimers()
    })

    it('does not save a value that has not changed', async () => {
        // The field is left every time somebody clicks anywhere in the dialog, and a request
        // per click is a request per click.
        const saveMetadata = vi.fn(async () => true)
        const component = mount(mediaPicker, { saveMetadata })

        component.items = [item({ id: 'a' })]
        component.picked = 'a'
        component.details = item({ id: 'a', alt: 'The harbour' })
        component.detailsFor = 'a'
        component.description = 'The harbour'

        await component.saveDescription()

        expect(saveMetadata).not.toHaveBeenCalled()
    })

    it('puts the old value back when the server refuses', async () => {
        const component = mount(mediaPicker, { saveMetadata: async () => false })

        component.items = [item({ id: 'a' })]
        component.picked = 'a'
        component.details = item({ id: 'a', alt: 'The harbour' })
        component.detailsFor = 'a'
        component.description = 'Something the server would not take'

        await component.saveDescription()

        expect(component.description).toBe('The harbour')
        expect(component.descriptionSaved).toBe(false)
    })

    it('stays quiet when the request itself fails', async () => {
        const error = vi.spyOn(console, 'error').mockImplementation(() => {})

        const component = mount(mediaPicker, {
            saveMetadata: async () => {
                throw new Error('gone')
            },
        })

        component.items = [item({ id: 'a' })]
        component.picked = 'a'
        component.details = item({ id: 'a', alt: 'The harbour' })
        component.detailsFor = 'a'
        component.description = 'New'

        await component.saveDescription()

        expect(component.description).toBe('The harbour')
        expect(error).toHaveBeenCalled()
    })

    it('keeps what was saved in the details, so leaving and coming back shows it', async () => {
        const component = mount(mediaPicker, { saveMetadata: async () => true })

        component.items = [item({ id: 'a' })]
        component.picked = 'a'
        component.details = item({ id: 'a', alt: '' })
        component.detailsFor = 'a'
        component.description = 'The harbour'

        await component.saveDescription()

        expect(component.details.alt).toBe('The harbour')
    })

    it('does nothing at all when nothing is selected', async () => {
        const saveMetadata = vi.fn(async () => true)
        const component = mount(mediaPicker, { saveMetadata })

        component.description = 'orphan'

        await component.saveDescription()

        expect(saveMetadata).not.toHaveBeenCalled()
    })
})

describe('something added from a dialog on top', () => {
    it('reloads and selects what was just added', async () => {
        // On the window, because that is where Livewire fires a component event - a listener
        // on the picker's own element is below it and never hears one.
        const fetchPage = vi.fn(async () => page({ items: [item({ id: 'new' })] }))

        const component = mount(mediaPicker, { fetchPage })

        component.watchAdded()

        window.dispatchEvent(new CustomEvent('arte-media-added', { detail: { id: 'new' } }))

        await vi.waitFor(() => expect(component.picked).toBe('new'))

        component.destroy()
    })

    it('stops listening once the dialog is gone', async () => {
        // The dialog is built fresh every time it opens, so a listener left behind is one
        // more reload per opening for ever.
        const fetchPage = vi.fn(async () => page())
        const component = mount(mediaPicker, { fetchPage })

        component.watchAdded()
        component.destroy()

        window.dispatchEvent(new CustomEvent('arte-media-added', { detail: { id: 'new' } }))

        await new Promise((resolve) => setTimeout(resolve, 10))

        expect(fetchPage).not.toHaveBeenCalled()
    })

    it('reloads and selects nothing when nothing was added to the library', async () => {
        // A typed address is a link, not an entry - there is no tile to select.
        const fetchPage = vi.fn(async () => page())

        const component = mount(mediaPicker, { fetchPage })

        component.picked = 'was-picked'
        component.watchAdded()

        window.dispatchEvent(new CustomEvent('arte-media-added', { detail: { id: null } }))

        await vi.waitFor(() => expect(fetchPage).toHaveBeenCalled())

        expect(component.picked).toBe('was-picked')

        component.destroy()
    })
})

describe('deleting what is selected', () => {
    const labels = {
        sorts: {},
        delete: 'Delete',
        deleteHeading: 'Delete “:name” for good?',
        confirmDelete: 'Anything pointing at it will break.',
    }

    /**
     * A grid with one file selected, whose dialog answers as it is told to.
     */
    const deleting = (answer, config = {}) => {
        const component = mount(mediaPicker, { labels, fetchPage: vi.fn(async () => page({ items: [] })), ...config })
        const ask = vi.spyOn(component, 'ask').mockResolvedValue(answer)

        component.items = [item({ id: 'a', name: 'sunset.png' })]
        component.picked = 'a'

        return { component, ask }
    }

    it('asks first, deletes, and clears the selection', async () => {
        const deleteMedia = vi.fn(async () => true)
        const fetchPage = vi.fn(async () => page({ items: [] }))
        const { component, ask } = deleting(true, { deleteMedia, fetchPage })

        await component.remove()

        expect(ask).toHaveBeenCalledOnce()
        expect(deleteMedia).toHaveBeenCalledWith('a')
        expect(component.picked).toBeNull()
        expect(fetchPage).toHaveBeenCalled()
    })

    it('asks in a dialog that names the file, and says what will happen to it', async () => {
        const { component, ask } = deleting(false)

        await component.remove()

        expect(ask).toHaveBeenCalledExactlyOnceWith({
            kind: 'delete',
            heading: 'Delete “sunset.png” for good?',
            description: 'Anything pointing at it will break.',
            confirm: 'Delete',
        })
    })

    it('does nothing when the question is answered no', async () => {
        const deleteMedia = vi.fn(async () => true)
        const { component } = deleting(false, { deleteMedia })

        await component.remove()

        expect(deleteMedia).not.toHaveBeenCalled()
        expect(component.picked).toBe('a')
        expect(component.deleting).toBe(false)
    })

    it('keeps the selection when the server refuses', async () => {
        const { component } = deleting(true, { deleteMedia: async () => false })

        await component.remove()

        expect(component.picked).toBe('a')
    })

    it('never asks in a library that offers no delete', async () => {
        const deleteMedia = vi.fn(async () => true)
        const { component, ask } = deleting(true, { deleteMedia, canDelete: false })

        await component.remove()

        expect(ask).not.toHaveBeenCalled()
        expect(deleteMedia).not.toHaveBeenCalled()
    })

    it('says it is busy while the server deletes, and not before or after', async () => {
        // Finding the entries and rewriting them can take a moment, and a button that looks
        // idle in the meantime is one that gets pressed twice.
        let during = null

        const { component } = deleting(true, {
            deleteMedia: async () => {
                during = component.deleting

                return true
            },
        })

        expect(component.deleting).toBe(false)

        await component.remove()

        expect(during).toBe(true)
        expect(component.deleting).toBe(false)
    })

    it('is not busy any more when the server fell over', async () => {
        vi.spyOn(console, 'error').mockImplementation(() => {})

        const { component } = deleting(true, {
            deleteMedia: async () => {
                throw new Error('offline')
            },
        })

        await component.remove()

        expect(component.deleting).toBe(false)
        expect(component.picked).toBe('a')
    })
})

describe('a document', () => {
    const report = (attributes = {}) =>
        item({
            kind: 'file',
            name: 'report.docx',
            mime: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            thumbnail: null,
            url: '/storage/report.docx',
            badge: 'DOCX',
            tint: '#2563eb',
            ...attributes,
        })

    it('wears the letters its card will wear, not a slice of its mime type', () => {
        // Read off the mime, a Word document was badged `VND.`.
        const component = mount(mediaPicker)

        expect(component.format(report())).toBe('DOCX')
    })

    it('draws a tile in its card\'s colour, and never its own address as a picture', () => {
        const component = mount(mediaPicker)

        expect(component.thumbnailOf(report())).toBeNull()
        expect(component.drawable(report())).toBe(false)
        expect(component.isPicture(report())).toBe(false)
        expect(component.tileStyle(report())).toEqual({ backgroundColor: '#2563eb', color: '#ffffff' })
        // Everything else keeps the sign the stylesheet draws.
        expect(component.tileStyle(item({ kind: 'video' }))).toEqual({})
    })

    it('has no description field, since nothing would read one', () => {
        const component = mount(mediaPicker)

        component.items = [report({ id: 'a' })]
        component.picked = 'a'

        expect(component.describable).toBe(false)

        component.items = [item({ id: 'b' })]
        component.picked = 'b'

        expect(component.describable).toBe(true)
    })
})

describe('an upload that is refused', () => {
    beforeEach(() => {
        vi.useFakeTimers()
    })

    afterEach(() => {
        vi.useRealTimers()
        delete window.Alpine
    })

    const withPond = () => {
        const handlers = {}
        const pond = {
            browse: vi.fn(),
            addFiles: vi.fn(async () => []),
            removeFile: vi.fn(),
            on: (event, callback) => {
                handlers[event] = callback
            },
        }

        const modal = document.createElement('div')
        modal.className = 'fi-modal'

        const root = document.createElement('div')
        const uploader = document.createElement('div')
        uploader.className = 'fi-arte-media-uploader'

        modal.append(root, uploader)
        document.body.append(modal)

        window.Alpine = { $data: (element) => (element === uploader ? { pond } : null) }

        return { handlers, pond, root }
    }

    it('says which files the server let go of, until it is dismissed', async () => {
        const fetchPage = vi.fn()
            .mockResolvedValueOnce(page({ rejected: ['page.html'] }))
            .mockResolvedValueOnce(page({ rejected: [] }))
        const component = mount(mediaPicker, { fetchPage })

        await component.load()

        expect(component.rejected).toEqual(['page.html'])

        // The server says it once, when it lets go of the file; the next page must not make
        // the note disappear before anybody read it.
        await component.load()

        expect(component.rejected).toEqual(['page.html'])

        component.dismissRejected()

        expect(component.rejected).toEqual([])
    })

    it('names a file the upload widget turned away before it travelled, and lets go of it', () => {
        // Kept, it would mark the widget's own input invalid - and a form holding an invalid
        // input refuses to submit, without a word, since the widget is off screen.
        const { handlers, pond, root } = withPond()
        const component = mount(mediaPicker, {}, { root })

        component.watchUploads()

        handlers.addfile({ main: 'File is of invalid type' }, { id: 'one', filename: 'setup.exe' })
        handlers.addfile(null, { id: 'two', filename: 'report.pdf' })

        expect(component.rejected).toEqual(['setup.exe'])
        expect(pond.removeFile).toHaveBeenCalledExactlyOnceWith('one')
    })

    it('names a file that failed on its way, lets go of it, and does not go looking for it', async () => {
        const fetchPage = vi.fn(async () => page())
        const { handlers, pond, root } = withPond()
        const component = mount(mediaPicker, { fetchPage }, { root })

        component.watchUploads()

        await handlers.processfile({ main: 'Upload failed' }, { id: 'big', filename: 'huge.zip' })

        expect(component.rejected).toEqual(['huge.zip'])
        expect(pond.removeFile).toHaveBeenCalledExactlyOnceWith('big')
        expect(fetchPage).not.toHaveBeenCalled()
    })

    it('does not leave a refused drop as an error nobody handles', () => {
        // The widget answers a drop holding one refused file by rejecting the whole promise;
        // the refusal is already told through `addfile`, so the promise has nothing to add -
        // and left alone it is an unhandled rejection in the console on every refused drop.
        const { pond, root } = withPond()
        const added = { catch: vi.fn() }
        pond.addFiles = vi.fn(() => added)

        const component = mount(mediaPicker, {}, { root })

        component.onDrop({
            dataTransfer: { files: [new File(['x'], 'page.html')] },
            preventDefault: () => {},
        })

        expect(pond.addFiles).toHaveBeenCalledOnce()
        expect(added.catch).toHaveBeenCalledOnce()
    })
})

describe('the actions the panel offers', () => {
    const offering = (selected, config = {}) => {
        const component = mount(mediaPicker, config)

        component.items = [selected]
        component.picked = selected.id

        return component
    }

    it('offers all four for a file in the library', () => {
        const component = offering(item({ replace: ['.jpg', '.png'] }))

        expect(component.actions).toEqual(['copy', 'download', 'replace', 'delete'])
        expect(['copy', 'download', 'replace', 'delete'].some((action) => component.wide(action))).toBe(false)
    })

    it('offers nothing to download or replace for an embed, which is a link', () => {
        const component = offering(item({ kind: 'embed', replace: null }))

        expect(component.actions).toEqual(['copy', 'delete'])
    })

    it('offers only the download for an upload that is not saved yet', () => {
        // Nothing of it is in the library, and its address is a temporary one.
        const component = offering(item({ pending: true, replace: null }))

        expect(component.actions).toEqual(['download'])
        expect(component.wide('download')).toBe(true)
    })

    it('offers no delete where the field may not, and widens the one left over', () => {
        const component = offering(item({ replace: ['.jpg'] }), { canDelete: false })

        expect(component.actions).toEqual(['copy', 'download', 'replace'])
        expect(component.wide('replace')).toBe(true)
        expect(component.wide('copy')).toBe(false)
    })

    it('offers no replace where the server named nothing that may take the place', () => {
        expect(offering(item({ replace: null })).actions).not.toContain('replace')
        expect(offering(item({ replace: ['.jpg'] }), { canReplace: false }).actions).not.toContain('replace')
    })

    it('offers nothing while nothing is selected', () => {
        const component = mount(mediaPicker)

        expect(component.actions).toEqual([])
        expect(component.has('copy')).toBe(false)
    })

    it('keeps Replace in its place until the server has said what may take the file\'s', () => {
        // The row a click is made on carries no answer - the details do - and a button that
        // turns up when they arrive moves every other one from under the cursor.
        const component = offering(item())

        expect(component.actions).toEqual(['copy', 'download', 'replace', 'delete'])
        expect(component.replaceReady).toBe(false)
    })

    it('is ready to replace once the server named what may take the place', () => {
        expect(offering(item({ replace: ['.jpg'] })).replaceReady).toBe(true)
        expect(offering(item({ replace: [] })).replaceReady).toBe(false)
        expect(offering(item({ replace: null })).replaceReady).toBe(false)
    })

    it('does not guess for an embed or an upload, which the row already says', () => {
        expect(offering(item({ kind: 'embed' })).actions).not.toContain('replace')
        expect(offering(item({ pending: true })).actions).not.toContain('replace')
    })

    it('has nothing to be ready for while nothing is selected', () => {
        expect(mount(mediaPicker).replaceReady).toBe(false)
    })
})

describe('replacing what is selected', () => {
    beforeEach(() => {
        vi.useFakeTimers()
    })

    afterEach(() => {
        vi.useRealTimers()
        vi.restoreAllMocks()
        delete window.Alpine
    })

    /**
     * The dialog with its second upload field, the one kept for replacements - with the
     * input FilePond clicks when it browses.
     */
    const withReplacer = () => {
        const handlers = {}
        const input = document.createElement('input')
        input.type = 'file'

        const element = document.createElement('div')
        element.append(input)

        const pond = {
            element,
            browse: vi.fn(),
            removeFile: vi.fn(),
            removeFiles: vi.fn(),
            on: (event, callback) => {
                handlers[event] = callback
            },
        }

        const modal = document.createElement('div')
        modal.className = 'fi-modal'

        const root = document.createElement('div')
        const replacer = document.createElement('div')
        replacer.className = 'fi-arte-media-replacer'

        modal.append(root, replacer)
        document.body.append(modal)

        window.Alpine = { $data: (candidate) => (candidate === replacer ? { pond } : null) }

        return { handlers, input, pond, root }
    }

    const labels = {
        sorts: {},
        replace: 'Replace',
        replacing: 'Uploading…',
        replaceHeading: 'Replace “:from” with “:to”?',
        confirmReplace: 'Every entry shows the new file.',
        replaceRefused: '“:name” cannot take its place. It takes :accept.',
        replaceFailed: 'The file could not be replaced.',
    }

    /**
     * A grid with one file selected and the upload field wired, whose dialog answers as it is
     * told to.
     */
    const replacing = (config = {}, answer = true) => {
        const { handlers, input, pond, root } = withReplacer()
        const component = mount(mediaPicker, { labels, fetchPage: vi.fn(async () => page()), ...config }, { root })
        const ask = vi.spyOn(component, 'ask').mockResolvedValue(answer)

        component.items = [item({ id: 'one', name: 'sunset.png', replace: ['.png'] })]
        component.picked = 'one'
        component.watchReplacements()

        return { ask, component, handlers, input, pond }
    }

    it('narrows the picker to what may take the place, and opens it', () => {
        const { component, input, pond } = replacing()

        component.replace()

        expect(input.getAttribute('accept')).toBe('.png')
        expect(pond.browse).toHaveBeenCalledOnce()
    })

    it('does not open the picker before the server has said what may take the place', () => {
        // The row carries no answer yet, and there is nothing to narrow the picker to.
        const { component, pond } = replacing()

        component.items = [item({ id: 'one', name: 'sunset.png' })]
        component.details = null

        component.replace()

        expect(pond.browse).not.toHaveBeenCalled()
    })

    it('asks first, naming both files, and replaces what was selected when the picker opened', async () => {
        const replaceMedia = vi.fn(async () => ({
            replaced: true,
            item: item({ id: 'one', name: 'sunset.png', url: 'https://example.test/dawn.png' }),
        }))
        const { ask, component, handlers, pond } = replacing({ replaceMedia })

        component.replace()
        handlers.addfile(null, { id: 'f1', filename: 'dawn.png' })

        // Somebody clicking about while the upload travels does not move the target.
        component.picked = null

        await handlers.processfile(null, { id: 'f1', filename: 'dawn.png' })

        expect(ask).toHaveBeenCalledExactlyOnceWith({
            kind: 'replace',
            heading: 'Replace “sunset.png” with “dawn.png”?',
            description: 'Every entry shows the new file.',
            confirm: 'Replace',
        })
        expect(replaceMedia).toHaveBeenCalledExactlyOnceWith('one')
        expect(component.replacing).toBe(false)
        expect(pond.removeFiles).toHaveBeenCalled()
    })

    it('says it is busy while the new file travels', () => {
        const { component, handlers } = replacing()

        component.replace()
        handlers.addfile(null, { id: 'f1', filename: 'dawn.png' })

        expect(component.replacing).toBe(true)
    })

    it('shows the new file in the panel and the grid', async () => {
        const fetchPage = vi.fn(async () => page({ items: [item({ id: 'one', url: 'https://example.test/dawn.png' })] }))
        const { component, handlers } = replacing({
            fetchPage,
            replaceMedia: async () => ({ replaced: true, item: item({ id: 'one', name: 'sunset.png', url: 'https://example.test/dawn.png', width: 12 }) }),
        })

        component.detailsFor = 'one'
        component.details = component.items[0]

        component.replace()
        await handlers.processfile(null, { id: 'f1', filename: 'dawn.png' })

        expect(component.selected.width).toBe(12)
        expect(fetchPage).toHaveBeenCalled()
    })

    it('draws a file whose address did not change as the new one, not the cached old one', async () => {
        vi.setSystemTime(new Date('2026-09-29T10:00:00Z'))

        const { component, handlers } = replacing({
            // A disk keeps the path, so the address is the old one.
            replaceMedia: async () => ({ replaced: true, item: item({ id: 'one', url: 'https://example.test/one.jpg' }) }),
        })

        component.replace()
        await handlers.processfile(null, { id: 'f1', filename: 'dawn.jpg' })

        const stamp = new Date('2026-09-29T10:00:00Z').getTime()

        expect(component.fresh('one', 'https://example.test/one.jpg')).toBe(`https://example.test/one.jpg?v=${stamp}`)
        // A signed address carries its own query, and is a new one every time anyway -
        // adding to it would break the signature.
        expect(component.fresh('one', 'https://s3.test/one.jpg?X-Amz-Signature=abc')).toBe('https://s3.test/one.jpg?X-Amz-Signature=abc')
        expect(component.fresh('two', 'https://example.test/two.jpg')).toBe('https://example.test/two.jpg')
    })

    it('tells the open editors what changed, so their nodes point at the new file', async () => {
        const heard = vi.fn()
        window.addEventListener('arte-media-replaced', heard)

        const { component, handlers } = replacing({
            replaceMedia: async () => ({
                replaced: true,
                item: item({ id: 'one', url: 'https://example.test/report-2026.pdf', kind: 'file', width: null, height: null }),
                card: { name: 'report.pdf', size: '2 KB' },
            }),
        })

        component.replace()
        await handlers.processfile(null, { id: 'f1', filename: 'report-2026.pdf' })

        window.removeEventListener('arte-media-replaced', heard)

        expect(heard).toHaveBeenCalledOnce()
        expect(heard.mock.calls[0][0].detail).toEqual({
            id: 'one',
            src: 'https://example.test/report-2026.pdf',
            name: 'report.pdf',
            size: '2 KB',
            width: null,
            height: null,
        })
    })

    it('lets go of the upload when the question is answered no', async () => {
        const replaceMedia = vi.fn()
        const { component, handlers, pond } = replacing({ replaceMedia }, false)

        component.replace()
        handlers.addfile(null, { id: 'f1', filename: 'dawn.png' })
        await handlers.processfile(null, { id: 'f1', filename: 'dawn.png' })

        // Reverted, so the server lets go of it too - left in the dialog, it would be the
        // file the next Replace found first.
        expect(pond.removeFile).toHaveBeenCalledExactlyOnceWith('f1', { revert: true })
        expect(replaceMedia).not.toHaveBeenCalled()
        expect(component.replacing).toBe(false)
    })

    it('says why the server refused, and what it would have taken', async () => {
        const { component, handlers } = replacing({
            replaceMedia: async () => ({ replaced: false, accept: ['.png'] }),
        })

        component.replace()
        await handlers.processfile(null, { id: 'f1', filename: 'dawn.jpg' })

        expect(component.replaceError).toBe('“dawn.jpg” cannot take its place. It takes .png.')
    })

    it('says so when the upload failed on its way', async () => {
        const { ask, component, handlers, pond } = replacing()

        component.replace()
        await handlers.processfile({ main: 'Upload failed' }, { id: 'f1', filename: 'huge.png' })

        expect(component.replaceError).toBe('The file could not be replaced.')
        expect(ask).not.toHaveBeenCalled()
        expect(pond.removeFile).toHaveBeenCalledExactlyOnceWith('f1')
    })

    it('forgets the refusal once another file is selected', async () => {
        const { component } = replacing()

        component.replaceError = 'Nope'

        await component.loadDetails('two')

        expect(component.replaceError).toBeNull()
    })

    it('ignores an upload into the field that nobody asked for', async () => {
        // FilePond reports every file it holds; only one sent by the button replaces anything.
        const replaceMedia = vi.fn()
        const { handlers } = replacing({ replaceMedia })

        await handlers.processfile(null, { id: 'f1', filename: 'dawn.png' })

        expect(replaceMedia).not.toHaveBeenCalled()
    })

    it('never opens the picker where the field may not replace', () => {
        const { component, pond } = replacing({ canReplace: false })

        component.replace()

        expect(pond.browse).not.toHaveBeenCalled()
    })
})

describe('deleting from a shared library', () => {
    it('warns that the file may be in documents nobody here can see', async () => {
        const component = mount(mediaPicker, {
            labels: {
                sorts: {},
                delete: 'Delete',
                deleteHeading: 'Delete “:name” for good?',
                confirmDelete: 'Delete?',
                confirmDeleteShared: 'Delete from every document?',
            },
            shared: true,
        })

        const ask = vi.spyOn(component, 'ask').mockResolvedValue(false)

        component.items = [item({ id: 'a' })]
        component.picked = 'a'

        await component.remove()

        expect(ask.mock.calls[0][0].description).toBe('Delete from every document?')
    })
})

describe('deleting a file other entries use', () => {
    afterEach(() => {
        vi.restoreAllMocks()
    })

    const labels = {
        sorts: {},
        delete: 'Delete',
        deleteHeading: 'Delete “:name”?',
        confirmDelete: 'Delete?',
        confirmDeleteShared: 'Delete from the shared library?',
        confirmDeleteUsed: ':count entries use it (:entries).',
        confirmDeleteUsedOne: 'One entry uses it (:entries).',
        usageMore: '+:count more',
        deletedFrom: 'Deleted, and taken out of :count entries.',
        deletedFromOne: 'Deleted, and taken out of one entry.',
    }

    const deleting = (answer, config = {}) => {
        const component = mount(mediaPicker, { labels, fetchPage: vi.fn(async () => page({ items: [] })), ...config })
        const ask = vi.spyOn(component, 'ask').mockResolvedValue(answer)

        component.items = [item({ id: 'a', name: 'Preise.pdf' })]
        component.picked = 'a'

        return { component, ask }
    }

    it('names the entries in the dialog', async () => {
        const fetchUsage = vi.fn(async () => ({ count: 2, entries: ['Post #1 “A”', 'Post #2 “B”'] }))
        const { component, ask } = deleting(false, { fetchUsage })

        await component.remove()

        expect(fetchUsage).toHaveBeenCalledWith('a')
        expect(ask).toHaveBeenCalledExactlyOnceWith({
            kind: 'delete',
            heading: 'Delete “Preise.pdf”?',
            description: '2 entries use it (Post #1 “A”, Post #2 “B”).',
            confirm: 'Delete',
        })
    })

    it('says so in the singular for one entry', async () => {
        const { component, ask } = deleting(false, { fetchUsage: async () => ({ count: 1, entries: ['Post #1 “A”'] }) })

        await component.remove()

        expect(ask.mock.calls[0][0].description).toBe('One entry uses it (Post #1 “A”).')
    })

    it('names a few and counts the rest', async () => {
        const { component, ask } = deleting(false, { fetchUsage: async () => ({ count: 5, entries: ['A', 'B', 'C'] }) })

        await component.remove()

        expect(ask.mock.calls[0][0].description).toBe('5 entries use it (A, B, C, +2 more).')
    })

    it('asks the plain question where nothing uses the file, or nothing could be read', async () => {
        vi.spyOn(console, 'error').mockImplementation(() => {})

        const shared = deleting(false, { fetchUsage: async () => ({ count: 0, entries: [] }), shared: true })
        const failed = deleting(false, {
            fetchUsage: async () => {
                throw new Error('offline')
            },
        })

        await shared.component.remove()
        await failed.component.remove()

        expect(shared.ask.mock.calls[0][0].description).toBe('Delete from the shared library?')
        expect(failed.ask.mock.calls[0][0].description).toBe('Delete?')
    })

    it('tells the open editors to let go of the file, and says how many entries it left', async () => {
        const heard = vi.fn()
        window.addEventListener('arte-media-deleted', heard)

        const { component } = deleting(true, {
            fetchUsage: async () => ({ count: 3, entries: ['A', 'B', 'C'] }),
            deleteMedia: async () => ({ deleted: true, documents: 3 }),
        })

        await component.remove()

        window.removeEventListener('arte-media-deleted', heard)

        expect(heard).toHaveBeenCalledOnce()
        expect(heard.mock.calls[0][0].detail).toEqual({ id: 'a' })
        expect(component.notice).toBe('Deleted, and taken out of 3 entries.')
        expect(component.picked).toBeNull()
    })

    it('says nothing and tells nobody when the server did not delete', async () => {
        const heard = vi.fn()
        window.addEventListener('arte-media-deleted', heard)

        const { component } = deleting(true, { deleteMedia: async () => ({ deleted: false, documents: 0 }) })

        await component.remove()

        window.removeEventListener('arte-media-deleted', heard)

        expect(heard).not.toHaveBeenCalled()
        expect(component.notice).toBeNull()
        expect(component.picked).toBe('a')
    })
})

describe('replacing a file other entries use', () => {
    beforeEach(() => {
        vi.useFakeTimers()
    })

    afterEach(() => {
        vi.useRealTimers()
        vi.restoreAllMocks()
        delete window.Alpine
    })

    const withReplacer = () => {
        const handlers = {}
        const pond = {
            element: document.createElement('div'),
            browse: vi.fn(),
            removeFile: vi.fn(),
            removeFiles: vi.fn(),
            on: (event, callback) => {
                handlers[event] = callback
            },
        }

        const modal = document.createElement('div')
        modal.className = 'fi-modal'

        const root = document.createElement('div')
        const replacer = document.createElement('div')
        replacer.className = 'fi-arte-media-replacer'

        modal.append(root, replacer)
        document.body.append(modal)

        window.Alpine = { $data: (candidate) => (candidate === replacer ? { pond } : null) }

        return { handlers, root }
    }

    const labels = {
        sorts: {},
        replace: 'Replace',
        replaceHeading: 'Replace “:from” with “:to”?',
        confirmReplace: 'Every entry shows the new file.',
        confirmReplaceUsed: ':count entries show it (:entries).',
        confirmReplaceUsedOne: 'One entry shows it (:entries).',
        usageMore: '+:count more',
        replacedIn: 'Replaced, and updated in :count entries.',
        replacedInOne: 'Replaced, and updated in one entry.',
    }

    const replacing = (config = {}, answer = true) => {
        const { handlers, root } = withReplacer()
        const component = mount(mediaPicker, { labels, fetchPage: vi.fn(async () => page()), ...config }, { root })
        const ask = vi.spyOn(component, 'ask').mockResolvedValue(answer)

        component.items = [item({ id: 'one', name: 'sunset.png', replace: ['.png'] })]
        component.picked = 'one'
        component.watchReplacements()

        return { ask, component, handlers }
    }

    it('names the entries showing the file in the dialog', async () => {
        const { ask, component, handlers } = replacing({
            fetchUsage: async () => ({ count: 2, entries: ['Post #1 “A”', 'Post #2 “B”'] }),
        }, false)

        component.replace()
        await handlers.processfile(null, { id: 'f1', filename: 'dawn.png' })

        expect(ask).toHaveBeenCalledExactlyOnceWith({
            kind: 'replace',
            heading: 'Replace “sunset.png” with “dawn.png”?',
            description: '2 entries show it (Post #1 “A”, Post #2 “B”).',
            confirm: 'Replace',
        })
    })

    it('says so in the singular for one entry', async () => {
        const { ask, component, handlers } = replacing({
            fetchUsage: async () => ({ count: 1, entries: ['Post #1 “A”'] }),
        }, false)

        component.replace()
        await handlers.processfile(null, { id: 'f1', filename: 'dawn.png' })

        expect(ask.mock.calls[0][0].description).toBe('One entry shows it (Post #1 “A”).')
    })

    it('says how many entries now show the new file', async () => {
        const { component, handlers } = replacing({
            fetchUsage: async () => ({ count: 1, entries: ['Post #1 “A”'] }),
            replaceMedia: async () => ({ replaced: true, item: item({ id: 'one' }), documents: 1 }),
        })

        component.replace()
        await handlers.processfile(null, { id: 'f1', filename: 'dawn.png' })

        expect(component.notice).toBe('Replaced, and updated in one entry.')
    })
})

describe('the dialog that asks', () => {
    /**
     * Filament's own modal is drawn by the view and answers to the events it always answers
     * to, so what is pinned here is the conversation with it: which event opens which dialog,
     * and that a question is answered exactly once, whichever way the dialog closes.
     */
    const question = (kind = 'delete') => ({ kind, heading: 'Delete it?', description: 'For good.', confirm: 'Delete' })

    const asking = () => mount(mediaPicker, { confirmId: 'confirm' })

    it('opens the dialog of its kind, with the words of the question', () => {
        const component = asking()

        component.ask(question('replace'))

        expect(component.dispatched).toEqual([{ name: 'open-modal', detail: { id: 'confirm-replace' } }])
        expect(component.question).toMatchObject({ heading: 'Delete it?', description: 'For good.', confirm: 'Delete' })
    })

    it('answers yes when its confirm button is pressed, and closes the dialog', async () => {
        const component = asking()
        const answer = component.ask(question())

        component.yes()

        await expect(answer).resolves.toBe(true)
        expect(component.dispatched.at(-1)).toEqual({ name: 'close-modal', detail: { id: 'confirm-delete' } })
    })

    it('closes the dialog when its cancel button is pressed, and leaves the answer to the closing', async () => {
        const component = asking()
        const answer = component.ask(question())

        component.no()

        expect(component.dispatched.at(-1)).toEqual({ name: 'close-modal', detail: { id: 'confirm-delete' } })

        // What Filament says once the modal has closed.
        component.dismissed('confirm-delete')

        await expect(answer).resolves.toBe(false)
    })

    it('answers no when the dialog closes any other way', async () => {
        // Its cancel button, Escape and a click beside it all end in the same event.
        const component = asking()
        const answer = component.ask(question())

        component.dismissed('confirm-delete')

        await expect(answer).resolves.toBe(false)
    })

    it('answers once: the closing that follows a yes is nobody\'s to answer', async () => {
        // Closing the dialog from the confirm button is itself a closing, and says so.
        const component = asking()
        const answer = component.ask(question())

        component.yes()
        component.dismissed('confirm-delete')

        await expect(answer).resolves.toBe(true)
    })

    it('waits through the closing of a dialog that is not its own', async () => {
        // Filament announces every modal that closes, the browser's own included.
        const component = asking()
        const answer = component.ask(question())
        const settled = vi.fn()

        answer.then(settled)

        component.dismissed('fi-livewire-action-0')
        await Promise.resolve()

        expect(settled).not.toHaveBeenCalled()

        component.yes()
        await answer
    })

    it('answers a question still open with no once the next one is asked', async () => {
        // Two dialogs at once would leave the first waiting for ever.
        const component = asking()
        const first = component.ask(question('delete'))
        const second = component.ask(question('replace'))

        await expect(first).resolves.toBe(false)

        component.yes()

        await expect(second).resolves.toBe(true)
    })

    it('keeps the words of the last question, so the dialog does not empty as it fades', () => {
        const component = asking()

        component.ask(question())
        component.yes()

        expect(component.question.heading).toBe('Delete it?')
    })
})
