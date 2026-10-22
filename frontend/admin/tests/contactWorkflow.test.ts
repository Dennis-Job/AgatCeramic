import { createPinia, setActivePinia } from 'pinia'
import { nextTick } from 'vue'
import { beforeEach, expect, test, vi } from 'vitest'
import { useAuthStore } from '../src/stores/auth'
import { useContactsWorkspace } from '../src/features/contacts/composables/useContactsWorkspace'
import type {
  Contact,
  ContactStatus,
} from '../src/features/contacts/types/contact.types'

const api = vi.hoisted(() => ({
  getContacts: vi.fn(),
  getContact: vi.fn(),
  getContactStatuses: vi.fn(),
  getContactStatusCounts: vi.fn(),
  getContactAssignees: vi.fn(),
  getContactHistory: vi.fn(),
  getContactComments: vi.fn(),
  updateContactStatus: vi.fn(),
  assignContact: vi.fn(),
  addContactComment: vi.fn(),
}))
const notify = vi.hoisted(() => vi.fn())
vi.mock('../src/features/contacts/services/contacts', () => api)
vi.mock('../src/features/notifications/services/notificationChanges', () => ({
  notifyAdminNotificationsChanged: notify,
}))
const contact = (id = 42, status = 'new'): Contact => ({
  id,
  type: 'callback',
  contact: { name: 'Клиент', phone: '+79991234567', email: null },
  message: null,
  source: 'site',
  status,
  assigned_at: null,
  completed_at: null,
  created_at: '2026-10-22T10:00:00Z',
})
const catalog: ContactStatus[] = [
  {
    code: 'new',
    name: 'Новое',
    is_terminal: false,
    allowed_transitions: ['processing', 'rejected'],
  },
  {
    code: 'processing',
    name: 'В обработке',
    is_terminal: false,
    allowed_transitions: ['completed'],
  },
  {
    code: 'completed',
    name: 'Завершено',
    is_terminal: true,
    allowed_transitions: [],
  },
  {
    code: 'rejected',
    name: 'Отклонено',
    is_terminal: true,
    allowed_transitions: [],
  },
]
beforeEach(() => {
  setActivePinia(createPinia())
  vi.resetAllMocks()
  useAuthStore().user = {
    id: 1,
    name: 'Менеджер',
    email: 'admin@example.test',
    status: 'active',
    last_login_at: null,
    permissions: ['contacts.view', 'contacts.manage'],
  }
  api.getContacts.mockResolvedValue({
    data: [],
    meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
  })
  api.getContactStatuses.mockResolvedValue(catalog)
  api.getContactStatusCounts.mockResolvedValue({
    new: 2,
    processing: 3,
    awaiting_client: 1,
  })
  api.getContactAssignees.mockResolvedValue([{ id: 7, name: 'Менеджер 7' }])
  api.getContact.mockImplementation(async (id: number) => contact(id))
  api.getContactHistory.mockResolvedValue([])
  api.getContactComments.mockResolvedValue([])
})
async function ready() {
  const workspace = useContactsWorkspace()
  await workspace.initialize()
  await workspace.select(contact())
  return workspace
}

test('only server catalog transitions can be submitted and missing metadata fails closed', async () => {
  const workspace = await ready()
  expect(workspace.currentStatus.value?.code).toBe('new')
  expect(workspace.allowedStatuses.value.map((item) => item.code)).toEqual([
    'processing',
    'rejected',
  ])
  expect(workspace.transitionReady.value).toBe(true)
  await workspace.changeStatus('completed')
  await workspace.changeStatus('awaiting_client')
  expect(api.updateContactStatus).not.toHaveBeenCalled()
  workspace.statuses.value = [
    { code: 'new', name: 'Новое', is_terminal: false },
  ]
  expect(workspace.transitionReady.value).toBe(false)
  expect(workspace.allowedStatuses.value).toEqual([])
  await workspace.changeStatus('processing')
  expect(api.updateContactStatus).not.toHaveBeenCalled()
})

