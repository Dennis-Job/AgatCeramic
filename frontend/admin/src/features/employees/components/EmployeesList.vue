<script setup lang="ts">
import { Pencil, Trash2 } from '@lucide/vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiTable from '../../../components/ui/UiTable.vue'
import { roleDisplayName } from '../services/employees'
import type { Employee } from '../types/employee.types'

defineProps<{ employees: Employee[]; loading: boolean; canManage: boolean; canEdit: boolean }>()
defineEmits<{ edit: [employee: Employee]; remove: [employee: Employee] }>()
const lastLogin = (value: string | null): string => value ? new Date(value).toLocaleString('ru-RU') : '—'
</script>

<template>
  <UiLoadingState v-if="loading" label="Загрузка сотрудников…" />
  <UiEmptyState v-else-if="!employees.length" label="Сотрудники не найдены." />
  <template v-else>
    <div class="divide-y divide-gray-100 xl:hidden">
      <article v-for="employee in employees" :key="employee.id" class="p-4">
        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><h2 class="break-words font-semibold text-gray-700">{{ employee.name }}</h2><p class="break-all text-xs text-gray-500">{{ employee.email }}</p></div><UiBadge :tone="employee.status === 'active' ? 'success' : 'danger'">{{ employee.status === 'active' ? 'Активен' : 'Заблокирован' }}</UiBadge></div>
        <div class="mt-3 flex flex-wrap gap-1"><UiBadge v-for="role in employee.roles" :key="role.id" tone="primary">{{ roleDisplayName(role) }}</UiBadge><span v-if="!employee.roles.length" class="text-sm text-gray-400">Роли не назначены</span></div>
        <p class="mt-3 text-xs text-gray-500">Последний вход: {{ lastLogin(employee.last_login_at) }}</p>
        <div v-if="canManage" class="mt-3 flex justify-end gap-1"><UiButton variant="ghost" size="sm" :disabled="!canEdit" :aria-label="`Редактировать сотрудника ${employee.name}`" @click="$emit('edit', employee)"><Pencil :size="17" /></UiButton><UiButton variant="danger-ghost" size="sm" :aria-label="`Удалить сотрудника ${employee.name}`" @click="$emit('remove', employee)"><Trash2 :size="17" /></UiButton></div>
      </article>
    </div>
    <UiTable class="hidden xl:block" min-width="min-w-[680px]" table-class="admin-table-employees" label="Список сотрудников">
      <thead class="bg-gray-25 text-xs font-medium text-gray-500"><tr><th class="px-6 py-3">Сотрудник</th><th class="px-6 py-3">Роли</th><th class="px-6 py-3">Статус</th><th class="px-6 py-3">Последний вход</th><th v-if="canManage" class="px-6 py-3"><span class="sr-only">Действия</span></th></tr></thead>
      <tbody><tr v-for="employee in employees" :key="employee.id" class="border-t border-gray-100 text-gray-600"><td class="px-6 py-4"><p class="font-semibold text-gray-700">{{ employee.name }}</p><p class="mt-0.5 text-xs text-gray-500">{{ employee.email }}</p></td><td class="px-6 py-4"><UiBadge v-for="role in employee.roles" :key="role.id" class="mr-1" tone="primary">{{ roleDisplayName(role) }}</UiBadge></td><td class="px-6 py-4"><UiBadge :tone="employee.status === 'active' ? 'success' : 'danger'">{{ employee.status === 'active' ? 'Активен' : 'Заблокирован' }}</UiBadge></td><td class="px-6 py-4 text-gray-500">{{ lastLogin(employee.last_login_at) }}</td><td v-if="canManage" class="px-6 py-4"><div class="flex justify-end gap-1"><UiButton variant="ghost" size="sm" :disabled="!canEdit" :aria-label="`Редактировать сотрудника ${employee.name}`" @click="$emit('edit', employee)"><Pencil :size="17" /></UiButton><UiButton variant="danger-ghost" size="sm" :aria-label="`Удалить сотрудника ${employee.name}`" @click="$emit('remove', employee)"><Trash2 :size="17" /></UiButton></div></td></tr></tbody>
    </UiTable>
  </template>
</template>
