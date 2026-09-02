import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import formatBrushExtension, {
    applicationFor,
    carriedMarkNames,
    marksInSelection,
    nextMode,
    pickFrom,
} from '../../resources/js/format-brush.js'

/**
 * The brush that picks formatting up at one place and puts it down at another.
 *
 * Two things carry it and both were measured on a running editor rather than reasoned
 * about. What may be carried is decided by the schema of *this* field, because two editors
 * on one page do not have the same one - the styles plugin is on for one and off for the
 * other, and a hard-coded list would promise a mark that is not there. And what is picked
 * up at a caret is not the same question as what is picked up over a selection: at a caret
 * the answer is the one ProseMirror itself uses to decide what typed text would get, and
 * over a selection it is what every character in it has in common.
 */

const markType = (name, { attrs = {}, excludes = undefined } = {}) => ({
    name,
    spec: { attrs: Object.keys(attrs).length ? attrs : undefined, excludes },
})

const schemaOf = (...types) => ({
    marks: Object.fromEntries(types.map((type) => [type.name, type])),
})

/** Every mark this package and Filament really declare, as measured in the browser. */
const fullSchema = () =>
    schemaOf(
        markType('bold'),
        markType('italic'),
        markType('underline'),
        markType('strike'),
        markType('subscript'),
        markType('superscript'),
        markType('highlight'),
        markType('small'),
        markType('textColor', { attrs: { 'data-color': {} } }),
        markType('fontSize', { attrs: { size: {} } }),
        markType('fontFamily', { attrs: { family: {} } }),
        markType('textBackground', { attrs: { color: {} } }),
        markType('language', { attrs: { code: {} } }),
        markType('code', { excludes: '_' }),
        markType('link', {
            attrs: { href: {}, target: {}, rel: {}, id: {}, class: {}, hreflang: {}, referrerpolicy: {}, title: {} },
        }),
    )

/**
 * A mark as ProseMirror hands one over: a type, its attributes, and an equality that
 * accounts for both. The brush leans on `eq` rather than comparing names, because two
 * `fontSize` marks of different sizes are not the same formatting.
 */
const mark = (name, attrs = {}) => ({
    type: { name },
    attrs,
    eq(other) {
        return other?.type?.name === name && JSON.stringify(other.attrs ?? {}) === JSON.stringify(attrs)
    },
})

const leaf = (marks, { code = false } = {}) => ({
    node: { isText: true, marks },
    // `nodesBetween` hands the parent over as its third argument, and the parent is the
    // only place the answer lives: a text node knows its marks, not the block it sits in.
    parent: { type: { spec: code ? { code: true, marks: '' } : {} } },
})

/**
 * A document walked the way ProseMirror walks one, and a selection carrying one range per
 * disjoint piece.
 *
 * Keyed on each range's own start, because a selection across table cells has four of them
 * and `from`/`to` are the ends of the first - measured, and the reason a brush that read
 * `from`/`to` would paint one cell out of four.
 */
const stateOf = ({ leaves = {}, ranges = [[0, 1]], empty = false, node = null, storedMarks = null, caretMarks = [] } = {}) => ({
    schema: fullSchema(),
    storedMarks,
    // A transaction carrying no steps is how the button is told to look again, so a state
    // double without one would fail the commands for a reason the editor never has.
    tr: { setMeta: (key, value) => ({ meta: [key, value] }) },
    selection: {
        empty,
        node,
        ranges: ranges.map(([from, to]) => ({ $from: { pos: from }, $to: { pos: to } })),
        $from: { marks: () => caretMarks },
    },
    doc: {
        nodesBetween(from, to, callback) {
            for (const { node, parent } of leaves[from] ?? []) {
                callback(node, from, parent)
            }
        },
    },
})

