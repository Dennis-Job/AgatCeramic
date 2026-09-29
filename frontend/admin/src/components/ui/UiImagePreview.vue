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
    class="flex aspect-[16/9] w-full items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-gray-50 text-gray-500"
  >
    <img
      v-if="url && !failed"
      :src="url"
      :alt="alt"
      class="h-full w-full object-cover"
      @error="failed = true"
    />
    <div
      v-else
      class="flex flex-col items-center gap-2 p-3 text-center text-sm"
      role="status"
    >
      <ImageOff :size="24" aria-hidden="true" />
      <span>{{
        failed ? 'Не удалось загрузить изображение' : 'Изображение не задано'
      }}</span>
    </div>
  </div>
</template>
