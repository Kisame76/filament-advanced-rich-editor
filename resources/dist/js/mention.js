/*
 * The mention menu, and the node it inserts.
 *
 * Forked from filament/forms v5.7.6:
 * vendor/filament/forms/resources/js/components/rich-editor/extension-mention.js
 *
 * The node half is upstream's, kept as it is so that a document written here is the same
 * document Filament writes: the same `data-id`, `data-label`, `data-char` and class, the
 * same backspace behaviour, and the same pass that fills in a label the server has since
 * changed. Nothing stored changes shape because this file exists.
 *
 * The menu half is ours, and it is the reason for the fork. Upstream draws a row as one
 * span of text - `textContent`, no avatar, no second line, and no `data-id` on the row to
 * hang either on. There is no seam to reach in through: the suggestion is built into
 * ProseMirror plugins while the editor is constructed, so it can only be replaced whole.
 * Filament replaces a built-in extension with a custom one of the same name, which is the
 * door this walks through.
 *
 * `@tiptap/suggestion` and `@floating-ui/dom` are not on the global Filament publishes, so
 * neither is used: the trigger is found by reading the text before the caret, and the panel
 * is placed from the caret's own coordinates. That is the same shape `slash-menu.js` has
 * had since it was written.
 *
 * This package ships no bundler, so the file must stay free of `import` statements.
 */

export const SEARCH_DEBOUNCE = 300
const MAX_HEIGHT = 320
const WIDTH = 17 * 16
const MARGIN = 8
const GAP = 6

const escapeRegExp = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')

/**
 * The trigger the caret is sitting in, or null.
 *
 * A trigger only counts at the start of a block or after a space, which is what keeps
 * `ann@example.test` an email address rather than a mention of `example`. The nearest one
 * to the caret wins, so a second mention on a line opens on itself rather than on the
 * first.
 */
export function queryInText(before, chars) {
    let found = null

    for (const char of chars ?? []) {
        const escaped = escapeRegExp(char)
        const match = new RegExp(`(?:^|\\s)(${escaped}[^\\s${escaped}]*)$`).exec(before)

        if (!match) {
            continue
        }

        const start = match.index + (match[0].length - match[1].length)

        if (found === null || start > found.start) {
            found = { char, query: match[1].slice(char.length), start }
        }
    }

    return found
}

/**
 * The rows the server sent, in the one shape the menu draws.
 *
 * Three shapes arrive here and all three are Filament's own: the `id => label` map its
 * providers return, a list of objects, and a list of bare strings. A row carrying a picture
 * and a second line is this package's addition and rides in the same list.
 */
export function normalizeItems(raw) {
    if (!raw) {
        return []
    }

    const rows = Array.isArray(raw)
        ? raw
        : Object.entries(raw).map(([id, label]) => ({ id, label }))

    return rows
        .map((row) => {
            if (typeof row === 'string') {
                return { id: row, label: row, avatar: null, hint: null }
            }

            // Without an id there is nobody to mention: the row would insert a node the
            // server cannot resolve back to a record, which is markup that looks like a
            // mention. Upstream draws it; this drops it.
            if (!row || row.id === undefined || row.id === null || row.id === '') {
                return null
            }

            const id = String(row.id)

            return {
                id,
                label: String(row.label ?? row.name ?? id),
                avatar: row.avatar ?? null,
                hint: row.hint ?? null,
            }
        })
        .filter(Boolean)
}

/**
 * The rows matching what has been typed, in the order they should be read.
 *
 * A name that starts with the query comes before one that merely contains it, and the
 * second line is searched too - somebody typing `mathem` is looking for the mathematician
 * and does not know how the name is spelled.
 */
export function filterItems(items, query) {
    if (!query) {
        return items
    }

    const needle = query.toLowerCase()

    const rank = (item) => {
        const haystacks = [item.label, item.hint].filter(Boolean).map((value) => value.toLowerCase())

        if (haystacks.some((value) => value.startsWith(needle))) {
            return 0
        }

        return haystacks.some((value) => value.includes(needle)) ? 1 : null
    }

    return items
        .map((item) => ({ item, rank: rank(item) }))
        .filter((entry) => entry.rank !== null)
        .sort((a, b) => a.rank - b.rank)
        .map((entry) => entry.item)
}

