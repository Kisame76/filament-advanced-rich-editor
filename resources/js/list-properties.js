/*
 * What a list is told about itself: which marker it draws, where it starts counting, and
 * whether it counts backwards.
 *
 * All three ride in the attributes HTML already has - `type`, `start` and `reversed` - and
 * all three are on Symfony's safe attribute list, so a list keeps its numbering on the
 * rendered page with nothing added to the application's sanitiser and no stylesheet at all.
 *
 * This half adds LESS than the PHP one, and the asymmetry is deliberate rather than an
 * oversight: TipTap's bundled `orderedList` already declares `start` and `type`, so
 * declaring them again would be two definitions of one attribute with one of them silently
 * winning. What is missing here is `reversed` on the ordered list and `type` on the bullet
 * list, and that is exactly what this adds. `ListProperties` in PHP adds what the PHP
 * nodes are missing, which is a different set.
 *
 * Registered as global attributes rather than by redefining the two nodes: TipTap merges
 * them into the schema of the types they name, so Filament's own list nodes keep their
 * definitions.
 */

/** Mirrors `ListProperties::ORDERED` and `::BULLET` in PHP. Both halves have to agree. */
const TYPES = {
    orderedList: ['1', 'a', 'A', 'i', 'I'],
    bulletList: ['disc', 'circle', 'square'],
}

const MAX_START = 100000

/**
 * What each marker is called in CSS. Mirrors `ListProperties::CSS` in PHP.
 *
 * The attribute alone is not enough, and the live editor is where that shows: Filament's
 * prose styles set `list-style-type` on every list, and a stylesheet beats a presentational
 * attribute. An inline style beats the stylesheet, so the marker is written twice - as the
 * attribute, which is what both halves parse and what a bare browser honours, and as the
 * CSS, which is what survives somebody else's.
 *
 * The same reasoning the embed wrapper carries its aspect ratio inline for: the page a
 * document ends up on is not this package's, and `style` is what travels there.
 */
const CSS = {
    1: 'decimal',
    a: 'lower-alpha',
    A: 'upper-alpha',
    i: 'lower-roman',
    I: 'upper-roman',
    disc: 'disc',
    circle: 'circle',
    square: 'square',
}

/** The `style` a marker renders as, or nothing. */
function markerStyle(value, list) {
    const type = listType(value, list)

    return type ? { style: `list-style-type: ${CSS[type]};` } : {}
}

/**
 * Case is kept rather than lowered, which is the one place this package does not fold it:
 * `a` and `A` are different alphabets and `i` and `I` are different numerals.
 */
export function listType(value, listType) {
    const type = String(value ?? '').trim()

    return (TYPES[listType] ?? []).includes(type) ? type : null
}

/**
 * The floating toolbars `ToolbarListPanel` lives in, one per kind of list. Mirrors
 * `AdvancedRichEditor::getDefaultFloatingToolbars()`: Filament names a bubble menu's plugin
 * after the node it is keyed on, and that name is what a message to it is addressed to.
 */
export const TOOLBARS = ['bulletList', 'orderedList']

/**
 * Whether a toolbar is holding the interaction the editor has just lost.
 *
 * Two answers rather than one, because focus alone does not cover the moment that matters.
 * A click on a button ends with that button focused, and asking where the focus is finds
 * it. A blur does not: the number input commits what was typed into it on the way out, and
 * during a blur the focus has left the input and not yet arrived anywhere, so the document
 * reports its body. What still says the interaction is inside the panel then is the pointer
 * that caused the blur - the press that is on its way to the checkbox next to it.
 */
export function holdsInteraction(element, pressed = null) {
    if (! element) {
        return false
    }

    const focused = element.ownerDocument?.activeElement ?? null

    return (
        (focused !== null && element.contains(focused)) ||
        (pressed !== null && element.contains(pressed))
    )
}

