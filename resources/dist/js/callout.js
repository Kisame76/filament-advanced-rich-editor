/**
 * Callouts, on the editor's side.
 *
 * A note, a tip, a warning or a danger box. One node with the kind on it rather than four
 * nodes: changing a note into a warning is a change of colour, not a rewrite, and a
 * document that already holds one keeps working when a project renames what it offers.
 *
 * This package ships no bundler, so the file must stay free of `import` statements:
 * Filament loads it verbatim through a dynamic `import()` in
 * `filament/forms/resources/js/components/rich-editor/extensions.js`. TipTap is therefore
 * read from the global that Filament's own bundle publishes
 * (`window.FilamentRichEditor.tiptap`). Sharing that instance is not a size optimisation --
 * ProseMirror leans on `instanceof` checks, so a second copy of the library would silently
 * fail to interoperate.
 *
 * The markup mirrors `RichEditor\Nodes\Callout` in PHP, class for class, so a document
 * written here and a document rendered there parse back into the same thing and are drawn
 * by the same stylesheet.
 */

/**
 * A variant may name a class and a JavaScript identifier. Mirrors `Callouts::NAME` in PHP;
 * both halves have to agree, because one of them writes what the other reads.
 */
const NAME = /^[a-z][a-z0-9-]*$/

const DEFAULT_VARIANT = 'note'

const CLASS_PREFIX = 'fi-arte-callout-'

export function calloutVariant(value) {
    const name = String(value ?? '')
        .trim()
        .toLowerCase()

    return NAME.test(name) ? name : null
}

/**
 * The variant a wrapper's class list says it is, or null. The kind rides in a class
 * because that is one of the two attributes Filament's sanitiser keeps on a rendered
 * page -- `data-type` says it is a callout, the class says which.
 */
