import { flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { effectScope, type EffectScope } from 'vue'
import { useAuthStore } from '../src/stores/auth'
import { useAdminNotifications } from '../src/features/notifications/composables/useAdminNotifications'
import type { Contact } from '../src/features/contacts/types/contact.types'
import type { Order } from '../src/features/orders/types/order.types'
import type { PaginatedResponse } from '../src/services/pagination'
import { notifyAdminNotificationsChanged } from '../src/features/notifications/services/notificationChanges'

const api = vi.hoisted(() => ({ getContacts: vi.fn(), getOrders: vi.fn() }))
vi.mock('../src/features/contacts/services/contacts', () => ({
  getContacts: api.getContacts,
}))
vi.mock('../src/features/orders/services/orders', () => ({
  getOrders: api.getOrders,
}))

const contacts: Contact[] = ['callback', 'email', 'partner'].map((type, i) => ({
  id: i + 1,
  type: type as Contact['type'],
  contact: { name: 'Покупатель', phone: '+79991234567', email: null },
  message: null,
  source: 'site',
  status: 'new',
  assigned_at: null,
  completed_at: null,
  created_at: '2026-10-21T10:00:00Z',
}))
const order: Order = {
  id: 42,
  order_number: 'AG-42',
  customer: { name: 'Покупатель', phone: '+79991234567', email: null },
  delivery_address: 'Тестовый адрес',
  customer_comment: null,
  status: 'new',
  payment_status: 'unpaid',
  payment_amount: null,
  payment_method: null,
  payment_reference: null,
  total_amount: '1000.00',
  items: [],
  paid_at: null,
  completed_at: null,
  created_at: '2026-10-21T10:00:00Z',
}
function page<T>(data: T[], total: number): PaginatedResponse<T> {
  return {
    data,
    meta: { current_page: 1, last_page: 2, per_page: 5, total },
  }
}
function deferred<T>() {
  let resolve!: (value: T) => void
  const promise = new Promise<T>((finish) => {
    resolve = finish
  })
  return { promise, resolve }
}
function authorize(permissions = ['contacts.view', 'orders.view'], id = 1) {
  useAuthStore().user = {
    id,
    name: 'Сотрудник',
    email: 'admin@example.test',
    status: 'active',
    last_login_at: null,
    permissions,
  }
}
let scopes: EffectScope[] = []
function start() {
  const scope = effectScope()
  scopes.push(scope)
  return { scope, state: scope.run(() => useAdminNotifications())! }
}
beforeEach(() => {
  setActivePinia(createPinia())
  vi.resetAllMocks()
  vi.useFakeTimers()
  api.getContacts.mockResolvedValue(page(contacts, 8))
  api.getOrders.mockResolvedValue(page([order], 12))
})
afterEach(() => {
  scopes.forEach((scope) => scope.stop())
  scopes = []
  vi.useRealTimers()
})

test('counts all new contact types and new orders from totals rather than preview lengths', async () => {
  authorize()
  const { state } = start()
  await flushPromises()
  expect(api.getContacts).toHaveBeenCalledWith(
    { status: 'new' },
    { perPage: 5 },
  )
  expect(api.getOrders).toHaveBeenCalledWith({ status: 'new' }, { perPage: 5 })
  expect(state.contacts.value).toEqual(contacts)
  expect(state.orders.value).toEqual([order])
  expect(state.contactsTotal.value).toBe(8)
  expect(state.ordersTotal.value).toBe(12)
  expect(state.total.value).toBe(20)
  expect(state.loading.value).toBe(false)
})

test('a saved status immediately refreshes counters without waiting for polling', async () => {
  authorize()
  const { state, scope } = start()
  await flushPromises()
  api.getContacts.mockResolvedValue(page([], 0))
  api.getOrders.mockResolvedValue(page([], 0))
  notifyAdminNotificationsChanged()
  await flushPromises()
  expect(state.total.value).toBe(0)
  expect(api.getContacts).toHaveBeenCalledTimes(2)
  expect(api.getOrders).toHaveBeenCalledTimes(2)
  scope.stop()
  notifyAdminNotificationsChanged()
  await flushPromises()
  expect(api.getContacts).toHaveBeenCalledTimes(2)
})

test('pagination ignores a superseded response', async () => {
  authorize()
  const { state } = start()
  await flushPromises()
  const pending = deferred<PaginatedResponse<Contact>>()
  api.getContacts.mockReturnValueOnce(pending.promise)
  const stale = state.setPage('contacts', 2)
  api.getContacts.mockResolvedValue(page([contacts[2]!], 8))
  await state.setPage('contacts', 1)
  pending.resolve({
    ...page([contacts[1]!], 8),
    meta: { current_page: 2, last_page: 2, per_page: 5, total: 8 },
  })
  await stale
  expect(api.getContacts).toHaveBeenCalledWith(
    { status: 'new' },
    { perPage: 5, page: 2 },
  )
  expect(state.contacts.value).toEqual([contacts[2]])
  expect(state.contactsMeta.value.current_page).toBe(1)
  expect(state.ordersTotal.value).toBe(12)
})

test('a saved status invalidates a pending background result and immediately requests fresh totals', async () => {
  authorize()
  const oldContacts = deferred<PaginatedResponse<Contact>>()
  const oldOrders = deferred<PaginatedResponse<Order>>()
  api.getContacts.mockReturnValueOnce(oldContacts.promise)
  api.getOrders.mockReturnValueOnce(oldOrders.promise)
  const { state } = start()
  api.getContacts.mockResolvedValue(page([], 0))
  api.getOrders.mockResolvedValue(page([], 0))
  notifyAdminNotificationsChanged()
  await flushPromises()
  expect(state.total.value).toBe(0)
  expect(api.getContacts).toHaveBeenCalledTimes(2)
  oldContacts.resolve(page(contacts, 8))
  oldOrders.resolve(page([order], 12))
  await flushPromises()
  expect(state.total.value).toBe(0)
  expect(state.loading.value).toBe(false)
})

test.each([
  { permissions: [], contactsCalls: 0, ordersCalls: 0, total: 0 },
  {
    permissions: ['contacts.view'],
    contactsCalls: 1,
    ordersCalls: 0,
    total: 8,
  },
  { permissions: ['orders.view'], contactsCalls: 0, ordersCalls: 1, total: 12 },
])(
  'only queries permitted resources for $permissions',
  async ({ permissions, contactsCalls, ordersCalls, total }) => {
    authorize(permissions)
    const { state } = start()
    await flushPromises()
    expect(api.getContacts).toHaveBeenCalledTimes(contactsCalls)
    expect(api.getOrders).toHaveBeenCalledTimes(ordersCalls)
    expect(state.canView.value).toBe(total > 0)
    expect(state.canViewContacts.value).toBe(contactsCalls > 0)
    expect(state.canViewOrders.value).toBe(ordersCalls > 0)
    expect(state.total.value).toBe(total)
  },
)

test('does not poll or manually refresh while signed out', async () => {
  const { state } = start()
  await state.refresh()
  await vi.advanceTimersByTimeAsync(60_000)
  expect(api.getContacts).not.toHaveBeenCalled()
  expect(api.getOrders).not.toHaveBeenCalled()
  expect(state.total.value).toBe(0)
})

test('polls once per interval and skips overlapping manual and timer refreshes', async () => {
  authorize()
  const pending = deferred<PaginatedResponse<Contact>>()
  api.getContacts.mockReturnValueOnce(pending.promise)
  const { state, scope } = start()
  await state.refresh()
  await vi.advanceTimersByTimeAsync(60_000)
  expect(api.getContacts).toHaveBeenCalledTimes(1)
  expect(api.getOrders).toHaveBeenCalledTimes(1)
  expect(state.loading.value).toBe(true)
  pending.resolve(page(contacts, 8))
  await flushPromises()
  api.getContacts.mockResolvedValue(page([], 0))
  api.getOrders.mockResolvedValue(page([], 0))
  await vi.advanceTimersByTimeAsync(30_000)
  expect(api.getContacts).toHaveBeenCalledTimes(2)
  expect(api.getOrders).toHaveBeenCalledTimes(2)
  expect(state.total.value).toBe(0)
  expect(state.contacts.value).toEqual([])
  expect(state.orders.value).toEqual([])
  scope.stop()
  await state.refresh()
  await vi.advanceTimersByTimeAsync(60_000)
  expect(api.getContacts).toHaveBeenCalledTimes(2)
  expect(api.getOrders).toHaveBeenCalledTimes(2)
})

test.each(['contacts', 'orders'])(
  'clears failed %s results while preserving successful results and allows retry',
  async (failed) => {
    authorize()
    const { state } = start()
    await flushPromises()
    const failedRequest =
      failed === 'contacts' ? api.getContacts : api.getOrders
    failedRequest.mockRejectedValueOnce(new Error('Недоступно'))
    await state.refresh()
    expect(state.contactsTotal.value).toBe(failed === 'contacts' ? 0 : 8)
    expect(state.ordersTotal.value).toBe(failed === 'orders' ? 0 : 12)
    expect(state.contacts.value).toEqual(failed === 'contacts' ? [] : contacts)
    expect(state.orders.value).toEqual(failed === 'orders' ? [] : [order])
    expect(state.error.value).not.toBe('')
    expect(state.loading.value).toBe(false)
    await state.refresh()
    expect(state.error.value).toBe('')
    expect(state.total.value).toBe(20)
  },
)

test('reports both failures and clears the combined counter', async () => {
  authorize()
  api.getContacts.mockRejectedValueOnce(null)
  api.getOrders.mockRejectedValueOnce(new Error('Недоступно'))
  const { state } = start()
  await flushPromises()
  expect(state.total.value).toBe(0)
  expect(state.error.value).toContain('обращения')
  expect(state.error.value).toContain('заказы')
  expect(state.loading.value).toBe(false)
})

test.each([
  'logout',
  'different user',
  'same user relogin',
  'permission revocation',
  'other permission change',
  'unmount',
])('ignores late results after %s', async (change) => {
  authorize()
  const pendingContacts = deferred<PaginatedResponse<Contact>>()
  const pendingOrders = deferred<PaginatedResponse<Order>>()
  api.getContacts.mockReturnValueOnce(pendingContacts.promise)
  api.getOrders.mockReturnValueOnce(pendingOrders.promise)
  const { state, scope } = start()
  api.getContacts.mockResolvedValue(page([], 0))
  api.getOrders.mockResolvedValue(page([], 0))
  if (change === 'logout') useAuthStore().user = null
  if (change === 'different user') authorize(undefined, 2)
  if (change === 'same user relogin') {
    useAuthStore().user = null
    authorize()
  }
  if (change === 'permission revocation') useAuthStore().user!.permissions = []
  if (change === 'other permission change')
    useAuthStore().user!.permissions.push('products.view')
  if (change === 'unmount') scope.stop()
  await flushPromises()
  pendingContacts.resolve(page(contacts, 8))
  pendingOrders.resolve(page([order], 12))
  await flushPromises()
  expect(state.contacts.value).toEqual([])
  expect(state.orders.value).toEqual([])
  expect(state.total.value).toBe(0)
  expect(state.loading.value).toBe(false)
})

test('immediately clears cached data on permission loss and restarts only remaining permitted requests', async () => {
  authorize()
  const { state } = start()
  await flushPromises()
  const pending = deferred<PaginatedResponse<Order>>()
  api.getOrders.mockReturnValueOnce(pending.promise)
  useAuthStore().user!.permissions = ['orders.view']
  expect(state.contacts.value).toEqual([])
  expect(state.orders.value).toEqual([])
  expect(state.total.value).toBe(0)
  pending.resolve(page([order], 3))
  await flushPromises()
  expect(state.contactsTotal.value).toBe(0)
  expect(state.ordersTotal.value).toBe(3)
  await vi.advanceTimersByTimeAsync(30_000)
  expect(api.getContacts).toHaveBeenCalledTimes(1)
  expect(api.getOrders).toHaveBeenCalledTimes(3)
})
