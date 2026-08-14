<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
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
const generatedSlug = ref('')
const canManage = computed(() => auth.hasPermission('roles.manage'))
const title = computed(() => editing.value ? `Роль: ${editing.value.name}` : 'Новая роль')

const transliterationMap: Record<string, string> = { а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'yo', ж: 'zh', з: 'z', и: 'i', й: 'y', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u', ф: 'f', х: 'kh', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'shch', ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya' }

function toSlug(value: string): string {
  return Array.from(value.toLowerCase(), (character) => transliterationMap[character] ?? character).join('').normalize('NFKD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')
}

watch(() => form.value.name, (name) => {
  if (editing.value || (form.value.slug !== '' && form.value.slug !== generatedSlug.value)) return
  generatedSlug.value = toSlug(name)
  form.value.slug = generatedSlug.value
})

function open(role: AccessRole | null = null): void {
  editing.value = role
  generatedSlug.value = ''
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
  <section class="mx-auto admin-page"><div class="mb-7 flex flex-wrap items-end justify-between gap-4"><div><p class="text-sm font-medium text-gray-500">Управление доступом</p><h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">Роли</h1></div><button v-if="canManage" class="inline-flex items-center gap-2 rounded-lg bg-primary-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-600" @click="open()"><Plus :size="18" />Добавить роль</button></div><p v-if="error" class="mb-4 rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-500">{{ error }}</p><div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"><article v-for="role in roles" :key="role.id" class="rounded-xl border border-gray-200 bg-white p-5 shadow-card"><div class="flex gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-primary-50 text-primary-600"><ShieldCheck :size="20" /></span><div class="min-w-0 flex-1"><div class="flex items-start justify-between gap-2"><div><h2 class="font-semibold text-gray-700">{{ role.name }}</h2><p class="mt-0.5 text-xs text-gray-400">{{ role.slug }}</p></div><span v-if="role.is_system" class="admin-badge rounded-full bg-gray-50 px-2 py-1 text-xs font-medium text-gray-500">Системная</span></div><p class="mt-3 min-h-10 text-sm text-gray-500">{{ role.description || 'Без описания' }}</p></div></div><p class="mt-4 text-sm font-medium text-gray-600">Прав: {{ role.permissions.length }}</p><div v-if="canManage" class="mt-4 flex justify-end gap-2 border-t border-gray-100 pt-4"><button class="rounded-lg p-2 text-gray-500 hover:bg-primary-50 hover:text-primary-600" @click="open(role)"><Pencil :size="17" /></button><button v-if="!role.is_system" class="rounded-lg p-2 text-gray-500 hover:bg-error-50 hover:text-error-500" @click="remove(role)"><Trash2 :size="17" /></button></div></article></div><div v-if="opened" class="fixed inset-0 z-50 grid place-items-center bg-gray-900/50 p-4" @click.self="opened = false"><form class="admin-dialog-content w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl" @submit.prevent="save"><div class="flex items-start justify-between"><div><h2 class="text-lg font-bold text-gray-900">{{ title }}</h2><p class="mt-1 text-sm text-gray-500">Настройте роль и доступные ей права.</p></div><button type="button" class="p-1 text-gray-500" @click="opened = false"><X :size="20" /></button></div><div class="mt-6 grid gap-4"><div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium text-gray-700">Название<BaseInput v-model="form.name" class="mt-1.5" :disabled="editing?.is_system" required /></label><label class="text-sm font-medium text-gray-700">Технический код<BaseInput v-model="form.slug" class="mt-1.5" :disabled="editing?.is_system" required /></label></div><label class="text-sm font-medium text-gray-700">Описание<textarea v-model="form.description" class="mt-1.5 min-h-20 w-full rounded-lg border border-gray-300 p-3 font-normal outline-none focus:border-primary-500" /></label><fieldset><legend class="text-sm font-medium text-gray-700">Права</legend><div class="mt-2 grid gap-2 sm:grid-cols-2"><BaseCheckbox v-for="permission in permissions" :key="permission.id" v-model="form.permission_ids" :value="permission.id">{{ permission.name }} <span class="text-xs text-gray-400">({{ permission.code }})</span></BaseCheckbox></div></fieldset></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="px-4 py-2.5 text-sm font-semibold text-gray-600" @click="opened = false">Отмена</button><button class="rounded-lg bg-primary-500 px-4 py-2.5 text-sm font-semibold text-white">Сохранить</button></div></form></div></section>
</template>
