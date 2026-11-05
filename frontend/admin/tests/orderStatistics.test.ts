import { effectScope, nextTick } from 'vue'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { useOrderStatistics } from '../src/features/orders/composables/useOrderStatistics'
import { notifyAdminNotificationsChanged } from '../src/features/notifications/services/notificationChanges'
import type { OrderStatistics } from '../src/features/orders/types/order.types'

const api = vi.hoisted(() => ({ getOrderStatistics: vi.fn() }))
vi.mock('../src/features/orders/services/orders', () => api)
const scopes: ReturnType<typeof effectScope>[] = []
const result = (month: string, count = 1): OrderStatistics => ({
  month,
  previous_month: '2026-09',
  timezone: 'Europe/Moscow',
  metrics: {
    total: { count, previous_count: 0, change_percent: null },
    new: { count: 0, previous_count: 0, change_percent: 0 },
    completed: { count: 0, previous_count: 0, change_percent: 0 },
    cancelled: { count: 0, previous_count: 0, change_percent: 0 },
  },
})
function create() {
  const scope = effectScope()
  scopes.push(scope)
  return scope.run(useOrderStatistics)!
}
beforeEach(() => {
  vi.resetAllMocks()
  vi.useFakeTimers()
  vi.setSystemTime(new Date('2026-09-30T21:30:00Z'))
  api.getOrderStatistics.mockImplementation(async (month: string) =>
    result(month),
  )
})
afterEach(() => {
  scopes.splice(0).forEach((scope) => scope.stop())
  vi.useRealTimers()
})

test('defaults to Moscow calendar month and requests only the selected month', async () => {
  const stats = create()
  expect(stats.selectedMonth.value).toBe('2026-10')
  await nextTick()
  expect(stats.statistics.value?.month).toBe('2026-10')
  stats.selectedMonth.value = '2024-02'
  expect(stats.loading.value).toBe(true)
  expect(stats.statistics.value).toBeNull()
  await nextTick()
  expect(api.getOrderStatistics).toHaveBeenLastCalledWith('2024-02')
  expect(stats.statistics.value?.month).toBe('2024-02')
})

test('old requests cannot replace the newly selected month or its loading state', async () => {
  let resolve!: (data: OrderStatistics) => void
  api.getOrderStatistics.mockReturnValueOnce(
    new Promise((done) => (resolve = done)),
  )
  const stats = create()
  stats.selectedMonth.value = '2025-01'
  await nextTick()
  resolve(result('2026-10', 999))
  await nextTick()
  expect(stats.statistics.value?.month).toBe('2025-01')
  expect(stats.loading.value).toBe(false)
})

test('failures stay unknown, retry recovers and successful changes trigger immediate refresh', async () => {
  api.getOrderStatistics.mockRejectedValueOnce(
    new Error('Статистика недоступна'),
  )
  const stats = create()
  await nextTick()
  expect(stats.error.value).toBe('Статистика недоступна')
  expect(stats.statistics.value).toBeNull()
  await stats.refresh()
  expect(stats.error.value).toBe('')
  expect(stats.statistics.value?.metrics.total.count).toBe(1)
  api.getOrderStatistics.mockResolvedValueOnce(result('2026-10', 2))
  notifyAdminNotificationsChanged()
  await nextTick()
  expect(stats.statistics.value?.metrics.total.count).toBe(2)
})

test('disposing unsubscribes and late responses cannot mutate the abandoned scope', async () => {
  let resolve!: (data: OrderStatistics) => void
  api.getOrderStatistics.mockReturnValueOnce(
    new Promise((done) => (resolve = done)),
  )
  const stats = create()
  scopes[0]!.stop()
  resolve(result('2026-10'))
  await nextTick()
  notifyAdminNotificationsChanged()
  await nextTick()
  expect(stats.statistics.value).toBeNull()
  expect(api.getOrderStatistics).toHaveBeenCalledTimes(1)
})
