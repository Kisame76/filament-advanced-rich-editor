/*
 * The counting half of the character counter.
 *
 * It counts nothing of its own invention: the numbers have to be the ones Filament rejects
 * a save over, or the line under the editor is worse than no line at all. Filament measures
 * `maxLength` with `Str::length($editor->getText())` on the PHP side, and that serialiser
 * (`Tiptap\Core\TextSerializer`) does two things a reader would not expect - it escapes the
 * text the way HTML wants it, so a single `&` counts as the five characters of `&amp;`, and
 * it joins EVERY nesting level with a blank line, not only the top level blocks. A list
 * item inside a list therefore costs two separators, not one.
 *
 * Both quirks are mirrored below. Reading the rendered text instead would be simpler and
 * would show a smaller number than the one that decides whether the record saves.
 *
 * Words are counted on the text as written, without the escaping: nobody means `&amp;` when
 * they count words.
 */

const BLOCK_SEPARATOR = '\n\n'

const ESCAPES = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
}

// `htmlspecialchars($text, ENT_QUOTES, 'UTF-8')`, which is what the PHP serialiser applies.
function escapeHtml(text) {
    return text.replace(/[&<>"']/g, (character) => ESCAPES[character])
}

function serialize(node, isEscaped) {
    if (node.content?.length) {
        return node.content.map((child) => serialize(child, isEscaped)).join(BLOCK_SEPARATOR)
    }

    if (typeof node.text === 'string') {
        return isEscaped ? escapeHtml(node.text) : node.text
    }

    return ''
}

function textOf(document, isEscaped) {
    return (document.content ?? [])
        .map((node) => serialize(node, isEscaped))
        .join(BLOCK_SEPARATOR)
}

/**
 * How long a document is in characters, and nothing else.
 *
 * Apart from `countDocument()` because the limit below only ever asks this one number,
 * while asking for both walks the document a second time to build a word count nobody
 * reads. That second walk is free on a paragraph and milliseconds on a long article, and
 * the limit asks on every transaction the editor sees.
 */
export function charactersIn(document) {
    // Code points, like PHP's `mb_strlen`: an emoji is one character in both.
    return Array.from(textOf(document, true)).length
}

export function countDocument(document) {
    return {
        characters: charactersIn(document),
        words: textOf(document, false).split(/\s+/).filter(Boolean).length,
    }
}

function count(editor) {
    return countDocument(editor.getJSON())
}

/**
 * Whether these steps are one replacement of the whole document.
 *
 * That is the shape `setContent` makes, and nothing a person does at the keyboard makes it:
 * typing, pasting and deleting all replace a range inside the document, never the document
 * from end to end in a single step.
 *
 * It matters because the limit below has to let those through. A document saved before the
 * limit existed, a draft restored from the browser's storage, the source dialog handing
 * back edited markup - each arrives this way, and a filter that measured only the size
 * would refuse to load any of them.
 */
export function replacesWholeDocument(steps, sizeBefore) {
    return steps.length === 1 && steps[0].from === 0 && steps[0].to === sizeBefore
}

/**
 * What a key belonging to ProseMirror's history plugin looks like.
 *
 * A prefix rather than a constant, because `PluginKey` builds its key by appending `$` to
 * the plugin's name and a counter after that: the first key named `history` becomes
 * `history$`, a second `history$1`.
 */
const HISTORY_KEY = /^history\$/

/**
 * Whether this transaction is ProseMirror's history putting something back, which is the
 * only way to tell an undo from somebody typing the same text.
 *
 * Asked of the transaction and the editor's own plugins rather than of a key written down
 * here, and asked of *every* key that looks like history rather than the first one found.
 * Both halves matter. A literal `history$` stops matching the day something else registers
 * that name first; and picking the first matching key is the same bug one step along,
 * because it is whichever plugin registered the name first that owns the bare `history$` -
 * so the impostor is exactly the one that gets picked, and the real history is never asked.
 *
 * Asking all of them removes the choice: the key that marked this transaction is the one
 * that put the content back, whatever counter its name ended up with.
 */
export function isHistoryTransaction(transaction, plugins) {
    return (plugins ?? []).some(
        (plugin) => HISTORY_KEY.test(plugin?.key ?? '') && transaction.getMeta(plugin.key) !== undefined,
    )
}

/**
 * The plugins this extension adds for one editor: one, or none.
 *
 * Split out so the wiring can be tested at all - which limit was read off the element, and
 * how an undo is recognised - rather than only the rule it ends up calling.
 */
export function limitPluginsFor(editor, pmState) {
    const limit = limitFrom(editor?.options?.element?.dataset?.arteCharacterCount)

    // Nothing to hold, so nothing is registered: a field that only shows the number pays
    // for no filter at all.
    if (!limit || !pmState) {
        return []
    }

    const { Plugin, PluginKey } = pmState

    return [
        new Plugin({
            key: new PluginKey('arteCharacterCountLimit'),
            filterTransaction: (transaction, state) => allowsTransaction({
                limit,
                changed: transaction.docChanged,
                after: () => charactersIn(transaction.doc.toJSON()),
                before: () => charactersIn(state.doc.toJSON()),
                replacesWholeDocument: replacesWholeDocument(
                    transaction.steps,
                    state.doc.content.size,
                ),
                isHistory: () => isHistoryTransaction(transaction, state.plugins),
            }),
        }),
    ]
}

/**
 * Whether the editor lets a transaction through once a field holds its limit.
 *
 * Five ways to be allowed, and three of them are what keep the rule from turning into a
 * trap rather than a limit:
 *
 *   - a document arriving whole is never refused, or one saved before the limit existed
 *     could not be opened;
 *   - an undo is never refused, or a deletion made in a document that was already too long
 *     cannot be taken back;
 *   - a document already over the limit stays editable as long as it is not growing.
 *
 * `isHistory`, `after` and `before` are callbacks and not values on purpose, and the branches
 * are ordered cheapest first. This runs on every transaction the editor sees, and most of
 * them are a caret moving - which changes no text and is answered by the first line without
 * measuring anything at all. Measuring means walking the whole document and building a
 * string from it: on a long one that is milliseconds, paid per arrow key, for an answer no
 * branch reads. `before` costs the same again and is needed only in the last branch, which
 * is reached only once the result is already too long.
 */
export function allowsTransaction({ limit, changed, before, after, replacesWholeDocument: isWhole, isHistory }) {
    if (!limit || !changed || isWhole || isHistory()) {
        return true
    }

    const grown = after()

    if (grown <= limit) {
        return true
    }

    // Over the limit, so the only remaining question is whether this made it worse.
    return grown <= before()
}

/**
 * The limit this field holds, or null where it holds none.
 *
 * Read off the element rather than handed in, the way every other extension in this package
 * reads its settings: the module is loaded once per page and mounted per field, so the
 * field is the only thing that can answer.
 */
export function limitFrom(raw) {
    if (!raw) {
        return null
    }

    try {
        const settings = JSON.parse(raw)

        const limit = Number(settings?.limit)

        // Converted here rather than trusted: this is the boundary between the page and the
        // rule, and a limit that arrived as a string would be compared to a number by
        // coercion - which works until the day it does not.
        return settings?.enforce && limit > 0 ? limit : null
    } catch (error) {
        console.error('The advanced rich editor could not read its character count settings:', error)

        return null
    }
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core
    const pmState = window.FilamentRichEditor?.tiptap?.pmState

    if (!tiptap) {
        console.error(
            'The advanced rich editor character count extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap

    // Announced rather than polled, and from the editor's own element so that a page with
    // two editors keeps their numbers apart: the line under each one listens for the event
    // that came out of the field it sits in.
    const announce = (editor) => {
        editor.view.dom.dispatchEvent(
            new CustomEvent('arte-character-count', {
                bubbles: true,
                detail: count(editor),
            }),
        )
    }

    return Extension.create({
        name: 'arteCharacterCount',

        onCreate() {
            announce(this.editor)
        },

        onUpdate() {
            announce(this.editor)
        },

        addProseMirrorPlugins() {
            return limitPluginsFor(this.editor, pmState)
        },
    })
}
