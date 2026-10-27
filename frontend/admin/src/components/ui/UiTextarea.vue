<script setup lang="ts">
import { useAttrs } from 'vue'
defineOptions({ inheritAttrs: false })
withDefaults(defineProps<{ modelValue?: string | null; compact?: boolean }>(), {
  modelValue: '',
  compact: false,
})
defineEmits<{ 'update:modelValue': [value: string] }>()
const attrs = useAttrs()
</script>
<template>
  <textarea
    v-bind="attrs"
    :value="modelValue"
    class="w-full resize-y rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-500 shadow-input outline-none transition placeholder:text-gray-500 admin-control-focus"
    :class="compact ? 'min-h-0' : 'min-h-28'"
    @input="
      $emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)
    "
  />
</template>
