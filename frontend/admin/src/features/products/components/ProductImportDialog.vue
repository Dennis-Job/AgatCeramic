<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import { CheckCircle2, Download, ImagePlus, Upload, X } from '@lucide/vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiRadio from '../../../components/ui/UiRadio.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import { useProductImportDialog } from '../composables/useProductImportDialog'

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: []; completed: [] }>()
const input = ref<HTMLInputElement | null>(null)
const imageInput = ref<HTMLInputElement | null>(null)
const {
  tab, importMode, categoryId, categoriesLoading, categoryError, categoryOptions, successful, progress,
  imageProgress, statusText, productImport, imageImport, loadCategories, downloadTemplate, uploadProducts,
  downloadImageErrors,
} = useProductImportDialog(() => emit('completed'))
const {
  file, result, downloading, error, notice, pollingError, busy, finished,
  selectFile: selectProductFile, poll,
} = productImport
const {
  file: imageFile, result: imageResult, uploading: imageUploading, downloading: imageDownloading,
  error: imageError, pollingError: imagePollingError, busy: imageBusy, finished: imageFinished,
  selectFile: selectProductImageFile, upload: uploadImageImport, poll: pollImageImport,
} = imageImport

watch(() => props.open, open => { if (open && !categoryOptions.value.length) void loadCategories() })
watch([busy, downloading], async () => {
  await nextTick()
  if (props.open && tab.value === 'products' && (document.activeElement === document.body || document.activeElement?.matches(':disabled'))) {
    document.getElementById('import-tab-products')?.focus()
  }
})
watch([categoryId, importMode], () => { if (input.value) input.value.value = '' })
function selectFile(event: Event) {
  selectProductFile((event.target as HTMLInputElement).files?.[0] ?? null)
}
async function upload() {
  await uploadProducts()
}
function selectImageFile(event: Event) {
  const target = event.target as HTMLInputElement
  selectProductImageFile(target.files?.[0] ?? null)
  target.value = ''
}
function changeTab(event: KeyboardEvent) {
  if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return
  event.preventDefault()
  tab.value = event.key === 'Home' ? 'products' : event.key === 'End' ? 'images' : tab.value === 'products' ? 'images' : 'products'
  void nextTick(() => document.getElementById(`import-tab-${tab.value}`)?.focus())
}
</script>

