<script setup lang="ts">
import { Pencil, Trash2 } from '@lucide/vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiSwitch from '../../../components/ui/UiSwitch.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import type { Attribute, AttributeDisplayField } from '../types/attribute.types'
import type { AttributeGroup } from '../../attribute-groups/types/attributeGroup.types'
import { attributeTypeLabel } from '../../../utils/attributeTypes'
defineProps<{
  attributes: Attribute[]
  groups: AttributeGroup[]
  canManage: boolean
  pendingIds: Set<number>
  showGroup: boolean
  emptyLabel: string
}>()
const emit = defineEmits<{
  edit: [attribute: Attribute]
  remove: [attribute: Attribute]
  toggle: [attribute: Attribute, field: AttributeDisplayField, value: boolean]
}>()
</script>
<template>
  <div v-if="attributes.length" class="attributes-list">
    <div class="attribute-columns text-sm text-gray-500" aria-hidden="true">
      <span></span><span></span><span>В фильтрах</span
      ><span>В карточке товара</span><span></span>
    </div>
    <article
      v-for="attribute in attributes"
      :key="attribute.id"
      class="attribute-row"
      :aria-label="`Характеристика ${attribute.name}`"
      :aria-busy="pendingIds.has(attribute.id)"
    >
      <div class="attribute-name min-w-0">
        <h3
          class="break-words text-lg font-bold text-gray-500 [overflow-wrap:anywhere]"
        >
          {{ attribute.name }}
        </h3>
        <p class="mt-1 break-words text-sm text-gray-500">
          /{{ attribute.slug }}
        </p>
        <p v-if="showGroup" class="mt-2 break-words text-xs text-gray-500">
          Группа:
          {{
            groups.find((group) => group.id === attribute.attribute_group_id)
              ?.name ?? 'Без группы'
          }}
        </p>
      </div>
      <div class="attribute-meta min-w-0 break-words text-sm text-gray-500">
        <p>Тип: {{ attributeTypeLabel(attribute.type) }}</p>
        <p>Единица: {{ attribute.unit || 'Не задана' }}</p>
        <p>Порядок: {{ attribute.sort_order }}</p>
        <template v-if="['select', 'multiselect'].includes(attribute.type)"
          ><p class="mt-2">Значений: {{ attribute.options.length }}</p>
          <p class="break-words">
            {{
              attribute.options.map((option) => option.label).join(' · ') ||
              'Нет вариантов'
            }}
          </p></template
        >
      </div>
      <div class="attribute-setting">
        <span class="attribute-setting-label text-sm text-gray-500"
          >В фильтрах</span
        ><UiSwitch
          :checked="attribute.is_filterable"
          :accessible-name="`Участие в фильтрах: ${attribute.name}`"
          :disabled="!canManage || pendingIds.has(attribute.id)"
          :busy="pendingIds.has(attribute.id)"
          @update:checked="emit('toggle', attribute, 'is_filterable', $event)"
        />
      </div>
      <div class="attribute-setting">
        <span class="attribute-setting-label text-sm text-gray-500"
          >В карточке товара</span
        ><UiSwitch
          :checked="attribute.is_visible_on_product_page"
          :accessible-name="`Отображение в карточке товара: ${attribute.name}`"
          :disabled="!canManage || pendingIds.has(attribute.id)"
          :busy="pendingIds.has(attribute.id)"
          @update:checked="
            emit('toggle', attribute, 'is_visible_on_product_page', $event)
          "
        />
      </div>
      <div v-if="canManage" class="attribute-actions flex gap-1">
        <UiButton
          variant="primary-ghost"
          size="sm"
          :disabled="pendingIds.has(attribute.id)"
          :aria-label="`Редактировать характеристику ${attribute.name}`"
          tooltip="Редактировать"
          @click="emit('edit', attribute)"
          ><Pencil :size="17" /></UiButton
        ><UiButton
          variant="danger-ghost"
          size="sm"
          :disabled="pendingIds.has(attribute.id)"
          :aria-label="`Удалить характеристику ${attribute.name}`"
          tooltip="Удалить"
          @click="emit('remove', attribute)"
          ><Trash2 :size="17"
        /></UiButton>
      </div>
    </article>
  </div>
  <UiEmptyState v-else :label="emptyLabel" />
</template>
<style scoped>
.attribute-columns {
  display: none;
}
.attribute-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--admin-spacing-3);
  padding-block: var(--admin-spacing-5);
  border-top: var(--admin-border-width) solid var(--admin-color-gray-200);
}
.attribute-setting {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--admin-spacing-2);
}
.attribute-actions {
  justify-content: end;
}
@media (min-width: 640px) {
  .attribute-row {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .attribute-actions {
    grid-column: 1 / -1;
  }
  .attribute-setting {
    justify-content: start;
  }
}
@media (min-width: 1280px) {
  .attribute-row,
  .attribute-columns {
    grid-template-columns:
      minmax(140px, 1fr) minmax(165px, 1fr)
      90px 140px 88px;
    column-gap: var(--admin-spacing-4);
  }
  .attribute-columns {
    display: grid;
    padding-block: var(--admin-spacing-3);
    text-align: center;
  }
  .attribute-row {
    align-items: start;
  }
  .attribute-setting {
    justify-content: center;
  }
  .attribute-setting-label {
    display: none;
  }
  .attribute-actions {
    grid-column: auto;
  }
}
</style>
