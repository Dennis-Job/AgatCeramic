<script setup lang="ts">
import UiCheckbox from '~/components/ui/UiCheckbox.vue'
import UiColorSwatch from '~/components/ui/UiColorSwatch.vue'
import { hexColor } from '~/utils/hexColor'
import type { AttributeFilter, CatalogAttribute } from '../types/catalog'

const props = defineProps<{ attribute: CatalogAttribute }>()
const model = defineModel<AttributeFilter>()
const emit = defineEmits<{ change: [delayed: boolean] }>()
const value = computed(() => model.value || {})
const colors = computed(
  () =>
    ['tsvet', 'color'].includes(props.attribute.slug) &&
    ['select', 'multiselect'].includes(props.attribute.type),
)
const title = computed(
  () =>
    props.attribute.name +
    (props.attribute.unit ? ' · ' + props.attribute.unit : ''),
)
const options = computed(() => {
  const options = props.attribute.options
  return [
    ...options,
    ...(value.value.values || [])
      .filter((value) => !options.some((option) => option.value === value))
      .map((value) => ({
        value,
        label:
          props.attribute.type === 'boolean'
            ? value === 'true'
              ? 'Да'
              : value === 'false'
                ? 'Нет'
                : value
            : value,
      })),
  ]
})
function toggle(option: string, checked: boolean) {
  const values = (value.value.values || []).filter((value) => value !== option)
  if (checked) values.push(option)
  model.value = { values }
  emit('change', false)
}
</script>

<template>
  <fieldset class="attribute-filter">
    <legend>{{ title }}</legend>
    <div :class="{ 'attribute-filter__colors': colors }">
      <template v-for="option in options" :key="option.value">
        <UiColorSwatch
          v-if="colors && hexColor(option.value)"
          :color="option.value"
          :label="option.label"
          :title="option.label"
          :model-value="value.values?.includes(option.value) || false"
          @update:model-value="toggle(option.value, $event)"
        />
        <UiCheckbox
          v-else
          :label="option.label"
          :title="option.label"
          size="compact"
          :model-value="value.values?.includes(option.value) || false"
          @update:model-value="toggle(option.value, $event)"
        />
      </template>
    </div>
    <p v-if="!options.length" class="attribute-filter__empty">
      Нет доступных значений
    </p>
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
.attribute-filter__colors {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.attribute-filter__empty {
  color: var(--color-muted);
  font-size: 13px;
}
</style>
