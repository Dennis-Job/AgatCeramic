<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Layers3, Pencil, Plus, Trash2 } from '@lucide/vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiPagination from '../../../components/ui/UiPagination.vue'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import { useAuthStore } from '../../../stores/auth'
import { useAttributeGroupsCatalog } from '../composables/useAttributeGroupsCatalog'
import { useAttributeGroupForm } from '../composables/useAttributeGroupForm'
import type { AttributeGroup } from '../types/attributeGroup.types'
import AttributeGroupFormDialog from './AttributeGroupFormDialog.vue'

const auth = useAuthStore()
const { groups, pagination, error, loading, load, remove, save } = useAttributeGroupsCatalog()
const { open, title, busy: saving, error: formError, form, show, close, updateName, updateSlug, submit } = useAttributeGroupForm(save, load)
const deleting = ref<AttributeGroup | null>(null)
const deletingBusy = ref(false)
const canManage = computed(() => auth.hasPermission('catalog.manage'))
async function confirmDelete(): Promise<void> { if (!deleting.value) return; deletingBusy.value = true; try { await remove(deleting.value.id); deleting.value = null } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось удалить группу.' } finally { deletingBusy.value = false } }
onMounted(load)
</script>
<template><section class="mx-auto admin-page" :aria-busy="loading"><PageHeader class="mb-7" eyebrow="Каталог" title="Группы характеристик"><template #actions><UiButton v-if="canManage" @click="show()"><Plus :size="18" />Добавить группу</UiButton></template></PageHeader><UiAlert v-if="error" class="mb-4">{{ error }}</UiAlert><UiCard class="overflow-hidden"><UiLoadingState v-if="loading" label="Загрузка групп характеристик…" /><div v-else-if="groups.length" class="divide-y divide-gray-100"><article v-for="group in groups" :key="group.id" class="flex flex-wrap items-start gap-3 p-4 sm:flex-nowrap sm:items-center sm:gap-4"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-primary-50 text-primary-600"><Layers3 :size="20" /></span><div class="min-w-0 flex-[1_1_calc(100%-52px)] sm:flex-1"><h2 class="break-words font-semibold text-gray-800">{{ group.name }}</h2><p class="mt-1 break-words text-sm text-gray-500">/{{ group.slug }} · Порядок: {{ group.sort_order }}</p><p v-if="group.description" class="mt-1 break-words text-sm text-gray-500">{{ group.description }}</p></div><div v-if="canManage" class="ml-[52px] flex gap-1 sm:ml-0"><UiButton variant="ghost" size="sm" :aria-label="`Редактировать группу ${group.name}`" @click="show(group)"><Pencil :size="17" /></UiButton><UiButton variant="ghost" size="sm" :aria-label="`Удалить группу ${group.name}`" @click="deleting = group"><Trash2 :size="17" /></UiButton></div></article></div><UiEmptyState v-else label="Групп пока нет." /></UiCard><UiPagination v-if="pagination" :meta="pagination" :loading="loading" @change="load" /><AttributeGroupFormDialog :open="open" :title="title" :busy="saving" :error="formError" :form="form" @close="close" @submit="submit" @update-name="updateName" @update-slug="updateSlug" /><ConfirmDialog :open="Boolean(deleting)" title="Удалить группу?" :description="`Группа «${deleting?.name ?? ''}» будет удалена. Это действие нельзя отменить.`" :busy="deletingBusy" @close="deleting = null" @confirm="confirmDelete" /></section></template>
