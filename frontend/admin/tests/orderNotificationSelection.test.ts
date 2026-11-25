import { createPinia, setActivePinia } from 'pinia'
import { afterEach, expect, test, vi } from 'vitest'
import { effectScope, nextTick } from 'vue'
import { useOrdersWorkspace } from '../src/features/orders/composables/useOrdersWorkspace'
import type { Order } from '../src/features/orders/types/order.types'
import { useAuthStore } from '../src/stores/auth'

const api = vi.hoisted(() => ({
  getOrder: vi.fn(),
  getOrderHistory: vi.fn(),
  getOrderComments: vi.fn(),
  getOrders: vi.fn(),
  updateOrderStatus: vi.fn(),
  updateOrderPayment: vi.fn(),
  addOrderComment: vi.fn(),
}))
vi.mock('../src/features/orders/services/orders', () => api)
afterEach(() => vi.clearAllMocks())

function createManagerWorkspace() {
  setActivePinia(createPinia())
  useAuthStore().user = {
    id: 1,
    name: 'Менеджер',
    email: 'manager@example.test',
    status: 'active',
    last_login_at: null,
    permissions: ['orders.manage', 'payments.manage'],
  }
  api.getOrder.mockReset().mockImplementation(async (id: number) => ({
    id,
    status: 'new',
    payment_status: 'not_paid',
  }))
  api.getOrderHistory.mockReset().mockResolvedValue([])
  api.getOrderComments.mockReset().mockResolvedValue([])
  api.getOrders.mockReset().mockImplementation(async (_filters, { page }) => ({
    data: [],
    meta: { current_page: page, last_page: 3, per_page: 25, total: 60 },
  }))
  const scope = effectScope()
  const state = scope.run(useOrdersWorkspace)!
  state.statusReady.value = true
  return { state, scope }
}

test('publishes the order, history and comments together and can retry a failed bundle', async () => {
  const { state, scope } = createManagerWorkspace()
  const history = Promise.withResolvers<[]>()
  api.getOrderHistory.mockReturnValueOnce(history.promise)
  const pending = state.selectOrder({ id: 1 })
  await nextTick()
  expect(state.detailLoading.value).toBe(true)
  expect(state.selected.value).toBeNull()
  history.reject(new Error('История недоступна'))
  await pending
  expect(state.selected.value).toBeNull()
  expect(state.detailLoading.value).toBe(false)
  expect(state.detailError.value).toBe('История недоступна')
  await state.selectOrder({ id: 1 })
  expect(state.selected.value?.id).toBe(1)
  expect(state.detailError.value).toBe('')
  scope.stop()
})

test('a saved payment refreshes the requested list page without touching the newly selected order', async () => {
  const { state, scope } = createManagerWorkspace()
  await state.load(1)
  await state.selectOrder({ id: 1 })
  api.getOrder.mockClear()
  const payment = Promise.withResolvers<void>()
  api.updateOrderPayment.mockReturnValueOnce(payment.promise)
  const saved = state.savePayment()
  const page = Promise.withResolvers<{
    data: []
    meta: {
      current_page: number
      last_page: number
      per_page: number
      total: number
    }
  }>()
  api.getOrders.mockReturnValueOnce(page.promise)
  state.search.value = 'Применённый поиск'
  const requested = state.load(2)
  state.search.value = 'Ещё не отправленный поиск'
  await state.selectOrder({ id: 2 })
  state.paymentForm.value.payment_method = 'Новый черновик'
  payment.resolve()
  await saved
  page.resolve({
    data: [],
    meta: { current_page: 2, last_page: 3, per_page: 25, total: 60 },
  })
  await requested
  expect(api.getOrder).toHaveBeenCalledTimes(1)
  expect(api.getOrder).toHaveBeenCalledWith(2)
  expect(state.selected.value?.id).toBe(2)
  expect(state.paymentForm.value.payment_method).toBe('Новый черновик')
  expect(api.getOrders).toHaveBeenCalledTimes(3)
  expect(api.getOrders).toHaveBeenLastCalledWith(
    expect.objectContaining({ search: 'Применённый поиск' }),
    { page: 2 },
  )
  expect(state.meta.value.current_page).toBe(2)
  scope.stop()
})