/**
 * The initials a name is drawn by where there is no picture.
 *
 * A blank square would leave the rows ragged, and the column an avatar sits in is what
 * makes a list of people scannable in the first place.
 */
function initials(label) {
    return label
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0].toUpperCase())
        .join('')
}

/**
 * One row of the menu.
 *
 * Built rather than written as markup, and filled with `textContent` throughout: a label
 * is somebody else's data - a user's display name - and the one thing it must never be is
 * markup.
 */
export function renderRow(item, isSelected) {
    const row = document.createElement('button')
    row.type = 'button'
    row.className = `fi-arte-mention-item${isSelected ? ' fi-arte-mention-item-active' : ''}`
    row.setAttribute('role', 'option')
    row.setAttribute('aria-selected', isSelected ? 'true' : 'false')

    if (item.avatar) {
        const avatar = document.createElement('img')
        avatar.className = 'fi-arte-mention-avatar'
        avatar.src = item.avatar
        // Decoration: the label beside it names the person, and a screen reader reading
        // that name twice is worse than one that does not read the picture at all.
        avatar.alt = ''
        // Set as attributes rather than as properties: a row that has scrolled out of the
        // panel should not have fetched its picture, and `loading` only means that as an
        // attribute in every browser this runs in.
        avatar.setAttribute('loading', 'lazy')
        avatar.setAttribute('decoding', 'async')
        row.append(avatar)
    } else {
        const avatar = document.createElement('span')
        avatar.className = 'fi-arte-mention-avatar'
        avatar.setAttribute('aria-hidden', 'true')
        avatar.textContent = initials(item.label)
        row.append(avatar)
    }

    const text = document.createElement('span')
    text.className = 'fi-arte-mention-text'

    const label = document.createElement('span')
    label.className = 'fi-arte-mention-label'
    label.textContent = item.label
    text.append(label)

    if (item.hint) {
        const hint = document.createElement('span')
        hint.className = 'fi-arte-mention-hint'
        hint.textContent = item.hint
        text.append(hint)
    }

    row.append(text)

    return row
}

/**
 * The configuration the field wrote onto the element the editor was mounted on.
 *
 * Filament replaces a built-in extension with a custom one of the same name rather than
 * merging the two, so everything its own `Mention.configure()` carried - the triggers, the
 * messages, the way back to the component - is gone by the time this runs. The element is
 * the one channel left, and it is the channel `slash-menu.js` already uses.
 */
function readConfig(editor) {
    const raw = editor.options.element?.dataset?.arteMentions

    if (!raw) {
        return null
    }

    try {
        const config = JSON.parse(raw)

        return config?.triggers?.length ? config : null
    } catch (error) {
        console.error('The advanced rich editor could not read its mention menu:', error)

        return null
    }
}

/**
 * The Livewire component the field lives in.
 *
 * Reached through Alpine rather than through a closure, because the closure Filament
 * passes to its own extension is lost when this one replaces it. Evaluating `$wire` in the
 * editor's own scope is what a toolbar button does for every other server call in this
 * package.
 */
function wireFor(editor) {
    const element = editor.options.element

    if (!element || !window.Alpine) {
        return null
    }

    try {
        return window.Alpine.evaluate(element, '$wire') ?? null
    } catch (error) {
        console.error('The advanced rich editor could not reach Livewire for its mentions:', error)

        return null
    }
}

export class MentionMenu {
    constructor(editor) {
        this.editor = editor
        this.config = readConfig(editor)
        this.panel = null
        this.list = null
        this.items = []
        // What the server last answered for this trigger, kept so that the next keystroke
        // can narrow it on the spot instead of waiting for a second answer.
        this.results = []
        this.active = 0
        this.range = null
        this.trigger = null
        this.query = ''
        this.dismissed = false
        this.loading = false
        this.timer = null
        this.request = 0

        this.onOutsideClick = (event) => {
            if (this.panel && !this.panel.contains(event.target)) {
                this.close()
            }
        }
    }

    get isOpen() {
        return this.panel !== null
    }

    get chars() {
        return (this.config?.triggers ?? []).map((trigger) => trigger.char)
    }

