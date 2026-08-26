/*
 * The emoji picker.
 *
 * An emoji is a Unicode character, so nothing here touches the schema: the picker inserts
 * plain text and the character travels through the sanitiser, the save and the server side
 * renderer like any other letter. That is the whole reason this is an extension with two
 * commands rather than a node with a parser.
 *
 * The popup itself is `glyph-picker.js`, shared with the special characters picker: the two
 * do the same thing to the same kind of thing, and only the list, the strings, the storage
 * key and the class names differ. Those are what this file supplies.
 *
 * The popup is not a toolbar component of this package's own because the tool has to
 * survive being put inside a dropdown, where Filament renders button names and nothing
 * else. So the button asks for a popup, and the shell owns it.
 *
 * The list is a sibling module, fetched the first time the picker opens - 60 KB of emoji
 * has no business loading in an editor nobody clicks that button in. The tabs, their labels
 * and their icons come the other way, from PHP, so they follow the locale and the package's
 * icon registry.
 */

/**
 * Written out rather than built from a prefix: `PublishedAssetsTest` reads these names out
 * of the source to check that every one of them has a rule in the stylesheet, and a name
 * assembled from a variable is a name it can no longer see.
 */
const CLASSES = {
    popup: 'fi-arte-emoji-popup',
    header: 'fi-arte-emoji-header',
    title: 'fi-arte-emoji-title',
    close: 'fi-arte-emoji-close',
    search: 'fi-arte-emoji-search',
    tabs: 'fi-arte-emoji-tabs',
    tab: 'fi-arte-emoji-tab',
    grid: 'fi-arte-emoji-grid',
    empty: 'fi-arte-emoji-empty',
    item: 'fi-arte-emoji-item',
}

const WIDTH = 22 * 16

const RECENT_KEY = 'arte-emoji-recent'

let shell = null
let shellPromise = null

/**
 * The shell, loaded once per page. The URL is derived from this module's own so the two
 * files stay together wherever Filament published them, and the version query is carried
 * over so a package upgrade is not served from a stale cache.
 */
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

function loadEmojis() {
    if (!load) {
        load = shell.dataLoader(import.meta.url, './emoji-data.js', 'emoji list')
    }

    return load()
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

    // Warmed as soon as the extension loads, so the first press of the button opens rather
    // than waits. The list itself is not: that one is the 60 KB.
    loadShell()

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
                        loadShell().then(() =>
                            shell?.open(editor, anchor, {
                                name: 'emoji',
                                classes: CLASSES,
                                labels,
                                recentKey: RECENT_KEY,
                                width: WIDTH,
                                load: loadEmojis,
                                onPick: (target, emoji) =>
                                    target.chain().focus().insertEmoji(emoji).run(),
                            }),
                        )

                        return true
                    },
            }
        },
    })
}
