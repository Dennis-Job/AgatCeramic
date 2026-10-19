<script setup lang="ts">
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import type { CategoryPayload } from '../types/category.types'
import { slugPattern } from '../../catalog/validation/slug'
import MediaImageEditor from '../../media/components/MediaImageEditor.vue'

defineProps<{
  form: CategoryPayload
  busy: boolean
  parentOptions: { label: string; value: string }[]
  updateName: (value: string) => void
  updateSlug: (value: string) => void
}>()
const emit = defineEmits<{
  mediaPending: [value: boolean]
  mediaUploading: [value: boolean]
  imagePreviewOpen: [value: boolean]
}>()
</script>
<template>
  <div class="mt-6 grid gap-4">
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="text-sm font-medium text-gray-500"
        >Название<UiInput
          :model-value="form.name"
          class="mt-1.5"
          data-autofocus
          required
          @update:model-value="updateName" /></label
      ><label class="text-sm font-medium text-gray-500"
        >Технический код (slug)<UiInput
          :model-value="form.slug"
          class="mt-1.5"
          :pattern="slugPattern"
          required
          @update:model-value="updateSlug"
      /></label>
    </div>
    <label class="text-sm font-medium text-gray-500"
      >Описание<UiTextarea
        v-model="form.description"
        class="mt-1.5 font-normal"
    /></label>
    <MediaImageEditor
      label="Изображение категории"
      upload-success-message="Изображение загружено и выбрано. Сохраните категорию, чтобы применить его."
      :disabled="busy"
      :model-value="form.image_id"
      @pending="emit('mediaPending', $event)"
      @uploading="emit('mediaUploading', $event)"
      @preview-open="emit('imagePreviewOpen', $event)"
      @update:model-value="form.image_id = $event"
    />
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="text-sm font-medium text-gray-500"
        >Родительская категория<UiSelect
          :model-value="form.parent_id === null ? '' : String(form.parent_id)"
          class="mt-1.5 w-full font-normal"
          accessible-name="Родительская категория"
          teleport-menu
          :options="parentOptions"
          @update:model-value="
            form.parent_id = $event === '' ? null : Number($event)
          "
      /></label>
      <label class="text-sm font-medium text-gray-500"
        >Порядок сортировки<UiInput
          :model-value="String(form.sort_order)"
          class="mt-1.5"
          type="number"
          min="0"
          required
          @update:model-value="form.sort_order = Number($event)"
      /></label>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
      <UiCheckbox
        mode="boolean"
        :checked="form.is_parent"
        @update:checked="form.is_parent = $event"
        >Родительская категория</UiCheckbox
      ><UiCheckbox
        mode="boolean"
        :checked="form.is_active"
        @update:checked="form.is_active = $event"
        >Категория активна</UiCheckbox
      >
    </div>
  </div>
</template>