/**
 * Filament's rule for showing a floating toolbar, widened by the one case a panel has and a
 * bar of buttons does not.
 *
 * Filament shows a floating toolbar while `editor.isFocused && editor.isActive(<its key>)`.
 * That is right for a row of buttons that never take the focus and wrong for one holding a
 * panel: picking a marker focuses the button it landed on, so the transaction that same
 * click dispatches arrives with the editor unfocused, and the bubble menu answers by
 * REMOVING its element from the DOM - which takes the panel, and the Alpine state saying it
 * was open, with it. The window shuts under the pointer that opened it.
 *
 * So the toolbar also counts as wanted while the panel inside it is what has the focus. The
 * image toolbar widens its rule for exactly this reason and has done since the size inputs
 * arrived; these two never got the same treatment.
 *
 * The first clause is Filament's own, and is kept rather than dropped: a list item holds a
 * paragraph, so somebody who has selected words inside one wants the bar that formats them
 * and not the one that renumbers the list around them.
 */
export function listToolbarVisibility(
    nodeName,
    { hasTextToolbar = false, pressed = () => null } = {},
) {
    return ({ editor, element }) => {
        if (hasTextToolbar && ! editor.state.selection.empty && editor.isActive('paragraph')) {
            return false
        }

        return (
            editor.isActive(nodeName) &&
            (editor.isFocused || holdsInteraction(element, pressed()))
        )
    }
}

/**
 * `1` comes back as null: writing `start="1"` on every list would put an attribute into
 * every document saying exactly what its absence says.
 */
