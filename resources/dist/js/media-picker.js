/**
 * The media browser.
 *
 * Two columns, because choosing a picture is two questions: which one, and is this the right
 * one. The left column answers the first by showing many at once; the right answers the second
 * about the one that is selected, with the numbers that tell two similar photographs apart.
 *
 * Nothing about the library is decided here. Every picture arrives from the editor this grid
 * belongs to, through the two callbacks the view builds - which is what keeps the pool that
 * authorises a stored id on the server, where it has to stay. This file knows how to ask and
 * what to do with the answer, and that is all.
 *
 * This package ships no bundler, so the file must stay free of `import` statements: Filament
 * loads it verbatim as an Alpine component through `x-load-src`. The default export is the
 * factory the `x-data` expression calls.
 */
export default ({
    labels,
    hasFolders,
    listView,
    pageSize,
    picked,
    fetchPage,
    fetchDetails,
}) => ({
    items: [],
    folders: [],
    types: [],
    parent: null,
    folder: null,
    search: '',
    type: '',
    sort: 'newest',
    page: 1,
    total: 0,
    hasMore: false,
    loading: false,
    copied: false,
    dropping: false,
    details: null,
    detailsFor: null,
    list: listView,
    // What the server pages by. Guessing it from how many tiles came back read a
    // short last page as a tiny page size, and the footer then divided the whole
    // library by it - inventing pages that led to an empty grid.
    perPage: pageSize,
    picked,
    labels,
    hasFolders,

    init() {
        // Which layout somebody browses in is a habit rather than a setting, so it is
        // remembered where habits belong - in this browser - instead of being asked
        // again every time the dialog opens.
        const remembered = window.localStorage?.getItem('arte-media-view')

        if (remembered === 'list' || remembered === 'grid') {
            this.list = remembered === 'list'
        }

        this.$watch('list', (value) => {
            try {
                window.localStorage?.setItem('arte-media-view', value ? 'list' : 'grid')
            } catch (error) {
                // Private browsing, or a full quota. Not remembering is survivable.
            }
        })

        this.load()

        this.watchUploads()

        // Debounced by hand rather than with `x-model.debounce`, because a folder or a
        // filter change has to reload at once while typing must not fire a request per
        // keystroke.
        this.$watch('search', () => {
            clearTimeout(this._timer)
            this._timer = setTimeout(() => this.reload(), 300)
        })

        this.$watch('type', () => this.reload())
        this.$watch('sort', () => this.reload())

        // The panel follows the selection rather than the click, so it is also right
        // when the selection was restored from an image already in the document.
        this.$watch('picked', (id) => this.loadDetails(id))

        this.loadDetails(this.picked)
    },

    get pages() {
        return Math.max(1, Math.ceil(this.total / Math.max(1, this.perPage)))
    },

    get isEmpty() {
        return ! this.loading && this.items.length === 0 && this.folders.length === 0
    },

    get isFiltered() {
        return this.type !== '' || this.sort !== 'newest'
    },

    get selected() {
        // The measured copy wins over the row it came from. The row is what fills the
        // panel instantly, but it carries no size in pixels - that is the one field
        // worth a second request, and preferring the row would throw it away again.
        if (this.details && this.detailsFor === this.picked) {
            return this.details
        }

        return this.items.find((item) => item.id === this.picked) ?? null
    },

    reload() {
        this.page = 1

        return this.load()
    },

    open(path) {
        this.folder = path
        this.search = ''
        this.reload()
    },

    async load() {
        this.loading = true

        try {
            const result = await fetchPage({
                search: this.search,
                folder: this.folder,
                page: this.page,
                type: this.type || null,
                sort: this.sort,
            })

            this.items = result?.items ?? []
            this.hasMore = result?.hasMore ?? false
            this.total = result?.total ?? this.items.length
            this.types = result?.types ?? []
            this.perPage = result?.perPage ?? this.perPage
            this.folders = result?.folders ?? []
            this.parent = result?.parent ?? null
        } catch (error) {
            console.error('The advanced rich editor could not read the media library:', error)
        } finally {
            this.loading = false
        }
    },

    async loadDetails(id) {
        if (! id) {
            this.details = null
            this.detailsFor = null

            return
        }

        if (this.detailsFor === id) {
            return
        }

        this.details = this.items.find((item) => item.id === id) ?? null
        this.detailsFor = id

        try {
            const result = await fetchDetails(id)

            if (result && this.detailsFor === id) {
                this.details = result
            }
        } catch (error) {
            console.error('The advanced rich editor could not read that picture:', error)
        }
    },

    go(page) {
        if (page < 1 || page > this.pages || page === this.page) {
            return
        }

        this.page = page
        this.load()
    },

    pick(item) {
        // Only ever a selection. An upload that is not chosen stays where it is, in
        // plain sight: it was fetched on purpose, and having it vanish because
        // something else was clicked is the surprise this used to spring.
        //
        // Nothing is lost by keeping it. A file that is never inserted is never turned
        // into an attachment either - it stays a temporary upload and expires on its
        // own, so there is nothing to tidy up and nothing to delete.
        //
        // Clicking the chosen one again unpicks it, so the dialog can be used to change
        // only the alt text of an image that is already in the document.
        this.picked = this.picked === item.id ? null : item.id
    },

    /**
     * Filament's own upload field, kept off screen.
     *
     * Every way of adding a picture ends at this one object: the button in the header
     * and a file dropped onto the library both hand it over. Driving the widget through
     * its own API rather than faking events at the input underneath it is what makes
     * both routes behave identically - and it is the whole upload path, with Livewire's
     * protocol, the size and type checks and the progress behind it.
     */
    get pond() {
        const scope = this.$root.closest('.fi-modal') ?? document

        const element = scope.querySelector('.fi-arte-media-uploader')

        return element ? (window.Alpine.$data(element)?.pond ?? null) : null
    },

    /**
     * Runs something once the upload field exists.
     *
     * It is lazily loaded, so for the first moments after the dialog opens it is not
     * there - and that is exactly when somebody drops the picture they opened the
     * dialog for. Waiting is what stops that drop from quietly doing nothing.
     */
    whenPond(callback, attempt = 0) {
        const pond = this.pond

        if (pond) {
            callback(pond)

            return
        }

        if (attempt < 60) {
            setTimeout(() => this.whenPond(callback, attempt + 1), 100)
        }
    },

    watchUploads() {
        this.whenPond((pond) => {
            // An upload does not stay in this dialog - it is handed to the editor as it
            // arrives, which is what makes it survive the dialog closing. So there is
            // nothing to mirror here: the grid simply asks again, and the new picture
            // is in the answer, described by the server like every other one.
            pond.on('processfile', () => this.revealUploads().then(() => this.selectNewest()))
        })
    },

    /**
     * Brings the grid back to where an upload can be seen.
     *
     * A picture that is not saved yet is not in the library, so the server can only
     * ever put it in front of the first page of the root - and only when the search
     * and the type filter would have let it through. A grid still standing in a folder,
     * or on a search the file name does not match, therefore asked and was answered
     * without it: no tile, no selection, and no clue that anything had happened.
     */
    revealUploads() {
        this.folder = null
        this.search = ''
        this.type = ''

        return this.reload()
    },

    /**
     * Selects the picture that has just arrived.
     *
     * Uploads that are not saved yet sort to the front, newest first, so the first of
     * them is the one somebody just went and fetched - and selecting it is what they
     * expect after dropping a file.
     */
    selectNewest() {
        const arrived = this.items.find((item) => item.pending)

        if (arrived) {
            this.picked = arrived.id
        }
    },

    upload() {
        this.whenPond((pond) => pond.browse())
    },

    onDragOver(event) {
        // Files only. Dragging selected text across the dialog is not an upload.
        if (! [...(event.dataTransfer?.types ?? [])].includes('Files')) {
            return
        }

        event.preventDefault()
        this.dropping = true
    },

    onDragLeave(event) {
        // `dragleave` fires for every child the pointer crosses, so the highlight is
        // only dropped once the pointer has actually left the area.
        if (event.currentTarget.contains(event.relatedTarget)) {
            return
        }

        this.dropping = false
    },

    onDrop(event) {
        const files = [...(event.dataTransfer?.files ?? [])]

        if (files.length === 0) {
            return
        }

        event.preventDefault()
        this.dropping = false

        // Handed to the upload widget itself, which is the same thing the browse
        // button does - so a dropped picture and a chosen one travel one path. Held
        // until it exists, because a drop in the first moment after the dialog opens
        // must not be the one that gets lost.
        this.whenPond((pond) => pond.addFiles(files))
    },

    async copy() {
        const url = this.selected?.url

        if (! url) {
            return
        }

        try {
            await navigator.clipboard.writeText(url)

            this.copied = true
            setTimeout(() => (this.copied = false), 1500)
        } catch (error) {
            console.error('The advanced rich editor could not copy that link:', error)
        }
    },

    bytes(value) {
        if (! value) {
            return '—'
        }

        const units = ['B', 'KB', 'MB', 'GB']
        let size = value
        let unit = 0

        while (size >= 1024 && unit < units.length - 1) {
            size /= 1024
            unit++
        }

        return `${size < 10 && unit > 0 ? size.toFixed(1) : Math.round(size)} ${units[unit]}`
    },

    pixels(item) {
        return (item?.width && item?.height) ? `${item.width} × ${item.height}` : null
    },

    meta(item) {
        return [this.pixels(item), this.bytes(item.size)].filter(Boolean).join(' · ')
    },

    kind(item) {
        return (item?.mime ?? '').split('/')[1]?.toUpperCase().slice(0, 4) || 'IMG'
    },

    when(value) {
        if (! value) {
            return '—'
        }

        // Parsed as local time: the value is a plain `Y-m-d H:i:s` from the server, and
        // handing that to `Date` unchanged is read as UTC by some browsers and as local
        // by others.
        const date = new Date(value.replace(' ', 'T'))

        return Number.isNaN(date.valueOf()) ? value : date.toLocaleString()
    },
})
