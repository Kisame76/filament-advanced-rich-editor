/*
 * Assists Filament's image resizing: a live size readout while dragging, and a switch
 * that frees the drag from the image's aspect ratio.
 *
 * Filament configures TipTap's own resizable image node view with
 * `alwaysPreserveAspectRatio: true`, and that option is read once, when the node view is
 * created - so it cannot be changed later through the extension's options. What the node
 * view *does* read on every mouse move is its own `preserveAspectRatio` property, so the
 * lock is flipped on the instance instead, right when a drag starts. Nothing about the
 * built-in extension is replaced.
 *
 * The lock is a drag-time modifier, not content: it lives on the editor rather than on the
 * image node, because Filament's HTML sanitiser drops unknown attributes anyway.
 */
export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap

    if (!tiptap?.core) {
        console.error(
            'The advanced rich editor image resize assist needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap.core
    const { Plugin, PluginKey } = tiptap.pmState

    // TipTap leaves both minimums undefined here, and `Math.max(undefined, n)` is NaN as
    // soon as the aspect ratio no longer forces a value - which is exactly what unlocking
    // does. A real floor is therefore installed alongside the unlock.
    const MIN_SIZE = { width: 8, height: 8 }

    /**
     * Which way each handle points, in screen directions. The rotation extension gives a
     * turned image compensating margins, so the wrapper these sit on is the picture's own
     * box however it is turned - `bottom-right` is the corner at the bottom right of the
     * picture at every angle.
     */
    const OUTWARD = {
        'bottom-right': [1, 1],
        'bottom-left': [-1, 1],
        'top-right': [1, -1],
        'top-left': [-1, -1],
        right: [1, 0],
        left: [-1, 0],
        bottom: [0, 1],
        top: [0, -1],
    }

    /**
     * What each screen edge is called in the element's own axes, per quarter turn.
     *
     * A turn moves the picture but not the handles: they keep sitting on the wrapper, so
     * the one at the bottom right is still at the bottom right. The node view, though,
     * reads the handle's NAME as a corner of the element - and after a quarter turn the
     * element's right edge is the picture's bottom. Renaming the handle for the length of
     * the drag is what lets the node view go on doing its own arithmetic and still change
     * the side the pointer is pulling.
     */
    const EDGE_AT_ANGLE = {
        0: { right: 'right', left: 'left', top: 'top', bottom: 'bottom' },
        90: { right: 'top', bottom: 'right', left: 'bottom', top: 'left' },
        180: { right: 'left', left: 'right', top: 'bottom', bottom: 'top' },
        270: { right: 'bottom', bottom: 'left', left: 'top', top: 'right' },
    }

    /**
     * The pointer, read in the element's axes instead of the screen's: the inverse of the
     * rotation the picture is drawn with.
     */
    const DELTA_AT_ANGLE = {
        0: (deltaX, deltaY) => [deltaX, deltaY],
        90: (deltaX, deltaY) => [deltaY, -deltaX],
        180: (deltaX, deltaY) => [-deltaX, -deltaY],
        270: (deltaX, deltaY) => [-deltaY, deltaX],
    }

    /**
     * Keeps a turned picture's layout box on its live size while it is being dragged.
     *
     * The rotation extension works the compensating margins out from the node's width and
     * height, and those only change when the drag commits - but the node view writes the new
     * size straight onto the element on every step. Between the two the box belongs to the
     * size the picture had before the drag: the frame and the handles sit somewhere the
     * picture no longer is. Recomputed here on the same steps, with the same arithmetic, and
     * overwritten by the extension's own render as soon as the drag lands.
     */
    const followTurnedSize = (image, angle, width, height) => {
        if (!image) {
            return
        }

        if (angle % 180 === 0 || !Number.isFinite(width) || !Number.isFinite(height)) {
            return
        }

        image.style.marginBlock = `${(width - height) / 2}px`
        image.style.marginInline = `${(height - width) / 2}px`
    }

    /** The angle an image is drawn at, as one of the four quarter turns. */
    const angleOf = (nodeView) => {
        const rotate = Number.parseFloat(nodeView?.node?.attrs?.rotate) || 0

        return (((Math.round(rotate / 90) * 90) % 360) + 360) % 360
    }

    /**
     * `bottom-right` at 90 degrees is the element's `top-right`, and so on. Written back the
     * way the node view names things - the vertical edge first - so the result is a name it
     * would have produced itself.
     */
    const renameHandle = (handleName, angle) => {
        const edges = EDGE_AT_ANGLE[angle]

        if (!edges || !handleName) {
            return handleName
        }

        const renamed = handleName.split('-').map((edge) => edges[edge] ?? edge)

        const vertical = renamed.find((edge) => edge === 'top' || edge === 'bottom')
        const horizontal = renamed.find((edge) => edge === 'left' || edge === 'right')

        return [vertical, horizontal].filter(Boolean).join('-')
    }

    // Re-selecting the picture needs the class a node selection is made of. Missing only on
    // a build that does not expose ProseMirror's state module, where the toolbar goes back
    // to blinking out and nothing else breaks.
    const NodeSelection = window.FilamentRichEditor?.tiptap?.pmState?.NodeSelection ?? null

    /**
     * Puts the selection back on the picture.
     *
     * The floating toolbar is shown while the image is active. Grabbing a handle never
     * selects it - the node view swallows that mousedown before ProseMirror sees it - and
     * the transaction that commits a finished drag leaves a caret beside the picture rather
     * than a selection of it. Both would close the bar under the pointer that is using it.
     */
    const reselect = (editorView, nodeView) => {
        if (!NodeSelection || !editorView || typeof nodeView?.getPos !== 'function') {
            return
        }

        const position = nodeView.getPos()

        if (typeof position !== 'number') {
            return
        }

        const { state } = editorView
        const node = state.doc.nodeAt(position)

        if (!node || !NodeSelection.isSelectable(node)) {
            return
        }

        if (state.selection.from === position && state.selection.node) {
            return
        }

        editorView.dispatch(
            state.tr
                .setMeta('addToHistory', false)
                .setSelection(NodeSelection.create(state.doc, position)),
        )
    }

    const findNodeView = (element) => {
        let candidate = element?.closest?.('[data-resize-container]')

        while (candidate) {
            const description = candidate.pmViewDesc

            if (description?.spec && 'preserveAspectRatio' in description.spec) {
                return description.spec
            }

            candidate = candidate.parentElement
        }

        return null
    }

    return Extension.create({
        name: 'arteImageResize',

        addStorage() {
            return { unlocked: false, resizing: false }
        },

        onCreate() {
            /*
             * Widens the image toolbar's visibility rule.
             *
             * Filament shows a floating toolbar while `editor.isFocused && isActive(key)`,
             * which is right for a bar of buttons and wrong for one holding inputs: typing
             * in an input blurs the editor, and the first transaction after that hides the
             * bar - along with the input being typed into. TipTap's own default rule
             * already covers this case by also accepting focus inside the bar itself, so
             * that is what is installed here.
             *
             * Done through the bubble menu plugin's own `updateOptions` message rather than
             * by touching Filament's component, and only for the image toolbar.
             */
            const { editor } = this

            const shouldShow = ({ editor: currentEditor, element }) =>
                // A drag holds the bar open on its own. The transaction that commits one
                // lands with the picture briefly unselected, and without this the bar would
                // blink out under the pointer that is still resizing it.
                this.storage.resizing ||
                (currentEditor.isActive('image') &&
                    (currentEditor.isFocused ||
                        element?.contains(document.activeElement) === true))

            editor.view.dispatch(
                editor.state.tr
                    .setMeta('addToHistory', false)
                    .setMeta('floatingToolbar::image', {
                        type: 'updateOptions',
                        options: { shouldShow },
                    }),
            )
        },

        addProseMirrorPlugins() {
            const storage = this.storage

            return [
                new Plugin({
                    key: new PluginKey('arteImageResize'),

                    view: (editorView) => {
                        /*
                         * A broken image is otherwise invisible: the node keeps its width
                         * and height, so the editor shows an empty hole where a picture
                         * should be, with nothing to say the file did not load. The state
                         * is marked on the resize wrapper for the stylesheet to draw.
                         */
                        const markImage = (image) => {
                            const wrapper =
                                image.closest('[data-resize-wrapper]') ??
                                image.parentElement

                            if (!wrapper) {
                                return
                            }

                            const state =
                                image.complete && image.naturalWidth === 0
                                    ? 'error'
                                    : 'loaded'

                            // Written only on a real change: this attribute sits inside
                            // the editable DOM, and rewriting it on every editor update
                            // would feed ProseMirror a stream of mutations to inspect.
                            if (wrapper.getAttribute('data-arte-image') !== state) {
                                wrapper.setAttribute('data-arte-image', state)
                            }

                            if (state === 'loaded') {
                                pinTurnedSize(image)
                            }
                        }

                        /*
                         * A turned picture needs a width and a height of its own: they are
                         * what the compensating margins are worked out from, and without
                         * them its layout box stays unturned - it lies across the lines
                         * around it and its handles are off its corners.
                         *
                         * Turning normally pins them from what is on screen, but an image
                         * that had not loaded yet could not be measured. This is the second
                         * chance, taken the moment the file arrives.
                         */
                        const pinTurnedSize = (image) => {
                            const nodeView = findNodeView(image)
                            const attrs = nodeView?.node?.attrs

                            if (!attrs?.rotate) {
                                return
                            }

                            if (
                                Number.isFinite(Number.parseFloat(attrs.width)) &&
                                Number.isFinite(Number.parseFloat(attrs.height))
                            ) {
                                return
                            }

                            if (!image.offsetWidth || !image.offsetHeight) {
                                return
                            }

                            const position = nodeView.getPos?.()

                            if (typeof position !== 'number') {
                                return
                            }

                            const { state: editorState } = editorView
                            const node = editorState.doc.nodeAt(position)

                            if (node?.type.name !== 'image') {
                                return
                            }

                            editorView.dispatch(
                                editorState.tr
                                    .setMeta('addToHistory', false)
                                    .setNodeMarkup(position, undefined, {
                                        ...node.attrs,
                                        width: image.offsetWidth,
                                        height: image.offsetHeight,
                                    }),
                            )
                        }

                        const onImageEvent = (event) => {
                            if (event.target?.tagName === 'IMG') {
                                markImage(event.target)
                            }
                        }

                        // `load` and `error` do not bubble, so both are captured. They
                        // fire once per source, which is deliberately the only trigger:
                        // sweeping the document on every editor update would write into
                        // the editable DOM continuously and hand ProseMirror a mutation to
                        // re-inspect each time.
                        editorView.dom.addEventListener('error', onImageEvent, true)
                        editorView.dom.addEventListener('load', onImageEvent, true)

                        // Images restored from cache are already decided by the time this
                        // runs, so they never fire an event of their own. One pass, once.
                        const initialSweep = setTimeout(() => {
                            editorView.dom.querySelectorAll('img').forEach((image) => {
                                if (image.complete) {
                                    markImage(image)
                                }
                            })
                        }, 0)

                        const onResizeStart = (event) => {
                            const handle =
                                event.target?.closest?.('[data-resize-handle]')

                            if (!handle) {
                                return
                            }

                            /*
                             * Only the button that actually drags. The node view starts a
                             * resize on any button, so a right click on a handle resizes the
                             * picture behind its own context menu - and does it without the
                             * corrections below, which are what make a turned one behave.
                             *
                             * This listener is on the editor and captures, so stopping the
                             * event here keeps it from ever reaching the handle the node
                             * view is listening on. The context menu is its own event and
                             * still opens.
                             */
                            if (event.button !== undefined && event.button !== 0) {
                                event.stopPropagation()

                                return
                            }

                            const nodeView = findNodeView(handle)

                            if (!nodeView) {
                                return
                            }

                            // Clicking a handle is clicking the picture: the bar opens, and
                            // stays open, exactly as it does when the picture itself is
                            // clicked. Without this the node view swallows the event and a
                            // handle grabbed on an unselected image opens nothing.
                            reselect(editorView, nodeView)

                            storage.resizing = true

                            nodeView.minSize = { ...MIN_SIZE }
                            // Shift still forces the ratio while unlocked, because the
                            // node view ORs the two together - a welcome accident.
                            nodeView.preserveAspectRatio = !storage.unlocked

                            /*
                             * The node view listens for the shift key only while a drag is
                             * running, so a shift released after the mouse button leaves the
                             * flag standing - and every later drag keeps the ratio however
                             * the lock is set. Re-read from the event that starts this drag.
                             */
                            nodeView.isShiftKeyPressed = event.shiftKey === true

                            const handleName = handle.getAttribute('data-resize-handle')
                            const outward = OUTWARD[handleName]

                            if (outward) {
                                // A drag whose end was never heard would otherwise leave its
                                // wrapper in place, and this one would wrap the wrapper.
                                handle.__arteRestoreResize?.()

                                const [outwardX, outwardY] = outward
                                const angle = angleOf(nodeView)
                                const rotateDelta = DELTA_AT_ANGLE[angle] ?? DELTA_AT_ANGLE[0]
                                const renamedHandle = renameHandle(handleName, angle)
                                const widthGrowsWithPositiveDelta = handleName.includes('right')

                                const originalHandleResize = nodeView.handleResize.bind(nodeView)

                                nodeView.handleResize = (deltaX, deltaY) => {
                                    /*
                                     * With the ratio kept, one number drives both sides -
                                     * and for a corner the node view only ever reads the
                                     * width, discarding the other delta. Dragging a corner
                                     * straight down therefore did nothing at all, and on a
                                     * quarter turned picture it was dragging sideways that
                                     * did nothing. So the pointer is measured along the
                                     * direction the handle points in, and that one distance
                                     * is what grows the picture.
                                     */
                                    if (nodeView.preserveAspectRatio || nodeView.isShiftKeyPressed) {
                                        const byX = outwardX * deltaX
                                        const byY = outwardY * deltaY

                                        // The axis the pointer moved furthest along, not the
                                        // average of the two: averaging would make the handle
                                        // travel at half the speed of the cursor whenever the
                                        // drag is along one axis only.
                                        const outwardBy =
                                            Math.abs(byX) >= Math.abs(byY) ? byX : byY

                                        return originalHandleResize(
                                            widthGrowsWithPositiveDelta ? outwardBy : -outwardBy,
                                            0,
                                        )
                                    }

                                    /*
                                     * Otherwise the drag is handed over in the element's own
                                     * axes: the pointer rotated back out of the picture's
                                     * frame, and the handle renamed to the edge of the
                                     * element that the pointer is actually pulling. The node
                                     * view then does its own arithmetic, unchanged, and gets
                                     * it right at every angle - corners and edges alike.
                                     */
                                    const realHandle = nodeView.activeHandle

                                    nodeView.activeHandle = renamedHandle

                                    try {
                                        return originalHandleResize(...rotateDelta(deltaX, deltaY))
                                    } finally {
                                        nodeView.activeHandle = realHandle
                                    }
                                }

                                // Back to the node view's own method when the drag ends, so
                                // nothing of this survives into the next one.
                                handle.__arteRestoreResize = () => {
                                    delete nodeView.handleResize
                                }
                            }

                            const wrapper = handle.closest('[data-resize-wrapper]')
                            const image = nodeView.element

                            if (!wrapper || !image) {
                                return
                            }

                            const badge = document.createElement('div')
                            badge.className = 'fi-arte-image-size'
                            badge.setAttribute('aria-hidden', 'true')

                            const write = (width, height) => {
                                badge.textContent = `${Math.round(width)} × ${Math.round(height)}`
                            }

                            write(image.offsetWidth, image.offsetHeight)
                            wrapper.appendChild(badge)

                            // The node view reports every step of the drag through this
                            // callback, so the readout needs no polling.
                            const previousOnResize = nodeView.onResize

                            const angleWhileDragging = angleOf(nodeView)

                            nodeView.onResize = (width, height) => {
                                previousOnResize?.(width, height)
                                write(width, height)
                                followTurnedSize(image, angleWhileDragging, width, height)
                            }

                            const finish = () => {
                                nodeView.onResize = previousOnResize

                                handle.__arteRestoreResize?.()
                                delete handle.__arteRestoreResize

                                badge.remove()

                                // After the commit that ends the drag has landed, not before
                                // it: the node view dispatches that one from its own mouseup
                                // handler, and it is what drops the selection. A timeout
                                // rather than a frame, because frames do not come while the
                                // tab is in the background and the selection would be left
                                // dropped until it was looked at again.
                                setTimeout(() => {
                                    reselect(editorView, nodeView)
                                    storage.resizing = false
                                }, 0)

                                document.removeEventListener('mouseup', finish)
                                document.removeEventListener('touchend', finish)
                                document.removeEventListener('touchcancel', finish)
                                window.removeEventListener('blur', finish)
                            }

                            document.addEventListener('mouseup', finish)
                            document.addEventListener('touchend', finish)
                            document.addEventListener('touchcancel', finish)
                            // A button released outside the window can leave the mouseup
                            // unheard, and everything this drag put in place would stay -
                            // including a floating bar that never closes again.
                            window.addEventListener('blur', finish)
                        }

                        // Capture phase: the node view's own handler stops propagation, so
                        // a bubbling listener would never see the drag start.
                        editorView.dom.addEventListener(
                            'mousedown',
                            onResizeStart,
                            true,
                        )
                        editorView.dom.addEventListener(
                            'touchstart',
                            onResizeStart,
                            true,
                        )

                        return {
                            destroy: () => {
                                clearTimeout(initialSweep)

                                editorView.dom.removeEventListener(
                                    'mousedown',
                                    onResizeStart,
                                    true,
                                )
                                editorView.dom.removeEventListener(
                                    'touchstart',
                                    onResizeStart,
                                    true,
                                )
                                editorView.dom.removeEventListener(
                                    'error',
                                    onImageEvent,
                                    true,
                                )
                                editorView.dom.removeEventListener(
                                    'load',
                                    onImageEvent,
                                    true,
                                )
                            },
                        }
                    },
                }),
            ]
        },
    })
}
