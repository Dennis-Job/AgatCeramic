import { computed, onScopeDispose, ref, watch, type UnwrapRef } from 'vue'
import { useAuthStore } from '../../../stores/auth'
import { getContacts } from '../../contacts/services/contacts'
import type { Contact } from '../../contacts/types/contact.types'
import { getOrders } from '../../orders/services/orders'
import type { Order } from '../../orders/types/order.types'
import { subscribeAdminNotificationChanges } from '../services/notificationChanges'

export function useAdminNotifications() {
  const auth = useAuthStore()
  const canViewContacts = computed(() => auth.hasPermission('contacts.view'))
  const canViewOrders = computed(() => auth.hasPermission('orders.view'))
  const canView = computed(() => canViewContacts.value || canViewOrders.value)
  const contacts = ref<Contact[]>([])
  const orders = ref<Order[]>([])
  const contactsTotal = ref(0)
  const ordersTotal = ref(0)
  const total = computed(() => contactsTotal.value + ordersTotal.value)
  const loading = ref(false)
  const error = ref('')
  let generation = 0
  let timer: ReturnType<typeof setInterval> | undefined
  let disposed = false

  function clear() {
    contacts.value = []
    orders.value = []
    contactsTotal.value = 0
    ordersTotal.value = 0
    loading.value = false
    error.value = ''
  }

  async function refresh(): Promise<void> {
    if (!canView.value || loading.value || disposed) return
    const request = generation
    const userId = auth.user?.id
    loading.value = true
    error.value = ''

    const [contactResult, orderResult] = await Promise.allSettled([
      canViewContacts.value
        ? getContacts({ status: 'new' }, { perPage: 5 })
        : Promise.resolve(null),
      canViewOrders.value
        ? getOrders({ status: 'new' }, { perPage: 5 })
        : Promise.resolve(null),
    ])
    if (
      disposed ||
      request !== generation ||
      !canView.value ||
      userId !== auth.user?.id
    )
      return

    const failures: string[] = []
    if (contactResult.status === 'fulfilled') {
      contacts.value = contactResult.value?.data ?? []
      contactsTotal.value = contactResult.value?.meta.total ?? 0
    } else {
      contacts.value = []
      contactsTotal.value = 0
      failures.push('Не удалось загрузить обращения.')
    }
    if (orderResult.status === 'fulfilled') {
      orders.value = orderResult.value?.data ?? []
      ordersTotal.value = orderResult.value?.meta.total ?? 0
    } else {
      orders.value = []
      ordersTotal.value = 0
      failures.push('Не удалось загрузить заказы.')
    }
    error.value = failures.join(' ')
    loading.value = false
  }

  watch(
    [() => auth.user?.id, () => JSON.stringify(auth.user?.permissions ?? [])],
    () => {
      generation++
      clearInterval(timer)
      clear()
      if (canView.value) {
        void refresh()
        timer = setInterval(() => void refresh(), 30_000)
      }
    },
    { immediate: true, flush: 'sync' },
  )

  const unsubscribe = subscribeAdminNotificationChanges(() => {
    // A response started before the save must not restore the old totals.
    generation++
    loading.value = false
    void refresh()
  })

  onScopeDispose(() => {
    unsubscribe()
    disposed = true
    generation++
    clearInterval(timer)
    clear()
  })

  return {
    contacts,
    orders,
    contactsTotal,
    ordersTotal,
    total,
    loading,
    error,
    canView,
    canViewContacts,
    canViewOrders,
    refresh,
  }
}

export type AdminNotificationState = UnwrapRef<
  ReturnType<typeof useAdminNotifications>
>
