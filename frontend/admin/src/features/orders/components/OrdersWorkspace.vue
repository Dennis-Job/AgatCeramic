<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
  onBeforeRouteLeave,
  onBeforeRouteUpdate,
  useRoute,
  useRouter,
} from 'vue-router'
import AdminSidePanel from '../../../components/shared/AdminSidePanel.vue'
import AdminWorkspace from '../../../components/shared/AdminWorkspace.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiPagination from '../../../components/ui/UiPagination.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiTabs from '../../../components/ui/UiTabs.vue'
import {
  orderPaymentOptions,
  useOrdersWorkspace,
} from '../composables/useOrdersWorkspace'
import OrderDetails from './OrderDetails.vue'
import OrdersList from './OrdersList.vue'
import OrderStatistics from './OrderStatistics.vue'

const workspace = useOrdersWorkspace()
const route = useRoute()
const router = useRouter()
const compactStatusQuery = window.matchMedia?.('(max-width: 1023px)')
const compactStatuses = ref(compactStatusQuery?.matches ?? false)
function updateStatusLayout(event: MediaQueryListEvent) {
  compactStatuses.value = event.matches
}
const tabs = computed(() =>
  workspace.statusOptions.value.map(({ value, label }) => ({
    value,
    label,
    disabled: !workspace.statusReady.value,
  })),
)
const list = ref<HTMLElement | null>(null)
const drawerOpen = ref(false)
const orderId = ref<number | null>(null)
const discardOpen = ref(false)
let discardResolve: ((accepted: boolean) => void) | null = null
let opener: HTMLElement | null = null
function openDetails(id: number) {
  if (workspace.saving.value) return
  if (!drawerOpen.value)
    opener =
      document.activeElement instanceof HTMLElement
        ? document.activeElement
        : null
  workspace.clearSelection()
  orderId.value = id
  drawerOpen.value = true
  void workspace.selectOrder({ id })
}
function selectOrder(order: { id: number }) {
  if (workspace.saving.value) return
  if (route.query.order === String(order.id)) {
    if (!drawerOpen.value) openDetails(order.id)
  } else void router.replace({ query: { ...route.query, order: order.id } })
}
function allowDiscard(): Promise<boolean> {
  if (!workspace.hasUnsavedChanges.value) return Promise.resolve(true)
  discardOpen.value = true
  return new Promise((resolve) => {
    discardResolve = resolve
  })
}
function resolveDiscard(accepted: boolean) {
  discardOpen.value = false
  discardResolve?.(accepted)
  discardResolve = null
}
async function closeDetails() {
  if (workspace.saving.value) return
  const query = { ...route.query }
  delete query.order
  await router.replace({ query })
}
onBeforeRouteLeave(
  async () => !workspace.saving.value && (await allowDiscard()),
)
onBeforeRouteUpdate(async (to, from) => {
  if (to.query.order === from.query.order) return true
  return !workspace.saving.value && (await allowDiscard())
})
watch(
  () => route.query.order,
  async (value) => {
    const id =
      typeof value === 'string' && /^[1-9]\d*$/.test(value)
        ? Number(value)
        : NaN
    if (Number.isSafeInteger(id)) openDetails(id)
    else if (drawerOpen.value) {
      drawerOpen.value = false
      orderId.value = null
      workspace.clearSelection()
      await nextTick()
      if (opener?.isConnected && opener !== document.body)
        opener.focus({ preventScroll: true })
      else list.value?.focus({ preventScroll: true })
    }
  },
  { immediate: true },
)
watch([workspace.detailLoading, workspace.saving], async () => {
  await nextTick()
  if (drawerOpen.value && document.activeElement === document.body)
    document
      .getElementById('order-details-title')
      ?.focus({ preventScroll: true })
})
onMounted(() => {
  compactStatusQuery?.addEventListener('change', updateStatusLayout)
  void workspace.initialize()
})
onBeforeUnmount(() => {
  compactStatusQuery?.removeEventListener('change', updateStatusLayout)
  discardResolve?.(false)
})
</script>

