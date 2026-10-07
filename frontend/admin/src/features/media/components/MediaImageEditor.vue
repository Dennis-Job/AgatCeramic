<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue'
import { FileImage, Trash2, X } from '@lucide/vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiFileDropzone from '../../../components/ui/UiFileDropzone.vue'
import UiImagePreview from '../../../components/ui/UiImagePreview.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import { useAuthStore } from '../../../stores/auth'
import { useInlineMediaUpload } from '../composables/useInlineMediaUpload'
import { useMediaOptions } from '../composables/useMediaOptions'

const props = defineProps<{
  label: string
  modelValue: number | null
  disabled?: boolean
  uploadSuccessMessage?: string
}>()
const emit = defineEmits<{
  'update:modelValue': [value: number | null]
  pending: [value: boolean]
  uploading: [value: boolean]
  previewOpen: [value: boolean]
}>()
const auth = useAuthStore()
const canUpload = computed(() => auth.hasPermission('media.manage'))
const canSelect = computed(
  () =>
    canUpload.value ||
    auth.hasPermission('catalog.manage') ||
    auth.hasPermission('content.manage'),
)
const { options, loading, error, load } = useMediaOptions('image', canSelect)
const input = ref<HTMLInputElement | null>(null)
const libraryOpen = ref(false)
const libraryButton = ref<InstanceType<typeof UiButton> | null>(null)
const viewerOpen = ref(false)
const viewerFailed = ref(false)
const viewerTitleId = useId()
const noImage = ref(props.modelValue === null)
const noImageControl = ref<InstanceType<typeof UiCheckbox> | null>(null)
const previousImage = ref(props.modelValue)
const preview = computed(() =>
  options.value.find((item) => item.id === props.modelValue),
)
const upload = useInlineMediaUpload(
  'image',
  (media) => {
    options.value = [
      media,
      ...options.value.filter((item) => item.id !== media.id),
    ]
    emit('update:modelValue', media.id)
  },
  props.uploadSuccessMessage,
)
const controlsDisabled = computed(
  () => props.disabled || upload.uploading.value,
)
watch(
  () => props.modelValue,
  (value) => {
    if (value !== null) previousImage.value = value
    noImage.value = value === null && !upload.file.value
  },
)
watch(
  () => Boolean(upload.file.value) || upload.uploading.value,
  (value) => emit('pending', value),
)
watch(upload.uploading, (value) => emit('uploading', value))
watch(
  viewerOpen,
  (value) => {
    viewerFailed.value = false
    if (value) upload.success.value = ''
    emit('previewOpen', value)
  },
  { flush: 'sync' },
)
onBeforeUnmount(() => {
  emit('pending', false)
  emit('uploading', false)
  emit('previewOpen', false)
})
function toggleNoImage(value: boolean) {
  if (controlsDisabled.value) return
  noImage.value = value
  if (value) {
    upload.cancel()
    upload.success.value = ''
    emit('update:modelValue', null)
  } else if (previousImage.value !== null) {
    emit('update:modelValue', previousImage.value)
  }
}
async function selectImage(value: string) {
  noImage.value = value === ''
  emit('update:modelValue', value ? Number(value) : null)
  libraryOpen.value = false
  await nextTick()
  libraryButton.value?.$el.focus()
}
async function removeImage() {
  if (controlsDisabled.value || !canSelect.value) return
  toggleNoImage(true)
  await nextTick()
  noImageControl.value?.$el.querySelector('input')?.focus()
}
function chooseFiles(files: FileList | null) {
  if (controlsDisabled.value || !canUpload.value || !files?.length) return
  const file = files[0]!
  if (
    !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ||
    file.size > 10 * 1024 * 1024
  ) {
    upload.error.value = 'Выберите JPEG, PNG или WebP размером не больше 10 МБ.'
    if (input.value) input.value.value = ''
    return
  }
  upload.chooseFile(file)
  noImage.value = false
}
function cancelFile() {
  upload.cancel()
  noImage.value = props.modelValue === null
}
</script>

