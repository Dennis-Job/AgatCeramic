<script setup lang="ts">
import { Minus, Plus } from '@lucide/vue'
const props = withDefaults(
  defineProps<{
    modelValue: number
    label: string
    min?: number
    max?: number
    disabled?: boolean
    compact?: boolean
  }>(),
  { min: 1, max: 9999 },
)
const emit = defineEmits<{
  'update:modelValue': [value: number]
  commit: [value: number]
  invalid: []
}>()
const id = useId()
const draft = ref(String(props.modelValue))
watch(
  () => props.modelValue,
  (value) => {
    draft.value = Number.isFinite(value) ? String(value) : ''
  },
)
function value(): number {
  return Number(draft.value)
}
function valid(): boolean {
  return (
    draft.value.trim() !== '' &&
    Number.isInteger(value()) &&
    value() >= props.min &&
    value() <= props.max
  )
}
function input(event: Event) {
  draft.value = (event.target as HTMLInputElement).value
  emit('update:modelValue', draft.value === '' ? Number.NaN : value())
}
function commit() {
  if (valid()) emit('commit', value())
  else emit('invalid')
}
function step(delta: number) {
  const next = Math.min(
    props.max,
    Math.max(props.min, valid() ? value() + delta : props.min),
  )
  draft.value = String(next)
  emit('update:modelValue', next)
  emit('commit', next)
}
</script>
<template>
  <div
    class="ui-quantity"
    :class="{ 'is-disabled': disabled, 'ui-quantity--compact': compact }"
  >
    <label :for="id" class="visually-hidden">{{ label }}</label>
    <button
      type="button"
      :disabled="disabled || (valid() && value() <= min)"
      :aria-label="'Уменьшить количество ' + label"
      @click="step(-1)"
    >
      <Minus :size="14" :stroke-width="1.6" aria-hidden="true" />
    </button>
    <input
      :id="id"
      :value="draft"
      type="number"
      inputmode="numeric"
      :min="min"
      :max="max"
      step="1"
      required
      :disabled="disabled"
      :aria-invalid="!valid() || undefined"
      @input="input"
      @change="commit"
    />
    <button
      type="button"
      :disabled="disabled || (valid() && value() >= max)"
      :aria-label="'Увеличить количество ' + label"
      @click="step(1)"
    >
      <Plus :size="14" :stroke-width="1.6" aria-hidden="true" />
    </button>
  </div>
</template>
<style scoped>
.ui-quantity {
  display: flex;
  align-items: stretch;
  min-width: 0;
  width: 165px;
  border: 1px solid var(--color-line);
  background: var(--color-bg-soft);
  transition: border-color 0.3s;
}
.ui-quantity:focus-within {
  border-color: var(--color-ink);
}
button {
  display: grid;
  place-items: center;
  width: 44px;
  min-height: 50px;
  flex: none;
  border: 0;
  padding: 0;
  background: transparent;
  color: var(--color-ink);
  transition: background 0.3s;
}
button:hover:not(:disabled) {
  background: var(--color-beige);
}
button:disabled,
.is-disabled {
  opacity: 0.55;
}
input {
  width: 0;
  min-width: 0;
  flex: 1;
  padding: 8px 0;
  border: 0;
  border-radius: 0;
  background: transparent;
  color: var(--color-ink);
  font: inherit;
  font-size: 14px;
  text-align: center;
  font-variant-numeric: tabular-nums;
  appearance: textfield;
  outline: none;
  box-shadow: none;
}
input::-webkit-inner-spin-button,
input::-webkit-outer-spin-button {
  appearance: none;
  margin: 0;
}
.ui-quantity--compact {
  width: max-content;
  max-width: 100%;
  align-items: center;
  gap: 8px;
  border: 0;
  background: transparent;
}
.ui-quantity--compact button {
  width: 24px;
  min-height: 24px;
  border: 1px solid var(--color-line);
  transition:
    background-color 0.25s,
    border-color 0.25s,
    color 0.25s;
}
.ui-quantity--compact button:hover:not(:disabled) {
  border-color: var(--color-ink);
  background: var(--color-ink);
  color: var(--color-white);
}
.ui-quantity--compact input {
  width: 36px;
  min-height: 24px;
  padding: 0;
  font-size: 13px;
}
.ui-quantity--compact input:focus-visible {
  outline: 2px solid var(--color-ink);
  outline-offset: 1px;
}
@media (pointer: coarse) {
  .ui-quantity--compact button,
  .ui-quantity--compact input {
    min-height: 32px;
  }
}
</style>
