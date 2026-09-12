import { computed, ref, watch } from 'vue'
import { useAuthStore } from '../../../stores/auth'
import { addContactComment, assignContact, getContact, getContactAssignees, getContactComments, getContactHistory, getContacts, getContactStatuses, updateContactStatus } from '../services/contacts'
import type { Contact, ContactAssignee, ContactComment, ContactHistory, ContactStatus } from '../types/contact.types'

export const contactTypeOptions = [
  { label: 'Все типы', value: '' }, { label: 'Обратный звонок', value: 'callback' },
  { label: 'Email', value: 'email' }, { label: 'Партнёр', value: 'partner' },
]

export function useContactsWorkspace() {
  const auth = useAuthStore()
  const contacts = ref<Contact[]>([])
  const selected = ref<Contact | null>(null)
  const statuses = ref<ContactStatus[]>([])
  const assignees = ref<ContactAssignee[]>([])
  const history = ref<ContactHistory[]>([])
  const comments = ref<ContactComment[]>([])
  const search = ref('')
  const type = ref('')
  const status = ref('')
  const unassigned = ref(false)
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
  const canManage = computed(() => auth.hasPermission('contacts.manage'))
  const canManageStatus = computed(() => canManage.value && statusReady.value && !statusLoading.value && !statusError.value)
  const canAssign = computed(() => canManage.value && assigneeReady.value && !assigneeLoading.value && !assigneeError.value)
  const statusOptions = computed(() => [{ label: 'Все статусы', value: '' }, ...statuses.value.map(item => ({ label: item.name, value: item.code }))])
  const assigneeOptions = computed(() => [{ label: 'Не назначен', value: '' }, ...assignees.value.map(item => ({ label: item.name, value: String(item.id) }))])
  const date = (value: string | null): string => value ? new Date(value).toLocaleString('ru-RU') : '—'
  const statusName = (code: string): string => statuses.value.find(item => item.code === code)?.name ?? code
  const typeName = (value: string): string => contactTypeOptions.find(item => item.value === value)?.label ?? value

  async function load(page = 1): Promise<void> {
    loading.value = true
    error.value = ''
    try {
      const result = await getContacts({ search: search.value, type: type.value, status: status.value, unassigned: unassigned.value }, { page })
      contacts.value = result.data
      meta.value = result.meta
    } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось загрузить обращения.' }
    finally { loading.value = false }
  }

  async function select(contact: Contact): Promise<void> {
    detailLoading.value = true
    actionError.value = ''
    try {
      selected.value = await getContact(contact.id)
      ;[history.value, comments.value] = await Promise.all([getContactHistory(contact.id), getContactComments(contact.id)])
    } catch (reason) { actionError.value = reason instanceof Error ? reason.message : 'Не удалось загрузить детали обращения.' }
    finally { detailLoading.value = false }
  }

  async function changeStatus(): Promise<void> {
    if (!selected.value || !canManageStatus.value) return
    saving.value = true
    actionError.value = ''
    try { await updateContactStatus(selected.value.id, selected.value.status); await select(selected.value); await load(meta.value.current_page) }
    catch (reason) { actionError.value = reason instanceof Error ? reason.message : 'Не удалось изменить статус.' }
    finally { saving.value = false }
  }

  async function changeAssignee(value: string): Promise<void> {
    if (!selected.value || !canAssign.value) return
    saving.value = true
    actionError.value = ''
    try { await assignContact(selected.value.id, value ? Number(value) : null); await select(selected.value); await load(meta.value.current_page) }
    catch (reason) { actionError.value = reason instanceof Error ? reason.message : 'Не удалось назначить ответственного.' }
    finally { saving.value = false }
  }

  async function submitComment(): Promise<void> {
    if (!selected.value || !commentBody.value.trim()) return
    saving.value = true
    actionError.value = ''
    try { await addContactComment(selected.value.id, commentBody.value); commentBody.value = ''; comments.value = await getContactComments(selected.value.id) }
    catch (reason) { actionError.value = reason instanceof Error ? reason.message : 'Не удалось добавить комментарий.' }
    finally { saving.value = false }
  }

  async function initialize(): Promise<void> {
    loading.value = true
    statusError.value = ''
    assigneeError.value = ''
    statusReady.value = false
    assigneeReady.value = false
    statusLoading.value = true
    try { statuses.value = await getContactStatuses(); statusReady.value = true }
    catch (reason) { statusError.value = reason instanceof Error ? reason.message : 'Не удалось загрузить статусы.' }
    finally { statusLoading.value = false }
    if (canManage.value) {
      assigneeLoading.value = true
      try { assignees.value = await getContactAssignees(); assigneeReady.value = true }
      catch (reason) { assigneeError.value = reason instanceof Error ? reason.message : 'Не удалось загрузить список ответственных.' }
      finally { assigneeLoading.value = false }
    }
    await load()
  }

  watch([type, status, unassigned], () => void load())
  return { contacts, selected, statuses, assignees, history, comments, search, type, status, unassigned, loading, detailLoading, saving, error, statusLoading, statusReady, statusError, assigneeLoading, assigneeReady, assigneeError, actionError, commentBody, meta, canManage, canManageStatus, canAssign, statusOptions, assigneeOptions, date, statusName, typeName, load, select, changeStatus, changeAssignee, submitComment, initialize }
}
