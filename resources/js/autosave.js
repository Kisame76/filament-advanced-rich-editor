/*
 * A draft in the browser, for the moment the server loses one.
 *
 * This is not a save. Nothing here reaches the application, nothing here is a record, and
 * a draft that is never restored is never anything - it exists for the ten seconds between
 * an editor full of work and a page that cannot submit it. Livewire is the reason it earns
 * its place: a form that has been open for an hour meets an expired session, a validation
 * error on a field nobody can see, or the 500 this package's own README explains how to
 * cause, and the reply to the submit is not the saved record but a page that has forgotten
 * the article.
 *
 * Three parts, and they are three because they fail at three different moments. The draft
 * is written while typing, so a tab that dies has one. The bar is offered on opening, so a
 * tab that died leaves something to say yes to. The warning is raised on leaving, so a tab
 * that is about to be closed on purpose says what is in it first.
 *
 * What is stored is the document as TipTap's own JSON, under a key the field works out for
 * itself: an identity from PHP, which knows the record and the field, and the path of the
 * page, which the browser knows and PHP does not - a form that is open twice, once to
 * create and once to edit, is two drafts and not one.
 *
 * The restoring is a plain ProseMirror transaction rather than a call into TipTap, and that
 * is on purpose: a transaction is what Filament's own update handler is listening for, so a
 * restored draft reaches the state the form submits. It is also one step in the undo chain,
 * which makes restoring something that can be taken back.
 *
 * Drafts are dropped as soon as they are stale - written into the same document that is now
 * on screen, or older than the age a project allows. That last one matters more than it
 * looks: this is content in a browser's storage on whatever machine somebody was working
 * on, and it outlives the session that made it. A day is long enough to recover a lost
 * article and short enough that a shared machine is not an archive.
 */

/** Everything this package writes lives under one prefix, so it can find its own again. */
export const PREFIX = 'arte-draft:'

/**
 * Whether the sweeping up has already happened on this page.
 *
 * It is the same walk over the same storage whichever field asks for it, so a form with
 * four editors on it would otherwise do it four times before any of them could be typed in.
 */
let swept = false

/**
 * The key one field's draft lives under.
 *
 * Two halves, because neither knows enough alone: PHP knows the record and the field but
 * not which page they are on - to Livewire every request looks like the same endpoint - and
 * the browser knows the page but nothing about what is in it.
 */
export function draftKey(identity, path) {
    return `${PREFIX}${path}::${identity}`
}

/**
 * A draft, or null when there is none worth having.
 *
 * Expiry is answered on the way out rather than by anything sweeping up: the only moment
 * that matters is the one where a draft is about to be offered.
 */
export function readDraft(storage, key, { now = 0, ttl = 0 } = {}) {
    let draft

    try {
        draft = JSON.parse(storage.getItem(key) ?? 'null')
    } catch (error) {
        draft = null
    }

    if (! draft?.content || typeof draft.savedAt !== 'number') {
        return null
    }

    if (ttl > 0 && now - draft.savedAt > ttl) {
        storage.removeItem(key)

        return null
    }

    return draft
}

/**
 * Every one of this package's drafts that has outlived its welcome, gone.
 *
 * Swept on opening rather than on a timer, because the tab that wrote a draft is usually
 * the tab that is never coming back - and nothing else would ever remove it.
 */
export function prune(storage, { now = 0, ttl = 0 } = {}) {
    if (ttl <= 0) {
        return []
    }

    // Every key first, and only then a word about any of them. `readDraft` removes what it
    // finds expired, and removing from a storage shifts every key after it down one - so a
    // walk that reads by index while that is happening steps over whatever moved into the
    // place just freed, and leaves every second expired draft exactly where it was.
    const ours = []

    for (let index = 0; index < storage.length; index++) {
        const key = storage.key(index)

        if (key?.startsWith(PREFIX)) {
            ours.push(key)
        }
    }

    const stale = []

    for (const key of ours) {
        // Anything unreadable counts as expired too - a draft this version cannot parse is
        // a draft from a version that is gone.
        if (! readDraft(storage, key, { now, ttl })) {
            stale.push(key)
            storage.removeItem(key)
        }
    }

    return stale
}

/**
 * Writing one, and making room for it if that is what it takes.
 *
 * A browser's storage is shared by the whole origin and a long article is not small, so a
 * write can fail on a quota somebody else filled. Rather than give up, this drops its own
 * older drafts and tries once more: a draft nobody has come back for in a day is worth less
 * than the one being written now.
 */