describe('what the brush may carry', () => {
    it('reads the marks off this field rather than a list of its own', () => {
        // Measured: `styleClass` is in one field's schema and not in another's on the same
        // page, because the styles plugin is per field. A written-out list would promise a
        // mark that does not exist here and quietly drop one that does.
        const narrow = schemaOf(markType('bold'), markType('italic'))

        expect(carriedMarkNames(narrow)).toEqual(['bold', 'italic'])
        expect(carriedMarkNames(schemaOf(markType('bold'), markType('styleClass', { attrs: { name: {} } }))))
            .toContain('styleClass')
    })

    it('refuses the mark that takes every other one away with it', () => {
        // `code` declares `excludes: "_"`, the only one in the whole bundle. Measured both
        // ways round: picking up {bold, fontSize, code} and applying it leaves `code` alone
        // on the target, and applying `code` first makes the other two refuse to go on. A
        // brush that carried it would put down something other than what it picked up.
        expect(carriedMarkNames(fullSchema())).not.toContain('code')
    })

    it('refuses a mark that points somewhere or names something', () => {
        // A link is a destination rather than a look, and its `id` is a fragment that has
        // to be unique - brushed onto a second passage it makes two elements answer to one
        // `#name` and the second unreachable. Judged by what the mark declares rather than
        // by its name, so a plugin's own anchor-carrying mark is refused on the same
        // grounds without an entry anywhere.
        expect(carriedMarkNames(fullSchema())).not.toContain('link')
        expect(carriedMarkNames(schemaOf(markType('citation', { attrs: { href: {} } })))).toEqual([])
        expect(carriedMarkNames(schemaOf(markType('bookmark', { attrs: { id: {} } })))).toEqual([])
    })

    it('refuses the one that says what a passage is, not how it looks', () => {
        // `language` marks the tongue a passage is written in, which is what a screen
        // reader switches voice on. Nothing structural gives it away - its attribute is
        // just `code` - so it is named, with the reason written down beside it.
        expect(carriedMarkNames(fullSchema())).not.toContain('language')
    })

    it('carries everything else, attributes and all', () => {
        expect(carriedMarkNames(fullSchema())).toEqual([
            'bold', 'italic', 'underline', 'strike', 'subscript', 'superscript',
            'highlight', 'small', 'textColor', 'fontSize', 'fontFamily', 'textBackground',
        ])
    })
})

describe('picking formatting up', () => {
    it('takes what typing there would produce, at a caret', () => {
        // Measured against the real editor: `storedMarks` is null unless the user has just
        // toggled something at the caret, and `$from.marks()` is what ProseMirror falls
        // back to. Using that pair means the brush picks up exactly what the toolbar says
        // is active at the same caret - including its edge behaviour, which is the editor's
        // own answer rather than a defect to correct.
        expect(pickFrom(stateOf({ empty: true, caretMarks: [mark('bold')] })))
            .toEqual([{ name: 'bold', attrs: {} }])

        expect(pickFrom(stateOf({ empty: true, caretMarks: [], storedMarks: [mark('italic')] })))
            .toEqual([{ name: 'italic', attrs: {} }])
    })

    it('takes only what the whole selection has in common', () => {
        // Half a bold word and half a plain one has no formatting in common, and a brush
        // that answered "bold" there would be reading the first character rather than the
        // selection. `marksAcross` does exactly that and is measured returning ['bold'] for
        // this case, which is why it is not used.
        const common = stateOf({
            leaves: { 0: [leaf([mark('bold'), mark('italic')]), leaf([mark('bold')])] },
        })

        expect(pickFrom(common)).toEqual([{ name: 'bold', attrs: {} }])

        const nothing = stateOf({ leaves: { 0: [leaf([mark('bold')]), leaf([])] } })

        expect(pickFrom(nothing)).toBeNull()
    })

    it('tells two sizes apart', () => {
        const state = stateOf({
            leaves: { 0: [leaf([mark('fontSize', { size: '2rem' })]), leaf([mark('fontSize', { size: '3rem' })])] },
        })

        expect(pickFrom(state)).toBeNull()
    })

    it('looks in every range a selection has, not only the first', () => {
        // A selection across table cells carries four, and `from`/`to` are the ends of one
        // of them. Measured on a real CellSelection: `[[606,613],[577,584],[586,593],[597,604]]`,
        // and not in document order either.
        const state = stateOf({
            leaves: { 10: [leaf([mark('bold'), mark('italic')])], 20: [leaf([mark('bold')])] },
            ranges: [[10, 11], [20, 21]],
        })

        expect(pickFrom(state)).toEqual([{ name: 'bold', attrs: {} }])
    })

    it('refuses a selected picture', () => {
        // Measured, and the reason this is a refusal rather than a no-op: an image declares
        // no `marks` of its own, so ProseMirror's default lets every mark onto it. The
        // chain returns true, `can()` returns true, and the document quietly gains a bold
        // picture. The same test `text-toolbar.js` makes, for the same reason.
        //
        // Given something it *would* pick up, which is the whole of the test. Written
        // without it this passed with the guard deleted - an empty document double answers
        // null for having found nothing, which looks exactly like a refusal.
        const state = stateOf({
            node: { type: { name: 'image' } },
            leaves: { 0: [leaf([mark('bold')])] },
        })

        expect(pickFrom({ ...state, selection: { ...state.selection, node: null } }))
            .toEqual([{ name: 'bold', attrs: {} }])
            .not.toBeNull()

        expect(pickFrom(state)).toBeNull()
    })

    it('refuses a place where there is nothing to pick up', () => {
        expect(pickFrom(stateOf({ empty: true, caretMarks: [] }))).toBeNull()
        expect(pickFrom(stateOf({ leaves: { 0: [] } }))).toBeNull()
    })

    it('walks past text a code block holds', () => {
        // A code block declares `marks: ""`, so nothing in it carries formatting and
        // nothing put there would stay. Counting its characters towards what the selection
        // has in common would make every selection crossing one pick up nothing.
        const state = stateOf({
            leaves: { 0: [leaf([mark('bold')]), leaf([], { code: true })] },
        })

        expect(pickFrom(state)).toEqual([{ name: 'bold', attrs: {} }])
    })
})

