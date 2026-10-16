<script setup lang="ts">
import { Clock, Mail, Pencil, Trash2 } from '@lucide/vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import type { Employee } from '../types/employee.types'

defineProps<{
  employees: Employee[]
  loading: boolean
  canManage: boolean
  canEdit: boolean
}>()
defineEmits<{ edit: [employee: Employee]; remove: [employee: Employee] }>()
const lastLogin = (value: string): string =>
  new Date(value).toLocaleString('ru-RU')
const initials = (name: string): string =>
  name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => Array.from(part)[0])
    .join('')
    .toLocaleUpperCase('ru-RU')
</script>

<template>
  <UiLoadingState
    v-if="loading"
    class="admin-container"
    label="Загрузка сотрудников…"
  />
  <UiEmptyState
    v-else-if="!employees.length"
    class="admin-container"
    label="Сотрудники не найдены."
  />
  <div
    v-else
    class="admin-container py-5"
    role="region"
    aria-label="Список сотрудников"
  >
    <ul class="employee-grid">
      <li v-for="employee in employees" :key="employee.id" class="min-w-0">
        <UiCard
          :aria-labelledby="`employee-name-${employee.id}`"
          class="h-full"
          :padded="false"
        >
          <template #header>
            <div class="flex min-w-0 items-start gap-3">
              <span
                class="employee-avatar text-xl font-bold"
                aria-hidden="true"
                >{{ initials(employee.name) }}</span
              >
              <div class="min-w-0 flex-1">
                <h2
                  :id="`employee-name-${employee.id}`"
                  class="break-words text-base font-bold text-gray-500 [overflow-wrap:anywhere]"
                >
                  {{ employee.name }}
                </h2>
                <UiBadge
                  class="mt-2"
                  :tone="employee.status === 'active' ? 'success' : 'danger'"
                  >{{
                    employee.status === 'active' ? 'Активен' : 'Заблокирован'
                  }}</UiBadge
                >
              </div>
            </div>
          </template>
          <div class="flex h-full min-w-0 flex-col">
            <div class="min-w-0 flex-1 space-y-5 p-4 sm:p-5">
              <p class="flex min-w-0 items-start gap-2 text-sm text-gray-500">
                <Mail :size="17" class="mt-0.5 shrink-0" aria-hidden="true" />
                <span class="min-w-0 break-all">{{ employee.email }}</span>
              </p>
              <div>
                <h3 class="mb-2 text-sm text-gray-500">Назначенные роли</h3>
                <div v-if="employee.roles.length" class="flex flex-wrap gap-2">
                  <UiBadge
                    v-for="role in employee.roles"
                    :key="role.id"
                    tone="primary"
                    >{{ role.name }}</UiBadge
                  >
                </div>
                <p v-else class="text-sm text-gray-500">Роли не назначены</p>
              </div>
            </div>
            <footer class="employee-footer">
              <div class="flex min-w-0 items-start gap-2 text-xs text-gray-500">
                <Clock :size="16" class="shrink-0" aria-hidden="true" />
                <div class="min-w-0">
                  <p class="mb-1">Последний вход</p>
                  <time
                    v-if="employee.last_login_at"
                    :datetime="employee.last_login_at"
                    >{{ lastLogin(employee.last_login_at) }}</time
                  >
                  <span v-else>Не входил</span>
                </div>
              </div>
              <div v-if="canManage" class="flex flex-wrap items-center gap-1">
                <UiButton
                  variant="primary-ghost"
                  size="sm"
                  :disabled="!canEdit"
                  :aria-label="`Редактировать сотрудника ${employee.name}`"
                  @click="$emit('edit', employee)"
                >
                  <Pencil :size="16" aria-hidden="true" />Редактировать
                </UiButton>
                <UiButton
                  variant="danger-ghost"
                  size="sm"
                  tooltip="Удалить сотрудника"
                  :aria-label="`Удалить сотрудника ${employee.name}`"
                  @click="$emit('remove', employee)"
                >
                  <Trash2 :size="16" aria-hidden="true" />
                </UiButton>
              </div>
            </footer>
          </div>
        </UiCard>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.employee-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--admin-spacing-5);
  list-style: none;
}
.employee-avatar {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: var(--admin-dialog-close-size);
  height: var(--admin-dialog-close-size);
  border-radius: 50%;
  background: var(--admin-color-gray-200);
  color: var(--admin-color-gray-500);
}
.employee-footer {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--admin-spacing-3);
  padding: var(--admin-spacing-4);
  border-top: var(--admin-border-width) solid var(--admin-color-gray-100);
}
@media (min-width: 768px) {
  .employee-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (min-width: 1280px) {
  .employee-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
</style>
