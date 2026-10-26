<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import AdminWorkspace from '../../../components/shared/AdminWorkspace.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiNotificationCount from '../../../components/ui/UiNotificationCount.vue'
import UiPagination from '../../../components/ui/UiPagination.vue'
import { useNotificationContext } from '../composables/notificationContext'
import { formatPhone } from '../../../utils/phone'
import { formatMoney } from '../../../utils/formatMoney'

const state = useNotificationContext()
const route = useRoute()
const panel = ref<HTMLElement | null>(null)
const sections = computed(() => [
  ...(state.canViewOrders
    ? [
        {
          value: 'orders' as const,
          label: 'Заказы',
          count: state.ordersTotal,
          description: 'Новых заказов',
        },
      ]
    : []),
  ...(state.canViewContacts
    ? [
        {
          value: 'contacts' as const,
          label: 'Обращения',
          count: state.contactsTotal,
          description: 'Новых обращений',
        },
      ]
    : []),
])
const section = computed(
  () =>
    sections.value.find((item) => item.value === route.query.section) ??
    sections.value[0],
)
const isOrders = computed(() => section.value?.value === 'orders')
const error = computed(() =>
  isOrders.value ? state.ordersError : state.contactsError,
)
const meta = computed(() =>
  isOrders.value ? state.ordersMeta : state.contactsMeta,
)
const hasItems = computed(() =>
  isOrders.value ? !!state.orders.length : !!state.contacts.length,
)
const date = (value: string) =>
  new Date(value).toLocaleString('ru-RU', { timeZone: 'Europe/Moscow' })
const contactLabels = {
  callback: 'Обратный звонок',
  email: 'Email',
  partner: 'Партнёрство',
}

function retry() {
  panel.value?.focus({ preventScroll: true })
  void state.refresh()
}
onMounted(() => void state.refresh())
</script>

<template>
  <AdminWorkspace mode="overview">
    <template #intro>
      <PageHeader
        title="Уведомления"
        description="Новые заказы и обращения. Остаются здесь до смены статуса."
        class="mb-7"
      />
    </template>
    <div class="notifications-workspace admin-container">
      <nav
        v-if="sections.length"
        class="notifications-sections"
        aria-label="Разделы уведомлений"
      >
        <RouterLink
          v-for="item in sections"
          :key="item.value"
          :to="{ path: '/notifications', query: { section: item.value } }"
          class="admin-focus notifications-section"
          :aria-label="item.label"
          :aria-current="section?.value === item.value ? 'page' : undefined"
          :aria-describedby="`notifications-${item.value}-count`"
        >
          <span>{{ item.label }}</span>
          <UiNotificationCount :count="item.count" />
          <span :id="`notifications-${item.value}-count`" class="sr-only"
            >{{ item.description }}: {{ item.count }}</span
          >
        </RouterLink>
      </nav>
      <section
        ref="panel"
        class="notifications-list min-w-0 admin-focus"
        aria-label="Уведомления"
        tabindex="-1"
      >
        <template v-if="section">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-bold text-gray-500">
              {{ isOrders ? 'Новые заказы' : 'Новые обращения' }}
            </h2>
            <RouterLink
              :to="isOrders ? '/orders' : '/contacts'"
              class="admin-focus rounded-lg text-sm font-semibold text-primary-500"
              >{{ isOrders ? 'Все заказы' : 'Все обращения' }}</RouterLink
            >
          </div>
          <UiLoadingState
            v-if="state.loading && !hasItems"
            label="Загрузка уведомлений…"
          />
          <div v-if="error" class="space-y-3">
            <UiAlert>{{ error }}</UiAlert>
            <UiButton
              variant="secondary"
              :disabled="state.loading"
              @click="retry"
              >Повторить</UiButton
            >
          </div>
          <UiEmptyState
            v-if="!state.loading && !error && !hasItems"
            label="Новых уведомлений нет"
          />
          <ul
            v-if="hasItems"
            class="divide-y divide-gray-100"
            :aria-busy="state.loading"
          >
            <template v-if="isOrders">
              <li v-for="item in state.orders" :key="item.id">
                <RouterLink
                  :to="{ path: '/orders', query: { order: item.id } }"
                  class="admin-focus notifications-item"
                >
                  <span class="font-semibold text-primary-500"
                    >Заказ {{ item.order_number }}</span
                  >
                  <span>{{ item.customer.name || 'Без имени' }}</span>
                  <span v-if="item.customer.phone">{{
                    formatPhone(item.customer.phone)
                  }}</span>
                  <span>{{ formatMoney(item.total_amount) }}</span>
                  <time class="text-xs" :datetime="item.created_at">{{
                    date(item.created_at)
                  }}</time>
                </RouterLink>
              </li>
            </template>
            <template v-else>
              <li v-for="item in state.contacts" :key="item.id">
                <RouterLink
                  :to="{ path: '/contacts', query: { contact: item.id } }"
                  class="admin-focus notifications-item"
                >
                  <span class="font-semibold text-primary-500"
                    >{{ contactLabels[item.type] }} #{{ item.id }}</span
                  >
                  <span>{{ item.contact.name || 'Без имени' }}</span>
                  <span v-if="item.contact.phone">{{
                    formatPhone(item.contact.phone)
                  }}</span>
                  <time class="text-xs" :datetime="item.created_at">{{
                    date(item.created_at)
                  }}</time>
                </RouterLink>
              </li>
            </template>
          </ul>
          <UiPagination
            v-if="!error"
            :meta="meta"
            :loading="state.loading"
            @change="state.setPage(section.value, $event)"
          />
        </template>
        <UiEmptyState
          v-else
          label="Нет доступа к уведомлениям о заказах и обращениях"
        />
      </section>
    </div>
  </AdminWorkspace>
</template>

<style scoped>
.notifications-workspace {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--admin-spacing-5);
  border-top: var(--admin-border-width) solid var(--admin-color-gray-200);
  padding-top: var(--admin-spacing-5);
}
.notifications-sections {
  display: flex;
  flex-wrap: wrap;
  align-content: start;
  gap: var(--admin-spacing-2);
  min-width: 0;
}
.notifications-section {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--admin-spacing-3);
  padding: var(--admin-spacing-3) var(--admin-spacing-4);
  border-radius: var(--admin-radius-lg);
  font-size: 0.875rem;
  font-weight: 600;
}
.notifications-section:hover,
.notifications-section[aria-current='page'] {
  background: var(--admin-color-primary-50);
  color: var(--admin-color-primary-500);
}
.notifications-item {
  display: flex;
  flex-direction: column;
  gap: var(--admin-spacing-1);
  padding: var(--admin-spacing-4);
  border-radius: var(--admin-radius-lg);
  overflow-wrap: anywhere;
  font-size: 0.875rem;
}
.notifications-item:hover {
  background: var(--admin-color-gray-50);
}
@media (min-width: 1024px) {
  .notifications-workspace {
    grid-template-columns: 240px minmax(0, 1fr);
    gap: var(--admin-spacing-6);
  }
  .notifications-sections {
    flex-direction: column;
    border-right: var(--admin-border-width) solid var(--admin-color-gray-200);
    padding-right: var(--admin-spacing-5);
  }
}
</style>
