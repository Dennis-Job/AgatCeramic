import { computed, onScopeDispose, ref, watch } from 'vue'
import { usePaginatedCollection } from '../../../composables/usePaginatedCollection'
import { formatMoney } from '../../../utils/formatMoney'
import { useAuthStore } from '../../../stores/auth'
import { notifyAdminNotificationsChanged } from '../../notifications/services/notificationChanges'
import { useOrderAssignees } from './useOrderAssignees'
import { useOrderStatusCounts } from './useOrderStatusCounts'
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

function paymentValues(order?: Order): PaymentForm {
  return {
    payment_status: order?.payment_status ?? 'not_paid',
    payment_amount: order?.payment_amount ?? '',
    payment_method: order?.payment_method ?? '',
    payment_reference: order?.payment_reference ?? '',
  }
}

function samePayment(first: PaymentForm, second: PaymentForm): boolean {
  return (
    first.payment_status === second.payment_status &&
    first.payment_amount === second.payment_amount &&
    first.payment_method === second.payment_method &&
    first.payment_reference === second.payment_reference
  )
}

export function useOrdersWorkspace() {
  const queueCounts = useOrderStatusCounts()
  const auth = useAuthStore()
  const list = usePaginatedCollection<Order>('Не удалось загрузить заказы.')
  const details = ref<{
    order: Order
    histories: OrderHistory[]
    comments: OrderComment[]
  } | null>(null)
  const selected = computed(() => details.value?.order ?? null)
  const histories = computed(() => details.value?.histories ?? [])
  const comments = computed(() => details.value?.comments ?? [])
  const statuses = ref<OrderStatus[]>([])
  const search = ref('')
  const status = ref('')
  const paymentStatus = ref('')
  const assigneeFilter = ref('')
  const initialMeta = { current_page: 1, last_page: 1, per_page: 25, total: 0 }
  const meta = computed(() => list.pagination.value ?? initialMeta)
  list.loading.value = true
  let listRequested = false
  const detailLoading = ref(false)
  const detailError = ref('')
  let detailRequest = 0
  let selectedId: number | null = null
  let savedDraft: { status?: string; payment?: PaymentForm } = {}
  onScopeDispose(() => {
    detailRequest++
  })
  const statusLoading = ref(false)
  const statusReady = ref(false)
  const statusError = ref('')
  const actionError = ref('')
  const saving = ref(false)
  const commentBody = ref('')
  const statusDraft = ref('')
  const paymentForm = ref(paymentValues())
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
        (statusDraft.value !== selected.value.status ||
          !samePayment(paymentForm.value, paymentValues(selected.value))),
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
    listRequested = true
    const filters = {
      search: search.value,
      status: status.value === 'working' ? '' : status.value,
      ...(status.value === 'working'
        ? { statuses: workingStatuses.value }
        : {}),
      payment_status: paymentStatus.value,
      ...(assigneeFilter.value
        ? {
            assignee_id:
              assigneeFilter.value === 'unassigned' ? '' : assigneeFilter.value,
            unassigned: assigneeFilter.value === 'unassigned',
          }
        : {}),
    }
    await list.load(nextPage, (page) => getOrders(filters, { page }))
  }

  function clearSelection(): void {
    detailRequest++
    selectedId = null
    savedDraft = {}
    details.value = null
    commentBody.value = ''
    statusDraft.value = ''
    paymentForm.value = paymentValues()
    actionError.value = ''
    detailError.value = ''
    detailLoading.value = false
    saving.value = false
  }

  async function loadDetails(id: number, request: number): Promise<void> {
    detailLoading.value = true
    detailError.value = ''
    actionError.value = ''
    try {
      const order = await getOrder(id)
      if (request !== detailRequest) return
      const [nextHistories, nextComments] = await Promise.all([
        getOrderHistory(id),
        getOrderComments(id),
      ])
      if (request !== detailRequest) return

      const current = selected.value
      if (
        !current ||
        statusDraft.value === (savedDraft.status ?? current.status)
      )
        statusDraft.value = order.status
      if (
        !current ||
        samePayment(
          paymentForm.value,
          savedDraft.payment ?? paymentValues(current),
        )
      )
        paymentForm.value = paymentValues(order)
      savedDraft = {}
      details.value = {
        order,
        histories: nextHistories,
        comments: nextComments,
      }
    } catch (reason) {
      if (request !== detailRequest) return
      detailError.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось загрузить детали заказа.'
    } finally {
      if (request === detailRequest) detailLoading.value = false
    }
  }

  async function selectOrder(order: Pick<Order, 'id'>): Promise<void> {
    if (selectedId !== order.id) clearSelection()
    selectedId = order.id
    saving.value = false
    await loadDetails(order.id, ++detailRequest)
  }

  async function changeStatus(): Promise<void> {
    if (
      !selected.value ||
      !canManageStatus.value ||
      saving.value ||
      detailLoading.value
    )
      return
    const { id } = selected.value
    const request = detailRequest
    const status = statusDraft.value
    saving.value = true
    actionError.value = ''
    try {
      await updateOrderStatus(id, status)
      notifyAdminNotificationsChanged()
      const current = request === detailRequest
      if (current) savedDraft.status = status
      await Promise.all([current && loadDetails(id, request), list.reload()])
    } catch (reason) {
      if (request === detailRequest)
        actionError.value =
          reason instanceof Error
            ? reason.message
            : 'Не удалось изменить статус.'
    } finally {
      if (request === detailRequest) saving.value = false
    }
  }

  async function savePayment(): Promise<void> {
    if (
      !selected.value ||
      !canManagePayment.value ||
      saving.value ||
      detailLoading.value
    )
      return
    const { id } = selected.value
    const request = detailRequest
    const payment = { ...paymentForm.value }
    saving.value = true
    actionError.value = ''
    try {
      const payload = ['not_paid', 'pending'].includes(payment.payment_status)
        ? { payment_status: payment.payment_status }
        : { ...payment, paid_at: new Date().toISOString() }
      await updateOrderPayment(id, payload)
      const current = request === detailRequest
      if (current) savedDraft.payment = payment
      await Promise.all([current && loadDetails(id, request), list.reload()])
    } catch (reason) {
      if (request === detailRequest)
        actionError.value =
          reason instanceof Error
            ? reason.message
            : 'Не удалось сохранить оплату.'
    } finally {
      if (request === detailRequest) saving.value = false
    }
  }

  async function submitComment(): Promise<void> {
    if (
      !selected.value ||
      !canManage.value ||
      saving.value ||
      detailLoading.value ||
      !commentBody.value.trim()
    )
      return
    const { id } = selected.value
    const request = detailRequest
    const body = commentBody.value
    saving.value = true
    actionError.value = ''
    try {
      await addOrderComment(id, body)
      if (request !== detailRequest) return
      if (commentBody.value === body) commentBody.value = ''
      const nextComments = await getOrderComments(id)
      if (request === detailRequest && details.value)
        details.value.comments = nextComments
    } catch (reason) {
      if (request === detailRequest)
        actionError.value =
          reason instanceof Error
            ? reason.message
            : 'Не удалось добавить комментарий.'
    } finally {
      if (request === detailRequest) saving.value = false
    }
  }

  async function changeAssignee(value: string): Promise<void> {
    if (
      saving.value ||
      detailLoading.value ||
      !selected.value ||
      !assignment.canAssign.value
    )
      return
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
      await Promise.all([
        request === detailRequest && refreshAssignee(id, request),
        list.reload(),
      ])
    } catch (reason) {
      if (request === detailRequest)
        actionError.value =
          reason instanceof Error
            ? reason.message
            : 'Не удалось обновить ответственного.'
    } finally {
      if (request === detailRequest) saving.value = false
    }
  }

  async function refreshAssignee(id: number, request: number): Promise<void> {
    try {
      const order = await getOrder(id)
      if (request !== detailRequest || !details.value) return
      details.value.order = {
        ...details.value.order,
        assignee: order.assignee,
        assigned_at: order.assigned_at,
      }
    } catch (reason) {
      if (request === detailRequest)
        detailError.value =
          reason instanceof Error
            ? reason.message
            : 'Не удалось загрузить детали заказа.'
    }
  }

  async function initialize(): Promise<void> {
    const counts = queueCounts.refreshStatusCounts()
    const candidates = assignment.load()
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
    await Promise.all([!listRequested && load(), candidates, counts])
  }

  watch([status, paymentStatus, assigneeFilter], () => void load())
  return {
    ...queueCounts,
    orders: list.items,
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
    loading: list.loading,
    detailLoading,
    detailError,
    error: list.error,
    statusLoading,
    statusReady,
    statusError,
    actionError,
    saving,
    commentBody,
    hasUnsavedChanges,
    statusDraft,
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
