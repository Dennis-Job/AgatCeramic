<script setup lang="ts">
import UiNotification from '../../../components/ui/UiNotification.vue'
import { X } from '@lucide/vue'
import { ref } from 'vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import type { BannerPayload } from '../types/banner.types'
import UiImagePreview from '../../../components/ui/UiImagePreview.vue'
import MediaReferenceField from '../../media/components/MediaReferenceField.vue'
import MediaImageEditor from '../../media/components/MediaImageEditor.vue'

defineProps<{
  open: boolean
  editing: boolean
  busy: boolean
  error: string
  form: BannerPayload
  inlineUpload?: boolean
  suspended?: boolean
}>()
const emit = defineEmits<{
  close: []
  submit: []
  mediaPending: [value: boolean]
  mediaUploading: [value: boolean]
}>()
const imagePreviewOpen = ref(false)
</script>

<template>
  <UiDialog
    :open="open"
    labelledby="banner-dialog-title"
    :close-disabled="busy"
    :suspended="suspended || imagePreviewOpen"
    panel-class="w-full max-w-2xl"
    @close="emit('close')"
  >
    <form
      class="max-h-[90vh] w-full overflow-y-auto rounded-xl bg-white p-6 shadow-xl"
      @submit.prevent="emit('submit')"
    >
      <div class="flex items-start justify-between gap-3">
        <h2 id="banner-dialog-title" class="text-lg font-bold">
          {{ editing ? 'Редактировать баннер' : 'Новый баннер' }}
        </h2>
        <UiButton
          type="button"
          variant="surface"
          size="sm"
          aria-label="Закрыть окно баннера"
          :disabled="busy"
          @click="emit('close')"
          ><X :size="20"
        /></UiButton>
      </div>
      <UiNotification v-if="error">
        {{ error }}
      </UiNotification>
      <div class="mt-4 grid gap-3">
        <UiField label="Заголовок" required
          ><UiInput
            v-model="form.title"
            required
            :disabled="busy"
            maxlength="255"
            data-autofocus
        /></UiField>
        <UiField
          label="Надпись над заголовком"
          help="Например, AgatCeramic · Керамогранит. Используется на главной странице."
        >
          <UiInput v-model="form.eyebrow" :disabled="busy" maxlength="255" />
        </UiField>
        <UiField
          label="Описание"
          help="Короткий текст. HTML будет показан как текст."
          ><UiTextarea
            v-model="form.description"
            compact
            :disabled="busy"
            maxlength="2000"
            rows="2"
        /></UiField>
        <MediaImageEditor
          v-if="inlineUpload"
          compact
          require-manage-permission
          label="Изображение баннера"
          preview-label="Увеличить изображение баннера"
          remove-label="Снять выбранный файл баннера"
          :fallback-url="form.image_url"
          :fallback-alt="form.image_alt || form.title"
          :model-value="form.image_media_id"
          :disabled="busy"
          @preview-open="imagePreviewOpen = $event"
          @pending="emit('mediaPending', $event)"
          @uploading="emit('mediaUploading', $event)"
          @update:model-value="form.image_media_id = $event"
        />
        <MediaReferenceField
          v-else
          kind="image"
          require-manage-permission
          label="Изображение баннера"
          :inline-upload="inlineUpload"
          :model-value="form.image_media_id"
          :disabled="busy"
          @pending="emit('mediaPending', $event)"
          @uploading="emit('mediaUploading', $event)"
          @update:model-value="
            form.image_media_id = Array.isArray($event) ? null : $event
          "
        />
        <UiField
          v-if="form.image_url && !form.image_media_id && !inlineUpload"
          label="Существующий адрес изображения"
          help="Локальный путь /images/... или HTTPS-адрес доступен до выбора файла в медиатеке."
          ><UiInput v-model="form.image_url" :disabled="busy" maxlength="2048"
        /></UiField>
        <UiImagePreview
          v-if="!form.image_media_id && !inlineUpload"
          :url="form.image_url"
          :alt="`Баннер «${form.title || 'Без названия'}»`"
        />
        <details v-if="inlineUpload" class="text-sm text-gray-500">
          <summary class="cursor-pointer rounded admin-focus">
            Исходный адрес изображения
          </summary>
          <UiField
            class="mt-2"
            label="Существующий адрес изображения"
            help="Локальный путь /images/... или HTTPS-адрес используется до выбора файла в медиатеке."
            ><UiInput
              v-model="form.image_url"
              :disabled="busy"
              maxlength="2048"
          /></UiField>
        </details>
        <UiField
          label="Альтернативный текст изображения"
          help="Кратко опишите изображение для посетителей, использующих экранный диктор."
        >
          <UiInput v-model="form.image_alt" :disabled="busy" maxlength="255" />
        </UiField>
        <div class="grid gap-3 sm:grid-cols-2">
          <UiField label="Подпись кнопки"
            ><UiInput
              v-model="form.link_label"
              :disabled="busy"
              maxlength="255"
          /></UiField>
          <UiField
            label="Ссылка кнопки"
            help="Укажите вместе с подписью. Внутренний путь /... или HTTPS-адрес."
            ><UiInput v-model="form.link_url" :disabled="busy" maxlength="2048"
          /></UiField>
        </div>
        <UiCheckbox
          mode="boolean"
          :checked="form.is_published"
          :disabled="busy"
          @update:checked="form.is_published = $event"
          >Опубликовать баннер</UiCheckbox
        >
      </div>
      <div class="mt-6 flex flex-wrap justify-end gap-3">
        <UiButton
          type="button"
          variant="secondary"
          :disabled="busy"
          @click="emit('close')"
          >Отмена</UiButton
        >
        <UiButton :loading="busy">Сохранить</UiButton>
      </div>
    </form>
  </UiDialog>
</template>