    triggerFor(char) {
        return (this.config?.triggers ?? []).find((trigger) => trigger.char === char) ?? null
    }

    update() {
        if (!this.config) {
            return
        }

        const { selection } = this.editor.state

        if (!selection.empty) {
            this.close()

            return
        }

        const { $from } = selection

        // A trigger inside a code block is nearly always code.
        if ($from.parent.type.spec.code) {
            this.close()

            return
        }

        // `￼` stands in for every leaf the block holds, so an image counts as one character
        // and the offsets stay true.
        const before = $from.parent.textBetween(0, $from.parentOffset, undefined, '￼')
        const found = queryInText(before, this.chars)

        if (!found) {
            this.dismissed = false
            this.close()

            return
        }

        // Escape closes the menu for the name being typed rather than for good.
        if (this.dismissed) {
            return
        }

        const trigger = this.triggerFor(found.char)

        // A different trigger is a different pool - the people it found are not the
        // categories the next one will.
        if (trigger !== this.trigger) {
            this.results = []
        }

        this.trigger = trigger
        this.range = { from: $from.start() + found.start, to: $from.pos }
        this.query = found.query

        this.load()
    }

    /**
     * The rows for what has been typed.
     *
     * A provider that searches on the server is asked once the typing pauses, and the rows
     * it already has are drawn in the meantime - a menu that empties itself between two
     * keystrokes reads as a menu that found nothing.
     */
    load() {
        const trigger = this.trigger

        clearTimeout(this.timer)

        // A provider handed its whole list is searched here rather than on the server, so
        // there is nothing to wait for and nothing to ask.
        if (!trigger?.isSearchable) {
            this.items = filterItems(normalizeItems(trigger?.items), this.query)
            this.active = 0
            this.render()

            return
        }

        // The names already found, narrowed by the letter just typed. The server is asked
        // again in a moment and will answer better, but a panel that empties itself between
        // two keystrokes reads as a panel that found nobody.
        this.items = filterItems(this.results, this.query)
        this.active = 0
        this.render()

        this.timer = setTimeout(() => this.search(), SEARCH_DEBOUNCE)
    }

    async search() {
        const wire = wireFor(this.editor)
        const trigger = this.trigger

        if (!wire || !trigger) {
            return
        }

        const request = ++this.request
        const query = this.query

        this.loading = this.items.length === 0
        this.render()

        try {
            const results = await wire.callSchemaComponentMethod(
                this.config.key,
                'getMentionSearchResultsForJs',
                { search: query, char: trigger.char },
            )

            // An answer to a name that has since been typed further is an answer to a
            // question nobody is asking any more.
            if (request !== this.request || !this.isOpen) {
                return
            }

            this.results = normalizeItems(results)
            this.items = filterItems(this.results, this.query)
            this.active = 0
        } catch (error) {
            console.error('The advanced rich editor could not search for mentions:', error)
        } finally {
            if (request === this.request) {
                this.loading = false
                this.render()
            }
        }
    }

    render() {
        if (!this.panel) {
            this.panel = document.createElement('div')
            this.panel.className = 'fi-arte-mention-menu'

            // The scrolling is the inner element's, so the bar it draws is held clear of the
            // rounded corners - see the stylesheet. The list is what carries the role: the
            // options are inside it, and a listbox has to be the thing that holds them.
            this.list = document.createElement('div')
            this.list.className = 'fi-arte-mention-list'
            this.list.setAttribute('role', 'listbox')
            this.panel.append(this.list)

            document.body.append(this.panel)
            document.addEventListener('mousedown', this.onOutsideClick, true)
        }

        this.list.replaceChildren()

        if (this.items.length === 0) {
            const note = document.createElement('div')
            note.className = 'fi-arte-mention-note'
            note.textContent = this.emptyMessage()
            this.list.append(note)
            this.position()

            return
        }

        this.items.forEach((item, index) => {
            const row = renderRow(item, index === this.active)
            row.addEventListener('mousedown', (event) => {
                // The caret has to stay where it is: losing the selection to the click
                // would leave nowhere to insert.
                event.preventDefault()
                this.choose(index)
            })
            this.list.append(row)
        })

        this.position()
    }

