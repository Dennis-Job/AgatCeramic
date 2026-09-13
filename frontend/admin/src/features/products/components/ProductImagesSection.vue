<script setup lang="ts">
import { GripVertical, ImagePlus, Star } from '@lucide/vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import { useProductEditorContext } from '../composables/useProductEditorContext'
const { imageInput, images, selectedFile, saving, selectFile, chooseFile, upload, imageStatus, draggedImageId, draggedOverImageId, canManage, startImageDrag, endImageDrag, markImageDropTarget, dropImage, form, reorderImage, confirmError, imageDeleting } = useProductEditorContext()
void imageInput
</script>

<template>
<div>
  <h3 class="font-bold text-gray-900">Фото этой позиции</h3>
  <form class="mt-5 rounded-xl border border-gray-200 bg-gray-25 p-4" @submit.prevent="upload">
    <p id="product-image-label" class="text-sm font-medium text-gray-700">Изображение</p>
    <input ref="imageInput" class="sr-only" type="file" accept="image/jpeg,image/png,image/webp" tabindex="-1" aria-label="Файл изображения" @change="selectFile">
    <div class="mt-2 flex min-w-0 flex-col gap-2 sm:flex-row">
      <output class="min-h-11 min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 shadow-input [overflow-wrap:anywhere]" aria-live="polite">{{selectedFile?.name??'Файл не выбран'}}</output>
      <UiButton type="button" class="shrink-0" variant="secondary" :disabled="saving" aria-describedby="product-image-file-help" @click="chooseFile">Выбрать файл</UiButton>
    </div>
    <p id="product-image-file-help" class="mt-2 text-xs text-gray-500">Допустимые форматы: JPG, PNG или WebP. Максимальный размер — 10 МБ.</p>
    <UiButton class="mt-4 w-fit" :loading="saving" :disabled="saving || !selectedFile"><ImagePlus :size="17" />{{ saving ? 'Загрузка…' : 'Загрузить' }}</UiButton>
  </form>
  <p class="sr-only" role="status" aria-live="polite">{{ imageStatus }}</p><UiEmptyState v-if="!images.length" class="mt-5 rounded-xl border border-dashed border-gray-300" label="Фотографий пока нет." />
  <div v-else class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3"><article v-for="(image,index) in images" :key="image.id" class="relative overflow-hidden rounded-xl border border-gray-200 transition-colors" :class="{ 'cursor-grab opacity-50': draggedImageId===image.id, 'border-primary-500 bg-primary-50': draggedOverImageId===image.id }" :draggable="canManage&&!saving" @dragstart="startImageDrag(image,$event)" @dragend="endImageDrag" @dragover.prevent="markImageDropTarget(image)" @drop="dropImage(index)"><img :src="image.url" :alt="image.alt||form.name" class="aspect-[4/3] w-full object-cover"><UiBadge v-if="index === 0" class="absolute left-3 top-3 z-10 gap-1 shadow-sm" tone="warning"><Star :size="13" />Обложка</UiBadge><div class="space-y-3 p-3"><div class="flex flex-wrap items-center justify-between gap-2"><span class="inline-flex items-center gap-1 text-xs text-gray-500"><GripVertical :size="15" />Перетащите для изменения порядка</span><span class="break-all text-xs text-gray-500">{{ image.alt }}</span></div><div class="flex flex-wrap gap-1"><UiButton type="button" variant="ghost" size="sm" class="text-primary-600" :disabled="index === 0 || saving" :aria-label="`Переместить изображение ${index + 1} выше`" @click="reorderImage(index, -1)">Выше</UiButton><UiButton type="button" variant="ghost" size="sm" class="text-primary-600" :disabled="index === images.length - 1 || saving" :aria-label="`Переместить изображение ${index + 1} ниже`" @click="reorderImage(index, 1)">Ниже</UiButton><UiButton type="button" variant="danger-ghost" size="sm" :disabled="saving" :aria-label="`Удалить изображение ${index + 1}`" @click="confirmError = ''; imageDeleting = image">Удалить</UiButton></div></div></article></div>
</div>
</template>
