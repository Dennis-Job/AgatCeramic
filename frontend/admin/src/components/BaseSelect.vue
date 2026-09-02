<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref } from 'vue'
import { Check, ChevronDown, X } from '@lucide/vue'

type SelectOption = { label: string; value: string }

const props = withDefaults(defineProps<{
  modelValue: string
  options: SelectOption[]
  placeholder?: string
  accessibleName: string
  searchable?: boolean
  searchPlaceholder?: string
  clearable?: boolean
  teleportMenu?: boolean
}>(), { placeholder: 'Выберите значение', searchable: false, searchPlaceholder: 'Начните вводить для поиска', clearable: false, teleportMenu: false })

const emit = defineEmits<{ 'update:modelValue': [value: string]; change: [value: string] }>()
const isOpen = ref(false)
const root = ref<HTMLElement | null>(null)
const triggerButton = ref<HTMLButtonElement | null>(null)
const menu = ref<HTMLElement | null>(null)
const menuStyle = ref<Record<string, string>>({})
const search = ref('')
const searchInput = ref<HTMLInputElement | null>(null)
const selectedLabel = computed(() => props.options.find((option) => option.value === props.modelValue)?.label ?? props.placeholder)
const filteredOptions = computed(() => {
  const query = search.value.trim().toLocaleLowerCase('ru')

  return query === ''
    ? props.options
    : props.options.filter((option) => option.label.toLocaleLowerCase('ru').includes(query))
})

function toggle(): void {
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    search.value = ''
    void nextTick(() => {
      updateMenuPosition()
      if (props.searchable) searchInput.value?.focus()
      else focusInitialOption()
    })
  }
}

function optionButtons(): HTMLButtonElement[] {
  return Array.from(menu.value?.querySelectorAll<HTMLButtonElement>('[data-select-option]') ?? [])
}

function focusInitialOption(): void {
  const buttons = optionButtons()
  const selected = buttons.find(button => button.dataset.value === props.modelValue)
  const initial = selected ?? buttons[0]
  initial?.focus()
}

function handleMenuKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    event.stopPropagation()
    event.preventDefault()
    closeAndFocus()
    return
  }

  const buttons = optionButtons()
  const currentIndex = buttons.indexOf(document.activeElement as HTMLButtonElement)
  let nextIndex: number | null = null
  if (event.key === 'ArrowDown') nextIndex = Math.min(currentIndex + 1, buttons.length - 1)
  if (event.key === 'ArrowUp') nextIndex = Math.max(currentIndex - 1, 0)
  if (event.key === 'Home') nextIndex = 0
  if (event.key === 'End') nextIndex = buttons.length - 1
  if (nextIndex === null || nextIndex < 0) return

  event.preventDefault()
  buttons[nextIndex]?.focus()
}

function handleRootEscape(event: KeyboardEvent): void {
  if (!isOpen.value) return
  event.stopPropagation()
  event.preventDefault()
  closeAndFocus()
}

function updateMenuPosition(): void {
  if (!props.teleportMenu || !isOpen.value || !triggerButton.value) return

  const rect = triggerButton.value.getBoundingClientRect()
  const gap = 6
  const preferredHeight = Math.min(menu.value?.scrollHeight ?? 264, 264)
  const availableBelow = window.innerHeight - rect.bottom - gap
  const availableAbove = rect.top - gap
  const placeAbove = availableBelow < Math.min(preferredHeight, 160) && availableAbove > availableBelow
  const availableHeight = Math.max(96, Math.min(264, placeAbove ? availableAbove : availableBelow))

  menuStyle.value = {
    left: `${rect.left}px`,
    width: `${rect.width}px`,
    maxHeight: `${availableHeight}px`,
    ...(placeAbove
      ? { bottom: `${window.innerHeight - rect.top + gap}px` }
      : { top: `${rect.bottom + gap}px` }),
  }
}