test('explicit target remains unsaved until server reload, blocks double submit, and preserves the draft', async () => {
  const workspace = await ready()
  workspace.commentBody.value = 'Черновик'
  let finish!: () => void
  api.updateContactStatus.mockReturnValueOnce(
    new Promise<void>((resolve) => {
      finish = resolve
    }),
  )
  const pending = workspace.changeStatus('processing')
  expect(api.updateContactStatus).toHaveBeenCalledWith(42, 'processing')
  expect(workspace.selected.value?.status).toBe('new')
  expect(notify).not.toHaveBeenCalled()
  await workspace.changeStatus('rejected')
  expect(api.updateContactStatus).toHaveBeenCalledTimes(1)
  api.getContact.mockResolvedValueOnce(contact(42, 'processing'))
  finish()
  await pending
  expect(workspace.selected.value?.status).toBe('processing')
  expect(workspace.commentBody.value).toBe('Черновик')
  expect(notify).toHaveBeenCalledTimes(1)
  expect(workspace.saving.value).toBe(false)
})

test('failed status requests keep the saved status and expose the error without notification', async () => {
  const workspace = await ready()
  api.updateContactStatus.mockRejectedValueOnce(new Error('Переход отклонён'))
  await workspace.changeStatus('processing')
  expect(workspace.selected.value?.status).toBe('new')
  expect(workspace.actionError.value).toBe('Переход отклонён')
  expect(notify).not.toHaveBeenCalled()
})

test('terminal, unavailable catalogs and view-only access cannot submit transitions', async () => {
  const workspace = await ready()
  workspace.selected.value = contact(42, 'completed')
  expect(workspace.currentStatus.value?.is_terminal).toBe(true)
  expect(workspace.allowedStatuses.value).toEqual([])
  await workspace.changeStatus('processing')
  workspace.selected.value = contact()
  workspace.statusError.value = 'Ошибка каталога'
  expect(workspace.transitionReady.value).toBe(false)
  await workspace.changeStatus('processing')
  workspace.statusError.value = ''
  useAuthStore().user!.permissions = ['contacts.view']
  await workspace.changeStatus('processing')
  expect(api.updateContactStatus).not.toHaveBeenCalled()
})

test('clearSelection invalidates pending detail and clears only the inspector', async () => {
  const workspace = await ready()
  workspace.contacts.value = [contact()]
  workspace.commentBody.value = 'Черновик'
  let finish!: (value: Contact) => void
  api.getContact.mockReturnValueOnce(
    new Promise<Contact>((resolve) => {
      finish = resolve
    }),
  )
  const pending = workspace.select(contact(43))
  workspace.clearSelection()
  finish(contact(43))
  await pending
  expect(workspace.selected.value).toBeNull()
  expect(workspace.history.value).toEqual([])
  expect(workspace.comments.value).toEqual([])
  expect(workspace.commentBody.value).toBe('')
  expect(workspace.detailLoading.value).toBe(false)
  expect(workspace.contacts.value).toHaveLength(1)
})

test('late successful status patches cannot replace another selection or reopen a closed inspector', async () => {
  const workspace = await ready()
  let finish!: () => void
  api.updateContactStatus.mockReturnValueOnce(
    new Promise<void>((resolve) => {
      finish = resolve
    }),
  )
  const pending = workspace.changeStatus('processing')
  await workspace.select(contact(43))
  finish()
  await pending
  expect(workspace.selected.value?.id).toBe(43)
  api.updateContactStatus.mockReturnValueOnce(
    new Promise<void>((resolve) => {
      finish = resolve
    }),
  )
  const closed = workspace.changeStatus('processing')
  workspace.clearSelection()
  finish()
  await closed
  expect(workspace.selected.value).toBeNull()
  expect(notify).toHaveBeenCalledTimes(2)
})

test('draft survives assignee refresh and clears when another contact is selected', async () => {
  const workspace = await ready()
  workspace.commentBody.value = 'Черновик'
  await workspace.changeAssignee('7')
  expect(workspace.commentBody.value).toBe('Черновик')
  await workspace.select(contact(43))
  expect(workspace.commentBody.value).toBe('')
})

