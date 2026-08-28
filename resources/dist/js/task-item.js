/**
 * TipTap `taskItem` node for the Filament rich editor.
 *
 * See `task-list.js` for why this file contains no `import` statements and
 * exports a factory rather than an instance.
 *
 * Command availability was verified against the prebuilt bundle
 * `filament/forms/dist/components/rich-editor.js`:
 *
 * - The core command namespace is built as
 *   `Al={};Tl(Al,{blur:()=>...,liftListItem:()=>mb,sinkListItem:()=>Fb,splitListItem:()=>Vb,toggleList:()=>jb,...,wrapInList:()=>e0})`
 *   and registered globally by `U.create({name:"commands",addCommands(){return{...Al}}})`.
 *   So `splitListItem`, `sinkListItem` and `liftListItem` can be called through
 *   `this.editor.commands.*` -- no hand-rolled ProseMirror transforms required.
 * - The bundle does contain compiled `taskList`/`taskItem` extensions from
 *   `@tiptap/extension-list` (`V.create({name:"taskItem",...})`, `V.create({name:"taskList",...})`),
 *   but `extensions.js` only registers `BulletList`, `ListItem` and `OrderedList`
 *   from that package, and `window.FilamentRichEditor.tiptap` exposes only
 *   `core`/`pmState`/`pmView`/`pmModel`. The nodes are therefore unreachable and
 *   have to be re-implemented here.
 * - The `listKeymap` extension (`U.create({name:"listKeymap",...})`) is likewise
 *   part of `ListKit` only and is not registered, which is why the Backspace
 *   behaviour below is implemented in this file instead of being inherited.
 *
 * Re-check the three points above after every Filament upgrade.
 */
