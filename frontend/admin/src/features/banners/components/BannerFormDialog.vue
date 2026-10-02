<script setup lang="ts">
import UiNotification from '../../../components/ui/UiNotification.vue'
import { X } from '@lucide/vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import type { BannerPayload } from '../types/banner.types'
import UiImagePreview from '../../../components/ui/UiImagePreview.vue'
import MediaReferenceField from '../../media/components/MediaReferenceField.vue'

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
</script>

<template>
  <UiDialog
    :open="open"
    labelledby="banner-dialog-title"
    :close-disabled="busy"
    :suspended="suspended"
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
          variant="ghost"
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
      <div class="mt-6 grid gap-4">
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
            :disabled="busy"
            maxlength="2000"
            rows="4"
        /></UiField>
        <MediaReferenceField
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
          v-if="form.image_url && !form.image_media_id"
          label="Существующий адрес изображения"
          help="Локальный путь /images/... или HTTPS-адрес доступен до выбора файла в медиатеке."
          ><UiInput v-model="form.image_url" :disabled="busy" maxlength="2048"
        /></UiField>
        <UiImagePreview
          v-if="!form.image_media_id"
          :url="form.image_url"
          :alt="`Баннер «${form.title || 'Без названия'}»`"
        />
        <UiField
          label="Альтернативный текст изображения"
          help="Кратко опишите изображение для посетителей, использующих экранный диктор."
        >
          <UiInput v-model="form.image_alt" :disabled="busy" maxlength="255" />
        </UiField>
        <UiField label="Подпись кнопки"
          ><UiInput v-model="form.link_label" :disabled="busy" maxlength="255"
        /></UiField>
        <UiField
          label="Ссылка кнопки"
          help="Укажите вместе с подписью. Внутренний путь /... или HTTPS-адрес."
          ><UiInput v-model="form.link_url" :disabled="busy" maxlength="2048"
        /></UiField>
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
