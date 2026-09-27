<script setup lang="ts">
import { ref, watch } from 'vue'
import { ImageOff } from '@lucide/vue'

const props = defineProps<{ url: string | null; alt: string }>()
const failed = ref(false)
watch(
  () => props.url,
  () => {
    failed.value = false
  },
)
</script>

<template>
  <div
    class="flex shrink-0 items-center justify-center overflow-hidden rounded-lg bg-gray-50 text-gray-500"
  >
    <img
      v-if="url && !failed"
      :src="url"
      :alt="alt"
      class="h-full w-full object-contain"
      @error="failed = true"
    />
    <span
      v-else
      role="status"
      :aria-label="`Не удалось показать изображение: ${alt}`"
      ><ImageOff :size="24" aria-hidden="true"
    /></span>
  </div>
</template>
