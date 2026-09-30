<script setup lang="ts">
import { computed, onBeforeUnmount, watch } from 'vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiImagePreview from '../../../components/ui/UiImagePreview.vue'
import { useAuthStore } from '../../../stores/auth'
import { useInlineMediaUpload } from '../composables/useInlineMediaUpload'
import { useMediaOptions } from '../composables/useMediaOptions'
import type { Media } from '../types/media.types'
import MediaImagePreview from './MediaImagePreview.vue'

const props = defineProps<{
  kind: Media['kind']
  label: string
  modelValue: number | null | number[]
  disabled?: boolean
  inlineUpload?: boolean
  requireManagePermission?: boolean
}>()
const emit = defineEmits<{
  'update:modelValue': [value: number | null | number[]]
  pending: [value: boolean]
  uploading: [value: boolean]
}>()
const auth = useAuthStore()
const canManage = computed(() => auth.hasPermission('media.manage'))
const canPreview = computed(
  () =>
    auth.hasPermission('content.manage') ||
    auth.hasPermission('catalog.manage'),
)
const canSelect = computed(
  () => canManage.value || (!props.requireManagePermission && canPreview.value),
)
const { options, loading, error, load } = useMediaOptions(
  props.kind,
  () => canSelect.value || (canPreview.value && Boolean(props.modelValue)),
)
const upload = useInlineMediaUpload(props.kind, (media) => {
  options.value = [
    media,
    ...options.value.filter((item) => item.id !== media.id),
  ]
  emit(
    'update:modelValue',
    props.kind === 'document' ? [...selected.value, media.id] : media.id,
  )
})
watch(
  () => upload.uploading.value || Boolean(upload.file.value),
  (value) => emit('pending', value),
)
const preview = computed(() =>
  options.value.find((item) => item.id === props.modelValue),
)
watch(upload.uploading, (value) => emit('uploading', value))
onBeforeUnmount(() => {
  emit('pending', false)
  emit('uploading', false)
})
const selected = computed(() =>
  Array.isArray(props.modelValue) ? props.modelValue : [],
)

function toggle(id: number, checked: boolean) {
  emit(
    'update:modelValue',
    checked
      ? [...selected.value, id]
      : selected.value.filter((value) => value !== id),
  )
}
</script>

