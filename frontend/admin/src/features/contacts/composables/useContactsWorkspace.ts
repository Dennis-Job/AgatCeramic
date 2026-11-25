import { computed, getCurrentScope, onScopeDispose, ref, watch } from 'vue'
import { usePaginatedCollection } from '../../../composables/usePaginatedCollection'
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
  const list = usePaginatedCollection<Contact>(
    'Не удалось загрузить обращения.',
  )
  const details = ref<{
    contact: Contact
    history: ContactHistory[]
    comments: ContactComment[]
  } | null>(null)
  const selected = computed(() => details.value?.contact ?? null)
  const history = computed(() => details.value?.history ?? [])
  const comments = computed(() => details.value?.comments ?? [])
  const statuses = ref<ContactStatus[]>([])
  const statusCounts = ref<ContactStatusCounts | null>(null)
  const countsError = ref('')
  const assignees = ref<ContactAssignee[]>([])
  const search = ref('')
  const type = ref('')
  const status = ref('')
  const unassigned = ref(false)
  const assigneeFilter = ref('')
  list.loading.value = true
  let listRequested = false
  const detailLoading = ref(false)
  const detailError = ref('')
  const saving = ref(false)
  const statusLoading = ref(false)
  const statusReady = ref(false)
  const statusError = ref('')
  const assigneeLoading = ref(false)
  const assigneeReady = ref(false)
  const assigneeError = ref('')
  const actionError = ref('')
  const commentBody = ref('')
  const initialMeta = { current_page: 1, last_page: 1, per_page: 25, total: 0 }
  const meta = computed(() => list.pagination.value ?? initialMeta)
  let detailRequest = 0
  let countsRequest = 0
  let disposed = false
  let selectedId: number | null = null
  if (getCurrentScope())
    onScopeDispose(() => {
      disposed = true
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
    if (disposed) return
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
    listRequested = true
    const filters = {
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
    }
    await list.load(page, (requestedPage) =>
      getContacts(filters, { page: requestedPage }),
    )
  }

  async function loadDetails(id: number, request: number): Promise<void> {
    detailLoading.value = true
    detailError.value = ''
    actionError.value = ''
    try {
      const contact = await getContact(id)
      if (request !== detailRequest) return
      const [nextHistory, nextComments] = await Promise.all([
        getContactHistory(id),
        getContactComments(id),
      ])
      if (request !== detailRequest) return
      details.value = { contact, history: nextHistory, comments: nextComments }
    } catch (reason) {
      if (request !== detailRequest) return
      detailError.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось загрузить детали обращения.'
    } finally {
      if (request === detailRequest) detailLoading.value = false
    }
  }

  async function select(contact: Pick<Contact, 'id'>): Promise<void> {
    if (selectedId !== contact.id) clearSelection()
    selectedId = contact.id
    saving.value = false
    await loadDetails(contact.id, ++detailRequest)
  }

  function clearSelection(): void {
    detailRequest++
    selectedId = null
    details.value = null
    detailLoading.value = false
    detailError.value = ''
    actionError.value = ''
    commentBody.value = ''
    saving.value = false
  }

  async function changeStatus(targetStatus: string): Promise<void> {
    if (
      saving.value ||
      detailLoading.value ||
      !selected.value ||
      !canManageStatus.value ||
      !transitionReady.value ||
      !allowedStatuses.value.some((item) => item.code === targetStatus)
    )
      return
    const { id } = selected.value
    const request = detailRequest
    saving.value = true
    actionError.value = ''
    try {
      await updateContactStatus(id, targetStatus)
      notifyAdminNotificationsChanged()
      await Promise.all([
        refreshStatusCounts(),
        request === detailRequest && loadDetails(id, request),
        list.reload(),
      ])
    } catch (reason) {
      if (request === detailRequest)
        actionError.value =
          reason instanceof Error
            ? reason.message
            : 'Не удалось изменить статус.'
    } finally {
      if (request === detailRequest) saving.value = false
    }
  }

  async function changeAssignee(value: string): Promise<void> {
    if (
      saving.value ||
      detailLoading.value ||
      !selected.value ||
      !canAssign.value
    )
      return
    const { id } = selected.value
    const request = detailRequest
    saving.value = true
    actionError.value = ''
    try {
      await assignContact(id, value ? Number(value) : null)
      await Promise.all([
        request === detailRequest && loadDetails(id, request),
        list.reload(),
      ])
    } catch (reason) {
      if (request === detailRequest)
        actionError.value =
          reason instanceof Error
            ? reason.message
            : 'Не удалось назначить ответственного.'
    } finally {
      if (request === detailRequest) saving.value = false
    }
  }

  async function submitComment(): Promise<void> {
    if (
      saving.value ||
      detailLoading.value ||
      !canManage.value ||
      !selected.value ||
      !commentBody.value.trim()
    )
      return
    const { id } = selected.value
    const body = commentBody.value
    const request = detailRequest
    saving.value = true
    actionError.value = ''
    try {
      await addContactComment(id, body)
      if (request !== detailRequest) return
      if (commentBody.value === body) commentBody.value = ''
      const nextComments = await getContactComments(id)
      if (request === detailRequest && details.value)
        details.value.comments = nextComments
    } catch (reason) {
      if (request === detailRequest)
        actionError.value =
          reason instanceof Error
            ? reason.message
            : 'Не удалось добавить комментарий.'
    } finally {
      if (request === detailRequest) saving.value = false
    }
  }

  async function initialize(): Promise<void> {
    const countsRefresh = refreshStatusCounts()
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
    await Promise.all([!listRequested && load(), countsRefresh])
  }

  watch([type, status, unassigned, assigneeFilter], () => void load())
  return {
    contacts: list.items,
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
    loading: list.loading,
    detailLoading,
    detailError,
    saving,
    error: list.error,
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
