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
    replaceMedia,
    // Which entries use a file, asked before deleting or replacing it. A default that knows
    // of none, so a view built before this existed still asks its plain question.
    fetchUsage = async () => ({ count: 0, entries: [] }),
    canDelete = false,
    canReplace = false,
    shared = false,
    // The dialogs' ids are drawn by the view, which is what keeps two pickers on one page from
    // opening each other's. One id per kind of question: they differ in colour.
    confirmId = 'arte-media-confirm',
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
    // The files that were turned away, by name, until somebody dismisses the note. A file
    // that simply never turned up in the grid reads as the dialog having lost it.
    rejected: [],
    // A replacement on its way, and what went wrong with the last one. The note stays until
    // the selection moves, since it is about the file that was selected.
    replacing: false,
    // The server is deleting the selected file, and the entries it was in.
    deleting: false,
    replaceError: null,
    // Which file the picker was opened for. Held rather than read off the selection when the
    // upload lands: somebody may have clicked another tile while it travelled.
    _replaceTarget: null,
    _replaceAccept: null,
    // When each file was replaced in this dialog, for the addresses that did not change.
    replacedAt: {},
    // What a delete or a replacement did beyond the library - how many entries it reached -
    // until somebody dismisses it. After a delete nothing is selected, so the panel is not
    // where this can be said.
    notice: null,
    // What the confirmation dialog says. Kept after it closes: it fades out over a moment, and
    // words that vanish first leave an empty box on screen.
    question: { kind: 'delete', heading: '', description: '', confirm: '' },
    // Whoever is waiting for the dialog to be answered.
    _answer: null,
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
    canReplace,
    shared,

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

        this.watchReplacements()

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

    /**
     * What the panel offers for what is selected, in the order the grid draws it.
     *
     * An embed is a link, so there is no file to download or to put something in the place
     * of. An upload that is not saved yet is not in the library at all: its address is a
     * temporary one nobody should be handed, and there is nothing yet to replace or delete.
     * Replace needs the server to have named what may take the file's place - but that answer
     * comes with the details, a moment after the click, and the row the panel shows until
     * then has none. Where nothing has been said yet the button is drawn anyway, so the grid
     * has the shape it will keep instead of growing a cell while the pointer is over it; it
     * is `replaceReady` that says whether it can be pressed. Only a `null` or an empty answer
     * takes it away.
     */
    get actions() {
        const selected = this.selected

        if (!selected) {
            return []
        }

        const pending = Boolean(selected.pending)
        const said = selected.replace !== undefined

        return [
            !pending && 'copy',
            !this.isEmbed(selected) && 'download',
            !pending && !this.isEmbed(selected) && this.canReplace && (!said || (selected.replace?.length ?? 0) > 0) && 'replace',
            !pending && this.canDelete && 'delete',
        ].filter(Boolean)
    },

    /**
     * Whether the server has said what may take the selected file's place, and it is
     * something. Until then Replace is drawn and cannot be pressed.
     */
    get replaceReady() {
        const accept = this.selected?.replace

        return Array.isArray(accept) && accept.length > 0
    },

    has(action) {
        return this.actions.includes(action)
    },

    /**
     * Whether an action spans both columns: the last of an odd number, so the grid ends in a
     * full row rather than a hole beside the last button.
     */
    wide(action) {
        const actions = this.actions

        return actions.length % 2 === 1 && actions[actions.length - 1] === action
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

    /**
     * Whether the panel offers the field at all. A document is a card, and a card shows the
     * file's name - a title typed here would be saved and then read by nothing, which is
     * worse than no field, because it looks like it worked.
     */
    get describable() {
        return (this.selected?.kind ?? 'image') !== 'file'
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

            // Added to rather than replaced: the server says it once, when it lets go of the
            // file, and the next page must not take the note away before anybody read it.
            for (const name of result?.rejected ?? []) {
                this.reject(name)
            }
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
            this.replaceError = null

            return
        }

        if (this.detailsFor === id) {
            return
        }

        this.replaceError = null
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
        return this.pondIn('.fi-arte-media-uploader')
    },

    /**
     * The second upload field, kept for replacements. Apart from the first because the first
     * holds what Submit inserts, and a new price list is not something to insert beside the
     * old one.
     */
    get replacer() {
        return this.pondIn('.fi-arte-media-replacer')
    },

    pondIn(selector) {
        const scope = this.$root.closest('.fi-modal') ?? document

        const element = scope.querySelector(selector)

        return element ? (window.Alpine.$data(element)?.pond ?? null) : null
    },

    /**
     * Runs something once the upload field exists.
     *
     * It is lazily loaded, so for the first moments after the dialog opens it is not
     * there - and that is exactly when somebody drops the picture they opened the
     * dialog for. Waiting is what stops that drop from quietly doing nothing.
     */
    whenPond(callback, attempt = 0, which = 'pond') {
        const pond = this[which]

        if (pond) {
            callback(pond)

            return
        }

        if (attempt < 60) {
            setTimeout(() => this.whenPond(callback, attempt + 1, which), 100)
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

        // A question nobody can answer any more is answered no, or the flow waiting on it
        // stays suspended in a picker that no longer exists.
        this.settle(false)
    },

    watchUploads() {
        this.whenPond((pond) => {
            // An upload does not stay in this dialog - it is handed to the editor as it
            // arrives, which is what makes it survive the dialog closing. So there is
            // nothing to mirror here: the grid simply asks again, and the new picture
            // is in the answer, described by the server like every other one.
            pond.on('processfile', (error, file) => {
                // Refused on the way, by size or by the server. There is nothing to reveal,
                // and saying which file it was is the whole of what can be done about it.
                if (error) {
                    this.refuse(pond, file)

                    return
                }

                return this.revealUploads().then(() => this.selectNewest())
            })

            // Turned away before it travelled, by what the browser says the file is. The
            // widget draws its own complaint, but the widget is kept off screen.
            pond.on('addfile', (error, file) => {
                if (error) {
                    this.refuse(pond, file)
                }
            })
        })
    },

    /**
     * Names a refused file, and takes it out of the widget.
     *
     * Kept there, it marks the widget's own input invalid - and a form holding an invalid
     * input refuses to submit without a word, since the input is off screen. Everything
     * else in the dialog would then look fine and do nothing.
     */
    refuse(pond, file) {
        this.reject(file?.filename)

        if (file?.id) {
            pond.removeFile(file.id)
        }
    },

    reject(name) {
        if (name && ! this.rejected.includes(name)) {
            this.rejected = [...this.rejected, name]
        }
    },

    dismissRejected() {
        this.rejected = []
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
        //
        // A drop holding one refused file rejects the widget's whole promise. The refusal is
        // told through `addfile` already, so the promise has nothing to add, and left alone
        // it is an unhandled error in the console on every refused drop.
        this.whenPond((pond) => pond.addFiles(files)?.catch?.(() => {}))
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
     * Opens the file picker for a file to put in the selected one's place.
     *
     * Narrowed first to what may take the place - the server's answer for this file, which is
     * the same ending on a disk and the same family in a media library. Set on the input
     * rather than through the widget's own option, which would also check types in the
     * browser, and a browser's idea of a document's type is not the server's.
     */
    replace() {
        const id = this.picked
        const accept = this.selected?.replace

        if (!id || !this.canReplace || !Array.isArray(accept) || accept.length === 0 || this.replacing) {
            return
        }

        this.replaceError = null

        this.whenPond(
            (pond) => {
                this._replaceTarget = id
                this._replaceAccept = accept

                pond.element?.querySelector('input[type="file"]')?.setAttribute('accept', accept.join(','))
                pond.browse()
            },
            0,
            'replacer',
        )
    },

    watchReplacements() {
        this.whenPond(
            (pond) => {
                pond.on('addfile', (error, file) => {
                    if (!this._replaceTarget) {
                        return
                    }

                    // Turned away before it travelled - too big, most likely. The widget's
                    // own complaint is off screen with the widget.
                    if (error) {
                        this.replaceError = this.fill(this.labels.replaceRefused, {
                            name: file?.filename ?? '',
                            accept: (this._replaceAccept ?? []).join(', '),
                        })
                        this._replaceTarget = null

                        if (file?.id) {
                            pond.removeFile(file.id)
                        }

                        return
                    }

                    this.replacing = true
                })

                pond.on('processfile', (error, file) => this.finishReplacing(pond, error, file))
            },
            0,
            'replacer',
        )
    },

    /**
     * The upload has arrived: ask, then hand it over.
     *
     * Asked only now, when both names are known - and a no lets go of the upload on the
     * server too, or it would be the file the next Replace found first. A yes leaves the
     * letting go to the server, which takes the upload out of the dialog whatever it answers.
     */
    async finishReplacing(pond, error, file) {
        const id = this._replaceTarget

        if (!id) {
            return
        }

        this._replaceTarget = null

        if (error) {
            this.replacing = false
            this.replaceError = this.labels.replaceFailed

            if (file?.id) {
                pond.removeFile(file.id)
            }

            return
        }

        const to = file?.filename ?? ''
        const usage = await this.usageOf(id)
        const names = { from: this.nameOf(id), to }

        const confirmed = await this.ask({
            kind: 'replace',
            heading: this.fill(this.labels.replaceHeading, names),
            description:
                usage.count > 0
                    ? this.fill(usage.count === 1 ? this.labels.confirmReplaceUsedOne : this.labels.confirmReplaceUsed, {
                          ...names,
                          count: usage.count,
                          entries: this.listOf(usage),
                      })
                    : this.fill(this.labels.confirmReplace, names),
            confirm: this.labels.replace,
        })

        if (!confirmed) {
            this.replacing = false
            pond.removeFile(file?.id, { revert: true })

            return
        }

        this.replacing = true

        try {
            const result = await replaceMedia(id)

            if (!result?.replaced) {
                this.replaceError = Array.isArray(result?.accept)
                    ? this.fill(this.labels.replaceRefused, { name: to, accept: result.accept.join(', ') })
                    : this.labels.replaceFailed

                return
            }

            this.replacedAt = { ...this.replacedAt, [id]: Date.now() }

            if (result.item && this.detailsFor === id) {
                this.details = result.item
            }

            // Every open editor pointing at the file follows - see `media-replace.js`.
            window.dispatchEvent(
                new CustomEvent('arte-media-replaced', {
                    detail: {
                        id,
                        src: result.item?.url ?? null,
                        name: result.card?.name ?? null,
                        size: result.card?.size ?? null,
                        width: result.item?.width ?? null,
                        height: result.item?.height ?? null,
                    },
                }),
            )

            this.notice = this.reached(result.documents, this.labels.replacedIn, this.labels.replacedInOne)

            await this.load()
        } catch (failure) {
            console.error('The advanced rich editor could not replace that file:', failure)

            this.replaceError = this.labels.replaceFailed
        } finally {
            this.replacing = false

            // The server has let go of it already; this empties the widget itself.
            pond.removeFiles()
        }
    },

    /**
     * An address that shows the new file after a replacement kept it the same, as a disk
     * does - the browser would otherwise draw the old picture out of its cache. Only where
     * there is no query already: a signed address is new every time, and adding to it would
     * break its signature.
     */
    fresh(id, url) {
        const stamp = this.replacedAt[id]

        if (!stamp || typeof url !== 'string' || url.includes('?')) {
            return url
        }

        return `${url}?v=${stamp}`
    },

    /**
     * Which entries use a file, or none where the answer cannot be had - the question is then
     * asked without it rather than not at all.
     */
    async usageOf(id) {
        try {
            const usage = await fetchUsage(id)

            return {
                count: Number(usage?.count ?? 0) || 0,
                entries: Array.isArray(usage?.entries) ? usage.entries : Object.values(usage?.entries ?? {}),
            }
        } catch (error) {
            console.error('The advanced rich editor could not find where that file is used:', error)

            return { count: 0, entries: [] }
        }
    },

    /** The entries a question names, and how many more there are. */
    listOf(usage) {
        const more = usage.count - usage.entries.length

        return [...usage.entries, ...(more > 0 ? [this.fill(this.labels.usageMore, { count: more })] : [])].join(', ')
    },

    /** What a delete or a replacement reached, or nothing where it reached no entry. */
    reached(count, many, one) {
        const documents = Number(count ?? 0) || 0

        if (documents <= 0) {
            return null
        }

        return this.fill(documents === 1 ? one : many, { count: documents })
    },

    dismissNotice() {
        this.notice = null
    },

    nameOf(id) {
        const described = this.detailsFor === id ? this.details : null

        // Trimmed: a name can carry a space at its end, which the dialog then shows inside
        // its quotes.
        return String(described?.name ?? this.items.find((item) => item.id === id)?.name ?? '').trim()
    },

    /** A label with its `:placeholders` filled in, the way Laravel's own would have been. */
    fill(template, values) {
        return String(template ?? '').replace(/:([a-z]+)/g, (match, key) => values[key] ?? match)
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
     * It is asked in Filament's own dialog, and what the dialog says depends on where the
     * file is: the entries it is taken out of, by name, where the server knows of any -
     * otherwise said differently where it may be in documents nobody here can see.
     */
    async remove() {
        const id = this.picked

        if (!id || !this.canDelete || this.deleting) {
            return
        }

        const usage = await this.usageOf(id)

        const confirmed = await this.ask({
            kind: 'delete',
            heading: this.fill(this.labels.deleteHeading, { name: this.nameOf(id) }),
            description:
                usage.count > 0
                    ? this.fill(usage.count === 1 ? this.labels.confirmDeleteUsedOne : this.labels.confirmDeleteUsed, {
                          count: usage.count,
                          entries: this.listOf(usage),
                      })
                    : this.shared
                      ? (this.labels.confirmDeleteShared ?? this.labels.confirmDelete)
                      : this.labels.confirmDelete,
            confirm: this.labels.delete,
        })

        if (!confirmed) {
            return
        }

        this.deleting = true

        try {
            const result = await deleteMedia(id)

            // `true` from a server built before the answer said how many entries it reached.
            if (!(result === true || result?.deleted === true)) {
                return
            }

            this.picked = null
            this.details = null
            this.detailsFor = null

            // Every open editor pointing at the file lets go of it - see `media-replace.js`.
            window.dispatchEvent(new CustomEvent('arte-media-deleted', { detail: { id } }))

            this.notice = this.reached(result?.documents, this.labels.deletedFrom, this.labels.deletedFromOne)

            await this.reload()
        } catch (error) {
            console.error('The advanced rich editor could not delete that file:', error)
        } finally {
            this.deleting = false
        }
    },

    /**
     * Asks in Filament's own confirmation dialog and answers with what was pressed.
     *
     * A promise, so a flow reads as it did with the browser's `confirm()`: ask, then go on or
     * not. The dialog itself is the modal component, drawn by the view and opened by the event
     * every Filament modal opens on - which is what makes it look and behave like the
     * confirmations everywhere else in the panel, Escape and a click beside it included.
     *
     * @param {{kind: 'delete'|'replace', heading: string, description: string, confirm: string}} question
     * @returns {Promise<boolean>}
     */
    ask(question) {
        return new Promise((resolve) => {
            // Two dialogs at once would leave the first waiting for ever.
            this.settle(false)

            this.question = question
            this._answer = resolve

            this.$dispatch('open-modal', { id: this.dialogId })
        })
    },

    /** The id of the dialog the last question was asked in. */
    get dialogId() {
        return `${confirmId}-${this.question.kind}`
    },

    /**
     * Answers whoever is waiting, once. A dialog closes after a yes as well, and says so - and
     * that closing answers nothing, since it is already answered.
     */
    settle(answer) {
        const resolve = this._answer

        this._answer = null

        resolve?.(answer)
    },

    /** The dialog's confirm button. */
    yes() {
        const id = this.dialogId

        this.settle(true)

        this.$dispatch('close-modal', { id })
    },

    /**
     * The dialog's cancel button. It only closes the dialog: the answer is given by the closing,
     * which is the same one Escape and a click beside the dialog end in.
     */
    no() {
        this.$dispatch('close-modal', { id: this.dialogId })
    },

    /**
     * A modal closed - Filament says so on the window for every one of them, the browser's
     * own included, so it only counts if it was one of these. Cancel, Escape and a click
     * beside the dialog all come through here, and all of them mean no.
     */
    dismissed(id) {
        if (id === `${confirmId}-delete` || id === `${confirmId}-replace`) {
            this.settle(false)
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

    /**
     * The badge on a tile: `PNG`, `MP4`, `MPEG` - or which service an embed is from. A
     * document arrives with its own, the letters its card will wear: read off the mime, a
     * Word document was badged `VND.`.
     */
    format(item) {
        if (item?.badge) {
            return item.badge
        }

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
            return this.fresh(item.id, item.thumbnail)
        }

        return (item?.kind ?? 'image') === 'image' ? this.fresh(item?.id, item?.url ?? null) : null
    },

    /**
     * The colour a document's tile is drawn in: its card's, which the server sent with the
     * row. Everything else keeps the neutral sign the stylesheet draws.
     */
    tileStyle(item) {
        return (item?.kind ?? '') === 'file' && item?.tint
            ? { backgroundColor: item.tint, color: '#ffffff' }
            : {}
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
