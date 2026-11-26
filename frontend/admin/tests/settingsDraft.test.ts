import { defineComponent } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, expect, test, vi } from 'vitest'
import { useSettingsWorkspace } from '../src/features/settings/composables/useSettingsWorkspace'
import * as api from '../src/features/settings/services/settings'
import type { LegalDocument } from '../src/features/settings/types/settings.types'

vi.mock('../src/features/settings/services/settings', () => ({
  getSettings: vi.fn().mockResolvedValue({ phones: [] }),
  getLegalDocuments: vi.fn(),
  getComplianceApprovals: vi.fn().mockResolvedValue([]),
  saveSettings: vi.fn(),
  createComplianceApproval: vi.fn(),
  createLegalDocument: vi.fn(),
  updateLegalDocument: vi.fn(),
  publishLegalDocument: vi.fn(),
}))

const draft: LegalDocument = {
  id: 4,
  type: 'privacy_policy',
  version: 'v2',
  body: 'До исправления',
  content: {
    type: 'doc',
    content: [
      {
        type: 'paragraph',
        content: [{ type: 'text', text: 'До исправления' }],
      },
    ],
  },
  published_at: null,
  created_at: '2026-11-09T10:00:00Z',
}
afterEach(() => vi.clearAllMocks())

async function setup() {
  vi.mocked(api.getLegalDocuments).mockResolvedValue([structuredClone(draft)])
  let workspace!: ReturnType<typeof useSettingsWorkspace>
  const wrapper = mount(
    defineComponent({
      setup() {
        workspace = useSettingsWorkspace()
        return () => null
      },
    }),
  )
  await flushPromises()
  return { workspace, wrapper }
}

test('updates the same draft, preserves input on error and restores the new-version form', async () => {
  const { workspace: w, wrapper } = await setup()
  w.legal.documentForm.value.version = 'new-v3'
  w.legal.documentForm.value.body = 'Начатая новая версия'
  expect(w.legal.editDocument(w.legal.documents.value[0])).toBe(true)
  w.legal.documentForm.value.version = 'v2-fixed'
  w.legal.documentForm.value.body = 'Исправлено'
  w.legal.documentForm.value.content!.content![0].content![0].text =
    'Исправлено'
  expect(w.legal.documents.value[0].body).toBe('До исправления')
  expect(w.legal.documents.value[0].content!.content![0].content![0].text).toBe(
    'До исправления',
  )
  vi.mocked(api.updateLegalDocument).mockRejectedValueOnce(
    new Error('Повторите сохранение.'),
  )
  await w.legal.addDocument()
  expect(w.error.value).toBe('Повторите сохранение.')
  expect(w.legal.editingDocumentId.value).toBe(4)
  expect(w.legal.documentForm.value.body).toBe('Исправлено')
  vi.mocked(api.updateLegalDocument).mockResolvedValueOnce({
    ...draft,
    ...w.legal.documentForm.value,
  })
  await w.legal.addDocument()
  expect(api.updateLegalDocument).toHaveBeenLastCalledWith(
    4,
    expect.objectContaining({ version: 'v2-fixed', body: 'Исправлено' }),
  )
  expect(api.createLegalDocument).not.toHaveBeenCalled()
  expect(w.legal.documents.value).toHaveLength(1)
  expect(w.legal.documents.value[0]).toMatchObject({
    id: 4,
    version: 'v2-fixed',
    published_at: null,
  })
  expect(w.legal.editingDocumentId.value).toBeNull()
  expect(w.legal.documentForm.value).toMatchObject({
    version: 'new-v3',
    body: 'Начатая новая версия',
  })
  expect(w.success.value).toBe('Изменения черновика сохранены.')
  wrapper.unmount()
})

test('cancel preserves saved draft; published or busy documents cannot be edited and editing cannot publish', async () => {
  const { workspace: w, wrapper } = await setup()
  expect(
    w.legal.editDocument({ ...draft, published_at: '2026-11-09T11:00:00Z' }),
  ).toBe(false)
  let complete!: () => void
  vi.mocked(api.saveSettings).mockImplementationOnce(async (payload) => {
    await new Promise<void>((resolve) => {
      complete = resolve
    })
    return { ...payload, updated_at: null }
  })
  const saving = w.seller.save()
  expect(w.legal.editDocument(draft)).toBe(false)
  complete()
  await saving
  expect(w.legal.editDocument(w.legal.documents.value[0])).toBe(true)
  w.legal.documentForm.value.body = 'Несохранённое'
  expect(w.legal.editDocument({ ...draft, id: 5 })).toBe(false)
  w.legal.openPreview(4)
  await w.legal.publishPreview()
  expect(api.publishLegalDocument).not.toHaveBeenCalled()
  let failUpdate!: (reason: Error) => void
  vi.mocked(api.updateLegalDocument).mockReturnValueOnce(
    new Promise((_, reject) => {
      failUpdate = reject
    }),
  )
  const updating = w.legal.addDocument()
  w.legal.cancelDocumentEdit()
  expect(w.legal.editingDocumentId.value).toBe(4)
  failUpdate(new Error('Не удалось сохранить.'))
  await updating
  w.legal.cancelDocumentEdit()
  expect(w.legal.editingDocumentId.value).toBeNull()
  expect(w.legal.documents.value[0].body).toBe('До исправления')
  expect(api.updateLegalDocument).toHaveBeenCalledTimes(1)
  wrapper.unmount()
})

test('document validation releases the shared action lock and a successful create resets only the document form', async () => {
  const { workspace: w, wrapper } = await setup()
  w.seller.settings.value.seller_name = 'Несохранённые реквизиты'
  w.compliance.approvalForm.value.reviewer_name = 'Несохранённое согласование'
  w.legal.documentForm.value.version = 'v3'
  await w.legal.addDocument()
  expect(w.error.value).toBe('Введите текст документа.')
  expect(w.legal.documentForm.value.version).toBe('v3')
  expect(api.createLegalDocument).not.toHaveBeenCalled()

  w.legal.documentForm.value.body = 'Новая оферта'
  let complete!: (value: LegalDocument) => void
  vi.mocked(api.createLegalDocument).mockReturnValueOnce(
    new Promise((resolve) => {
      complete = resolve
    }),
  )
  const submit = w.legal.addDocument()
  expect(w.busyAction.value).toBe('document')
  await w.seller.save()
  await w.compliance.addApproval()
  expect(api.saveSettings).not.toHaveBeenCalled()
  expect(api.createComplianceApproval).not.toHaveBeenCalled()
  complete({
    id: 5,
    type: 'offer',
    version: 'v3',
    body: 'Новая оферта',
    content: null,
    published_at: null,
    created_at: '2026-11-09T10:00:00Z',
  })
  await submit

  expect(w.legal.documents.value[0]).toMatchObject({
    id: 5,
    published_at: null,
  })
  expect(w.legal.documentForm.value).toEqual({
    type: 'offer',
    version: '',
    body: '',
    content: null,
  })
  expect(w.seller.settings.value.seller_name).toBe('Несохранённые реквизиты')
  expect(w.compliance.approvalForm.value.reviewer_name).toBe(
    'Несохранённое согласование',
  )
  expect(w.busyAction.value).toBeNull()
  wrapper.unmount()
})
