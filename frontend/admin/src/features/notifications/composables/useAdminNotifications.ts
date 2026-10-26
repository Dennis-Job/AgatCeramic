import { computed, onScopeDispose, ref, watch, type UnwrapRef } from 'vue'
import { useAuthStore } from '../../../stores/auth'
import { getContacts } from '../../contacts/services/contacts'
import type { Contact } from '../../contacts/types/contact.types'
import { getOrders } from '../../orders/services/orders'
import type { Order } from '../../orders/types/order.types'
import { subscribeAdminNotificationChanges } from '../services/notificationChanges'
import type { PaginationMeta } from '../../../services/pagination'

const emptyMeta = (): PaginationMeta => ({
  current_page: 1,
  last_page: 1,
  per_page: 5,
  total: 0,
})

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
  const contactsError = ref('')
  const ordersError = ref('')
  const contactsMeta = ref(emptyMeta())
  const ordersMeta = ref(emptyMeta())
  const contactsPage = ref(1)
  const ordersPage = ref(1)
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
    contactsError.value = ''
    ordersError.value = ''
    contactsMeta.value = emptyMeta()
    ordersMeta.value = emptyMeta()
    contactsPage.value = 1
    ordersPage.value = 1
  }

  async function refresh(): Promise<void> {
    if (!canView.value || loading.value || disposed) return
    const request = generation
    const userId = auth.user?.id
    loading.value = true
    error.value = ''
    contactsError.value = ''
    ordersError.value = ''

    const [contactResult, orderResult] = await Promise.allSettled([
      canViewContacts.value
        ? getContacts(
            { status: 'new' },
            {
              perPage: 5,
              ...(contactsPage.value > 1 ? { page: contactsPage.value } : {}),
            },
          )
        : Promise.resolve(null),
      canViewOrders.value
        ? getOrders(
            { status: 'new' },
            {
              perPage: 5,
              ...(ordersPage.value > 1 ? { page: ordersPage.value } : {}),
            },
          )
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
      contactsMeta.value = { ...emptyMeta(), ...contactResult.value?.meta }
    } else {
      contacts.value = []
      contactsTotal.value = 0
      failures.push('Не удалось загрузить обращения.')
      contactsError.value = 'Не удалось загрузить обращения.'
      contactsMeta.value = emptyMeta()
    }
    if (orderResult.status === 'fulfilled') {
      orders.value = orderResult.value?.data ?? []
      ordersTotal.value = orderResult.value?.meta.total ?? 0
      ordersMeta.value = { ...emptyMeta(), ...orderResult.value?.meta }
    } else {
      orders.value = []
      ordersTotal.value = 0
      failures.push('Не удалось загрузить заказы.')
      ordersError.value = 'Не удалось загрузить заказы.'
      ordersMeta.value = emptyMeta()
    }
    error.value = failures.join(' ')
    loading.value = false
    const nextContacts = Math.min(
      contactsPage.value,
      Math.max(1, contactsMeta.value.last_page),
    )
    const nextOrders = Math.min(
      ordersPage.value,
      Math.max(1, ordersMeta.value.last_page),
    )
    if (
      (!contactsError.value && nextContacts !== contactsPage.value) ||
      (!ordersError.value && nextOrders !== ordersPage.value)
    ) {
      if (!contactsError.value) contactsPage.value = nextContacts
      if (!ordersError.value) ordersPage.value = nextOrders
      await refresh()
    }
  }

  async function setPage(
    section: 'contacts' | 'orders',
    page: number,
  ): Promise<void> {
    if (!Number.isInteger(page) || page < 1) return
    if (section === 'contacts' && !canViewContacts.value) return
    if (section === 'orders' && !canViewOrders.value) return
    generation++
    loading.value = false
    if (section === 'contacts') {
      contactsPage.value = page
      contacts.value = []
    } else {
      ordersPage.value = page
      orders.value = []
    }
    await refresh()
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
    contactsError,
    ordersError,
    contactsMeta,
    ordersMeta,
    setPage,
    canView,
    canViewContacts,
    canViewOrders,
    refresh,
  }
}

export type AdminNotificationState = UnwrapRef<
  ReturnType<typeof useAdminNotifications>
>
