<script setup lang="ts">
import { useAttrs } from 'vue'

defineOptions({ inheritAttrs: false })

withDefaults(defineProps<{ modelValue?: string | null }>(), { modelValue: '' })
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const attrs = useAttrs()
</script>

<template>
  <textarea
    v-bind="attrs"
    :value="modelValue"
    class="min-h-28 w-full resize-y rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 shadow-input outline-none transition placeholder:text-gray-400 focus:border-primary-500 focus:ring-4 focus:ring-primary-50"
    @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
  />
</template>
