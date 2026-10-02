<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RefreshCw } from '@lucide/vue'
import AdminListDetail from '../../../components/shared/AdminListDetail.vue'
import AdminWorkspace from '../../../components/shared/AdminWorkspace.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiPagination from '../../../components/ui/UiPagination.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import {
  orderPaymentOptions,
  useOrdersWorkspace,
} from '../composables/useOrdersWorkspace'
import OrderDetails from './OrderDetails.vue'
import OrdersList from './OrdersList.vue'

const listDetail = ref<InstanceType<typeof AdminListDetail> | null>(null)
const workspace = useOrdersWorkspace()
onMounted(workspace.initialize)
</script>

<template>
  <AdminWorkspace mode="list">
    <template #intro>
      <PageHeader
        class="mb-7"
        eyebrow="Продажи"
        title="Заказы"
        description="Просматривайте состав и данные заказа, статусы, оплату и внутренние комментарии."
      />
    </template>

    <UiAlert v-if="workspace.error.value" class="admin-container mb-4">{{
      workspace.error.value
    }}</UiAlert>
    <UiAlert
      v-if="!workspace.error.value && workspace.statusError.value"
      class="admin-container mb-4"
      >{{ workspace.statusError.value }} Смена статуса заказа временно
      недоступна.</UiAlert
    >
    <AdminListDetail ref="listDetail">
      <template #list>
        <div class="min-w-0">
          <form
            role="search"
            class="admin-container grid gap-3 border-b border-gray-100 py-4 sm:grid-cols-2"
            @submit.prevent="workspace.load()"
          >
            <UiInput
              v-model="workspace.search.value"
              searchable
              placeholder="Номер, клиент, телефон или email"
              aria-label="Поиск заказов"
            />
            <UiSelect
              v-model="workspace.status.value"
              :options="workspace.statusOptions.value"
              accessible-name="Статус заказа"
              :disabled="!workspace.statusReady.value"
            />
            <UiSelect
              v-model="workspace.paymentStatus.value"
              :options="orderPaymentOptions"
              accessible-name="Статус оплаты"
            />
            <UiButton type="submit" variant="secondary"
              ><RefreshCw :size="17" aria-hidden="true" />Найти</UiButton
            >
          </form>
          <OrdersList
            v-if="!workspace.error.value"
            :orders="workspace.orders.value"
            :selected-id="workspace.selected.value?.id"
            :loading="workspace.loading.value"
            :date="workspace.date"
            :amount="workspace.amount"
            :status-name="workspace.statusName"
            :payment-name="workspace.paymentName"
            @select="
              (item) =>
                listDetail?.showDetail(() => workspace.selectOrder(item))
            "
          />
          <UiPagination
            v-if="!workspace.error.value"
            :meta="workspace.meta.value"
            :loading="workspace.loading.value"
            class="admin-container border-t border-gray-100 pb-4"
            @change="workspace.load"
          />
        </div>
      </template>
      <template #detail>
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
          :date="workspace.date"
          :amount="workspace.amount"
          :status-name="workspace.statusName"
          :payment-options="workspace.paymentEditOptions"
          @change-status="workspace.changeStatus"
          @save-payment="workspace.savePayment"
          @submit-comment="workspace.submitComment"
        />
      </template>
    </AdminListDetail>
  </AdminWorkspace>
</template>
