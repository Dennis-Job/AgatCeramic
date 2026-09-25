import { computed, onMounted, ref } from 'vue'
import {
  createComplianceApproval,
  createLegalDocument,
  getComplianceApprovals,
  getLegalDocuments,
  getSettings,
  publishLegalDocument,
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
  const phonesText = ref('')
  const documents = ref<LegalDocument[]>([])
  const approvals = ref<ComplianceApproval[]>([])
  const loading = ref(true)
  const loaded = ref(false)
  const busyAction = ref('')
  const error = ref('')
  const success = ref('')
  const documentForm = ref<Pick<LegalDocument, 'type' | 'version' | 'body'>>({
    type: 'offer',
    version: '',
    body: '',
  })
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
      phonesText.value = loadedSettings.phones.join('\n')
      documents.value = loadedDocuments
      approvals.value = loadedApprovals
      loaded.value = true
    } catch (reason) {
      error.value = message(reason)
    } finally {
      loading.value = false
    }
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
        const payload = { ...settings.value }
        delete (payload as Partial<SiteSettings>).updated_at
        settings.value = await saveSettings({
          ...payload,
          phones: phonesText.value
            .split('\n')
            .map((phone) => phone.trim())
            .filter(Boolean),
        })
        phonesText.value = settings.value.phones.join('\n')
      },
      'Реквизиты сохранены.',
      'settings',
    )
  }

  function addDocument(): Promise<void> {
    return run(
      async () => {
        const document = await createLegalDocument(documentForm.value)
        documents.value.unshift(document)
        documentForm.value = { type: 'offer', version: '', body: '' }
      },
      'Версия документа сохранена как черновик.',
      'document',
    )
  }

  function publish(id: number): Promise<void> {
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
    phonesText,
    documents,
    approvals,
    policyVersions,
    loading,
    loaded,
    busyAction,
    error,
    success,
    documentForm,
    approvalForm,
    load,
    save,
    addDocument,
    publish,
    addApproval,
  }
}
