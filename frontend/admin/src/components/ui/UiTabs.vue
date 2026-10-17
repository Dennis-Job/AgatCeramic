<script setup lang="ts">
import { ref } from 'vue'

const props = defineProps<{
  id: string
  label: string
  items: { value: string; label: string; disabled?: boolean }[]
}>()
const model = defineModel<string>({ required: true })
const root = ref<HTMLElement | null>(null)
function navigate(event: KeyboardEvent): void {
  if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return
  const buttons = [
    ...(root.value?.querySelectorAll<HTMLButtonElement>(
      'button:not(:disabled)',
    ) ?? []),
  ]
  const current = buttons.indexOf(event.target as HTMLButtonElement)
  if (current < 0 || !buttons.length) return
  event.preventDefault()
  const next =
    event.key === 'Home'
      ? 0
      : event.key === 'End'
        ? buttons.length - 1
        : (current + (event.key === 'ArrowRight' ? 1 : -1) + buttons.length) %
          buttons.length
  const button = buttons[next]!
  model.value = button.dataset.value!
  button.focus()
}
</script>

<template>
  <div
    ref="root"
    class="ui-tabs"
    role="tablist"
    :aria-label="props.label"
    @keydown="navigate"
  >
    <button
      v-for="item in items"
      :id="`${id}-${item.value}`"
      :key="item.value"
      type="button"
      role="tab"
      class="ui-tab admin-focus"
      :data-value="item.value"
      :aria-controls="`${id}-${item.value}-panel`"
      :aria-selected="model === item.value"
      :tabindex="model === item.value ? 0 : -1"
      :disabled="item.disabled"
      @click="model = item.value"
    >
      {{ item.label }}
    </button>
  </div>
</template>

<style scoped>
.ui-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: var(--admin-spacing-2);
  border-bottom: var(--admin-border-width) solid var(--admin-color-gray-200);
  background: var(--admin-color-gray-50);
}
.ui-tab {
  min-height: var(--admin-control-height-lg);
  padding: var(--admin-spacing-3) var(--admin-spacing-4);
  border-bottom: 2px solid transparent;
  font-size: 0.875rem;
  color: var(--admin-color-gray-500);
}
.ui-tab[aria-selected='true'] {
  color: var(--admin-color-primary-500);
  border-bottom-color: currentColor;
}
.ui-tab:hover:not(:disabled) {
  background: var(--admin-color-primary-50);
}
.ui-tab:disabled {
  cursor: not-allowed;
  color: var(--admin-color-gray-400);
}
</style>
