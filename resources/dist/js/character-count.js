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
function escape(text) {
    return text.replace(/[&<>"']/g, (character) => ESCAPES[character])
}

function serialize(node, isEscaped) {
    if (node.content?.length) {
        return node.content.map((child) => serialize(child, isEscaped)).join(BLOCK_SEPARATOR)
    }

    if (typeof node.text === 'string') {
        return isEscaped ? escape(node.text) : node.text
    }

    return ''
}

function textOf(document, isEscaped) {
    return (document.content ?? [])
        .map((node) => serialize(node, isEscaped))
        .join(BLOCK_SEPARATOR)
}

function count(editor) {
    const document = editor.getJSON()

    return {
        // Code points, like PHP's `mb_strlen`: an emoji is one character in both.
        characters: Array.from(textOf(document, true)).length,
        words: textOf(document, false).split(/\s+/).filter(Boolean).length,
    }
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

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
    })
}