<template>
  <UiDialog :open="open" labelledby="product-import-title" describedby="product-import-description" panel-class="flex w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-card" @close="emit('close')">
    <header class="flex shrink-0 items-start justify-between gap-4 border-b border-gray-200 p-5 sm:px-7">
      <div class="min-w-0">
        <h2 id="product-import-title" class="text-xl font-bold text-gray-900">Массовая загрузка</h2>
        <p id="product-import-description" class="mt-1 text-sm text-gray-500">{{ tab === 'products' ? 'Добавление и массовое редактирование товаров из Excel' : 'Массовая загрузка изображений товаров из ZIP-архива' }}</p>
      </div>
      <UiButton type="button" class="shrink-0" variant="ghost" size="sm" aria-label="Закрыть массовую загрузку" @click="emit('close')"><X :size="20" aria-hidden="true" /></UiButton>
    </header>
    <div class="min-h-0 overflow-y-auto overscroll-contain px-5 sm:px-7">
      <div class="grid grid-cols-2 gap-2 border-b border-gray-200" role="tablist" aria-label="Тип загрузки" @keydown="changeTab">
        <UiButton v-for="item in [{ id: 'products', label: 'Загрузка товаров' }, { id: 'images', label: 'Загрузка изображений' }]" :id="`import-tab-${item.id}`" :key="item.id" type="button" variant="ghost" role="tab" :aria-selected="tab === item.id" :aria-controls="`import-panel-${item.id}`" :tabindex="tab === item.id ? 0 : -1" class="rounded-none border-b-2 py-4" :class="tab === item.id ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'" @click="tab = item.id as typeof tab">{{ item.label }}</UiButton>
      </div>
      <div v-show="tab === 'products'" id="import-panel-products" role="tabpanel" aria-labelledby="import-tab-products" class="space-y-5 py-5 sm:py-6">
        <fieldset :disabled="busy || downloading" class="grid gap-3 rounded-xl border border-gray-200 p-4 sm:grid-cols-2 sm:p-5">
          <legend class="px-1 text-base font-semibold text-gray-900">1. Выберите сценарий</legend>
          <UiRadio v-model="importMode" name="product-import-mode" value="template"><span><span class="block font-semibold text-gray-900">Добавить товары</span><span class="mt-1 block text-gray-600">Создайте товары по шаблону выбранной категории.</span></span></UiRadio>
          <UiRadio v-model="importMode" name="product-import-mode" value="edit"><span><span class="block font-semibold text-gray-900">Редактировать товары</span><span class="mt-1 block text-gray-600">Скачайте товары категории с SKU и списками характеристик.</span></span></UiRadio>
        </fieldset>
        <div>
          <h3 class="text-base font-semibold text-gray-900">2. Подготовьте шаблон</h3>
          <p class="mt-1 text-sm text-gray-500">{{ importMode === 'template' ? 'До 5 000 товаров в одном файле. Шаблон содержит характеристики выбранной категории.' : 'Файл содержит все товары выбранной категории, их SKU и характеристики. SKU определяет редактируемый товар.' }}</p>
          <UiLoadingState v-if="categoriesLoading" class="mt-3" label="Загружаем категории…" />
          <UiAlert v-else-if="categoryError" class="mt-3">{{ categoryError }} <UiButton type="button" variant="danger-ghost" size="sm" @click="loadCategories">Повторить</UiButton></UiAlert>
          <UiEmptyState v-else-if="!categoryOptions.length" class="mt-3" label="Категорий пока нет. Сначала создайте категорию в каталоге." />
          <fieldset v-else :disabled="busy || downloading" class="mt-4 grid min-w-0 gap-3 sm:grid-cols-2">
            <UiField class="min-w-0" label="Категория товаров"><UiSelect v-model="categoryId" class="mt-1.5" :options="categoryOptions" accessible-name="Категория товаров для загрузки" placeholder="Выберите категорию" searchable /></UiField>
            <UiButton type="button" variant="secondary" class="self-end" :loading="downloading" :disabled="!categoryId || downloading" @click="downloadTemplate()"><Download :size="18" class="shrink-0" aria-hidden="true" />{{ downloading ? 'Скачиваем…' : 'Скачать шаблон Excel' }}</UiButton>
          </fieldset>
          <p class="mt-3 text-sm leading-6 text-gray-500">{{ importMode === 'template' ? 'SKU присваивается автоматически. Slug можно оставить пустым — он создастся из наименования.' : 'Не изменяйте SKU и не добавляйте строки: редактируются только товары выбранной категории.' }} Значения списков выбирайте в ячейках Excel; новые значения добавляются на сайте с соответствующими правами.</p>
        </div>
        <form class="rounded-xl border border-gray-200 p-4 sm:p-5" @submit.prevent="upload">
          <h3 class="text-base font-semibold text-gray-900">3. Загрузите заполненный файл</h3>
          <p id="product-import-file-help" class="mt-1 text-sm text-gray-500">XLSX, до 10 МБ. Корректные товары сохранятся, строки с ошибками можно будет исправить и загрузить повторно.</p>
          <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
            <div class="min-w-0"><p id="product-import-file-label" class="mb-1.5 text-sm font-medium text-gray-700">Заполненный шаблон</p><input id="product-import-file" ref="input" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" aria-label="Заполненный шаблон" tabindex="-1" :disabled="busy || !categoryId" class="hidden" @change="selectFile"><UiButton type="button" variant="secondary" class="w-full min-w-0 justify-start text-left" :disabled="busy || !categoryId" aria-labelledby="product-import-file-label product-import-file-selection" aria-describedby="product-import-file-help" @click="input?.click()"><Upload :size="18" class="shrink-0" aria-hidden="true" /><span id="product-import-file-selection" class="min-w-0 break-all">{{ file?.name ?? 'Выбрать файл XLSX' }}</span></UiButton></div>
            <UiButton type="submit" :loading="busy" :disabled="busy || !file || !categoryId"><Upload :size="18" aria-hidden="true" />{{ busy ? 'Загрузка…' : 'Загрузить' }}</UiButton>
          </div>
        </form>
        <UiAlert v-if="error">{{ error }}</UiAlert>
        <UiAlert v-if="notice" tone="success" live="polite">{{ notice }}</UiAlert>
        <div class="min-h-32 rounded-xl border p-4 sm:p-5" :class="finished ? result?.failed_rows || result?.status === 'failed' ? 'border-warning-200 bg-warning-50' : 'border-success-200 bg-success-50' : 'border-gray-200 bg-gray-25'">
          <template v-if="finished && result">
            <div role="status" aria-live="polite">
              <h3 class="flex items-center gap-2 font-semibold text-gray-900"><CheckCircle2 v-if="!result.failed_rows && result.status === 'completed'" :size="20" class="shrink-0 text-success-700" aria-hidden="true" />{{ result.status === 'failed' ? 'Загрузка не завершена' : result.failed_rows ? 'Загрузка завершена с ошибками' : 'Загрузка завершена' }}</h3>
              <p class="mt-2 text-sm text-gray-700">Успешно: {{ successful }}. С ошибками: {{ result.failed_rows ?? 0 }}.</p>
              <p v-if="result.error_message" class="mt-2 break-words text-sm text-error-500">{{ result.error_message }}</p>
            </div>
            <ul v-if="result.row_errors?.length" class="mt-4 max-h-64 space-y-3 overflow-y-auto" aria-label="Ошибки товаров" tabindex="0">
              <li v-for="entry in result.row_errors" :key="entry.row" class="break-words rounded-lg border border-warning-200 bg-white p-3 text-sm"><p class="font-semibold text-gray-900">{{ entry.name || 'Без наименования' }} <span class="font-normal text-gray-500">· строка {{ entry.row }}</span></p><p v-for="message in entry.messages" :key="message" class="mt-1 text-gray-700">{{ message }}</p></li>
            </ul>
            <UiButton v-if="result.has_error_file" type="button" variant="secondary" class="mt-4" :loading="downloading" :disabled="downloading" @click="downloadTemplate(true)"><Download :size="18" class="shrink-0" aria-hidden="true" />Скачать Excel с ошибками</UiButton>
          </template>
          <template v-else>
            <p role="status" aria-live="polite" class="text-sm text-gray-700">{{ statusText }}</p>
            <progress v-if="busy" class="mt-4 h-2 w-full accent-primary-500" aria-label="Обработка товаров" :value="progress" max="100" />
            <div v-else class="mt-4 h-2 rounded-full bg-gray-200" aria-hidden="true" />
            <p v-if="busy" class="mt-3 text-xs text-gray-500">Можно закрыть окно — обработка продолжится. Откройте «Загрузить массово», чтобы посмотреть результат.</p>
            <UiAlert v-if="pollingError && result" class="mt-3">Не удалось получить статус. Обработка на сервере продолжается. <UiButton type="button" variant="danger-ghost" size="sm" @click="poll(result.id)">Обновить статус</UiButton></UiAlert>
          </template>
        </div>
      </div>
      <div v-show="tab === 'images'" id="import-panel-images" role="tabpanel" aria-labelledby="import-tab-images" class="space-y-5 py-5 sm:py-6">
        <section class="rounded-xl border border-gray-200 p-4 sm:p-5" aria-labelledby="image-import-preparation-title">
          <div class="flex items-start gap-3">
            <ImagePlus :size="24" class="mt-0.5 shrink-0 text-primary-500" aria-hidden="true" />
            <div class="min-w-0">
              <h3 id="image-import-preparation-title" class="text-base font-semibold text-gray-900">Подготовьте ZIP-архив</h3>
              <p class="mt-1 text-sm leading-6 text-gray-600">Для каждого уже созданного товара создайте папку с его SKU. Внутри назовите файлы по схеме <code class="rounded bg-gray-100 px-1 py-0.5 text-gray-700">SKU_номер.расширение</code>.</p>
            </div>
          </div>
          <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 bg-gray-25 p-3 text-sm text-gray-700" tabindex="0" aria-label="Пример структуры ZIP-архива">
            <pre class="min-w-max font-mono leading-6">images.zip
