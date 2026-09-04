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
    kind: initialKind = '',
    picked,
    fetchPage,
    fetchDetails,
    saveMetadata,
    deleteMedia,
    canDelete = false,
}) => ({
    items: [],
    folders: [],
    types: [],
    // The families the pool holds, which is what the tabs are drawn from. A tab over an
    // empty family is a door onto a wall, so a library of nothing but pictures shows no
    // tabs at all.
    kinds: [],
    // The tab the dialog opened on, which is which button was pressed.
    kind: initialKind,
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
    // Whether the panel's embed has been asked to play. Off again whenever the selection
    // moves: a player left running in a hidden element is a video you can hear and cannot
    // stop.
    playing: false,
    // What the panel's one field holds. Kept beside `details` rather than read out of it,
    // because it is being typed into: binding an input straight at the fetched row would
    // have every keystroke fight whatever the last request answered.
    description: '',
    descriptionSaving: false,
    descriptionSaved: false,
    list: listView,
    // What the server pages by. Guessing it from how many tiles came back read a
    // short last page as a tiny page size, and the footer then divided the whole
    // library by it - inventing pages that led to an empty grid.
    perPage: pageSize,
    // Set while a tab change is clearing the mime filter, so the filter's own watcher knows
    // the reload is already on its way.
    _clearedByKind: false,
    picked,
    labels,
    hasFolders,
    canDelete,

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
            } catch {
                // Private browsing, or a full quota. Not remembering is survivable.
            }
        })

        this.load()

        this.watchUploads()

        this.watchAdded()

        // Debounced by hand rather than with `x-model.debounce`, because a folder or a
        // filter change has to reload at once while typing must not fire a request per
        // keystroke.
        this.$watch('search', () => {
            clearTimeout(this._timer)
            this._timer = setTimeout(() => this.reload(), 300)
        })

        this.$watch('type', () => {
            // Swallowed once when a tab change cleared it, or the two watchers would send
            // two overlapping requests and the later answer would win by luck.
            if (this._clearedByKind) {
                this._clearedByKind = false

                return
            }

            this.reload()
        })

        this.$watch('sort', () => this.reload())

        // A tab narrows the pool, so the mime filter under it has to let go of a value that
        // is no longer in the pool - otherwise switching from Pictures to Video leaves
        // `image/png` selected and the grid is empty for a reason nothing on screen explains.
        this.$watch('kind', () => {
            if (this.type !== '') {
                this._clearedByKind = true
                this.type = ''
            }

            this.reload()
        })

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
        return this.type !== '' || this.kind !== '' || this.sort !== 'newest'
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

    /**
     * Which field this is. A picture is described by an alt text - what a screen reader
     * reads instead of it - and a film or a sound by a title, which is what a screen reader
     * reads instead of the file name. One input, one label, decided by what is selected.
     */
    get descriptionKey() {
        return (this.selected?.kind ?? 'image') === 'image' ? 'alt' : 'title'
    },

    get descriptionLabel() {
        return this.descriptionKey === 'alt' ? this.labels.alt : this.labels.title
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
                kind: this.kind || null,
            })

            this.items = result?.items ?? []
            this.hasMore = result?.hasMore ?? false
            this.total = result?.total ?? this.items.length
            this.types = result?.types ?? []

            // Taken as answered, tab or no tab. Both sources read the families off the pool
            // BEFORE the tab narrows it, so the list does not collapse to the tab you are
            // standing on - and a guard here that kept the previous list instead never
            // populated it at all when the dialog opened on a tab, which left the row
            // hidden and no way back to All.
            this.kinds = result?.kinds ?? []
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
            this.playing = false

            return
        }

        if (this.detailsFor === id) {
            return
        }

        this.details = this.items.find((item) => item.id === id) ?? null
        this.detailsFor = id
        this.playing = false
        this.description = this.details?.[this.descriptionKey] ?? ''

        try {
            const result = await fetchDetails(id)

            if (result && this.detailsFor === id) {
                this.details = result
                this.description = this.details?.[this.descriptionKey] ?? ''
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

    /**
     * Something was added that is not an upload - an embed, or an address.
     *
     * Both are written by a dialog on top of this one, so there is no `processfile` event
     * to hang off: the dialog says so itself when it closes.
     *
     * On `window`, and that is not a shortcut. Livewire dispatches a component event as a
     * `CustomEvent` on the window; a listener on this component's own element never hears
     * it, because events go up from where they are fired and this element is below. Bound
     * here so `destroy()` can take it off again - the dialog is built fresh every time it
     * opens, and a listener left behind is one more reload per opening.
     */
    watchAdded() {
        this._onAdded = (event) => {
            const id = event.detail?.id ?? null

            this.revealUploads().then(() => {
                if (id) {
                    this.picked = id
                }
            })
        }

        window.addEventListener('arte-media-added', this._onAdded)
    },

    destroy() {
        if (this._onAdded) {
            window.removeEventListener('arte-media-added', this._onAdded)
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

    /**
     * Saves the description as the field is left.
     *
     * On blur rather than on a button, because a button beside one input is a button
     * somebody has to notice - and the value is a single line that is finished the moment
     * focus moves. Unchanged values are not sent: the field is left every time anything else
     * in the dialog is clicked.
     */
    async saveDescription() {
        const id = this.picked

        if (!id) {
            return
        }

        const key = this.descriptionKey
        const previous = this.details?.[key] ?? ''
        const value = this.description.trim()

        if (value === previous) {
            return
        }

        this.descriptionSaving = true

        try {
            const saved = await saveMetadata(id, { [key]: value })

            if (!saved) {
                // Refused - a read-only disk, a row that is gone, a value the server would
                // not take. Showing the value it did take is the only honest thing left.
                this.description = previous

                return
            }

            // Written into the details as well, so leaving the file and coming back shows
            // what was saved rather than what the last fetch happened to carry.
            if (this.details && this.detailsFor === id) {
                this.details = { ...this.details, [key]: value }
            }

            this.descriptionSaved = true
            setTimeout(() => (this.descriptionSaved = false), 2000)
        } catch (error) {
            console.error('The advanced rich editor could not save that description:', error)

            this.description = previous
        } finally {
            this.descriptionSaving = false
        }
    },

    /**
     * Throws the selected file away.
     *
     * The browser's own `confirm()` rather than a Filament dialog: a second modal on top of
     * a modal that is itself on top of the editor is three layers deep, and what is being
     * asked is one sentence.
     */
    async remove() {
        const id = this.picked

        if (!id || !this.canDelete) {
            return
        }

        if (!window.confirm(this.labels.confirmDelete)) {
            return
        }

        try {
            if (!(await deleteMedia(id))) {
                return
            }

            this.picked = null
            this.details = null
            this.detailsFor = null

            await this.reload()
        } catch (error) {
            console.error('The advanced rich editor could not delete that file:', error)
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

    /** The badge on a tile: `PNG`, `MP4`, `MPEG` - or which service an embed is from. */
    format(item) {
        if ((item?.kind ?? '') === 'embed') {
            return (item?.embed?.provider ?? 'embed').toUpperCase().slice(0, 7)
        }

        return (item?.mime ?? '').split('/')[1]?.toUpperCase().slice(0, 4) || 'FILE'
    },

    /**
     * The picture a tile draws, or null where it has none and needs a sign instead.
     *
     * A film and a sound have a cover once one has been made for them, and that cover is
     * the whole point of making it - so the tile draws whatever `thumbnail` it was given,
     * whatever family the row is. Only a picture falls back to its own address: doing that
     * for a film would put an mp4 in an `<img>`, which is the broken-image icon this is
     * here to avoid.
     */
    thumbnailOf(item) {
        if (item?.thumbnail) {
            return item.thumbnail
        }

        return (item?.kind ?? 'image') === 'image' ? (item?.url ?? null) : null
    },

    /** Whether this row is a video somebody else hosts rather than a file of ours. */
    isEmbed(item) {
        return (item?.kind ?? '') === 'embed'
    },

    /** Which service an embed comes from, in the reader's own language. */
    providerOf(item) {
        const provider = item?.embed?.provider ?? ''

        return this.labels.providers?.[provider] ?? provider ?? '—'
    },

    /** Whether a tile has a picture to draw, or needs a sign standing in for one. */
    drawable(item) {
        return Boolean(this.thumbnailOf(item))
    },

    /**
     * Whether the panel should draw this in an `<img>`.
     *
     * Not the same question as `drawable()`, and the difference matters: the panel draws a
     * film in a `<video>` so it can be played, and a film with a cover would otherwise get
     * both - the player and an `<img>` pointing at the mp4 beside it.
     */
    isPicture(item) {
        return (item?.kind ?? 'image') === 'image' && Boolean(item?.thumbnail ?? item?.url)
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
