import { computed, onScopeDispose, ref, watch } from 'vue'
import { formatMoney } from '../../../utils/formatMoney'
import { useAuthStore } from '../../../stores/auth'
import { notifyAdminNotificationsChanged } from '../../notifications/services/notificationChanges'
import { useOrderAssignees } from './useOrderAssignees'
import {
  addOrderComment,
  assignOrder,
  getOrder,
  getOrderComments,
  getOrderHistory,
  getOrders,
  getOrderStatuses,
  updateOrderPayment,
  updateOrderStatus,
} from '../services/orders'
import type {
  Order,
  OrderComment,
  OrderHistory,
  OrderStatus,
  PaymentForm,
} from '../types/order.types'

export const orderPaymentOptions = [
  { label: 'Все оплаты', value: '' },
  { label: 'Не оплачено', value: 'not_paid' },
  { label: 'Ожидает оплаты', value: 'pending' },
  { label: 'Оплачено', value: 'paid' },
  { label: 'Частично оплачено', value: 'partially_paid' },
  { label: 'Возврат', value: 'refunded' },
]

export function useOrdersWorkspace() {
  const auth = useAuthStore()
  const orders = ref<Order[]>([])
  const selected = ref<Order | null>(null)
  const statuses = ref<OrderStatus[]>([])
  const histories = ref<OrderHistory[]>([])
  const comments = ref<OrderComment[]>([])
  const search = ref('')
  const status = ref('')
  const paymentStatus = ref('')
  const assigneeFilter = ref('')
  const page = ref(1)
  const meta = ref({ current_page: 1, last_page: 1, per_page: 25, total: 0 })
  const loading = ref(true)
  const detailLoading = ref(false)
  let detailRequest = 0
  let listRequest = 0
  onScopeDispose(() => {
    detailRequest++
    listRequest++
  })
  const error = ref('')
  const statusLoading = ref(false)
  const statusReady = ref(false)
  const statusError = ref('')
  const actionError = ref('')
  const saving = ref(false)
  const commentBody = ref('')
  const persistedStatus = ref('')
  const paymentForm = ref<PaymentForm>({
    payment_status: 'not_paid',
    payment_amount: '',
    payment_method: '',
    payment_reference: '',
  })
  const canManage = computed(() => auth.hasPermission('orders.manage'))
  const assignment = useOrderAssignees(canManage, selected)
  const canManageStatus = computed(
    () =>
      canManage.value &&
      statusReady.value &&
      !statusLoading.value &&
      !statusError.value,
  )
  const canManagePayment = computed(() => auth.hasPermission('payments.manage'))
  const workingStatuses = computed(() =>
    statuses.value
      .filter(
        (item) => !item.is_terminal && !['new', 'shipped'].includes(item.code),
      )
      .map((item) => item.code),
  )
  const statusOptions = computed(() =>
    [
      { label: 'Все', value: '' },
      { label: 'Новые', value: 'new' },
      { label: 'В работе', value: 'working' },
      { label: 'В доставке', value: 'shipped' },
      { label: 'Завершённые', value: 'completed' },
      { label: 'Отменённые', value: 'cancelled' },
    ].filter(
      (item) =>
        item.value === '' ||
        (item.value === 'working'
          ? workingStatuses.value.length > 0
          : statuses.value.some((status) => status.code === item.value)),
    ),
  )
  const paymentEditOptions = orderPaymentOptions.slice(1)
  const hasUnsavedChanges = computed(
    () =>
      Boolean(commentBody.value.trim()) ||
      Boolean(
        selected.value &&
        (selected.value.status !== persistedStatus.value ||
          paymentForm.value.payment_status !== selected.value.payment_status ||
          paymentForm.value.payment_amount !==
            (selected.value.payment_amount ?? '') ||
          paymentForm.value.payment_method !==
            (selected.value.payment_method ?? '') ||
          paymentForm.value.payment_reference !==
            (selected.value.payment_reference ?? '')),
      ),
  )

  const date = (value: string | null): string =>
    value ? new Date(value).toLocaleString('ru-RU') : '—'
  const amount = formatMoney
  const statusName = (code: string): string =>
    statuses.value.find((item) => item.code === code)?.name ?? code
  const paymentName = (code: string): string =>
    orderPaymentOptions.find((item) => item.value === code)?.label ?? code

  async function load(nextPage = 1): Promise<void> {
    const request = ++listRequest
    loading.value = true
    error.value = ''
    try {
      const response = await getOrders(
        {
          search: search.value,
          status: status.value === 'working' ? '' : status.value,
          ...(status.value === 'working'
            ? { statuses: workingStatuses.value }
            : {}),
          payment_status: paymentStatus.value,
          ...(assigneeFilter.value
            ? {
                assignee_id:
                  assigneeFilter.value === 'unassigned'
                    ? ''
                    : assigneeFilter.value,
                unassigned: assigneeFilter.value === 'unassigned',
              }
            : {}),
        },
        { page: nextPage },
      )
      if (request !== listRequest) return
      orders.value = response.data
      meta.value = response.meta
      page.value = response.meta.current_page
    } catch (reason) {
      if (request !== listRequest) return
      error.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось загрузить заказы.'
    } finally {
      if (request === listRequest) loading.value = false
    }
  }

  function clearSelection(): void {
    detailRequest++
    selected.value = null
    histories.value = []
    comments.value = []
    commentBody.value = ''
    persistedStatus.value = ''
    actionError.value = ''
    detailLoading.value = false
  }

  function syncPaymentForm(): void {
    if (!selected.value) return
    paymentForm.value = {
      payment_status: selected.value.payment_status,
      payment_amount: selected.value.payment_amount ?? '',
      payment_method: selected.value.payment_method ?? '',
      payment_reference: selected.value.payment_reference ?? '',
    }
  }

  async function selectOrder(order: Pick<Order, 'id'>): Promise<void> {
    const request = ++detailRequest
    detailLoading.value = true
    actionError.value = ''
    selected.value = null
    histories.value = []
    comments.value = []
    try {
      const item = await getOrder(order.id)
      if (request !== detailRequest) return
      selected.value = item
      persistedStatus.value = item.status
      syncPaymentForm()
      const [history, activity] = await Promise.all([
        getOrderHistory(order.id),
        getOrderComments(order.id),
      ])
      if (request !== detailRequest) return
      histories.value = history
      comments.value = activity
    } catch (reason) {
      if (request !== detailRequest) return
      actionError.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось загрузить детали заказа.'
    } finally {
      if (request === detailRequest) detailLoading.value = false
    }
  }

  async function changeStatus(): Promise<void> {
    if (!selected.value || !canManageStatus.value || saving.value) return
    const paymentDraft = { ...paymentForm.value }
    saving.value = true
    actionError.value = ''
    try {
      await updateOrderStatus(selected.value.id, selected.value.status)
      notifyAdminNotificationsChanged()
      await selectOrder(selected.value)
      paymentForm.value = paymentDraft
      await load(page.value)
    } catch (reason) {
      actionError.value =
        reason instanceof Error ? reason.message : 'Не удалось изменить статус.'
    } finally {
      saving.value = false
    }
  }

  async function savePayment(): Promise<void> {
    if (!selected.value || !canManagePayment.value || saving.value) return
    const statusDraft = selected.value.status
    saving.value = true
    actionError.value = ''
    try {
      const payload = ['not_paid', 'pending'].includes(
        paymentForm.value.payment_status,
      )
        ? { payment_status: paymentForm.value.payment_status }
        : { ...paymentForm.value, paid_at: new Date().toISOString() }
      await updateOrderPayment(selected.value.id, payload)
      await selectOrder(selected.value)
      if (selected.value) selected.value.status = statusDraft
      await load(page.value)
    } catch (reason) {
      actionError.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось сохранить оплату.'
    } finally {
      saving.value = false
    }
  }

  async function submitComment(): Promise<void> {
    if (
      !selected.value ||
      !canManage.value ||
      saving.value ||
      !commentBody.value.trim()
    )
      return
    saving.value = true
    actionError.value = ''
    try {
      await addOrderComment(selected.value.id, commentBody.value)
      commentBody.value = ''
      comments.value = await getOrderComments(selected.value.id)
    } catch (reason) {
      actionError.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось добавить комментарий.'
    } finally {
      saving.value = false
    }
  }

  async function changeAssignee(value: string): Promise<void> {
    if (saving.value || !selected.value || !assignment.canAssign.value) return
    const assigneeId = value ? Number(value) : null
    if (
      assigneeId !== null &&
      (!Number.isSafeInteger(assigneeId) || assigneeId <= 0)
    )
      return
    if ((selected.value.assignee?.id ?? null) === assigneeId) return
    const id = selected.value.id
    const request = detailRequest
    saving.value = true
    actionError.value = ''
    try {
      await assignOrder(id, assigneeId)
      if (request !== detailRequest || selected.value?.id !== id) return
      const current = await getOrder(id)
      if (request !== detailRequest || selected.value?.id !== id) return
      selected.value.assignee = current.assignee
      selected.value.assigned_at = current.assigned_at
      await load(page.value)
    } catch (reason) {
      if (request === detailRequest)
        actionError.value =
          reason instanceof Error
            ? reason.message
            : 'Не удалось обновить ответственного.'
    } finally {
      saving.value = false
    }
  }

  async function initialize(): Promise<void> {
    const candidates = assignment.load()
    loading.value = true
    statusError.value = ''
    statusReady.value = false
    statusLoading.value = true
    try {
      statuses.value = await getOrderStatuses()
      statusReady.value = true
    } catch (reason) {
      statusError.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось загрузить статусы.'
    } finally {
      statusLoading.value = false
    }
    await Promise.all([load(), candidates])
  }

  watch([status, paymentStatus, assigneeFilter], () => void load())
  return {
    orders,
    selected,
    statuses,
    histories,
    comments,
    search,
    status,
    paymentStatus,
    assigneeFilter,
    assigneeReady: assignment.ready,
    assigneeError: assignment.error,
    assigneeOptions: assignment.options,
    assigneeFilterOptions: assignment.filterOptions,
    loadAssignees: assignment.load,
    canAssign: assignment.canAssign,
    meta,
    loading,
    detailLoading,
    error,
    statusLoading,
    statusReady,
    statusError,
    actionError,
    saving,
    commentBody,
    hasUnsavedChanges,
    paymentForm,
    canManage,
    canManageStatus,
    canManagePayment,
    statusOptions,
    paymentEditOptions,
    date,
    amount,
    statusName,
    paymentName,
    load,
    selectOrder,
    clearSelection,
    changeStatus,
    savePayment,
    submitComment,
    changeAssignee,
    initialize,
  }
}
