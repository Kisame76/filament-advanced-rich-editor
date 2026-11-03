/**
 * What a component needs to be run outside Alpine.
 *
 * The factory returns a plain object, and everything Alpine would add to it - `$watch`,
 * `$root` - is added here instead. `$watch` records rather than reacts: there is no
 * reactivity engine in these tests, so a watcher is fired by name from the test that wants
 * to see what it does. That is the honest shape of the assertion anyway - what is being
 * checked is which watcher was registered and what it does, not whether Alpine works.
 */
export function mount(factory, config = {}, { root = null } = {}) {
    const watchers = {}
    const dispatched = []

    const component = factory({
        labels: { sorts: {} },
        hasFolders: false,
        listView: false,
        pageSize: 40,
        picked: null,
        fetchPage: async () => ({}),
        fetchDetails: async () => null,
        saveMetadata: async () => true,
        deleteMedia: async () => true,
        replaceMedia: async () => ({ replaced: false }),
        canDelete: true,
        canReplace: true,
        shared: false,
        ...config,
    })

    Object.defineProperties(component, {
        $watch: {
            value: (property, callback) => {
                watchers[property] = callback
            },
        },
        $root: { value: root ?? document.createElement('div'), writable: true },
        // Recorded rather than sent: what a test wants to know is which event the component
        // asked for, and to whom it would have gone.
        $dispatch: { value: (name, detail) => dispatched.push({ name, detail }) },
    })

    component.watchers = watchers
    component.dispatched = dispatched

    component.trigger = (property, value) => watchers[property]?.(value)

    return component
}

/**
 * A media row as the server describes one.
 */
export function item(attributes = {}) {
    return {
        id: 'one',
        url: 'https://example.test/one.jpg',
        name: 'one.jpg',
        mime: 'image/jpeg',
        size: 2048,
        width: 800,
        height: 600,
        kind: 'image',
        alt: null,
        title: null,
        pending: false,
        ...attributes,
    }
}

/*
 * Documents built the way ProseMirror counts one: a text node is as long as its text, a leaf
 * is one, and every other node is its content plus an opening and a closing token. Getting
 * those positions right is the whole job of the code that reads them - a mark one position off
 * sits on the wrong letter.
 */
export const textNode = (value) => ({
    isText: true,
    isTextblock: false,
    text: value,
    nodeSize: value.length,
    type: { name: 'text', spec: {} },
})

export const leafNode = (name) => ({ isText: false, isTextblock: false, nodeSize: 1, type: { name, spec: {} }, children: [] })

export const blockNode = (name, children = [], { textblock = false, code = false } = {}) => ({
    isText: false,
    isTextblock: textblock,
    type: { name, spec: code ? { code: true } : {} },
    children,
    nodeSize: children.reduce((size, child) => size + child.nodeSize, 0) + 2,
})

/**
 * `descendants()`, `nodesBetween()` and `forEach()` as ProseMirror defines them, over the
 * nodes above. `walked` counts how often a node's inline content was read, which is how a
 * test sees that a paragraph nobody touched was left alone.
 */
export function walkable(target) {
    const children = (target.children ?? []).map(walkable)

    return {
        ...target,
        children,
        walked: 0,
        content: { size: children.reduce((size, child) => size + child.nodeSize, 0) },
        nodesBetween(from, to, callback, start = 0) {
            let pos = start

            for (const child of children) {
                const end = pos + child.nodeSize

                if (end > from && pos < to && callback(child, pos) !== false && child.children.length > 0) {
                    child.nodesBetween(from, to, callback, pos + 1)
                }

                pos = end
            }
        },
        forEach(callback) {
            this.walked++

            let offset = 0

            for (const child of children) {
                callback(child, offset)
                offset += child.nodeSize
            }
        },
        descendants(callback) {
            const visit = (parent, start) => {
                let pos = start

                for (const child of parent.children) {
                    if (callback(child, pos, parent) !== false && child.children.length > 0) {
                        visit(child, pos + 1)
                    }

                    pos += child.nodeSize
                }
            }

            visit(this, 0)
        },
    }
}

export const documentOf = (...children) => walkable({ ...blockNode('doc', children), nodeSize: undefined })
