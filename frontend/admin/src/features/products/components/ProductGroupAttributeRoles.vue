<script setup lang="ts">
import { computed, useId } from 'vue'
import UiField from '../../../components/ui/UiField.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import type { Attribute } from '../../attributes/types/attribute.types'

const props = defineProps<{
  attributes: Attribute[]
  axisAttributeIds: number[]
  individualAttributeIds: number[]
  errors: Record<string, string[]>
  disabled: boolean
}>()
const emit = defineEmits<{
  'update:axisAttributeIds': [ids: number[]]
  'update:individualAttributeIds': [ids: number[]]
}>()
const helpId = useId()
const roleOptions = [
  { value: 'shared', label: 'Общая' },
  { value: 'axis', label: 'Для выбора варианта' },
  { value: 'individual', label: 'Индивидуальная' },
]
const roleErrors = computed(() =>
  Object.entries(props.errors)
    .filter(([key]) => /^(axis|individual)_attribute_ids(?:\.|$)/.test(key))
    .flatMap(([, messages]) => messages),
)
function role(id: number): string {
  if (props.axisAttributeIds.includes(id)) return 'axis'
  return props.individualAttributeIds.includes(id) ? 'individual' : 'shared'
}
function setRole(id: number, next: string): void {
  const axes = props.axisAttributeIds.filter((value) => value !== id)
  const individual = props.individualAttributeIds.filter(
    (value) => value !== id,
  )
  if (next === 'axis') axes.push(id)
  if (next === 'individual') individual.push(id)
  emit('update:axisAttributeIds', axes)
  emit('update:individualAttributeIds', individual)
}
</script>

<template>
  <fieldset :disabled="disabled" :aria-describedby="helpId">
    <legend class="sr-only">Роли характеристик</legend>
    <div :id="helpId" class="mb-4 space-y-1 text-sm text-gray-500">
      <p>
        Для выбора варианта — параметры, по которым покупатель переключается
        между товарами, например цвет и размер плитки.
      </p>
      <p>
        Индивидуальная — может отличаться у каждого товара, например м²,
        количество штук и габариты упаковки. В выборе варианта не участвует.
      </p>
      <p>
        Общая — одинакова у всех товаров группы. Изменение или очистка значения
        применяется ко всей группе.
      </p>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
      <UiField
        v-for="attribute in attributes"
        :key="attribute.id"
        :label="attribute.name"
      >
        <UiSelect
          :model-value="role(attribute.id)"
          :options="
            roleOptions.filter(
              (option) =>
                option.value !== 'axis' ||
                !['text', 'multiselect'].includes(attribute.type),
            )
          "
          :accessible-name="`Роль: ${attribute.name}`"
          :description-id="helpId"
          :disabled="disabled"
          teleport-menu
          class="mt-1.5"
          @update:model-value="setRole(attribute.id, $event)"
        />
      </UiField>
    </div>
    <p
      v-for="message in roleErrors"
      :key="message"
      role="alert"
      class="mt-2 text-sm text-error-500"
    >
      {{ message }}
    </p>
    <UiEmptyState
      v-if="!attributes.length"
      label="Для этой категории характеристики пока не назначены."
    />
  </fieldset>
</template>
