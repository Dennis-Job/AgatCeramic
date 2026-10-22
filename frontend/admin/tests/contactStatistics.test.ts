import { effectScope, nextTick } from 'vue'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { useContactStatistics } from '../src/features/contacts/composables/useContactStatistics'
import { notifyAdminNotificationsChanged } from '../src/features/notifications/services/notificationChanges'
import type { ContactStatistics } from '../src/features/contacts/types/contact.types'

const api = vi.hoisted(() => ({ getContactStatistics: vi.fn() }))
vi.mock('../src/features/contacts/services/contacts', () => api)
const scopes: ReturnType<typeof effectScope>[] = []
const result = (month: string, count = 1): ContactStatistics => ({
  month,
  previous_month: '2026-09',
  timezone: 'Europe/Moscow',
  metrics: {
    partner: { count, previous_count: 0, change_percent: null },
    callback: { count: 0, previous_count: 0, change_percent: 0 },
    completed: { count: 0, previous_count: 0, change_percent: 0 },
    rejected: { count: 0, previous_count: 0, change_percent: 0 },
  },
})
function create() {
  const scope = effectScope()
  scopes.push(scope)
  return scope.run(useContactStatistics)!
}
beforeEach(() => {
  vi.resetAllMocks()
  vi.useFakeTimers()
  vi.setSystemTime(new Date('2026-09-30T21:30:00Z'))
  api.getContactStatistics.mockImplementation(async (month: string) =>
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
  expect(api.getContactStatistics).toHaveBeenLastCalledWith('2024-02')
  expect(stats.statistics.value?.month).toBe('2024-02')
})

test('old requests cannot replace the newly selected month or its loading state', async () => {
  let resolve!: (data: ContactStatistics) => void
  api.getContactStatistics.mockReturnValueOnce(
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
  api.getContactStatistics.mockRejectedValueOnce(
    new Error('Статистика недоступна'),
  )
  const stats = create()
  await nextTick()
  expect(stats.error.value).toBe('Статистика недоступна')
  expect(stats.statistics.value).toBeNull()
  await stats.refresh()
  expect(stats.error.value).toBe('')
  expect(stats.statistics.value?.metrics.partner.count).toBe(1)
  api.getContactStatistics.mockResolvedValueOnce(result('2026-10', 2))
  notifyAdminNotificationsChanged()
  await nextTick()
  expect(stats.statistics.value?.metrics.partner.count).toBe(2)
})

test('disposing unsubscribes and late responses cannot mutate the abandoned scope', async () => {
  let resolve!: (data: ContactStatistics) => void
  api.getContactStatistics.mockReturnValueOnce(
    new Promise((done) => (resolve = done)),
  )
  const stats = create()
  scopes[0]!.stop()
  resolve(result('2026-10'))
  await nextTick()
  notifyAdminNotificationsChanged()
  await nextTick()
  expect(stats.statistics.value).toBeNull()
  expect(api.getContactStatistics).toHaveBeenCalledTimes(1)
})
