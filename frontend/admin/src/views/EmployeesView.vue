<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Pencil, Plus, Trash2, X } from '@lucide/vue'
import { useRouter } from 'vue-router'
import BaseSelect from '../components/BaseSelect.vue'
import BaseCheckbox from '../components/BaseCheckbox.vue'
import BaseInput from '../components/BaseInput.vue'
import UiAlert from '../components/ui/UiAlert.vue'
import UiBadge from '../components/ui/UiBadge.vue'
import UiButton from '../components/ui/UiButton.vue'
import UiDialog from '../components/ui/UiDialog.vue'
import UiTable from '../components/ui/UiTable.vue'
import ConfirmDialog from '../components/shared/ConfirmDialog.vue'
import PageHeader from '../components/shared/PageHeader.vue'
import { deleteEmployee, getEmployees, getRoles, roleDisplayName, saveEmployee, type Employee, type EmployeePayload, type Role } from '../services/employees'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()
const employees = ref<Employee[]>([])
const roles = ref<Role[]>([])
const search = ref('')
const status = ref('')
const loading = ref(false)
const error = ref('')
const isFormOpen = ref(false)
const editing = ref<Employee | null>(null)
const deleting = ref<Employee | null>(null)
const isDeleting = ref(false)
const form = ref<EmployeePayload>({ name: '', email: '', password: '', password_confirmation: '', status: 'active', role_ids: [] })
const title = computed(() => editing.value ? 'Редактировать сотрудника' : 'Новый сотрудник')
const statusFilterOptions = [
  { label: 'Все статусы', value: '' },
  { label: 'Активные', value: 'active' },
  { label: 'Заблокированные', value: 'blocked' },
]
const statusOptions = [
  { label: 'Активен', value: 'active' },
  { label: 'Заблокирован', value: 'blocked' },
]

function openForm(employee: Employee | null = null): void {
  editing.value = employee
  form.value = employee ? { name: employee.name, email: employee.email, status: employee.status, role_ids: employee.roles.map((role) => role.id) } : { name: '', email: '', password: '', password_confirmation: '', status: 'active', role_ids: [] }
  isFormOpen.value = true
}

async function load(): Promise<void> {
  loading.value = true; error.value = ''
  try { employees.value = (await getEmployees(search.value, status.value)).data } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось загрузить сотрудников.' } finally { loading.value = false }
}

async function submit(): Promise<void> {
  error.value = ''
  const changedOwnPassword = Boolean(form.value.password) && editing.value?.id === auth.user?.id
  try {
    await saveEmployee(editing.value?.id ?? null, form.value)
    if (changedOwnPassword) {
      await auth.logout()
      await router.replace({ name: 'login', query: { password_changed: '1' } })
      return
    }
    isFormOpen.value = false
    await load()
  } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось сохранить сотрудника.' }
}

function requestRemoval(employee: Employee): void {
  deleting.value = employee
}

async function remove(): Promise<void> {
  if (!deleting.value) return
  error.value = ''
  isDeleting.value = true
  try {
    await deleteEmployee(deleting.value.id)
    deleting.value = null
    await load()
  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : 'Не удалось удалить сотрудника.'
  } finally {
    isDeleting.value = false
  }
}

