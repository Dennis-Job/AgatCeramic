<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
  onBeforeRouteLeave,
  onBeforeRouteUpdate,
  useRoute,
  useRouter,
} from 'vue-router'
import AdminWorkspace from '../../../components/shared/AdminWorkspace.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import AdminSidePanel from '../../../components/shared/AdminSidePanel.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiPagination from '../../../components/ui/UiPagination.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiTabs from '../../../components/ui/UiTabs.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import { contactStatusTone } from '../utils/contactStatusTone'
import {
  contactTypeOptions,
  useContactsWorkspace,
} from '../composables/useContactsWorkspace'
import ContactDetails from './ContactDetails.vue'
import ContactsList from './ContactsList.vue'
import ContactStatistics from './ContactStatistics.vue'

const workspace = useContactsWorkspace()
const route = useRoute()
const router = useRouter()
const initialized = ref(false)
const compactStatusQuery = window.matchMedia?.('(max-width: 1023px)')
const compactStatuses = ref(compactStatusQuery?.matches ?? false)
function updateStatusLayout(event: MediaQueryListEvent) {
  compactStatuses.value = event.matches
}
const list = ref<HTMLElement | null>(null)
const drawerOpen = ref(false)
const contactId = ref<number | null>(null)
const discardOpen = ref(false)
let discardResolve: ((accepted: boolean) => void) | null = null
let opener: HTMLElement | null = null
const tabs = computed(() => [
  { value: '', label: 'Все', disabled: !workspace.statusReady.value },
  ...workspace.statuses.value.map((item) => ({
    value: item.code,
    label: item.name,
    disabled: !workspace.statusReady.value,
  })),
])
const statusSelectOptions = computed(() =>
  tabs.value.map(({ value, label }) => {
    const count =
      workspace.statusCounts.value?.[
        value as 'new' | 'processing' | 'awaiting_client'
      ]
    return { value, label: count === undefined ? label : `${label} (${count})` }
  }),
)

function openDetails(id: number) {
  if (workspace.saving.value) return
  if (!drawerOpen.value)
    opener =
      document.activeElement instanceof HTMLElement
        ? document.activeElement
        : null
  contactId.value = id
  drawerOpen.value = true
  void workspace.select({ id })
}
function selectContact(contact: { id: number }) {
  if (workspace.saving.value) return
  if (route.query.contact === String(contact.id)) openDetails(contact.id)
  else void router.replace({ query: { ...route.query, contact: contact.id } })
}
function allowDiscard(): Promise<boolean> {
  if (!workspace.commentBody.value.trim()) return Promise.resolve(true)
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
  if (workspace.saving.value || !(await allowDiscard())) return
  drawerOpen.value = false
  workspace.clearSelection()
  const query = { ...route.query }
  delete query.contact
  await router.replace({ query })
  await nextTick()
  if (opener?.isConnected && opener !== document.body)
    opener.focus({ preventScroll: true })
  else list.value?.focus({ preventScroll: true })
}
onBeforeRouteLeave(
  async () => !workspace.saving.value && (await allowDiscard()),
)
onBeforeRouteUpdate(async (to, from) => {
  if (to.query.contact === from.query.contact) return true
  return !workspace.saving.value && (await allowDiscard())
})
watch([workspace.detailLoading, workspace.saving], async () => {
  await nextTick()
  if (drawerOpen.value && document.activeElement === document.body)
    document
      .getElementById('contact-details-title')
      ?.focus({ preventScroll: true })
})
onBeforeUnmount(() => {
  compactStatusQuery?.removeEventListener('change', updateStatusLayout)
  discardResolve?.(false)
})
onMounted(async () => {
  compactStatusQuery?.addEventListener('change', updateStatusLayout)
  await workspace.initialize()
  initialized.value = true
})
watch([initialized, () => route.query.contact], ([ready, value]) => {
  if (!ready) return
  const id =
    typeof value === 'string' && /^[1-9]\d*$/.test(value) ? Number(value) : NaN
  if (Number.isSafeInteger(id)) openDetails(id)
  else if (drawerOpen.value) {
    drawerOpen.value = false
    workspace.clearSelection()
  }
})
</script>