function select(value: string): void {
  emit('update:modelValue', value)
  emit('change', value)
  isOpen.value = false
  search.value = ''
  void nextTick(() => triggerButton.value?.focus())
}

function closeAndFocus(): void {
  isOpen.value = false
  search.value = ''
  void nextTick(() => triggerButton.value?.focus())
}

function clear(): void {
  emit('update:modelValue', '')
  emit('change', '')
  isOpen.value = false
  search.value = ''
  void nextTick(() => triggerButton.value?.focus())
}

function closeOnOutsideClick(event: MouseEvent): void {
  const target = event.target as Node
  if (root.value && !root.value.contains(target) && !menu.value?.contains(target)) isOpen.value = false
}

document.addEventListener('click', closeOnOutsideClick)
window.addEventListener('resize', updateMenuPosition)
document.addEventListener('scroll', updateMenuPosition, true)
onBeforeUnmount(() => {
  document.removeEventListener('click', closeOnOutsideClick)
  window.removeEventListener('resize', updateMenuPosition)
  document.removeEventListener('scroll', updateMenuPosition, true)
})
</script>

<template>
  <div ref="root" class="relative" @keydown.escape="handleRootEscape">
    <button
      ref="triggerButton"
      type="button"
      class="flex w-full items-center justify-between gap-3 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-left text-sm font-medium text-gray-600 shadow-input outline-none transition hover:border-primary-200 focus:border-primary-500 focus:ring-4 focus:ring-primary-50"
      :aria-expanded="isOpen"
      :aria-label="accessibleName"
      @click="toggle"
    >
      <span class="truncate">{{ selectedLabel }}</span>
      <span class="flex shrink-0 items-center gap-2">
        <span v-if="clearable && modelValue" class="h-5 w-5" aria-hidden="true" />
        <ChevronDown :size="18" class="text-gray-500 transition-transform" :class="{ 'rotate-180': isOpen }" />
      </span>
    </button>
    <button
      v-if="clearable && modelValue"
      type="button"
      class="absolute right-9 top-1/2 z-10 grid h-6 w-6 -translate-y-1/2 place-items-center rounded text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-50"
      :aria-label="`Очистить выбор: ${accessibleName}`"
      @click="clear"
    >
      <X :size="15" aria-hidden="true" />
    </button>
    <Teleport to="body" :disabled="!teleportMenu">
    <div
      v-if="isOpen"
      ref="menu"
      :data-floating-select-menu="teleportMenu ? '' : undefined"
      class="rounded-lg border border-gray-100 bg-white py-1 shadow-dropdown"
      :class="teleportMenu ? 'fixed z-[70] overflow-x-hidden overflow-y-auto' : 'absolute left-0 right-0 z-40 mt-1.5 overflow-hidden'"
      :style="teleportMenu ? menuStyle : undefined"
      @keydown="handleMenuKeydown"
    >
      <div v-if="searchable" class="border-b border-gray-100 p-2">
        <input
          ref="searchInput"
          v-model="search"
          type="search"
          class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-500"
          :placeholder="searchPlaceholder"
          :aria-label="`Поиск: ${accessibleName}`"
        >
      </div>
      <div :class="teleportMenu ? undefined : 'max-h-64 overflow-y-auto'">
      <button
        v-for="option in filteredOptions"
        :key="option.value"
        data-select-option
        :data-value="option.value"
        type="button"
        class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left text-sm text-gray-700 transition hover:bg-gray-25"
        :class="{ 'bg-primary-50 font-semibold text-primary-600': option.value === modelValue }"
        @click="select(option.value)"
      >
        {{ option.label }}
        <Check v-if="option.value === modelValue" :size="17" />
      </button>
      <p v-if="searchable && filteredOptions.length === 0" class="px-3 py-3 text-sm text-gray-500">Ничего не найдено</p>
      </div>
    </div>
    </Teleport>
  </div>
</template>
