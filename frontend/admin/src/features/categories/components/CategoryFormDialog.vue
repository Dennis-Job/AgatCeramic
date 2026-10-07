<script setup lang="ts">
import { X } from '@lucide/vue'
import { ref } from 'vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiDialogFooter from '../../../components/ui/UiDialogFooter.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import type { CategoryPayload } from '../types/category.types'
import CategoryMainSection from './CategoryMainSection.vue'
const imagePreviewOpen = ref(false)
defineProps<{
  open: boolean
  title: string
  busy: boolean
  mediaPending: boolean
  mediaUploading: boolean
  error: string
  form: CategoryPayload
  parentOptions: { label: string; value: string }[]
  updateName: (value: string) => void
  updateSlug: (value: string) => void
}>()
const emit = defineEmits<{
  close: []
  submit: []
  mediaPending: [value: boolean]
  mediaUploading: [value: boolean]
}>()
</script>
<template>
  <UiDialog
    :open="open"
    :suspended="imagePreviewOpen"
    labelledby="category-dialog-title"
    describedby="category-dialog-description"
    :close-disabled="busy || mediaUploading"
    panel-class="w-full max-w-3xl"
    @close="emit('close')"
    ><form
      class="flex max-h-[90dvh] w-full flex-col rounded-xl bg-white p-6 shadow-xl"
      @submit.prevent="emit('submit')"
    >
      <div class="flex shrink-0 items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
          <h2
            id="category-dialog-title"
            class="break-words text-lg font-bold text-gray-500 [overflow-wrap:anywhere]"
          >
            {{ title }}
          </h2>
        </div>
        <UiButton
          type="button"
          variant="surface"
          size="sm"
          aria-label="Закрыть окно категории"
          :disabled="busy || mediaUploading"
          @click="emit('close')"
          ><X :size="20"
        /></UiButton>
      </div>
      <div class="min-h-0 overflow-y-auto overscroll-contain">
        <UiNotification v-if="error">{{ error }}</UiNotification>
        <CategoryMainSection
          :form="form"
          :busy="busy"
          :parent-options="parentOptions"
          :update-name="updateName"
          :update-slug="updateSlug"
          @media-pending="emit('mediaPending', $event)"
          @media-uploading="emit('mediaUploading', $event)"
          @image-preview-open="imagePreviewOpen = $event"
        />
      </div>
      <UiDialogFooter>
        <template #note
          ><span id="category-dialog-description">{{
            mediaUploading
              ? 'Изображение загружается…'
              : mediaPending
                ? 'Загрузите выбранное изображение или отмените выбор файла перед сохранением.'
                : 'Настройте отображаемое название и адрес страницы категории.'
          }}</span></template
        >
        <div class="flex shrink-0 items-center gap-3">
          <UiButton
            type="button"
            variant="surface"
            :disabled="busy || mediaUploading"
            @click="emit('close')"
            >Отмена</UiButton
          ><UiButton :loading="busy" :disabled="mediaPending || mediaUploading"
            >Сохранить</UiButton
          >
        </div>
      </UiDialogFooter>
    </form></UiDialog
  >
</template>