describe('the gesture', () => {
    it('goes round in three, on plain clicks', () => {
        // One click arms it for a single stroke, a second keeps it armed, a third puts it
        // away. Three states on one button rather than click-versus-double-click, which is
        // the shape every editor copies from Word and the one thing about this feature that
        // could not be built reliably: it reads `event.detail`, and a second click meant as
        // "make it stick" is indistinguishable from the second half of a double-click.
        expect(nextMode(null)).toBe('once')
        expect(nextMode('once')).toBe('sticky')
        expect(nextMode('sticky')).toBeNull()
    })
})

describe('putting it down', () => {
    it('replaces the formatting it carries and leaves the rest alone', () => {
        // Word replaces character formatting rather than adding to it, and so does this.
        // Measured, and the reason it is done mark by mark rather than with
        // `unsetAllMarks`: that command takes the hyperlink off too, and a link is content.
        // Naming only the carried set spares it without a special case.
        const plan = applicationFor([{ name: 'bold', attrs: {} }], carriedMarkNames(fullSchema()))

        expect(plan.unset).toContain('italic')
        expect(plan.unset).toContain('fontSize')
        expect(plan.unset).not.toContain('link')
        expect(plan.unset).not.toContain('language')
        expect(plan.unset).not.toContain('code')
        expect(plan.set).toEqual([{ name: 'bold', attrs: {} }])
    })

    it('takes the formatting away where nothing was picked up', () => {
        // Which is not reachable through the button - it refuses to arm on an empty pick -
        // but the command is public, and a plan that silently did nothing would be a worse
        // answer than one that clears.
        expect(applicationFor([], ['bold', 'italic']).unset).toEqual(['bold', 'italic'])
    })
})

/**
 * The half that runs rather than the half that decides.
 *
 * `Extension.create` hands TipTap a plain object, so the object is what a test can hold:
 * `addStorage()`, `addCommands()` and `addProseMirrorPlugins()` are called here exactly the
 * way TipTap calls them, against an editor double that records what was asked of it. That
 * is the whole of the state machine, the stroke and the three DOM handlers - which is where
 * every one of this feature's own defects has been found so far.
 */

/** What the module reaches for on `window`, and nothing beyond it. */
const exposeTiptap = () => {
    window.FilamentRichEditor = {
        tiptap: {
            core: { Extension: { create: (config) => config } },
            pmState: {
                Plugin: class {
                    constructor(spec) {
                        Object.assign(this, spec)
                    }
                },
                PluginKey: class {
                    constructor(name) {
                        this.name = name
                    }
                },
            },
        },
    }
}