<template>
  <div class="min-w-0">
    <p class="text-sm font-medium text-gray-700">{{ label }}</p>
    <template v-if="!canSelect">
      <p class="mt-1 text-sm text-gray-500" role="status">
        {{ modelValue ? `Выбран файл #${modelValue}.` : 'Файл не выбран.' }}
        Выбор и загрузка доступны сотруднику с правом управления медиа.
      </p>
      <p
        v-if="loading && modelValue"
        class="mt-1 text-sm text-gray-500"
        role="status"
      >
        Загрузка выбранного изображения…
      </p>
      <p v-else-if="error" class="mt-1 text-sm text-error-600" role="alert">
        {{ error }}
      </p>
      <UiImagePreview
        v-if="kind === 'image' && modelValue && !loading"
        :url="preview?.thumbnail_url || preview?.url || null"
        :alt="preview?.alt || label"
        class="mt-2 max-w-sm"
      />
    </template>
    <p v-else-if="loading" class="mt-1 text-sm text-gray-500" role="status">
      Загрузка файлов…
    </p>
    <div v-else-if="error" class="mt-1">
      <p class="text-sm text-error-600" role="alert">{{ error }}</p>
      <UiButton
        type="button"
        variant="secondary"
        size="sm"
        class="mt-2"
        @click="load"
        >Повторить загрузку</UiButton
      >
    </div>
    <template v-else-if="kind === 'image'">
      <UiSelect
        :model-value="modelValue === null ? '' : String(modelValue)"
        :options="[
          { label: 'Без изображения', value: '' },
          ...options.map((item) => ({
            label: `${item.title} (#${item.id})`,
            value: String(item.id),
          })),
        ]"
        :accessible-name="label"
        searchable
        teleport-menu
        :disabled="disabled || upload.uploading.value"
        @update:model-value="
          emit('update:modelValue', $event ? Number($event) : null)
        "
      />
      <UiImagePreview
        v-if="inlineUpload"
        :url="preview?.thumbnail_url || preview?.url || null"
        :alt="preview?.alt || label"
        class="mt-2 max-w-sm"
      />
      <MediaImagePreview
        v-else-if="preview"
        :url="preview.thumbnail_url || preview.url"
        :alt="preview.alt || label"
        class="mt-2 h-20 w-20 max-w-full"
      />
      <p
        v-if="modelValue && !preview"
        class="mt-1 text-sm text-gray-500"
        role="status"
      >
        Выбранный файл #{{ modelValue }} отсутствует в доступном списке.
        Выберите другой файл или обновите список.
      </p>
      <p
        v-if="!options.length"
        class="mt-1 text-sm text-gray-500"
        role="status"
      >
        Изображений пока нет.
      </p>
    </template>
    <div
      v-else
      class="mt-2 max-h-44 space-y-2 overflow-y-auto rounded-lg border border-gray-200 p-3"
    >
      <p v-if="!options.length" class="text-sm text-gray-500" role="status">
        Документов пока нет.
      </p>
      <div
        v-for="item in options"
        :key="item.id"
        class="flex flex-wrap items-center gap-2"
      >
        <UiCheckbox
          mode="boolean"
          :checked="selected.includes(item.id)"
          :disabled="disabled"
          :accessible-name="`${item.title}, файл ${item.id}`"
          class="min-w-0 flex-1"
          @update:checked="toggle(item.id, $event)"
          >{{ item.title }} · #{{ item.id }}</UiCheckbox
        >
        <a
          :href="item.url"
          target="_blank"
          rel="noopener noreferrer"
          :aria-label="`Открыть документ ${item.title}, файл ${item.id}`"
          class="text-sm text-primary-700 underline focus-visible:outline-2 focus-visible:outline-primary-500"
          >Открыть</a
        >
      </div>
    </div>
    <div
      v-if="canManage && inlineUpload"
      class="mt-4 space-y-3 rounded-lg border border-gray-200 p-3"
    >
      <label class="block text-sm font-medium text-gray-700"
        >Загрузить файл — {{ label }}
        <input
          :key="upload.inputKey.value"
          type="file"
          :accept="
            kind === 'image'
              ? 'image/jpeg,image/png,image/webp'
              : 'application/pdf'
          "
          :disabled="disabled || upload.uploading.value"
          class="mt-2 block w-full min-w-0 text-sm"
          @change="upload.choose"
        />
      </label>
      <template v-if="upload.file.value">
        <UiField label="Название файла" required
          ><UiInput
            v-model="upload.title.value"
            maxlength="255"
            :disabled="disabled || upload.uploading.value"
        /></UiField>
        <UiField
          v-if="kind === 'image'"
          label="Описание загружаемого изображения"
          required
          ><UiInput
            v-model="upload.alt.value"
            maxlength="255"
            :disabled="disabled || upload.uploading.value"
        /></UiField>
        <UiButton
          type="button"
          variant="secondary"
          size="sm"
          :loading="upload.uploading.value"
          :disabled="disabled"
          @click="upload.upload"
          >Загрузить и выбрать</UiButton
        >
        <UiButton
          type="button"
          variant="ghost"
          size="sm"
          :disabled="upload.uploading.value"
          @click="upload.cancel"
          >Отменить выбор файла</UiButton
        >
      </template>
      <p v-if="upload.error.value" class="text-sm text-error-600" role="alert">
        {{ upload.error.value }}
      </p>
      <p
        v-if="upload.success.value"
        class="text-sm text-gray-600"
        role="status"
      >
        {{ upload.success.value }}
      </p>
    </div>
    <p v-else-if="canManage" class="mt-1 text-xs text-gray-500">
      Загрузка новых файлов доступна в разделе «Медиатека».
    </p>
  </div>
</template>