test('a stale comment response keeps the new order draft and does not replace its comments', async () => {
  const { state, scope } = createManagerWorkspace()
  await state.selectOrder({ id: 1 })
  state.commentBody.value = 'Первый комментарий'
  const added = Promise.withResolvers<void>()
  api.addOrderComment.mockReturnValueOnce(added.promise)
  const submitted = state.submitComment()
  await state.selectOrder({ id: 2 })
  state.commentBody.value = 'Второй черновик'
  added.resolve()
  await submitted
  expect(api.getOrderComments.mock.calls).toEqual([[1], [2]])
  expect(state.commentBody.value).toBe('Второй черновик')
  expect(state.saving.value).toBe(false)
  scope.stop()
})

test.each([
  { afterFailure: '100', afterRetry: '100.00', dirty: false },
  { afterFailure: '125', afterRetry: '125', dirty: true },
])(
  'retry after a saved payment preserves only later edits (amount $afterFailure)',
  async ({ afterFailure, afterRetry, dirty }) => {
    const { state, scope } = createManagerWorkspace()
    await state.selectOrder({ id: 1 })
    api.updateOrderPayment.mockResolvedValueOnce(undefined)
    api.getOrder.mockResolvedValue({
      id: 1,
      status: 'new',
      payment_status: 'paid',
      payment_amount: '100.00',
    })
    api.getOrderHistory.mockRejectedValueOnce(new Error('История недоступна'))
    state.paymentForm.value.payment_status = 'paid'
    state.paymentForm.value.payment_amount = '100'
    await state.savePayment()
    expect(state.detailError.value).toBe('История недоступна')
    expect(state.selected.value?.payment_status).toBe('not_paid')

    state.paymentForm.value.payment_amount = afterFailure
    await state.selectOrder({ id: 1 })
    expect(state.selected.value?.payment_amount).toBe('100.00')
    expect(state.paymentForm.value.payment_amount).toBe(afterRetry)
    expect(state.hasUnsavedChanges.value).toBe(dirty)
    expect(state.detailError.value).toBe('')
    scope.stop()
  },
)

test('queue groups intermediate statuses while payment stays an independent filter', async () => {
  setActivePinia(createPinia())
  api.getOrders.mockResolvedValue({
    data: [],
    meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
  })
  const scope = effectScope()
  const state = scope.run(useOrdersWorkspace)!
  state.statuses.value = [
    'new',
    'processing',
    'awaiting_clarification',
    'confirmed',
    'awaiting_payment',
    'paid',
    'picking',
    'shipped',
    'completed',
    'cancelled',
    'custom_stage',
  ].map((code, index) => ({
    code,
    name: code,
    sort_order: index,
    is_terminal: ['completed', 'cancelled'].includes(code),
  }))
  expect(state.statusOptions.value.map((item) => item.label)).toEqual([
    'Все',
    'Новые',
    'В работе',
    'В доставке',
    'Завершённые',
    'Отменённые',
  ])
  state.status.value = 'working'
  state.paymentStatus.value = 'paid'
  await nextTick()
  expect(api.getOrders).toHaveBeenLastCalledWith(
    {
      search: '',
      status: '',
      statuses: [
        'processing',
        'awaiting_clarification',
        'confirmed',
        'awaiting_payment',
        'paid',
        'picking',
        'custom_stage',
      ],
      payment_status: 'paid',
    },
    { page: 1 },
  )
  scope.stop()
})

test('changing the linked order cannot restore details from a slower previous request', async () => {
  setActivePinia(createPinia())
  api.getOrderHistory.mockResolvedValue([])
  api.getOrderComments.mockResolvedValue([])
  let finish!: (order: Order) => void
  api.getOrder.mockReturnValueOnce(
    new Promise<Order>((resolve) => {
      finish = resolve
    }),
  )
  api.getOrder.mockResolvedValueOnce({ id: 2, order_number: 'AG-2' })
  const scope = effectScope()
  const state = scope.run(useOrdersWorkspace)!
  const stale = state.selectOrder({ id: 1 })
  await state.selectOrder({ id: 2 })
  finish({ id: 1, order_number: 'AG-1' } as Order)
  await stale
  expect(state.selected.value?.id).toBe(2)
  expect(api.getOrderHistory).not.toHaveBeenCalledWith(1)
  scope.stop()
})

