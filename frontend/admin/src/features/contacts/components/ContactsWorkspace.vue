<script setup lang="ts">
import { onMounted } from 'vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiPagination from '../../../components/ui/UiPagination.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import { contactTypeOptions, useContactsWorkspace } from '../composables/useContactsWorkspace'
import ContactDetails from './ContactDetails.vue'
import ContactsList from './ContactsList.vue'

const workspace = useContactsWorkspace()
onMounted(workspace.initialize)
</script>

<template>
  <section class="mx-auto admin-page">
    <PageHeader class="mb-7" eyebrow="Клиенты" title="Обращения" description="Звонки, письма и партнёрские запросы в единой очереди обработки." />
    <UiAlert v-if="workspace.error.value" class="mb-4">{{ workspace.error.value }}</UiAlert>
    <UiAlert v-if="!workspace.error.value && workspace.statusError.value" class="mb-4">{{ workspace.statusError.value }} Смена статуса обращения временно недоступна.</UiAlert>
    <UiAlert v-if="!workspace.error.value && workspace.assigneeError.value" class="mb-4">{{ workspace.assigneeError.value }} Назначение ответственного временно недоступно.</UiAlert>
    <div class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(380px,0.9fr)]">
      <UiCard :padded="false" class="min-w-0 overflow-hidden">
        <form role="search" class="grid gap-3 border-b border-gray-100 p-4 sm:grid-cols-2" @submit.prevent="workspace.load()">
          <UiInput v-model="workspace.search.value" searchable placeholder="Имя, телефон, email или текст" aria-label="Поиск обращений" />
          <UiSelect v-model="workspace.type.value" :options="contactTypeOptions" accessible-name="Тип обращения" />
          <UiSelect v-model="workspace.status.value" :options="workspace.statusOptions.value" accessible-name="Статус обращения" :disabled="!workspace.statusReady.value" />
          <UiCheckbox v-model:checked="workspace.unassigned.value" mode="boolean">Только нераспределённые</UiCheckbox>
          <UiButton type="submit" variant="secondary">Найти</UiButton>
        </form>
        <ContactsList v-if="!workspace.error.value" :contacts="workspace.contacts.value" :selected-id="workspace.selected.value?.id" :loading="workspace.loading.value" :date="workspace.date" :status-name="workspace.statusName" :type-name="workspace.typeName" @select="workspace.select" />
        <UiPagination v-if="!workspace.error.value" :meta="workspace.meta.value" :loading="workspace.loading.value" class="border-t border-gray-100 px-5 pb-4" @change="workspace.load" />
      </UiCard>
      <ContactDetails v-model:comment-body="workspace.commentBody.value" :selected="workspace.selected.value" :statuses="workspace.statuses.value" :assignees="workspace.assignees.value" :history="workspace.history.value" :comments="workspace.comments.value" :detail-loading="workspace.detailLoading.value" :saving="workspace.saving.value" :action-error="workspace.actionError.value" :can-manage="workspace.canManage.value" :can-manage-status="workspace.canManageStatus.value" :can-assign="workspace.canAssign.value" :date="workspace.date" :status-name="workspace.statusName" :type-name="workspace.typeName" :assignee-options="workspace.assigneeOptions.value" @change-status="workspace.changeStatus" @change-assignee="workspace.changeAssignee" @submit-comment="workspace.submitComment" />
    </div>
  </section>
</template>
