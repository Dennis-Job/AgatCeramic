<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AdminListDetail from '../../../components/shared/AdminListDetail.vue'
import AdminWorkspace from '../../../components/shared/AdminWorkspace.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiPagination from '../../../components/ui/UiPagination.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import {
  contactTypeOptions,
  useContactsWorkspace,
} from '../composables/useContactsWorkspace'
import ContactDetails from './ContactDetails.vue'
import ContactsList from './ContactsList.vue'

const listDetail = ref<InstanceType<typeof AdminListDetail> | null>(null)
const list = ref<HTMLElement | null>(null)
const workspace = useContactsWorkspace()
const route = useRoute()
const router = useRouter()
const initialized = ref(false)

function openDetails(id: number) {
  if (!list.value?.contains(document.activeElement)) {
    list.value?.focus({ preventScroll: true })
  }
  void listDetail.value?.showDetail(() => workspace.select({ id }))
}

function selectContact(contact: { id: number }) {
  if (route.query.contact === String(contact.id)) openDetails(contact.id)
  else void router.replace({ query: { ...route.query, contact: contact.id } })
}
onMounted(async () => {
  await workspace.initialize()
  initialized.value = true
})
watch([initialized, () => route.query.contact], ([ready, value]) => {
  if (!ready || typeof value !== 'string' || !/^[1-9]\d*$/.test(value)) return
  const id = Number(value)
  if (!Number.isSafeInteger(id)) return
  openDetails(id)
})
</script>

<template>
  <AdminWorkspace mode="list">
    <template #intro>
      <PageHeader
        class="mb-7"
        eyebrow="Клиенты"
        title="Обращения"
        description="Звонки, письма и партнёрские запросы в единой очереди обработки."
      />
    </template>

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
    <AdminListDetail ref="listDetail">
      <template #list>
        <div
          ref="list"
          class="admin-focus min-w-0"
          role="region"
          aria-label="Очередь обращений"
          tabindex="-1"
        >
          <form
            role="search"
            class="admin-container grid gap-3 border-b border-gray-100 py-4 sm:grid-cols-2"
            @submit.prevent="workspace.load()"
          >
            <UiInput
              v-model="workspace.search.value"
              searchable
              placeholder="Имя, телефон, email или текст"
              aria-label="Поиск обращений"
            />
            <UiSelect
              v-model="workspace.type.value"
              :options="contactTypeOptions"
              accessible-name="Тип обращения"
            />
            <UiSelect
              v-model="workspace.status.value"
              :options="workspace.statusOptions.value"
              accessible-name="Статус обращения"
              :disabled="!workspace.statusReady.value"
            />
            <UiCheckbox
              v-model:checked="workspace.unassigned.value"
              mode="boolean"
              >Только нераспределённые</UiCheckbox
            >
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
            class="admin-container border-t border-gray-100 pb-4"
            @change="workspace.load"
          />
        </div>
      </template>
      <template #detail>
        <ContactDetails
          v-model:comment-body="workspace.commentBody.value"
          :selected="workspace.selected.value"
          :statuses="workspace.statuses.value"
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
        />
      </template>
    </AdminListDetail>
  </AdminWorkspace>
</template>
