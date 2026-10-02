<script setup lang="ts">
import { Search, X } from '@lucide/vue'
import { computed, useAttrs } from 'vue'
defineOptions({ inheritAttrs: false })
const props = withDefaults(
  defineProps<{ modelValue?: string; type?: string; searchable?: boolean }>(),
  { modelValue: '', type: 'text', searchable: false },
)
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const attrs = useAttrs()
const hasValue = computed(() => props.modelValue.length > 0)
const isDisabled = computed(
  () => attrs.disabled !== undefined && attrs.disabled !== false,
)
const containerClass = computed(() => attrs.class)
const inputAttrs = computed(() => {
  const attributes = { ...attrs }
  delete attributes.class
  return attributes
})

function updateValue(value: string): void {
  if (!isDisabled.value) emit('update:modelValue', value)
}

function clear(): void {
  if (!isDisabled.value) emit('update:modelValue', '')
}
</script>
<template>
  <div
    :class="[
      containerClass,
      isDisabled
        ? 'cursor-not-allowed border-gray-200 bg-gray-50'
        : 'border-gray-300 bg-white admin-control-focus',
    ]"
    class="flex min-h-[var(--admin-control-height-md)] w-full min-w-0 items-center gap-2 rounded-lg border px-3 shadow-input transition"
  >
    <Search
      v-if="searchable"
      :size="17"
      class="shrink-0"
      :class="isDisabled ? 'text-gray-500' : 'text-gray-400'"
      aria-hidden="true"
    />
    <input
      data-composite-control
      v-bind="inputAttrs"
      :value="modelValue"
      :type="type"
      class="min-w-0 flex-1 bg-transparent py-1.5 text-sm text-gray-700 outline-none placeholder:text-gray-400 disabled:cursor-not-allowed disabled:text-gray-500"
      @input="updateValue(($event.target as HTMLInputElement).value)"
    />
    <button
      v-if="hasValue"
      type="button"
      class="grid h-5 w-5 shrink-0 place-items-center rounded text-gray-400 transition hover:bg-gray-50 hover:text-gray-600 admin-focus disabled:cursor-not-allowed disabled:bg-transparent disabled:text-gray-500 disabled:hover:bg-transparent disabled:hover:text-gray-500"
      aria-label="Очистить поле"
      :disabled="isDisabled"
      @click="clear"
    >
      <X :size="16" aria-hidden="true" />
    </button>
  </div>
</template>
