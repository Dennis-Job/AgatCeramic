<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { CheckCircle2, Download, ImagePlus, Upload, X } from '@lucide/vue'
import BaseDialog from './BaseDialog.vue'
import BaseSelect from './BaseSelect.vue'
import { getCategories, type Category } from '../services/categories'
import { getProductImport, getProductImportErrors, getProductImportTemplate, uploadProductImport, type ProductImport } from '../services/products'
import { compareAlphabetically } from '../utils/alphabetical'

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: []; completed: [] }>()
const tab = ref<'products' | 'images'>('products')
const categories = ref<Category[]>([])
const categoryId = ref('')
const categoriesLoading = ref(false)
const categoryError = ref('')
const file = ref<File | null>(null)
const input = ref<HTMLInputElement | null>(null)
const result = ref<ProductImport | null>(null)
const uploading = ref(false)
const downloading = ref(false)
const error = ref('')
const notice = ref('')
const pollingError = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined
let disposed = false
const busy = computed(() => uploading.value || result.value?.status === 'pending' || result.value?.status === 'processing')
const finished = computed(() => result.value?.status === 'completed' || result.value?.status === 'failed')
const successful = computed(() => (result.value?.created_rows ?? 0) + (result.value?.updated_rows ?? 0))
const progress = computed(() => result.value?.total_rows ? Math.min(100, Math.round(result.value.processed_rows / result.value.total_rows * 100)) : undefined)
function flatten(nodes: Category[], depth = 0): { value: string; label: string }[] {
  return [...nodes].sort((a, b) => compareAlphabetically(a.name, b.name)).flatMap(item => [
    { value: String(item.id), label: `${'— '.repeat(depth)}${item.name}${item.is_active ? '' : ' (скрыта)'}` },
    ...flatten(item.children ?? [], depth + 1),
  ])
}
const categoryOptions = computed(() => flatten(categories.value))
const statusText = computed(() => {
  if (uploading.value) return 'Загружаем XLSX-файл…'
  if (result.value?.status === 'pending') return 'Файл ожидает обработки…'
  if (result.value?.status === 'processing') return result.value.total_rows
    ? `Обработано ${result.value.processed_rows} из ${result.value.total_rows} товаров`
    : 'Проверяем файл и импортируем товары…'
  return 'Выберите категорию, скачайте и заполните шаблон, затем загрузите файл.'
})
async function loadCategories() {
  categoriesLoading.value = true
  categoryError.value = ''
  try { categories.value = await getCategories() }
  catch (reason) { categoryError.value = reason instanceof Error ? reason.message : 'Не удалось загрузить категории.' }
  finally { categoriesLoading.value = false }
}
watch(() => props.open, open => { if (open && !categories.value.length) void loadCategories() })
watch([busy, downloading], async () => {
  await nextTick()
  if (props.open && tab.value === 'products' && (document.activeElement === document.body || document.activeElement?.matches(':disabled'))) {
    document.getElementById('import-tab-products')?.focus()
  }
})
watch(categoryId, () => {
  file.value = null
  if (input.value) input.value.value = ''
  error.value = ''; notice.value = ''
})
function selectFile(event: Event) {
  file.value = (event.target as HTMLInputElement).files?.[0] ?? null
  error.value = ''; notice.value = ''
  if (finished.value) result.value = null
}
function saveDownload(download: { blob: Blob; filename: string }) {
  const url = URL.createObjectURL(download.blob)
  const link = document.createElement('a')
  link.href = url; link.download = download.filename
  document.body.appendChild(link); link.click(); link.remove()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}
