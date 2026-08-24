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

    const component = factory({
        labels: { sorts: {} },
        hasFolders: false,
        listView: false,
        pageSize: 40,
        picked: null,
        fetchPage: async () => ({}),
        fetchDetails: async () => null,
        ...config,
    })

    Object.defineProperties(component, {
        $watch: {
            value: (property, callback) => {
                watchers[property] = callback
            },
        },
        $root: { value: root ?? document.createElement('div'), writable: true },
    })

    component.watchers = watchers

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
        pending: false,
        ...attributes,
    }
}
