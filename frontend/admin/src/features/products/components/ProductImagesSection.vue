<script setup lang="ts">
import { GripVertical, Star } from '@lucide/vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiImagePreview from '../../../components/ui/UiImagePreview.vue'
import ProductPhotoUpload from './ProductPhotoUpload.vue'
import { useProductEditorContext } from '../composables/useProductEditorContext'
const {
  images,
  saving,
  imageStatus,
  draggedImageId,
  draggedOverImageId,
  canManage,
  startImageDrag,
  endImageDrag,
  markImageDropTarget,
  dropImage,
  form,
  reorderImage,
  confirmError,
  imageDeleting,
} = useProductEditorContext()

function requestImageDeletion(image: (typeof images.value)[number]): void {
  confirmError.value = ''
  imageDeleting.value = image
}
</script>

<template>
  <div>
    <h3 class="font-bold text-gray-900">Фото этой позиции</h3>
    <ProductPhotoUpload />
    <p class="sr-only" role="status" aria-live="polite">{{ imageStatus }}</p>
    <UiEmptyState
      v-if="!images.length"
      class="mt-5"
      label="Фото этого товара ещё не добавлены."
    />
    <div v-else class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <article
        v-for="(image, index) in images"
        :key="image.id"
        class="relative overflow-hidden rounded-xl border border-gray-200 transition-colors"
        :class="{
          'cursor-grab opacity-50': draggedImageId === image.id,
          'border-primary-500 bg-primary-50': draggedOverImageId === image.id,
        }"
        :draggable="canManage && !saving"
        @dragstart="startImageDrag(image, $event)"
        @dragend="endImageDrag"
        @dragover.prevent="markImageDropTarget(image)"
        @drop="dropImage(index)"
      >
        <UiImagePreview
          :url="image.url"
          :alt="image.alt || form.name"
          class="product-image-preview"
        /><UiBadge
          v-if="index === 0"
          class="absolute left-3 top-3 z-10 gap-1 shadow-sm"
          tone="warning"
          ><Star :size="13" />Обложка</UiBadge
        >
        <div class="space-y-3 p-3">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="inline-flex items-center gap-1 text-xs text-gray-500"
              ><GripVertical :size="15" />Перетащите для изменения порядка</span
            ><span class="break-all text-xs text-gray-500">{{
              image.alt
            }}</span>
          </div>
          <div class="flex flex-wrap gap-1">
            <UiButton
              type="button"
              variant="ghost"
              size="sm"
              class="text-primary-600"
              :disabled="index === 0 || saving"
              :aria-label="`Переместить изображение ${index + 1} выше`"
              @click="reorderImage(index, -1)"
              >Выше</UiButton
            ><UiButton
              type="button"
              variant="ghost"
              size="sm"
              class="text-primary-600"
              :disabled="index === images.length - 1 || saving"
              :aria-label="`Переместить изображение ${index + 1} ниже`"
              @click="reorderImage(index, 1)"
              >Ниже</UiButton
            ><UiButton
              type="button"
              variant="danger-ghost"
              size="sm"
              :disabled="saving"
              :aria-label="`Удалить изображение ${index + 1}`"
              @click="requestImageDeletion(image)"
              >Удалить</UiButton
            >
          </div>
        </div>
      </article>
    </div>
  </div>
</template>

<style scoped>
.product-image-preview {
  aspect-ratio: 4 / 3;
  border: 0;
  border-radius: 0;
}
.product-image-preview :deep(img) {
  object-fit: contain;
}
</style>
