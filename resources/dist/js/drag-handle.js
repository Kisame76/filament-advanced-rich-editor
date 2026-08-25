/*
 * The grip in the margin, and the plus beside it.
 *
 * Hovering a block puts two small controls in the gutter to its left: one to drag the block
 * somewhere else, one to start a new block under it. Together with the slash menu the field
 * already has, that is the whole of what people mean when they say an editor should feel
 * like Notion - a document you can take apart with the mouse rather than one you can only
 * type into.
 *
 * Almost none of the moving is done here. ProseMirror already knows how to drop a slice at
 * a position, which node a point is inside, and where the insertion line goes - Filament
 * registers its drop cursor - so what this adds is the part that has no home anywhere else:
 * something to take hold of. A drag begins by selecting the block and handing the selection
 * to `view.dragging`, and from there it is ProseMirror's drag, with ProseMirror's rules
 * about where a node may land. Reimplementing that would mean owning every question about
 * what can sit inside what, in a schema that a project can add nodes to.
 *
 * The handle hangs off the editor's own box rather than off the document, and it is
 * positioned rather than laid out: it sits in the field's left padding, which the
 * stylesheet widens for a field that has one. Widening it is not free and it is the honest
 * price - Filament's editor leaves twenty pixels there and two controls need fifty - but
 * the alternative is a handle that appears on top of the first word of every block it is
 * meant to be beside. The padding belongs to fields carrying the settings attribute and to
 * no others, so an editor without the grip is the editor Filament drew.
 *
 * Only the top level of the document gets one. A paragraph, a heading, a list, a table, an
 * image: one grip each, and the grip on a list takes the list. Grabbing a single item out
 * of a list is what people ask for next, and it is deliberately not here - a list item is a
 * node that may only live inside a list, so dropping one into a paragraph is a question
 * ProseMirror answers with "nowhere", and the honest version of that feature is a drag that
 * refuses more often than it works.
 */

/** How wide the two controls are together, and how far they sit from the text. */
const WIDTH = 44
const HEIGHT = 24
const GAP = 6
const MARGIN = 2

/**
 * Where the handle goes for a block, or null when the block is not on screen.
 *
 * Pure, and separately testable, because it is the half of this that has nothing to do with
 * ProseMirror: two rectangles in, a point out. The vertical answer is the interesting one -
 * a handle centred on the block would sit halfway down a paragraph of nine lines, which
 * reads as belonging to the middle of the text rather than to the block. It is centred on
 * the first line instead, which is where the eye already is.
 *
 * @param {{top: number, bottom: number, left: number, height: number}} block
 * @param {{top: number, bottom: number, left: number}} editor
 * @param {{width?: number, height?: number, gap?: number, margin?: number, lineHeight?: number}} options
 * @returns {{left: number, top: number}|null}
 */
export function handlePosition(block, editor, options = {}) {
    const {
        width = WIDTH,
        height = HEIGHT,
        gap = GAP,
        margin = MARGIN,
        lineHeight = block.height,
    } = options

    // Scrolled out of the field's own window. A field with `maxHeight()` scrolls inside its
    // box, so a block can be above or below what is visible while the mouse is still in the
    // editor - and a handle drawn for it would be a handle floating over the toolbar.
    if (block.top >= editor.bottom || block.bottom <= editor.top) {
        return null
    }

    const first = Math.min(lineHeight || block.height, block.height)

    return {
        left: Math.max(editor.left + margin, block.left - gap - width),
        top: block.top + (first - height) / 2,
    }
}

/**
 * Whether the plus should use the block it was pressed on rather than make a new one.
 *
 * Pressing plus on the empty paragraph somebody just made and getting a second empty
 * paragraph under it is the kind of thing that is obvious once and irritating afterwards.
 *
 * @param {{type: {name: string}, content: {size: number}}} node
 */
export function reusesBlock(node) {
    return node?.type?.name === 'paragraph' && node.content.size === 0
}

/**
 * The character that opens the slash menu, read off the same attribute the menu reads.
 *
 * Nothing is duplicated and nothing is passed along: where the menu is switched off there
 * is no attribute, no character, and the plus makes an empty block and stops there - which
 * is the whole of what it can honestly do without a list to offer.
 */
function slashChar(editor) {
    const raw = editor.options.element?.dataset?.arteSlash

    if (!raw) {
        return null
    }

    try {
        return JSON.parse(raw)?.char ?? null
    } catch (error) {
        return null
    }
}

/**
 * What the field configured, off the element the editor was mounted on.
 */
