import { describe, expect, it, vi } from 'vitest'
import { accepts, settingsOf, takes, uploadAll, uploadKey } from '../../resources/js/document-upload.js'

/**
 * A document dropped or pasted into a field without a media browser.
 *
 * What is decided here is whether this handler takes a drop at all - everything it does not
 * take falls through to Filament's own, which then inserts a picture - and that what it takes
 * ends up in the document in the order it came, with the field told it was busy however the
 * uploads went. The node an upload becomes is the field's answer and is asserted in
 * `DocumentUploadTest.php`.
 */
const element = (settings) => {
    const node = document.createElement('div')

    if (settings !== undefined) {
        node.dataset.arteDocumentUpload = typeof settings === 'string' ? settings : JSON.stringify(settings)
    }

    return node
}

const settings = {
    key: 'form.content',
    statePath: 'data.content',
    acceptedTypes: ['image/png', 'application/pdf'],
    maxSize: 10,
    uploadingMessage: 'Uploading file...',
}

const file = (type, size = 100) => ({ type, size, name: `file.${type.split('/')[1]}` })

describe('the settings', () => {
    it('reads what the field handed over', () => {
        expect(settingsOf(element(settings))).toEqual(settings)
    })

    it('reads no list as any type and no limit as none', () => {
        expect(settingsOf(element({ ...settings, acceptedTypes: null, maxSize: null }))).toMatchObject({
            acceptedTypes: null,
            maxSize: null,
        })
    })

    it('stays inert where there are none, or none it can read', () => {
        expect(settingsOf(element())).toBeNull()
        expect(settingsOf(element('{not json'))).toBeNull()
        expect(settingsOf(element({ statePath: 'data.content' }))).toBeNull()
        expect(settingsOf(null)).toBeNull()
    })
})

describe('which drops it takes', () => {
    const read = settingsOf(element(settings))

    it('takes a document the field accepts', () => {
        expect(takes([file('application/pdf')], read)).toBe(true)
    })

    it('takes a document together with pictures, so they land in the order they came', () => {
        expect(takes([file('image/png'), file('application/pdf')], read)).toBe(true)
    })

    it('leaves pictures alone to Filament', () => {
        expect(takes([file('image/png'), file('image/png')], read)).toBe(false)
    })

    it('leaves anything the field refuses to Filament, whose message that is', () => {
        expect(takes([file('application/zip')], read)).toBe(false)
        expect(takes([file('application/pdf'), file('application/zip')], read)).toBe(false)
        // Ten kilobytes, measured the way Filament measures them.
        expect(takes([file('application/pdf', 10 * 1024 + 1)], read)).toBe(false)
        expect(accepts(file('application/pdf', 10 * 1024), read)).toBe(true)
    })

    it('takes nothing without settings or without files', () => {
        expect(takes([file('application/pdf')], null)).toBe(false)
        expect(takes([], read)).toBe(false)
    })

    it('takes any type where the field names none', () => {
        expect(takes([file('application/zip')], { ...read, acceptedTypes: null })).toBe(true)
    })
})