/**
 * An editor as the commands use one: storage, a state to read, a DOM element whose classes
 * are watched, a view that records dispatches, and a chain that records the stroke.
 */
const editorDouble = ({ state, runs = true } = {}) => {
    const dom = document.createElement('div')
    const calls = []

    const stroke = new Proxy(
        {},
        {
            get:
                (_, name) =>
                (...args) => {
                    calls.push([name, ...args])

                    return name === 'run' ? runs : stroke
                },
        },
    )

    const dispatched = []

    return {
        storage: { arteFormatBrush: { picked: null, mode: null } },
        schema: state?.schema,
        state,
        isDestroyed: false,
        dispatched,
        calls,
        dom,
        view: {
            dom,
            dispatch: (transaction) => dispatched.push(transaction),
        },
        chain: () => stroke,
        commands: {},
    }
}

const commandsOf = (editor) => {
    const config = formatBrushExtension()
    const commands = config.addCommands()

    return {
        config,
        cycle: () => commands.cycleFormatBrush()({ editor, chain: editor.chain }),
        apply: () => commands.applyFormatBrush()({ editor, chain: editor.chain }),
        clear: () => commands.clearFormatBrush()({ editor, chain: editor.chain }),
    }
}

const bold = () => stateOf({ leaves: { 0: [leaf([mark('bold')])] } })

