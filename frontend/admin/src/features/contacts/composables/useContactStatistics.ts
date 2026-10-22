import { getCurrentScope, onScopeDispose, ref, watch } from 'vue'
import { currentMoscowMonth } from '../../../utils/month'
import { subscribeAdminNotificationChanges } from '../../notifications/services/notificationChanges'
import { getContactStatistics } from '../services/contacts'
import type { ContactStatistics } from '../types/contact.types'

export function useContactStatistics() {
  const selectedMonth = ref(currentMoscowMonth())
  const statistics = ref<ContactStatistics | null>(null)
  const loading = ref(false)
  const error = ref('')
  let generation = 0
  let disposed = false

  async function refresh(): Promise<void> {
    if (disposed) return
    const request = ++generation
    statistics.value = null
    loading.value = true
    error.value = ''
    try {
      const result = await getContactStatistics(selectedMonth.value)
      if (request === generation && !disposed) statistics.value = result
    } catch (cause) {
      if (request === generation && !disposed)
        error.value =
          cause instanceof Error
            ? cause.message
            : 'Не удалось загрузить статистику обращений.'
    } finally {
      if (request === generation && !disposed) loading.value = false
    }
  }

  const stopWatching = watch(selectedMonth, () => void refresh(), {
    immediate: true,
    flush: 'sync',
  })
  const unsubscribe = subscribeAdminNotificationChanges(() => void refresh())
  if (getCurrentScope())
    onScopeDispose(() => {
      disposed = true
      generation++
      stopWatching()
      unsubscribe()
    })

  return { selectedMonth, statistics, loading, error, refresh }
}
