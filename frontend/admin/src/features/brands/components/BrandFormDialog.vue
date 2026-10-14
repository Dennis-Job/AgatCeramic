<script setup lang="ts">
import { ref } from 'vue'
import { X } from '@lucide/vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import UiDialogFooter from '../../../components/ui/UiDialogFooter.vue'
import { COUNTRY_OPTIONS } from '../../../constants/countries'
import type { BrandPayload } from '../types/brand.types'
import { slugPattern } from '../../catalog/validation/slug'
import MediaImageEditor from '../../media/components/MediaImageEditor.vue'
import MediaReferenceField from '../../media/components/MediaReferenceField.vue'

const imagePreviewOpen = ref(false)
defineProps<{
  open: boolean
  title: string
  busy: boolean
  mediaPending: boolean
  mediaUploading: boolean
  error: string
  form: BrandPayload
}>()
const emit = defineEmits<{
  close: []
  submit: []
  updateName: [string]
  updateSlug: [string]
  mediaPending: [boolean]
  mediaUploading: [boolean]
}>()
</script>

<template>
  <UiDialog
    :open="open"
    :suspended="imagePreviewOpen"
    labelledby="brand-dialog-title"
    describedby="brand-dialog-description"
    :close-disabled="busy || mediaUploading"
    panel-class="w-full max-w-3xl"
    @close="emit('close')"
  >
    <form
      class="flex max-h-[90dvh] w-full flex-col rounded-xl bg-white p-6 shadow-xl"
      @submit.prevent="emit('submit')"
    >
      <div class="flex shrink-0 items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
          <h2
            id="brand-dialog-title"
            class="break-words text-lg font-bold text-gray-500 [overflow-wrap:anywhere]"
          >
            {{ title }}
          </h2>
        </div>
        <UiButton
          type="button"
          variant="surface"
          size="sm"
          aria-label="Закрыть окно бренда"
          :disabled="busy || mediaUploading"
          @click="emit('close')"
          ><X :size="20"
        /></UiButton>
      </div>
      <div
        class="-m-[var(--admin-focus-halo-width)] min-h-0 overflow-y-auto overscroll-contain p-[var(--admin-focus-halo-width)]"
      >
        <UiNotification v-if="error">{{ error }}</UiNotification>
        <fieldset
          :disabled="busy || mediaUploading"
          class="mt-6 grid min-w-0 gap-4"
        >
          <legend class="sr-only">Информация о бренде</legend>
          <div class="grid gap-4 sm:grid-cols-2">
            <label class="text-sm font-medium text-gray-500"
              >Название<UiInput
                :model-value="form.name"
                class="mt-1.5"
                required
                data-autofocus
                @update:model-value="emit('updateName', $event)"
            /></label>
            <label class="text-sm font-medium text-gray-500"
              >Технический код (slug)<UiInput
                :model-value="form.slug"
                class="mt-1.5"
                required
                :pattern="slugPattern"
                @update:model-value="emit('updateSlug', $event)"
            /></label>
          </div>
          <label class="text-sm font-medium text-gray-500"
            >Описание<UiTextarea
              v-model="form.description"
              class="mt-1.5 font-normal"
          /></label>
          <MediaImageEditor
            label="Логотип бренда"
            preview-label="Увеличить логотип бренда"
            remove-label="Убрать логотип бренда"
            upload-success-message="Логотип загружен и выбран. Сохраните бренд, чтобы применить его."
            :disabled="busy"
            :model-value="form.logo_id"
            @pending="emit('mediaPending', $event)"
            @uploading="emit('mediaUploading', $event)"
            @preview-open="imagePreviewOpen = $event"
            @update:model-value="form.logo_id = $event"
          />
          <label class="text-sm font-medium text-gray-500"
            >Страна<UiSelect
              :model-value="form.country_code ?? ''"
              class="mt-1.5 w-full font-normal"
              :options="[
                { label: 'Страна не указана', value: '' },
                ...COUNTRY_OPTIONS,
              ]"
              accessible-name="Страна происхождения"
              searchable
              teleport-menu
              :disabled="busy || mediaUploading"
              @update:model-value="form.country_code = $event || null"
          /></label>
          <MediaReferenceField
            kind="document"
            label="Документы бренда"
            :model-value="form.document_ids"
            :disabled="busy || mediaUploading"
            @update:model-value="
              form.document_ids = Array.isArray($event) ? $event : []
            "
          />
          <UiCheckbox
            mode="boolean"
            :checked="form.is_active"
            :disabled="busy || mediaUploading"
            @update:checked="form.is_active = $event"
            >Бренд активен</UiCheckbox
          >
        </fieldset>
      </div>
      <UiDialogFooter>
        <template #note
          ><span id="brand-dialog-description">{{
            mediaUploading
              ? 'Логотип загружается…'
              : mediaPending
                ? 'Загрузите выбранный логотип или отмените выбор файла перед сохранением.'
                : 'Настройте название, логотип и информацию о бренде.'
          }}</span></template
        >
        <div class="flex shrink-0 items-center gap-3">
          <UiButton
            type="button"
            variant="surface"
            :disabled="busy || mediaUploading"
            @click="emit('close')"
            >Отмена</UiButton
          >
          <UiButton :loading="busy" :disabled="mediaPending || mediaUploading"
            >Сохранить</UiButton
          >
        </div>
      </UiDialogFooter>
    </form>
  </UiDialog>
</template>