<template>
  <AdminWorkspace mode="list">
    <template #intro>
      <PageHeader
        class="mb-7"
        eyebrow="Клиенты"
        title="Обращения"
        description="Список и обработка звонков, писем и партнёрских запросов."
      />
    </template>
    <ContactStatistics />
    <UiNotification v-if="workspace.error.value">{{
      workspace.error.value
    }}</UiNotification>
    <UiNotification v-if="!workspace.error.value && workspace.statusError.value"
      >{{ workspace.statusError.value }} Смена статуса обращения временно
      недоступна.</UiNotification
    >
    <UiNotification
      v-if="!workspace.error.value && workspace.assigneeError.value"
      >{{ workspace.assigneeError.value }} Назначение ответственного временно
      недоступно.</UiNotification
    >
    <UiNotification v-if="workspace.countsError.value">
      {{ workspace.countsError.value }}
      <UiButton
        variant="secondary"
        size="sm"
        class="ml-3"
        @click="workspace.refreshStatusCounts"
      >
        Повторить загрузку счётчиков
      </UiButton>
    </UiNotification>
    <div
      ref="list"
      class="admin-focus min-w-0"
      role="region"
      aria-label="Очередь обращений"
      tabindex="-1"
    >
      <div class="admin-container">
        <UiSelect
          v-if="compactStatuses"
          v-model="workspace.status.value"
          :options="statusSelectOptions"
          accessible-name="Статус обращения"
          :disabled="!workspace.statusReady.value"
        />
        <UiTabs
          v-else
          id="contact-status-tabs"
          v-model="workspace.status.value"
          label="Статус обращения"
          :items="tabs"
        >
          <template #item="{ item }">
            <span class="inline-flex items-center gap-2 whitespace-nowrap">
              {{ item.label }}
              <UiBadge
                v-if="
                  workspace.statusCounts.value?.[
                    item.value as 'new' | 'processing' | 'awaiting_client'
                  ] !== undefined
                "
                class="shrink-0 whitespace-nowrap"
                :tone="contactStatusTone(item.value)"
                :data-contact-count="item.value"
                >{{
                  workspace.statusCounts.value?.[
                    item.value as 'new' | 'processing' | 'awaiting_client'
                  ]
                }}</UiBadge
              >
            </span>
          </template>
        </UiTabs>
      </div>
      <div
        :id="`contact-status-tabs-${workspace.status.value}-panel`"
        :role="compactStatuses ? 'region' : 'tabpanel'"
        :aria-label="
          compactStatuses ? 'Обращения по выбранному статусу' : undefined
        "
        :aria-labelledby="
          compactStatuses
            ? undefined
            : `contact-status-tabs-${workspace.status.value}`
        "
      >
        <form
          role="search"
          class="admin-container contacts-filters"
          @submit.prevent="workspace.load()"
        >
          <UiInput
            v-model="workspace.search.value"
            searchable
            placeholder="Имя, телефон, email или текст обращения"
            aria-label="Поиск обращений"
          />
          <UiSelect
            v-model="workspace.type.value"
            :options="contactTypeOptions"
            accessible-name="Тип обращения"
          />
          <UiSelect
            v-model="workspace.assigneeFilter.value"
            :options="workspace.assigneeFilterOptions.value"
            accessible-name="Ответственный в списке"
            :disabled="
              workspace.canManage.value && !workspace.assigneeReady.value
            "
          />
          <UiButton type="submit" variant="secondary">Найти</UiButton>
        </form>
        <ContactsList
          v-if="!workspace.error.value"
          :contacts="workspace.contacts.value"
          :selected-id="workspace.selected.value?.id"
          :loading="workspace.loading.value"
          :date="workspace.date"
          :status-name="workspace.statusName"
          :type-name="workspace.typeName"
          @select="selectContact"
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
    :title="`Обращение #${contactId}`"
    title-id="contact-details-title"
    close-label="Закрыть обращение"
    :busy="workspace.saving.value"
    :suspended="discardOpen"
    overlay-class="contacts-panel-overlay"
    panel-class="contacts-panel"
    transition-name="contacts-panel"
    @close="closeDetails"
  >
    <ContactDetails
      v-model:comment-body="workspace.commentBody.value"
      :selected="workspace.selected.value"
      :statuses="workspace.statuses.value"
      :current-status="workspace.currentStatus.value"
      :allowed-statuses="workspace.allowedStatuses.value"
      :transition-ready="workspace.transitionReady.value"
      :assignees="workspace.assignees.value"
      :history="workspace.history.value"
      :comments="workspace.comments.value"
      :detail-loading="workspace.detailLoading.value"
      :saving="workspace.saving.value"
      :action-error="workspace.actionError.value"
      :can-manage="workspace.canManage.value"
      :can-manage-status="workspace.canManageStatus.value"
      :can-assign="workspace.canAssign.value"
      :date="workspace.date"
      :status-name="workspace.statusName"
      :type-name="workspace.typeName"
      :assignee-options="workspace.assigneeOptions.value"
      @change-status="workspace.changeStatus"
      @change-assignee="workspace.changeAssignee"
      @submit-comment="workspace.submitComment"
      @retry="workspace.initialize"
      @retry-detail="contactId && openDetails(contactId)"
    />
  </AdminSidePanel>
  <ConfirmDialog
    :open="discardOpen"
    title="Закрыть без сохранения комментария?"
    description="Внутренний комментарий ещё не добавлен. Если закрыть панель, черновик будет потерян."
    confirm-label="Закрыть без сохранения"
    @close="resolveDiscard(false)"
    @confirm="resolveDiscard(true)"
  />
</template>

<style scoped>
.contacts-filters {
  display: grid;
  grid-template-columns:
    minmax(200px, 1fr) minmax(160px, 0.4fr) minmax(160px, 0.4fr)
    auto;
  gap: var(--admin-spacing-4);
  align-items: center;
  padding-block: var(--admin-spacing-6);
}
@media (max-width: 767px) {
  .contacts-filters {
    grid-template-columns: minmax(0, 1fr);
    gap: var(--admin-spacing-3);
  }
}
@media (min-width: 768px) and (max-width: 1023px) {
  .contacts-filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
