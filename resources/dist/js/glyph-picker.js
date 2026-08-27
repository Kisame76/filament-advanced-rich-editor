/*
 * The popup two pickers share: the emoji one and the special characters one.
 *
 * Both do the same thing to the same kind of thing - a grid of characters, searchable by
 * name, grouped into tabs, with the ones last used kept first - and a character is a
 * character, so neither of them touches the schema. What differs between them is the list,
 * the strings, the storage key and the class names, and those are the arguments.
 *
 * The class names are passed in as whole literals rather than built from a prefix here, and
 * that is on purpose: `PublishedAssetsTest` reads the class names out of the source files
 * to check that every one of them has a rule, and a name assembled from a variable is a
 * name it can no longer see.
 *
 * The popup lives on `document.body`, opens under the line being written rather than over
 * it, and stays open until it is dismissed - picking a character is rarely something anyone
 * does once, and a picker that closed on every pick would have to be reopened for the next
 * one. Which means it needs a way out and a way aside: the header carries a close button
 * and doubles as a drag handle, Escape closes, and so does a click anywhere outside it -
 * except inside the editor, where a click is how the caret gets moved to the next spot a
 * character belongs in.
 *
 * There is one popup, not one per picker. Opening the characters picker therefore takes the
 * emoji one away, which is the right answer: they occupy the same corner of the screen and
 * two of them there would be two things claiming to be the thing you just clicked.
 */

const HEIGHT = 320
const MIN_HEIGHT = 200
const MARGIN = 8
const RESULT_LIMIT = 180
const RECENT_LIMIT = 32
const RECENT = 'recent'

let popup = null
let anchorRect = null
let anchorElement = null
let editorElement = null
let drag = null
let preferredWidth = 0

/** Which picker the open popup belongs to, so a second click on its own button closes it. */
let openPicker = null

/**
 * The characters last picked, newest first. Kept in `localStorage` because they belong to
 * the person, not to the record: the same handful get used across every form in the panel.
 * A browser that refuses storage simply has no first tab worth showing.
 */
function readRecent(key) {
    try {
        const stored = JSON.parse(window.localStorage.getItem(key) ?? '[]')

        return Array.isArray(stored) ? stored.filter((entry) => typeof entry === 'string') : []
    } catch {
        return []
    }
}

function remember(key, value) {
    try {
        const recent = [value, ...readRecent(key).filter((entry) => entry !== value)].slice(0, RECENT_LIMIT)

        window.localStorage.setItem(key, JSON.stringify(recent))
    } catch {
        // Storage can be full or switched off. Losing the history is not worth an error.
    }
}

