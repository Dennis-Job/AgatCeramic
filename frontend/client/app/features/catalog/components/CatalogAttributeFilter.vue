<script setup lang="ts">
import UiCheckbox from '~/components/ui/UiCheckbox.vue'
import UiColorSwatch from '~/components/ui/UiColorSwatch.vue'
import UiTextField from '~/components/ui/UiTextField.vue'
import { hexColor } from '~/utils/hexColor'
import type { AttributeFilter, CatalogAttribute } from '../types/catalog'

const props = defineProps<{ attribute: CatalogAttribute }>()
const model = defineModel<AttributeFilter>()
const value = computed(() => model.value || {})
const choices = computed(
  () =>
    ['select', 'multiselect', 'boolean'].includes(props.attribute.type) ||
    (props.attribute.slug === 'razmer' &&
      ['string', 'text'].includes(props.attribute.type)),
)
const colors = computed(
  () =>
    ['tsvet', 'color'].includes(props.attribute.slug) &&
    ['select', 'multiselect'].includes(props.attribute.type),
)
const range = computed(() =>
  ['integer', 'decimal', 'date'].includes(props.attribute.type),
)
const title = computed(
  () =>
    props.attribute.name +
    (props.attribute.unit ? ' · ' + props.attribute.unit : ''),
)
const options = computed(() => {
  const options =
    props.attribute.type === 'boolean'
      ? [
          { value: 'true', label: 'Да' },
          { value: 'false', label: 'Нет' },
        ]
      : props.attribute.options
  return [
    ...options,
    ...(value.value.values || [])
      .filter((value) => !options.some((option) => option.value === value))
      .map((value) => ({ value, label: value })),
  ]
})
function toggle(option: string, checked: boolean) {
  const values = (value.value.values || []).filter((value) => value !== option)
  if (checked) values.push(option)
  model.value = { values }
}
function bound(key: 'min' | 'max' | 'text', text: string) {
  model.value = { ...value.value, [key]: text }
}
</script>

<template>
  <fieldset class="attribute-filter">
    <legend>{{ title }}</legend>
    <template v-if="choices">
      <div :class="{ 'attribute-filter__colors': colors }">
        <template v-for="option in options" :key="option.value">
          <UiColorSwatch
            v-if="colors && hexColor(option.value)"
            :color="option.value"
            :label="option.label"
            :model-value="value.values?.includes(option.value) || false"
            @update:model-value="toggle(option.value, $event)"
          />
          <UiCheckbox
            v-else
            :label="option.label"
            size="compact"
            :model-value="value.values?.includes(option.value) || false"
            @update:model-value="toggle(option.value, $event)"
          />
        </template>
      </div>
      <p v-if="!options.length" class="attribute-filter__empty">
        Нет доступных значений
      </p>
    </template>
    <div
      v-else-if="range"
      class="attribute-filter__range"
      :class="{ 'attribute-filter__range--date': attribute.type === 'date' }"
    >
      <UiTextField
        :label="attribute.name + ' от'"
        size="compact"
        :type="attribute.type === 'date' ? 'date' : 'number'"
        :step="attribute.type === 'integer' ? '1' : 'any'"
        :model-value="value.min || ''"
        @update:model-value="bound('min', $event)"
      />
      <UiTextField
        :label="attribute.name + ' до'"
        size="compact"
        :type="attribute.type === 'date' ? 'date' : 'number'"
        :step="attribute.type === 'integer' ? '1' : 'any'"
        :model-value="value.max || ''"
        @update:model-value="bound('max', $event)"
      />
    </div>
    <UiTextField
      v-else
      :label="attribute.name"
      size="compact"
      :maxlength="200"
      :model-value="value.text || ''"
      @update:model-value="bound('text', $event)"
    />
  </fieldset>
</template>

<style scoped>
.attribute-filter {
  min-width: 0;
  margin: 0;
  border: 0;
  border-top: 1px solid var(--color-line);
  padding: 16px 0 0;
}
legend {
  float: left;
  width: 100%;
  margin-bottom: 8px;
  padding: 0;
  font-size: 13px;
  font-weight: 500;
  overflow-wrap: anywhere;
}
legend + * {
  clear: both;
}
.attribute-filter__range {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}
.attribute-filter__colors {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.attribute-filter__range--date {
  grid-template-columns: minmax(0, 1fr);
}
.attribute-filter__empty {
  color: var(--color-muted);
  font-size: 13px;
}
</style>
