<script setup lang="ts">
const props = defineProps<{
  min: number
  max: number
  labelFrom: string
  labelTo: string
  disabled?: boolean
  formatValue?: (value: number) => string
}>()
const from = defineModel<number>('from', { required: true })
const to = defineModel<number>('to', { required: true })
const emit = defineEmits<{ change: [] }>()
const selectionStyle = computed(() => {
  const width = props.max - props.min
  const position = (value: number) =>
    width > 0
      ? Math.min(100, Math.max(0, ((value - props.min) / width) * 100))
      : 0
  return {
    left: `${position(from.value)}%`,
    right: `${100 - position(to.value)}%`,
  }
})
function updateFrom(event: Event) {
  const input = event.target as HTMLInputElement
  from.value = Math.min(Number(input.value), to.value)
  input.value = String(from.value)
  emit('change')
}
function updateTo(event: Event) {
  const input = event.target as HTMLInputElement
  to.value = Math.max(Number(input.value), from.value)
  input.value = String(to.value)
  emit('change')
}
</script>

<template>
  <div class="ui-range">
    <span class="ui-range__track" aria-hidden="true" />
    <span
      class="ui-range__selection"
      :style="selectionStyle"
      aria-hidden="true"
    />
    <input
      :class="{ 'ui-range__lower-front': from === to && from > min }"
      type="range"
      :aria-label="labelFrom"
      :min="min"
      :max="max"
      :aria-valuemax="to"
      :aria-valuetext="formatValue?.(from)"
      step="1"
      :value="from"
      :disabled="disabled"
      @input="updateFrom"
    />
    <input
      type="range"
      :aria-label="labelTo"
      :min="min"
      :max="max"
      :aria-valuemin="from"
      :aria-valuetext="formatValue?.(to)"
      step="1"
      :value="to"
      :disabled="disabled"
      @input="updateTo"
    />
  </div>
</template>

<style scoped>
.ui-range {
  position: relative;
  height: 44px;
  margin-inline: 9px;
}
.ui-range__track,
.ui-range__selection {
  position: absolute;
  inset: calc(50% - 1px) 0 auto;
  height: 2px;
  background: var(--color-line);
}
.ui-range__selection {
  background: var(--color-ink);
}
input {
  position: absolute;
  inset: 0 -9px;
  width: calc(100% + 18px);
  height: 44px;
  margin: 0;
  appearance: none;
  background: transparent;
  pointer-events: none;
}
.ui-range__lower-front {
  z-index: 1;
}
input::-webkit-slider-thumb {
  appearance: none;
  width: 18px;
  height: 18px;
  border: 1px solid var(--color-ink);
  border-radius: 50%;
  background: var(--color-bg-soft);
  pointer-events: auto;
  cursor: grab;
}
input::-moz-range-thumb {
  width: 16px;
  height: 16px;
  border: 1px solid var(--color-ink);
  border-radius: 50%;
  background: var(--color-bg-soft);
  pointer-events: auto;
  cursor: grab;
}
input:disabled {
  opacity: 0.55;
}
input:disabled::-webkit-slider-thumb {
  pointer-events: none;
}
input:disabled::-moz-range-thumb {
  pointer-events: none;
}
@media (pointer: coarse) {
  .ui-range {
    margin-inline: 22px;
  }
  input {
    inset-inline: -22px;
    width: calc(100% + 44px);
  }
  input::-webkit-slider-thumb {
    width: 44px;
    height: 44px;
    border: 13px solid transparent;
    background-clip: padding-box;
    box-shadow: inset 0 0 0 1px var(--color-ink);
  }
  input::-moz-range-thumb {
    box-sizing: border-box;
    width: 44px;
    height: 44px;
    border: 13px solid transparent;
    background-clip: padding-box;
    box-shadow: inset 0 0 0 1px var(--color-ink);
  }
}
@media (forced-colors: active) {
  .ui-range__track {
    background: GrayText;
  }
  .ui-range__selection {
    background: CanvasText;
  }
  input::-webkit-slider-thumb {
    border: 1px solid ButtonText;
    background: Canvas;
  }
  input::-moz-range-thumb {
    border: 1px solid ButtonText;
    background: Canvas;
  }
}
</style>
