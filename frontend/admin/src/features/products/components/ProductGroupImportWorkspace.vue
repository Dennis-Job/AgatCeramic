<script setup lang="ts">
import { nextTick, onActivated, onDeactivated, ref, watch } from 'vue'
import {
  CheckCircle2,
  CircleAlert,
  Download,
  FileSpreadsheet,
  Upload,
} from '@lucide/vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiFileDropzone from '../../../components/ui/UiFileDropzone.vue'
import AdminWorkspace from '../../../components/shared/AdminWorkspace.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import { useProductGroupImport } from '../composables/useProductGroupImport'

const active = ref(true)
onActivated(() => {
  active.value = true
})
onDeactivated(() => {
  active.value = false
})
const input = ref<HTMLInputElement | null>(null)
const workflow = useProductGroupImport(() => {})
const {
  file,
  result,
  downloading,
  uploading,
  error,
  notice,
  pollingError,
  busy,
  finished,
  progress,
  resultClass,
  downloadTemplate,
  poll,
  upload,
} = workflow
function selectFile(event: Event) {
  const target = event.target as HTMLInputElement
  workflow.selectFile(target.files?.[0] ?? null)
  target.value = ''
}
function selectDroppedFile(files: FileList) {
  if (busy.value) return
  workflow.selectFile(files[0] ?? null)
  if (input.value) input.value.value = ''
}
watch([busy, downloading], async () => {
  await nextTick()
  if (
    active.value &&
    (document.activeElement === document.body ||
      document.activeElement?.matches(':disabled'))
  )
    document.getElementById('group-import-template')?.focus()
})
</script>

<template>
  <AdminWorkspace mode="form">
    <PageHeader
      class="mb-7"
      eyebrow="Товары"
      title="Объединить товары"
      description="Объединение товаров в группы вариантов и управление их составом из Excel."
    />
    <div class="min-w-0 space-y-5">
      <UiCard aria-labelledby="group-import-preparation">
        <template #header>
          <h2 id="group-import-preparation" class="font-semibold text-gray-500">
            1. Скачайте актуальную выгрузку
          </h2>
        </template>
        <UiAlert tone="info" live="polite" class="mt-3">
          На листе «Группы» выберите действие: создать, изменить или
          расформировать. На листе «Состав» укажите полный итоговый список SKU
          для создаваемой или изменяемой группы. Товар можно перенести между
          группами одним файлом.
        </UiAlert>
        <UiButton
          id="group-import-template"
          type="button"
          variant="secondary"
          class="mt-4"
          :loading="downloading"
          :disabled="busy"
          @click="downloadTemplate()"
          ><Download :size="18" aria-hidden="true" />{{
            downloading ? 'Скачиваем…' : 'Скачать Excel с группами'
          }}</UiButton
        >
      </UiCard>
      <UiCard aria-labelledby="group-import-upload-title">
        <template #header>
          <div>
            <h2
              id="group-import-upload-title"
              class="font-semibold text-gray-500"
            >
              2. Загрузите отредактированный файл
            </h2>
          </div>
        </template>
        <form @submit.prevent="upload">
          <input
            ref="input"
            class="hidden"
            type="file"
            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
            aria-label="Файл Excel для импорта групп вариантов"
            @change="selectFile"
          />
          <UiFileDropzone
            data-group-import-excel-dropzone
            label="Выберите или перетащите Excel в эту область"
            drop-label="Отпустите файл Excel для добавления"
            description="XLSX до 10 МБ. Если в файле есть ошибка, изменения из него не применяются."
            format-label="XLSX"
            icon-tone="green"
            description-id="group-import-file-selection"
            :disabled="busy"
            @choose="input?.click()"
            @files="selectDroppedFile"
          >
            <template #icon>
              <FileSpreadsheet :size="44" :stroke-width="1.4" />
            </template>
          </UiFileDropzone>
          <output
            v-if="file"
            id="group-import-file-selection"
            class="mt-3 block min-w-0 text-sm text-gray-500 [overflow-wrap:anywhere]"
            aria-live="polite"
          >
            <span class="block font-semibold">{{ file.name }}</span>
            <span class="mt-1 block text-xs">
              {{
                (file.size / 1024).toLocaleString('ru', {
                  maximumFractionDigits: 1,
                })
              }}
              КБ · Нажмите на область, чтобы заменить файл.
            </span>
          </output>
          <output
            v-else
            id="group-import-file-selection"
            class="sr-only"
            aria-live="polite"
            >Файл не выбран</output
          >
          <UiButton
            type="submit"
            class="mt-4"
            :loading="uploading"
            :disabled="!file || busy"
            ><Upload :size="18" aria-hidden="true" />{{
              uploading
                ? 'Загружаем…'
                : busy
                  ? 'Обработка…'
                  : 'Запустить обработку'
            }}</UiButton
          ><UiNotification
            v-if="active && busy && !uploading"
            tone="info"
            live="polite"
            >Файл обрабатывается в фоне. Можно перейти на другую страницу и
            вернуться в «Объединить товары» за результатом.</UiNotification
          >
        </form>
      </UiCard>
      <UiNotification v-if="active && error">{{ error }}</UiNotification
      ><UiNotification v-if="active && notice" tone="success" live="polite">{{
        notice
      }}</UiNotification>
      <section
        v-if="result"
        class="admin-panel--state p-4 sm:p-5"
        :class="resultClass"
        aria-live="polite"
      >
        <h2 class="flex items-center gap-2 font-semibold text-gray-500">
          <CircleAlert
            v-if="result.status === 'failed'"
            :size="20"
            class="text-error-500"
            aria-hidden="true"
          /><CheckCircle2
            v-else-if="finished"
            :size="20"
            aria-hidden="true"
          />{{
            result.status === 'failed'
              ? 'Обработка не завершена'
              : finished
                ? result.failed_rows
                  ? 'Обработка завершена с ошибками'
                  : 'Обработка завершена'
                : 'Обработка выполняется'
          }}
        </h2>
        <p class="mt-2 text-sm text-gray-500">
          Обработано групп: {{ result.processed_rows }} из
          {{ result.total_rows }}. Изменено: {{ result.updated_rows }}. Ошибок:
          {{ result.failed_rows }}.
        </p>
        <UiNotification
          v-if="active && result.status === 'failed' && result.error_message"
          >{{ result.error_message }}</UiNotification
        ><progress
          v-if="progress !== null"
          class="mt-3 h-2 w-full overflow-hidden rounded-full"
          :value="Math.min(progress, 100)"
          max="100"
          aria-label="Ход обработки групп"
        >
          {{ progress }}%</progress
        ><UiNotification v-if="active && pollingError"
          >Не удалось обновить статус.
          <UiButton
            type="button"
            variant="danger-ghost"
            size="sm"
            @click="poll(result!.id)"
            >Повторить</UiButton
          ></UiNotification
        ><UiButton
          v-if="finished && result.has_error_file"
          type="button"
          variant="secondary"
          class="mt-4"
          :loading="downloading"
          @click="downloadTemplate(true)"
          ><Download :size="18" aria-hidden="true" />Скачать Excel с
          ошибками</UiButton
        >
      </section>
    </div>
  </AdminWorkspace>
</template>