export default () => {
    const { Node, mergeAttributes, wrappingInputRule } =
        window.FilamentRichEditor.tiptap.core

    // Turns a leading `[] ` or `[x] ` into a task item, the same shorthand the
    // upstream TipTap extension supports.
    const inputRegex = /^\s*(\[([( |x])?\])\s$/

    return Node.create({
        name: 'taskItem',

        addOptions() {
            return {
                HTMLAttributes: {},
                taskListTypeName: 'taskList',
            }
        },

        // `block*` after the paragraph is what allows a nested task list, and
        // therefore `sinkListItem()`, to work at all.
        content: 'paragraph block*',

        defining: true,

        addAttributes() {
            return {
                checked: {
                    default: false,

                    // Splitting a ticked item with Enter must produce an
                    // unticked one, not a second ticked one.
                    keepOnSplit: false,

                    parseHTML: (element) => {
                        const value = element.getAttribute('data-checked')

                        // `Tiptap\Utils\HTML::renderAttributes()` casts booleans
                        // to the strings "true"/"false"; a bare `data-checked`
                        // attribute is treated as checked for hand-written HTML.
                        return value === '' || value === 'true'
                    },

                    renderHTML: (attributes) => ({
                        'data-checked': attributes.checked,
                    }),
                },
            }
        },

        parseHTML() {
            return [
                {
                    // Outranks the bundled list item, which claims plain `li` at
                    // the default priority of 50.
                    tag: `li[data-type="${this.name}"]`,
                    priority: 51,
                },
            ]
        },

        renderHTML({ node, HTMLAttributes }) {
            // Structurally identical to the package's PHP task item node, so markup
            // produced on either side round-trips through `parseHTML()` above and is
            // styled by the same stylesheet.
            //
            // Deliberately no `<input type="checkbox">`: Filament sanitises rich
            // content before rendering it on a page and its sanitiser drops `input`
            // elements as well as the `data-checked` attribute, which would take the
            // tick state with them. `class` survives sanitisation, so the state is
            // carried by a modifier class and the box is drawn in CSS. The editor
            // itself uses the node view below, which does render a real checkbox.
            return [
                'li',
                mergeAttributes(this.options.HTMLAttributes, HTMLAttributes, {
                    'data-type': this.name,
                    class: node.attrs.checked
                        ? 'fi-arte-task-item fi-arte-task-item-checked'
                        : 'fi-arte-task-item',
                }),
                [
                    'label',
                    { class: 'fi-arte-task-item-control' },
                    ['span', { class: 'fi-arte-task-item-box' }],
                ],
                ['div', { class: 'fi-arte-task-item-content' }, 0],
            ]
        },

        addKeyboardShortcuts() {
            return {
                Enter: () => this.editor.commands.splitListItem(this.name),

                Tab: () => this.editor.commands.sinkListItem(this.name),

                'Shift-Tab': () => this.editor.commands.liftListItem(this.name),

                Backspace: () => {
                    const { empty, $anchor } = this.editor.state.selection

                    // Only a collapsed cursor at the very start of the item's
                    // first block should unwrap it; everywhere else the browser
                    // default is the correct behaviour.
                    if (!empty || $anchor.parentOffset !== 0) {
                        return false
                    }

                    if (
                        $anchor.depth < 2 ||
                        $anchor.node(-1).type.name !== this.name ||
                        $anchor.index(-1) !== 0
                    ) {
                        return false
                    }

                    // Lifts the item out one level, or out of the list entirely
                    // when it is already at the top level.
                    return this.editor.commands.liftListItem(this.name)
                },
            }
        },

        addInputRules() {
            return [
                wrappingInputRule({
                    find: inputRegex,
                    type: this.type,
                    getAttributes: (match) => ({
                        checked: match[match.length - 1] === 'x',
                    }),
                }),
            ]
        },

        addNodeView() {
            return ({ node, HTMLAttributes, getPos, editor }) => {
                const listItem = document.createElement('li')
                const label = document.createElement('label')
                const checkbox = document.createElement('input')
                const checkboxDecoration = document.createElement('span')
                const content = document.createElement('div')

                // The label is chrome rather than document content, so it must
                // stay outside the editable region.
                label.contentEditable = 'false'

                checkbox.type = 'checkbox'
                checkbox.checked = node.attrs.checked
                checkbox.disabled = !editor.isEditable

                // The `<label>` has no text of its own, so the checkbox would
                // otherwise be announced without a name. Translations are not
                // reachable from a bundler-less module, hence the item's own
                // text is used verbatim.
                const updateAccessibleName = (currentNode) => {
                    const text = currentNode.textContent.trim()

                    if (text === '') {
                        checkbox.removeAttribute('aria-label')

                        return
                    }

                    checkbox.setAttribute('aria-label', text)
                }

                // Without this the click would move the ProseMirror selection
                // before the change handler gets to run.
                checkbox.addEventListener('mousedown', (event) =>
                    event.preventDefault(),
                )

                checkbox.addEventListener('change', (event) => {
                    // A disabled or read-only editor must never be mutated from
                    // the DOM, so undo the browser's optimistic toggle.
                    if (!editor.isEditable || typeof getPos !== 'function') {
                        checkbox.checked = !checkbox.checked

                        return
                    }

                    const { checked } = event.target

                    editor
                        .chain()
                        .focus(undefined, { scrollIntoView: false })
                        .command(({ tr }) => {
                            const position = getPos()

                            if (typeof position !== 'number') {
                                return false
                            }

                            const currentNode = tr.doc.nodeAt(position)

                            if (currentNode?.type !== this.type) {
                                return false
                            }

                            tr.setNodeMarkup(position, undefined, {
                                ...currentNode.attrs,
                                checked,
                            })

                            return true
                        })
                        .run()
                })

                Object.entries(this.options.HTMLAttributes).forEach(([name, value]) => {
                    listItem.setAttribute(name, value)
                })

                Object.entries(HTMLAttributes).forEach(([name, value]) => {
                    listItem.setAttribute(name, value)
                })

                // Applied last so a `class` coming from the attributes above
                // cannot overwrite the styling hook.
                listItem.setAttribute('data-type', this.name)
                listItem.dataset.checked = node.attrs.checked
                listItem.classList.add('fi-arte-task-item')

                /*
                 * Keeps the checkbox on the optical centre of the item's FIRST line.
                 *
                 * The stylesheet can only size the control against the list item's own
                 * font, which is the theme's - so an item whose text carries a larger
                 * inline font size would leave the checkbox stranded near the top. The
                 * real height of the first line box is measured here instead and handed
                 * to CSS, where the control is centred inside it.
                 */
                const syncLineHeight = () => {
                    const target = content.firstElementChild ?? content

                    if (!target?.isConnected) {
                        return
                    }

                    let rect = null

                    try {
                        const range = document.createRange()
                        range.selectNodeContents(target)

                        // The first client rect covers the first line, whatever mix of
                        // font sizes it happens to contain.
                        rect = range.getClientRects()[0] ?? null
                    } catch {
                        rect = null
                    }

                    if (!rect?.height) {
                        return
                    }

                    // A line box is taller than the text in it, and the extra leading sits
                    // above and below. Without the offset the control would be centred on
                    // the line box instead of on the text, which is a visible couple of
                    // pixels at body size and a lot more once the text is large.
                    const offset = rect.top - content.getBoundingClientRect().top
                    const round = (value) => Math.round(value * 100) / 100

                    listItem.style.setProperty('--fi-arte-task-line', `${round(rect.height)}px`)
                    listItem.style.setProperty('--fi-arte-task-line-offset', `${round(Math.max(0, offset))}px`)
                }

                /*
                 * Three passes, because none of them is reliable on its own: the
                 * synchronous one may run before ProseMirror has written the new DOM, the
                 * timer runs once the DOM is in place (and keeps working in a background
                 * tab), and the animation frame is the cheap one that wins in a visible
                 * tab. They all read the same value, so the extra passes are no-ops as
                 * soon as the layout has settled.
                 */
                const scheduleSync = () => {
                    syncLineHeight()
                    setTimeout(syncLineHeight, 0)
                    requestAnimationFrame(syncLineHeight)
                }

                // Re-measured on layout changes as well as on document changes: a font size
                // applied to the text does not necessarily reach this node view's own
                // `update`, because the mark lives on the paragraph inside it.
                //
                // Two observers, because rendering - and with it every resize callback -
                // is suspended in a background tab, while mutations are still delivered.
                const resizeObserver =
                    typeof ResizeObserver === 'undefined'
                        ? null
                        : new ResizeObserver(() => syncLineHeight())

                const mutationObserver =
                    typeof MutationObserver === 'undefined'
                        ? null
                        : new MutationObserver(() => scheduleSync())

                label.append(checkbox, checkboxDecoration)
                listItem.append(label, content)

                updateAccessibleName(node)

                scheduleSync()
                resizeObserver?.observe(content)
                // The style attribute is where an inline font size lands.
                mutationObserver?.observe(content, {
                    subtree: true,
                    childList: true,
                    characterData: true,
                    attributes: true,
                    attributeFilter: ['style', 'class'],
                })

                return {
                    dom: listItem,

                    contentDOM: content,

                    update: (updatedNode) => {
                        if (updatedNode.type !== this.type) {
                            return false
                        }

                        listItem.dataset.checked = updatedNode.attrs.checked
                        checkbox.checked = updatedNode.attrs.checked
                        checkbox.disabled = !editor.isEditable

                        updateAccessibleName(updatedNode)
                        scheduleSync()

                        return true
                    },

                    destroy: () => {
                        resizeObserver?.disconnect()
                        mutationObserver?.disconnect()
                    },

                    // The checkbox and the `data-*` bookkeeping live outside
                    // `contentDOM`; ProseMirror must not try to read them back
                    // as document content.
                    ignoreMutation: (mutation) =>
                        !content.contains(mutation.target),
                }
            }
        },
    })
}