describe('the upload', () => {
    it('names every upload apart, and never with the browser prefix', () => {
        const keys = new Set(Array.from({ length: 20 }, uploadKey))

        expect(keys.size).toBe(20)

        for (const key of keys) {
            expect(key).toMatch(/^[0-9a-f-]{36}$/)
            expect(key.startsWith('arte-')).toBe(false)
        }
    })

    const harness = ({ fails = [] } = {}) => {
        const form = document.createElement('form')
        const dom = document.createElement('div')
        form.appendChild(dom)

        const inserted = []
        const events = []

        for (const name of ['form-processing-started', 'form-processing-finished']) {
            form.addEventListener(name, () => events.push(name))
        }

        for (const name of ['rich-editor-uploading-file', 'rich-editor-uploaded-file']) {
            dom.addEventListener(name, (event) => events.push(`${name}:${event.detail.key}:${event.detail.livewireId}`))
        }

        let uploads = 0

        const wire = {
            id: 'lw-1',
            upload: vi.fn((_path, _file, finish, error) => {
                uploads++

                if (fails.includes(uploads)) {
                    error()
                } else {
                    finish()
                }
            }),
            callSchemaComponentMethod: vi.fn(async (_key, _method, { attachment }) => ({
                type: 'file',
                attrs: { id: attachment },
            })),
        }

        const editor = {
            isEditable: true,
            isDestroyed: false,
            setEditable: vi.fn(function (value) {
                this.isEditable = value
            }),
            chain: () => ({
                insertContentAt: (position, nodes) => ({
                    run: () => inserted.push({ position, nodes, editable: editor.isEditable }),
                }),
            }),
        }

        return { wire, editor, view: { dom }, inserted, events }
    }

    it('uploads each file where Filament files its own, and inserts what each became, in order', async () => {
        const { wire, editor, view, inserted } = harness()

        await uploadAll({
            wire,
            editor,
            view,
            files: [file('application/pdf'), file('image/png')],
            settings,
            position: () => 7,
        })

        const paths = wire.upload.mock.calls.map(([path]) => path)

        expect(paths).toHaveLength(2)
        expect(paths[0]).toMatch(/^componentFileAttachments\.data\.content\.[0-9a-f-]{36}$/)
        expect(wire.callSchemaComponentMethod.mock.calls.map(([key, method]) => [key, method])).toEqual([
            ['form.content', 'getUploadedFileAttachmentNodeForJs'],
            ['form.content', 'getUploadedFileAttachmentNodeForJs'],
        ])

        // One insertion, both nodes, in the order they were dropped.
        expect(inserted).toHaveLength(1)
        expect(inserted[0].position).toBe(7)
        expect(inserted[0].nodes.map((node) => node.attrs.id)).toEqual(paths.map((path) => path.split('.').pop()))
    })

    it('says it is busy while it works and done afterwards, and gives the editor back', async () => {
        const { wire, editor, view, events } = harness()

        await uploadAll({ wire, editor, view, files: [file('application/pdf')], settings, position: () => 1 })

        expect(events).toEqual([
            'form-processing-started',
            'rich-editor-uploading-file:form.content:lw-1',
            'rich-editor-uploaded-file:form.content:lw-1',
            'form-processing-finished',
        ])
        expect(editor.setEditable.mock.calls).toEqual([[false], [true]])
    })

    it('inserts what did arrive when one upload fails', async () => {
        const { wire, editor, view, inserted, events } = harness({ fails: [1] })

        await uploadAll({
            wire,
            editor,
            view,
            files: [file('application/pdf'), file('application/pdf')],
            settings,
            position: () => 1,
        })

        expect(wire.callSchemaComponentMethod).toHaveBeenCalledTimes(1)
        expect(inserted[0].nodes).toHaveLength(1)
        expect(events.at(-1)).toBe('form-processing-finished')
    })

    it('inserts nothing the field would not name, and still says it is done', async () => {
        const { wire, editor, view, inserted, events } = harness()
        wire.callSchemaComponentMethod.mockResolvedValue(null)

        await uploadAll({ wire, editor, view, files: [file('application/pdf')], settings, position: () => 1 })

        expect(inserted).toEqual([])
        expect(events.at(-1)).toBe('form-processing-finished')
        expect(editor.isEditable).toBe(true)
    })

    it('says it is done when the server call throws', async () => {
        const { wire, editor, view, events } = harness()
        const error = vi.spyOn(console, 'error').mockImplementation(() => {})
        wire.callSchemaComponentMethod.mockRejectedValue(new Error('500'))

        await uploadAll({ wire, editor, view, files: [file('application/pdf')], settings, position: () => 1 })

        expect(events.at(-1)).toBe('form-processing-finished')
        expect(editor.isEditable).toBe(true)
        error.mockRestore()
    })
})
