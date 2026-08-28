/*
 * The slash menu.
 *
 * Typing `/` on an empty line, or after a space, opens a searchable list of the commands
 * the field offers. It is the toolbar reached by typing instead of by aiming - which is
 * what makes it worth having in an editor whose toolbar already has an overflow dropdown:
 * the tools nobody could find are the ones a search is for.
 *
 * Nothing here knows what any command does. The list comes from PHP, built out of the
 * tools the field actually registered, and each entry carries that tool's own handler -
 * the same string the toolbar button runs. Picking an entry evaluates it in the editor's
 * Alpine scope, so `$getEditor()`, `$wire` and `editorSelection` resolve exactly as they
 * do for a click. A command and its button therefore cannot come apart: there is only one
 * of them, and this file is a different way to press it.
 *
 * The rows carry an icon and a name and nothing else. A keyboard shortcut in a third
 * column makes every row as wide as its widest entry, and the panel then covers the text
 * being written - which is the one thing a menu opened mid-sentence must not do. The
 * shortcuts are in the help dialog, where they are looked up on purpose.
 *
 * The panel lives on `document.body` rather than inside the editor. It has to escape the
 * editor's own overflow - a field with `maxHeight()` scrolls, and a menu clipped by the
 * box it opens in would be unusable on the last line.
 */

const WIDTH = 16 * 16
const MAX_HEIGHT = 320
const MARGIN = 8
const GAP = 6

const escapeRegExp = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')

/**
 * The menu the field configured, read off the element the editor was mounted on.
 *
 * The list is built in PHP - it carries labels, icons and handlers that only PHP can
 * produce - and this is the one channel a TipTap extension has to receive anything from
 * the field it belongs to.
 */
function readMenu(editor) {
    const raw = editor.options.element?.dataset?.arteSlash

    if (!raw) {
        return null
    }

    try {
        return JSON.parse(raw)
    } catch (error) {
        console.error('The advanced rich editor could not read its slash menu:', error)

        return null
    }
}

/**
 * The slash query the caret is sitting in, or null.
 *
 * A slash only opens the menu at the start of a block or after a space: `and/or` is a word
 * somebody is writing, not a command. Code blocks are left alone entirely - a slash there
 * is nearly always code.
 */
function activeQuery(state, char) {
    const { selection } = state

    if (!selection.empty) {
        return null
    }

    const { $from } = selection

    if ($from.parent.type.spec.code) {
        return null
    }

    // `￼` stands in for every leaf the block holds, so an image or a merge tag counts
    // as one character rather than as nothing and the offsets stay true.
    const before = $from.parent.textBetween(0, $from.parentOffset, undefined, '￼')
    const escaped = escapeRegExp(char)
    const match = new RegExp(`(?:^|\\s)(${escaped}[^\\s${escaped}]*)$`).exec(before)

    if (!match) {
        return null
    }

    const from = $from.start() + match.index + (match[0].length - match[1].length)

    return { from, to: $from.pos, query: match[1].slice(char.length).toLowerCase() }
}

/**
 * The items matching a query, in the order they should be read.
 *
 * A command whose name starts with what was typed comes before one that merely contains
 * it, because someone typing `/co` means the code block far more often than they mean
 * "table of contents".
 */
function filterGroups(groups, query) {
    if (!query) {
        return groups
    }

    const rank = (item) => {
        const haystacks = [item.label, item.name, ...(item.aliases ?? [])].map((value) =>
            String(value).toLowerCase(),
        )

        if (haystacks.some((value) => value.startsWith(query))) {
            return 0
        }

        return haystacks.some((value) => value.includes(query)) ? 1 : null
    }

    return groups
        .map((group) => ({
            ...group,
            items: group.items
                .map((item) => ({ item, rank: rank(item) }))
                .filter(({ rank }) => rank !== null)
                .sort((a, b) => a.rank - b.rank)
                .map(({ item }) => item),
        }))
        .filter((group) => group.items.length > 0)
}

class SlashMenu {
    constructor(editor) {
        this.editor = editor
        this.menu = readMenu(editor)
        this.panel = null
        this.list = null
        this.items = []
        this.active = 0
        this.range = null
        this.dismissed = false

        this.onOutsideClick = (event) => {
            if (this.panel && !this.panel.contains(event.target)) {
                this.close()
            }
        }
    }

    get isOpen() {
        return this.panel !== null
    }

    update() {
        if (!this.menu) {
            return
        }

        const query = activeQuery(this.editor.state, this.menu.char)

        if (!query) {
            this.dismissed = false
            this.close()

            return
        }

        // Escape closes the menu for the word being typed rather than for good: it reopens
        // as soon as this slash is finished with and another one is started.
        if (this.dismissed) {
            return
        }

        this.range = { from: query.from, to: query.to }

        const groups = filterGroups(this.menu.groups, query.query)

        this.items = groups.flatMap((group) => group.items)
        this.active = 0

        this.render(groups)
        this.position()
    }