└── 6000011/
    ├── 6000011_1.jpg
    ├── 6000011_2.webp
    └── 6000011_3.png</pre>
          </div>
          <ul class="mt-4 space-y-2 text-sm leading-6 text-gray-600">
            <li>Поддерживаются JPG, PNG и WebP; ZIP — до 500 МБ.</li>
            <li>Имя папки и начало имени каждого файла должны совпадать со SKU товара.</li>
            <li>Фото с номером <code class="rounded bg-gray-100 px-1 py-0.5 text-gray-700">_1</code> станет обложкой. При том же номере новое фото заменит старое, а отсутствующие в архиве фото сохранятся.</li>
          </ul>
        </section>

        <form class="rounded-xl border border-gray-200 p-4 sm:p-5" @submit.prevent="uploadImageImport">
          <h3 class="text-base font-semibold text-gray-900">Загрузите архив</h3>
          <p id="image-import-file-help" class="mt-1 text-sm text-gray-500">Обработка выполняется в фоне. Ошибка одной папки не помешает импортировать остальные товары.</p>
          <div class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
            <div class="min-w-0">
              <p id="image-import-file-label" class="mb-1.5 text-sm font-medium text-gray-700">ZIP-архив с изображениями</p>
              <input id="image-import-file" ref="imageInput" type="file" accept=".zip,application/zip" class="hidden" :disabled="imageBusy" @change="selectImageFile">
              <UiButton type="button" variant="secondary" class="w-full min-w-0 justify-start text-left" :disabled="imageBusy" aria-labelledby="image-import-file-label image-import-file-selection" aria-describedby="image-import-file-help" @click="imageInput?.click()">
                <Upload :size="18" class="shrink-0" aria-hidden="true" />
                <span id="image-import-file-selection" class="min-w-0 break-all">{{ imageFile?.name ?? 'Выбрать ZIP-архив' }}</span>
              </UiButton>
            </div>
            <UiButton type="submit" :loading="imageBusy" :disabled="imageBusy || !imageFile"><Upload :size="18" aria-hidden="true" />{{ imageBusy ? 'Загрузка…' : 'Загрузить архив' }}</UiButton>
          </div>
        </form>

        <UiAlert v-if="imageError">{{ imageError }}</UiAlert>

        <section class="min-h-32 rounded-xl border p-4 sm:p-5" :class="imageFinished ? imageResult?.failed_folders || imageResult?.status === 'failed' ? 'border-warning-200 bg-warning-50' : 'border-success-200 bg-success-50' : 'border-gray-200 bg-gray-25'" aria-labelledby="image-import-result-title">
          <template v-if="imageFinished && imageResult">
            <div role="status" aria-live="polite">
              <h3 id="image-import-result-title" class="flex items-center gap-2 font-semibold text-gray-900"><CheckCircle2 v-if="!imageResult.failed_folders && imageResult.status === 'completed'" :size="20" class="shrink-0 text-success-700" aria-hidden="true" />{{ imageResult.status === 'failed' ? 'Загрузка не завершена' : imageResult.failed_folders ? 'Загрузка завершена с ошибками' : 'Загрузка завершена' }}</h3>
              <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                <div><dt class="text-gray-500">Папок обработано</dt><dd class="mt-1 font-semibold text-gray-900">{{ imageResult.processed_folders }} из {{ imageResult.total_folders }}</dd></div>
                <div><dt class="text-gray-500">Добавлено фото</dt><dd class="mt-1 font-semibold text-gray-900">{{ imageResult.created_images }}</dd></div>
                <div><dt class="text-gray-500">Заменено фото</dt><dd class="mt-1 font-semibold text-gray-900">{{ imageResult.replaced_images }}</dd></div>
                <div><dt class="text-gray-500">Папок с ошибками</dt><dd class="mt-1 font-semibold text-gray-900">{{ imageResult.failed_folders }}</dd></div>
              </dl>
              <p v-if="imageResult.error_message" class="mt-3 break-words text-sm text-error-500">{{ imageResult.error_message }}</p>
            </div>
            <ul v-if="imageResult.errors?.length" class="mt-4 max-h-64 space-y-3 overflow-y-auto" aria-label="Ошибки импорта изображений" tabindex="0">
              <li v-for="entry in imageResult.errors" :key="`${entry.sku}-${entry.entry}`" class="break-words rounded-lg border border-warning-200 bg-white p-3 text-sm"><p class="font-semibold text-gray-900">{{ entry.sku || 'Без названия папки' }}<span v-if="entry.entry" class="font-normal text-gray-500"> · {{ entry.entry }}</span></p><p v-for="message in entry.messages" :key="message" class="mt-1 text-gray-700">{{ message }}</p></li>
            </ul>
            <UiButton v-if="imageResult.has_error_file" type="button" variant="secondary" class="mt-4" :loading="imageDownloading" :disabled="imageDownloading" @click="downloadImageErrors"><Download :size="18" class="shrink-0" aria-hidden="true" />{{ imageDownloading ? 'Скачиваем…' : 'Скачать отчёт с ошибками' }}</UiButton>
          </template>
          <template v-else>
            <h3 id="image-import-result-title" class="text-base font-semibold text-gray-900">Статус импорта</h3>
            <p role="status" aria-live="polite" class="mt-2 text-sm text-gray-700">{{ imageUploading ? 'Загружаем ZIP-архив…' : imageResult?.status === 'pending' ? 'Архив ожидает обработки…' : imageResult?.status === 'processing' ? imageResult.total_folders ? `Обработано ${imageResult.processed_folders} из ${imageResult.total_folders} папок` : 'Проверяем архив и импортируем изображения…' : 'Выберите подготовленный ZIP-архив, чтобы начать импорт.' }}</p>
            <progress v-if="imageBusy" class="mt-4 h-2 w-full accent-primary-500" aria-label="Обработка изображений" :value="imageProgress" max="100" />
            <div v-else class="mt-4 h-2 rounded-full bg-gray-200" aria-hidden="true" />
            <p v-if="imageBusy" class="mt-3 text-xs text-gray-500">Можно закрыть окно — обработка продолжится. Откройте «Загрузить массово», чтобы посмотреть результат.</p>
            <UiAlert v-if="imagePollingError && imageResult" class="mt-3">Не удалось получить статус. Обработка на сервере продолжается. <UiButton type="button" variant="danger-ghost" size="sm" @click="pollImageImport(imageResult.id)">Обновить статус</UiButton></UiAlert>
          </template>
        </section>
      </div>
    </div>
  </UiDialog>
</template>
