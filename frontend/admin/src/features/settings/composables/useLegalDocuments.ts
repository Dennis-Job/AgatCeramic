import { ref } from 'vue'
import {
  createLegalDocument,
  publishLegalDocument,
  updateLegalDocument,
} from '../services/settings'
import type { LegalDocument, SettingsActions } from '../types/settings.types'

type DocumentForm = Pick<LegalDocument, 'type' | 'version' | 'body' | 'content'>

const emptyDocument = (): DocumentForm => ({
  type: 'offer',
  version: '',
  body: '',
  content: null,
})

function copyDocumentForm({
  type,
  version,
  body,
  content,
}: DocumentForm): DocumentForm {
  return JSON.parse(
    JSON.stringify({ type, version, body, content }),
  ) as DocumentForm
}

export function useLegalDocuments({
  busyAction,
  run,
  clearFeedback,
}: SettingsActions) {
  const documents = ref<LegalDocument[]>([])
  const documentForm = ref(emptyDocument())
  const editingDocumentId = ref<number | null>(null)
  const preview = ref<LegalDocument | null>(null)
  let newDocumentForm: DocumentForm | null = null

  function editDocument(document: LegalDocument): boolean {
    if (
      document.published_at ||
      busyAction.value ||
      editingDocumentId.value !== null
    )
      return false

    // Both forms own their JSON trees so editing cannot change the saved preview.
    newDocumentForm = copyDocumentForm(documentForm.value)
    documentForm.value = copyDocumentForm(document)
    editingDocumentId.value = document.id
    closePreview()
    clearFeedback()
    return true
  }

  function finishDocumentEdit(): void {
    editingDocumentId.value = null
    if (newDocumentForm) documentForm.value = newDocumentForm
    newDocumentForm = null
  }

  function cancelDocumentEdit(): void {
    if (busyAction.value || editingDocumentId.value === null) return
    finishDocumentEdit()
    clearFeedback()
  }

  function addDocument() {
    const editingId = editingDocumentId.value
    return run(
      async () => {
        if (!documentForm.value.body.trim())
          throw new Error('Введите текст документа.')
        if (
          documentForm.value.body.length > 200000 ||
          new TextEncoder().encode(JSON.stringify(documentForm.value.content))
            .length > 200000
        )
          throw new Error(
            'Текст документа слишком длинный. Сократите текст или форматирование (лимит — 200 КБ).',
          )
        if (editingId !== null) {
          const document = await updateLegalDocument(
            editingId,
            documentForm.value,
          )
          documents.value = documents.value.map((entry) =>
            entry.id === document.id ? document : entry,
          )
          finishDocumentEdit()
          return
        }
        const document = await createLegalDocument(documentForm.value)
        documents.value.unshift(document)
        documentForm.value = emptyDocument()
      },
      editingId !== null
        ? 'Изменения черновика сохранены.'
        : 'Версия документа сохранена как черновик.',
      'document',
    )
  }

  function openPreview(id: number): void {
    preview.value =
      documents.value.find((document) => document.id === id) ?? null
  }

  function closePreview(): void {
    preview.value = null
  }

  async function publishPreview(): Promise<void> {
    const document = preview.value
    if (!document || document.published_at || editingDocumentId.value !== null)
      return
    const { id } = document
    const published = await run(
      async () => {
        const document = await publishLegalDocument(id)
        documents.value = documents.value.map((entry) =>
          entry.id === id ? document : entry,
        )
      },
      'Версия опубликована.',
      `publish:${id}`,
    )
    if (published) closePreview()
  }

  return {
    documents,
    documentForm,
    editingDocumentId,
    editDocument,
    cancelDocumentEdit,
    addDocument,
    preview,
    openPreview,
    closePreview,
    publishPreview,
  }
}