export function writeDraft(storage, key, content, { now = 0 } = {}) {
    const draft = JSON.stringify({ content, savedAt: now })

    try {
        storage.setItem(key, draft)

        return true
    } catch (error) {
        // Everything of ours except this field's own, oldest first, until it fits.
        const others = []

        for (let index = 0; index < storage.length; index++) {
            const other = storage.key(index)

            if (other?.startsWith(PREFIX) && other !== key) {
                others.push([other, readDraft(storage, other)?.savedAt ?? 0])
            }
        }

        others.sort((one, two) => one[1] - two[1])

        for (const [other] of others) {
            storage.removeItem(other)

            try {
                storage.setItem(key, draft)

                return true
            } catch (retry) {
                continue
            }
        }

        return false
    }
}

/**
 * Whether a draft is worth offering, given what the server just rendered.
 *
 * A draft that says the same as the document on screen is a draft of work that arrived
 * safely, and offering to restore it would be offering to do nothing - twice, since the
 * same question would come back on the next opening.
 */
export function shouldOffer(draft, current) {
    return Boolean(draft) && JSON.stringify(draft.content) !== JSON.stringify(current)
}

/**
 * What the field configured, off the element the editor was mounted on.
 */
function settingsOf(editor) {
    const raw = editor.options.element?.dataset?.arteAutosave

    if (! raw) {
        return null
    }

    try {
        return JSON.parse(raw)
    } catch (error) {
        console.error('The advanced rich editor could not read its autosave settings:', error)

        return null
    }
}

/**
 * The browser's storage, or nothing at all.
 *
 * Private windows, storage somebody switched off and iframes on a third-party origin all
 * answer the same way: by throwing. A field that cannot keep a draft is a field without
 * this feature, which is what it was before - so it says so once and carries on.
 */
