<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Pencil, Plus, ShieldCheck, Trash2, X } from '@lucide/vue'
import BaseCheckbox from '../components/BaseCheckbox.vue'
import BaseInput from '../components/BaseInput.vue'
import { deleteRole, getPermissions, getRoles, saveRole, type AccessRole, type Permission, type RolePayload } from '../services/roles'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const roles = ref<AccessRole[]>([])
const permissions = ref<Permission[]>([])
const error = ref('')
const opened = ref(false)
const editing = ref<AccessRole | null>(null)
const form = ref<RolePayload>({ name: '', slug: '', description: '', permission_ids: [] })
const canManage = computed(() => auth.hasPermission('roles.manage'))
const title = computed(() => editing.value ? `Роль: ${editing.value.name}` : 'Новая роль')

function open(role: AccessRole | null = null): void {
  editing.value = role
  form.value = role ? { name: role.name, slug: role.slug, description: role.description ?? '', permission_ids: role.permissions.map((item) => item.id) } : { name: '', slug: '', description: '', permission_ids: [] }
  opened.value = true
}
async function load(): Promise<void> {
  try { roles.value = await getRoles(); if (canManage.value) permissions.value = await getPermissions() } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось загрузить роли.' }
}
async function save(): Promise<void> {
  try { await saveRole(editing.value?.id ?? null, form.value); opened.value = false; await load() } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось сохранить роль.' }
}
async function remove(role: AccessRole): Promise<void> {
  if (!window.confirm(`Удалить роль «${role.name}»?`)) return
  try { await deleteRole(role.id); await load() } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось удалить роль.' }
}
onMounted(load)
</script>

<template>
  <section class="mx-auto max-w-[1440px]"><div class="mb-7 flex flex-wrap items-end justify-between gap-4"><div><p class="text-sm font-medium text-[#667085]">Управление доступом</p><h1 class="mt-1 text-2xl font-bold tracking-tight text-[#101828] sm:text-3xl">Роли</h1></div><button v-if="canManage" class="inline-flex items-center gap-2 rounded-lg bg-[#7f56d9] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#6941c6]" @click="open()"><Plus :size="18" />Добавить роль</button></div><p v-if="error" class="mb-4 rounded-lg border border-[#fecdca] bg-[#fef3f2] px-4 py-3 text-sm text-[#b42318]">{{ error }}</p><div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"><article v-for="role in roles" :key="role.id" class="rounded-xl border border-[#e4e7ec] bg-white p-5 shadow-[0_1px_2px_rgba(16,24,40,.04)]"><div class="flex gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-[#f4f3ff] text-[#6941c6]"><ShieldCheck :size="20" /></span><div class="min-w-0 flex-1"><div class="flex items-start justify-between gap-2"><div><h2 class="font-semibold text-[#344054]">{{ role.name }}</h2><p class="mt-0.5 text-xs text-[#98a2b3]">{{ role.slug }}</p></div><span v-if="role.is_system" class="admin-badge rounded-full bg-[#f2f4f7] px-2 py-1 text-xs font-medium text-[#667085]">Системная</span></div><p class="mt-3 min-h-10 text-sm text-[#667085]">{{ role.description || 'Без описания' }}</p></div></div><p class="mt-4 text-sm font-medium text-[#475467]">Прав: {{ role.permissions.length }}</p><div v-if="canManage" class="mt-4 flex justify-end gap-2 border-t border-[#eaecf0] pt-4"><button class="rounded-lg p-2 text-[#667085] hover:bg-[#f4f3ff] hover:text-[#6941c6]" @click="open(role)"><Pencil :size="17" /></button><button v-if="!role.is_system" class="rounded-lg p-2 text-[#667085] hover:bg-[#fef3f2] hover:text-[#b42318]" @click="remove(role)"><Trash2 :size="17" /></button></div></article></div><div v-if="opened" class="fixed inset-0 z-50 grid place-items-center bg-[#101828]/50 p-4" @click.self="opened = false"><form class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl" @submit.prevent="save"><div class="flex items-start justify-between"><div><h2 class="text-lg font-bold text-[#101828]">{{ title }}</h2><p class="mt-1 text-sm text-[#667085]">Настройте роль и доступные ей права.</p></div><button type="button" class="p-1 text-[#667085]" @click="opened = false"><X :size="20" /></button></div><div class="mt-6 grid gap-4"><div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium text-[#344054]">Название<BaseInput v-model="form.name" class="mt-1.5" :disabled="editing?.is_system" required /></label><label class="text-sm font-medium text-[#344054]">Технический код<BaseInput v-model="form.slug" class="mt-1.5" :disabled="editing?.is_system" required /></label></div><label class="text-sm font-medium text-[#344054]">Описание<textarea v-model="form.description" class="mt-1.5 min-h-20 w-full rounded-lg border border-[#d0d5dd] p-3 font-normal outline-none focus:border-[#7f56d9]" /></label><fieldset><legend class="text-sm font-medium text-[#344054]">Права</legend><div class="mt-2 grid gap-2 sm:grid-cols-2"><BaseCheckbox v-for="permission in permissions" :key="permission.id" v-model="form.permission_ids" :value="permission.id">{{ permission.name }} <span class="text-xs text-[#98a2b3]">({{ permission.code }})</span></BaseCheckbox></div></fieldset></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="px-4 py-2.5 text-sm font-semibold text-[#475467]" @click="opened = false">Отмена</button><button class="rounded-lg bg-[#7f56d9] px-4 py-2.5 text-sm font-semibold text-white">Сохранить</button></div></form></div></section>
</template>