onMounted(async () => { try { roles.value = await getRoles() } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось загрузить роли.' }; await load() })
</script>

<template>
  <section class="mx-auto admin-page">
    <PageHeader class="mb-7" eyebrow="Управление доступом" title="Сотрудники"><template #actions><UiButton @click="openForm()"><Plus :size="18" aria-hidden="true" />Добавить сотрудника</UiButton></template></PageHeader>
    <UiAlert v-if="error" class="mb-4">{{ error }}</UiAlert>
    <div class="rounded-xl border border-gray-200 bg-white shadow-card"><div class="grid grid-cols-2 gap-3 border-b border-gray-100 p-5 admin-employees-filter-grid"><BaseInput v-model="search" class="col-span-2 sm:col-auto" searchable placeholder="Имя или email" @keyup.enter="load" /><BaseSelect v-model="status" accessible-name="Фильтр по статусу" :options="statusFilterOptions" @change="load" /><UiButton variant="secondary" class="w-full sm:w-auto" @click="load">Найти</UiButton></div>
      <UiTable min-width="min-w-[680px]" table-class="admin-table-employees" label="Список сотрудников"><thead class="bg-gray-25 text-xs font-medium text-gray-500"><tr><th class="px-6 py-3">Сотрудник</th><th class="px-6 py-3">Роли</th><th class="px-6 py-3">Статус</th><th class="px-6 py-3">Последний вход</th><th class="px-6 py-3" /></tr></thead><tbody><tr v-if="loading"><td class="px-6 py-6 text-gray-500" colspan="5" role="status" aria-live="polite">Загрузка…</td></tr><tr v-else-if="!employees.length"><td class="px-6 py-6 text-gray-500" colspan="5" role="status" aria-live="polite">Сотрудники не найдены.</td></tr><tr v-for="employee in employees" :key="employee.id" class="border-t border-gray-100 text-gray-600"><td class="px-6 py-4"><p class="font-semibold text-gray-700">{{ employee.name }}</p><p class="mt-0.5 text-xs text-gray-500">{{ employee.email }}</p></td><td class="px-6 py-4"><UiBadge v-for="role in employee.roles" :key="role.id" class="mr-1" tone="primary">{{ roleDisplayName(role) }}</UiBadge></td><td class="px-6 py-4"><UiBadge :tone="employee.status === 'active' ? 'success' : 'danger'">{{ employee.status === 'active' ? 'Активен' : 'Заблокирован' }}</UiBadge></td><td class="px-6 py-4 text-gray-500">{{ employee.last_login_at ? new Date(employee.last_login_at).toLocaleString('ru-RU') : '—' }}</td><td class="px-6 py-4"><div class="flex justify-end gap-2"><button class="rounded-lg p-2 text-gray-500 hover:bg-primary-50 hover:text-primary-600" aria-label="Редактировать" @click="openForm(employee)"><Pencil :size="17" /></button><button class="rounded-lg p-2 text-gray-500 hover:bg-error-50 hover:text-error-500" aria-label="Удалить" @click="requestRemoval(employee)"><Trash2 :size="17" /></button></div></td></tr></tbody></UiTable>
    </div>
    <UiDialog :open="isFormOpen" labelledby="employee-dialog-title" describedby="employee-dialog-description" panel-class="w-full max-w-xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl" @close="isFormOpen = false"><form @submit.prevent="submit"><div class="flex items-start justify-between"><div><h2 id="employee-dialog-title" class="text-lg font-bold text-gray-900">{{ title }}</h2><p id="employee-dialog-description" class="mt-1 text-sm text-gray-500">Укажите учётные данные, статус и роли.</p></div><button type="button" class="rounded-lg p-1 text-gray-500" aria-label="Закрыть окно сотрудника" @click="isFormOpen = false"><X :size="20" /></button></div><div class="mt-6 grid gap-4"><label class="text-sm font-medium text-gray-700">Имя<BaseInput v-model="form.name" class="mt-1.5 w-full font-normal" required /></label><label class="text-sm font-medium text-gray-700">Email<BaseInput v-model="form.email" class="mt-1.5 w-full font-normal" required type="email" /></label><div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium text-gray-700">{{ editing ? 'Новый пароль (необязательно)' : 'Пароль' }}<BaseInput v-model="form.password" class="mt-1.5 w-full font-normal" :required="!editing" minlength="12" type="password" /></label><label class="text-sm font-medium text-gray-700">Подтверждение пароля<BaseInput v-model="form.password_confirmation" class="mt-1.5 w-full font-normal" :required="!editing || Boolean(form.password)" type="password" /></label></div><label class="text-sm font-medium text-gray-700">Статус<BaseSelect v-model="form.status" class="mt-1.5 w-full font-normal" accessible-name="Статус сотрудника" :options="statusOptions" /></label><fieldset><legend class="text-sm font-medium text-gray-700">Роли</legend><div class="mt-2 grid gap-2 sm:grid-cols-2"><BaseCheckbox v-for="role in roles" :key="role.id" v-model="form.role_ids" :value="role.id">{{ roleDisplayName(role) }}</BaseCheckbox></div></fieldset></div><div class="mt-6 flex justify-end gap-3"><UiButton type="button" variant="ghost" @click="isFormOpen = false">Отмена</UiButton><UiButton>Сохранить</UiButton></div></form></UiDialog>
    <ConfirmDialog :open="Boolean(deleting)" title="Удалить сотрудника?" :description="`Учётная запись «${deleting?.name ?? ''}» будет удалена. Это действие нельзя отменить.`" :busy="isDeleting" @close="deleting = null" @confirm="remove" />
  </section>
</template>