test('a comment finishing after selection changes cannot clear the new draft or replace comments', async () => {
  const workspace = await ready()
  workspace.commentBody.value = 'Первый комментарий'
  let finish!: () => void
  api.addContactComment.mockReturnValueOnce(
    new Promise<void>((resolve) => {
      finish = resolve
    }),
  )
  const pending = workspace.submitComment()
  await workspace.select(contact(43))
  workspace.commentBody.value = 'Другой черновик'
  finish()
  await pending
  expect(workspace.commentBody.value).toBe('Другой черновик')
  expect(api.getContactComments).toHaveBeenLastCalledWith(43)
})

test('assignee dropdown maps all, unassigned, and concrete employees to exclusive filters', async () => {
  const workspace = await ready()
  expect(workspace.assigneeFilterOptions.value).toEqual([
    { value: '', label: 'Все сотрудники' },
    { value: 'unassigned', label: 'Не назначен' },
    { value: '7', label: 'Менеджер 7' },
  ])
  workspace.assigneeFilter.value = '7'
  await nextTick()
  expect(api.getContacts).toHaveBeenLastCalledWith(
    expect.objectContaining({ assignee_id: '7', unassigned: false }),
    { page: 1 },
  )
  workspace.assigneeFilter.value = 'unassigned'
  await nextTick()
  expect(api.getContacts).toHaveBeenLastCalledWith(
    expect.objectContaining({ assignee_id: undefined, unassigned: true }),
    { page: 1 },
  )
  workspace.assigneeFilter.value = ''
  await nextTick()
  expect(api.getContacts).toHaveBeenLastCalledWith(
    expect.objectContaining({ assignee_id: undefined, unassigned: false }),
    { page: 1 },
  )
})

test('global counts load separately from filters and refresh immediately after a saved status', async () => {
  const workspace = await ready()
  expect(workspace.statusCounts.value).toEqual({
    new: 2,
    processing: 3,
    awaiting_client: 1,
  })
  workspace.search.value = 'Клиент'
  workspace.status.value = 'processing'
  workspace.type.value = 'email'
  await nextTick()
  expect(api.getContactStatusCounts).toHaveBeenCalledTimes(1)
  api.getContactStatusCounts.mockResolvedValueOnce({
    new: 1,
    processing: 4,
    awaiting_client: 1,
  })
  await workspace.changeStatus('processing')
  expect(api.getContactStatusCounts).toHaveBeenCalledTimes(2)
  expect(workspace.statusCounts.value).toEqual({
    new: 1,
    processing: 4,
    awaiting_client: 1,
  })
  api.updateContactStatus.mockRejectedValueOnce(new Error('Отказано'))
  await workspace.changeStatus('processing')
  expect(api.getContactStatusCounts).toHaveBeenCalledTimes(2)
})

test('late counts cannot overwrite refreshed totals and failures stay unknown without blocking the list', async () => {
  const workspace = await ready()
  let finish!: (value: {
    new: number
    processing: number
    awaiting_client: number
  }) => void
  api.getContactStatusCounts.mockReturnValueOnce(
    new Promise((resolve) => {
      finish = resolve
    }),
  )
  const stale = workspace.refreshStatusCounts()
  api.getContactStatusCounts.mockResolvedValueOnce({
    new: 1,
    processing: 4,
    awaiting_client: 1,
  })
  await workspace.changeStatus('processing')
  finish({ new: 99, processing: 0, awaiting_client: 0 })
  await stale
  expect(workspace.statusCounts.value?.new).toBe(1)
  api.getContactStatusCounts.mockRejectedValueOnce(
    new Error('Счётчики недоступны'),
  )
  await workspace.initialize()
  expect(workspace.statusCounts.value).toBeNull()
  expect(workspace.countsError.value).toBe('Счётчики недоступны')
  expect(workspace.error.value).toBe('')
  expect(workspace.loading.value).toBe(false)
})
