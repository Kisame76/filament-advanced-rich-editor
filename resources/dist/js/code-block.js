/*
 * The language a code block is written in, pickable in the editor.
 *
 * TipTap stores the language on the node and writes it out as `class="language-php"` -
 * everything needed to colour the block on the rendered page is therefore already in the
 * document. What was missing is a way to say which language it is: the block is created by
 * a toolbar button that cannot ask, and typing the class by hand means the source view.
 *
 * This adds no node of its own. The code block belongs to Filament, and redefining it here
 * would mean owning its input rules, its shortcuts and its paste handling for the sake of
 * one select. A node view registered through a plain ProseMirror plugin draws the control
 * around the node Filament already has.
 *
 * The editor does not colour anything. A highlighter worth having in a browser is measured
 * in megabytes and needs a build step this package does not have - and it would colour text
 * only its author ever sees. The colours happen in PHP, once, when the page is rendered.
 */

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core
    const pmState = window.FilamentRichEditor?.tiptap?.pmState

    if (!tiptap || !pmState) {
        console.error(
            'The advanced rich editor code block extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap
    const { Plugin, PluginKey } = pmState

    const settingsOf = (editor) => {
        const raw = editor?.options?.element?.dataset?.arteCodeBlock

        if (!raw) {
            return null
        }

        try {
            const settings = JSON.parse(raw)

            // `languages` is a map of value to label, not a list: `.length` on it is
            // undefined, which reads as "no languages" and takes the picker away.
            return Object.keys(settings?.languages ?? {}).length > 0 ? settings : null
        } catch (error) {
            console.error('The advanced rich editor could not read its code block settings:', error)

            return null
        }
    }

    return Extension.create({
        name: 'arteCodeBlockLanguage',

        addProseMirrorPlugins() {
            const editor = this.editor

            return [
                new Plugin({
                    key: new PluginKey('arteCodeBlockLanguage'),
                    props: {
                        nodeViews: {
                            codeBlock: (node, view, getPos) => {
                                const settings = settingsOf(editor)

                                // No list, no control: a project that curated the languages
                                // down to nothing said it does not want the picker.
                                if (!settings) {
                                    return null
                                }

                                const wrapper = document.createElement('div')
                                wrapper.className = 'fi-arte-code-block'

                                const select = document.createElement('select')
                                select.className = 'fi-arte-code-block-language'
                                select.contentEditable = 'false'

                                const plain = document.createElement('option')
                                plain.value = ''
                                plain.textContent = settings.plain ?? 'Plain text'
                                select.append(plain)

                                for (const [value, label] of Object.entries(settings.languages)) {
                                    const option = document.createElement('option')
                                    option.value = value
                                    option.textContent = label
                                    select.append(option)
                                }

                                // A language the block already carries that the project did
                                // not list is still what the block is written in, so it is
                                // added rather than silently reset to plain text.
                                const current = node.attrs.language ?? ''

                                if (current && !(current in settings.languages)) {
                                    const option = document.createElement('option')
                                    option.value = current
                                    option.textContent = current
                                    select.append(option)
                                }

                                select.value = current

                                select.addEventListener('change', () => {
                                    const pos = typeof getPos === 'function' ? getPos() : null

                                    if (pos === null || pos === undefined) {
                                        return
                                    }

                                    view.dispatch(
                                        view.state.tr.setNodeAttribute(
                                            pos,
                                            'language',
                                            select.value || null,
                                        ),
                                    )

                                    editor.commands.focus()
                                })

                                // Keeps a click on the select from moving the caret into the
                                // block behind it.
                                select.addEventListener('mousedown', (event) => event.stopPropagation())

                                const pre = document.createElement('pre')
                                const code = document.createElement('code')

                                if (current) {
                                    code.className = `language-${current}`
                                }

                                pre.append(code)
                                wrapper.append(select, pre)

                                return {
                                    dom: wrapper,
                                    contentDOM: code,
                                    update: (updated) => {
                                        if (updated.type.name !== 'codeBlock') {
                                            return false
                                        }

                                        const language = updated.attrs.language ?? ''

                                        select.value = language
                                        code.className = language ? `language-${language}` : ''

                                        return true
                                    },
                                    // ProseMirror must not touch anything inside the select;
                                    // it is chrome around the node rather than part of it.
                                    ignoreMutation: (mutation) =>
                                        mutation.target === select || select.contains(mutation.target),
                                }
                            },
                        },
                    },
                }),
            ]
        },
    })
}
