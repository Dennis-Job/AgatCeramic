<script setup lang="ts">
import UiTable from '../../../components/ui/UiTable.vue'
import { Pencil, Trash2 } from '@lucide/vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import type { Attribute } from '../types/attribute.types'
import type { AttributeGroup } from '../../attribute-groups/types/attributeGroup.types'
import { attributeTypeLabel } from '../../../utils/attributeTypes'
defineProps<{
  attributes: Attribute[]
  groups: AttributeGroup[]
  canManage: boolean
}>()
const emit = defineEmits<{
  edit: [attribute: Attribute]
  remove: [attribute: Attribute]
}>()
function groupName(
  attribute: Attribute,
  groups: AttributeGroup[],
): string | null {
  return attribute.attribute_group_id === null
    ? null
    : (groups.find((group) => group.id === attribute.attribute_group_id)
        ?.name ?? null)
}
</script>
<template>
  <UiTable
    v-if="attributes.length"
    full-bleed
    label="Список характеристик"
    min-width="min-w-[960px]"
    table-class="seller-table"
    sticky-header
    :sticky-edges="canManage"
  >
    <thead>
      <tr>
        <th scope="col">Характеристика</th>
        <th scope="col" class="w-52">Группа</th>
        <th scope="col" class="w-52">Тип / значения</th>
        <th scope="col" class="w-32">В фильтрах</th>
        <th v-if="canManage" scope="col" class="w-40">
          <span class="sr-only">Действия</span>
        </th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="attribute in attributes" :key="attribute.id">
        <td>
          <h2 class="font-semibold text-gray-500">
            {{ attribute.name
            }}<span v-if="attribute.unit" class="font-medium text-gray-500">
              ({{ attribute.unit }})</span
            >
          </h2>
          <p class="mt-1 text-xs text-gray-500">/{{ attribute.slug }}</p>
        </td>
        <td>{{ groupName(attribute, groups) ?? 'Без группы' }}</td>
        <td>
          <p>{{ attributeTypeLabel(attribute.type) }}</p>
          <p class="mt-1 text-xs text-gray-500">
            значений: {{ attribute.options.length }}
          </p>
        </td>
        <td>
          <UiBadge :tone="attribute.is_filterable ? 'success' : 'neutral'">{{
            attribute.is_filterable ? 'В фильтрах' : 'Нет'
          }}</UiBadge>
        </td>
        <td v-if="canManage">
          <div class="flex justify-end gap-1">
            <UiButton
              variant="primary-ghost"
              size="sm"
              :aria-label="`Редактировать характеристику ${attribute.name}`"
              @click="emit('edit', attribute)"
              ><Pencil :size="17" /></UiButton
            ><UiButton
              variant="danger-ghost"
              size="sm"
              :aria-label="`Удалить характеристику ${attribute.name}`"
              @click="emit('remove', attribute)"
              ><Trash2 :size="17"
            /></UiButton>
          </div>
        </td>
      </tr></tbody
  ></UiTable>
  <UiEmptyState
    v-else
    class="admin-container"
    label="Характеристик пока нет."
  />
</template>