describe('the runtime half', () => {
    beforeEach(() => {
        exposeTiptap()
        vi.stubGlobal('setTimeout', (callback) => {
            callback()

            return 1
        })
    })

    afterEach(() => {
        delete window.FilamentRichEditor
    })

    it('says so rather than throwing where Filament exposed nothing', () => {
        delete window.FilamentRichEditor
        const error = vi.spyOn(console, 'error').mockImplementation(() => {})

        expect(formatBrushExtension()).toBeNull()
        expect(error).toHaveBeenCalled()
    })

    it('starts with nothing picked up', () => {
        expect(formatBrushExtension().addStorage()).toEqual({ picked: null, mode: null, escape: null })
    })

    it('refuses to arm where there is nothing to take', () => {
        // Which is what makes the button's own state exact: `picked` is either null or a
        // set with something in it, so the expression that lights it is the truth rather
        // than an approximation. An editor left armed on an empty set would light a button
        // whose stroke does nothing.
        const editor = editorDouble({ state: stateOf({ leaves: { 0: [leaf([])] } }) })

        expect(commandsOf(editor).cycle()).toBe(false)
        expect(editor.storage.arteFormatBrush.picked).toBeNull()
        expect(editor.storage.arteFormatBrush.mode).toBeNull()
        expect(editor.dom.classList.contains('fi-arte-brush-armed')).toBe(false)
    })

    it('goes round the three states and paints the editor while it is armed', () => {
        const editor = editorDouble({ state: bold() })
        const { cycle } = commandsOf(editor)

        expect(cycle()).toBe(true)
        expect(editor.storage.arteFormatBrush).toEqual({ picked: [{ name: 'bold', attrs: {} }], mode: 'once' })
        expect(editor.dom.classList.contains('fi-arte-brush-armed')).toBe(true)

        cycle()
        expect(editor.storage.arteFormatBrush.mode).toBe('sticky')
        // The same set, not a fresh reading: the second click means "keep it", not "take
        // whatever is under the caret now".
        expect(editor.storage.arteFormatBrush.picked).toEqual([{ name: 'bold', attrs: {} }])

        cycle()
        expect(editor.storage.arteFormatBrush.picked).toBeNull()
        expect(editor.storage.arteFormatBrush.mode).toBeNull()
        expect(editor.dom.classList.contains('fi-arte-brush-armed')).toBe(false)
    })

    it('tells the button to look again every time it changes', () => {
        // Filament wraps a tool's active expression as `editorUpdatedAt && (...)`, and that
        // is bumped by `editor.on('transaction')` - so a transaction carrying no steps is
        // what repaints a button for a change the document knows nothing about. Without it
        // the brush arms and the button stays dark.
        const editor = editorDouble({ state: bold() })
        const { cycle, clear } = commandsOf(editor)

        cycle()
        expect(editor.dispatched).toHaveLength(1)

        clear()
        expect(editor.dispatched).toHaveLength(2)
    })

    it('puts the formatting down and stands down again, once', () => {
        const editor = editorDouble({ state: bold() })
        const { cycle, apply } = commandsOf(editor)

        cycle()
        // A target already carrying something else, so there is a removal to see.
        editor.state = stateOf({ leaves: { 0: [leaf([mark('italic'), mark('bold')])] } })
        editor.calls.length = 0

        expect(apply()).toBe(true)

        expect(editor.calls).toContainEqual(['setMark', 'bold', {}])
        expect(editor.calls).toContainEqual(['unsetMark', 'italic'])
        expect(editor.calls.map(([name]) => name)).toContain('focus')
        // Never the three it refuses, which is what spares a link on the target.
        expect(editor.calls.filter(([name, mark]) => name === 'unsetMark' && ['link', 'language', 'code'].includes(mark)))
            .toEqual([])

        expect(editor.storage.arteFormatBrush.picked).toBeNull()
        expect(editor.storage.arteFormatBrush.mode).toBeNull()
        expect(editor.dom.classList.contains('fi-arte-brush-armed')).toBe(false)
    })

    it('takes off only what the target is really wearing', () => {
        // Told nothing about the target, a stroke issues an `unsetMark` for every mark it
        // could carry - a dozen steps in the transaction to remove eleven things that were
        // never there. Brushing bold onto text that is already bold should ask for one
        // thing and no removals at all.
        const editor = editorDouble({ state: bold() })
        const { cycle, apply } = commandsOf(editor)

        cycle()
        editor.calls.length = 0
        apply()

        expect(editor.calls.filter(([name]) => name === 'unsetMark')).toEqual([])
    })

    it('stays armed after a stroke where it was armed for good', () => {
        const editor = editorDouble({ state: bold() })
        const { cycle, apply } = commandsOf(editor)

        cycle()
        cycle()
        apply()

        expect(editor.storage.arteFormatBrush.mode).toBe('sticky')
        expect(editor.storage.arteFormatBrush.picked).toEqual([{ name: 'bold', attrs: {} }])
    })

    it('paints nothing where there is nothing to paint on', () => {
        const armed = editorDouble({ state: bold() })
        commandsOf(armed).cycle()

        // A caret rather than a selection: `setMark` there sets `storedMarks` and changes
        // nothing anybody can see, then evaporates on the next click.
        armed.state = stateOf({ empty: true, caretMarks: [] })
        expect(commandsOf(armed).apply()).toBe(false)

        // And a selected picture, for the reason the pick-up refuses one.
        armed.state = stateOf({ node: { type: { name: 'image' } } })
        expect(commandsOf(armed).apply()).toBe(false)

        // Never armed at all.
        const idle = editorDouble({ state: bold() })
        expect(commandsOf(idle).apply()).toBe(false)
    })

    it('stays armed where the stroke did not take', () => {
        const editor = editorDouble({ state: bold(), runs: false })
        const { cycle, apply } = commandsOf(editor)

        cycle()

        expect(apply()).toBe(false)
        expect(editor.storage.arteFormatBrush.mode).toBe('once')
    })

    it('has nothing to clear when it is not armed', () => {
        const editor = editorDouble({ state: bold() })

        expect(commandsOf(editor).clear()).toBe(false)
        expect(editor.dispatched).toHaveLength(0)
    })
})

const handlersFor = (editor) => {
    const config = formatBrushExtension()
    const commands = config.addCommands()

    editor.commands = {
        applyFormatBrush: () => commands.applyFormatBrush()({ editor, chain: editor.chain }),
        clearFormatBrush: () => commands.clearFormatBrush()({ editor, chain: editor.chain }),
    }

    // A *stale* storage on the context, which is what TipTap really hands a plugin -
    // measured in a running panel, with `mode: 'once'` on `editor.storage.arteFormatBrush`
    // and `null` on the context's own copy at the same moment. The first draft of this
    // double passed the live object in, which is why it stayed green while the only
    // gesture that paints did nothing at all.
    const [plugin] = config.addProseMirrorPlugins.call({ storage: { picked: null, mode: null }, editor })

    return plugin.props.handleDOMEvents
}

