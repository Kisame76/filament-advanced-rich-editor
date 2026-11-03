import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

/**
 * What the buttons standing on a picture hand the library dialog.
 *
 * The expression is the one `MediaLibraryAction::PICTURE_ARGUMENTS` holds, read out of the PHP
 * class rather than copied here, so what runs is what ships. Filament spreads it into the
 * arguments it mounts the dialog with, `{ editorSelection, ...expression }`, with `$getEditor`
 * in scope.
 */

const php = readFileSync(resolve(process.cwd(), 'src/RichEditor/Actions/MediaLibraryAction.php'), 'utf8')
const expression = php.match(/PICTURE_ARGUMENTS = <<<'JS'\n([\s\S]*?)\n\s*JS;/)[1].trim()

const mounted = (attributes, wrapped = expression) =>
    new Function('$getEditor', 'editorSelection', `return { editorSelection, ...${wrapped} }`)(
        () => ({ getAttributes: (type) => (type === 'image' ? attributes : {}) }),
        { type: 'node', anchor: 4 },
    )

describe('the arguments', () => {
    it('carries the file, the description and the size the picture is drawn at', () => {
        expect(mounted({ id: 'library/wide.png', src: '/storage/library/wide.png', alt: 'Hafen', width: 320, height: 180, loading: 'lazy' })).toEqual({
            editorSelection: { type: 'node', anchor: 4 },
            id: 'library/wide.png',
            src: '/storage/library/wide.png',
            alt: 'Hafen',
            width: 320,
            height: 180,
        })
    })

    it('carries nothing of a picture where the caret is not on one', () => {
        expect(mounted({})).toEqual({
            editorSelection: { type: 'node', anchor: 4 },
            id: undefined,
            src: undefined,
            alt: undefined,
            width: undefined,
            height: undefined,
        })
    })

    it('fits beside the family the Replace button names', () => {
        // How `imageReplace` writes it.
        expect(mounted({ id: 'library/wide.png', width: 320, height: 180 }, `{ kind: 'image', ...${expression} }`)).toMatchObject({
            kind: 'image',
            id: 'library/wide.png',
            width: 320,
            height: 180,
        })
    })
})
