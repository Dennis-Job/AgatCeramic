<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref } from 'vue'
import { Check, ChevronDown } from '@lucide/vue'

type SelectOption = { label: string; value: string }

const props = withDefaults(defineProps<{
  modelValue: string
  options: SelectOption[]
  placeholder?: string
  accessibleName: string
  searchable?: boolean
}>(), { placeholder: 'Выберите значение', searchable: false })

const emit = defineEmits<{ 'update:modelValue': [value: string]; change: [value: string] }>()
const isOpen = ref(false)
const root = ref<HTMLElement | null>(null)
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
  if (isOpen.value && props.searchable) {
    search.value = ''
    void nextTick(() => searchInput.value?.focus())
  }
}

function select(value: string): void {
  emit('update:modelValue', value)
  emit('change', value)
  isOpen.value = false
  search.value = ''
}

function closeOnOutsideClick(event: MouseEvent): void {
  if (root.value && !root.value.contains(event.target as Node)) isOpen.value = false
}

document.addEventListener('click', closeOnOutsideClick)
onBeforeUnmount(() => document.removeEventListener('click', closeOnOutsideClick))
</script>

<template>
  <div ref="root" class="relative" @keydown.escape="isOpen = false">
    <button
      type="button"
      class="flex w-full items-center justify-between gap-3 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-left text-sm font-medium text-gray-600 shadow-input outline-none transition hover:border-primary-200 focus:border-primary-500 focus:ring-4 focus:ring-primary-50"
      :aria-expanded="isOpen"
      :aria-label="accessibleName"
      @click="toggle"
    >
      <span class="truncate">{{ selectedLabel }}</span>
      <ChevronDown :size="18" class="shrink-0 text-gray-500 transition-transform" :class="{ 'rotate-180': isOpen }" />
    </button>
    <div v-if="isOpen" class="absolute left-0 right-0 z-40 mt-1.5 overflow-hidden rounded-lg border border-gray-100 bg-white py-1 shadow-dropdown">
      <div v-if="searchable" class="border-b border-gray-100 p-2">
        <input
          ref="searchInput"
          v-model="search"
          type="search"
          class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm outline-none focus:border-primary-500"
          placeholder="Начните вводить страну"
          :aria-label="`Поиск: ${accessibleName}`"
          @keydown.escape="isOpen = false"
        >
      </div>
      <div class="max-h-64 overflow-y-auto">
      <button
        v-for="option in filteredOptions"
        :key="option.value"
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
  </div>
</template>