export function close() {
    if (!popup) {
        return
    }

    document.removeEventListener('pointerdown', onPointerDown, true)
    document.removeEventListener('keydown', onKeyDown, true)
    window.removeEventListener('resize', reposition)

    endDrag()

    popup.remove()
    popup = null
    openPicker = null
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

    position(preferredWidth)
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

function position(preferredWidth) {
    const width = Math.min(preferredWidth, window.innerWidth - 2 * MARGIN)

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
 * Matches every word of the query against the character's name, so "cat face" finds the
 * grinning cat and "cat" alone still finds the whole litter.
 */
export function matches(name, words) {
    return words.every((word) => name.includes(word))
}

/**
 * The rows a query finds, across every group, capped so that a single letter does not
 * paint the whole list.
 */
export function search(groups, query, limit = RESULT_LIMIT) {
    const words = String(query ?? '')
        .toLowerCase()
        .split(/\s+/)
        .filter(Boolean)

    if (!words.length) {
        return null
    }

    const found = []

    for (const [, entries] of groups) {
        for (const entry of entries) {
            if (matches(entry[1], words)) {
                found.push(entry)
            }

            if (found.length >= limit) {
                return found
            }
        }
    }

    return found
}

function build(editor, groups, options) {
    const { classes, labels, recentKey, width, onPick } = options

    const entriesOf = (key) => groups.find(([group]) => group === key)?.[1] ?? []

    // Recent picks are stored as bare characters, so the names come back out of the list.
    // The whole row is kept, not just the name: a recent pick has to come back with the
    // stand-in it is drawn by, or the non-breaking space would be a blank button again as
    // soon as somebody used it once.
    const known = new Map(groups.flatMap(([, entries]) => entries.map((entry) => [entry[0], entry])))
    const recentEntries = () =>
        readRecent(recentKey).map((value) => known.get(value) ?? [value, value])

    popup = document.createElement('div')
    popup.className = classes.popup
    popup.setAttribute('role', 'dialog')
    popup.setAttribute('aria-label', labels.label)

    const header = document.createElement('div')
    header.className = classes.header

    const title = document.createElement('span')
    title.className = classes.title
    title.textContent = labels.label

    const dismiss = document.createElement('button')
    dismiss.type = 'button'
    dismiss.className = classes.close
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

    const searchField = document.createElement('input')
    searchField.type = 'search'
    searchField.className = classes.search
    searchField.placeholder = labels.search
    searchField.setAttribute('aria-label', labels.search)

    const tabs = document.createElement('div')
    tabs.className = classes.tabs
    tabs.setAttribute('role', 'tablist')

    const grid = document.createElement('div')
    grid.className = classes.grid
    grid.setAttribute('role', 'listbox')

    const empty = document.createElement('p')
    empty.className = classes.empty
    empty.hidden = true

    const paint = (entries, emptyText) => {
        // One string beats a thousand appendChild calls, and every character in it comes
        // from a bundled list rather than from anything a user typed.
        grid.innerHTML = entries
            .map(
                // The third element, where a list gives one, is what the button draws when
                // the character itself would draw nothing - a non-breaking space is still
                // worth offering, and a blank button is one nobody can aim at.
                ([value, name, display]) =>
                    `<button type="button" tabindex="-1" role="option" class="${classes.item}" title="${name}" aria-label="${name}" data-glyph="${value}">${display ?? value}</button>`,
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
        tab.className = classes.tab
        tab.dataset.group = key
        tab.setAttribute('role', 'tab')
        tab.title = label
        tab.setAttribute('aria-label', label)
        // The icons are rendered by PHP through the package's icon registry, so a project
        // can swap them like every other icon - and so a row of coloured emoji does not
        // have to double as chrome.
        tab.innerHTML = icon

        tab.addEventListener('click', () => {
            searchField.value = ''
            paintTab(key)
        })

        tabs.append(tab)
    }

    searchField.addEventListener('input', () => {
        const found = search(groups, searchField.value)

        if (found === null) {
            paintTab(current)

            return
        }

        for (const tab of tabs.children) {
            tab.classList.remove('fi-active')
            tab.setAttribute('aria-selected', 'false')
        }

        paint(found, labels.empty)
    })

    grid.addEventListener('click', (event) => {
        const value = event.target.closest(`.${classes.item}`)?.dataset.glyph

        if (!value) {
            return
        }

        remember(recentKey, value)
        onPick(editor, value)

        // Staying open is the point, but the recent tab would then be showing a list it is
        // no longer telling the truth about.
        if (current === RECENT && !searchField.value) {
            paintTab(RECENT)
        }
    })

    popup.append(header, searchField, tabs, grid, empty)
    document.body.append(popup)

    // The recent tab is where a picker is usually meant to stop: the same few characters
    // get reached for again and again. It opens on the first real group until there are any.
    paintTab(
        readRecent(recentKey).length
            ? RECENT
            : labels.tabs.find((tab) => tab.key !== RECENT)?.key,
    )
    position(width)

    // After the click that opened it, or the picker would close on its own opening.
    setTimeout(() => {
        document.addEventListener('pointerdown', onPointerDown, true)
        document.addEventListener('keydown', onKeyDown, true)
        window.addEventListener('resize', reposition)

        searchField.focus()
    }, 0)
}

/**
 * Opens the picker, or closes the one that is open.
 *
 * `load` is awaited rather than required up front: the lists are tens of kilobytes and have
 * no business loading in an editor nobody clicks that button in.
 */
export async function open(editor, anchor, options) {
    const was = openPicker

    close()

    // A second click on the same button closes what the first one opened. A click on the
    // other picker's button swaps the popup instead of closing it: there is only one, and
    // the two would otherwise both claim the same corner of the screen.
    if (was === options.name) {
        return
    }

    if (!anchor) {
        return
    }

    // Read now, not later: the dropdown the button sits in hides itself on this very click.
    anchorElement = anchor
    editorElement = editor.view?.dom?.closest('.fi-fo-rich-editor') ?? editor.view?.dom ?? null
    anchorRect = caretRect(editor) ?? anchor.getBoundingClientRect()

    const groups = await options.load()

    if (!groups.length || popup) {
        return
    }

    preferredWidth = options.width
    openPicker = options.name

    build(editor, groups, options)
}

/**
 * A loader for a sibling data module, resolved against the calling module's own URL so the
 * two files stay together wherever Filament published them - and carrying the version query
 * over, so a package upgrade is not served from a stale cache.
 */
export function dataLoader(moduleUrl, file, description) {
    let promise = null

    return () => {
        if (!promise) {
            const here = new URL(moduleUrl)
            const url = new URL(file, here)

            url.search = here.search

            promise = import(url.href)
                .then((module) => module.default)
                .catch((error) => {
                    console.error(`The advanced rich editor could not load its ${description}:`, error)

                    promise = null

                    return []
                })
        }

        return promise
    }
}
