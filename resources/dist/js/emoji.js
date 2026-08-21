/*
 * The emoji picker.
 *
 * An emoji is a Unicode character, so nothing here touches the schema: the picker inserts
 * plain text and the character travels through the sanitiser, the save and the server side
 * renderer like any other letter. That is the whole reason this is an extension with two
 * commands rather than a node with a parser.
 *
 * The popup is built here instead of being a toolbar component of its own because the tool
 * has to survive being put inside a dropdown, where Filament renders button names and
 * nothing else. So the button asks for a popup, and this file owns it: it lives on
 * `document.body`, opens under the line being written rather than over it, and stays open
 * until it is dismissed - picking an emoji is rarely something anyone does once, and a
 * picker that closed on every pick would have to be reopened for the next one.
 *
 * Which means it needs a way out and a way aside: the header carries a close button and
 * doubles as a drag handle, Escape closes, and so does a click anywhere outside it - except
 * inside the editor, where a click is how the caret gets moved to the next spot an emoji
 * belongs in.
 *
 * The list itself is a sibling module, fetched the first time the picker opens - 60 KB of
 * emoji has no business loading in an editor nobody clicks that button in. The tabs, their
 * labels and their icons come the other way, from PHP, so they follow the locale and the
 * package's icon registry.
 */

const WIDTH = 22 * 16
const HEIGHT = 320
const MIN_HEIGHT = 200
const MARGIN = 8
const RESULT_LIMIT = 180
const RECENT_KEY = 'arte-emoji-recent'
const RECENT_LIMIT = 32
const RECENT = 'recent'

let popup = null
let anchorRect = null
let anchorElement = null
let editorElement = null
let emojisPromise = null
let drag = null

/**
 * The list, loaded once per page. The URL is derived from this module's own so the two
 * files stay together wherever Filament published them, and the version query is carried
 * over so a package upgrade is not served from a stale cache.
 */
function loadEmojis() {
    if (!emojisPromise) {
        const here = new URL(import.meta.url)
        const url = new URL('./emoji-data.js', here)

        url.search = here.search

        emojisPromise = import(url.href)
            .then((module) => module.default)
            .catch((error) => {
                console.error('The advanced rich editor could not load its emoji list:', error)

                emojisPromise = null

                return []
            })
    }

    return emojisPromise
}

/**
 * The emoji last picked, newest first. Kept in `localStorage` because it belongs to the
 * person, not to the record: the same handful of emoji get used across every form in the
 * panel. A browser that refuses storage simply has no first tab worth showing.
 */
function readRecent() {
    try {
        const stored = JSON.parse(window.localStorage.getItem(RECENT_KEY) ?? '[]')

        return Array.isArray(stored) ? stored.filter((entry) => typeof entry === 'string') : []
    } catch {
        return []
    }
}

function remember(emoji) {
    try {
        const recent = [emoji, ...readRecent().filter((entry) => entry !== emoji)].slice(0, RECENT_LIMIT)

        window.localStorage.setItem(RECENT_KEY, JSON.stringify(recent))
    } catch {
        // Storage can be full or switched off. Losing the history is not worth an error.
    }
}

function close() {
    if (!popup) {
        return
    }

    document.removeEventListener('pointerdown', onPointerDown, true)
    document.removeEventListener('keydown', onKeyDown, true)
    window.removeEventListener('resize', reposition)

    endDrag()

    popup.remove()
    popup = null
    anchorRect = null
    anchorElement = null
    editorElement = null
}

function onPointerDown(event) {
    if (!popup || popup.contains(event.target)) {
        return
    }

    // A click in the editor is how the next insertion point gets chosen, so it is the one
    // outside click that must not take the picker away.
    if (editorElement?.contains(event.target)) {
        return
    }

    close()
}

function onKeyDown(event) {
    if (event.key === 'Escape') {
        event.preventDefault()
        close()
    }
}

function reposition() {
    // Once it has been dragged the popup is where someone wanted it, and a window resize is
    // no reason to take that back.
    if (!popup || popup.dataset.dragged) {
        return
    }

    // The button that opened the picker is usually inside a dropdown that closed itself on
    // the very same click, so its rect is only trustworthy while it is still on screen.
    const rect = anchorElement?.getBoundingClientRect()

    if (rect && rect.width) {
        anchorRect = rect
    }

    position()
}

/**
 * The line the caret sits on, so the picker can open under it instead of over it. Falls
 * back to the button when there is no selection to measure - an editor that was never
 * clicked into has no line yet.
 */
