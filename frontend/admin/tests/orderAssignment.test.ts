import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, afterEach, expect, test, vi } from 'vitest'
import { effectScope, nextTick } from 'vue'
import { useAuthStore } from '../src/stores/auth'
import { useOrdersWorkspace } from '../src/features/orders/composables/useOrdersWorkspace'
import type { Order } from '../src/features/orders/types/order.types'

const api = vi.hoisted(() => ({
  getOrders: vi.fn(),
  getOrder: vi.fn(),
  getOrderHistory: vi.fn(),
  getOrderComments: vi.fn(),
  getOrderStatuses: vi.fn(),
  getOrderAssignees: vi.fn(),
  assignOrder: vi.fn(),
}))
vi.mock('../src/features/orders/services/orders', () => api)
let scope: ReturnType<typeof effectScope>
const record = () =>
  ({
    id: 1,
    order_number: 'AG-1',
    status: 'new',
    payment_status: 'not_paid',
    payment_amount: null,
    payment_method: null,
    payment_reference: null,
    assignee: null,
    assigned_at: null,
  }) as Order
function create(manage = true) {
  useAuthStore().user = {
    id: 1,
    name: 'Сотрудник',
    email: 'staff@example.test',
    status: 'active',
    last_login_at: null,
    permissions: ['orders.view', ...(manage ? ['orders.manage'] : [])],
  }
  scope = effectScope()
  return scope.run(useOrdersWorkspace)!
}
beforeEach(() => {
  setActivePinia(createPinia())
  vi.resetAllMocks()
  api.getOrders.mockResolvedValue({
    data: [record()],
    meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
  })
  api.getOrder.mockResolvedValue(record())
  api.getOrderHistory.mockResolvedValue([])
  api.getOrderComments.mockResolvedValue([])
  api.getOrderStatuses.mockResolvedValue([])
  api.getOrderAssignees.mockResolvedValue([{ id: 2, name: 'Менеджер' }])
  api.assignOrder.mockResolvedValue(undefined)
})
afterEach(() => scope?.stop())

test('assignment refreshes only assignee fields and preserves all existing drafts', async () => {
  const state = create()
  await state.initialize()
  await state.selectOrder({ id: 1 })
  state.selected.value!.status = 'processing'
  state.paymentForm.value.payment_method = 'Наличные'
  state.commentBody.value = 'Перезвонить'
  api.getOrder.mockResolvedValue({
    ...record(),
    assignee: { id: 2, name: 'Менеджер' },
    assigned_at: '2026-11-05T10:00:00Z',
  })
  await state.changeAssignee('2')
  expect(api.assignOrder).toHaveBeenCalledWith(1, 2)
  expect(state.selected.value!.assignee?.id).toBe(2)
  expect(state.selected.value!.status).toBe('processing')
  expect(state.paymentForm.value.payment_method).toBe('Наличные')
  expect(state.commentBody.value).toBe('Перезвонить')
  expect(state.hasUnsavedChanges.value).toBe(true)
})

test('unassignment is nullable and filters remain independent of the payment filter', async () => {
  const state = create()
  await state.initialize()
  api.getOrder.mockResolvedValueOnce({
    ...record(),
    assignee: { id: 2, name: 'Менеджер' },
  })
  await state.selectOrder({ id: 1 })
  await state.changeAssignee('')
  expect(api.assignOrder).toHaveBeenCalledWith(1, null)
  state.assigneeFilter.value = 'unassigned'
  state.paymentStatus.value = 'paid'
  await nextTick()
  expect(api.getOrders).toHaveBeenLastCalledWith(
    expect.objectContaining({
      unassigned: true,
      assignee_id: '',
      payment_status: 'paid',
    }),
    { page: 1 },
  )
})

test('view-only users do not load candidates or send assignment mutations', async () => {
  const state = create(false)
  await state.initialize()
  await state.selectOrder({ id: 1 })
  await state.changeAssignee('2')
  expect(api.getOrderAssignees).not.toHaveBeenCalled()
  expect(api.assignOrder).not.toHaveBeenCalled()
  expect(state.canAssign.value).toBe(false)
})

test('candidate errors allow retry without blocking the queue or hiding an existing assignee', async () => {
  api.getOrderAssignees.mockRejectedValueOnce(new Error('Список недоступен'))
  const state = create()
  await state.initialize()
  expect(state.orders.value).toHaveLength(1)
  expect(state.canAssign.value).toBe(false)
  expect(state.assigneeError.value).toBe('Список недоступен')
  api.getOrder.mockResolvedValueOnce({
    ...record(),
    assignee: { id: 9, name: 'Прежний сотрудник' },
  })
  await state.selectOrder({ id: 1 })
  expect(state.assigneeOptions.value).toContainEqual({
    value: '9',
    label: 'Прежний сотрудник',
  })
  await state.loadAssignees()
  expect(state.canAssign.value).toBe(true)
  expect(state.assigneeError.value).toBe('')
})

test('failed and stale assignment responses cannot replace the selected order', async () => {
  const state = create()
  await state.initialize()
  await state.selectOrder({ id: 1 })
  api.assignOrder.mockRejectedValueOnce(new Error('Назначение отклонено'))
  await state.changeAssignee('2')
  expect(state.selected.value!.assignee).toBeNull()
  expect(state.actionError.value).toBe('Назначение отклонено')
  let release!: () => void
  api.assignOrder.mockReturnValueOnce(
    new Promise<void>((resolve) => {
      release = resolve
    }),
  )
  const pending = state.changeAssignee('2')
  state.clearSelection()
  release()
  await pending
  expect(state.selected.value).toBeNull()
  expect(state.saving.value).toBe(false)
})
