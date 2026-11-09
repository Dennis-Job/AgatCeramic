import { computed, onMounted, ref } from 'vue'
import { normalizePhoneInput } from '../../../utils/phone'
import {
  createComplianceApproval,
  createLegalDocument,
  getComplianceApprovals,
  getLegalDocuments,
  getSettings,
  publishLegalDocument,
  updateLegalDocument,
  saveSettings,
} from '../services/settings'
import type {
  ComplianceApproval,
  LegalDocument,
  SiteSettings,
} from '../types/settings.types'

const emptySettings = (): SiteSettings => ({
  operator_type: null,
  seller_name: null,
  entrepreneur_name: null,
  inn: null,
  ogrnip: null,
  address: null,
  phones: [],
  email: null,
  partner_email: null,
  notification_email: null,
  bank_name: null,
  bank_bik: null,
  bank_account: null,
  bank_correspondent_account: null,
  publish_bank_details: false,
  updated_at: null,
})

function message(reason: unknown): string {
  return reason instanceof Error
    ? reason.message
    : 'Не удалось выполнить запрос.'
}

export function useSettingsWorkspace() {
  const settings = ref<SiteSettings>(emptySettings())
  const phone = ref('')
  const phoneSelectionRequired = ref(false)
  const legacyPhones = ref<string[]>([])
  const documents = ref<LegalDocument[]>([])
  const approvals = ref<ComplianceApproval[]>([])
  const loading = ref(true)
  const loaded = ref(false)
  const busyAction = ref('')
  const error = ref('')
  const success = ref('')
  const documentForm = ref<
    Pick<LegalDocument, 'type' | 'version' | 'body' | 'content'>
  >({
    type: 'offer',
    version: '',
    body: '',
    content: null,
  })
  const editingDocumentId = ref<number | null>(null)
  let newDocumentForm: typeof documentForm.value | null = null

  function editDocument(document: LegalDocument): boolean {
    if (
      document.published_at ||
      busyAction.value ||
      editingDocumentId.value !== null
    )
      return false
    // Both forms own their JSON tree; editing must not mutate the saved preview.
    newDocumentForm = JSON.parse(
      JSON.stringify(documentForm.value),
    ) as typeof documentForm.value
    documentForm.value = JSON.parse(
      JSON.stringify({
        type: document.type,
        version: document.version,
        body: document.body,
        content: document.content,
      }),
    ) as typeof documentForm.value
    editingDocumentId.value = document.id
    error.value = ''
    success.value = ''
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
    error.value = ''
    success.value = ''
  }
  const approvalForm = ref({
    role: 'business_owner' as ComplianceApproval['role'],
    reviewer_name: '',
    decision: 'approved' as ComplianceApproval['decision'],
    decided_on: '',
    document_version_id: '',
  })
  const policyVersions = computed(() =>
    documents.value.filter(
      (document) => document.type === 'privacy_policy' && document.published_at,
    ),
  )

  async function load(): Promise<void> {
    loading.value = true
    loaded.value = false
    error.value = ''
    try {
      const [loadedSettings, loadedDocuments, loadedApprovals] =
        await Promise.all([
          getSettings(),
          getLegalDocuments(),
          getComplianceApprovals(),
        ])
      settings.value = loadedSettings
      setLoadedPhones(loadedSettings.phones)
      documents.value = loadedDocuments
      approvals.value = loadedApprovals
      loaded.value = true
    } catch (reason) {
      error.value = message(reason)
    } finally {
      loading.value = false
    }
  }

  function setLoadedPhones(phones: string[]): void {
    legacyPhones.value = [...phones]
    phoneSelectionRequired.value = phones.length > 1
    phone.value = phoneSelectionRequired.value ? '' : (phones[0] ?? '')
  }

  function updatePhone(value: string): void {
    phone.value = value
    if (/^\+7\d{10}$/.test(normalizePhoneInput(value) ?? '')) {
      phoneSelectionRequired.value = false
    }
  }

  function clearPhone(): void {
    phone.value = ''
    phoneSelectionRequired.value = false
  }

  async function run(
    action: () => Promise<void>,
    notice: string,
    actionId: string,
  ): Promise<void> {
    if (busyAction.value) return
    busyAction.value = actionId
    error.value = ''
    success.value = ''
    try {
      await action()
      success.value = notice
    } catch (reason) {
      error.value = message(reason)
    } finally {
      busyAction.value = ''
    }
  }

  function save(): Promise<void> {
    return run(
      async () => {
        if (phoneSelectionRequired.value) {
          throw new Error(
            'Выберите один телефон магазина или введите новый номер перед сохранением.',
          )
        }
        const normalizedPhone = normalizePhoneInput(phone.value)
        if (
          normalizedPhone === null ||
          (normalizedPhone && !/^\+7\d{10}$/.test(normalizedPhone))
        ) {
          throw new Error('Введите 10 цифр номера телефона после +7.')
        }
        const payload = { ...settings.value }
        delete (payload as Partial<SiteSettings>).updated_at
        settings.value = await saveSettings({
          ...payload,
          phones: normalizedPhone ? [normalizedPhone] : [],
        })
        setLoadedPhones(settings.value.phones)
      },
      'Реквизиты сохранены.',
      'settings',
    )
  }

  function addDocument(): Promise<void> {
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
        documentForm.value = {
          type: 'offer',
          version: '',
          body: '',
          content: null,
        }
      },
      editingId !== null
        ? 'Изменения черновика сохранены.'
        : 'Версия документа сохранена как черновик.',
      'document',
    )
  }

  function publish(id: number): Promise<void> {
    if (editingDocumentId.value !== null) return Promise.resolve()
    return run(
      async () => {
        const document = await publishLegalDocument(id)
        documents.value = documents.value.map((entry) =>
          entry.id === id ? document : entry,
        )
      },
      'Версия опубликована.',
      `publish:${id}`,
    )
  }

  function addApproval(): Promise<void> {
    return run(
      async () => {
        const approval = await createComplianceApproval({
          role: approvalForm.value.role,
          reviewer_name: approvalForm.value.reviewer_name,
          decision: approvalForm.value.decision,
          decided_on: approvalForm.value.decided_on,
          document_version_id: Number(approvalForm.value.document_version_id),
        })
        approvals.value.unshift(approval)
        approvalForm.value = {
          role: 'business_owner',
          reviewer_name: '',
          decision: 'approved',
          decided_on: '',
          document_version_id: '',
        }
      },
      'Решение зафиксировано в журнале.',
      'approval',
    )
  }

  onMounted(load)
  return {
    settings,
    phone,
    phoneSelectionRequired,
    legacyPhones,
    updatePhone,
    clearPhone,
    documents,
    approvals,
    policyVersions,
    loading,
    loaded,
    busyAction,
    error,
    success,
    documentForm,
    editingDocumentId,
    editDocument,
    cancelDocumentEdit,
    approvalForm,
    load,
    save,
    addDocument,
    publish,
    addApproval,
  }
}
