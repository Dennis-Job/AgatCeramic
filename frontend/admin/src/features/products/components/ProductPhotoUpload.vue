<script setup lang="ts">
import { ref } from 'vue'
import { FileImage, ImagePlus } from '@lucide/vue'
import UiButton from '../../../components/ui/UiButton.vue'
import { useProductEditorContext } from '../composables/useProductEditorContext'

const {
  imageInput,
  selectedFile,
  saving,
  selectFile,
  chooseFile,
  selectImageFiles,
  upload,
} = useProductEditorContext()
const dragDepth = ref(0)
void imageInput

function dragEnter(event: DragEvent) {
  if (!event.dataTransfer?.types.includes('Files')) return
  event.preventDefault()
  if (saving.value) return
  dragDepth.value++
}
function dragOver(event: DragEvent) {
  if (!event.dataTransfer?.types.includes('Files')) return
  event.preventDefault()
  event.dataTransfer.dropEffect = saving.value ? 'none' : 'copy'
}
function dropFile(event: DragEvent) {
  dragDepth.value = 0
  if (!event.dataTransfer?.types.includes('Files')) return
  event.preventDefault()
  selectImageFiles(event.dataTransfer.files)
}
</script>

<template>
  <form class="mt-5" @submit.prevent="upload">
    <input
      ref="imageInput"
      class="sr-only"
      type="file"
      accept="image/jpeg,image/png,image/webp"
      tabindex="-1"
      aria-label="Файл изображения"
      :disabled="saving"
      @change="selectFile"
    />
    <UiButton
      type="button"
      variant="surface"
      class="product-photo-dropzone"
      :class="{ 'product-photo-dropzone-active': dragDepth > 0 && !saving }"
      :disabled="saving"
      aria-labelledby="product-image-upload-label"
      aria-describedby="product-image-file-help"
      @click="chooseFile"
      @dragenter="dragEnter"
      @dragover="dragOver"
      @dragleave="dragDepth = Math.max(0, dragDepth - 1)"
      @drop="dropFile"
    >
      <span class="product-photo-file-icon" aria-hidden="true">
        <FileImage :size="44" :stroke-width="1.4" />
        <span class="product-photo-file-badge">IMG</span>
      </span>
      <span class="min-w-0 flex-1 text-left font-normal">
        <span
          id="product-image-upload-label"
          class="block text-sm font-semibold text-gray-500 sm:text-base"
        >
          {{
            dragDepth > 0 && !saving
              ? 'Отпустите фото для добавления'
              : 'Выберите или перетащите фото в эту область'
          }}
        </span>
        <span
          id="product-image-file-help"
          class="mt-1 block text-xs text-gray-500 sm:text-sm"
        >
          Формат — JPEG, JPG, PNG, WebP. Размер — не больше 10 МБ.
        </span>
      </span>
    </UiButton>
    <div
      v-if="selectedFile"
      class="mt-3 flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center"
    >
      <output
        class="min-w-0 flex-1 text-sm text-gray-500 [overflow-wrap:anywhere]"
        aria-live="polite"
      >
        {{ selectedFile.name }}
      </output>
      <UiButton
        type="submit"
        class="shrink-0"
        :loading="saving"
        :disabled="saving"
      >
        <ImagePlus :size="17" aria-hidden="true" />{{
          saving ? 'Загрузка…' : 'Загрузить'
        }}
      </UiButton>
    </div>
    <output v-else class="sr-only" aria-live="polite">Файл не выбран</output>
  </form>
</template>

<style scoped>
.product-photo-dropzone {
  width: 100%;
  display: flex;
  justify-content: flex-start;
  gap: var(--admin-spacing-4);
  padding: var(--admin-spacing-5);
  border: var(--admin-spacing-1) solid var(--color-gray-50);
  border-radius: var(--admin-radius-2xl);
  background: var(--color-white);
}
.product-photo-dropzone:hover:not(:disabled),
.product-photo-dropzone:focus-visible,
.product-photo-dropzone-active {
  border-color: var(--color-primary-200);
  background: var(--color-primary-25);
}
.product-photo-dropzone:disabled {
  background: var(--color-gray-50);
}
.product-photo-file-icon {
  position: relative;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: calc(var(--admin-spacing-4) * 3);
  height: calc(var(--admin-spacing-4) * 3);
  color: var(--color-gray-500);
}
.product-photo-file-icon svg {
  fill: var(--color-gray-100);
  transform: rotate(-6deg);
}
.product-photo-file-badge {
  position: absolute;
  left: 0;
  bottom: var(--admin-spacing-1);
  padding: var(--admin-spacing-1) var(--admin-spacing-2);
  border-radius: var(--admin-radius-lg);
  background: var(--color-primary-100);
  color: var(--color-primary-700);
  box-shadow: var(--admin-shadow-sm);
  font-size: var(--text-xs);
  font-weight: 700;
}
@media (max-width: 639px) {
  .product-photo-dropzone {
    gap: var(--admin-spacing-3);
    padding: var(--admin-spacing-3);
  }
}
</style>
