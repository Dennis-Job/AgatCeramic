<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import { CheckCircle2, CircleAlert, Download, Upload, X } from '@lucide/vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import { useProductGroupImport } from '../composables/useProductGroupImport'

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: []; completed: [] }>()
const input = ref<HTMLInputElement | null>(null)
const workflow = useProductGroupImport(() => emit('completed'))
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
watch([busy, downloading], async () => {
  await nextTick()
  if (
    props.open &&
    (document.activeElement === document.body ||
      document.activeElement?.matches(':disabled'))
  )
    document.getElementById('group-import-template')?.focus()
})
</script>

<template>
  <UiDialog
    :open="open"
    labelledby="group-import-title"
    describedby="group-import-description"
    panel-class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl bg-white shadow-card"
    @close="emit('close')"
  >
    <header
      class="flex items-start justify-between gap-4 border-b border-gray-200 p-5 sm:px-7"
    >
      <div>
        <h2 id="group-import-title" class="text-xl font-bold text-gray-900">
          Группы вариантов из Excel
        </h2>
        <p id="group-import-description" class="mt-1 text-sm text-gray-500">
          Массовое управление составом и осями групп без изменения товаров.
        </p>
      </div>
      <UiButton
        type="button"
        variant="ghost"
        size="sm"
        aria-label="Закрыть окно импорта групп"
        @click="emit('close')"
        ><X :size="20" aria-hidden="true"
      /></UiButton>
    </header>
    <div
      class="min-h-0 space-y-5 overflow-y-auto overscroll-contain p-5 sm:p-7"
    >
      <section
        class="rounded-xl border border-gray-200 p-4 sm:p-5"
        aria-labelledby="group-import-preparation"
      >
        <h3
          id="group-import-preparation"
          class="text-base font-semibold text-gray-900"
        >
          1. Скачайте актуальную выгрузку
        </h3>
        <p class="mt-1 text-sm leading-6 text-gray-500">
          На листе «Группы» выберите действие: создать, изменить или
          расформировать. На листе «Состав» укажите полный итоговый список SKU
          для создаваемой или изменяемой группы. Товар можно перенести между
          группами одним файлом.
        </p>
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
      </section>
      <form
        class="rounded-xl border border-gray-200 p-4 sm:p-5"
        @submit.prevent="upload"
      >
        <h3 class="text-base font-semibold text-gray-900">
          2. Загрузите отредактированный файл
        </h3>
        <p id="group-import-file-help" class="mt-1 text-sm text-gray-500">
          XLSX до 10 МБ. Если в файле есть ошибка, изменения из него не
          применяются.
        </p>
        <input
          ref="input"
          class="hidden"
          type="file"
          accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
          aria-label="Файл Excel для импорта групп вариантов"
          @change="selectFile"
        />
        <p
          id="group-import-file-selection"
          class="sr-only"
          role="status"
          aria-live="polite"
        >
          {{ file ? `Выбран файл: ${file.name}` : 'Файл не выбран' }}
        </p>
        <UiButton
          type="button"
          variant="secondary"
          class="mt-4 flex w-full justify-start text-left"
          :disabled="busy"
          aria-describedby="group-import-file-help group-import-file-selection"
          @click="input?.click()"
          ><Upload :size="18" class="shrink-0" aria-hidden="true" /><span
            class="break-all"
            >{{ file?.name ?? 'Выбрать файл XLSX' }}</span
          ></UiButton
        ><UiButton
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
        ><UiAlert
          v-if="busy && !uploading"
          class="mt-3"
          tone="info"
          live="polite"
          >Файл обрабатывается в фоне. Окно можно закрыть.</UiAlert
        >
      </form>
      <UiAlert v-if="error">{{ error }}</UiAlert
      ><UiAlert v-if="notice" tone="success" live="polite">{{
        notice
      }}</UiAlert>
      <section
        v-if="result"
        class="rounded-xl border p-4 sm:p-5"
        :class="resultClass"
        aria-live="polite"
      >
        <h3 class="flex items-center gap-2 font-semibold text-gray-900">
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
        </h3>
        <p class="mt-2 text-sm text-gray-700">
          Обработано групп: {{ result.processed_rows }} из
          {{ result.total_rows }}. Изменено: {{ result.updated_rows }}. Ошибок:
          {{ result.failed_rows }}.
        </p>
        <UiAlert
          v-if="result.status === 'failed' && result.error_message"
          class="mt-3"
          >{{ result.error_message }}</UiAlert
        ><progress
          v-if="progress !== null"
          class="mt-3 h-2 w-full overflow-hidden rounded-full"
          :value="Math.min(progress, 100)"
          max="100"
          aria-label="Ход обработки групп"
        >
          {{ progress }}%</progress
        ><UiAlert v-if="pollingError" class="mt-3"
          >Не удалось обновить статус.
          <UiButton
            type="button"
            variant="danger-ghost"
            size="sm"
            @click="poll(result!.id)"
            >Повторить</UiButton
          ></UiAlert
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
  </UiDialog>
</template>
