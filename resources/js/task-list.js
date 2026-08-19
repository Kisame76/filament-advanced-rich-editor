/**
 * TipTap `taskList` node for the Filament rich editor.
 *
 * This package ships no bundler, so the file must stay free of `import`
 * statements: Filament loads it verbatim through a dynamic `import()` in
 * `filament/forms/resources/js/components/rich-editor/extensions.js`. TipTap
 * and ProseMirror are therefore read from the global that Filament's own
 * bundle publishes (`window.FilamentRichEditor.tiptap`). Sharing that instance
 * is not just a size optimisation -- ProseMirror leans on `instanceof` checks,
 * so a second copy of the library would silently fail to interoperate.
 *
 * The default export is a zero-argument factory instead of a ready-made
 * instance. The loader accepts either, but a factory defers reading the global
 * until it is actually called, which is guaranteed to happen after Filament's
 * bundle has populated `window.FilamentRichEditor`.
 */
export default () => {
    const { Node, mergeAttributes } = window.FilamentRichEditor.tiptap.core

    return Node.create({
        name: 'taskList',

        addOptions() {
            return {
                itemTypeName: 'taskItem',
                HTMLAttributes: {},
            }
        },

        group: 'block list',

        content() {
            return `${this.options.itemTypeName}+`
        },

        parseHTML() {
            return [
                {
                    // The bundled bullet list claims plain `ul` at the default
                    // priority of 50, so this rule has to outrank it.
                    tag: `ul[data-type="${this.name}"]`,
                    priority: 51,
                },
            ]
        },

        renderHTML({ HTMLAttributes }) {
            // Mirrors the PHP `Tiptap\Nodes\TaskList` node as it is configured in
            // `TaskListPlugin`, class included, so content written in the editor and
            // content rendered by the PHP renderer parse back into the same document
            // and are styled by the same stylesheet.
            return [
                'ul',
                mergeAttributes(this.options.HTMLAttributes, HTMLAttributes, {
                    'data-type': this.name,
                    class: 'fi-arte-task-list',
                }),
                0,
            ]
        },

        addCommands() {
            return {
                // `toggleList` is available as a global command. Evidence, taken
                // from the prebuilt bundle `filament/forms/dist/components/rich-editor.js`:
                // the core command namespace is assembled as
                // `Al={};Tl(Al,{blur:()=>...,liftListItem:()=>mb,sinkListItem:()=>Fb,splitListItem:()=>Vb,toggleList:()=>jb,...,wrapInList:()=>e0})`
                // and registered wholesale by `U.create({name:"commands",addCommands(){return{...Al}}})`.
                // No ProseMirror transform fallback is needed here.
                toggleTaskList:
                    () =>
                    ({ commands }) =>
                        commands.toggleList(
                            this.name,
                            this.options.itemTypeName,
                        ),
            }
        },

        addKeyboardShortcuts() {
            return {
                'Mod-Shift-9': () => this.editor.commands.toggleTaskList(),
            }
        },
    })
}
