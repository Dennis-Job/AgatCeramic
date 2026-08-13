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
  <div :class="containerClass" class="flex items-center gap-2 rounded-lg border border-[#d0d5dd] bg-white px-3 shadow-[0_1px_2px_rgba(16,24,40,.05)] transition focus-within:border-[#7f56d9] focus-within:ring-4 focus-within:ring-[#f4f3ff]">
    <Search v-if="searchable" :size="17" class="shrink-0 text-[#98a2b3]" />
    <input
      v-bind="inputAttrs"
      :value="modelValue"
      :type="type"
      class="min-w-0 flex-1 bg-transparent py-2.5 text-sm text-[#344054] outline-none placeholder:text-[#98a2b3]"
      @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
    <button v-if="hasValue" type="button" class="grid h-5 w-5 shrink-0 place-items-center rounded text-[#98a2b3] transition hover:bg-[#f2f4f7] hover:text-[#475467]" aria-label="Очистить поле" @click="clear">
      <X :size="16" />
    </button>
  </div>
</template>
