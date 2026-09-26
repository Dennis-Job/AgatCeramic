<script setup lang="ts">
import { X } from '@lucide/vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import type { BannerPayload } from '../types/banner.types'
import BannerImagePreview from './BannerImagePreview.vue'

defineProps<{
  open: boolean
  editing: boolean
  busy: boolean
  error: string
  form: BannerPayload
}>()
const emit = defineEmits<{ close: []; submit: [] }>()
</script>

<template>
  <UiDialog
    :open="open"
    labelledby="banner-dialog-title"
    :close-disabled="busy"
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
      <p v-if="error" role="alert" class="mt-3 text-sm text-error-600">
        {{ error }}
      </p>
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
          label="Описание"
          help="Короткий текст. HTML будет показан как текст."
          ><UiTextarea
            v-model="form.description"
            :disabled="busy"
            maxlength="2000"
            rows="4"
        /></UiField>
        <UiField
          label="URL изображения"
          help="HTTPS или HTTP ссылка на изображение. Пока медиатека не подключена, изображение размещается отдельно."
          ><UiInput
            v-model="form.image_url"
            type="url"
            :disabled="busy"
            maxlength="2048"
        /></UiField>
        <BannerImagePreview
          :url="form.image_url"
          :alt="`Баннер «${form.title || 'Без названия'}»`"
        />
        <UiField label="Подпись кнопки"
          ><UiInput v-model="form.link_label" :disabled="busy" maxlength="255"
        /></UiField>
        <UiField label="Ссылка кнопки" help="Укажите вместе с подписью кнопки."
          ><UiInput
            v-model="form.link_url"
            type="url"
            :disabled="busy"
            maxlength="2048"
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
