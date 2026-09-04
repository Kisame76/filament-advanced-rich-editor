{{--
    The media browser.

    Two columns, because choosing a picture is two questions: which one, and is this the right
    one. The left column answers the first by showing many at once; the right answers the second
    about the one that is selected, with the numbers that tell two similar photographs apart.

    Nothing about the library is rendered here. The markup is an empty shell; every picture in
    it arrives from `getMediaLibraryPageForJs()` on the editor, which reads the pool from the
    field on each call. That is deliberate: the pool is also what authorises a stored id, so it
    must never be something the browser was handed once and can answer with later.

    The behaviour behind it lives in `resources/js/media-picker.js` rather than in an `x-data`
    attribute, so it can be run - and tested - without a Filament application rendering first.
    What crosses over from here is what only PHP knows: the labels, the two settings, the
    entangled selection, and the two calls that reach the editor this grid belongs to.

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
    $isDescribable = $isDescribable();
    $isRecordScoped = $isRecordScoped();
    $fromUrlAction = $getFromUrlAction();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-load
        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('media-picker', 'kisame76/filament-advanced-rich-editor') }}"
        x-data="arteMediaPicker({
            labels: @js($labels),
            hasFolders: @js($hasFolders),
            listView: @js($isListView),
            pageSize: @js($pageSize),
            kind: @js($getKind()),
            picked: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            fetchPage: (query) => $wire.callSchemaComponentMethod(
                @js($editorKey),
                'getMediaLibraryPageForJs',
                query,
            ),
            fetchDetails: (id) => $wire.callSchemaComponentMethod(
                @js($editorKey),
                'getMediaDetailsForJs',
                { id },
            ),
            saveMetadata: (id, data) => $wire.callSchemaComponentMethod(
                @js($editorKey),
                'saveMediaMetadataForJs',
                { id, data },
            ),
            deleteMedia: (id) => $wire.callSchemaComponentMethod(
                @js($editorKey),
                'deleteMediaForJs',
                { id },
            ),
            canDelete: @js($isRecordScoped),
        })"
        class="fi-arte-media"
    >
        <div class="fi-arte-media-header">
            {{--
                The two ways of putting something in, side by side. They used to be a row
                under the grid, which was wrong twice: it sat below the *tallest* column - the
                details panel - so there was a field of nothing under a short list, and
                "add something" is not a thing you look for at the bottom of what you are
                looking through.
            --}}
            <div class="fi-arte-media-add">
                <x-filament::button
                    icon="heroicon-m-arrow-up-tray"
                    x-on:click="upload()"
                >
                    <span x-text="labels.upload"></span>
                </x-filament::button>

                {{--
                    The object rather than `->toHtml()`: Blade renders anything `Htmlable`
                    as markup and escapes a plain string, so calling the method here put an
                    escaped `<button>` on screen as text.
                --}}
                @if ($fromUrlAction)
                    {{ $fromUrlAction }}
                @endif
            </div>

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
            The tabs, under the toolbar and over the grid rather than inside the filter
            dropdown beside it. They were in the dropdown first, which was wrong twice:
            a tab is where you are, not a setting you went looking for, and a person
            sent to the Video tab by the video button would have had to open a funnel
            menu to find out there was a way back.

            Drawn where the pool holds more than one family, so a library of nothing but
            pictures is the dialog it always was and does not grow a row of buttons that
            all say the same thing.

            And drawn whenever a tab is chosen, whatever the pool holds: the video
            button opens straight onto Video, and a library with no video in it would
            otherwise be an empty grid with no way back to All.
        --}}
        <div class="fi-arte-media-tabs" x-show="kinds.length > 1 || kind !== ''" role="tablist">
            <button
                type="button"
                role="tab"
                x-bind:aria-selected="kind === ''"
                x-bind:class="{ 'fi-active': kind === '' }"
                x-on:click="kind = ''"
                class="fi-arte-media-tab"
                x-text="labels.allKinds"
            ></button>

            <template x-for="entry in kinds" :key="entry">
                <button
                    type="button"
                    role="tab"
                    x-bind:aria-selected="kind === entry"
                    x-bind:class="{ 'fi-active': kind === entry }"
                    x-on:click="kind = entry"
                    class="fi-arte-media-tab"
                    x-text="labels.kinds[entry] ?? entry"
                ></button>
            </template>
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
                            <span x-show="list" x-text="format(item)" class="fi-arte-media-kind"></span>

                            {{--
                                Lazy, because a library is a long list and a dialog that opens
                                by requesting two hundred pictures is a dialog that opens
                                slowly.
                            --}}
                            <img
                                x-show="drawable(item)"
                                x-bind:src="thumbnailOf(item)"
                                x-bind:alt="item.name"
                                loading="lazy"
                                decoding="async"
                                class="fi-arte-media-item-image"
                            />

                            {{--
                                A video or a sound with no cover yet. Drawing one in an
                                `<img>` anyway is a broken-image icon in a grid, which reads
                                as a broken library rather than as a film.
                            --}}
                            <span
                                x-show="! drawable(item)"
                                x-bind:class="`fi-arte-media-item-sign fi-arte-media-item-sign-${item.kind ?? 'file'}`"
                                class="fi-arte-media-item-sign"
                                aria-hidden="true"
                            >
                                <span x-text="format(item)"></span>
                            </span>

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
                            x-show="isPicture(selected)"
                            x-bind:src="selected.url"
                            x-bind:alt="selected.name"
                            decoding="async"
                            class="fi-arte-media-preview"
                        />

                        {{--
                            The real element for a film and a sound, because the panel is
                            where somebody checks they picked the right one - and for a video
                            that means watching a second of it. `preload="metadata"` fetches
                            a few kilobytes; nothing streams until play is pressed.
                        --}}
                        {{--
                            Stopped as well as hidden. `x-show` only sets `display: none`, so
                            a film left playing would go on playing out of an invisible
                            element the moment the panel moved to another file - audible, and
                            with no control left on screen to stop it.
                        --}}
                        <video
                            x-show="(selected.kind ?? '') === 'video'"
                            x-effect="if ((selected?.kind ?? '') !== 'video') { $el.pause() }"
                            x-bind:src="(selected.kind ?? '') === 'video' ? selected.url : ''"
                            controls
                            preload="metadata"
                            class="fi-arte-media-preview"
                        ></video>

                        {{--
                            An embed, and the panel is where somebody checks they picked the
                            right video - so it plays, but only when asked. Until then it is
                            the still this package fetched and stored itself: drawing an
                            iframe straight away would call YouTube from every editor that
                            opens the dialog, which is the tracking the cookie-free host is
                            there to avoid.
                        --}}
                        <template x-if="(selected.kind ?? '') === 'embed' && ! playing">
                            <button
                                type="button"
                                x-on:click="playing = true"
                                x-bind:title="labels.play"
                                x-bind:aria-label="labels.play"
                                class="fi-arte-media-play"
                            >
                                <img
                                    x-show="selected.thumbnail"
                                    x-bind:src="selected.thumbnail"
                                    x-bind:alt="selected.name"
                                    decoding="async"
                                    class="fi-arte-media-preview"
                                />

                                <span class="fi-arte-media-play-mark" aria-hidden="true">
                                    <svg viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M6.3 2.8A1.5 1.5 0 0 0 4 4.1v11.8a1.5 1.5 0 0 0 2.3 1.3l9.3-5.9a1.5 1.5 0 0 0 0-2.6L6.3 2.8Z" />
                                    </svg>
                                </span>
                            </button>
                        </template>

                        <template x-if="(selected.kind ?? '') === 'embed' && playing">
                            <iframe
                                x-bind:src="selected.frame"
                                x-bind:title="selected.name"
                                allowfullscreen
                                loading="lazy"
                                referrerpolicy="strict-origin-when-cross-origin"
                                class="fi-arte-media-preview fi-arte-media-preview-embed"
                            ></iframe>
                        </template>

                        <audio
                            x-show="(selected.kind ?? '') === 'audio'"
                            x-effect="if ((selected?.kind ?? '') !== 'audio') { $el.pause() }"
                            x-bind:src="(selected.kind ?? '') === 'audio' ? selected.url : ''"
                            controls
                            preload="metadata"
                            class="fi-arte-media-preview fi-arte-media-preview-audio"
                        ></audio>

                        {{--
                            The description, beside the thing it describes. It used to sit
                            under the grid, where it read as part of the picker and was asked
                            again for every insert - which is how one picture ends up
                            described three different ways in three documents.

                            Saved as the field is left rather than on a button: it is one
                            line, and it is finished the moment focus moves.
                        --}}
                        @if ($isDescribable)
                            <label class="fi-arte-media-describe">
                                <span x-text="descriptionLabel"></span>

                                <x-filament::input.wrapper>
                                    <x-filament::input
                                        type="text"
                                        maxlength="1000"
                                        x-model="description"
                                        x-on:blur="saveDescription()"
                                        x-bind:aria-label="descriptionLabel"
                                    />
                                </x-filament::input.wrapper>

                                <span
                                    x-show="descriptionSaved"
                                    x-cloak
                                    x-text="labels.saved"
                                    class="fi-arte-media-describe-saved"
                                ></span>
                            </label>
                        @endif

                        <dl class="fi-arte-media-facts">
                            <div>
                                <dt x-text="labels.name"></dt>
                                <dd x-text="selected.name"></dd>
                            </div>
                            <div x-show="! isEmbed(selected)">
                                <dt x-text="labels.size"></dt>
                                <dd x-text="bytes(selected.size)"></dd>
                            </div>
                            <div x-show="! isEmbed(selected)">
                                <dt x-text="labels.dimensions"></dt>
                                <dd x-text="pixels(selected) ?? '—'"></dd>
                            </div>
                            <div>
                                <dt x-text="labels.type"></dt>
                                <dd x-text="isEmbed(selected) ? providerOf(selected) : (selected.mime || '—')"></dd>
                            </div>
                            <div>
                                <dt x-text="labels.modified"></dt>
                                <dd x-text="when(selected.modifiedAt ?? selected.createdAt)"></dd>
                            </div>
                        </dl>

                        <div class="fi-arte-media-actions">
                            <x-filament::button
                                color="gray"
                                size="sm"
                                icon="heroicon-m-link"
                                x-on:click="copy()"
                            >
                                <span x-text="copied ? labels.copied : labels.copy"></span>
                            </x-filament::button>

                            {{--
                                A plain anchor, because saving a file is what an anchor with
                                `download` does and nothing here can do it better. On a
                                private disk `url` is already the temporary signed address,
                                so this needs to know nothing about visibility.
                            --}}
                            <x-filament::button
                                tag="a"
                                x-show="! isEmbed(selected)"
                                x-cloak
                                color="gray"
                                size="sm"
                                icon="heroicon-m-arrow-down-tray"
                                x-bind:href="selected.url"
                                x-bind:download="selected.fileName ?? selected.name"
                            >
                                <span x-text="labels.download"></span>
                            </x-filament::button>

                            {{--
                                Only where the library is this record's own attachments. In a
                                shared library the file may be in another record's content
                                that nobody standing here can see, and a button that cannot
                                know that is a button that quietly breaks somebody's page.
                            --}}
                            <x-filament::button
                                x-show="canDelete"
                                x-cloak
                                color="danger"
                                size="sm"
                                icon="heroicon-m-trash"
                                x-on:click="remove()"
                            >
                                <span x-text="labels.delete"></span>
                            </x-filament::button>
                        </div>
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
