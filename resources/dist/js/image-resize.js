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
            return { unlocked: false }
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
                currentEditor.isActive('image') &&
                (currentEditor.isFocused ||
                    element?.contains(document.activeElement) === true)

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

                            const nodeView = findNodeView(handle)

                            if (!nodeView) {
                                return
                            }

                            nodeView.minSize = { ...MIN_SIZE }
                            // Shift still forces the ratio while unlocked, because the
                            // node view ORs the two together - a welcome accident.
                            nodeView.preserveAspectRatio = !storage.unlocked

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

                            nodeView.onResize = (width, height) => {
                                previousOnResize?.(width, height)
                                write(width, height)
                            }

                            const finish = () => {
                                nodeView.onResize = previousOnResize
                                badge.remove()

                                document.removeEventListener('mouseup', finish)
                                document.removeEventListener('touchend', finish)
                                document.removeEventListener('touchcancel', finish)
                            }

                            document.addEventListener('mouseup', finish)
                            document.addEventListener('touchend', finish)
                            document.addEventListener('touchcancel', finish)
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
