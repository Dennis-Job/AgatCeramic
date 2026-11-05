import { onScopeDispose, ref } from 'vue'
import { getOrderStatusCounts } from '../services/orders'
import { subscribeAdminNotificationChanges } from '../../notifications/services/notificationChanges'
import type { OrderStatusCounts } from '../types/order.types'

export function useOrderStatusCounts() {
  const statusCounts = ref<OrderStatusCounts | null>(null)
  const countsError = ref('')
  const countsLoading = ref(false)
  let generation = 0
  let started = false
  let disposed = false

  async function refreshStatusCounts(): Promise<void> {
    if (disposed) return
    started = true
    const request = ++generation
    countsError.value = ''
    countsLoading.value = true
    try {
      const result = await getOrderStatusCounts()
      if (request === generation && !disposed) statusCounts.value = result
    } catch (reason) {
      if (request !== generation || disposed) return
      statusCounts.value = null
      countsError.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось загрузить счётчики заказов.'
    } finally {
      if (request === generation && !disposed) countsLoading.value = false
    }
  }
  const unsubscribe = subscribeAdminNotificationChanges(() => {
    if (started) void refreshStatusCounts()
  })
  onScopeDispose(() => {
    disposed = true
    generation++
    unsubscribe()
  })
  return { statusCounts, countsError, countsLoading, refreshStatusCounts }
}
