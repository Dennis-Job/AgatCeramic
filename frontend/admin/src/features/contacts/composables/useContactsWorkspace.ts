import { computed, getCurrentScope, onScopeDispose, ref, watch } from 'vue'
import { useAuthStore } from '../../../stores/auth'
import { notifyAdminNotificationsChanged } from '../../notifications/services/notificationChanges'
import {
  addContactComment,
  assignContact,
  getContact,
  getContactAssignees,
  getContactComments,
  getContactHistory,
  getContacts,
  getContactStatuses,
  getContactStatusCounts,
  updateContactStatus,
} from '../services/contacts'
import type {
  Contact,
  ContactAssignee,
  ContactComment,
  ContactHistory,
  ContactStatus,
  ContactStatusCounts,
} from '../types/contact.types'

export const contactTypeOptions = [
  { label: 'Все типы', value: '' },
  { label: 'Обратный звонок', value: 'callback' },
  { label: 'Email', value: 'email' },
  { label: 'Партнёр', value: 'partner' },
]

export function useContactsWorkspace() {
  const auth = useAuthStore()
  const contacts = ref<Contact[]>([])
  const selected = ref<Contact | null>(null)
  const statuses = ref<ContactStatus[]>([])
  const statusCounts = ref<ContactStatusCounts | null>(null)
  const countsError = ref('')
  const assignees = ref<ContactAssignee[]>([])
  const history = ref<ContactHistory[]>([])
  const comments = ref<ContactComment[]>([])
  const search = ref('')
  const type = ref('')
  const status = ref('')
  const unassigned = ref(false)
  const assigneeFilter = ref('')
  const loading = ref(true)
  const detailLoading = ref(false)
  const saving = ref(false)
  const error = ref('')
  const statusLoading = ref(false)
  const statusReady = ref(false)
  const statusError = ref('')
  const assigneeLoading = ref(false)
  const assigneeReady = ref(false)
  const assigneeError = ref('')
  const actionError = ref('')
  const commentBody = ref('')
  const meta = ref({ current_page: 1, last_page: 1, per_page: 25, total: 0 })
  let detailRequest = 0
  let countsRequest = 0
  let selectedId: number | null = null
  if (getCurrentScope())
    onScopeDispose(() => {
      detailRequest++
      countsRequest++
    })
  const canManage = computed(() => auth.hasPermission('contacts.manage'))
  const currentStatus = computed(() =>
    statuses.value.find((item) => item.code === selected.value?.status),
  )
  const allowedStatuses = computed(() =>
    (currentStatus.value?.allowed_transitions ?? [])
      .map((code) => statuses.value.find((item) => item.code === code))
      .filter((item): item is ContactStatus => !!item),
  )
  const transitionReady = computed(
    () =>
      !!currentStatus.value &&
      Array.isArray(currentStatus.value.allowed_transitions) &&
      statusReady.value &&
      !statusLoading.value &&
      !statusError.value,
  )
  const canManageStatus = computed(
    () =>
      canManage.value &&
      statusReady.value &&
      !statusLoading.value &&
      !statusError.value,
  )
  const canAssign = computed(
    () =>
      canManage.value &&
      assigneeReady.value &&
      !assigneeLoading.value &&
      !assigneeError.value,
  )
  const statusOptions = computed(() => [
    { label: 'Все статусы', value: '' },
    ...statuses.value.map((item) => ({ label: item.name, value: item.code })),
  ])
  const assigneeOptions = computed(() => [
    { label: 'Не назначен', value: '' },
    ...assignees.value.map((item) => ({
      label: item.name,
      value: String(item.id),
    })),
  ])
  const assigneeFilterOptions = computed(() => [
    { value: '', label: 'Все сотрудники' },
    { value: 'unassigned', label: 'Не назначен' },
    ...assigneeOptions.value.filter((item) => item.value),
  ])
  const date = (value: string | null): string =>
    value ? new Date(value).toLocaleString('ru-RU') : '—'
  const statusName = (code: string): string =>
    statuses.value.find((item) => item.code === code)?.name ?? code
  const typeName = (value: string): string =>
    contactTypeOptions.find((item) => item.value === value)?.label ?? value

  async function refreshStatusCounts(): Promise<void> {
    const request = ++countsRequest
    countsError.value = ''
    try {
      const counts = await getContactStatusCounts()
      if (request === countsRequest) statusCounts.value = counts
    } catch (reason) {
      if (request !== countsRequest) return
      statusCounts.value = null
      countsError.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось загрузить счётчики обращений.'
    }
  }

  async function load(page = 1): Promise<void> {
    loading.value = true
    error.value = ''
    try {
      const result = await getContacts(
        {
          search: search.value,
          type: type.value,
          status: status.value,
          assignee_id:
            assigneeFilter.value && assigneeFilter.value !== 'unassigned'
              ? assigneeFilter.value
              : undefined,
          unassigned:
            assigneeFilter.value === 'unassigned' ||
            (!assigneeFilter.value && unassigned.value),
        },
        { page },
      )
      contacts.value = result.data
      meta.value = result.meta
    } catch (reason) {
      error.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось загрузить обращения.'
    } finally {
      loading.value = false
    }
  }

  async function select(contact: Pick<Contact, 'id'>): Promise<void> {
    const request = ++detailRequest
    const differentContact = selectedId !== contact.id
    selectedId = contact.id
    detailLoading.value = true
    if (differentContact) {
      selected.value = null
      history.value = []
      comments.value = []
      commentBody.value = ''
    }
    actionError.value = ''
    try {
      const details = await getContact(contact.id)
      if (request !== detailRequest) return
      const [nextHistory, nextComments] = await Promise.all([
        getContactHistory(contact.id),
        getContactComments(contact.id),
      ])
      if (request !== detailRequest) return
      selected.value = details
      history.value = nextHistory
      comments.value = nextComments
    } catch (reason) {
      if (request !== detailRequest) return
      actionError.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось загрузить детали обращения.'
    } finally {
      if (request === detailRequest) detailLoading.value = false
    }
  }

  function clearSelection(): void {
    detailRequest++
    selectedId = null
    selected.value = null
    history.value = []
    comments.value = []
    detailLoading.value = false
    actionError.value = ''
    commentBody.value = ''
  }

  async function changeStatus(targetStatus: string): Promise<void> {
    if (
      saving.value ||
      !selected.value ||
      !canManageStatus.value ||
      !transitionReady.value ||
      !allowedStatuses.value.some((item) => item.code === targetStatus)
    )
      return
    const contact = { id: selected.value.id }
    const request = detailRequest
    saving.value = true
    actionError.value = ''
    try {
      await updateContactStatus(contact.id, targetStatus)
      notifyAdminNotificationsChanged()
      const countsRefresh = refreshStatusCounts()
      if (request === detailRequest) await select(contact)
      await Promise.all([load(meta.value.current_page), countsRefresh])
    } catch (reason) {
      if (selectedId === contact.id)
        actionError.value =
          reason instanceof Error
            ? reason.message
            : 'Не удалось изменить статус.'
    } finally {
      saving.value = false
    }
  }

  async function changeAssignee(value: string): Promise<void> {
    if (saving.value || !selected.value || !canAssign.value) return
    const contact = { id: selected.value.id }
    const request = detailRequest
    saving.value = true
    actionError.value = ''
    try {
      await assignContact(contact.id, value ? Number(value) : null)
      if (request === detailRequest) await select(contact)
      await load(meta.value.current_page)
    } catch (reason) {
      if (selectedId === contact.id)
        actionError.value =
          reason instanceof Error
            ? reason.message
            : 'Не удалось назначить ответственного.'
    } finally {
      saving.value = false
    }
  }

  async function submitComment(): Promise<void> {
    if (saving.value || !selected.value || !commentBody.value.trim()) return
    const contact = { id: selected.value.id }
    const body = commentBody.value
    const request = detailRequest
    saving.value = true
    actionError.value = ''
    try {
      await addContactComment(contact.id, body)
      if (request !== detailRequest) return
      if (commentBody.value === body) commentBody.value = ''
      const nextComments = await getContactComments(contact.id)
      if (request === detailRequest) comments.value = nextComments
    } catch (reason) {
      if (request === detailRequest)
        actionError.value =
          reason instanceof Error
            ? reason.message
            : 'Не удалось добавить комментарий.'
    } finally {
      saving.value = false
    }
  }

  async function initialize(): Promise<void> {
    const countsRefresh = refreshStatusCounts()
    loading.value = true
    statusError.value = ''
    assigneeError.value = ''
    statusReady.value = false
    assigneeReady.value = false
    statusLoading.value = true
    try {
      statuses.value = await getContactStatuses()
      statusReady.value = true
    } catch (reason) {
      statusError.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось загрузить статусы.'
    } finally {
      statusLoading.value = false
    }
    if (canManage.value) {
      assigneeLoading.value = true
      try {
        assignees.value = await getContactAssignees()
        assigneeReady.value = true
      } catch (reason) {
        assigneeError.value =
          reason instanceof Error
            ? reason.message
            : 'Не удалось загрузить список ответственных.'
      } finally {
        assigneeLoading.value = false
      }
    }
    await Promise.all([load(), countsRefresh])
  }

  watch([type, status, unassigned, assigneeFilter], () => void load())
  return {
    contacts,
    selected,
    statuses,
    statusCounts,
    countsError,
    assignees,
    history,
    comments,
    search,
    type,
    status,
    unassigned,
    assigneeFilter,
    loading,
    detailLoading,
    saving,
    error,
    statusLoading,
    statusReady,
    statusError,
    assigneeLoading,
    assigneeReady,
    assigneeError,
    actionError,
    commentBody,
    meta,
    canManage,
    canManageStatus,
    currentStatus,
    allowedStatuses,
    transitionReady,
    canAssign,
    statusOptions,
    assigneeOptions,
    assigneeFilterOptions,
    date,
    statusName,
    typeName,
    load,
    select,
    clearSelection,
    changeStatus,
    changeAssignee,
    submitComment,
    initialize,
    refreshStatusCounts,
  }
}
