<script setup lang="ts">
import { onBeforeUnmount } from 'vue'
import { Star, Trash2 } from '@lucide/vue'
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

function handleImageKeydown(event: KeyboardEvent, index: number): void {
  if (
    event.target !== event.currentTarget ||
    saving.value ||
    !canManage.value
  ) {
    return
  }

  if (event.key === 'ArrowUp' && index > 0) {
    event.preventDefault()
    reorderImage(index, -1)
  } else if (event.key === 'ArrowDown' && index < images.value.length - 1) {
    event.preventDefault()
    reorderImage(index, 1)
  }
}

let touchDragTimer: ReturnType<typeof setTimeout> | null = null
let touchDragId: number | null = null
let touchDragActive = false

function clearTouchDrag(): void {
  if (touchDragTimer) clearTimeout(touchDragTimer)
  touchDragTimer = null
  touchDragId = null
  touchDragActive = false
}

function startTouchDrag(
  image: (typeof images.value)[number],
  event: TouchEvent,
) {
  if (!canManage.value || saving.value || imageDeleting.value) return
  if (event.target instanceof Element && event.target.closest('button')) return
  const touch = event.changedTouches[0]
  if (!touch) return
  clearTouchDrag()
  touchDragId = touch.identifier
  const imageId = image.id
  touchDragTimer = setTimeout(() => {
    touchDragActive = true
    draggedImageId.value = imageId
  }, 450)
}

function moveTouchDrag(event: TouchEvent): void {
  if (touchDragId === null) return
  const touch = Array.from(event.changedTouches).find(
    (item) => item.identifier === touchDragId,
  )
  if (!touch) return
  if (!touchDragActive) {
    if (touchDragTimer) clearTimeout(touchDragTimer)
    touchDragTimer = null
    return
  }
  if (event.cancelable) event.preventDefault()
  const target = document
    .elementFromPoint(touch.clientX, touch.clientY)
    ?.closest<HTMLElement>('[data-product-image-id]')
  const targetId = Number(target?.dataset.productImageId)
  const targetImage = images.value.find((item) => item.id === targetId)
  if (targetImage) markImageDropTarget(targetImage)
}

async function endTouchDrag(event: TouchEvent): Promise<void> {
  if (touchDragId === null) return
  const touch = Array.from(event.changedTouches).find(
    (item) => item.identifier === touchDragId,
  )
  if (!touch) return
  if (touchDragTimer) clearTimeout(touchDragTimer)
  touchDragTimer = null
  const wasDragging = touchDragActive
  touchDragActive = false
  touchDragId = null
  if (!wasDragging) return
  const target = document
    .elementFromPoint(touch.clientX, touch.clientY)
    ?.closest<HTMLElement>('[data-product-image-id]')
  const targetId = Number(target?.dataset.productImageId)
  const targetIndex = images.value.findIndex((item) => item.id === targetId)
  if (targetIndex >= 0) await dropImage(targetIndex)
  else endImageDrag()
}

function cancelTouchDrag(): void {
  const wasDragging = touchDragActive
  clearTouchDrag()
  if (wasDragging) endImageDrag()
}

onBeforeUnmount(clearTouchDrag)
</script>

<template>
  <div>
    <h3 class="font-bold text-gray-500">Фото этой позиции</h3>
    <ProductPhotoUpload />
    <p class="sr-only" role="status" aria-live="polite">{{ imageStatus }}</p>
    <UiEmptyState
      v-if="!images.length"
      class="mt-5"
      label="Фото этого товара ещё не добавлены."
    />
    <template v-else>
      <p id="product-image-reorder-help" class="sr-only">
        Перетаскивайте фотографии, чтобы изменить порядок. На сенсорном экране
        удерживайте фото и переместите его. Сфокусируйте фото и используйте
        стрелки вверх и вниз для изменения порядка с клавиатуры.
      </p>
      <ul class="mt-5 grid list-none gap-4 p-0 sm:grid-cols-2 lg:grid-cols-4">
        <li
          v-for="(image, index) in images"
          :key="image.id"
          class="admin-focus relative overflow-hidden rounded-xl border border-gray-200 transition-colors focus:outline-none"
          :tabindex="canManage && !saving ? 0 : undefined"
          :aria-label="`Фото ${index + 1}${index === 0 ? ', обложка' : ''}`"
          aria-describedby="product-image-reorder-help"
          :data-product-image-id="image.id"
          :class="{
            'cursor-grab opacity-50': draggedImageId === image.id,
            'border-primary-500 bg-primary-50': draggedOverImageId === image.id,
          }"
          :draggable="canManage && !saving"
          @dragstart="startImageDrag(image, $event)"
          @dragend="endImageDrag"
          @dragover.prevent="markImageDropTarget(image)"
          @drop="dropImage(index)"
          @keydown="handleImageKeydown($event, index)"
          @touchstart="startTouchDrag(image, $event)"
          @touchmove="moveTouchDrag"
          @touchend="endTouchDrag"
          @touchcancel="cancelTouchDrag"
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
          <UiButton
            type="button"
            variant="danger-ghost"
            size="sm"
            class="absolute right-2 top-2 z-20 h-9 w-9 min-h-9 !p-0 rounded-full bg-white/95 shadow-sm"
            :disabled="saving"
            :aria-label="`Удалить изображение ${index + 1}`"
            @click.stop="requestImageDeletion(image)"
            ><Trash2 :size="17" aria-hidden="true"
          /></UiButton>
        </li>
      </ul>
    </template>
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
