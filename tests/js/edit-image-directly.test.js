import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * The Edit image dialog, which goes straight into Filament's image editor and straight back.
 *
 * The script is the one `EditImageAction::OPENS_DIRECTLY` holds, read out of the PHP class
 * rather than copied here, so what runs is what ships. It runs where Alpine runs it: on
 * Filament's upload component, with `$data` that component and `$wire` the page.
 */

const php = readFileSync(resolve(process.cwd(), 'src/RichEditor/Actions/EditImageAction.php'), 'utf8')
const script = php.match(/OPENS_DIRECTLY = <<<'JS'\n([\s\S]*?)\n\s*JS;/)[1]

/** FilePond's origins: the picture the dialog opened on, and the one the editor saved. */
const LOCAL = 3
const INPUT = 1

let window_
let upload
let data
let wire
let watched

const start = () =>
    new Function('$el', '$data', '$wire', '$watch', script)(upload, data, wire, (name, callback) => {
        watched[name] = callback
    })

const picture = (type = 'image/png') => new File(['x'], 'hafen.png', { type })

const pond = (name, detail) => upload.dispatchEvent(new CustomEvent(`FilePond:${name}`, { detail }))

const isUnseen = () => window_.classList.contains('fi-arte-image-editor-pending')

beforeEach(() => {
    document.body.innerHTML = ''

    window_ = document.createElement('div')
    window_.className = 'fi-modal-window fi-arte-image-editor-pending'
    upload = document.createElement('div')
    window_.append(upload)
    document.body.append(window_)

    data = { loadEditor: vi.fn(), pond: { getFiles: () => [] } }
    wire = { callMountedAction: vi.fn(), unmountAction: vi.fn() }
    watched = {}

    start()
})

describe('on the way in', () => {
    it('opens the editor on the picture the moment it has loaded, still unseen', () => {
        const file = picture()

        pond('addfile', { file: { origin: LOCAL, file } })

        expect(data.loadEditor).toHaveBeenCalledWith(file)
        expect(isUnseen()).toBe(true)
    })

    it('shows the dialog once the editor covers it', () => {
        watched.isEditorOpen(true)

        expect(isUnseen()).toBe(false)
    })

    it('does not open the editor again on the picture it saved', () => {
        pond('addfile', { file: { origin: INPUT, file: picture() } })

        expect(data.loadEditor).not.toHaveBeenCalled()
    })
})

describe('on the way out', () => {
    it('applies the edit as soon as it is uploaded', () => {
        pond('processfile', { file: { origin: INPUT } })

        expect(wire.callMountedAction).toHaveBeenCalledOnce()
    })

    it('closes the dialog when the editor is cancelled', () => {
        watched.isEditorOpen(false)

        expect(wire.unmountAction).toHaveBeenCalledOnce()
    })

    it('stays open while the saved picture uploads, showing how far it is', () => {
        data.pond.getFiles = () => [{ origin: INPUT }]

        watched.isEditorOpen(false)

        expect(wire.unmountAction).not.toHaveBeenCalled()
    })
})

describe('where the editor cannot open on its own', () => {
    it('shows the dialog where the picture did not load', () => {
        pond('addfile', { error: { main: 'Error during load' }, file: { origin: LOCAL } })

        expect(isUnseen()).toBe(false)
        expect(data.loadEditor).not.toHaveBeenCalled()
    })

    it('shows the dialog where what loaded is not a picture', () => {
        // A page answering 404, which Filament takes for the file.
        pond('addfile', { file: { origin: LOCAL, file: picture('text/html') } })

        expect(isUnseen()).toBe(false)
        expect(data.loadEditor).not.toHaveBeenCalled()
    })

    it('shows the dialog for a drawing, which Filament asks about before opening it', () => {
        pond('addfile', { file: { origin: LOCAL, file: picture('image/svg+xml') } })

        expect(isUnseen()).toBe(false)
        expect(data.loadEditor).toHaveBeenCalledOnce()
    })

    it('shows the dialog rather than applying where the upload failed', () => {
        pond('processfile', { error: { main: 'File is too large' }, file: { origin: INPUT } })

        expect(isUnseen()).toBe(false)
        expect(wire.callMountedAction).not.toHaveBeenCalled()
    })
})
