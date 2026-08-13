<script setup lang="ts">
import { Search, X } from '@lucide/vue'
import { computed, useAttrs } from 'vue'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  modelValue?: string
  type?: string
  searchable?: boolean
}>(), { modelValue: '', type: 'text', searchable: false })

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const attrs = useAttrs()
const hasValue = computed(() => props.modelValue.length > 0)
const containerClass = computed(() => attrs.class)
const inputAttrs = computed(() => {
  const { class: ignored, ...attributes } = attrs

  return attributes
})

function clear(): void {
  emit('update:modelValue', '')
}
</script>

<template>
  <div :class="containerClass" class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 shadow-input transition focus-within:border-primary-500 focus-within:ring-4 focus-within:ring-primary-50">
    <Search v-if="searchable" :size="17" class="shrink-0 text-gray-400" />
    <input
      v-bind="inputAttrs"
      :value="modelValue"
      :type="type"
      class="min-w-0 flex-1 bg-transparent py-2.5 text-sm text-gray-700 outline-none placeholder:text-gray-400"
      @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
    <button v-if="hasValue" type="button" class="grid h-5 w-5 shrink-0 place-items-center rounded text-gray-400 transition hover:bg-gray-50 hover:text-gray-600" aria-label="Очистить поле" @click="clear">
      <X :size="16" />
    </button>
  </div>
</template>
