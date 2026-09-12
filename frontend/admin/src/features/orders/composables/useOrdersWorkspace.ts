import { computed, ref, watch } from 'vue'
import { useAuthStore } from '../../../stores/auth'
import { addOrderComment, getOrder, getOrderComments, getOrderHistory, getOrders, getOrderStatuses, updateOrderPayment, updateOrderStatus } from '../services/orders'
import type { Order, OrderComment, OrderHistory, OrderStatus, PaymentForm } from '../types/order.types'

export const orderPaymentOptions = [
  { label: 'Все оплаты', value: '' }, { label: 'Не оплачено', value: 'not_paid' }, { label: 'Ожидает оплаты', value: 'pending' },
  { label: 'Оплачено', value: 'paid' }, { label: 'Частично оплачено', value: 'partially_paid' }, { label: 'Возврат', value: 'refunded' },
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
  const page = ref(1)
  const meta = ref({ current_page: 1, last_page: 1, per_page: 25, total: 0 })
  const loading = ref(true)
  const detailLoading = ref(false)
  const error = ref('')
  const statusLoading = ref(false)
  const statusReady = ref(false)
  const statusError = ref('')
  const actionError = ref('')
  const saving = ref(false)
  const commentBody = ref('')
  const paymentForm = ref<PaymentForm>({ payment_status: 'not_paid', payment_amount: '', payment_method: '', payment_reference: '' })
  const canManage = computed(() => auth.hasPermission('orders.manage'))
  const canManageStatus = computed(() => canManage.value && statusReady.value && !statusLoading.value && !statusError.value)
  const canManagePayment = computed(() => auth.hasPermission('payments.manage'))
  const statusOptions = computed(() => [{ label: 'Все статусы', value: '' }, ...statuses.value.map(item => ({ label: item.name, value: item.code }))])
  const paymentEditOptions = orderPaymentOptions.slice(1)

  const date = (value: string | null): string => value ? new Date(value).toLocaleString('ru-RU') : '—'
  const amount = (value: string | null): string => value === null ? '—' : new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB' }).format(Number(value))
  const statusName = (code: string): string => statuses.value.find(item => item.code === code)?.name ?? code
  const paymentName = (code: string): string => orderPaymentOptions.find(item => item.value === code)?.label ?? code

  async function load(nextPage = 1): Promise<void> {
    loading.value = true
    error.value = ''
    try {
      const response = await getOrders({ search: search.value, status: status.value, payment_status: paymentStatus.value }, { page: nextPage })
      orders.value = response.data
      meta.value = response.meta
      page.value = response.meta.current_page
    } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось загрузить заказы.' }
    finally { loading.value = false }
  }

  function syncPaymentForm(): void {
    if (!selected.value) return
    paymentForm.value = { payment_status: selected.value.payment_status, payment_amount: selected.value.payment_amount ?? '', payment_method: selected.value.payment_method ?? '', payment_reference: selected.value.payment_reference ?? '' }
  }

  async function selectOrder(order: Order): Promise<void> {
    detailLoading.value = true
    actionError.value = ''
    try {
      selected.value = await getOrder(order.id)
      ;[histories.value, comments.value] = await Promise.all([getOrderHistory(order.id), getOrderComments(order.id)])
      syncPaymentForm()
    } catch (reason) { actionError.value = reason instanceof Error ? reason.message : 'Не удалось загрузить детали заказа.' }
    finally { detailLoading.value = false }
  }

  async function changeStatus(): Promise<void> {
    if (!selected.value || !canManageStatus.value) return
    saving.value = true
    actionError.value = ''
    try { await updateOrderStatus(selected.value.id, selected.value.status); await selectOrder(selected.value); await load(page.value) }
    catch (reason) { actionError.value = reason instanceof Error ? reason.message : 'Не удалось изменить статус.' }
    finally { saving.value = false }
  }

  async function savePayment(): Promise<void> {
    if (!selected.value) return
    saving.value = true
    actionError.value = ''
    try {
      const payload = ['not_paid', 'pending'].includes(paymentForm.value.payment_status)
        ? { payment_status: paymentForm.value.payment_status }
        : { ...paymentForm.value, paid_at: new Date().toISOString() }
      await updateOrderPayment(selected.value.id, payload)
      await selectOrder(selected.value)
      await load(page.value)
    } catch (reason) { actionError.value = reason instanceof Error ? reason.message : 'Не удалось сохранить оплату.' }
    finally { saving.value = false }
  }

  async function submitComment(): Promise<void> {
    if (!selected.value || !commentBody.value.trim()) return
    saving.value = true
    actionError.value = ''
    try { await addOrderComment(selected.value.id, commentBody.value); commentBody.value = ''; comments.value = await getOrderComments(selected.value.id) }
    catch (reason) { actionError.value = reason instanceof Error ? reason.message : 'Не удалось добавить комментарий.' }
    finally { saving.value = false }
  }

  async function initialize(): Promise<void> {
    loading.value = true
    statusError.value = ''
    statusReady.value = false
    statusLoading.value = true
    try { statuses.value = await getOrderStatuses(); statusReady.value = true }
    catch (reason) { statusError.value = reason instanceof Error ? reason.message : 'Не удалось загрузить статусы.' }
    finally { statusLoading.value = false }
    await load()
  }

  watch([status, paymentStatus], () => void load())
  return { orders, selected, statuses, histories, comments, search, status, paymentStatus, meta, loading, detailLoading, error, statusLoading, statusReady, statusError, actionError, saving, commentBody, paymentForm, canManage, canManageStatus, canManagePayment, statusOptions, paymentEditOptions, date, amount, statusName, paymentName, load, selectOrder, changeStatus, savePayment, submitComment, initialize }
}
