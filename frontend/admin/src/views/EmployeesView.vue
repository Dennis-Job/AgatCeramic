<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Pencil, Plus, Search, Trash2, X } from '@lucide/vue'
import { deleteEmployee, getEmployees, getRoles, saveEmployee, type Employee, type EmployeePayload, type Role } from '../services/employees'

const employees = ref<Employee[]>([])
const roles = ref<Role[]>([])
const search = ref('')
const status = ref('')
const loading = ref(false)
const error = ref('')
const isFormOpen = ref(false)
const editing = ref<Employee | null>(null)
const form = ref<EmployeePayload>({ name: '', email: '', password: '', password_confirmation: '', status: 'active', role_ids: [] })
const title = computed(() => editing.value ? 'Редактировать сотрудника' : 'Новый сотрудник')

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
  try { await saveEmployee(editing.value?.id ?? null, form.value); isFormOpen.value = false; await load() } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось сохранить сотрудника.' }
}

async function remove(employee: Employee): Promise<void> {
  if (!window.confirm(`Удалить учётную запись «${employee.name}»?`)) return
  error.value = ''
  try { await deleteEmployee(employee.id); await load() } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось удалить сотрудника.' }
}

onMounted(async () => { try { roles.value = await getRoles() } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось загрузить роли.' }; await load() })
</script>

<template>
  <section class="mx-auto max-w-[1440px]">
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4"><div><p class="text-sm font-medium text-[#667085]">Управление доступом</p><h1 class="mt-1 text-2xl font-bold tracking-tight text-[#101828] sm:text-3xl">Сотрудники</h1></div><button class="inline-flex items-center gap-2 rounded-lg bg-[#7f56d9] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#6941c6]" @click="openForm()"><Plus :size="18" />Добавить сотрудника</button></div>
    <p v-if="error" class="mb-4 rounded-lg border border-[#fecdca] bg-[#fef3f2] px-4 py-3 text-sm text-[#b42318]">{{ error }}</p>
    <div class="rounded-xl border border-[#e4e7ec] bg-white shadow-[0_1px_2px_rgba(16,24,40,.04)]"><div class="flex flex-wrap gap-3 border-b border-[#eaecf0] p-5"><label class="flex min-w-[240px] flex-1 items-center gap-2 rounded-lg border border-[#d0d5dd] px-3 text-sm text-[#667085]"><Search :size="17" /><input v-model="search" class="w-full py-2 outline-none" placeholder="Имя или email" @keyup.enter="load" /></label><select v-model="status" class="rounded-lg border border-[#d0d5dd] bg-white px-3 text-sm text-[#475467]" @change="load"><option value="">Все статусы</option><option value="active">Активные</option><option value="blocked">Заблокированные</option></select><button class="rounded-lg border border-[#d0d5dd] px-4 text-sm font-semibold text-[#475467]" @click="load">Найти</button></div>
      <div class="overflow-x-auto"><table class="w-full min-w-[780px] text-left text-sm"><thead class="bg-[#f9fafb] text-xs font-medium text-[#667085]"><tr><th class="px-6 py-3">Сотрудник</th><th class="px-6 py-3">Роли</th><th class="px-6 py-3">Статус</th><th class="px-6 py-3">Последний вход</th><th class="px-6 py-3" /></tr></thead><tbody><tr v-if="loading"><td class="px-6 py-6 text-[#667085]" colspan="5">Загрузка…</td></tr><tr v-else-if="!employees.length"><td class="px-6 py-6 text-[#667085]" colspan="5">Сотрудники не найдены.</td></tr><tr v-for="employee in employees" :key="employee.id" class="border-t border-[#eaecf0] text-[#475467]"><td class="px-6 py-4"><p class="font-semibold text-[#344054]">{{ employee.name }}</p><p class="mt-0.5 text-xs text-[#667085]">{{ employee.email }}</p></td><td class="px-6 py-4"><span v-for="role in employee.roles" :key="role.id" class="mr-1 inline-block rounded-full bg-[#f4f3ff] px-2 py-1 text-xs font-medium text-[#6941c6]">{{ role.name }}</span></td><td class="px-6 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="employee.status === 'active' ? 'bg-[#ecfdf3] text-[#027a48]' : 'bg-[#fef3f2] text-[#b42318]'">{{ employee.status === 'active' ? 'Активен' : 'Заблокирован' }}</span></td><td class="px-6 py-4 text-[#667085]">{{ employee.last_login_at ? new Date(employee.last_login_at).toLocaleString('ru-RU') : '—' }}</td><td class="px-6 py-4"><div class="flex justify-end gap-2"><button class="rounded-lg p-2 text-[#667085] hover:bg-[#f4f3ff] hover:text-[#6941c6]" aria-label="Редактировать" @click="openForm(employee)"><Pencil :size="17" /></button><button class="rounded-lg p-2 text-[#667085] hover:bg-[#fef3f2] hover:text-[#b42318]" aria-label="Удалить" @click="remove(employee)"><Trash2 :size="17" /></button></div></td></tr></tbody></table></div>
    </div>
    <div v-if="isFormOpen" class="fixed inset-0 z-50 grid place-items-center bg-[#101828]/50 p-4" @click.self="isFormOpen = false"><form class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl" @submit.prevent="submit"><div class="flex items-start justify-between"><div><h2 class="text-lg font-bold text-[#101828]">{{ title }}</h2><p class="mt-1 text-sm text-[#667085]">Укажите учётные данные, статус и роли.</p></div><button type="button" class="rounded-lg p-1 text-[#667085]" @click="isFormOpen = false"><X :size="20" /></button></div><div class="mt-6 grid gap-4"><label class="text-sm font-medium text-[#344054]">Имя<input v-model="form.name" required class="mt-1.5 w-full rounded-lg border border-[#d0d5dd] px-3 py-2.5 font-normal outline-none focus:border-[#7f56d9]" /></label><label class="text-sm font-medium text-[#344054]">Email<input v-model="form.email" required type="email" class="mt-1.5 w-full rounded-lg border border-[#d0d5dd] px-3 py-2.5 font-normal outline-none focus:border-[#7f56d9]" /></label><div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium text-[#344054]">{{ editing ? 'Новый пароль (необязательно)' : 'Пароль' }}<input v-model="form.password" :required="!editing" minlength="12" type="password" class="mt-1.5 w-full rounded-lg border border-[#d0d5dd] px-3 py-2.5 font-normal outline-none focus:border-[#7f56d9]" /></label><label class="text-sm font-medium text-[#344054]">Подтверждение пароля<input v-model="form.password_confirmation" :required="!editing || Boolean(form.password)" type="password" class="mt-1.5 w-full rounded-lg border border-[#d0d5dd] px-3 py-2.5 font-normal outline-none focus:border-[#7f56d9]" /></label></div><label class="text-sm font-medium text-[#344054]">Статус<select v-model="form.status" class="mt-1.5 w-full rounded-lg border border-[#d0d5dd] bg-white px-3 py-2.5 font-normal outline-none focus:border-[#7f56d9]"><option value="active">Активен</option><option value="blocked">Заблокирован</option></select></label><fieldset><legend class="text-sm font-medium text-[#344054]">Роли</legend><div class="mt-2 grid gap-2 sm:grid-cols-2"><label v-for="role in roles" :key="role.id" class="flex items-center gap-2 rounded-lg border border-[#eaecf0] p-3 text-sm text-[#475467]"><input v-model="form.role_ids" type="checkbox" :value="role.id" />{{ role.name }}</label></div></fieldset></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-[#475467]" @click="isFormOpen = false">Отмена</button><button class="rounded-lg bg-[#7f56d9] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#6941c6]">Сохранить</button></div></form></div>
  </section>
</template>