<template>
  <AdminWorkspace mode="list">
    <template #intro>
      <PageHeader
        class="mb-7"
        eyebrow="Продажи"
        title="Заказы"
        description="Список и обработка заказов, оплата и внутренние комментарии."
      />
    </template>
    <OrderStatistics />
    <UiNotification v-if="workspace.error.value"
      >{{ workspace.error.value
      }}<UiButton
        variant="secondary"
        size="sm"
        class="ml-3"
        @click="workspace.load()"
        >Повторить загрузку заказов</UiButton
      ></UiNotification
    >
    <UiNotification v-if="!workspace.error.value && workspace.statusError.value"
      >{{ workspace.statusError.value }} Смена статуса заказа временно
      недоступна.<UiButton
        variant="secondary"
        size="sm"
        class="ml-3"
        @click="workspace.initialize"
        >Повторить загрузку статусов</UiButton
      ></UiNotification
    >
    <UiNotification v-if="workspace.assigneeError.value">
      {{ workspace.assigneeError.value }} Назначение ответственного временно
      недоступно.
      <UiButton
        variant="secondary"
        size="sm"
        class="ml-3"
        :disabled="workspace.saving.value"
        @click="workspace.loadAssignees"
        >Повторить загрузку сотрудников</UiButton
      >
    </UiNotification>
    <div
      ref="list"
      class="admin-focus min-w-0"
      role="region"
      aria-label="Очередь заказов"
      tabindex="-1"
    >
      <div class="admin-container">
        <UiSelect
          v-if="compactStatuses"
          v-model="workspace.status.value"
          :options="workspace.statusOptions.value"
          accessible-name="Статус заказа"
          :disabled="!workspace.statusReady.value"
        />
        <UiTabs
          v-else
          id="order-status-tabs"
          v-model="workspace.status.value"
          label="Статус заказа"
          :items="tabs"
        />
      </div>
      <div
        :id="`order-status-tabs-${workspace.status.value}-panel`"
        :role="compactStatuses ? 'region' : 'tabpanel'"
        :aria-label="
          compactStatuses ? 'Заказы по выбранному статусу' : undefined
        "
        :aria-labelledby="
          compactStatuses
            ? undefined
            : `order-status-tabs-${workspace.status.value}`
        "
      >
        <form
          role="search"
          class="admin-container orders-filters"
          @submit.prevent="workspace.load()"
        >
          <UiInput
            v-model="workspace.search.value"
            searchable
            placeholder="Номер, клиент, телефон или email"
            aria-label="Поиск заказов"
          />
          <UiSelect
            v-model="workspace.paymentStatus.value"
            :options="orderPaymentOptions"
            accessible-name="Статус оплаты"
          />
          <UiSelect
            v-model="workspace.assigneeFilter.value"
            :options="workspace.assigneeFilterOptions.value"
            accessible-name="Ответственный в списке заказов"
            :disabled="
              workspace.canManage.value && !workspace.assigneeReady.value
            "
          />
          <UiButton type="submit" variant="secondary">Найти</UiButton>
        </form>
        <OrdersList
          v-if="!workspace.error.value"
          :orders="workspace.orders.value"
          :selected-id="drawerOpen ? (orderId ?? undefined) : undefined"
          :loading="workspace.loading.value"
          :date="workspace.date"
          :amount="workspace.amount"
          :status-name="workspace.statusName"
          :payment-name="workspace.paymentName"
          @select="selectOrder"
        />
        <UiPagination
          v-if="!workspace.error.value"
          :meta="workspace.meta.value"
          :loading="workspace.loading.value"
          class="admin-container pb-4"
          @change="workspace.load"
        />
      </div>
    </div>
  </AdminWorkspace>
  <AdminSidePanel
    :open="drawerOpen"
    :title="workspace.selected.value?.order_number ?? `Заказ #${orderId}`"
    title-id="order-details-title"
    close-label="Закрыть заказ"
    :busy="workspace.saving.value"
    :suspended="discardOpen"
    @close="closeDetails"
  >
    <OrderDetails
      v-model:payment-form="workspace.paymentForm.value"
      v-model:comment-body="workspace.commentBody.value"
      :selected="workspace.selected.value"
      :statuses="workspace.statuses.value"
      :histories="workspace.histories.value"
      :comments="workspace.comments.value"
      :detail-loading="workspace.detailLoading.value"
      :saving="workspace.saving.value"
      :action-error="workspace.actionError.value"
      :can-manage="workspace.canManage.value"
      :can-manage-status="workspace.canManageStatus.value"
      :can-manage-payment="workspace.canManagePayment.value"
      :can-assign="workspace.canAssign.value"
      :assignee-options="workspace.assigneeOptions.value"
      :date="workspace.date"
      :amount="workspace.amount"
      :status-name="workspace.statusName"
      :payment-name="workspace.paymentName"
      :payment-options="workspace.paymentEditOptions"
      @change-status="workspace.changeStatus"
      @save-payment="workspace.savePayment"
      @submit-comment="workspace.submitComment"
      @change-assignee="workspace.changeAssignee"
      @retry="orderId && workspace.selectOrder({ id: orderId })"
    />
  </AdminSidePanel>
  <ConfirmDialog
    :open="discardOpen"
    title="Закрыть без сохранения изменений?"
    description="Статус, оплата или внутренний комментарий изменены. Несохранённые изменения будут потеряны."
    confirm-label="Закрыть без сохранения"
    @close="resolveDiscard(false)"
    @confirm="resolveDiscard(true)"
  />
</template>

<style scoped>
.orders-filters {
  display: grid;
  grid-template-columns:
    minmax(200px, 1fr) minmax(160px, 0.4fr) minmax(160px, 0.4fr)
    auto;
  gap: var(--admin-spacing-4);
  align-items: center;
  padding-block: var(--admin-spacing-6);
}
@media (max-width: 767px) {
  .orders-filters {
    grid-template-columns: minmax(0, 1fr);
    gap: var(--admin-spacing-3);
  }
}
@media (min-width: 768px) and (max-width: 1023px) {
  .orders-filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
