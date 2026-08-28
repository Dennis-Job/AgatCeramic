<script setup lang="ts">
import { computed } from 'vue'
import type { Attribute } from '../services/attributes'
import BaseCheckbox from './BaseCheckbox.vue'
import BaseDatePicker from './BaseDatePicker.vue'
import BaseInput from './BaseInput.vue'
import BaseSelect from './BaseSelect.vue'
import BaseTextarea from './BaseTextarea.vue'
import { sortByLabel } from '../utils/alphabetical'

export type AttributeDraftValue = string | string[]

const props = defineProps<{
  attribute: Attribute
  modelValue: AttributeDraftValue
  accessibleName?: string
  required?: boolean
}>()

const emit = defineEmits<{ 'update:modelValue': [value: AttributeDraftValue] }>()
const stringValue = computed(() => typeof props.modelValue === 'string' ? props.modelValue : '')
const selectedValues = computed({
  get: () => Array.isArray(props.modelValue) ? props.modelValue : [],
  set: (value: Array<number | string>) => emit('update:modelValue', value.map(String)),
})
const options = computed(() => sortByLabel(props.attribute.options.map(option => ({ value: option.value, label: option.label }))))
const label = computed(() => props.accessibleName ?? props.attribute.name)
</script>

<template>
  <div>
  <BaseInput v-if="attribute.type === 'string'" :model-value="stringValue" maxlength="255" :required="required" :aria-label="label" @update:model-value="emit('update:modelValue', $event)" />
  <BaseTextarea v-else-if="attribute.type === 'text'" :model-value="stringValue" maxlength="10000" :required="required" :aria-label="label" @update:model-value="emit('update:modelValue', $event)" />
  <BaseInput v-else-if="attribute.type === 'integer'" :model-value="stringValue" type="number" inputmode="numeric" step="1" :required="required" :aria-label="label" @update:model-value="emit('update:modelValue', $event)" />
  <BaseInput v-else-if="attribute.type === 'decimal'" :model-value="stringValue" type="number" inputmode="decimal" step="any" :required="required" :aria-label="label" @update:model-value="emit('update:modelValue', $event)" />
  <BaseCheckbox v-else-if="attribute.type === 'boolean'" :checked="stringValue === 'true'" mode="boolean" @update:checked="emit('update:modelValue', $event ? 'true' : 'false')">Да</BaseCheckbox>
  <BaseSelect v-else-if="attribute.type === 'select'" :model-value="stringValue" :options="options" :required="required" :accessible-name="label" @update:model-value="emit('update:modelValue', $event)" />
  <div v-else-if="attribute.type === 'multiselect'" class="grid gap-2" role="group" :aria-label="label">
    <BaseCheckbox v-for="option in options" :key="option.value" v-model="selectedValues" :value="option.value">{{ option.label }}</BaseCheckbox>
  </div>
  <BaseDatePicker v-else :model-value="stringValue" :accessible-name="label" @update:model-value="emit('update:modelValue', $event)" />
  </div>
</template>
