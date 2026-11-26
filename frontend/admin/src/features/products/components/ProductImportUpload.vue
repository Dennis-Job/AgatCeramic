<script setup lang="ts">
import { computed, useTemplateRef } from 'vue'
import { FileArchive, FileSpreadsheet, Upload } from '@lucide/vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiFileDropzone from '../../../components/ui/UiFileDropzone.vue'

defineOptions({ inheritAttrs: false })
const props = defineProps<{
  format: 'xlsx' | 'zip'
  file: File | null
  busy: boolean
  disabled?: boolean
}>()
const emit = defineEmits<{
  select: [file: File | null]
  upload: []
}>()
const input = useTemplateRef('input')
const formats = {
  xlsx: {
    inputId: 'product-import-file',
    accept:
      '.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    name: 'Заполненный шаблон',
    title: '3. Загрузите заполненный файл',
    choose: 'Выберите или перетащите Excel в эту область',
    drop: 'Отпустите файл Excel для добавления',
    description: 'Формат — XLSX. Размер — не больше 10 МБ.',
    help: 'Корректные товары сохранятся, строки с ошибками можно будет исправить и загрузить повторно.',
    dropzone: { 'data-product-excel-dropzone': '' },
    tone: 'green',
    icon: FileSpreadsheet,
    submit: 'Загрузить',
  },
  zip: {
    inputId: 'image-import-file',
    accept: '.zip,application/zip',
    name: 'ZIP-архив с изображениями',
    title: '2. Загрузите архив',
    choose: 'Выберите или перетащите ZIP-архив в эту область',
    drop: 'Отпустите ZIP-архив для добавления',
    description: 'Формат — ZIP. Размер — не больше 500 МБ.',
    help: 'Нажмите «Загрузить архив», чтобы начать импорт подготовленных изображений.',
    dropzone: { 'data-product-zip-dropzone': '' },
    tone: 'yellow',
    icon: FileArchive,
    submit: 'Загрузить архив',
  },
} as const
const presentation = computed(() => formats[props.format])
const selectionDisabled = computed(() => props.busy || props.disabled)

function selectFiles(files: FileList | null) {
  if (!selectionDisabled.value) emit('select', files?.[0] ?? null)
}

function selectFromInput(event: Event) {
  const target = event.target as HTMLInputElement
  selectFiles(target.files)
  target.value = ''
}
</script>

<template>
  <form @submit.prevent="emit('upload')">
    <UiCard class="h-full" v-bind="$attrs">
      <template #header>
        <h2 class="text-base font-semibold text-gray-500">
          {{ presentation.title }}
        </h2>
      </template>
      <input
        :id="presentation.inputId"
        ref="input"
        type="file"
        :accept="presentation.accept"
        :aria-label="presentation.name"
        tabindex="-1"
        :disabled="selectionDisabled"
        class="hidden"
        @change="selectFromInput"
      />
      <UiFileDropzone
        v-bind="presentation.dropzone"
        :label="presentation.choose"
        :drop-label="presentation.drop"
        :description="presentation.description"
        :format-label="format.toUpperCase()"
        :icon-tone="presentation.tone"
        :description-id="`${presentation.inputId}-help`"
        :disabled="selectionDisabled"
        @choose="input?.click()"
        @files="selectFiles"
      >
        <template #icon>
          <component :is="presentation.icon" :size="44" :stroke-width="1.4" />
        </template>
      </UiFileDropzone>
      <output
        :id="`${presentation.inputId}-selection`"
        :class="
          file
            ? 'mt-3 block min-w-0 text-sm text-gray-500 [overflow-wrap:anywhere]'
            : 'sr-only'
        "
        aria-live="polite"
      >
        <template v-if="file">
          <span class="block font-semibold">{{ file.name }}</span>
          <span class="mt-1 block text-xs">
            {{
              (file.size / 1024).toLocaleString('ru', {
                maximumFractionDigits: 1,
              })
            }}
            КБ · Нажмите на область, чтобы заменить файл.
          </span>
        </template>
        <template v-else>Файл не выбран</template>
      </output>
      <p
        :id="`${presentation.inputId}-help`"
        class="mt-4 text-sm leading-6 text-gray-500"
      >
        {{ presentation.help }}
      </p>
      <div class="mt-4 flex justify-end">
        <UiButton
          type="submit"
          :loading="busy"
          :disabled="selectionDisabled || !file"
        >
          <Upload :size="18" aria-hidden="true" />
          {{ busy ? 'Загрузка…' : presentation.submit }}
        </UiButton>
      </div>
    </UiCard>
  </form>
</template>
