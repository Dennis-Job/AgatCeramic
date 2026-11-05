import { effectScope, nextTick } from 'vue'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { useOrderStatusCounts } from '../src/features/orders/composables/useOrderStatusCounts'
import { notifyAdminNotificationsChanged } from '../src/features/notifications/services/notificationChanges'

const api = vi.hoisted(() => ({ getOrderStatusCounts: vi.fn() }))
vi.mock('../src/features/orders/services/orders', () => api)
let scope: ReturnType<typeof effectScope>
function create() {
  scope = effectScope()
  return scope.run(useOrderStatusCounts)!
}
beforeEach(() => vi.resetAllMocks())
afterEach(() => scope?.stop())

test('zero counts remain real values and successful mutations refresh the whole queue', async () => {
  api.getOrderStatusCounts.mockResolvedValue({ new: 3, working: 0, shipped: 0 })
  const state = create()
  expect(state.statusCounts.value).toBeNull()
  await state.refreshStatusCounts()
  expect(state.statusCounts.value).toEqual({ new: 3, working: 0, shipped: 0 })
  api.getOrderStatusCounts.mockResolvedValue({ new: 2, working: 1, shipped: 0 })
  notifyAdminNotificationsChanged()
  await nextTick()
  expect(state.statusCounts.value?.working).toBe(1)
  expect(api.getOrderStatusCounts).toHaveBeenCalledTimes(2)
})

test('errors hide unknown counts and retry recovers without fabricated zeros', async () => {
  api.getOrderStatusCounts.mockRejectedValueOnce(
    new Error('Счётчики недоступны'),
  )
  const state = create()
  await state.refreshStatusCounts()
  expect(state.statusCounts.value).toBeNull()
  expect(state.countsError.value).toBe('Счётчики недоступны')
  api.getOrderStatusCounts.mockResolvedValue({ new: 0, working: 0, shipped: 0 })
  await state.refreshStatusCounts()
  expect(state.countsError.value).toBe('')
  expect(state.statusCounts.value?.new).toBe(0)
})

test('older replies and disposed scopes cannot replace current counters', async () => {
  let finish!: (value: {
    new: number
    working: number
    shipped: number
  }) => void
  api.getOrderStatusCounts.mockReturnValueOnce(
    new Promise((resolve) => {
      finish = resolve
    }),
  )
  api.getOrderStatusCounts.mockResolvedValueOnce({
    new: 2,
    working: 1,
    shipped: 0,
  })
  const state = create()
  const pending = state.refreshStatusCounts()
  await state.refreshStatusCounts()
  finish({ new: 99, working: 99, shipped: 99 })
  await pending
  expect(state.statusCounts.value?.new).toBe(2)
  api.getOrderStatusCounts.mockReturnValueOnce(
    new Promise((resolve) => {
      finish = resolve
    }),
  )
  const abandoned = state.refreshStatusCounts()
  scope.stop()
  finish({ new: 55, working: 55, shipped: 55 })
  await abandoned
  notifyAdminNotificationsChanged()
  await nextTick()
  expect(state.statusCounts.value?.new).toBe(2)
  expect(api.getOrderStatusCounts).toHaveBeenCalledTimes(3)
})
