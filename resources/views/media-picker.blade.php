{{--
    The media browser.

    Two columns, because choosing a picture is two questions: which one, and is this the right
    one. The left column answers the first by showing many at once; the right answers the second
    about the one that is selected, with the numbers that tell two similar photographs apart.

    Nothing about the library is rendered here. The markup is an empty shell and one Alpine
    object; every picture in it arrives from `getMediaLibraryPageForJs()` on the editor, which
    reads the pool from the field on each call. That is deliberate: the pool is also what
    authorises a stored id, so it must never be something the browser was handed once and can
    answer with later.

    The controls are Filament's own components rather than lookalikes, so a panel with its own
    theme, its own dark mode or its own input styling gets a dialog that belongs to it.
--}}

@php
    $statePath = $getStatePath();
    $editorKey = $getEditorKey();
    $labels = $getLabels();
    $hasFolders = $hasFolders();
    $isListView = $isListView();
    $pageSize = $getPageSize();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
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
            list: @js($isListView),
            // What the server pages by. Guessing it from how many tiles came back read a
            // short last page as a tiny page size, and the footer then divided the whole
            // library by it - inventing pages that led to an empty grid.
            perPage: @js($pageSize),
            picked: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            labels: @js($labels),
            hasFolders: @js($hasFolders),

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
                    const result = await $wire.callSchemaComponentMethod(
                        @js($editorKey),
                        'getMediaLibraryPageForJs',
                        {
                            search: this.search,
                            folder: this.folder,
                            page: this.page,
                            type: this.type || null,
                            sort: this.sort,
                        },
                    )

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
                    const result = await $wire.callSchemaComponentMethod(
                        @js($editorKey),
                        'getMediaDetailsForJs',
                        { id },
                    )

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
        }"
        class="fi-arte-media"
    >
        <div class="fi-arte-media-header">
            <x-filament::button
                icon="heroicon-m-arrow-up-tray"
                x-on:click="upload()"
            >
                <span x-text="labels.upload"></span>
            </x-filament::button>

            <div class="fi-arte-media-header-end">
                <x-filament::input.wrapper class="fi-arte-media-search">
                    <x-filament::input
                        type="search"
                        x-model="search"
                        x-bind:placeholder="labels.search"
                        x-bind:aria-label="labels.search"
                    />
                </x-filament::input.wrapper>

                {{--
                    A dropdown rather than a row of controls that is always on screen: the
                    filter is used rarely and takes as much width as the search it would sit
                    beside. This is the shape Filament's own tables use for the same job.
                --}}
                <x-filament::dropdown placement="bottom-end" width="xs">
                    <x-slot name="trigger">
                        <x-filament::icon-button
                            icon="heroicon-m-funnel"
                            x-bind:color="isFiltered ? 'primary' : 'gray'"
                            color="gray"
                            x-bind:label="labels.filter"
                            x-bind:title="labels.filter"
                        />
                    </x-slot>

                    <div class="fi-arte-media-filters">
                        <label class="fi-arte-media-filter">
                            <span x-text="labels.filter"></span>

                            <x-filament::input.wrapper>
                                <x-filament::input.select x-model="type">
                                    <option value="" x-text="labels.allTypes"></option>

                                    <template x-for="entry in types" :key="entry">
                                        <option :value="entry" x-text="entry"></option>
                                    </template>
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </label>

                        <label class="fi-arte-media-filter">
                            <span x-text="labels.sort"></span>

                            <x-filament::input.wrapper>
                                <x-filament::input.select x-model="sort">
                                    <template x-for="[value, label] in Object.entries(labels.sorts)" :key="value">
                                        <option :value="value" x-text="label"></option>
                                    </template>
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </label>
                    </div>
                </x-filament::dropdown>

                {{--
                    One control with two halves rather than two buttons that happen to sit
                    together: they are the two settings of a single switch, and a segment that
                    shares its border with its neighbour says so.
                --}}
                <div class="fi-arte-media-views" role="group">
                    <button
                        type="button"
                        x-on:click="list = false"
                        x-bind:aria-pressed="! list"
                        x-bind:class="{ 'fi-arte-media-view-active': ! list }"
                        x-bind:title="labels.grid"
                        x-bind:aria-label="labels.grid"
                        class="fi-arte-media-view"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M3 4.75A1.75 1.75 0 0 1 4.75 3h2.5A1.75 1.75 0 0 1 9 4.75v2.5A1.75 1.75 0 0 1 7.25 9h-2.5A1.75 1.75 0 0 1 3 7.25v-2.5ZM11 4.75A1.75 1.75 0 0 1 12.75 3h2.5A1.75 1.75 0 0 1 17 4.75v2.5A1.75 1.75 0 0 1 15.25 9h-2.5A1.75 1.75 0 0 1 11 7.25v-2.5ZM3 12.75A1.75 1.75 0 0 1 4.75 11h2.5A1.75 1.75 0 0 1 9 12.75v2.5A1.75 1.75 0 0 1 7.25 17h-2.5A1.75 1.75 0 0 1 3 15.25v-2.5ZM11 12.75A1.75 1.75 0 0 1 12.75 11h2.5A1.75 1.75 0 0 1 17 12.75v2.5A1.75 1.75 0 0 1 15.25 17h-2.5A1.75 1.75 0 0 1 11 15.25v-2.5Z" />
                        </svg>
                    </button>

                    <button
                        type="button"
                        x-on:click="list = true"
                        x-bind:aria-pressed="list"
                        x-bind:class="{ 'fi-arte-media-view-active': list }"
                        x-bind:title="labels.list"
                        x-bind:aria-label="labels.list"
                        class="fi-arte-media-view"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M3 5.75A.75.75 0 0 1 3.75 5h12.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 5.75ZM3 10a.75.75 0 0 1 .75-.75h12.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 10Zm0 4.25a.75.75 0 0 1 .75-.75h12.5a.75.75 0 0 1 0 1.5H3.75a.75.75 0 0 1-.75-.75Z" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        {{--
            The library is the dropzone. A separate one under it would be a second place to
            look, and it would sit exactly where the pictures somebody is comparing want to be.
        --}}
        <div
            x-on:dragover="onDragOver($event)"
            x-on:dragleave="onDragLeave($event)"
            x-on:drop="onDrop($event)"
            x-bind:class="{ 'fi-arte-media-dropping': dropping }"
            class="fi-arte-media-body"
        >
            <div class="fi-arte-media-main">
                <nav
                    x-show="hasFolders && (parent !== null || folders.length > 0)"
                    x-cloak
                    class="fi-arte-media-folders"
                >
                    <button
                        type="button"
                        x-show="parent !== null"
                        x-on:click="open(parent)"
                        x-text="labels.up"
                        class="fi-arte-media-folder fi-arte-media-folder-up"
                    ></button>

                    <template x-for="entry in folders" :key="entry.path">
                        <button
                            type="button"
                            x-on:click="open(entry.path)"
                            x-text="entry.name"
                            class="fi-arte-media-folder"
                        ></button>
                    </template>
                </nav>

                {{--
                    Loading is shown by dimming what is already there, never by adding a line
                    underneath. A message that appears and disappears changes the height of the
                    dialog, and the pictures somebody is aiming at move out from under the
                    pointer every time a filter is touched.
                --}}
                <div
                    x-bind:class="[
                        list ? 'fi-arte-media-rows' : 'fi-arte-media-grid',
                        loading ? 'fi-arte-media-busy' : '',
                    ]"
                    role="listbox"
                    x-bind:aria-busy="loading"
                >
                    <template x-for="item in items" :key="item.id">
                        <button
                            type="button"
                            role="option"
                            x-on:click="pick(item)"
                            x-bind:aria-selected="picked === item.id"
                            x-bind:class="{
                                'fi-arte-media-item-picked': picked === item.id,
                                'fi-arte-media-item-pending': item.pending,
                            }"
                            x-bind:title="item.name"
                            class="fi-arte-media-item"
                        >
                            <span x-show="list" x-text="kind(item)" class="fi-arte-media-kind"></span>

                            {{--
                                Lazy, because a library is a long list and a dialog that opens
                                by requesting two hundred pictures is a dialog that opens
                                slowly.
                            --}}
                            <img
                                x-bind:src="item.thumbnail ?? item.url"
                                x-bind:alt="item.name"
                                loading="lazy"
                                decoding="async"
                                class="fi-arte-media-item-image"
                            />

                            <span class="fi-arte-media-item-text">
                                <span x-text="item.name" class="fi-arte-media-item-label"></span>

                                <span
                                    x-show="list"
                                    x-text="meta(item)"
                                    class="fi-arte-media-item-meta"
                                ></span>
                            </span>

                            {{--
                                Said out loud rather than left to be discovered: this picture is
                                not in the library yet, and navigating away without saving loses
                                it.
                            --}}
                            <span
                                x-show="item.pending"
                                x-text="labels.pending"
                                class="fi-arte-media-item-pending-note"
                            ></span>
                        </button>
                    </template>
                </div>

                <p
                    x-show="isEmpty"
                    x-cloak
                    x-text="search ? labels.emptySearch : labels.empty"
                    class="fi-arte-media-note"
                ></p>

                <div class="fi-arte-media-footer">
                    <span
                        class="fi-arte-media-count"
                        x-text="`${total} ${labels.items}`"
                    ></span>

                    <div x-show="pages > 1" x-cloak class="fi-arte-media-pages">
                        <x-filament::icon-button
                            icon="heroicon-m-chevron-left"
                            color="gray"
                            size="sm"
                            x-on:click="go(page - 1)"
                            x-bind:disabled="page <= 1"
                            x-bind:aria-label="labels.previous"
                        x-bind:title="labels.previous"
                        />

                        <span class="fi-arte-media-page" x-text="`${page} / ${pages}`"></span>

                        <x-filament::icon-button
                            icon="heroicon-m-chevron-right"
                            color="gray"
                            size="sm"
                            x-on:click="go(page + 1)"
                            x-bind:disabled="page >= pages"
                            x-bind:aria-label="labels.next"
                        x-bind:title="labels.next"
                        />
                    </div>
                </div>
            </div>

            <aside class="fi-arte-media-details">
                <template x-if="selected">
                    <div class="fi-arte-media-details-inner">
                        <img
                            x-bind:src="selected.url"
                            x-bind:alt="selected.name"
                            decoding="async"
                            class="fi-arte-media-preview"
                        />

                        <dl class="fi-arte-media-facts">
                            <div>
                                <dt x-text="labels.name"></dt>
                                <dd x-text="selected.name"></dd>
                            </div>
                            <div>
                                <dt x-text="labels.size"></dt>
                                <dd x-text="bytes(selected.size)"></dd>
                            </div>
                            <div>
                                <dt x-text="labels.dimensions"></dt>
                                <dd x-text="pixels(selected) ?? '—'"></dd>
                            </div>
                            <div>
                                <dt x-text="labels.type"></dt>
                                <dd x-text="selected.mime || '—'"></dd>
                            </div>
                            <div>
                                <dt x-text="labels.modified"></dt>
                                <dd x-text="when(selected.modifiedAt ?? selected.createdAt)"></dd>
                            </div>
                        </dl>

                        <x-filament::button
                            color="gray"
                            size="sm"
                            icon="heroicon-m-link"
                            x-on:click="copy()"
                        >
                            <span x-text="copied ? labels.copied : labels.copy"></span>
                        </x-filament::button>
                    </div>
                </template>

                <p
                    x-show="! selected"
                    x-cloak
                    x-text="labels.nothingSelected"
                    class="fi-arte-media-note"
                ></p>
            </aside>

            <p x-show="dropping" x-cloak x-text="labels.drop" class="fi-arte-media-drop-note"></p>
        </div>
    </div>
</x-dynamic-component>