export function listStart(value) {
    const start = Number.parseInt(String(value ?? '').trim(), 10)

    return Number.isFinite(start) && start > 1 && start <= MAX_START ? start : null
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor list properties extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap

    return Extension.create({
        name: 'arteListProperties',

        addStorage() {
            // What the pointer is currently pressed on, and the way to stop watching for it.
            return { pressed: null, release: null }
        },

        /**
         * Widens the visibility rule of the two toolbars the list panel lives in.
         *
         * Done through the bubble menu plugin's own `updateOptions` message rather than by
         * touching Filament's component, and only for these two - the same route the image
         * toolbar takes for the same reason.
         *
         * `onCreate` rather than the extension's constructor because Filament registers the
         * bubble menu plugins after `new Editor(...)` returns, and TipTap emits `create` a
         * task later - so by the time this runs there is something to address the message
         * to. A message to a plugin key nothing answers to is inert, which is what makes it
         * safe to send both without asking whether the panel was configured.
         */
        onCreate() {
            const { editor } = this
            const storage = this.storage
            const root = editor.view.dom.parentElement
            const owner = editor.view.dom.ownerDocument

            const press = (event) => {
                storage.pressed = event.target instanceof Node ? event.target : null
            }

            // Let go of once the click is over. A flag left standing would hold a bar open
            // long after the press that set it, and every later question would answer with
            // where the pointer last happened to be.
            const release = () => {
                storage.pressed = null
            }

            owner.addEventListener('pointerdown', press, true)
            owner.addEventListener('pointerup', release, true)
            owner.addEventListener('pointercancel', release, true)

            storage.release = () => {
                owner.removeEventListener('pointerdown', press, true)
                owner.removeEventListener('pointerup', release, true)
                owner.removeEventListener('pointercancel', release, true)
            }

            // Filament only registers the text toolbar where the field was given one, and
            // its rule for every other bubble stands down for it. Asked of the markup, which
            // is where the answer is: the toolbars are declared inside the element TipTap
            // mounts into, and Alpine leaves `x-ref` on them.
            const hasTextToolbar =
                root?.querySelector('[x-ref="floatingToolbar::paragraph"]') != null

            editor.view.dispatch(
                TOOLBARS.reduce(
                    (transaction, key) =>
                        transaction.setMeta(`floatingToolbar::${key}`, {
                            type: 'updateOptions',
                            options: {
                                shouldShow: listToolbarVisibility(key, {
                                    hasTextToolbar,
                                    pressed: () => storage.pressed,
                                }),
                            },
                        }),
                    editor.state.tr.setMeta('addToHistory', false),
                ),
            )
        },

        onDestroy() {
            this.storage.release?.()
        },

        addGlobalAttributes() {
            return [
                {
                    types: ['bulletList'],
                    attributes: {
                        type: {
                            default: null,
                            parseHTML: (element) =>
                                listType(element.getAttribute('type'), 'bulletList'),
                            renderHTML: (attributes) => {
                                const type = listType(attributes.type, 'bulletList')

                                return type
                                    ? { type, ...markerStyle(type, 'bulletList') }
                                    : {}
                            },
                        },
                    },
                },
                {
                    types: ['orderedList'],
                    attributes: {
                        /**
                         * Not an attribute of the document: it stores nothing, parses to
                         * nothing, and exists to render the CSS for the `type` that
                         * TipTap's own `orderedList` already declares.
                         *
                         * The bullet list gets the same thing for free, because `type`
                         * there is this extension's own attribute and it can render both.
                         * Ordered lists cannot: declaring `type` a second time would be two
                         * definitions of one attribute with one of them silently winning.
                         * A global attribute's `renderHTML` is handed the node's whole
                         * attribute set, so this one can read that `type` without owning it.
                         */
                        arteMarker: {
                            default: null,
                            parseHTML: () => null,
                            renderHTML: (attributes) =>
                                markerStyle(attributes.type, 'orderedList'),
                        },
                        reversed: {
                            default: null,
                            // A boolean attribute: what a browser reads is whether it is
                            // there, not what it says. `reversed="false"` is refused all
                            // the same - nothing writes it on purpose, and reading it as
                            // true would mean disagreeing with whoever typed it.
                            parseHTML: (element) =>
                                element.hasAttribute('reversed') &&
                                element.getAttribute('reversed')?.toLowerCase().trim() !== 'false'
                                    ? true
                                    : null,
                            // The attribute's own name as its value, which is how a boolean
                            // attribute is written in XHTML and is read as present by every
                            // parser, this package's PHP one included.
                            renderHTML: (attributes) =>
                                attributes.reversed === true ? { reversed: 'reversed' } : {},
                        },
                    },
                },
            ]
        },

        addCommands() {
            /**
             * The list the caret is in, as its type name, or null. Asked of the editor
             * rather than walked by hand, so a list inside a callout, a quote or another
             * list is found the same way.
             */
            const activeList = (editor) =>
                ['bulletList', 'orderedList'].find((type) => editor.isActive(type)) ?? null

            return {
                setListType:
                    (type) =>
                    ({ editor, commands }) => {
                        const list = activeList(editor)
                        const value = list ? listType(type, list) : null

                        return value === null
                            ? false
                            : commands.updateAttributes(list, { type: value })
                    },

                unsetListType:
                    () =>
                    ({ editor, commands }) => {
                        const list = activeList(editor)

                        return list ? commands.updateAttributes(list, { type: null }) : false
                    },

                setListStart:
                    (start) =>
                    ({ editor, commands }) =>
                        editor.isActive('orderedList')
                            // Back to `1` rather than to null: TipTap's own `orderedList`
                            // defaults the attribute to 1 and writes nothing for it, so 1
                            // is how "from the beginning" is spelled in that schema.
                            ? commands.updateAttributes('orderedList', {
                                  start: listStart(start) ?? 1,
                              })
                            : false,

                setListReversed:
                    (reversed) =>
                    ({ editor, commands }) =>
                        editor.isActive('orderedList')
                            ? commands.updateAttributes('orderedList', {
                                  reversed: reversed === true ? true : null,
                              })
                            : false,

                toggleListReversed:
                    () =>
                    ({ editor, commands }) =>
                        commands.setListReversed(
                            editor.getAttributes('orderedList')?.reversed !== true,
                        ),
            }
        },
    })
}
