<script setup lang="ts">
import { FileImage, ImagePlus } from '@lucide/vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiFileDropzone from '../../../components/ui/UiFileDropzone.vue'
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
void imageInput
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
    <UiFileDropzone
      class="product-photo-dropzone"
      label="Выберите или перетащите фото в эту область"
      drop-label="Отпустите фото для добавления"
      description="Формат — JPEG, JPG, PNG, WebP. Размер — не больше 10 МБ."
      format-label="IMG"
      :disabled="saving"
      @choose="chooseFile"
      @files="selectImageFiles"
    >
      <template #icon><FileImage :size="44" :stroke-width="1.4" /></template>
    </UiFileDropzone>
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