<template>
  <div class="min-w-0 space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <p class="text-sm font-medium text-gray-500">{{ label }}</p>
      <UiCheckbox
        v-if="canSelect"
        ref="noImageControl"
        mode="boolean"
        :checked="noImage"
        :disabled="controlsDisabled"
        @update:checked="toggleNoImage"
        >Без изображения</UiCheckbox
      >
    </div>
    <div class="grid items-start gap-4 sm:grid-cols-[8rem_minmax(0,1fr)]">
      <div class="min-w-0">
        <div v-if="preview" class="image-editor-photo">
          <UiButton
            type="button"
            variant="surface"
            class="image-editor-preview"
            aria-label="Увеличить изображение категории"
            @click="viewerOpen = true"
          >
            <UiImagePreview
              :url="preview.thumbnail_url || preview.url"
              :alt="preview.alt || label"
            />
          </UiButton>
          <UiButton
            v-if="canSelect"
            type="button"
            variant="danger-ghost"
            size="sm"
            class="image-editor-remove"
            aria-label="Убрать изображение категории"
            tooltip="Убрать изображение"
            :disabled="controlsDisabled"
            @click.stop="removeImage"
            ><Trash2 :size="18" aria-hidden="true"
          /></UiButton>
        </div>
        <UiImagePreview
          v-else
          :url="null"
          :alt="label"
          class="image-editor-preview"
        />
        <p
          v-if="modelValue && !preview && !loading"
          class="mt-2 text-xs text-gray-500"
          role="status"
        >
          Выбран файл #{{ modelValue }}. Предпросмотр недоступен.
        </p>
      </div>
      <div class="min-w-0 space-y-3">
        <template v-if="canUpload">
          <input
            ref="input"
            :key="upload.inputKey.value"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            class="sr-only"
            tabindex="-1"
            :aria-label="`Загрузить файл — ${label}`"
            :disabled="controlsDisabled"
            @change="chooseFiles(($event.target as HTMLInputElement).files)"
          />
          <UiFileDropzone
            label="Выберите или перетащите фото в эту область"
            drop-label="Отпустите фото для добавления"
            description="Формат — JPEG, JPG, PNG, WebP. Размер — не больше 10 МБ."
            format-label="IMG"
            :disabled="controlsDisabled"
            @choose="input?.click()"
            @files="chooseFiles"
          >
            <template #icon
              ><FileImage :size="44" :stroke-width="1.4"
            /></template>
          </UiFileDropzone>
          <template v-if="upload.file.value">
            <output
              class="block break-words text-xs text-gray-500 [overflow-wrap:anywhere]"
              aria-live="polite"
              >{{ upload.file.value.name }}</output
            >
            <UiField label="Название файла" required
              ><UiInput
                v-model="upload.title.value"
                maxlength="255"
                :disabled="controlsDisabled"
            /></UiField>
            <UiField label="Описание загружаемого изображения" required
              ><UiInput
                v-model="upload.alt.value"
                maxlength="255"
                :disabled="controlsDisabled"
            /></UiField>
            <div class="flex flex-wrap gap-2">
              <UiButton
                type="button"
                size="sm"
                :loading="upload.uploading.value"
                :disabled="disabled || loading"
                @click="upload.upload"
                >Загрузить и выбрать</UiButton
              >
              <UiButton
                type="button"
                variant="surface"
                size="sm"
                :disabled="controlsDisabled"
                @click="cancelFile"
                >Отменить выбор файла</UiButton
              >
            </div>
          </template>
        </template>
        <UiButton
          v-if="canSelect"
          ref="libraryButton"
          type="button"
          variant="surface"
          size="sm"
          :disabled="controlsDisabled || loading || Boolean(upload.file.value)"
          :aria-expanded="libraryOpen"
          @click="libraryOpen = !libraryOpen"
          >{{
            libraryOpen ? 'Скрыть список изображений' : 'Выбрать из загруженных'
          }}</UiButton
        >
        <UiSelect
          v-if="libraryOpen"
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
          :disabled="controlsDisabled"
          @update:model-value="selectImage"
        />
        <p
          v-if="libraryOpen && !options.length"
          class="text-xs text-gray-500"
          role="status"
        >
          Изображений пока нет.
        </p>
        <p v-if="loading" class="text-xs text-gray-500" role="status">
          Загрузка файлов…
        </p>
        <template v-if="error && !viewerOpen">
          <UiNotification>{{ error }}</UiNotification>
          <UiButton
            type="button"
            variant="secondary"
            size="sm"
            :disabled="controlsDisabled"
            @click="load"
            >Повторить загрузку</UiButton
          >
        </template>
        <UiNotification v-if="upload.error.value && !viewerOpen">{{
          upload.error.value
        }}</UiNotification>
        <UiNotification v-if="upload.success.value" tone="success">{{
          upload.success.value
        }}</UiNotification>
      </div>
    </div>
    <Teleport to="body">
      <UiDialog
        :open="viewerOpen"
        :labelledby="viewerTitleId"
        overlay-class="z-[60] grid place-items-center"
        panel-class="media-image-viewer flex h-dvh w-full flex-col bg-white p-4 sm:p-6"
        @close="viewerOpen = false"
      >
        <div class="flex shrink-0 items-center justify-between gap-4">
          <h2 :id="viewerTitleId" class="text-lg font-bold text-gray-500">
            Просмотр изображения
          </h2>
          <UiButton
            type="button"
            variant="surface"
            aria-label="Закрыть просмотр изображения"
            @click="viewerOpen = false"
            ><X :size="20"
          /></UiButton>
        </div>
        <div class="mt-4 flex min-h-0 flex-1 items-center justify-center">
          <img
            v-if="preview && !viewerFailed"
            :src="preview.url"
            :alt="preview.alt || label"
            class="h-full w-full object-contain"
            @error="viewerFailed = true"
          />
          <UiAlert v-else tone="error"
            >Не удалось загрузить изображение.</UiAlert
          >
        </div>
      </UiDialog>
    </Teleport>
  </div>
</template>

<style scoped>
:global(
  .admin-dialog-content.media-image-viewer:not(.admin-navigation-dialog-panel)
) {
  max-height: 100dvh;
  border-radius: 0;
}
.image-editor-preview {
  display: flex;
  width: calc(var(--admin-spacing-4) * 8);
  max-width: 100%;
  padding: 0;
  aspect-ratio: 1;
}
.image-editor-preview :deep(img) {
  object-fit: contain;
}
.image-editor-preview :deep(.aspect-\[16\/9\]) {
  aspect-ratio: 1;
  height: 100%;
}
.image-editor-photo {
  position: relative;
  width: calc(var(--admin-spacing-4) * 8);
  max-width: 100%;
}
.image-editor-remove {
  position: absolute;
  top: var(--admin-spacing-1);
  right: var(--admin-spacing-1);
  width: var(--admin-control-height-sm);
  height: var(--admin-control-height-sm);
  padding: 0;
  background: var(--color-white);
  opacity: 0;
  pointer-events: none;
}
.image-editor-photo:hover .image-editor-remove,
.image-editor-photo:focus-within .image-editor-remove {
  opacity: 1;
  pointer-events: auto;
}
@media (hover: none) {
  .image-editor-remove {
    opacity: 1;
    pointer-events: auto;
  }
}
</style>
