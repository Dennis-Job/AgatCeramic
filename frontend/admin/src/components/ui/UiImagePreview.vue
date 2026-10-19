<script setup lang="ts">
import { ref, watch } from 'vue'
import { ImageOff } from '@lucide/vue'

const props = withDefaults(
  defineProps<{ url: string | null; alt: string; compact?: boolean }>(),
  { compact: false },
)
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
    :class="{ 'ui-image-preview-compact': compact }"
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
      :class="{ 'ui-image-preview-fallback-compact': compact }"
    >
      <ImageOff :size="24" aria-hidden="true" />
      <span :class="{ 'sr-only': compact }">{{
        failed ? 'Не удалось загрузить изображение' : 'Изображение не задано'
      }}</span>
    </div>
  </div>
</template>

<style scoped>
.ui-image-preview-compact {
  width: calc(var(--admin-spacing-4) * 3);
  height: calc(var(--admin-spacing-4) * 3);
  aspect-ratio: 1;
  flex-shrink: 0;
}
.ui-image-preview-compact img {
  object-fit: contain;
}
.ui-image-preview-fallback-compact {
  padding: 0;
}
</style>