    render(groups) {
        if (!this.panel) {
            this.panel = document.createElement('div')
            this.panel.className = 'fi-arte-slash'

            // The scrolling is the inner element's, so the bar it draws is held clear of
            // the rounded corners - see the stylesheet. The list is what carries the role:
            // the options are inside it, and a listbox has to be the thing that holds them.
            this.list = document.createElement('div')
            this.list.className = 'fi-arte-slash-list'
            this.list.setAttribute('role', 'listbox')
            this.panel.append(this.list)

            document.body.append(this.panel)
            document.addEventListener('mousedown', this.onOutsideClick, true)
        }

        this.list.replaceChildren()

        if (groups.length === 0) {
            const empty = document.createElement('div')
            empty.className = 'fi-arte-slash-empty'
            empty.textContent = this.menu.empty
            this.list.append(empty)

            return
        }

        let index = 0

        for (const group of groups) {
            const section = document.createElement('div')
            section.className = 'fi-arte-slash-group'

            const label = document.createElement('div')
            label.className = 'fi-arte-slash-group-label'
            label.textContent = group.label
            section.append(label)

            for (const item of group.items) {
                section.append(this.button(item, index))
                index++
            }

            this.list.append(section)
        }

        this.paint()
    }

    button(item, index) {
        const button = document.createElement('button')
        button.type = 'button'
        button.className = 'fi-arte-slash-item'
        button.dataset.index = String(index)
        button.setAttribute('role', 'option')

        const icon = document.createElement('span')
        icon.className = 'fi-arte-slash-item-icon'
        // The icon is Blade Icons output built in PHP: markup this package produced, not
        // anything a person typed into the editor.
        icon.innerHTML = item.icon ?? ''

        const label = document.createElement('span')
        label.className = 'fi-arte-slash-item-label'
        label.textContent = item.label

        button.append(icon, label)

        // `mousedown` rather than `click`: a click would move focus out of the editor
        // first, and the command needs the selection it is standing on.
        button.addEventListener('mousedown', (event) => {
            event.preventDefault()
            this.choose(index)
        })

        button.addEventListener('mouseenter', () => {
            this.active = index
            this.paint()
        })

        return button
    }

    paint() {
        for (const button of this.list.querySelectorAll('.fi-arte-slash-item')) {
            const isActive = Number(button.dataset.index) === this.active

            button.classList.toggle('fi-arte-slash-item-active', isActive)
            button.setAttribute('aria-selected', isActive ? 'true' : 'false')

            if (isActive) {
                button.scrollIntoView({ block: 'nearest' })
            }
        }
    }

    position() {
        const coords = this.editor.view.coordsAtPos(this.range.from)
        const height = Math.min(this.panel.offsetHeight || MAX_HEIGHT, MAX_HEIGHT)

        // Below the line by default, above it when the line is too close to the bottom -
        // the menu opens where there is room for it rather than where it is asked to.
        const below = coords.bottom + GAP
        const fitsBelow = below + height + MARGIN <= window.innerHeight
        const top = fitsBelow ? below : Math.max(MARGIN, coords.top - GAP - height)

        // The right edge is only worth avoiding while there is room to avoid it: on a window
        // narrower than the panel the inner term goes negative, and clamping to it would
        // push the menu off the left of the screen instead.
        const left = Math.max(MARGIN, Math.min(coords.left, window.innerWidth - WIDTH - MARGIN))

        this.panel.style.left = `${left}px`
        this.panel.style.top = `${top}px`
    }

    move(offset) {
        if (this.items.length === 0) {
            return
        }

        this.active = (this.active + offset + this.items.length) % this.items.length
        this.paint()
    }

    choose(index) {
        const item = this.items[index]

        if (!item) {
            return
        }

        const range = this.range
        const element = this.editor.options.element

        this.close()

        // The typed `/query` goes first, so the command acts on the block as it will be
        // rather than on one with a stray word in it.
        this.editor.chain().focus().deleteRange(range).run()

        // A tick later: handlers read `editorSelection`, which the Alpine component keeps
        // in step with the editor through its own listener, and that listener has not run
        // yet at this point in the transaction.
        setTimeout(() => this.run(item, element), 0)
    }

    /**
     * Runs the tool's own handler in the editor's Alpine scope.
     *
     * The handler is the string the toolbar button carries, and it is written against that
     * scope: `$getEditor()`, `$wire` and `editorSelection` all come from the component the
     * editor element sits in. Alpine's evaluator is the only thing that can resolve those,
     * which is why the menu asks it rather than reimplementing any command.
     */
    run(item, element) {
        const Alpine = window.Alpine

        if (!Alpine?.evaluate) {
            console.error('The advanced rich editor slash menu needs Alpine to run a command.')

            return
        }

        try {
            // Some handlers anchor a popup to the element that was clicked. There was no
            // click, so the editor stands in for it.
            Alpine.evaluate(element, item.handler, { $event: { currentTarget: element } })
        } catch (error) {
            console.error(`The advanced rich editor could not run [${item.name}]:`, error)
        }
    }

    close() {
        if (!this.panel) {
            return
        }

        document.removeEventListener('mousedown', this.onOutsideClick, true)
        this.panel.remove()
        this.panel = null
        this.list = null
        this.items = []
        this.range = null
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
    const tiptap = window.FilamentRichEditor?.tiptap?.core
    const pmState = window.FilamentRichEditor?.tiptap?.pmState

    if (!tiptap || !pmState) {
        console.error(
            'The advanced rich editor slash menu needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap
    const { Plugin, PluginKey } = pmState

    return Extension.create({
        name: 'arteSlashMenu',

        addProseMirrorPlugins() {
            const editor = this.editor
            const menu = new SlashMenu(editor)

            return [
                new Plugin({
                    key: new PluginKey('arteSlashMenu'),
                    props: {
                        handleKeyDown: (_view, event) => menu.handleKeyDown(event),
                    },
                    view: () => ({
                        update: () => menu.update(),
                        destroy: () => menu.close(),
                    }),
                }),
            ]
        },
    })
}