test('closing an order invalidates pending detail requests and clears drafts', async () => {
  setActivePinia(createPinia())
  let finish!: (order: Order) => void
  api.getOrder.mockReturnValueOnce(
    new Promise<Order>((resolve) => (finish = resolve)),
  )
  const scope = effectScope()
  const state = scope.run(useOrdersWorkspace)!
  const pending = state.selectOrder({ id: 1 })
  state.commentBody.value = 'Черновик'
  state.clearSelection()
  finish({ id: 1, order_number: 'AG-1' } as Order)
  await pending
  expect(state.selected.value).toBeNull()
  expect(state.detailLoading.value).toBe(false)
  expect(state.commentBody.value).toBe('')
  scope.stop()
})

test('unsaved order status, payment and comment are tracked independently', async () => {
  setActivePinia(createPinia())
  api.getOrder.mockResolvedValueOnce({
    id: 1,
    status: 'new',
    payment_status: 'not_paid',
    payment_amount: null,
    payment_method: null,
    payment_reference: null,
  })
  api.getOrderHistory.mockResolvedValue([])
  api.getOrderComments.mockResolvedValue([])
  const scope = effectScope()
  const state = scope.run(useOrdersWorkspace)!
  await state.selectOrder({ id: 1 })
  expect(state.hasUnsavedChanges.value).toBe(false)
  state.paymentForm.value.payment_method = 'Наличные'
  expect(state.hasUnsavedChanges.value).toBe(true)
  state.paymentForm.value.payment_method = ''
  state.statusDraft.value = 'processing'
  expect(state.hasUnsavedChanges.value).toBe(true)
  expect(state.selected.value?.status).toBe('new')
  state.statusDraft.value = 'new'
  state.commentBody.value = 'Комментарий'
  expect(state.hasUnsavedChanges.value).toBe(true)
  state.clearSelection()
  expect(state.hasUnsavedChanges.value).toBe(false)
  scope.stop()
})

test('saving status and payment preserves drafts in the other form', async () => {
  setActivePinia(createPinia())
  useAuthStore().user = {
    id: 1,
    name: 'Manager',
    email: 'manager@example.test',
    status: 'active',
    last_login_at: null,
    permissions: ['orders.manage', 'payments.manage'],
  }
  const record = {
    id: 1,
    status: 'new',
    payment_status: 'not_paid',
    payment_amount: null,
    payment_method: null,
    payment_reference: null,
  }
  api.getOrder.mockImplementation(async () => ({ ...record }))
  api.getOrderHistory.mockResolvedValue([])
  api.getOrderComments.mockResolvedValue([])
  api.getOrders.mockResolvedValue({
    data: [],
    meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
  })
  api.updateOrderStatus.mockImplementation(async (_id, status) => {
    record.status = status
  })
  api.updateOrderPayment.mockImplementation(async (_id, payload) => {
    Object.assign(record, payload)
  })
  const scope = effectScope()
  const state = scope.run(useOrdersWorkspace)!
  state.statusReady.value = true
  await state.selectOrder({ id: 1 })
  state.paymentForm.value.payment_method = 'Наличные'
  state.statusDraft.value = 'processing'
  await state.changeStatus()
  expect(state.paymentForm.value.payment_method).toBe('Наличные')
  state.statusDraft.value = 'confirmed'
  state.paymentForm.value.payment_status = 'paid'
  state.paymentForm.value.payment_amount = '100.00'
  await state.savePayment()
  expect(state.selected.value?.status).toBe('processing')
  expect(state.statusDraft.value).toBe('confirmed')
  expect(state.hasUnsavedChanges.value).toBe(true)
  scope.stop()
})
