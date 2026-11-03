import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

/**
 * What the Edit image button hands its dialog.
 *
 * The expression is the one `EditImageAction::ARGUMENTS` holds, read out of the PHP class
 * rather than copied here, so what runs is what ships. It runs where the button runs it, with
 * `$getEditor` in scope.
 */

const php = readFileSync(resolve(process.cwd(), 'src/RichEditor/Actions/EditImageAction.php'), 'utf8')
const script = php.match(/ARGUMENTS = <<<'JS'\n([\s\S]*?)\n\s*JS;/)[1]

const run = (editor) => new Function('$getEditor', `return ${script}`)(() => editor)

/** A picture `naturalWidth` pixels wide, which jsdom never loads and so has to be told. */
const picture = (naturalWidth) => {
    const image = document.createElement('img')

    Object.defineProperty(image, 'naturalWidth', { value: naturalWidth })

    return image
}

const editor = (drawn) => ({
    getAttributes: (type) => (type === 'image' ? { src: '/storage/hafen.png', width: 320, height: 200, alt: 'Hafen' } : {}),
    state: { selection: { from: 7 } },
    view: { nodeDOM: (position) => (position === 7 ? drawn : null) },
})

describe('the arguments', () => {
    it('carries the address and the size of the selected picture', () => {
        expect(run(editor(picture(320)))).toEqual({
            src: '/storage/hafen.png',
            width: 320,
            height: 200,
            naturalWidth: 320,
        })
    })

    it('reads how wide the file is off the picture inside a resizable one', () => {
        // A picture that can be dragged is drawn inside a container with its handles.
        const container = document.createElement('div')

        container.append(picture(1600))

        expect(run(editor(container)).naturalWidth).toBe(1600)
    })

    it('says nothing about the file where nothing is drawn', () => {
        expect(run(editor(null)).naturalWidth).toBeUndefined()
    })
})