function caretRect(editor) {
    try {
        const coords = editor.view.coordsAtPos(editor.state.selection.from)

        return { top: coords.top, bottom: coords.bottom, left: coords.left }
    } catch {
        return null
    }
}

function startDrag(event) {
    const box = popup.getBoundingClientRect()

    drag = { x: event.clientX - box.left, y: event.clientY - box.top }
    popup.dataset.dragged = '1'

    document.addEventListener('pointermove', onDrag)
    document.addEventListener('pointerup', endDrag)
}

function onDrag(event) {
    if (!drag || !popup) {
        return
    }

    const box = popup.getBoundingClientRect()

    // Kept inside the window, or a picker dragged past the edge could not be dragged back.
    popup.style.left = `${clamp(event.clientX - drag.x, window.innerWidth - box.width)}px`
    popup.style.top = `${clamp(event.clientY - drag.y, window.innerHeight - box.height)}px`
}

function endDrag() {
    drag = null

    document.removeEventListener('pointermove', onDrag)
    document.removeEventListener('pointerup', endDrag)
}

function clamp(value, max) {
    return Math.min(Math.max(MARGIN, value), Math.max(MARGIN, max - MARGIN))
}

function position() {
    const width = Math.min(WIDTH, window.innerWidth - 2 * MARGIN)

    // Under the line being written rather than over it: the point of the picker is to put
    // something into that line, so the line has to stay in sight. A short window makes the
    // picker shorter before it makes it jump above the text - going over the line is the
    // last resort, not the first answer.
    const below = window.innerHeight - anchorRect.bottom - 2 * MARGIN
    const above = anchorRect.top - 2 * MARGIN

    const isBelow = below >= MIN_HEIGHT || below >= above
    const height = Math.max(
        MIN_HEIGHT,
        Math.min(HEIGHT, isBelow ? below : above, window.innerHeight - 2 * MARGIN),
    )

    popup.style.width = `${width}px`
    popup.style.height = `${height}px`

    popup.style.top = `${clamp(isBelow ? anchorRect.bottom + MARGIN : anchorRect.top - height - MARGIN, window.innerHeight - height)}px`
    popup.style.left = `${clamp(anchorRect.left, window.innerWidth - width)}px`
}

/**
 * Matches every word of the query against the emoji's Unicode name, so "cat face" finds
 * the grinning cat and "cat" alone still finds the whole litter.
 */
function matches(name, words) {
    return words.every((word) => name.includes(word))
}