async function downloadTemplate(errorsOnly = false) {
  if (downloading.value) return
  downloading.value = true; error.value = ''; notice.value = ''
  try {
    saveDownload(errorsOnly && result.value ? await getProductImportErrors(result.value.id) : await getProductImportTemplate(Number(categoryId.value)))
    notice.value = errorsOnly ? 'Файл с ошибочными товарами скачан. Исправьте ошибки и загрузите его повторно.' : 'Шаблон Excel скачан. Заполните его и прикрепите ниже.'
  } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось скачать файл.' }
  finally { downloading.value = false }
}
async function poll(id: number) {
  if (disposed) return
  pollingError.value = false
  try {
    const current = await getProductImport(id)
    if (disposed) return
    result.value = current
    if (current.status === 'completed' || current.status === 'failed') { emit('completed'); return }
    timer = setTimeout(() => { void poll(id) }, 1500)
  } catch { if (!disposed) pollingError.value = true }
}
async function upload() {
  if (!file.value || !categoryId.value || busy.value) return
  error.value = ''; notice.value = ''
  if (!file.value.name.toLowerCase().endsWith('.xlsx') || file.value.size > 10 * 1024 * 1024) {
    error.value = 'Прикрепите файл XLSX размером не более 10 МБ.'; return
  }
  uploading.value = true; result.value = null
  try {
    result.value = await uploadProductImport(file.value, Number(categoryId.value))
    void poll(result.value.id)
  } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось загрузить XLSX-файл.' }
  finally { uploading.value = false }
}
function changeTab(event: KeyboardEvent) {
  if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return
  event.preventDefault()
  tab.value = event.key === 'Home' ? 'products' : event.key === 'End' ? 'images' : tab.value === 'products' ? 'images' : 'products'
  void nextTick(() => document.getElementById(`import-tab-${tab.value}`)?.focus())
}
onBeforeUnmount(() => { disposed = true; if (timer) clearTimeout(timer) })
</script>

