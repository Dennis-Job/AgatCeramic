<script setup lang="ts">
import { useId } from 'vue'

const countId = useId()

defineProps<{
  modelValue: number | string
  name: string
  label: string
  hideLabel?: boolean
  fitContent?: boolean
  idPrefix?: string
  countsLoading?: boolean
  options: ReadonlyArray<{
    value: number | string
    label: string
    disabled?: boolean
    count?: number | null
    panelId?: string
  }>
  disabled?: boolean
}>()
defineEmits<{ 'update:modelValue': [value: number | string] }>()

function handleKeydown(event: KeyboardEvent) {
  if (event.key !== 'Home' && event.key !== 'End') return
  const radios = (
    event.currentTarget as HTMLElement
  ).querySelectorAll<HTMLInputElement>('input[type="radio"]:not(:disabled)')
  const target =
    event.key === 'Home' ? radios.item(0) : radios.item(radios.length - 1)
  if (!target) return
  event.preventDefault()
  target.click()
  target.focus()
}
</script>

<template>
  <fieldset
    class="min-w-0"
    :class="{ 'w-fit max-w-full': fitContent }"
    :disabled="disabled"
    @keydown="handleKeydown"
  >
    <legend
      :class="
        hideLabel ? 'sr-only' : 'mb-2 text-sm font-semibold text-gray-500'
      "
    >
      {{ label }}
    </legend>
    <div class="flex min-w-0 gap-1 rounded-xl bg-gray-100 p-1">
      <label
        v-for="(option, index) in options"
        :key="option.value"
        class="admin-choice-focus flex min-h-[var(--admin-control-height-md)] min-w-0 items-center justify-center rounded-lg border-b-2 px-1.5 py-1.5 text-center text-sm transition sm:px-3"
        :class="[
          fitContent ? 'flex-initial' : 'flex-auto basis-[min-content]',
          disabled || option.disabled
            ? 'cursor-not-allowed text-gray-500'
            : 'cursor-pointer hover:text-primary-500',
          modelValue === option.value
            ? disabled || option.disabled
              ? 'border-gray-300 bg-gray-50'
              : 'border-primary-500 bg-white font-semibold text-primary-500'
            : 'border-transparent font-normal text-gray-500',
        ]"
      >
        <input
          :id="idPrefix ? `${idPrefix}-${option.value}` : undefined"
          class="sr-only"
          type="radio"
          :name="name"
          :value="option.value"
          :aria-label="option.label"
          :aria-controls="option.panelId"
          :aria-describedby="
            option.count !== undefined ? `${countId}-${index}` : undefined
          "
          :checked="modelValue === option.value"
          :disabled="disabled || option.disabled"
          @change="$emit('update:modelValue', option.value)"
        />
        <span class="flex min-w-0 flex-wrap items-center justify-center gap-2">
          <span class="min-w-0 break-words">{{ option.label }}</span>
          <span
            v-if="option.count !== undefined"
            :id="`${countId}-${index}`"
            class="tabular-nums"
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
            }}</span
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