export function variantFromClassList(classes) {
    for (const name of String(classes ?? '').split(/\s+/)) {
        if (!name.startsWith(CLASS_PREFIX)) {
            continue
        }

        const variant = calloutVariant(name.slice(CLASS_PREFIX.length))

        if (variant) {
            return variant
        }
    }

    return null
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor callout extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Node, findParentNode, mergeAttributes, wrappingInputRule } = tiptap

    return Node.create({
        name: 'callout',

        group: 'block',

        /**
         * Blocks rather than inline content: a callout that cannot hold a list or a second
         * paragraph is a coloured sentence, not an infobox.
         */
        content: 'block+',

        /**
         * The same reason TipTap's own blockquote is defining: pasting over everything
         * inside a callout should leave the callout standing rather than replace it.
         */
        defining: true,

        addOptions() {
            return {
                HTMLAttributes: {},
                defaultVariant: DEFAULT_VARIANT,
            }
        },

        addAttributes() {
            return {
                variant: {
                    default: DEFAULT_VARIANT,
                    parseHTML: (element) =>
                        variantFromClassList(element.getAttribute('class')) ??
                        this.options.defaultVariant,
                    // Already written into the class below. Without this TipTap would put
                    // it on the wrapper a second time as a bare `variant="note"`, which is
                    // not an attribute HTML has and not one the sanitiser keeps.
                    renderHTML: () => ({}),
                },
            }
        },

        parseHTML() {
            return [
                {
                    // Above the default 50: `data-type` carries task lists, grids and
                    // custom blocks as well, so a rule claiming plain `div` must not get
                    // there first.
                    tag: 'div[data-type="callout"]',
                    priority: 51,
                },
            ]
        },

        renderHTML({ node, HTMLAttributes }) {
            const variant = calloutVariant(node.attrs.variant) ?? this.options.defaultVariant

            return [
                'div',
                mergeAttributes(this.options.HTMLAttributes, HTMLAttributes, {
                    class: `fi-arte-callout ${CLASS_PREFIX}${variant}`,
                    'data-type': this.name,
                }),
                0,
            ]
        },

        addCommands() {
            /*
             * `wrapIn` and `updateAttributes` are core commands. Evidence, taken from the
             * prebuilt bundle `filament/forms/dist/components/rich-editor.js`: the core
             * command namespace is assembled as
             * `Al={};Tl(Al,{...,updateAttributes:()=>T0,...,wrapIn:()=>A0,...})` and
             * registered wholesale by
             * `U.create({name:"commands",addCommands(){return{...Al}}})`.
             */
            return {
                /**
                 * Puts a box around what the selection is in.
                 *
                 * `wrapIn` answers the ordinary case - a paragraph, a heading, a run of
                 * blocks - and it answers it better than anything written here would,
                 * because ProseMirror works the wrapping out from the schema.
                 *
                 * It answers nothing at all inside a list, though: a list item has to
                 * begin with a paragraph, so a callout cannot go where the caret is, and
                 * the button quietly does nothing. What was meant there is the list, so
                 * the fallback climbs to the first level whose parent will take a callout
                 * and puts the box around that whole node instead.
                 */
                setCallout:
                    (variant) =>
                    ({ state, tr, dispatch, commands }) => {
                        const attributes = {
                            variant: calloutVariant(variant) ?? this.options.defaultVariant,
                        }

                        if (commands.wrapIn(this.name, attributes)) {
                            return true
                        }

                        const { $from, $to } = state.selection

                        for (let depth = $from.sharedDepth($to.pos); depth > 0; depth--) {
                            const parent = $from.node(depth - 1)

                            if (
                                !parent.canReplaceWith(
                                    $from.index(depth - 1),
                                    $to.indexAfter(depth - 1),
                                    this.type,
                                )
                            ) {
                                continue
                            }

                            const start = $from.before(depth)
                            const end = $to.after(depth)
                            const content = state.doc.slice(start, end).content

                            if (!this.type.validContent(content)) {
                                continue
                            }

                            if (dispatch) {
                                tr.replaceWith(
                                    start,
                                    end,
                                    this.type.create(attributes, content),
                                ).scrollIntoView()
                            }

                            return true
                        }

                        return false
                    },

                /**
                 * Takes the box off and leaves everything that was in it where it was.
                 *
                 * Not `lift`, which is one level rather than this level: with the caret in
                 * a list inside a callout it lifts the list item out of the list and leaves
                 * the box standing, so pressing the lit-up button un-bullets somebody's
                 * list. Replacing the node with its own content is the whole operation and
                 * says so, whatever is inside.
                 */
                unsetCallout:
                    () =>
                    ({ state, tr, dispatch }) => {
                        const found = findParentNode((node) => node.type === this.type)(
                            state.selection,
                        )

                        if (!found) {
                            return false
                        }

                        if (dispatch) {
                            tr.replaceWith(
                                found.pos,
                                found.pos + found.node.nodeSize,
                                found.node.content,
                            ).scrollIntoView()
                        }

                        return true
                    },

                /**
                 * The same kind again takes the box off, which is what a toolbar button
                 * that lights up has to do when it is pressed a second time. A different
                 * kind repaints the box rather than nesting a second one inside it.
                 */
                toggleCallout:
                    (variant) =>
                    ({ editor, commands }) => {
                        const value = calloutVariant(variant) ?? this.options.defaultVariant

                        if (!editor.isActive(this.name)) {
                            return commands.setCallout(value)
                        }

                        return editor.isActive(this.name, { variant: value })
                            ? commands.unsetCallout()
                            : commands.updateAttributes(this.name, { variant: value })
                    },
            }
        },

        addInputRules() {
            return [
                /*
                 * `:::warning ` at the start of a line, the spelling every documentation
                 * generator from MkDocs to Docusaurus uses for the same thing.
                 *
                 * The kind is taken from what was typed rather than checked against the
                 * field's list: this half is loaded as a plain file and is never told what
                 * the toolbar offers, and a variant nobody configured is drawn as the
                 * neutral box rather than lost. What a field offers governs its buttons and
                 * its slash menu, which is where a reader is choosing from a list.
                 */
                wrappingInputRule({
                    find: /^:::([a-z][a-z0-9-]*)[\s\n]$/,
                    type: this.type,
                    getAttributes: (match) => ({
                        variant: calloutVariant(match[1]) ?? this.options.defaultVariant,
                    }),
                }),
            ]
        },
    })
}
