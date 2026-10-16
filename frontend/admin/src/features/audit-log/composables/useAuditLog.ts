import { ref, watch } from 'vue'
import { getAuditLogs } from '../services/auditLogs'
import type { AuditLog, AuditLogFilters } from '../types/auditLog.types'

type Filters = Required<Omit<AuditLogFilters, 'page'>>

export { auditActionOptions, auditActionName } from '../utils/auditActions'

export function useAuditLog() {
  const logs = ref<AuditLog[]>([])
  const filters = ref<Filters>({
    search: '',
    action: '',
    date_from: '',
    date_to: '',
  })
  const page = ref(1)
  const lastPage = ref(1)
  const total = ref(0)
  const perPage = ref(25)
  const loading = ref(false)
  const error = ref('')
  let loadSequence = 0

  async function load(nextPage = 1): Promise<void> {
    const sequence = ++loadSequence
    loading.value = true
    error.value = ''
    try {
      const response = await getAuditLogs({ ...filters.value, page: nextPage })
      if (sequence !== loadSequence) return
      logs.value = response.data
      page.value = response.meta.current_page
      lastPage.value = response.meta.last_page
      total.value = response.meta.total
      perPage.value = response.meta.per_page ?? 25
    } catch (reason) {
      if (sequence === loadSequence)
        error.value =
          reason instanceof Error
            ? reason.message
            : 'Не удалось загрузить журнал аудита.'
    } finally {
      if (sequence === loadSequence) loading.value = false
    }
  }

  function reset(): void {
    const reactiveFiltersChanged = Boolean(
      filters.value.action || filters.value.date_from || filters.value.date_to,
    )
    filters.value = { search: '', action: '', date_from: '', date_to: '' }
    if (!reactiveFiltersChanged) void load()
  }

  watch(
    () => [
      filters.value.action,
      filters.value.date_from,
      filters.value.date_to,
    ],
    () => void load(),
  )
  return {
    logs,
    filters,
    page,
    lastPage,
    total,
    perPage,
    loading,
    error,
    load,
    reset,
  }
}