function settingsOf(editor) {
    const raw = editor.options.element?.dataset?.arteDragHandle

    if (!raw) {
        return null
    }

    try {
        return JSON.parse(raw)
    } catch (error) {
        console.error('The advanced rich editor could not read its drag handle settings:', error)

        return null
    }
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core
    const pmState = window.FilamentRichEditor?.tiptap?.pmState

    if (! tiptap || ! pmState) {
        console.error(
            'The advanced rich editor drag handle needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap
    const { NodeSelection, Plugin, PluginKey, TextSelection } = pmState

    /**
     * One editor's handle: the element, what it is currently pointing at, and the listeners
     * that keep the two together.
     */
    class DragHandle {
        constructor(view, editor, settings) {
            this.view = view
            this.editor = editor
            this.settings = settings

            // The block the handle is currently for. Kept as a DOM node rather than as a
            // position, because a position goes stale on every keystroke and the element
            // does not - and the position is resolved from the element when it is needed.
            this.block = null

            this.build()
            this.listen()
        }

        build() {
            const element = document.createElement('div')
            element.className = 'fi-arte-drag-handle'

            if (this.settings.insert) {
                this.insert = document.createElement('button')
                this.insert.type = 'button'
                this.insert.className = 'fi-arte-drag-handle-insert'
                this.insert.title = this.settings.labels?.insert ?? ''
                this.insert.setAttribute('aria-label', this.settings.labels?.insert ?? '')
                this.insert.innerHTML = this.settings.icons?.insert ?? '+'
                element.append(this.insert)
            }

            this.grip = document.createElement('button')
            this.grip.type = 'button'
            this.grip.className = 'fi-arte-drag-handle-grip'
            this.grip.draggable = true
            this.grip.title = this.settings.labels?.drag ?? ''
            this.grip.setAttribute('aria-label', this.settings.labels?.drag ?? '')
            this.grip.innerHTML = this.settings.icons?.grip ?? ''
            element.append(this.grip)

            this.element = element
            this.hide()

            // Beside the editor rather than inside it: anything appended to the editable
            // element is content as far as ProseMirror is concerned, and it would be typed
            // over, selected and saved along with the document.
            this.view.dom.parentElement?.append(element)
        }

        listen() {
            this.onPointerMove = (event) => this.track(event)
            this.onPointerLeave = () => this.hide()
            this.onScroll = () => this.follow()

            const container = this.view.dom.parentElement

            container?.addEventListener('mousemove', this.onPointerMove)
            container?.addEventListener('mouseleave', this.onPointerLeave)

            // Capturing, because the thing that scrolls is as often the field itself as the
            // page: `maxHeight()` makes the editor its own window, and a scroll inside an
            // element does not reach the one it bubbles to.
            window.addEventListener('scroll', this.onScroll, true)
            window.addEventListener('resize', this.onScroll)

            this.grip.addEventListener('mousedown', () => this.select())
            this.grip.addEventListener('dragstart', (event) => this.start(event))
            this.grip.addEventListener('dragend', () => this.end())
            this.insert?.addEventListener('click', () => this.add())
        }

        /**
         * Where the top-level block containing a position starts, or null.
         *
         * Two answers rather than one, because a position at the top level is already a
         * block boundary rather than a place inside a block: `resolve()` puts it at depth
         * zero, where there is no ancestor to ask for, and the node beginning there is the
         * block being looked for. Reading only the ancestor is the bug this exists to
         * state - it finds nothing for every paragraph in the document.
         */
        blockPos(candidate) {
            if (candidate === null || candidate === undefined || candidate < 0) {
                return null
            }

            const { doc } = this.view.state

            if (candidate > doc.content.size) {
                return null
            }

            const $pos = doc.resolve(candidate)

            if ($pos.depth >= 1) {
                return $pos.before(1)
            }

            return doc.nodeAt(candidate) ? candidate : null
        }

        /**
         * The top-level block under a point, as the element that draws it.
         */
        blockAt(event) {
            const found = this.view.posAtCoords({ left: event.clientX, top: event.clientY })

            if (! found) {
                return null
            }

            // The position inside the text first, and the node the point sits in second:
            // `inside` is -1 whenever the point is between two blocks rather than in one,
            // which is most of the gutter this handle lives in.
            const pos = this.blockPos(found.pos) ?? this.blockPos(found.inside)

            if (pos === null) {
                return null
            }

            const dom = this.view.nodeDOM(pos)

            return dom?.nodeType === 1 ? dom : null
        }

        /**
         * Where the block currently under the handle starts, resolved when it is needed
         * rather than remembered: everything typed since the handle appeared has moved it.
         */
        position() {
            if (! this.block?.isConnected) {
                return null
            }

            return this.blockPos(this.view.posAtDOM(this.block, 0))
        }

        track(event) {
            // A field nobody may type into is a field nobody may rearrange either, and the
            // handle would be the only part of it that still looked interactive.
            if (! this.view.editable) {
                return this.hide()
            }

            // Moving over the handle is not moving off the block it belongs to.
            if (this.element.contains(event.target)) {
                return
            }

            const block = this.blockAt(event)

            if (! block) {
                return this.hide()
            }

            this.block = block
            this.reposition()
        }

        reposition() {
            if (! this.block?.isConnected) {
                return this.hide()
            }

            // The box around the editable rather than the editable itself: the gutter the
            // handle sits in is that box's padding, so the editable's own left edge is
            // where the text starts and would clamp the handle on top of it.
            const container = this.view.dom.parentElement ?? this.view.dom

            // Measured rather than assumed: how big the two controls are is the
            // stylesheet's business, and a project that resizes them there would otherwise
            // get a handle sitting beside the line rather than on it.
            const where = handlePosition(
                this.block.getBoundingClientRect(),
                container.getBoundingClientRect(),
                {
                    width: this.element.offsetWidth || WIDTH,
                    height: this.element.offsetHeight || HEIGHT,
                    lineHeight: parseFloat(getComputedStyle(this.block).lineHeight),
                },
            )

            if (! where) {
                return this.hide()
            }

            this.element.style.left = `${where.left}px`
            this.element.style.top = `${where.top}px`
            this.element.classList.add('fi-arte-drag-handle-visible')
        }

        /**
         * Repositioning, but only while there is something to reposition.
         *
         * Measuring costs a style recalculation, and the two callers - every scroll and
         * every transaction, so every keystroke - are exactly the two that would pay it
         * over and over for a handle nobody is looking at.
         */
        follow() {
            if (this.element.classList.contains('fi-arte-drag-handle-visible')) {
                this.reposition()
            }
        }

        hide() {
            this.element.classList.remove('fi-arte-drag-handle-visible')
        }

        /**
         * Selects the block the handle points at, which is both what a click on the grip
         * does and what a drag needs to have happened before it starts.
         */
        select() {
            const pos = this.position()

            if (pos === null) {
                return null
            }

            const selection = NodeSelection.create(this.view.state.doc, pos)

            this.view.dispatch(this.view.state.tr.setSelection(selection))

            return selection
        }

        start(event) {
            const selection = this.select()

            if (! selection) {
                return event.preventDefault()
            }

            // From here it is ProseMirror's drag: it decides where the slice may land, it
            // draws the line saying so, and it removes the node from where it was. The
            // clipboard payload is only what a drop outside the editor would receive.
            this.view.dragging = { slice: selection.content(), move: true }

            event.dataTransfer.effectAllowed = 'move'
            event.dataTransfer.setData('text/plain', this.block?.textContent ?? '')

            if (this.block) {
                event.dataTransfer.setDragImage(this.block, 0, 0)
            }

            this.view.dom.classList.add('fi-arte-dragging')
        }

        end() {
            this.view.dom.classList.remove('fi-arte-dragging')
            this.view.dragging = null
        }

        /**
         * A new block under this one, with the caret in it - and the slash menu open on top
         * of that, which is the whole point of the button: what it inserts is not a
         * paragraph, it is the list of everything that could go there.
         */
        add() {
            const pos = this.position()

            if (pos === null) {
                return
            }

            const { state } = this.view
            const node = state.doc.nodeAt(pos)

            if (! node) {
                return
            }

            const paragraph = state.schema.nodes.paragraph

            let transaction = state.tr
            let caret = pos + 1

            if (! reusesBlock(node) && paragraph) {
                const end = pos + node.nodeSize

                transaction = transaction.insert(end, paragraph.create())
                caret = end + 1
            }

            transaction = transaction.setSelection(TextSelection.create(transaction.doc, caret))

            this.view.dispatch(transaction.scrollIntoView())
            this.view.focus()

            const char = slashChar(this.editor)

            // Typed rather than signalled: the menu opens on what the document says, so
            // inserting the character is the same event as somebody pressing the key, down
            // to the query it starts with and the way backspacing out of it closes again.
            if (char) {
                this.view.dispatch(this.view.state.tr.insertText(char))
            }

            this.hide()
        }

        destroy() {
            const container = this.view.dom.parentElement

            container?.removeEventListener('mousemove', this.onPointerMove)
            container?.removeEventListener('mouseleave', this.onPointerLeave)
            window.removeEventListener('scroll', this.onScroll, true)
            window.removeEventListener('resize', this.onScroll)

            this.element.remove()
        }
    }

    return Extension.create({
        name: 'arteDragHandle',

        addProseMirrorPlugins() {
            const editor = this.editor

            return [
                new Plugin({
                    key: new PluginKey('arteDragHandle'),
                    view: (view) => {
                        const settings = settingsOf(editor)

                        if (! settings) {
                            return {}
                        }

                        const handle = new DragHandle(view, editor, settings)

                        return {
                            // The document changed under the handle: the block it points at
                            // may have grown, moved or gone.
                            update: () => handle.follow(),
                            destroy: () => handle.destroy(),
                        }
                    },
                }),
            ]
        },
    })
}
