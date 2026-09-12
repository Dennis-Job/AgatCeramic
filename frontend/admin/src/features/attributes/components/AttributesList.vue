<script setup lang="ts">
import { ListFilter, Pencil, Trash2 } from '@lucide/vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import type { Attribute } from '../types/attribute.types'
import type { AttributeGroup } from '../../attribute-groups/types/attributeGroup.types'
import { attributeTypeLabel } from '../../../utils/attributeTypes'
defineProps<{ attributes: Attribute[]; groups: AttributeGroup[]; canManage: boolean }>()
const emit = defineEmits<{ edit: [attribute: Attribute]; remove: [attribute: Attribute] }>()
function groupName(attribute: Attribute, groups: AttributeGroup[]): string | null { return attribute.attribute_group_id === null ? null : groups.find(group => group.id === attribute.attribute_group_id)?.name ?? null }
</script>
<template><div v-if="attributes.length" class="divide-y divide-gray-100"><article v-for="attribute in attributes" :key="attribute.id" class="flex flex-wrap items-start gap-3 p-4 sm:flex-nowrap sm:items-center sm:gap-4"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-primary-50 text-primary-600"><ListFilter :size="20" /></span><div class="min-w-0 flex-[1_1_calc(100%-52px)] sm:flex-1"><div class="flex flex-wrap items-center gap-2"><h2 class="font-semibold text-gray-800">{{ attribute.name }}<span v-if="attribute.unit" class="font-medium text-gray-500"> ({{ attribute.unit }})</span></h2><UiBadge v-if="groupName(attribute, groups)" tone="primary">Группа: {{ groupName(attribute, groups) }}</UiBadge><UiBadge v-if="attribute.is_filterable" tone="success">В фильтрах</UiBadge></div><p class="mt-0.5 text-sm text-gray-500">{{ attributeTypeLabel(attribute.type) }} · /{{ attribute.slug }} · значений: {{ attribute.options.length }}</p></div><div v-if="canManage" class="ml-[52px] flex gap-1 sm:ml-0"><UiButton variant="ghost" size="sm" :aria-label="`Редактировать характеристику ${attribute.name}`" @click="emit('edit', attribute)"><Pencil :size="17" /></UiButton><UiButton variant="ghost" size="sm" :aria-label="`Удалить характеристику ${attribute.name}`" @click="emit('remove', attribute)"><Trash2 :size="17" /></UiButton></div></article></div><UiEmptyState v-else label="Характеристик пока нет." /></template>
