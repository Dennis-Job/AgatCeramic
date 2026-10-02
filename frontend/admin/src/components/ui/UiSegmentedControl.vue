<script setup lang="ts">
import UiBadge from './UiBadge.vue'
import { useId } from 'vue'

const countId = useId()

defineProps<{
  modelValue: number | string
  name: string
  label: string
  hideLabel?: boolean
  countsLoading?: boolean
  options: ReadonlyArray<{
    value: number | string
    label: string
    disabled?: boolean
    count?: number | null
  }>
  disabled?: boolean
}>()
defineEmits<{ 'update:modelValue': [value: number | string] }>()
</script>

<template>
  <fieldset class="min-w-0" :disabled="disabled">
    <legend
      :class="
        hideLabel ? 'sr-only' : 'mb-2 text-sm font-semibold text-gray-700'
      "
    >
      {{ label }}
    </legend>
    <div class="flex min-w-0 gap-1 rounded-lg bg-gray-100 p-1">
      <label
        v-for="(option, index) in options"
        :key="option.value"
        class="admin-choice-focus flex min-h-[var(--admin-control-height-md)] min-w-0 flex-auto basis-[min-content] items-center justify-center rounded-md border px-1.5 py-1.5 text-center text-sm font-semibold transition"
        :class="[
          disabled || option.disabled
            ? 'cursor-not-allowed text-gray-500'
            : 'cursor-pointer hover:text-primary-600',
          modelValue === option.value
            ? disabled || option.disabled
              ? 'border-gray-300 bg-gray-50'
              : 'border-gray-200 bg-white text-primary-600'
            : 'border-transparent text-gray-500',
        ]"
      >
        <input
          class="sr-only"
          type="radio"
          :name="name"
          :value="option.value"
          :aria-label="option.label"
          :aria-describedby="
            option.count !== undefined ? `${countId}-${index}` : undefined
          "
          :checked="modelValue === option.value"
          :disabled="disabled || option.disabled"
          @change="$emit('update:modelValue', option.value)"
        />
        <span class="flex min-w-0 flex-wrap items-center justify-center gap-1">
          <span class="min-w-0 break-words">{{ option.label }}</span>
          <UiBadge
            v-if="option.count !== undefined"
            :id="`${countId}-${index}`"
            :aria-label="
              countsLoading
                ? 'Обновляем количество товаров'
                : option.count === null
                  ? 'Количество недоступно'
                  : `Количество товаров: ${option.count}`
            "
            >{{
              countsLoading
                ? '…'
                : option.count === null
                  ? '—'
                  : option.count.toLocaleString('ru-RU')
            }}</UiBadge
          >
        </span>
      </label>
    </div>
  </fieldset>
</template>

<style scoped>
@media (forced-colors: active) {
  label {
    transition: none;
  }
  label:has(input:checked) {
    forced-color-adjust: none;
    border-color: Highlight;
    background: Highlight;
    color: HighlightText;
  }
  label:has(input:disabled) {
    forced-color-adjust: none;
    background: Canvas;
    color: GrayText;
  }
  label:has(input:checked:disabled) {
    border-color: GrayText;
  }
}
</style>