    emptyMessage() {
        const trigger = this.trigger ?? {}

        if (this.loading) {
            return trigger.searchingMessage ?? ''
        }

        if (this.query) {
            return trigger.noSearchResultsMessage ?? ''
        }

        return (trigger.isSearchable ? trigger.searchPrompt : trigger.noOptionsMessage) ?? ''
    }

    /**
     * The panel, put where the name being typed is.
     *
     * Below the caret unless there is no room below, and never off the side. The panel is
     * on `document.body` rather than inside the editor so that a field with `maxHeight()`
     * cannot clip it on the last line.
     */
    position() {
        if (!this.panel || !this.range) {
            return
        }

        const caret = this.editor.view.coordsAtPos(this.range.from)
        // Measured off the shell rather than out of it: the list inside is capped to the
        // room the shell has, so its own `scrollHeight` is the content's and the shell's is
        // not. `offsetHeight` is what the panel actually occupies, which is what a position
        // has to be worked out against.
        const height = Math.min(this.panel.offsetHeight || MAX_HEIGHT, MAX_HEIGHT)
        const below = window.innerHeight - caret.bottom - MARGIN
        const flip = below < height && caret.top > below

        this.panel.style.width = `${WIDTH}px`
        this.panel.style.maxHeight = `${MAX_HEIGHT}px`
        this.panel.style.left = `${Math.max(MARGIN, Math.min(caret.left, window.innerWidth - WIDTH - MARGIN))}px`
        this.panel.style.top = flip
            ? `${Math.max(MARGIN, caret.top - height - GAP)}px`
            : `${caret.bottom + GAP}px`
    }

    move(direction) {
        if (this.items.length === 0) {
            return
        }

        this.active = (this.active + direction + this.items.length) % this.items.length
        this.render()

        this.list?.children[this.active]?.scrollIntoView({ block: 'nearest' })
    }

    choose(index) {
        const item = this.items[index]
        const range = this.range
        const char = this.trigger?.char ?? '@'
        const extraAttributes = this.trigger?.extraAttributes ?? {}

        if (!item || !range) {
            return
        }

        this.close()

        this.editor
            .chain()
            .focus()
            .insertContentAt(range, [
                {
                    type: 'mention',
                    attrs: {
                        id: item.id,
                        label: item.label,
                        char,
                        ...(Object.keys(extraAttributes).length ? { extra: extraAttributes } : {}),
                    },
                },
                { type: 'text', text: ' ' },
            ])
            .run()
    }

    close() {
        clearTimeout(this.timer)
        this.request++

        if (!this.panel) {
            return
        }

        document.removeEventListener('mousedown', this.onOutsideClick, true)
        this.panel.remove()
        this.panel = null
        this.list = null
        this.items = []
        this.results = []
        this.range = null
        this.trigger = null
        this.loading = false
    }

    handleKeyDown(event) {
        if (!this.isOpen) {
            return false
        }

        if (event.key === 'Escape') {
            this.dismissed = true
            this.close()

            return true
        }

        if (event.key === 'ArrowDown') {
            this.move(1)

            return true
        }

        if (event.key === 'ArrowUp') {
            this.move(-1)

            return true
        }

        if (event.key === 'Enter' || event.key === 'Tab') {
            if (this.items.length === 0) {
                return false
            }

            this.choose(this.active)

            return true
        }

        return false
    }
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap

