<script setup lang="ts">
import { nextTick, ref } from 'vue'
import UiButton from './UiButton.vue'

export type UiSegmentedTabOption = {
  id: string
  label: string
  panelId: string
}

const props = defineProps<{
  modelValue: string
  idPrefix: string
  label: string
  options: ReadonlyArray<UiSegmentedTabOption>
}>()
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const tabList = ref<HTMLDivElement | null>(null)

function selectTab(id: string) {
  emit('update:modelValue', id)
}

function handleKeydown(event: KeyboardEvent) {
  const { options, modelValue } = props
  if (!options.length) return

  let nextIndex: number
  switch (event.key) {
    case 'ArrowRight':
      nextIndex =
        (options.findIndex((option) => option.id === modelValue) + 1) %
        options.length
      break
    case 'ArrowLeft':
      nextIndex =
        (options.findIndex((option) => option.id === modelValue) -
          1 +
          options.length) %
        options.length
      break
    case 'Home':
      nextIndex = 0
      break
    case 'End':
      nextIndex = options.length - 1
      break
    default:
      return
  }

  event.preventDefault()
  const nextTab = options[nextIndex]
  selectTab(nextTab.id)
  void nextTick(() => {
    tabList.value
      ?.querySelectorAll<HTMLButtonElement>('[role="tab"]')
      .item(nextIndex)
      ?.focus()
  })
}
</script>

<template>
  <div
    ref="tabList"
    class="flex min-w-0 rounded-xl border border-gray-200"
    role="tablist"
    :aria-label="label"
    @keydown="handleKeydown"
  >
    <UiButton
      v-for="option in options"
      :id="`${idPrefix}-${option.id}`"
      :key="option.id"
      type="button"
      variant="neutral-ghost"
      role="tab"
      :aria-selected="modelValue === option.id"
      :aria-controls="option.panelId"
      :tabindex="modelValue === option.id ? 0 : -1"
      class="relative min-w-0 flex-1 rounded-xl px-3 py-3 text-center text-sm leading-5"
      :class="
        modelValue === option.id
          ? 'z-10 text-gray-900 outline outline-1 outline-primary-500'
          : 'text-gray-500'
      "
      @click="selectTab(option.id)"
      >{{ option.label }}</UiButton
    >
  </div>
</template>
