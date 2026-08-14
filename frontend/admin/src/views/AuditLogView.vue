<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { ScrollText } from '@lucide/vue'
import BaseDatePicker from '../components/BaseDatePicker.vue'
import BaseInput from '../components/BaseInput.vue'
import BaseSelect from '../components/BaseSelect.vue'
import { getAuditLogs, type AuditLog, type AuditLogFilters } from '../services/audit-logs'

const logs = ref<AuditLog[]>([])
type Filters = Required<Omit<AuditLogFilters, 'page'>>
const filters = ref<Filters>({ search: '', action: '', date_from: '', date_to: '' })
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const loading = ref(false)
const error = ref('')
let loadSequence = 0

const actionOptions = [
  { label: 'Все действия', value: '' },
  { label: 'Вход в систему', value: 'auth.login' },
  { label: 'Выход из системы', value: 'auth.logout' },
  { label: 'Создание сотрудника', value: 'admin-user.created' },
  { label: 'Изменение сотрудника', value: 'admin-user.updated' },
  { label: 'Удаление сотрудника', value: 'admin-user.deleted' },
  { label: 'Создание роли', value: 'role.created' },
  { label: 'Изменение роли', value: 'role.updated' },
  { label: 'Удаление роли', value: 'role.deleted' },
  { label: 'Создание супер-администратора', value: 'admin.bootstrap' },
]

const actionNames = Object.fromEntries(actionOptions.filter((option) => option.value).map((option) => [option.value, option.label]))

function actionName(action: string): string {
  return actionNames[action] ?? action
}

async function load(nextPage = 1): Promise<void> {
  const sequence = ++loadSequence
  const query = { ...filters.value, page: nextPage }
  loading.value = true
  error.value = ''
  try {
    const response = await getAuditLogs(query)
    if (sequence !== loadSequence) return

    logs.value = response.data
    page.value = response.meta.current_page
    lastPage.value = response.meta.last_page
    total.value = response.meta.total
  } catch (reason) {
    if (sequence !== loadSequence) return

    error.value = reason instanceof Error ? reason.message : 'Не удалось загрузить журнал аудита.'
  } finally {
    if (sequence === loadSequence) loading.value = false
  }
}

function reset(): void {
  const reactiveFiltersChanged = Boolean(filters.value.action || filters.value.date_from || filters.value.date_to)
  filters.value = { search: '', action: '', date_from: '', date_to: '' }
  if (!reactiveFiltersChanged) void load()
}

watch(
  () => [filters.value.action, filters.value.date_from, filters.value.date_to],
  () => void load(),
)

onMounted(load)
</script>

<template>
  <section class="mx-auto admin-page">
    <div class="mb-7">
      <p class="text-sm font-medium text-gray-500">Безопасность</p>
      <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">Журнал аудита</h1>
      <p class="mt-2 text-sm text-gray-500">История важных действий в административной панели.</p>
    </div>

    <p v-if="error" class="mb-4 rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-500">{{ error }}</p>

    <div class="rounded-xl border border-gray-200 bg-white shadow-card">
      <div class="grid gap-3 border-b border-gray-100 p-4 md:grid-cols-2 admin-audit-log-filter-grid">
        <BaseInput v-model="filters.search" searchable placeholder="Действие или сотрудник" aria-label="Поиск в журнале" @keyup.enter="() => load()" />
        <BaseSelect v-model="filters.action" :options="actionOptions" accessible-name="Тип действия" />
        <BaseDatePicker v-model="filters.date_from" accessible-name="Дата с" />
        <BaseDatePicker v-model="filters.date_to" accessible-name="Дата по" />
        <button class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-25" @click="() => load()">Найти</button>
        <button class="px-3 py-2.5 text-sm font-semibold text-primary-600 hover:text-primary-700" @click="reset">Сбросить</button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full admin-table-audit-log text-left text-sm">
          <thead class="bg-gray-25 text-xs font-medium text-gray-500"><tr><th class="px-6 py-3">Действие</th><th class="px-6 py-3">Сотрудник</th><th class="px-6 py-3">Объект</th><th class="px-6 py-3">Время</th><th class="px-6 py-3">Детали</th></tr></thead>
          <tbody>
            <tr v-if="loading"><td colspan="5" class="px-6 py-8 text-gray-500">Загрузка…</td></tr>
            <tr v-else-if="!logs.length"><td colspan="5" class="px-6 py-8 text-gray-500">Записи не найдены.</td></tr>
            <tr v-for="log in logs" :key="log.id" class="border-t border-gray-100 text-gray-600">
              <td class="px-6 py-4"><span class="admin-badge inline-flex items-center gap-2 rounded-full bg-primary-50 px-2.5 py-1 text-xs font-medium text-primary-600"><ScrollText :size="14" />{{ actionName(log.action) }}</span><p class="mt-1 text-xs text-gray-400">{{ log.action }}</p></td>
              <td class="px-6 py-4 font-medium text-gray-700">{{ log.actor?.name ?? 'Система' }}</td>
              <td class="px-6 py-4 text-gray-500"><template v-if="log.entity?.name"><p class="font-medium text-gray-700">{{ log.entity.name }}</p><p v-if="log.entity.email" class="mt-0.5 text-xs">{{ log.entity.email }}</p></template><template v-else-if="log.entity">{{ log.entity.type }} #{{ log.entity.id }}</template><template v-else>—</template></td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-500">{{ new Date(log.occurred_at).toLocaleString('ru-RU') }}</td>
              <td class="max-w-72 px-6 py-4"><div v-if="log.details.length" class="space-y-1.5 text-sm"><p v-for="detail in log.details" :key="detail.label"><span class="text-gray-500">{{ detail.label }}:</span> <span class="font-medium text-gray-700">{{ detail.value }}</span></p></div><span v-else class="text-gray-400">—</span></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-5 py-4 text-sm text-gray-500">
        <span>Всего: {{ total }}</span>
        <div class="flex items-center gap-3"><button class="rounded-lg border border-gray-300 px-3 py-1.5 disabled:cursor-not-allowed disabled:opacity-50" :disabled="page === 1 || loading" @click="load(page - 1)">Назад</button><span>Страница {{ page }} из {{ lastPage }}</span><button class="rounded-lg border border-gray-300 px-3 py-1.5 disabled:cursor-not-allowed disabled:opacity-50" :disabled="page === lastPage || loading" @click="load(page + 1)">Вперёд</button></div>
      </div>
    </div>
  </section>
</template>
