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
  w.documentForm.value.version = 'new-v3'
  w.documentForm.value.body = 'Начатая новая версия'
  expect(w.editDocument(w.documents.value[0])).toBe(true)
  w.documentForm.value.version = 'v2-fixed'
  w.documentForm.value.body = 'Исправлено'
  w.documentForm.value.content!.content![0].content![0].text = 'Исправлено'
  expect(w.documents.value[0].body).toBe('До исправления')
  expect(w.documents.value[0].content!.content![0].content![0].text).toBe(
    'До исправления',
  )
  vi.mocked(api.updateLegalDocument).mockRejectedValueOnce(
    new Error('Повторите сохранение.'),
  )
  await w.addDocument()
  expect(w.error.value).toBe('Повторите сохранение.')
  expect(w.editingDocumentId.value).toBe(4)
  expect(w.documentForm.value.body).toBe('Исправлено')
  vi.mocked(api.updateLegalDocument).mockResolvedValueOnce({
    ...draft,
    ...w.documentForm.value,
  })
  await w.addDocument()
  expect(api.updateLegalDocument).toHaveBeenLastCalledWith(
    4,
    expect.objectContaining({ version: 'v2-fixed', body: 'Исправлено' }),
  )
  expect(api.createLegalDocument).not.toHaveBeenCalled()
  expect(w.documents.value).toHaveLength(1)
  expect(w.documents.value[0]).toMatchObject({
    id: 4,
    version: 'v2-fixed',
    published_at: null,
  })
  expect(w.editingDocumentId.value).toBeNull()
  expect(w.documentForm.value).toMatchObject({
    version: 'new-v3',
    body: 'Начатая новая версия',
  })
  expect(w.success.value).toBe('Изменения черновика сохранены.')
  wrapper.unmount()
})

test('cancel preserves saved draft; published or busy documents cannot be edited and editing cannot publish', async () => {
  const { workspace: w, wrapper } = await setup()
  expect(
    w.editDocument({ ...draft, published_at: '2026-11-09T11:00:00Z' }),
  ).toBe(false)
  w.busyAction.value = 'settings'
  expect(w.editDocument(draft)).toBe(false)
  w.busyAction.value = ''
  expect(w.editDocument(w.documents.value[0])).toBe(true)
  w.documentForm.value.body = 'Несохранённое'
  expect(w.editDocument({ ...draft, id: 5 })).toBe(false)
  await w.publish(4)
  expect(api.publishLegalDocument).not.toHaveBeenCalled()
  w.busyAction.value = 'document'
  w.cancelDocumentEdit()
  expect(w.editingDocumentId.value).toBe(4)
  w.busyAction.value = ''
  w.cancelDocumentEdit()
  expect(w.editingDocumentId.value).toBeNull()
  expect(w.documents.value[0].body).toBe('До исправления')
  expect(api.updateLegalDocument).not.toHaveBeenCalled()
  wrapper.unmount()
})
