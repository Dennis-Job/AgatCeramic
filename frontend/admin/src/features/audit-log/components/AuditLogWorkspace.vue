<script setup lang="ts">
import { computed, onMounted } from 'vue'
import UiDatePicker from '../../../components/ui/UiDatePicker.vue'
import AdminWorkspace from '../../../components/shared/AdminWorkspace.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiPagination from '../../../components/ui/UiPagination.vue'
import AuditTimeline from './AuditTimeline.vue'
import { auditActionOptions, useAuditLog } from '../composables/useAuditLog'

const audit = useAuditLog()
const pagination = computed(() => ({
  current_page: audit.page.value,
  last_page: audit.lastPage.value,
  total: audit.total.value,
  per_page: audit.perPage.value,
}))
onMounted(audit.load)
</script>

<template>
  <AdminWorkspace mode="overview">
    <template #intro>
      <PageHeader
        class="mb-7"
        eyebrow="Безопасность"
        title="Журнал аудита"
        description="История важных действий в административной панели."
      />
    </template>

    <UiNotification v-if="audit.error.value">{{
      audit.error.value
    }}</UiNotification>
    <div class="min-w-0">
      <form
        role="search"
        class="admin-container grid gap-3 border-b border-gray-100 py-4 md:grid-cols-2 admin-audit-log-filter-grid"
        @submit.prevent="audit.load()"
      >
        <UiInput
          v-model="audit.filters.value.search"
          searchable
          placeholder="Действие или сотрудник"
          aria-label="Поиск в журнале"
        />
        <UiSelect
          v-model="audit.filters.value.action"
          :options="auditActionOptions"
          accessible-name="Тип действия"
        />
        <UiDatePicker
          v-model="audit.filters.value.date_from"
          class="[&_[role=dialog]]:max-w-[calc(100vw-4rem)]"
          accessible-name="Дата с"
        />
        <UiDatePicker
          v-model="audit.filters.value.date_to"
          class="[&_[role=dialog]]:max-w-[calc(100vw-4rem)]"
          accessible-name="Дата по"
        />
        <UiButton type="submit" variant="secondary">Найти</UiButton>
        <UiButton type="button" variant="surface" @click="audit.reset"
          >Сбросить</UiButton
        >
      </form>
      <UiLoadingState
        v-if="audit.loading.value"
        class="admin-container"
        label="Загрузка журнала аудита…"
      />
      <UiEmptyState
        v-else-if="!audit.error.value && !audit.logs.value.length"
        class="admin-container"
        label="Записи не найдены."
      />
      <AuditTimeline v-else-if="!audit.error.value" :logs="audit.logs.value" />
      <div
        v-if="!audit.error.value"
        class="admin-container border-t border-gray-100 pb-6"
      >
        <UiPagination
          :meta="pagination"
          :loading="audit.loading.value"
          @change="audit.load"
        />
      </div>
    </div>
  </AdminWorkspace>
</template>
