<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { Check, ChevronDown } from '@lucide/vue'

type SelectOption = { label: string; value: string }

const props = withDefaults(defineProps<{
  modelValue: string
  options: SelectOption[]
  placeholder?: string
  accessibleName: string
}>(), { placeholder: 'Выберите значение' })

const emit = defineEmits<{ 'update:modelValue': [value: string]; change: [value: string] }>()
const isOpen = ref(false)
const root = ref<HTMLElement | null>(null)
const selectedLabel = computed(() => props.options.find((option) => option.value === props.modelValue)?.label ?? props.placeholder)

function select(value: string): void {
  emit('update:modelValue', value)
  emit('change', value)
  isOpen.value = false
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
      class="flex w-full items-center justify-between gap-3 rounded-lg border border-[#d0d5dd] bg-white px-3 py-2.5 text-left text-sm font-medium text-[#475467] shadow-[0_1px_2px_rgba(16,24,40,.05)] outline-none transition hover:border-[#bdb4fe] focus:border-[#7f56d9] focus:ring-4 focus:ring-[#f4f3ff]"
      :aria-expanded="isOpen"
      :aria-label="accessibleName"
      @click="isOpen = !isOpen"
    >
      <span class="truncate">{{ selectedLabel }}</span>
      <ChevronDown :size="18" class="shrink-0 text-[#667085] transition-transform" :class="{ 'rotate-180': isOpen }" />
    </button>
    <div v-if="isOpen" class="absolute left-0 right-0 z-40 mt-1.5 overflow-hidden rounded-lg border border-[#eaecf0] bg-white py-1 shadow-[0_12px_20px_rgba(16,24,40,.12)]">
      <button
        v-for="option in options"
        :key="option.value"
        type="button"
        class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left text-sm text-[#344054] transition hover:bg-[#f9fafb]"
        :class="{ 'bg-[#f4f3ff] font-semibold text-[#6941c6]': option.value === modelValue }"
        @click="select(option.value)"
      >
        {{ option.label }}
        <Check v-if="option.value === modelValue" :size="17" />
      </button>
    </div>
  </div>
</template>
