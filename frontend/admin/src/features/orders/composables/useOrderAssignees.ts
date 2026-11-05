import { computed, onScopeDispose, ref, type Ref } from 'vue'
import { getOrderAssignees } from '../services/orders'
import type { Order, OrderAssignee } from '../types/order.types'

export function useOrderAssignees(
  canManage: Ref<boolean>,
  selected: Ref<Order | null>,
) {
  const assignees = ref<OrderAssignee[]>([])
  const ready = ref(false)
  const loading = ref(false)
  const error = ref('')
  let generation = 0
  let disposed = false
  onScopeDispose(() => {
    disposed = true
    generation++
  })
  const canAssign = computed(
    () => canManage.value && ready.value && !loading.value && !error.value,
  )
  const options = computed(() => {
    const items = assignees.value.map((item) => ({
      value: String(item.id),
      label: item.name,
    }))
    const current = selected.value?.assignee
    if (current && !assignees.value.some((item) => item.id === current.id))
      items.push({ value: String(current.id), label: current.name })
    return [{ value: '', label: 'Не назначен' }, ...items]
  })
  const filterOptions = computed(() => [
    { value: '', label: 'Все сотрудники' },
    { value: 'unassigned', label: 'Не назначен' },
    ...assignees.value.map((item) => ({
      value: String(item.id),
      label: item.name,
    })),
  ])
  async function load(): Promise<void> {
    if (!canManage.value || disposed) return
    const request = ++generation
    loading.value = true
    ready.value = false
    error.value = ''
    try {
      const items = await getOrderAssignees()
      if (request === generation && !disposed) {
        assignees.value = items
        ready.value = true
      }
    } catch (reason) {
      if (request === generation && !disposed)
        error.value =
          reason instanceof Error
            ? reason.message
            : 'Не удалось загрузить список ответственных.'
    } finally {
      if (request === generation && !disposed) loading.value = false
    }
  }
  return {
    assignees,
    ready,
    loading,
    error,
    canAssign,
    options,
    filterOptions,
    load,
  }
}
