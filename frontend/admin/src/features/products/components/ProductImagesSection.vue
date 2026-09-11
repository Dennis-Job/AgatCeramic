<script setup lang="ts">
import { GripVertical, ImagePlus, Star } from '@lucide/vue'
import { useProductEditorContext } from '../composables/useProductEditorContext'
const { imageInput, selectedFile, saving, selectFile, chooseFile, upload, imageStatus, draggedImageId, draggedOverImageId, canManage, startImageDrag, endImageDrag, markImageDropTarget, dropImage, form, reorderImage, confirmError, imageDeleting } = useProductEditorContext()
const images: any[] = useProductEditorContext().images
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
      <button type="button" class="shrink-0 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-50 disabled:opacity-60" :disabled="saving" aria-describedby="product-image-file-help" @click="chooseFile">Выбрать файл</button>
    </div>
    <p id="product-image-file-help" class="mt-2 text-xs text-gray-500">Допустимые форматы: JPG, PNG или WebP. Максимальный размер — 10 МБ.</p>
    <button class="mt-4 inline-flex w-fit items-center gap-2 rounded-lg bg-primary-500 px-3 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving||!selectedFile"><ImagePlus :size="17"/>{{saving?'Загрузка…':'Загрузить'}}</button>
  </form>
  <p class="sr-only" role="status" aria-live="polite">{{imageStatus}}</p><p v-if="!images.length" class="mt-5 rounded-xl border border-dashed border-gray-300 px-5 py-10 text-center text-sm text-gray-500">Фотографий пока нет.</p>
  <div v-else class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3"><article v-for="(image,index) in images" :key="image.id" class="relative overflow-hidden rounded-xl border border-gray-200 transition-colors" :class="{ 'cursor-grab opacity-50': draggedImageId===image.id, 'border-primary-500 bg-primary-50': draggedOverImageId===image.id }" :draggable="canManage&&!saving" @dragstart="startImageDrag(image,$event)" @dragend="endImageDrag" @dragover.prevent="markImageDropTarget(image)" @drop="dropImage(index)"><img :src="image.url" :alt="image.alt||form.name" class="aspect-[4/3] w-full object-cover"><span v-if="index===0" class="absolute left-3 top-3 z-10 inline-flex items-center gap-1 rounded-full bg-warning-50 px-2 py-1 text-xs font-semibold text-warning-600 shadow-sm"><Star :size="13"/>Обложка</span><div class="space-y-3 p-3"><div class="flex flex-wrap items-center justify-between gap-2"><span class="inline-flex items-center gap-1 text-xs text-gray-500"><GripVertical :size="15"/>Перетащите для изменения порядка</span><span class="break-all text-xs text-gray-500">{{image.alt}}</span></div><div class="flex flex-wrap gap-1"><button type="button" class="rounded-lg px-2 py-1 text-sm text-primary-600 disabled:opacity-40" :disabled="index===0||saving" :aria-label="`Переместить изображение ${index+1} выше`" @click="reorderImage(index,-1)">Выше</button><button type="button" class="rounded-lg px-2 py-1 text-sm text-primary-600 disabled:opacity-40" :disabled="index===images.length-1||saving" :aria-label="`Переместить изображение ${index+1} ниже`" @click="reorderImage(index,1)">Ниже</button><button type="button" class="rounded-lg px-2 py-1 text-sm font-semibold text-error-500 disabled:opacity-40" :disabled="saving" :aria-label="`Удалить изображение ${index+1}`" @click="confirmError='';imageDeleting=image">Удалить</button></div></div></article></div>
</div>
</template>
