/*
 * The special characters picker.
 *
 * The twin of the emoji one, and for the same reason nothing here touches the schema: a
 * dash, a currency sign and a Greek letter are characters, so the picker inserts plain text
 * and it travels through the sanitiser, the save and the server side renderer like any
 * other letter.
 *
 * The popup is `glyph-picker.js`, shared with the emoji picker: the two do the same thing
 * to the same kind of thing, and what differs is the list, the strings, the storage key and
 * the class names. Those are what this file supplies. There is one popup between them, so
 * opening this one takes that one away - they claim the same corner of the screen.
 *
 * The list is a sibling module, fetched the first time the picker opens.
 */

/**
 * Written out rather than built from a prefix: `PublishedAssetsTest` reads these names out
 * of the source to check that every one of them has a rule in the stylesheet, and a name
 * assembled from a variable is a name it can no longer see.
 */
const CLASSES = {
    popup: 'fi-arte-characters-popup',
    header: 'fi-arte-characters-header',
    title: 'fi-arte-characters-title',
    close: 'fi-arte-characters-close',
    search: 'fi-arte-characters-search',
    tabs: 'fi-arte-characters-tabs',
    tab: 'fi-arte-characters-tab',
    grid: 'fi-arte-characters-grid',
    empty: 'fi-arte-characters-empty',
    item: 'fi-arte-characters-item',
}

/**
 * Wider than the emoji picker by two columns. These are letters rather than pictures, so
 * they are drawn at text size and eight of them fit where six emoji did.
 */
const WIDTH = 24 * 16

const RECENT_KEY = 'arte-characters-recent'

let shell = null
let shellPromise = null

function loadShell() {
    if (!shellPromise) {
        const here = new URL(import.meta.url)
        const url = new URL('./glyph-picker.js', here)

        url.search = here.search

        shellPromise = import(url.href)
            .then((module) => (shell = module))
            .catch((error) => {
                console.error('The advanced rich editor could not load its glyph picker:', error)

                shellPromise = null

                return null
            })
    }

    return shellPromise
}

let load = null

function loadCharacters() {
    if (!load) {
        load = shell.dataLoader(import.meta.url, './character-data.js', 'special characters list')
    }

    return load()
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor characters extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap

    // Warmed as soon as the extension loads, so the first press of the button opens rather
    // than waits.
    loadShell()

    return Extension.create({
        name: 'arteCharacters',

        addCommands() {
            return {
                insertCharacter:
                    (character) =>
                    ({ commands }) =>
                        commands.insertContent(character),

                openCharacterPicker:
                    (anchor, labels) =>
                    ({ editor }) => {
                        loadShell().then(() =>
                            shell?.open(editor, anchor, {
                                name: 'characters',
                                classes: CLASSES,
                                labels,
                                recentKey: RECENT_KEY,
                                width: WIDTH,
                                load: loadCharacters,
                                onPick: (target, character) =>
                                    target.chain().focus().insertCharacter(character).run(),
                            }),
                        )

                        return true
                    },
            }
        },
    })
}