    if (!tiptap?.core || !tiptap?.pmState || !tiptap?.pmModel) {
        console.error(
            'The advanced rich editor mention menu needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Node, mergeAttributes } = tiptap.core
    const { Plugin, PluginKey } = tiptap.pmState
    const { Node: ProseMirrorNode } = tiptap.pmModel

    return Node.create({
        name: 'mention',

        priority: 101,

        addOptions() {
            return {
                // Filament's own class, because a mention drawn differently inside this
                // editor than inside a plain one is a mention that looks like a bug.
                HTMLAttributes: { class: 'fi-fo-rich-editor-mention' },
                deleteTriggerWithBackspace: true,
                renderText({ node }) {
                    return `${node.attrs.char ?? '@'}${node.attrs.label ?? ''}`
                },
            }
        },

        group: 'inline',

        inline: true,

        selectable: false,

        atom: true,

        addAttributes() {
            return {
                id: {
                    default: null,
                    parseHTML: (element) => element.getAttribute('data-id'),
                    renderHTML: (attributes) => (attributes.id ? { 'data-id': attributes.id } : {}),
                },

                label: {
                    default: null,
                    keepOnSplit: false,
                    parseHTML: (element) => element.getAttribute('data-label'),
                    renderHTML: (attributes) =>
                        attributes.label ? { 'data-label': attributes.label } : {},
                },

                char: {
                    default: '@',
                    parseHTML: (element) => element.getAttribute('data-char') ?? '@',
                    renderHTML: (attributes) => (attributes.char ? { 'data-char': attributes.char } : {}),
                },

                extra: {
                    default: null,
                    renderHTML: (attributes) => {
                        const value = attributes?.extra

                        return value && typeof value === 'object' ? value : {}
                    },
                },
            }
        },

        parseHTML() {
            return [{ tag: `span[data-type="${this.name}"]` }]
        },

        renderHTML({ node, HTMLAttributes }) {
            return [
                'span',
                mergeAttributes(
                    { 'data-type': this.name },
                    this.options.HTMLAttributes,
                    HTMLAttributes,
                ),
                `${node.attrs.char ?? '@'}${node.attrs.label ?? ''}`,
            ]
        },

        renderText({ node }) {
            return this.options.renderText({ options: this.options, node })
        },

        addKeyboardShortcuts() {
            return {
                Backspace: () =>
                    this.editor.commands.command(({ tr: transaction, state }) => {
                        let isMention = false
                        const { selection } = state
                        const { empty, anchor } = selection

                        if (!empty) {
                            return false
                        }

                        let mentionNode = new ProseMirrorNode()
                        let mentionPos = 0

                        state.doc.nodesBetween(anchor - 1, anchor, (node, pos) => {
                            if (node.type.name === this.name) {
                                isMention = true
                                mentionNode = node
                                mentionPos = pos

                                return false
                            }
                        })

                        if (isMention) {
                            const trigger = mentionNode?.attrs?.char ?? '@'

                            transaction.insertText(
                                this.options.deleteTriggerWithBackspace ? '' : trigger,
                                mentionPos,
                                mentionPos + mentionNode.nodeSize,
                            )
                        }

                        return isMention
                    }),
            }
        },

        addProseMirrorPlugins() {
            const editor = this.editor
            const name = this.name
            const menu = new MentionMenu(editor)

            /**
             * Fills in a label the document does not carry.
             *
             * A mention stores an id; the name beside it is a copy of what the record was
             * called when it was typed. Content saved before this ran, or saved by
             * something that only wrote ids, would otherwise draw as a bare trigger.
             */
            const hydrate = async (view) => {
                const pending = []

                view.state.doc.descendants((node, pos) => {
                    if (node.type.name !== name || node.attrs?.label || !node.attrs?.id) {
                        return
                    }

                    pending.push({ id: node.attrs.id, char: node.attrs.char ?? '@', pos })
                })

                if (pending.length === 0) {
                    return
                }

                const wire = wireFor(editor)
                const key = menu.config?.key

                if (!wire || !key) {
                    return
                }

                try {
                    const labels = await wire.callSchemaComponentMethod(key, 'getMentionLabelsForJs', {
                        mentions: pending.map(({ id, char }) => ({ id, char })),
                    })

                    pending.forEach(({ id, pos }) => {
                        const label = labels?.[id]
                        const current = view.state.doc.nodeAt(pos)

                        if (!label || current?.type.name !== name) {
                            return
                        }

                        view.dispatch(
                            view.state.tr.setNodeMarkup(pos, undefined, { ...current.attrs, label }),
                        )
                    })
                } catch (error) {
                    console.error('The advanced rich editor could not read its mention labels:', error)
                }
            }

            return [
                new Plugin({
                    key: new PluginKey('arteMentionMenu'),
                    props: {
                        handleKeyDown: (_view, event) => menu.handleKeyDown(event),
                    },
                    view: (view) => {
                        setTimeout(() => hydrate(view), 0)

                        return {
                            update: (view) => {
                                menu.update()
                                hydrate(view)
                            },
                            destroy: () => menu.close(),
                        }
                    },
                }),
            ]
        },
    })
}
