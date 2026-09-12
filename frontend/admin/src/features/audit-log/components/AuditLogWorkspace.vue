<script setup lang="ts">
import { onMounted } from 'vue'
import { ScrollText } from '@lucide/vue'
import UiDatePicker from '../../../components/ui/UiDatePicker.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiTable from '../../../components/ui/UiTable.vue'
import { auditActionName, auditActionOptions, useAuditLog } from '../composables/useAuditLog'
import type { AuditLog } from '../types/auditLog.types'

const audit = useAuditLog()
const date = (value: string): string => new Date(value).toLocaleString('ru-RU')
const entityName = (log: AuditLog): string => log.entity?.name ?? (log.entity ? `${log.entity.type} #${log.entity.id}` : '—')
onMounted(audit.load)
</script>

<template>
  <section class="mx-auto admin-page">
    <PageHeader class="mb-7" eyebrow="Безопасность" title="Журнал аудита" description="История важных действий в административной панели." />
    <UiAlert v-if="audit.error.value" class="mb-4">{{ audit.error.value }}</UiAlert>
    <UiCard :padded="false">
      <form role="search" class="grid gap-3 border-b border-gray-100 p-4 md:grid-cols-2 admin-audit-log-filter-grid" @submit.prevent="audit.load()">
        <UiInput v-model="audit.filters.value.search" searchable placeholder="Действие или сотрудник" aria-label="Поиск в журнале" />
        <UiSelect v-model="audit.filters.value.action" :options="auditActionOptions" accessible-name="Тип действия" />
        <UiDatePicker v-model="audit.filters.value.date_from" class="[&_[role=dialog]]:max-w-[calc(100vw-4rem)]" accessible-name="Дата с" />
        <UiDatePicker v-model="audit.filters.value.date_to" class="[&_[role=dialog]]:max-w-[calc(100vw-4rem)]" accessible-name="Дата по" />
        <UiButton type="submit" variant="secondary">Найти</UiButton>
        <UiButton type="button" variant="ghost" @click="audit.reset">Сбросить</UiButton>
      </form>
      <UiLoadingState v-if="audit.loading.value" label="Загрузка журнала аудита…" />
      <UiEmptyState v-else-if="!audit.error.value && !audit.logs.value.length" label="Записи не найдены." />
      <template v-else-if="!audit.error.value">
        <div class="divide-y divide-gray-100 xl:hidden">
          <article v-for="log in audit.logs.value" :key="log.id" class="p-4">
            <div class="flex flex-wrap items-start justify-between gap-2"><div><UiBadge tone="primary"><ScrollText :size="14" aria-hidden="true" class="mr-1" />{{ auditActionName(log.action) }}</UiBadge><p class="mt-1 break-all text-xs text-gray-400">{{ log.action }}</p></div><time class="text-xs text-gray-500">{{ date(log.occurred_at) }}</time></div>
            <dl class="mt-3 grid gap-2 text-sm"><div><dt class="text-xs text-gray-500">Сотрудник</dt><dd class="break-words font-medium text-gray-700">{{ log.actor?.name ?? 'Система' }}</dd></div><div><dt class="text-xs text-gray-500">Объект</dt><dd class="break-words text-gray-700">{{ entityName(log) }}</dd><dd v-if="log.entity?.email" class="break-all text-xs text-gray-500">{{ log.entity.email }}</dd></div></dl>
            <div v-if="log.details.length" class="mt-3 space-y-1 text-sm"><p v-for="detail in log.details" :key="detail.label" class="break-words"><span class="text-gray-500">{{ detail.label }}:</span> <span class="font-medium text-gray-700">{{ detail.value }}</span></p></div>
          </article>
        </div>
        <div class="hidden xl:block">
        <UiTable class="hidden md:block" table-class="admin-table-audit-log" label="Записи журнала аудита"><thead class="bg-gray-25 text-xs font-medium text-gray-500"><tr><th class="px-6 py-3">Действие</th><th class="px-6 py-3">Сотрудник</th><th class="px-6 py-3">Объект</th><th class="px-6 py-3">Время</th><th class="px-6 py-3">Детали</th></tr></thead><tbody><tr v-for="log in audit.logs.value" :key="log.id" class="border-t border-gray-100 text-gray-600"><td class="px-6 py-4"><UiBadge tone="primary"><ScrollText :size="14" aria-hidden="true" class="mr-1" />{{ auditActionName(log.action) }}</UiBadge><p class="mt-1 text-xs text-gray-400">{{ log.action }}</p></td><td class="px-6 py-4 font-medium text-gray-700">{{ log.actor?.name ?? 'Система' }}</td><td class="px-6 py-4 text-gray-500"><p class="font-medium text-gray-700">{{ entityName(log) }}</p><p v-if="log.entity?.email" class="mt-0.5 text-xs">{{ log.entity.email }}</p></td><td class="whitespace-nowrap px-6 py-4 text-gray-500">{{ date(log.occurred_at) }}</td><td class="max-w-72 px-6 py-4"><div v-if="log.details.length" class="space-y-1.5 text-sm"><p v-for="detail in log.details" :key="detail.label"><span class="text-gray-500">{{ detail.label }}:</span> <span class="font-medium text-gray-700">{{ detail.value }}</span></p></div><span v-else class="text-gray-400">—</span></td></tr></tbody></UiTable>
        </div>
      </template>
      <div v-if="!audit.error.value" class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-5 py-4 text-sm text-gray-500"><span>Всего: {{ audit.total.value }}</span><nav class="flex items-center gap-3" aria-label="Пагинация журнала аудита"><UiButton size="sm" variant="secondary" :disabled="audit.page.value === 1 || audit.loading.value" @click="audit.load(audit.page.value - 1)">Назад</UiButton><span>Страница {{ audit.page.value }} из {{ audit.lastPage.value }}</span><UiButton size="sm" variant="secondary" :disabled="audit.page.value === audit.lastPage.value || audit.loading.value" @click="audit.load(audit.page.value + 1)">Вперёд</UiButton></nav></div>
    </UiCard>
  </section>
</template>