<template>
  <BaseDialog :open="open" labelledby="product-import-title" describedby="product-import-description" panel-class="flex w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-card" @close="emit('close')">
    <header class="flex shrink-0 items-start justify-between gap-4 border-b border-gray-200 p-5 sm:px-7">
      <div class="min-w-0">
        <h2 id="product-import-title" class="text-xl font-bold text-gray-900">Массовая загрузка</h2>
        <p id="product-import-description" class="mt-1 text-sm text-gray-500">Добавление товаров из шаблона Excel</p>
      </div>
      <button type="button" class="shrink-0 rounded-lg p-2 text-gray-500 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500" aria-label="Закрыть массовую загрузку" @click="emit('close')"><X :size="20" aria-hidden="true" /></button>
    </header>
    <div class="min-h-0 overflow-y-auto overscroll-contain px-5 sm:px-7">
      <div class="grid grid-cols-2 gap-2 border-b border-gray-200" role="tablist" aria-label="Тип загрузки" @keydown="changeTab">
        <button v-for="item in [{ id: 'products', label: 'Загрузка товаров' }, { id: 'images', label: 'Загрузка изображений' }]" :id="`import-tab-${item.id}`" :key="item.id" type="button" role="tab" :aria-selected="tab === item.id" :aria-controls="`import-panel-${item.id}`" :tabindex="tab === item.id ? 0 : -1" class="border-b-2 px-2 py-4 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500" :class="tab === item.id ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'" @click="tab = item.id as typeof tab">{{ item.label }}</button>
      </div>
      <div v-show="tab === 'products'" id="import-panel-products" role="tabpanel" aria-labelledby="import-tab-products" class="space-y-5 py-5 sm:py-6">
        <div>
          <h3 class="text-base font-semibold text-gray-900">1. Подготовьте шаблон</h3>
          <p class="mt-1 text-sm text-gray-500">До 5 000 товаров в одном файле. Шаблон содержит характеристики выбранной категории.</p>
          <p v-if="categoriesLoading" class="mt-3 text-sm text-gray-500" role="status">Загружаем категории…</p>
          <div v-else-if="categoryError" class="mt-3 text-sm text-error-500" role="alert">{{ categoryError }} <button type="button" class="rounded font-semibold underline focus-visible:ring-2 focus-visible:ring-primary-500" @click="loadCategories">Повторить</button></div>
          <p v-else-if="!categoryOptions.length" class="mt-3 text-sm text-gray-500">Категорий пока нет. Сначала создайте категорию в каталоге.</p>
          <fieldset v-else :disabled="busy || downloading" class="mt-4 grid min-w-0 gap-3 sm:grid-cols-2 disabled:opacity-60">
            <div class="min-w-0"><p class="mb-1.5 text-sm font-medium text-gray-700">Категория товаров</p><BaseSelect v-model="categoryId" :options="categoryOptions" accessible-name="Категория товаров для загрузки" placeholder="Выберите категорию" searchable /></div>
            <button type="button" :disabled="!categoryId" class="inline-flex items-center justify-center gap-2 self-end rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-50" @click="downloadTemplate()"><Download :size="18" class="shrink-0" aria-hidden="true" />{{ downloading ? 'Скачиваем…' : 'Скачать шаблон Excel' }}</button>
          </fieldset>
          <p class="mt-3 text-sm leading-6 text-gray-500">SKU присваивается автоматически. Slug можно оставить пустым — он создастся из наименования. Значения списков выбирайте в ячейках Excel; новые значения добавляются на сайте с соответствующими правами.</p>
        </div>
        <form class="rounded-xl border border-gray-200 p-4 sm:p-5" @submit.prevent="upload">
          <h3 class="text-base font-semibold text-gray-900">2. Загрузите заполненный файл</h3>
          <p id="product-import-file-help" class="mt-1 text-sm text-gray-500">XLSX, до 10 МБ. Корректные товары сохранятся, строки с ошибками можно будет исправить и загрузить повторно.</p>
          <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
            <div class="min-w-0"><p id="product-import-file-label" class="mb-1.5 text-sm font-medium text-gray-700">Заполненный шаблон</p><input id="product-import-file" ref="input" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" aria-label="Заполненный шаблон" tabindex="-1" :disabled="busy || !categoryId" class="hidden" @change="selectFile"><button type="button" :disabled="busy || !categoryId" aria-labelledby="product-import-file-label product-import-file-selection" aria-describedby="product-import-file-help" class="flex w-full min-w-0 items-center gap-3 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-left text-sm text-gray-600 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:opacity-50" @click="input?.click()"><Upload :size="18" class="shrink-0" aria-hidden="true" /><span id="product-import-file-selection" class="min-w-0 break-all">{{ file?.name ?? 'Выбрать файл XLSX' }}</span></button></div>
            <button type="submit" :disabled="busy || !file || !categoryId" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-500 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"><Upload :size="18" aria-hidden="true" />{{ busy ? 'Загрузка…' : 'Загрузить' }}</button>
          </div>
        </form>
        <p v-if="error" role="alert" class="rounded-lg border border-error-200 bg-error-50 p-4 text-sm text-error-500">{{ error }}</p>
        <p v-if="notice" role="status" class="text-sm text-success-700">{{ notice }}</p>
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
            <button v-if="result.has_error_file" type="button" :disabled="downloading" class="mt-4 inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:opacity-50" @click="downloadTemplate(true)"><Download :size="18" class="shrink-0" aria-hidden="true" />Скачать Excel с ошибками</button>
          </template>
          <template v-else>
            <p role="status" aria-live="polite" class="text-sm text-gray-700">{{ statusText }}</p>
            <progress v-if="busy" class="mt-4 h-2 w-full accent-primary-500" aria-label="Обработка товаров" :value="progress" max="100" />
            <div v-else class="mt-4 h-2 rounded-full bg-gray-200" aria-hidden="true" />
            <p v-if="busy" class="mt-3 text-xs text-gray-500">Можно закрыть окно — обработка продолжится. Откройте «Загрузить массово», чтобы посмотреть результат.</p>
            <p v-if="pollingError && result" class="mt-3 text-sm text-error-500" role="alert">Не удалось получить статус. Обработка на сервере продолжается. <button type="button" class="rounded font-semibold underline focus-visible:ring-2 focus-visible:ring-primary-500" @click="poll(result.id)">Обновить статус</button></p>
          </template>
        </div>
      </div>
      <div v-show="tab === 'images'" id="import-panel-images" role="tabpanel" aria-labelledby="import-tab-images" class="py-16 text-center">
        <ImagePlus :size="36" class="mx-auto text-gray-400" aria-hidden="true" />
        <h3 class="mt-4 text-lg font-semibold text-gray-900">Загрузка изображений</h3>
        <p class="mt-2 text-sm text-gray-500">Массовая загрузка изображений появится позже.</p>
      </div>
    </div>
  </BaseDialog>
</template>