function storageOf() {
    try {
        const storage = window.localStorage
        const probe = `${PREFIX}probe`

        storage.setItem(probe, '1')
        storage.removeItem(probe)

        return storage
    } catch (error) {
        return null
    }
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core
    const pmState = window.FilamentRichEditor?.tiptap?.pmState
    const pmModel = window.FilamentRichEditor?.tiptap?.pmModel

    if (! tiptap || ! pmState || ! pmModel) {
        console.error(
            'The advanced rich editor autosave needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap
    const { Plugin, PluginKey } = pmState
    const { Node } = pmModel

    /**
     * One field's draft: the key it lives under, the timer that writes it, the bar that
     * offers it back and the question asked on the way out of the page.
     */
    class Autosave {
        constructor(view, editor, settings, storage) {
            this.view = view
            this.editor = editor
            this.settings = settings
            this.storage = storage

            this.key = draftKey(settings.key, window.location.pathname)
            this.timer = null

            // What the server rendered. Everything this decides is decided against it: a
            // document that says this again is a document with nothing unsaved in it.
            this.saved = JSON.stringify(view.state.doc.toJSON())

            if (! swept) {
                swept = true

                prune(this.storage, { now: Date.now(), ttl: this.settings.ttl })
            }

            this.offer()
            this.listen()
        }

        listen() {
            this.onSubmit = () => this.submitted()
            this.onBeforeUnload = (event) => this.warn(event)

            // The form is the only thing that knows a submit happened, and a submit is the
            // only moment where what is on screen is on its way to the server. Whether it
            // arrives is a different question, which the next opening answers.
            this.form = this.view.dom.closest('form')
            this.form?.addEventListener('submit', this.onSubmit)

            if (this.settings.warnOnLeave) {
                window.addEventListener('beforeunload', this.onBeforeUnload)
            }
        }

        /**
         * The form went. What is on screen is on its way to the server, so from here on that
         * is what "unchanged" means - and the draft of it has nothing left to recover.
         *
         * Whether it arrives is a different question, and the next opening answers it: a
         * document that comes back saying something else is a document with a draft worth
         * offering. Without this the baseline stays the document the page was opened with,
         * and every save on a page that does not redirect leaves the field permanently
         * dirty - asking "leave site?" on the way out of work that was saved ten minutes ago.
         */
        submitted() {
            this.saved = JSON.stringify(this.view.state.doc.toJSON())
            this.forget()
        }

        /**
         * Whether the document says something the server has not been told.
         */
        isDirty() {
            return JSON.stringify(this.view.state.doc.toJSON()) !== this.saved
        }

        /**
         * A transaction went through. Only the ones that changed the document count - a
         * caret moving is not work anybody would want back.
         */
        changed(previous) {
            if (previous.doc.eq(this.view.state.doc)) {
                return
            }

            clearTimeout(this.timer)

            this.timer = setTimeout(() => this.store(), this.settings.debounce)
        }

        store() {
            if (! this.isDirty()) {
                // Typed back to where it started. There is nothing to recover, and leaving
                // the old draft would mean offering it after the next reload.
                return this.forget()
            }

            writeDraft(this.storage, this.key, this.view.state.doc.toJSON(), { now: Date.now() })
        }

        forget() {
            clearTimeout(this.timer)
            this.storage.removeItem(this.key)
        }

        /**
         * The bar, when there is a draft that says something else.
         */
        offer() {
            const draft = readDraft(this.storage, this.key, { now: Date.now(), ttl: this.settings.ttl })

            if (! shouldOffer(draft, this.view.state.doc.toJSON())) {
                // Same document, so the work reached the server after all.
                if (draft) {
                    this.forget()
                }

                return
            }

            this.bar = document.createElement('div')
            this.bar.className = 'fi-arte-draft-bar'

            const message = document.createElement('p')
            message.className = 'fi-arte-draft-message'
            message.textContent = (this.settings.labels?.found ?? '').replace(
                ':time',
                // Formatted by the browser, which is the only party here that knows what
                // the reader's clock and calendar look like.
                new Date(draft.savedAt).toLocaleString(),
            )

            const restore = document.createElement('button')
            restore.type = 'button'
            restore.className = 'fi-arte-draft-restore'
            restore.textContent = this.settings.labels?.restore ?? ''
            restore.addEventListener('click', () => this.restore(draft))

            const discard = document.createElement('button')
            discard.type = 'button'
            discard.className = 'fi-arte-draft-discard'
            discard.textContent = this.settings.labels?.discard ?? ''
            discard.addEventListener('click', () => {
                this.forget()
                this.close()
            })

            this.bar.append(message, restore, discard)

            // Between the toolbar and the document, which is the one place in this field
            // that is the full width of it: from `2xl` up Filament lays the box holding the
            // document out as a two-column row, so a bar appended there is not above the
            // text but beside it, in the column the panels open in.
            // `before()` rather than the parent's `insertBefore()`, because the box holding
            // the document is not a child of the field but of the element Alpine is mounted
            // on, and asking the wrong parent to insert before it throws.
            const main = this.view.dom
                .closest('.fi-fo-rich-editor')
                ?.querySelector('.fi-fo-rich-editor-main')

            if (main) {
                main.before(this.bar)
            } else {
                this.view.dom.parentElement?.insertBefore(this.bar, this.view.dom)
            }
        }

        /**
         * The draft, put back as an ordinary edit.
         *
         * A transaction rather than a call into TipTap, so that Filament's own update
         * handler sees it and the restored document reaches the state the form submits -
         * and so that restoring is one step in the undo chain rather than a thing that
         * happened to the editor.
         */
        restore(draft) {
            let content

            try {
                content = Node.fromJSON(this.view.state.schema, draft.content)
            } catch (error) {
                console.error('The advanced rich editor could not restore a draft:', error)

                this.forget()

                return this.close()
            }

            this.view.dispatch(
                this.view.state.tr
                    .replaceWith(0, this.view.state.doc.content.size, content.content)
                    .scrollIntoView(),
            )

            this.view.focus()
            this.close()
        }

        close() {
            this.bar?.remove()
            this.bar = null
        }

        warn(event) {
            if (! this.isDirty()) {
                return
            }

            // The wording is the browser's own and has been for years; what a page says here
            // is only whether to ask at all.
            event.preventDefault()
            event.returnValue = ''
        }

        destroy() {
            clearTimeout(this.timer)
            this.form?.removeEventListener('submit', this.onSubmit)
            window.removeEventListener('beforeunload', this.onBeforeUnload)
            this.close()
        }
    }

    return Extension.create({
        name: 'arteAutosave',

        addProseMirrorPlugins() {
            const editor = this.editor

            return [
                new Plugin({
                    key: new PluginKey('arteAutosave'),
                    view: (view) => {
                        const settings = settingsOf(editor)
                        const storage = settings ? storageOf() : null

                        if (! settings || ! storage) {
                            return {}
                        }

                        const autosave = new Autosave(view, editor, settings, storage)

                        return {
                            update: (_, previous) => autosave.changed(previous),
                            destroy: () => autosave.destroy(),
                        }
                    },
                }),
            ]
        },
    })
}
