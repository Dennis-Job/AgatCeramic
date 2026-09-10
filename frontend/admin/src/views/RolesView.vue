<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Pencil, Plus, ShieldCheck, Trash2, X } from '@lucide/vue'
import BaseAlert from '../components/BaseAlert.vue'
import BaseCheckbox from '../components/BaseCheckbox.vue'
import BaseConfirmDialog from '../components/BaseConfirmDialog.vue'
import BaseDialog from '../components/BaseDialog.vue'
import BaseEmptyState from '../components/BaseEmptyState.vue'
import BaseInput from '../components/BaseInput.vue'
import BaseTextarea from '../components/BaseTextarea.vue'
import CollectionLoadingState from '../components/CollectionLoadingState.vue'
import { deleteRole, getPermissions, getRoles, saveRole, type AccessRole, type Permission, type RolePayload } from '../services/roles'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const roles = ref<AccessRole[]>([])
const permissions = ref<Permission[]>([])
const error = ref('')
const formError = ref('')
const deleteError = ref('')
const loading = ref(false)
const saving = ref(false)
const deleting = ref<AccessRole | null>(null)
const isDeleting = ref(false)
const opened = ref(false)
const editing = ref<AccessRole | null>(null)
const form = ref<RolePayload>({ name: '', slug: '', description: '', permission_ids: [] })
const generatedSlug = ref('')
const canManage = computed(() => auth.hasPermission('roles.manage'))
const title = computed(() => editing.value ? `Роль: ${editing.value.name}` : 'Новая роль')
const transliterationMap: Record<string, string> = { а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'yo', ж: 'zh', з: 'z', и: 'i', й: 'y', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u', ф: 'f', х: 'kh', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'shch', ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya' }

function toSlug(value: string): string { return Array.from(value.toLowerCase(), character => transliterationMap[character] ?? character).join('').normalize('NFKD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') }
watch(() => form.value.name, name => { if (!editing.value && (form.value.slug === '' || form.value.slug === generatedSlug.value)) { generatedSlug.value = toSlug(name); form.value.slug = generatedSlug.value } })
function open(role: AccessRole | null = null): void { editing.value = role; formError.value = ''; generatedSlug.value = ''; form.value = role ? { name: role.name, slug: role.slug, description: role.description ?? '', permission_ids: role.permissions.map(item => item.id) } : { name: '', slug: '', description: '', permission_ids: [] }; opened.value = true }
async function load(): Promise<void> { loading.value = true; error.value = ''; try { roles.value = await getRoles(); if (canManage.value) permissions.value = await getPermissions() } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось загрузить роли.' } finally { loading.value = false } }
async function save(): Promise<void> { saving.value = true; formError.value = ''; try { await saveRole(editing.value?.id ?? null, form.value); opened.value = false; await load() } catch (reason) { formError.value = reason instanceof Error ? reason.message : 'Не удалось сохранить роль.' } finally { saving.value = false } }
async function remove(): Promise<void> { if (!deleting.value) return; isDeleting.value = true; deleteError.value = ''; try { await deleteRole(deleting.value.id); deleting.value = null; await load() } catch (reason) { deleteError.value = reason instanceof Error ? reason.message : 'Не удалось удалить роль.' } finally { isDeleting.value = false } }
onMounted(load)
</script>

<template>
  <section class="mx-auto admin-page" :aria-busy="loading">
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4"><div><p class="text-sm font-medium text-gray-500">Управление доступом</p><h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">Роли</h1></div><button v-if="canManage" type="button" class="inline-flex items-center gap-2 rounded-lg bg-primary-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm" @click="open()"><Plus :size="18" />Добавить роль</button></div>
    <BaseAlert v-if="error" class="mb-4">{{ error }}</BaseAlert>
    <CollectionLoadingState v-if="loading" label="Загрузка ролей…" />
    <BaseEmptyState v-else-if="!error && roles.length === 0" label="Роли не найдены." />
    <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"><article v-for="role in roles" :key="role.id" class="rounded-xl border border-gray-200 bg-white p-5 shadow-card"><div class="flex gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-primary-50 text-primary-600"><ShieldCheck :size="20" /></span><div class="min-w-0 flex-1"><div class="flex items-start justify-between gap-2"><div><h2 class="font-semibold text-gray-700">{{ role.name }}</h2><p class="mt-0.5 text-xs text-gray-400">{{ role.slug }}</p></div><span v-if="role.is_system" class="admin-badge rounded-full bg-gray-50 px-2 py-1 text-xs font-medium text-gray-500">Системная</span></div><p class="mt-3 min-h-10 text-sm text-gray-500">{{ role.description || 'Без описания' }}</p></div></div><p class="mt-4 text-sm font-medium text-gray-600">Прав: {{ role.permissions.length }}</p><div v-if="canManage" class="mt-4 flex justify-end gap-2 border-t border-gray-100 pt-4"><button type="button" class="rounded-lg p-2 text-gray-500" :aria-label="`Редактировать роль ${role.name}`" @click="open(role)"><Pencil :size="17" /></button><button v-if="!role.is_system" type="button" class="rounded-lg p-2 text-error-500" :aria-label="`Удалить роль ${role.name}`" @click="deleting = role"><Trash2 :size="17" /></button></div></article></div>
    <BaseDialog :open="opened" labelledby="role-dialog-title" describedby="role-dialog-description" :close-disabled="saving" panel-class="w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl" @close="opened = false"><form @submit.prevent="save"><div class="flex items-start justify-between"><div><h2 id="role-dialog-title" class="text-lg font-bold text-gray-900">{{ title }}</h2><p id="role-dialog-description" class="mt-1 text-sm text-gray-500">Настройте роль и доступные ей права.</p></div><button type="button" class="rounded-lg p-1 text-gray-500" aria-label="Закрыть окно роли" :disabled="saving" @click="opened = false"><X :size="20" /></button></div><BaseAlert v-if="formError" class="mt-4">{{ formError }}</BaseAlert><div class="mt-6 grid gap-4"><div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium text-gray-700">Название<BaseInput v-model="form.name" class="mt-1.5" :disabled="editing?.is_system || saving" required /></label><label class="text-sm font-medium text-gray-700">Технический код<BaseInput v-model="form.slug" class="mt-1.5" :disabled="editing?.is_system || saving" required /></label></div><label class="text-sm font-medium text-gray-700">Описание<BaseTextarea v-model="form.description" class="mt-1.5 min-h-20 font-normal" :disabled="saving" /></label><fieldset :disabled="saving"><legend class="text-sm font-medium text-gray-700">Права</legend><div class="mt-2 grid gap-2 sm:grid-cols-2"><BaseCheckbox v-for="permission in permissions" :key="permission.id" v-model="form.permission_ids" :value="permission.id">{{ permission.name }} <span class="text-xs text-gray-400">({{ permission.code }})</span></BaseCheckbox></div></fieldset></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-600 disabled:opacity-60" :disabled="saving" @click="opened = false">Отмена</button><button class="rounded-lg bg-primary-500 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60" :disabled="saving">{{ saving ? 'Сохранение…' : 'Сохранить' }}</button></div></form></BaseDialog>
    <BaseConfirmDialog :open="Boolean(deleting)" title="Удалить роль?" :description="`Роль «${deleting?.name ?? ''}» будет удалена. Это действие нельзя отменить.`" :busy="isDeleting" :error="deleteError" @close="deleting = null" @confirm="remove" />
  </section>
</template>
