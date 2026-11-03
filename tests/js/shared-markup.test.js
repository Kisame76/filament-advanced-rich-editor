import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { afterEach, describe, expect, it, vi } from 'vitest'

/**
 * The template that stamps out the bars a page's editors share.
 *
 * The script is the one `SharedMarkup` writes into the template's `x-init`, read out of the
 * PHP class rather than copied here, so what runs is what ships. It runs where Alpine runs it:
 * with the template as `$el`, and Alpine to hand the stamp to.
 */

const php = readFileSync(resolve(process.cwd(), 'src/RichEditor/SharedMarkup.php'), 'utf8')
const script = php.match(/<<<'JS'\n([\s\S]*?)\n\s*JS;/)[1]

const Alpine = { initTree: vi.fn() }

const run = (template) => new Function('$el', 'Alpine', script)(template, Alpine)

/** A template as the server writes it: carrying the bars, or only naming them. */
const template = ({ key = 'k1', carries = false } = {}) => {
    const element = document.createElement('template')

    element.dataset.arteMarkup = key

    if (carries) {
        element.innerHTML =
            '<div data-arte-stamped style="display: contents"><div x-ref="floatingToolbar::image"><button>Alt</button></div></div>'
    }

    const editor = document.createElement('div')

    editor.append(element)
    document.body.append(editor)

    return element
}

const stampAfter = (element) => element.nextElementSibling

afterEach(() => {
    document.body.innerHTML = ''
    delete window.arteSharedMarkup
    Alpine.initTree.mockClear()
    vi.restoreAllMocks()
})

describe('stamping', () => {
    it('stamps a copy of what it carries right after itself, and hands it to Alpine', () => {
        const carrier = template({ carries: true })

        run(carrier)

        const stamp = stampAfter(carrier)

        expect(stamp.hasAttribute('data-arte-stamped')).toBe(true)
        expect(stamp.querySelector('[x-ref="floatingToolbar::image"]')).not.toBeNull()
        expect(Alpine.initTree).toHaveBeenCalledWith(stamp)
        // A copy: the template keeps its markup for every editor after it.
        expect(carrier.content.querySelector('[x-ref="floatingToolbar::image"]')).not.toBeNull()
    })

    it('stamps what another editor carries when it only names it', () => {
        // Editors do not start in the order they stand in, so the carrier may not have run yet.
        template({ carries: true })
        const named = template()

        run(named)

        expect(stampAfter(named).querySelector('button').textContent).toBe('Alt')
    })

    it('still stamps once the editor that carried it has gone', () => {
        // A repeater item deleted: the store keeps what the page sent.
        const carrier = template({ carries: true })

        run(carrier)
        carrier.parentElement.remove()

        const named = template()

        run(named)

        expect(stampAfter(named).querySelector('[x-ref="floatingToolbar::image"]')).not.toBeNull()
    })

    it('takes only a template that carries something, not the first one with the name', () => {
        const first = template()
        template({ carries: true })

        run(first)

        expect(stampAfter(first)?.hasAttribute('data-arte-stamped')).toBe(true)
    })

    it('replaces a stamp that is already there instead of adding a second', () => {
        // What a page brought back by `wire:navigate` has: its snapshot is the DOM after Alpine.
        const carrier = template({ carries: true })

        run(carrier)
        run(carrier)

        expect(carrier.parentElement.querySelectorAll('[data-arte-stamped]')).toHaveLength(1)
    })

    it('says so and stamps nothing when the markup is nowhere to be found', () => {
        const error = vi.spyOn(console, 'error').mockImplementation(() => {})
        const named = template({ key: 'missing' })

        run(named)

        expect(stampAfter(named)).toBeNull()
        expect(Alpine.initTree).not.toHaveBeenCalled()
        expect(error).toHaveBeenCalledWith(expect.stringContaining('missing'))
    })

    it('keeps each name to its own markup', () => {
        template({ key: 'a', carries: true })
        const other = template({ key: 'b' })
        vi.spyOn(console, 'error').mockImplementation(() => {})

        run(other)

        expect(stampAfter(other)).toBeNull()
    })
})
