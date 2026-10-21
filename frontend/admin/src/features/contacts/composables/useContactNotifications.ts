import { computed, onScopeDispose, ref, watch } from 'vue'
import { useAuthStore } from '../../../stores/auth'
import { getContacts } from '../services/contacts'
import type { Contact } from '../types/contact.types'

export function useContactNotifications() {
  const auth = useAuthStore()
  const canView = computed(() => auth.hasPermission('contacts.view'))
  const contacts = ref<Contact[]>([])
  const total = ref(0)
  const loading = ref(false)
  const error = ref('')
  let generation = 0
  let timer: ReturnType<typeof setInterval> | undefined
  let disposed = false

  async function refresh(): Promise<void> {
    if (!canView.value || loading.value || disposed) return
    const request = generation
    const userId = auth.user?.id
    const current = () =>
      !disposed &&
      request === generation &&
      canView.value &&
      userId === auth.user?.id
    loading.value = true
    error.value = ''
    try {
      const result = await getContacts(
        { type: 'callback', status: 'new' },
        { perPage: 5 },
      )
      if (!current()) return
      contacts.value = result.data
      total.value = result.meta.total
    } catch (reason) {
      if (!current()) return
      contacts.value = []
      total.value = 0
      error.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось загрузить уведомления.'
    } finally {
      if (current()) loading.value = false
    }
  }

  watch(
    [() => auth.user?.id, canView],
    () => {
      generation++
      clearInterval(timer)
      contacts.value = []
      total.value = 0
      loading.value = false
      error.value = ''
      if (canView.value) {
        void refresh()
        timer = setInterval(() => void refresh(), 30_000)
      }
    },
    { immediate: true, flush: 'sync' },
  )

  onScopeDispose(() => {
    disposed = true
    generation++
    clearInterval(timer)
  })
  return { contacts, total, loading, error, canView, refresh }
}