function build(editor, groups, labels) {
    const entriesOf = (key) => groups.find(([group]) => group === key)?.[1] ?? []

    // Recent emoji are stored as bare characters, so the names come back out of the list.
    const names = new Map(groups.flatMap(([, entries]) => entries))
    const recentEntries = () => readRecent().map((emoji) => [emoji, names.get(emoji) ?? emoji])

    popup = document.createElement('div')
    popup.className = 'fi-arte-emoji-popup'
    popup.setAttribute('role', 'dialog')
    popup.setAttribute('aria-label', labels.label)

    const header = document.createElement('div')
    header.className = 'fi-arte-emoji-header'

    const title = document.createElement('span')
    title.className = 'fi-arte-emoji-title'
    title.textContent = labels.label

    const dismiss = document.createElement('button')
    dismiss.type = 'button'
    dismiss.className = 'fi-arte-emoji-close'
    dismiss.title = labels.close
    dismiss.setAttribute('aria-label', labels.close)
    dismiss.innerHTML = labels.closeIcon
    dismiss.addEventListener('click', close)

    // The bar the picker is titled by is also the one it is moved by, so nothing extra has
    // to be drawn for a handle. The close button is exempt, or it could not be clicked.
    header.addEventListener('pointerdown', (event) => {
        if (!dismiss.contains(event.target)) {
            event.preventDefault()
            startDrag(event)
        }
    })

    header.append(title, dismiss)

    const search = document.createElement('input')
    search.type = 'search'
    search.className = 'fi-arte-emoji-search'
    search.placeholder = labels.search
    search.setAttribute('aria-label', labels.search)

    const tabs = document.createElement('div')
    tabs.className = 'fi-arte-emoji-tabs'
    tabs.setAttribute('role', 'tablist')

    const grid = document.createElement('div')
    grid.className = 'fi-arte-emoji-grid'
    grid.setAttribute('role', 'listbox')

    const empty = document.createElement('p')
    empty.className = 'fi-arte-emoji-empty'
    empty.hidden = true

    const paint = (entries, emptyText) => {
        // One string beats a thousand appendChild calls, and every character in it comes
        // from the bundled list rather than from anything a user typed.
        grid.innerHTML = entries
            .map(
                ([emoji, name]) =>
                    `<button type="button" tabindex="-1" role="option" class="fi-arte-emoji-item" title="${name}" aria-label="${name}" data-emoji="${emoji}">${emoji}</button>`,
            )
            .join('')

        empty.textContent = emptyText
        empty.hidden = entries.length > 0
        grid.scrollTop = 0
    }

    let current = null

    const paintTab = (key) => {
        current = key

        for (const tab of tabs.children) {
            tab.classList.toggle('fi-active', tab.dataset.group === key)
            tab.setAttribute('aria-selected', String(tab.dataset.group === key))
        }

        paint(
            key === RECENT ? recentEntries() : entriesOf(key),
            key === RECENT ? labels.emptyRecent : labels.empty,
        )
    }

    for (const { key, label, icon } of labels.tabs) {
        const tab = document.createElement('button')

        tab.type = 'button'
        tab.tabIndex = -1
        tab.className = 'fi-arte-emoji-tab'
        tab.dataset.group = key
        tab.setAttribute('role', 'tab')
        tab.title = label
        tab.setAttribute('aria-label', label)
        // The icons are rendered by PHP through the package's icon registry, so a project
        // can swap them like every other icon - and so nine coloured emoji in a row do not
        // have to double as chrome.
        tab.innerHTML = icon

        tab.addEventListener('click', () => {
            search.value = ''
            paintTab(key)
        })

        tabs.append(tab)
    }

    search.addEventListener('input', () => {
        const words = search.value.toLowerCase().split(/\s+/).filter(Boolean)

        if (!words.length) {
            paintTab(current)

            return
        }

        for (const tab of tabs.children) {
            tab.classList.remove('fi-active')
            tab.setAttribute('aria-selected', 'false')
        }

        const found = []

        for (const [, entries] of groups) {
            for (const entry of entries) {
                if (matches(entry[1], words)) {
                    found.push(entry)
                }

                if (found.length >= RESULT_LIMIT) {
                    break
                }
            }
        }

        paint(found, labels.empty)
    })

    grid.addEventListener('click', (event) => {
        const emoji = event.target.closest('.fi-arte-emoji-item')?.dataset.emoji

        if (!emoji) {
            return
        }

        remember(emoji)
        editor.chain().focus().insertEmoji(emoji).run()

        // Staying open is the point, but the recent tab would then be showing a list it is
        // no longer telling the truth about.
        if (current === RECENT && !search.value) {
            paintTab(RECENT)
        }
    })

    popup.append(header, search, tabs, grid, empty)
    document.body.append(popup)

    // The recent tab is where a picker is usually meant to stop: the same few emoji get
    // reached for again and again. It opens on the first real group until there are any.
    paintTab(readRecent().length ? RECENT : labels.tabs.find((tab) => tab.key !== RECENT)?.key)
    position()

    // After the click that opened it, or the picker would close on its own opening.
    setTimeout(() => {
        document.addEventListener('pointerdown', onPointerDown, true)
        document.addEventListener('keydown', onKeyDown, true)
        window.addEventListener('resize', reposition)

        search.focus()
    }, 0)
}

async function open(editor, anchor, labels) {
    // A second click on the button closes what the first one opened.
    if (popup) {
        close()

        return
    }

    if (!anchor) {
        return
    }

    // Read now, not later: the dropdown the button sits in hides itself on this very click.
    anchorElement = anchor
    editorElement = editor.view?.dom?.closest('.fi-fo-rich-editor') ?? editor.view?.dom ?? null
    anchorRect = caretRect(editor) ?? anchor.getBoundingClientRect()

    const groups = await loadEmojis()

    if (!groups.length || popup) {
        return
    }

    build(editor, groups, labels)
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor emoji extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap

    return Extension.create({
        name: 'arteEmoji',

        addCommands() {
            return {
                insertEmoji:
                    (emoji) =>
                    ({ commands }) =>
                        commands.insertContent(emoji),

                openEmojiPicker:
                    (anchor, labels) =>
                    ({ editor }) => {
                        open(editor, anchor, labels)

                        return true
                    },
            }
        },
    })
}
