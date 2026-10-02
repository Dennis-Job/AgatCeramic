<script setup lang="ts">
import UiNotification from '../../../components/ui/UiNotification.vue'
import { X } from '@lucide/vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import { slugPattern } from '../../catalog/validation/slug'
import type { PagePayload } from '../types/page.types'
import { systemPageSlugs } from '../validation/page'

defineProps<{
  open: boolean
  editing: boolean
  busy: boolean
  error: string
  form: PagePayload
}>()
const emit = defineEmits<{ close: []; submit: [] }>()
</script>

<template>
  <UiDialog
    :open="open"
    labelledby="page-dialog-title"
    :close-disabled="busy"
    panel-class="w-full max-w-2xl"
    @close="emit('close')"
  >
    <form
      class="max-h-[90vh] w-full overflow-y-auto rounded-xl bg-white p-6 shadow-xl"
      @submit.prevent="emit('submit')"
    >
      <div class="flex items-start justify-between gap-3">
        <h2 id="page-dialog-title" class="text-lg font-bold">
          {{ editing ? 'Редактировать страницу' : 'Новая страница' }}
        </h2>
        <UiButton
          type="button"
          variant="ghost"
          size="sm"
          aria-label="Закрыть окно страницы"
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
          label="Адрес страницы (slug)"
          help="Латинские строчные буквы, цифры и дефисы."
          required
          ><UiInput
            v-model="form.slug"
            required
            :disabled="busy || (editing && systemPageSlugs.includes(form.slug))"
            maxlength="255"
            :pattern="slugPattern"
        /></UiField>
        <UiField
          label="Текст страницы"
          help="Обычный текст. HTML будет показан как текст."
          :required="!editing"
          ><UiTextarea
            v-model="form.body"
            :required="!editing"
            :disabled="busy"
            maxlength="100000"
            rows="12"
        /></UiField>
        <p class="text-sm text-gray-500">
          Сохранение меняет только черновик. Публикация доступна отдельным
          действием после сохранения.
        </p>
      </div>
      <div class="mt-6 flex flex-wrap justify-end gap-3">
        <UiButton
          type="button"
          variant="secondary"
          :disabled="busy"
          @click="emit('close')"
          >Отмена</UiButton
        >
        <UiButton :loading="busy">Сохранить черновик</UiButton>
      </div>
    </form>
  </UiDialog>
</template>