const armed = () => {
    const editor = editorDouble({ state: bold() })
    const config = formatBrushExtension()

    config.addCommands().cycleFormatBrush()({ editor, chain: editor.chain })

    return editor
}

describe('what finishes a stroke', () => {
    beforeEach(() => {
        exposeTiptap()
        vi.stubGlobal('setTimeout', (callback) => {
            callback()

            return 1
        })
    })

    afterEach(() => {
        delete window.FilamentRichEditor
    })

    it('paints when a selection is let go of', () => {
        // On mouseup rather than on every selection change: a drag reports a new selection
        // per pixel, and painting each one would colour the text as the pointer crossed it.
        const editor = armed()
        const handlers = handlersFor(editor)

        editor.calls.length = 0
        expect(handlers.mouseup(editor.view, { button: 0 })).toBe(false)

        expect(editor.calls.map(([name]) => name)).toContain('setMark')
    })

    it('does not paint on a right-click', () => {
        // A right-click releases a button too. Painting on it edits the document of
        // somebody who reached for the context menu, and in one-stroke mode disarms the
        // brush afterwards - so the stroke they never asked for is the one they cannot
        // repeat. Found by reading the handler, not by using it.
        const editor = armed()
        const handlers = handlersFor(editor)

        editor.calls.length = 0
        handlers.mouseup(editor.view, { button: 2 })

        expect(editor.calls).toEqual([])
        expect(editor.storage.arteFormatBrush.mode).toBe('once')
    })

    it('leaves the document alone on a mouseup where nothing is armed', () => {
        const editor = editorDouble({ state: bold() })
        const handlers = handlersFor(editor)

        handlers.mouseup(editor.view, { button: 0 })

        expect(editor.calls).toEqual([])
    })

    it('puts the brush away on Escape, and only then', () => {
        const editor = armed()
        const handlers = handlersFor(editor)

        // Handled, so the key stops here: a modal above the field would otherwise close on
        // the same press.
        expect(handlers.keydown(editor.view, { key: 'Escape' })).toBe(true)
        expect(editor.storage.arteFormatBrush.picked).toBeNull()
        expect(editor.storage.arteFormatBrush.mode).toBeNull()

        // Not armed, so the key is somebody else's.
        expect(handlers.keydown(editor.view, { key: 'Escape' })).toBe(false)
        expect(handlers.keydown(editor.view, { key: 'a' })).toBe(false)
    })
})

describe('what the fixes are made of', () => {
    beforeEach(() => {
        exposeTiptap()
        vi.stubGlobal('setTimeout', (callback) => {
            callback()

            return 1
        })
    })

    afterEach(() => {
        delete window.FilamentRichEditor
    })

    it('walks past a node that names the marks it holds', () => {
        // `marks: ""` is a total ban and `marks: "_"` is the default, everything. Anything
        // else is a list, and what is inside such a node is not what the rest of the
        // selection means by formatting - carrying one out of it lands nowhere on a target
        // that does not allow it. Nothing in the shipped schema declares a list today; the
        // brush reads whatever schema the field has, a plugin's own nodes included.
        const listed = { node: { isText: true, marks: [mark('italic')] }, parent: { type: { spec: { marks: 'bold italic' } } } }
        const anything = { node: { isText: true, marks: [mark('italic')] }, parent: { type: { spec: { marks: '_' } } } }

        expect(pickFrom(stateOf({ leaves: { 0: [leaf([mark('bold')]), listed] } })))
            .toEqual([{ name: 'bold', attrs: {} }])

        // The default is not a restriction, so a node that says "any" is walked into.
        expect(pickFrom(stateOf({ leaves: { 0: [leaf([mark('bold')]), anything] } }))).toBeNull()
    })

    it('reads back what the selection is really wearing', () => {
        const state = stateOf({
            leaves: { 0: [leaf([mark('bold'), mark('link', { href: 'x' })]), leaf([mark('italic')])] },
        })

        // The union rather than the intersection - the question is what a stroke would have
        // to take off, and one mark on one word is enough to need removing.
        expect(marksInSelection(state).sort()).toEqual(['bold', 'italic'])
        // Never the refused ones, which is what leaves a link on the target alone.
        expect(marksInSelection(state)).not.toContain('link')
    })

    it('asks for a removal only where there is something to remove', () => {
        expect(applicationFor([{ name: 'bold', attrs: {} }], ['bold', 'italic', 'small'], ['italic']))
            .toEqual({ unset: ['italic'], set: [{ name: 'bold', attrs: {} }] })

        // Told nothing, it still clears the whole carried set: the argument is an
        // improvement on the answer, not a change to what the method means.
        expect(applicationFor([], ['bold', 'italic']).unset).toEqual(['bold', 'italic'])
    })

    it('finishes a stroke on the keys that move a selection, and no others', () => {
        const editor = armed()
        const handlers = handlersFor(editor)

        editor.calls.length = 0
        handlers.keyup(editor.view, { shiftKey: true, key: 'A' })
        expect(editor.calls).toEqual([])

        handlers.keyup(editor.view, { shiftKey: false, key: 'ArrowRight' })
        expect(editor.calls).toEqual([])

        handlers.keyup(editor.view, { shiftKey: true, key: 'ArrowRight' })
        expect(editor.calls.map(([name]) => name)).toContain('setMark')
    })

    it('paints once for a gesture that reports itself twice', () => {
        // A shift-click releases a button and a key, and both handlers ask for the same
        // stroke. Armed for good, that painted twice - one gesture, two presses of undo.
        const editor = armed()
        const handlers = handlersFor(editor)

        // Armed for good, so the first stroke does not disarm and hide the second.
        editor.storage.arteFormatBrush.mode = 'sticky'
        editor.calls.length = 0

        handlers.mouseup(editor.view, { button: 0 })
        handlers.keyup(editor.view, { shiftKey: true, key: 'ArrowRight' })

        expect(editor.calls.filter(([name]) => name === 'run')).toHaveLength(1)
    })

    it('hears Escape from wherever the focus went', () => {
        // The plugin handles it inside the editor, which is where a caret usually is. The
        // brush is armed from a button, though, and a toolbar menu or a tab press leaves
        // the focus somewhere the editor's own handlers never see.
        const editor = armed()
        const config = formatBrushExtension()
        const context = { storage: editor.storage.arteFormatBrush, editor }

        editor.commands = {
            clearFormatBrush: () => config.addCommands().clearFormatBrush()({ editor, chain: editor.chain }),
        }

        config.onCreate.call(context)
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))

        expect(editor.storage.arteFormatBrush.mode).toBeNull()

        config.onDestroy.call(context)
    })

    it('lets go of the keys it borrowed from the document', () => {
        const editor = armed()
        const config = formatBrushExtension()
        const context = { storage: editor.storage.arteFormatBrush, editor }
        const remove = vi.spyOn(document, 'removeEventListener')

        config.onCreate.call(context)
        config.onDestroy.call(context)

        expect(remove).toHaveBeenCalledWith('keydown', expect.any(Function))
        expect(context.storage.escape).toBeNull()
    })

    it('puts the brush away when the document under it is replaced', () => {
        // The marks would still apply - they are names and attributes, not positions - so
        // nothing breaks. What would be wrong is a lit button and a copy cursor pointing
        // back at text that is gone.
        const editor = armed()
        const config = formatBrushExtension()

        editor.commands = {
            clearFormatBrush: () => config.addCommands().clearFormatBrush()({ editor, chain: editor.chain }),
        }

        const replacement = { before: { content: { size: 40 } }, steps: [{ from: 0, to: 40 }] }
        const edit = { before: { content: { size: 40 } }, steps: [{ from: 5, to: 9 }] }

        config.onUpdate.call({ editor }, { transaction: edit })
        expect(editor.storage.arteFormatBrush.mode).toBe('once')

        config.onUpdate.call({ editor }, { transaction: replacement })
        expect(editor.storage.arteFormatBrush.mode).toBeNull()
    })
})
